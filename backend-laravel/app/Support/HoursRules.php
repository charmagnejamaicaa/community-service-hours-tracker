<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Business rules for service hours. Plain PHP only (no database, no HTTP),
 * so the rules are easy to test and to explain.
 */
final class HoursRules
{
    public const MAX_HOURS_PER_SUBMISSION = 12;
    public const MAX_EVIDENCE_LENGTH = 500;
    public const MAX_DAYS_BACK = 30;

    public static function round2(float|int $n): float
    {
        return round((float) $n, 2);
    }

    private static function assertNonNegative(float|int $value, string $name): void
    {
        if (! is_finite((float) $value) || $value < 0) {
            throw new InvalidArgumentException("$name must be a number that is 0 or greater");
        }
    }

    /** Only APPROVED hours count. remaining = max(required - approved, 0) */
    public static function calculateRemainingHours(float|int $requiredHours, float|int $approvedHours): float
    {
        self::assertNonNegative($requiredHours, 'requiredHours');
        self::assertNonNegative($approvedHours, 'approvedHours');

        return self::round2(max($requiredHours - $approvedHours, 0));
    }

    /** Whole calendar days from $from to $to (negative if $to is earlier). */
    public static function daysBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) round((self::dayNumber($to) - self::dayNumber($from)) / 86400);
    }

    private static function dayNumber(DateTimeInterface $d): int
    {
        return gmmktime(0, 0, 0, (int) $d->format('n'), (int) $d->format('j'), (int) $d->format('Y'));
    }

    /** True for text such as 2026-11-03 that is a real calendar date. */
    public static function isDateText(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function parseDate(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /**
     * Progress shown on the student dashboard. status: COMPLETED | OVERDUE | IN_PROGRESS
     *
     * @param array{requiredHours: float|int, approvedHours: float|int, pendingHours?: float|int, deadline?: ?string} $in
     */
    public static function computeProgress(array $in, DateTimeInterface $today): array
    {
        $required = $in['requiredHours'];
        $approved = $in['approvedHours'];
        $pending = $in['pendingHours'] ?? 0;
        $deadline = $in['deadline'] ?? null;

        self::assertNonNegative($required, 'requiredHours');
        self::assertNonNegative($approved, 'approvedHours');
        self::assertNonNegative($pending, 'pendingHours');

        $remaining = self::calculateRemainingHours($required, $approved);
        $percent = $required == 0 ? 100.0 : min(100.0, self::round2(($approved / $required) * 100));

        $daysLeft = null;
        if ($deadline !== null && $deadline !== '') {
            $daysLeft = self::daysBetween($today, self::parseDate(substr((string) $deadline, 0, 10)));
        }

        $status = 'IN_PROGRESS';
        if ($remaining == 0) {
            $status = 'COMPLETED';
        } elseif ($daysLeft !== null && $daysLeft < 0) {
            $status = 'OVERDUE';
        }

        return [
            'requiredHours' => self::round2($required),
            'approvedHours' => self::round2($approved),
            'pendingHours' => self::round2($pending),
            'remainingHours' => $remaining,
            'percentComplete' => (float) $percent,
            'daysLeft' => $daysLeft,
            'status' => $status,
        ];
    }

    /**
     * Validates one hours submission. Returns a list of error messages (empty list = valid).
     *
     * @return list<string>
     */
    public static function validateSubmission(mixed $hours, mixed $serviceDate, mixed $evidenceNote, DateTimeInterface $today): array
    {
        $errors = [];
        $max = self::MAX_HOURS_PER_SUBMISSION;

        if (! (is_int($hours) || is_float($hours)) || ! is_finite((float) $hours) || $hours <= 0 || $hours > $max) {
            $errors[] = "Hours must be greater than 0 and at most $max.";
        }

        if (! self::isDateText($serviceDate)) {
            $errors[] = 'Service date is required and must be a valid date.';
        } else {
            $age = self::daysBetween(self::parseDate($serviceDate), $today);
            if ($age < 0) {
                $errors[] = 'Service date cannot be in the future.';
            } elseif ($age > self::MAX_DAYS_BACK) {
                $errors[] = 'Service date cannot be older than ' . self::MAX_DAYS_BACK . ' days.';
            }
        }

        if (! is_string($evidenceNote) || trim($evidenceNote) === '') {
            $errors[] = 'Evidence note is required.';
        } elseif (mb_strlen($evidenceNote) > self::MAX_EVIDENCE_LENGTH) {
            $errors[] = 'Evidence note must be at most ' . self::MAX_EVIDENCE_LENGTH . ' characters.';
        }

        return $errors;
    }
}
