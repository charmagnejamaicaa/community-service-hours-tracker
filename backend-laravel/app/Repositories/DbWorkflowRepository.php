<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Iso;
use Illuminate\Support\Facades\DB;

/** Activities and submissions in MySQL. Uses the existing tables: students, activities, submissions. */
final class DbWorkflowRepository
{
    // Only these fields may be updated.
    private const COLUMNS = [
        'status' => 'status',
        'rejectReason' => 'reject_reason',
        'hours' => 'hours',
        'serviceDate' => 'service_date',
        'evidenceNote' => 'evidence_note',
    ];

    private const SUBMISSION_SELECT = 'SELECT s.id, s.activity_id, a.student_id, s.hours, s.service_date,
         s.evidence_note, s.status, s.reject_reason, s.created_at
       FROM submissions s JOIN activities a ON a.id = s.activity_id';

    public function findStudent(int $id): bool
    {
        return DB::table('students')->where('id', $id)->exists();
    }

    public function createActivity(int $studentId, string $name, string $organization, string $activityDate): array
    {
        $id = DB::table('activities')->insertGetId([
            'student_id' => $studentId,
            'name' => $name,
            'organization' => $organization,
            'activity_date' => $activityDate,
        ]);

        return ['id' => (int) $id, 'studentId' => $studentId, 'name' => $name, 'organization' => $organization, 'activityDate' => $activityDate];
    }

    /** @return ?array{id:int, studentId:int} */
    public function getActivity(int $id): ?array
    {
        $row = DB::table('activities')->where('id', $id)->first(['id', 'student_id']);

        return $row === null ? null : ['id' => (int) $row->id, 'studentId' => (int) $row->student_id];
    }

    public function sumHoursForDay(int $studentId, string $serviceDate, ?int $excludeSubmissionId = null): float
    {
        $rows = DB::select(
            "SELECT COALESCE(SUM(s.hours), 0) AS total
             FROM submissions s JOIN activities a ON a.id = s.activity_id
             WHERE a.student_id = ? AND s.service_date = ? AND s.status <> 'REJECTED' AND s.id <> ?",
            [$studentId, $serviceDate, $excludeSubmissionId ?? 0]
        );

        return (float) $rows[0]->total;
    }

    public function createSubmission(int $activityId, float $hours, string $serviceDate, string $evidenceNote): array
    {
        $id = DB::table('submissions')->insertGetId([
            'activity_id' => $activityId,
            'hours' => $hours,
            'service_date' => $serviceDate,
            'evidence_note' => $evidenceNote,
        ]);

        return $this->getSubmission((int) $id);
    }

    public function getSubmission(int $id): ?array
    {
        $rows = DB::select(self::SUBMISSION_SELECT . ' WHERE s.id = ?', [$id]);

        return $rows === [] ? null : $this->toSubmission($rows[0]);
    }

    /** @return list<array> */
    public function listSubmissions(string $status): array
    {
        $rows = DB::select(self::SUBMISSION_SELECT . ' WHERE s.status = ? ORDER BY s.created_at, s.id', [$status]);

        return array_map(fn ($r) => $this->toSubmission($r), $rows);
    }

    /**
     * Change a submission ONLY if it is still in $fromStatus (the WHERE clause does the check),
     * so two people acting at the same time cannot both succeed.
     * Returns true if it changed, false if someone else changed it first.
     */
    public function transition(int $id, string $fromStatus, array $patch): bool
    {
        $set = [];
        foreach ($patch as $field => $value) {
            if (isset(self::COLUMNS[$field])) {
                $set[self::COLUMNS[$field]] = $value;
            }
        }
        if ($set === []) {
            return false;
        }

        return DB::table('submissions')->where('id', $id)->where('status', $fromStatus)->update($set) === 1;
    }

    /** @return ?array{id:int, requiredHours:float, approvedHours:float, pendingHours:float, deadline:?string} */
    public function getStudentHours(int $studentId): ?array
    {
        $rows = DB::select(
            "SELECT
               st.id, st.required_hours, st.deadline,
               COALESCE(SUM(CASE WHEN s.status = 'APPROVED' THEN s.hours END), 0) AS approved,
               COALESCE(SUM(CASE WHEN s.status IN ('SUBMITTED', 'VERIFIED') THEN s.hours END), 0) AS pending
             FROM students st
             LEFT JOIN activities a ON a.student_id = st.id
             LEFT JOIN submissions s ON s.activity_id = a.id
             WHERE st.id = ?
             GROUP BY st.id, st.required_hours, st.deadline",
            [$studentId]
        );
        if ($rows === []) {
            return null;
        }
        $r = $rows[0];

        return [
            'id' => (int) $r->id,
            'requiredHours' => (float) $r->required_hours,
            'approvedHours' => (float) $r->approved,
            'pendingHours' => (float) $r->pending,
            'deadline' => $r->deadline === null ? null : substr((string) $r->deadline, 0, 10),
        ];
    }

    private function toSubmission(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'activityId' => (int) $r->activity_id,
            'studentId' => (int) $r->student_id,
            'hours' => (float) $r->hours,
            'serviceDate' => substr((string) $r->service_date, 0, 10),
            'evidenceNote' => $r->evidence_note,
            'status' => $r->status,
            'rejectReason' => $r->reject_reason,
            'createdAt' => Iso::utc($r->created_at),
        ];
    }
}
