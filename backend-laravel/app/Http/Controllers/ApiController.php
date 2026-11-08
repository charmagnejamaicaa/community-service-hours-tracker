<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\EventLogger;
use App\Contracts\JobRepository;
use App\Contracts\NotificationStore;
use App\Repositories\DbWorkflowRepository;
use App\Services\JobRunner;
use App\Services\ProgressSummary;
use App\Support\ApiError;
use App\Support\Access;
use App\Support\Clock;
use App\Support\HoursRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Health, progress, summary, daily job, and the manual review queue. */
final class ApiController
{
    public function __construct(
        private readonly EventLogger $logger,
        private readonly DbWorkflowRepository $workflow,
        private readonly JobRepository $jobs,
        private readonly NotificationStore $notifications,
        private readonly JobRunner $runner,
    ) {
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'time' => gmdate('Y-m-d\TH:i:s\Z')]);
    }

    /** Student progress: required, approved, pending, remaining hours. */
    public function studentProgress(Request $request, string $id): JsonResponse
    {
        $studentId = ApiError::parseId($id);
        if ($studentId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Student id must be a positive integer.');
        }

        // Access control: a student may only see their own progress.
        $user = $request->user();
        $own = $user->student_id === null ? null : (int) $user->student_id;
        if (! Access::canViewStudent($user->role, $own, $studentId)) {
            $this->logger->warn('access_denied', [
                'referenceId' => ApiError::refId($request),
                'userId' => $user->id,
                'role' => $user->role,
                'resource' => 'student_progress',
                'targetStudentId' => $studentId,
            ]);

            return ApiError::response($request, 403, 'FORBIDDEN', 'You can only view your own progress.');
        }

        $row = $this->workflow->getStudentHours($studentId);
        if ($row === null) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Student not found.');
        }

        $progress = HoursRules::computeProgress($row, Clock::now());
        $this->logger->info('progress_calculated', [
            'referenceId' => ApiError::refId($request),
            'studentId' => $studentId,
            'status' => $progress['status'],
        ]);

        return response()->json(['studentId' => $studentId] + $progress + ['deadline' => $row['deadline']]);
    }

    /** Progress summary for all students (coordinator / adviser). */
    public function progressSummary(): JsonResponse
    {
        return response()->json(ProgressSummary::build($this->jobs->listStudentsWithHours(), Clock::now()));
    }

    /** Run the daily job now (coordinator only, for demos and testing). */
    public function runJob(Request $request): JsonResponse
    {
        $outcome = $this->runner->runOnce('manual');
        if ($outcome['skipped']) {
            return ApiError::response($request, 409, 'JOB_RUNNING', 'The job is already running. Try again in a moment.');
        }

        return response()->json($outcome['result']);
    }

    /**
     * Manual review queue: notifications that failed permanently.
     * The message text is NOT returned (data minimization): the coordinator only needs to know
     * who, what kind, and why it failed.
     */
    public function failedNotifications(): JsonResponse
    {
        $items = array_map(static fn (array $n) => [
            'id' => $n['id'],
            'type' => $n['type'],
            'studentId' => $n['studentId'],
            'referenceId' => $n['referenceId'],
            'attempts' => $n['attempts'],
            'reason' => $n['lastError'],
            'status' => $n['status'],
            'createdAt' => $n['createdAt'],
        ], $this->notifications->listByStatus('MANUAL_REVIEW'));

        return response()->json(['count' => count($items), 'items' => $items]);
    }

    /** Coordinator marks a failed notification as handled. */
    public function markHandled(Request $request, string $id): JsonResponse
    {
        $notificationId = ApiError::parseId($id);
        if ($notificationId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Notification id must be a positive integer.');
        }

        $current = $this->notifications->get($notificationId);
        if ($current === null) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Notification not found.');
        }
        if ($current['status'] !== 'MANUAL_REVIEW') {
            return ApiError::response($request, 409, 'INVALID_STATE', "Only notifications in MANUAL_REVIEW can be handled (current: {$current['status']}).");
        }

        $this->notifications->update($notificationId, ['status' => 'HANDLED', 'handledAt' => gmdate('Y-m-d H:i:s')]);
        $this->logger->info('notification_handled', ['referenceId' => ApiError::refId($request), 'notificationId' => $notificationId]);

        return response()->json(['id' => $notificationId, 'status' => 'HANDLED']);
    }
}
