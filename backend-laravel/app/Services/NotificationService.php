<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EventLogger;
use App\Contracts\NotificationProvider;
use App\Contracts\NotificationStore;
use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Sends a notification with BOUNDED retries (see docs/retry-recovery-policy.md).
 *
 *  - Transient failure (timeout, 503)  -> wait, then retry, up to maxAttempts
 *  - Permanent failure (rejected)      -> stop immediately, route to manual review
 *  - Retries used up                   -> route to manual review
 *  - Same notification requested twice -> skipped (idempotency key), no duplicate sent
 *
 * A failed notification never touches hour records: this class only knows about notifications.
 */
final class NotificationService
{
    /** @var callable(int): void */
    private $sleep;

    /**
     * @param list<int> $delaysMs wait before retry 2, retry 3, ... (the last value repeats)
     * @param ?callable(int): void $sleep receives milliseconds (tests pass a fake)
     */
    public function __construct(
        private readonly NotificationProvider $provider,
        private readonly NotificationStore $store,
        private readonly EventLogger $logger,
        private readonly int $maxAttempts = 3,
        private readonly array $delaysMs = [60000, 300000],
        ?callable $sleep = null,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be a whole number of 1 or more');
        }
        if ($delaysMs === []) {
            throw new InvalidArgumentException('delaysMs needs at least one value');
        }
        $this->sleep = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /** type + studentId + referenceId + date (one notification of a kind per day) */
    public static function buildKey(string $type, int $studentId, string $referenceId, DateTimeImmutable $now): string
    {
        return implode(':', [$type, $studentId, $referenceId, $now->format('Y-m-d')]);
    }

    /**
     * @param array{type: string, studentId: int, referenceId: string, message?: ?string} $data
     * @return array{outcome: 'SENT'|'MANUAL_REVIEW'|'DUPLICATE', notification: array}
     */
    public function send(array $data, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        $type = $data['type'];

        $made = $this->store->createIfNew([
            'key' => self::buildKey($type, $data['studentId'], $data['referenceId'], $now),
            'type' => $type,
            'studentId' => $data['studentId'],
            'referenceId' => $data['referenceId'],
            'message' => $data['message'] ?? null,
            'status' => 'PENDING',
        ]);

        if (! $made['created']) {
            $this->logger->info('notification_duplicate_skipped', [
                'notificationId' => $made['notification']['id'],
                'type' => $type,
            ]);

            return ['outcome' => 'DUPLICATE', 'notification' => $made['notification']];
        }

        $id = $made['notification']['id'];

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $this->provider->send($made['notification']);
                $this->store->update($id, ['status' => 'SENT', 'attempts' => $attempt, 'lastError' => null, 'nextAttemptAt' => null]);
                $this->logger->info('notification_sent', ['notificationId' => $id, 'type' => $type, 'attempt' => $attempt]);

                return ['outcome' => 'SENT', 'notification' => $this->store->get($id)];
            } catch (Throwable $e) {
                $reason = $e instanceof ProviderException ? $e->reason : 'UNKNOWN_ERROR';
                $permanent = $e instanceof ProviderException && $e->transient === false;
                $outOfAttempts = $attempt === $this->maxAttempts;

                if ($permanent || $outOfAttempts) {
                    $this->store->update($id, ['status' => 'MANUAL_REVIEW', 'attempts' => $attempt, 'lastError' => $reason, 'nextAttemptAt' => null]);
                    $this->logger->error('notification_routed_manual_review', [
                        'notificationId' => $id,
                        'type' => $type,
                        'attempts' => $attempt,
                        'reason' => $reason,
                        'permanent' => $permanent,
                    ]);

                    return ['outcome' => 'MANUAL_REVIEW', 'notification' => $this->store->get($id)];
                }

                $delay = $this->delaysMs[min($attempt - 1, count($this->delaysMs) - 1)];
                $next = $now->add(new DateInterval('PT' . (int) ceil($delay / 1000) . 'S'))->format('Y-m-d H:i:s');
                $this->store->update($id, ['status' => 'RETRYING', 'attempts' => $attempt, 'lastError' => $reason, 'nextAttemptAt' => $next]);
                $this->logger->warn('notification_attempt_failed', [
                    'notificationId' => $id,
                    'type' => $type,
                    'attempt' => $attempt,
                    'maxAttempts' => $this->maxAttempts,
                    'reason' => $reason,
                    'nextAttemptAt' => $next,
                ]);
                ($this->sleep)($delay);
            }
        }

        // Not reachable (the loop always returns). Kept as a safety net.
        throw new \LogicException('send() finished without a result');
    }
}
