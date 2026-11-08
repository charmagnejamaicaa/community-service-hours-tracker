<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Access;
use PHPUnit\Framework\TestCase;

final class AccessTest extends TestCase
{
    public function test_a_student_can_view_only_their_own_progress(): void
    {
        $this->assertTrue(Access::canViewStudent('student', 1, 1));
        $this->assertFalse(Access::canViewStudent('student', 1, 2));
    }

    public function test_a_student_account_without_a_student_record_sees_nothing(): void
    {
        $this->assertFalse(Access::canViewStudent('student', null, 1));
    }

    public function test_coordinator_and_adviser_can_view_any_student(): void
    {
        $this->assertTrue(Access::canViewStudent('coordinator', null, 7));
        $this->assertTrue(Access::canViewStudent('adviser', null, 7));
    }

    public function test_supervisor_and_unknown_roles_cannot_view_progress(): void
    {
        $this->assertFalse(Access::canViewStudent('supervisor', null, 1));
        $this->assertFalse(Access::canViewStudent('admin', null, 1));
        $this->assertFalse(Access::canViewStudent('', 1, 1));
    }

    public function test_ownership_needs_matching_ids(): void
    {
        $this->assertTrue(Access::isOwner(3, 3));
        $this->assertFalse(Access::isOwner(3, 4));
        $this->assertFalse(Access::isOwner(null, 3));
    }
}
