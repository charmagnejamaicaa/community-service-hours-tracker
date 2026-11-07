<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HoursRules;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HoursRulesTest extends TestCase
{
    private function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 10:00:00', new DateTimeZone('UTC'));
    }

    public function test_remaining_hours_is_required_minus_approved(): void
    {
        $this->assertSame(17.5, HoursRules::calculateRemainingHours(40, 22.5));
    }

    public function test_remaining_hours_never_goes_below_zero(): void
    {
        $this->assertSame(0.0, HoursRules::calculateRemainingHours(40, 45));
    }

    public function test_negative_hours_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HoursRules::calculateRemainingHours(-1, 0);
    }

    public function test_progress_in_progress_with_days_left(): void
    {
        $p = HoursRules::computeProgress(
            ['requiredHours' => 40, 'approvedHours' => 22.5, 'pendingHours' => 4.5, 'deadline' => '2026-12-15'],
            $this->day('2026-11-03')
        );
        $this->assertSame('IN_PROGRESS', $p['status']);
        $this->assertSame(17.5, $p['remainingHours']);
        $this->assertSame(56.25, $p['percentComplete']);
        $this->assertSame(42, $p['daysLeft']);
        $this->assertSame(4.5, $p['pendingHours']);
    }

    public function test_progress_completed_when_nothing_remains(): void
    {
        $p = HoursRules::computeProgress(['requiredHours' => 40, 'approvedHours' => 40, 'deadline' => '2026-11-01'], $this->day('2026-11-03'));
        $this->assertSame('COMPLETED', $p['status']);
        $this->assertSame(100.0, $p['percentComplete']);
    }

    public function test_progress_overdue_after_deadline(): void
    {
        $p = HoursRules::computeProgress(['requiredHours' => 40, 'approvedHours' => 10, 'deadline' => '2026-11-01'], $this->day('2026-11-05'));
        $this->assertSame('OVERDUE', $p['status']);
        $this->assertSame(-4, $p['daysLeft']);
    }

    public function test_progress_without_deadline_has_no_days_left(): void
    {
        $p = HoursRules::computeProgress(['requiredHours' => 40, 'approvedHours' => 10], $this->day('2026-11-05'));
        $this->assertNull($p['daysLeft']);
        $this->assertSame('IN_PROGRESS', $p['status']);
    }

    public function test_valid_submission_has_no_errors(): void
    {
        $this->assertSame([], HoursRules::validateSubmission(4.5, '2026-11-03', 'Signed sheet', $this->day('2026-11-05')));
    }

    public function test_hours_must_be_above_zero_and_at_most_twelve(): void
    {
        foreach ([0, -1, 12.5, 13, '4', null] as $bad) {
            $errors = HoursRules::validateSubmission($bad, '2026-11-03', 'ok', $this->day('2026-11-05'));
            $this->assertSame(['Hours must be greater than 0 and at most 12.'], $errors);
        }
        $this->assertSame([], HoursRules::validateSubmission(12, '2026-11-03', 'ok', $this->day('2026-11-05')));
    }

    public function test_service_date_cannot_be_in_the_future(): void
    {
        $errors = HoursRules::validateSubmission(2, '2026-11-06', 'ok', $this->day('2026-11-05'));
        $this->assertSame(['Service date cannot be in the future.'], $errors);
    }

    public function test_service_date_cannot_be_older_than_30_days(): void
    {
        $this->assertSame([], HoursRules::validateSubmission(2, '2026-10-06', 'ok', $this->day('2026-11-05')));
        $errors = HoursRules::validateSubmission(2, '2026-10-05', 'ok', $this->day('2026-11-05'));
        $this->assertSame(['Service date cannot be older than 30 days.'], $errors);
    }

    public function test_bad_dates_are_rejected(): void
    {
        foreach (['', 'tomorrow', '2026-02-30', '03/11/2026', null, 20261103] as $bad) {
            $errors = HoursRules::validateSubmission(2, $bad, 'ok', $this->day('2026-11-05'));
            $this->assertSame(['Service date is required and must be a valid date.'], $errors);
        }
    }

    public function test_evidence_note_is_required_and_limited(): void
    {
        $this->assertSame(['Evidence note is required.'], HoursRules::validateSubmission(2, '2026-11-03', '   ', $this->day('2026-11-05')));
        $this->assertSame(['Evidence note is required.'], HoursRules::validateSubmission(2, '2026-11-03', null, $this->day('2026-11-05')));
        $this->assertSame([], HoursRules::validateSubmission(2, '2026-11-03', str_repeat('a', 500), $this->day('2026-11-05')));
        $this->assertSame(
            ['Evidence note must be at most 500 characters.'],
            HoursRules::validateSubmission(2, '2026-11-03', str_repeat('a', 501), $this->day('2026-11-05'))
        );
    }

    public function test_several_problems_are_all_reported(): void
    {
        $errors = HoursRules::validateSubmission(0, 'nope', '', $this->day('2026-11-05'));
        $this->assertCount(3, $errors);
    }
}
