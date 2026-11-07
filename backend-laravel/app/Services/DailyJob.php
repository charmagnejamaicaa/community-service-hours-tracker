<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EventLogger;
use App\Contracts\JobRepository;
use App\Contracts\NotificationProvider;
use App\Contracts\NotificationStore;
use App\Support\HoursRules;
use DateTimeImmutable;
use Throwable;

/**
 * The daily automation job. It decides WHO gets a notification and hands each one to
 * NotificationService (retries, logs, manual review).
 *
 *  1. Deadline reminders      - students still short on hours whose deadline is close
 *  2. Missing-verification    - submissions that have waited too long for a supervisor
 *  3. Progress summary        - counts per status, written to the log and returned
 *
 * One bad item never stops the others. Running the job twice on the same day does not send
 * duplicates (the notification key includes the date).
 */
final class DailyJob
{
    /** @var callable(int): void|null */
    private $sleep;

    /**
     * @param array{reminderDaysBeforeDeadline:int, verificationAlertDays:int, maxAttempts:int, delaysMs:list<int>} $config
     * @param ?callable(int): void $sleep
     */
    public function __construct(
        private readonly JobRepository $repo,
        private readonly NotificationStore $store,
        private readonly NotificationProvider $provider,
        private readonly EventLogger $logger,
        private readonly array $config,
        ?callable $sleep = null,
    ) {
        $this->sleep = $sleep;
    }

    public function run(?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('now');

        $service = new NotificationService(
            $this->provider,
            $this->store,
            $this->logger,
            $this->config['maxAttempts'],
            $this->config['delaysMs'],
            $this->sleep,
        );

        $students = $this->repo->listStudentsWithHours();

        // 1. Deadline reminders
        $reminderTargets = [];
        foreach ($students as $student) {
            $progress = HoursRules::computeProgress([
                'requiredHours' => $student['requiredHours'],
                'approvedHours' => $student['approvedHours'],
                'pendingHours' => $student['pendingHours'],
                'deadline' => $student['deadline'],
            ], $today);

            $inWindow = $progress['daysLeft'] !== null
                && $progress['daysLeft'] >= 0
                && $progress['daysLeft'] <= $this->config['reminderDaysBeforeDeadline'];

            if ($progress['status'] === 'IN_PROGRESS' && $inWindow) {
                $reminderTargets[] = [
                    'studentId' => $student['id'],
                    'referenceId' => 'deadline-' . substr((string) $student['deadline'], 0, 10),
                    'message' => $progress['remainingHours'] . ' hours remaining, ' . $progress['daysLeft'] . ' days left.',
                ];
            }
        }
        $deadlineReminders = $this->deliver($service, 'DEADLINE_REMINDER', $reminderTargets, $today);

        // 2. Missing-verification alerts
        $alertTargets = [];
        foreach ($this->repo->listStaleSubmissions($this->config['verificationAlertDays']) as $s) {
            $alertTargets[] = [
                'studentId' => $s['studentId'],
                'referenceId' => 'submission-' . $s['id'],
                'message' => 'A submission is waiting for verification.',
            ];
        }
        $verificationAlerts = $this->deliver($service, 'MISSING_VERIFICATION_ALERT', $alertTargets, $today);

        // 3. Progress summary (counts only)
        $summary = ProgressSummary::build($students, $today);
        $this->logger->info('progress_summary', $summary['totals']);

        return [
            'ranAt' => $today->format(DATE_ATOM),
            'deadlineReminders' => $deadlineReminders,
            'verificationAlerts' => $verificationAlerts,
            'summary' => $summary['totals'],
        ];
    }

    private function deliver(NotificationService $service, string $type, array $targets, DateTimeImmutable $today): array
    {
        $result = ['considered' => count($targets), 'sent' => 0, 'manualReview' => 0, 'duplicates' => 0, 'errors' => 0];

        foreach ($targets as $target) {
            try {
                $outcome = $service->send([
                    'type' => $type,
                    'studentId' => $target['studentId'],
                    'referenceId' => $target['referenceId'],
                    'message' => $target['message'],
                ], $today)['outcome'];

                if ($outcome === 'DUPLICATE') {
                    $result['duplicates']++;
                } elseif ($outcome === 'SENT') {
                    $result['sent']++;
                } elseif ($outcome === 'MANUAL_REVIEW') {
                    $result['manualReview']++;
                }
            } catch (Throwable $e) {
                $result['errors']++;
                $this->logger->error('job_item_failed', ['type' => $type, 'studentId' => $target['studentId'], 'reason' => $e::class]);
            }
        }

        return $result;
    }
}
