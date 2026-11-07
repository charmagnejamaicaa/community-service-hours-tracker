<?php

declare(strict_types=1);

namespace App\Contracts;

interface JobRepository
{
    /**
     * @return list<array{id:int, requiredHours:float, approvedHours:float, pendingHours:float, deadline:?string}>
     */
    public function listStudentsWithHours(): array;

    /**
     * Submissions still SUBMITTED that have waited at least $days days.
     *
     * @return list<array{id:int, studentId:int}>
     */
    public function listStaleSubmissions(int $days): array;
}
