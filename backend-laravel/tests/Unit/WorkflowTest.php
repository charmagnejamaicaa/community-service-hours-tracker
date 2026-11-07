<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Workflow;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    public function test_normal_path_submitted_to_approved(): void
    {
        $this->assertSame('VERIFIED', Workflow::nextStatus('verify', 'SUBMITTED'));
        $this->assertSame('APPROVED', Workflow::nextStatus('approve', 'VERIFIED'));
    }

    public function test_reject_and_return_both_lead_to_rejected(): void
    {
        $this->assertSame('REJECTED', Workflow::nextStatus('reject', 'SUBMITTED'));
        $this->assertSame('REJECTED', Workflow::nextStatus('return', 'VERIFIED'));
    }

    public function test_student_can_resubmit_a_rejected_submission(): void
    {
        $this->assertSame('SUBMITTED', Workflow::nextStatus('resubmit', 'REJECTED'));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->assertNull(Workflow::nextStatus('approve', 'SUBMITTED'));
        $this->assertNull(Workflow::nextStatus('verify', 'VERIFIED'));
        $this->assertNull(Workflow::nextStatus('resubmit', 'SUBMITTED'));
    }

    public function test_approved_is_final(): void
    {
        foreach (['verify', 'reject', 'approve', 'return', 'resubmit'] as $action) {
            $this->assertNull(Workflow::nextStatus($action, 'APPROVED'));
        }
    }

    public function test_unknown_action_is_a_programming_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Workflow::nextStatus('delete', 'SUBMITTED');
    }
}
