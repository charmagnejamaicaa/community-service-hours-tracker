<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\EventLogger;
use App\Repositories\DbWorkflowRepository;
use App\Support\ApiError;
use App\Support\Clock;
use App\Support\HoursRules;
use App\Support\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Activities and submissions: register, submit, verify, approve, correct, resubmit. */
final class WorkflowController
{
    private const MAX_TEXT = 150;
    private const MAX_REASON = 255;

    public function __construct(
        private readonly DbWorkflowRepository $repo,
        private readonly EventLogger $logger,
    ) {
    }

    // --- Student registers an activity ---
    public function registerActivity(Request $request): JsonResponse
    {
        $b = $request->json()->all();
        $studentId = $b['studentId'] ?? null;
        $name = $b['name'] ?? null;
        $organization = $b['organization'] ?? null;
        $activityDate = $b['activityDate'] ?? null;

        $errors = [];
        if (! is_int($studentId) || $studentId <= 0) {
            $errors[] = 'Student id must be a positive whole number.';
        }
        if (! $this->isText($name, self::MAX_TEXT)) {
            $errors[] = 'Activity name is required (at most ' . self::MAX_TEXT . ' characters).';
        }
        if (! $this->isText($organization, self::MAX_TEXT)) {
            $errors[] = 'Organization is required (at most ' . self::MAX_TEXT . ' characters).';
        }
        if (! HoursRules::isDateText($activityDate)) {
            $errors[] = 'Activity date is required in the format YYYY-MM-DD.';
        }
        if ($errors !== []) {
            return ApiError::validation($request, $errors);
        }

        if (! $this->repo->findStudent($studentId)) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Student not found.');
        }

        $activity = $this->repo->createActivity($studentId, trim($name), trim($organization), $activityDate);
        $this->logger->info('activity_registered', [
            'referenceId' => ApiError::refId($request),
            'activityId' => $activity['id'],
            'studentId' => $studentId,
        ]);

