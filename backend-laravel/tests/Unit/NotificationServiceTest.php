<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MemoryNotificationStore;
use App\Services\FakeProvider;
use App\Services\NotificationService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\CollectingLogger;

final class NotificationServiceTest extends TestCase
{
    private MemoryNotificationStore $store;
    private CollectingLogger $logger;
    /** @var list<int> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->store = new MemoryNotificationStore();
        $this->logger = new CollectingLogger();
        $this->sleeps = [];
    }

    private function service(string $mode, int $max = 3): NotificationService
    {
        return new NotificationService(
            new FakeProvider($mode),
            $this->store,
            $this->logger,
            $max,
            [60000, 300000],
            function (int $ms): void {
                $this->sleeps[] = $ms;
            }
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-05 08:00:00', new DateTimeZone('UTC'));
    }

    private function request(string $ref = 'deadline-2026-12-15'): array
    {
        return ['type' => 'DEADLINE_REMINDER', 'studentId' => 1, 'referenceId' => $ref, 'message' => 'x'];
    }

    public function test_success_on_first_attempt(): void
    {
        $r = $this->service('ok')->send($this->request(), $this->now());
        $this->assertSame('SENT', $r['outcome']);
        $this->assertSame(1, $r['notification']['attempts']);
        $this->assertSame([], $this->sleeps);
    }

    public function test_transient_failure_recovers_on_second_attempt(): void
    {
        $r = $this->service('timeout_once')->send($this->request(), $this->now());
        $this->assertSame('SENT', $r['outcome']);
        $this->assertSame(2, $r['notification']['attempts']);
        $this->assertSame([60000], $this->sleeps);
        $this->assertContains('notification_attempt_failed', $this->logger->names());
        $this->assertContains('notification_sent', $this->logger->names());
    }

    public function test_retries_are_bounded_then_manual_review(): void
    {
        $r = $this->service('always_down')->send($this->request(), $this->now());
        $this->assertSame('MANUAL_REVIEW', $r['outcome']);
        $this->assertSame(3, $r['notification']['attempts']);
        $this->assertSame('PROVIDER_UNAVAILABLE', $r['notification']['lastError']);
        // waits 1 minute, then 5 minutes; no wait after the last attempt
        $this->assertSame([60000, 300000], $this->sleeps);
    }

    public function test_permanent_failure_goes_to_manual_review_immediately(): void
    {
        $r = $this->service('reject')->send($this->request(), $this->now());
        $this->assertSame('MANUAL_REVIEW', $r['outcome']);
        $this->assertSame(1, $r['notification']['attempts']);
        $this->assertSame('PROVIDER_REJECTED', $r['notification']['lastError']);
        $this->assertSame([], $this->sleeps);
    }

    public function test_same_notification_twice_is_not_sent_twice(): void
    {
        $provider = new FakeProvider('ok');
        $service = new NotificationService($provider, $this->store, $this->logger);
        $first = $service->send($this->request(), $this->now());
        $second = $service->send($this->request(), $this->now());
        $this->assertSame('SENT', $first['outcome']);
        $this->assertSame('DUPLICATE', $second['outcome']);
        $this->assertSame(1, $provider->calls());
    }

    public function test_the_same_notification_may_be_sent_again_on_another_day(): void
    {
        $service = $this->service('ok');
        $service->send($this->request(), $this->now());
        $next = $service->send($this->request(), $this->now()->modify('+1 day'));
        $this->assertSame('SENT', $next['outcome']);
    }

    public function test_manual_review_items_are_listed(): void
    {
        $this->service('reject')->send($this->request('a'), $this->now());
        $this->service('ok')->send($this->request('b'), $this->now());
        $this->assertCount(1, $this->store->listByStatus('MANUAL_REVIEW'));
    }

    public function test_logs_contain_ids_and_codes_but_not_the_message_text(): void
    {
        $this->service('reject')->send(['type' => 'T', 'studentId' => 1, 'referenceId' => 'r', 'message' => 'SECRET TEXT'], $this->now());
        $this->assertStringNotContainsString('SECRET TEXT', json_encode($this->logger->events));
    }

    public function test_max_attempts_must_be_at_least_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service('ok', 0);
    }
}
