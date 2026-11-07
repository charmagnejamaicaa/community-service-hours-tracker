<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\HoursRules;
use DateTimeImmutable;

final class ProgressSummary
{
    /**
     * Progress summary for all students. Only IDs and numbers (no names): data minimization.
     *
     * @param list<array{id:int, requiredHours:float, approvedHours:float, pendingHours:float, deadline:?string}> $students
     */
    public static function build(array $students, DateTimeImmutable $today): array
    {
        $rows = [];
        foreach ($students as $s) {
            $p = HoursRules::computeProgress([
                'requiredHours' => $s['requiredHours'],
                'approvedHours' => $s['approvedHours'],
                'pendingHours' => $s['pendingHours'],
                'deadline' => $s['deadline'],
            ], $today);
            $rows[] = ['studentId' => $s['id']] + $p + ['deadline' => $s['deadline'] === null ? null : substr($s['deadline'], 0, 10)];
        }

        $count = static fn (string $status): int => count(array_filter($rows, static fn ($r) => $r['status'] === $status));
        $sum = static fn (string $field): float => round(array_sum(array_column($rows, $field)), 2);

        return [
            'generatedAt' => $today->format(DATE_ATOM),
            'totals' => [
                'students' => count($rows),
                'completed' => $count('COMPLETED'),
                'inProgress' => $count('IN_PROGRESS'),
                'overdue' => $count('OVERDUE'),
                'approvedHours' => $sum('approvedHours'),
                'remainingHours' => $sum('remainingHours'),
            ],
            'students' => $rows,
        ];
    }
}