        return response()->json($activity, 201);
    }

    // --- Student submits hours and evidence ---
    public function submitHours(Request $request, string $id): JsonResponse
    {
        $activityId = ApiError::parseId($id);
        if ($activityId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Activity id must be a positive integer.');
        }

        $b = $request->json()->all();
        $hours = $b['hours'] ?? null;
        $serviceDate = $b['serviceDate'] ?? null;
        $evidenceNote = $b['evidenceNote'] ?? null;

        $errors = HoursRules::validateSubmission($hours, $serviceDate, $evidenceNote, Clock::now());
        if ($errors !== []) {
            return ApiError::validation($request, $errors);
        }

        $activity = $this->repo->getActivity($activityId);
        if ($activity === null) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Activity not found.');
        }

        $limitError = $this->dailyLimitError($activity['studentId'], $serviceDate, (float) $hours);
        if ($limitError !== null) {
            return ApiError::validation($request, [$limitError]);
        }

        $submission = $this->repo->createSubmission($activityId, (float) $hours, $serviceDate, trim($evidenceNote));
        $this->logger->info('submission_created', [
            'referenceId' => ApiError::refId($request),
            'submissionId' => $submission['id'],
            'activityId' => $activityId,
            'hours' => $submission['hours'],
        ]);

        return response()->json([
            'id' => $submission['id'],
            'status' => $submission['status'],
            'hours' => $submission['hours'],
            'createdAt' => $submission['createdAt'],
        ], 201);
    }

    // --- Supervisor / coordinator: list submissions by status (default: waiting for verification) ---
    public function listSubmissions(Request $request): JsonResponse
    {
        $raw = $request->query('status', 'SUBMITTED');
        $status = is_string($raw) ? strtoupper($raw) : '';
        if (! in_array($status, Workflow::STATUSES, true)) {
            return ApiError::validation($request, ['Status must be one of: ' . implode(', ', Workflow::STATUSES) . '.']);
        }

        $items = $this->repo->listSubmissions($status);

        return response()->json(['count' => count($items), 'items' => $items]);
    }

    // --- Supervisor verifies or rejects ---
    public function verify(Request $request, string $id): JsonResponse
    {
        $submissionId = ApiError::parseId($id);
        if ($submissionId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');
        }

        $decision = strtoupper((string) ($request->json()->all()['decision'] ?? ''));
        if ($decision !== 'VERIFY' && $decision !== 'REJECT') {
            return ApiError::validation($request, ['Decision must be VERIFY or REJECT.']);
        }
        [$reason, $errors] = $this->readReason($request, $decision === 'REJECT');
        if ($errors !== []) {
            return ApiError::validation($request, $errors);
        }

        $outcome = $this->applyTransition(
            $request,
            $submissionId,
            $decision === 'VERIFY' ? 'verify' : 'reject',
            $decision === 'REJECT' ? ['rejectReason' => $reason] : []
        );
        if ($outcome instanceof JsonResponse) {
            return $outcome;
        }

        return response()->json(['id' => $submissionId, 'status' => $outcome['to']]);
    }

    // --- Coordinator approves verified hours ---
    public function approve(Request $request, string $id): JsonResponse
    {
        $submissionId = ApiError::parseId($id);
        if ($submissionId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');
        }

        $outcome = $this->applyTransition($request, $submissionId, 'approve');
        if ($outcome instanceof JsonResponse) {
            return $outcome;
        }

        // Event-driven trigger (docs/trigger-decision.md): recalculate the remaining hours right away.
        $studentId = $outcome['submission']['studentId'];
        $row = $this->repo->getStudentHours($studentId);
        $progress = HoursRules::computeProgress($row, Clock::now());

        $this->logger->info('hours_approved', [
            'referenceId' => ApiError::refId($request),
            'submissionId' => $submissionId,
            'studentId' => $studentId,
            'remainingHours' => $progress['remainingHours'],
            'progressStatus' => $progress['status'],
        ]);
        if ($progress['status'] === 'COMPLETED') {
            $this->logger->info('student_completed', ['referenceId' => ApiError::refId($request), 'studentId' => $studentId]);
        }

        return response()->json([
            'id' => $submissionId,
            'status' => 'APPROVED',
            'studentId' => $studentId,
            'remainingHours' => $progress['remainingHours'],
            'progressStatus' => $progress['status'],
        ]);
    }

    // --- Coordinator returns a verified submission for correction ---
    public function correct(Request $request, string $id): JsonResponse
    {
        $submissionId = ApiError::parseId($id);
        if ($submissionId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');
        }

        [$reason, $errors] = $this->readReason($request, true);
        if ($errors !== []) {
            return ApiError::validation($request, $errors);
        }

        $outcome = $this->applyTransition($request, $submissionId, 'return', ['rejectReason' => $reason]);
        if ($outcome instanceof JsonResponse) {
            return $outcome;
        }

        return response()->json(['id' => $submissionId, 'status' => $outcome['to']]);
    }

    // --- Student fixes a rejected submission and sends it again ---
    public function resubmit(Request $request, string $id): JsonResponse
    {
        $submissionId = ApiError::parseId($id);
        if ($submissionId === null) {
            return ApiError::response($request, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');
        }

        $b = $request->json()->all();
        $hours = $b['hours'] ?? null;
        $serviceDate = $b['serviceDate'] ?? null;
        $evidenceNote = $b['evidenceNote'] ?? null;

        $errors = HoursRules::validateSubmission($hours, $serviceDate, $evidenceNote, Clock::now());
        if ($errors !== []) {
            return ApiError::validation($request, $errors);
        }

        $current = $this->repo->getSubmission($submissionId);
        if ($current === null) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Submission not found.');
        }

        $limitError = $this->dailyLimitError($current['studentId'], $serviceDate, (float) $hours, $submissionId);
        if ($limitError !== null) {
            return ApiError::validation($request, [$limitError]);
        }

        $outcome = $this->applyTransition($request, $submissionId, 'resubmit', [
            'hours' => (float) $hours,
            'serviceDate' => $serviceDate,
            'evidenceNote' => trim($evidenceNote),
            'rejectReason' => null,
        ]);
        if ($outcome instanceof JsonResponse) {
            return $outcome;
        }

        return response()->json(['id' => $submissionId, 'status' => $outcome['to']]);
    }

    // ---------- helpers ----------

    private function isText(mixed $value, int $max): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen(trim($value)) <= $max;
    }

    /** Checks the 12-hours-per-day rule for one student and date. Returns an error text or null. */
    private function dailyLimitError(int $studentId, string $serviceDate, float $hours, ?int $excludeSubmissionId = null): ?string
    {
        $already = $this->repo->sumHoursForDay($studentId, $serviceDate, $excludeSubmissionId);
        $max = HoursRules::MAX_HOURS_PER_SUBMISSION;
        if (HoursRules::round2($already + $hours) > $max) {
            return 'Daily limit: ' . HoursRules::round2($already) . " hours are already recorded for $serviceDate (maximum $max per day).";
        }

        return null;
    }

    /** @return array{0: string, 1: list<string>} */
    private function readReason(Request $request, bool $required): array
    {
        $raw = $request->json()->all()['reason'] ?? null;
        $reason = is_string($raw) ? trim($raw) : '';
        $errors = [];
        if ($required && $reason === '') {
            $errors[] = 'A reason is required.';
        }
        if (mb_strlen($reason) > self::MAX_REASON) {
            $errors[] = 'Reason must be at most ' . self::MAX_REASON . ' characters.';
        }

        return [$reason, $errors];
    }

    /**
     * Looks up a submission, checks the workflow rule, and applies the change safely.
     *
     * @return array{submission: array, to: string}|JsonResponse a JsonResponse means an error to send back
     */
    private function applyTransition(Request $request, int $id, string $action, array $patch = []): array|JsonResponse
    {
        $submission = $this->repo->getSubmission($id);
        if ($submission === null) {
            return ApiError::response($request, 404, 'NOT_FOUND', 'Submission not found.');
        }

        $to = Workflow::nextStatus($action, $submission['status']);
        if ($to === null) {
            return ApiError::response($request, 409, 'INVALID_STATE', "Cannot $action a submission that is {$submission['status']}.");
        }

        if (! $this->repo->transition($id, $submission['status'], ['status' => $to] + $patch)) {
            return ApiError::response($request, 409, 'INVALID_STATE', 'This submission was just changed by someone else. Reload and try again.');
        }

        $this->logger->info('submission_transition', [
            'referenceId' => ApiError::refId($request),
            'submissionId' => $id,
            'action' => $action,
            'from' => $submission['status'],
            'to' => $to,
            'role' => $request->attributes->get('role'),
        ]);

        return ['submission' => $submission, 'to' => $to];
    }
}
