<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * The rules of the hours workflow, in one place.
 *
 *   SUBMITTED --verify--> VERIFIED --approve--> APPROVED
 *   SUBMITTED --reject--> REJECTED
 *   VERIFIED  --return--> REJECTED      (coordinator sends it back for correction)
 *   REJECTED  --resubmit--> SUBMITTED   (student fixes it and sends it again)
 *
 * APPROVED is final. Only APPROVED hours count toward the requirement.
 */
final class Workflow
{
    public const STUDENT = 'student';
    public const SUPERVISOR = 'supervisor';
    public const COORDINATOR = 'coordinator';

    public const ROLES = [self::STUDENT, self::SUPERVISOR, self::COORDINATOR];
    public const STATUSES = ['SUBMITTED', 'VERIFIED', 'APPROVED', 'REJECTED'];

    public const TRANSITIONS = [
        'verify' => ['role' => self::SUPERVISOR, 'from' => ['SUBMITTED'], 'to' => 'VERIFIED'],
        'reject' => ['role' => self::SUPERVISOR, 'from' => ['SUBMITTED'], 'to' => 'REJECTED'],
        'approve' => ['role' => self::COORDINATOR, 'from' => ['VERIFIED'], 'to' => 'APPROVED'],
        'return' => ['role' => self::COORDINATOR, 'from' => ['VERIFIED'], 'to' => 'REJECTED'],
        'resubmit' => ['role' => self::STUDENT, 'from' => ['REJECTED'], 'to' => 'SUBMITTED'],
    ];

    /** The new status, or null when the action is not allowed from the current status. */
    public static function nextStatus(string $action, string $currentStatus): ?string
    {
        $rule = self::TRANSITIONS[$action] ?? null;
        if ($rule === null) {
            throw new InvalidArgumentException("Unknown action: $action");
        }

        return in_array($currentStatus, $rule['from'], true) ? $rule['to'] : null;
    }
}
