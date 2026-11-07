<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\JobRepository;
use Illuminate\Support\Facades\DB;

final class DbJobRepository implements JobRepository
{
    public function listStudentsWithHours(): array
    {
        $rows = DB::select(
            "SELECT
               st.id, st.required_hours, st.deadline,
               COALESCE(SUM(CASE WHEN s.status = 'APPROVED' THEN s.hours END), 0) AS approved,
               COALESCE(SUM(CASE WHEN s.status IN ('SUBMITTED', 'VERIFIED') THEN s.hours END), 0) AS pending
             FROM students st
             LEFT JOIN activities a ON a.student_id = st.id
             LEFT JOIN submissions s ON s.activity_id = a.id
             GROUP BY st.id, st.required_hours, st.deadline
             ORDER BY st.id"
        );

        return array_map(static fn ($r) => [
            'id' => (int) $r->id,
            'requiredHours' => (float) $r->required_hours,
            'approvedHours' => (float) $r->approved,
            'pendingHours' => (float) $r->pending,
            'deadline' => $r->deadline === null ? null : substr((string) $r->deadline, 0, 10),
        ], $rows);
    }

    public function listStaleSubmissions(int $days): array
    {
        $rows = DB::select(
            "SELECT s.id, a.student_id
             FROM submissions s
             JOIN activities a ON a.id = s.activity_id
             WHERE s.status = 'SUBMITTED' AND s.created_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY s.created_at, s.id",
            [$days]
        );

        return array_map(static fn ($r) => ['id' => (int) $r->id, 'studentId' => (int) $r->student_id], $rows);
    }
}
