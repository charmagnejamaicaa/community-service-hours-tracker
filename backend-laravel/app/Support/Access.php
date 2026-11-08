<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Who may see or change whose data (Week 14 access control). Plain PHP, easy to test.
 *
 *   student      own data only
 *   supervisor   verifies submissions; no access to progress figures
 *   coordinator  everything needed to approve, correct and monitor
 *   adviser      read-only progress figures
 */
final class Access
{
    public static function canViewStudent(string $role, ?int $ownStudentId, int $targetStudentId): bool
    {
        return match ($role) {
            Workflow::COORDINATOR, Workflow::ADVISER => true,
            Workflow::STUDENT => self::isOwner($ownStudentId, $targetStudentId),
            default => false,
        };
    }

    public static function isOwner(?int $ownStudentId, int $targetStudentId): bool
    {
        return $ownStudentId !== null && $ownStudentId === $targetStudentId;
    }
}
