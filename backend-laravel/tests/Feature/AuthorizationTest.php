<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/** Week 14: who may do what (roles) and whose data they may touch (ownership). */
class AuthorizationTest extends ApiTestCase
{
    private function today(): string
    {
        return \App\Support\Clock::now()->format('Y-m-d');
    }

    // ---- ownership ----

    public function test_a_student_can_view_their_own_progress(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->getJson('/api/students/1/progress')->assertOk()->assertJsonPath('studentId', 1);
    }

    public function test_a_student_cannot_view_another_students_progress(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->getJson('/api/students/2/progress')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_a_student_cannot_submit_hours_for_another_students_activity(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->postJson('/api/activities/2/submissions', [
            'hours' => 2, 'serviceDate' => $this->today(), 'evidenceNote' => 'x',
        ])->assertStatus(403);
        $this->assertSame(2, DB::table('submissions')->count()); // nothing was added
    }

    public function test_a_student_cannot_register_an_activity_for_someone_else(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->postJson('/api/activities', [
            'studentId' => 2, 'name' => 'Clean-up', 'organization' => 'Sample', 'activityDate' => $this->today(),
        ])->assertStatus(403);
        $this->assertSame(2, DB::table('activities')->count());
    }

    public function test_the_student_id_is_taken_from_the_token_not_from_the_request(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $response = $this->useToken($token)->postJson('/api/activities', [
            'name' => 'Clean-up', 'organization' => 'Sample', 'activityDate' => $this->today(),
        ]);
        $response->assertStatus(201)->assertJsonPath('studentId', 1);
    }

    public function test_a_student_cannot_resubmit_another_students_submission(): void
    {
        DB::table('submissions')->insert([
            'id' => 3, 'activity_id' => 2, 'hours' => 2, 'service_date' => '2026-11-02',
            'evidence_note' => 'x', 'status' => 'REJECTED',
        ]);
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->patchJson('/api/submissions/3/resubmit', [
            'hours' => 2, 'serviceDate' => $this->today(), 'evidenceNote' => 'changed',
        ])->assertStatus(403);
        $this->assertSame('x', DB::table('submissions')->where('id', 3)->value('evidence_note'));
    }

    // ---- roles ----

    public function test_a_student_cannot_approve_or_verify(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->patchJson('/api/submissions/2/approve')->assertStatus(403);
        $this->useToken($token)->patchJson('/api/submissions/1/verify', ['decision' => 'VERIFY'])->assertStatus(403);
        $this->assertSame('VERIFIED', DB::table('submissions')->where('id', 2)->value('status'));
        $this->assertSame('SUBMITTED', DB::table('submissions')->where('id', 1)->value('status'));
    }

    public function test_a_supervisor_cannot_approve(): void
    {
        $token = $this->tokenFor('supervisor@example.test');
        $this->useToken($token)->patchJson('/api/submissions/2/approve')->assertStatus(403);
    }

    public function test_a_supervisor_can_verify_and_a_coordinator_can_then_approve(): void
    {
        $supervisor = $this->tokenFor('supervisor@example.test');
        $this->useToken($supervisor)->patchJson('/api/submissions/1/verify', ['decision' => 'VERIFY'])
            ->assertOk()->assertJsonPath('status', 'VERIFIED');

        $coordinator = $this->tokenFor('coordinator@example.test');
        $this->useToken($coordinator)->patchJson('/api/submissions/1/approve')
            ->assertOk()->assertJsonPath('status', 'APPROVED');
    }

    public function test_only_a_coordinator_can_run_the_job_or_see_the_review_queue(): void
    {
        foreach (['student1@example.test', 'supervisor@example.test', 'adviser@example.test'] as $email) {
            $token = $this->tokenFor($email);
            $this->useToken($token)->postJson('/api/jobs/run')->assertStatus(403);
            $this->useToken($token)->getJson('/api/notifications/failed')->assertStatus(403);
        }
        $coordinator = $this->tokenFor('coordinator@example.test');
        $this->useToken($coordinator)->getJson('/api/notifications/failed')->assertOk()->assertJsonPath('count', 0);
    }

    public function test_the_summary_is_for_coordinator_and_adviser_only(): void
    {
        $this->useToken($this->tokenFor('student1@example.test'))->getJson('/api/summaries/progress')->assertStatus(403);
        $this->useToken($this->tokenFor('supervisor@example.test'))->getJson('/api/summaries/progress')->assertStatus(403);
        $this->useToken($this->tokenFor('adviser@example.test'))->getJson('/api/summaries/progress')->assertOk();
        $this->useToken($this->tokenFor('coordinator@example.test'))->getJson('/api/summaries/progress')->assertOk();
    }

    public function test_an_adviser_is_read_only(): void
    {
        $token = $this->tokenFor('adviser@example.test');
        $this->useToken($token)->getJson('/api/students/2/progress')->assertOk();
        $this->useToken($token)->patchJson('/api/submissions/2/approve')->assertStatus(403);
        $this->useToken($token)->postJson('/api/activities', [])->assertStatus(403);
    }

    public function test_a_supervisor_cannot_see_progress_figures(): void
    {
        $this->useToken($this->tokenFor('supervisor@example.test'))->getJson('/api/students/1/progress')->assertStatus(403);
    }

    public function test_a_student_cannot_list_submissions(): void
    {
        $this->useToken($this->tokenFor('student1@example.test'))->getJson('/api/submissions')->assertStatus(403);
    }

    // ---- input validation still works behind the login ----

    public function test_input_is_still_validated_for_signed_in_users(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->postJson('/api/activities/1/submissions', [
            'hours' => 13, 'serviceDate' => $this->today(), 'evidenceNote' => 'x',
        ])->assertStatus(400)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->useToken($token)->getJson('/api/students/abc/progress')->assertStatus(400);
    }

    // ---- headers ----

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->useToken(null)->getJson('/api/health');
        $response->assertOk();
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Strict-Transport-Security')); // plain HTTP: no HSTS
    }

    public function test_plain_http_is_refused_when_https_is_required(): void
    {
        config(['service_hours.require_https' => true]);
        $this->useToken(null)->postJson('/api/auth/login', ['email' => 'student1@example.test', 'password' => self::PASSWORD])
            ->assertStatus(426)
            ->assertJsonPath('error.code', 'HTTPS_REQUIRED');
    }

    public function test_https_requests_are_accepted_and_get_hsts_when_enabled(): void
    {
        config(['service_hours.require_https' => true, 'service_hours.hsts' => true]);
        $response = $this->useToken(null)->getJson('https://localhost/api/health');
        $response->assertOk();
        $this->assertStringContainsString('max-age', (string) $response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_is_off_by_default_so_localhost_development_keeps_working(): void
    {
        config(['service_hours.require_https' => true]);
        $response = $this->useToken(null)->getJson('https://localhost/api/health');
        $response->assertOk();
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }
}
