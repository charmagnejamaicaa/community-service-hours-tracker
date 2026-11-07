<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\JobRepository;
use App\Repositories\MemoryNotificationStore;
use App\Services\DailyJob;
use App\Services\FakeProvider;
use App\Services\ProgressSummary;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\CollectingLogger;

final class DailyJobTest extends TestCase
{
    private function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-05 08:00:00', new DateTimeZone('UTC'));
    }

    private function repo(array $students, array $stale = []): JobRepository
    {
        return new class($students, $stale) implements JobRepository {
            public function __construct(private array $students, private array $stale)
            {
            }

            public function listStudentsWithHours(): array
            {
                return $this->students;
            }

            public function listStaleSubmissions(int $days): array
            {
                return $this->stale;
            }
        };
    }

    private function student(int $id, float $approved, ?string $deadline): array
    {
        return ['id' => $id, 'requiredHours' => 40.0, 'approvedHours' => $approved, 'pendingHours' => 0.0, 'deadline' => $deadline];
    }

    private function job(JobRepository $repo, string $mode, MemoryNotificationStore $store, CollectingLogger $logger): DailyJob
    {
        return new DailyJob(
            $repo,
            $store,
            new FakeProvider($mode),
            $logger,
            ['reminderDaysBeforeDeadline' => 7, 'verificationAlertDays' => 3, 'maxAttempts' => 3, 'delaysMs' => [1, 1]],
            static function (int $ms): void {
            }
        );
    }

    public function test_reminds_only_students_who_are_short_and_close_to_the_deadline(): void
    {
        $repo = $this->repo([
            $this->student(1, 10.0, '2026-11-10'),  // 5 days left, short -> reminder
            $this->student(2, 10.0, '2026-12-30'),  // far away -> no
            $this->student(3, 40.0, '2026-11-10'),  // completed -> no
            $this->student(4, 10.0, '2026-11-01'),  // overdue -> no
            $this->student(5, 10.0, null),          // no deadline -> no
        ]);
        $result = $this->job($repo, 'ok', new MemoryNotificationStore(), new CollectingLogger())->run($this->today());
        $this->assertSame(1, $result['deadlineReminders']['considered']);
        $this->assertSame(1, $result['deadlineReminders']['sent']);
    }

    public function test_alerts_for_stale_submissions(): void
    {
        $repo = $this->repo([], [['id' => 4, 'studentId' => 2], ['id' => 9, 'studentId' => 3]]);
        $result = $this->job($repo, 'ok', new MemoryNotificationStore(), new CollectingLogger())->run($this->today());
        $this->assertSame(2, $result['verificationAlerts']['sent']);
    }

    public function test_running_twice_on_the_same_day_sends_no_duplicates(): void
    {
        $repo = $this->repo([$this->student(1, 10.0, '2026-11-10')]);
        $store = new MemoryNotificationStore();
        $job = $this->job($repo, 'ok', $store, new CollectingLogger());
        $job->run($this->today());
        $second = $job->run($this->today());
        $this->assertSame(0, $second['deadlineReminders']['sent']);
        $this->assertSame(1, $second['deadlineReminders']['duplicates']);
    }

    public function test_a_failing_provider_sends_items_to_manual_review_without_stopping_the_job(): void
    {
        $repo = $this->repo(
            [$this->student(1, 10.0, '2026-11-10')],
            [['id' => 4, 'studentId' => 2]]
        );
        $store = new MemoryNotificationStore();
        $result = $this->job($repo, 'reject', $store, new CollectingLogger())->run($this->today());
        $this->assertSame(1, $result['deadlineReminders']['manualReview']);
        $this->assertSame(1, $result['verificationAlerts']['manualReview']);
        $this->assertCount(2, $store->listByStatus('MANUAL_REVIEW'));
    }

    public function test_the_summary_counts_students_by_status(): void
    {
        $summary = ProgressSummary::build([
            $this->student(1, 10.0, '2026-11-10'),
            $this->student(2, 40.0, '2026-11-10'),
            $this->student(3, 5.0, '2026-11-01'),
        ], $this->today());
        $this->assertSame(3, $summary['totals']['students']);
        $this->assertSame(1, $summary['totals']['completed']);
        $this->assertSame(1, $summary['totals']['inProgress']);
        $this->assertSame(1, $summary['totals']['overdue']);
        $this->assertSame(55.0, $summary['totals']['approvedHours']);
        $this->assertSame(65.0, $summary['totals']['remainingHours']);
    }

    public function test_the_summary_has_ids_and_numbers_but_no_names(): void
    {
        $summary = ProgressSummary::build([$this->student(1, 10.0, '2026-11-10')], $this->today());
        $this->assertArrayNotHasKey('name', $summary['students'][0]);
        $this->assertSame(1, $summary['students'][0]['studentId']);
    }

    public function test_the_job_logs_a_progress_summary(): void
    {
        $logger = new CollectingLogger();
        $this->job($this->repo([$this->student(1, 10.0, '2026-11-10')]), 'ok', new MemoryNotificationStore(), $logger)->run($this->today());
        $this->assertContains('progress_summary', $logger->names());
    }
}
