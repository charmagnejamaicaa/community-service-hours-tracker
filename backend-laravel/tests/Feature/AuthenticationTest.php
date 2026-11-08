<?php

namespace Tests\Feature;

use App\Models\User;

/** Week 14: sign-in, and what happens with missing, invalid, altered and expired tokens. */
class AuthenticationTest extends ApiTestCase
{
    public function test_login_returns_a_token_and_the_user_but_never_a_password(): void
    {
        $response = $this->postJson('/api/auth/login', ['email' => 'student1@example.test', 'password' => self::PASSWORD]);

        $response->assertOk()
            ->assertJsonPath('tokenType', 'Bearer')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.studentId', 1);
        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEmpty($response->json('expiresAt'));

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::PASSWORD, $body);
        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('student1@example.test', $body);
    }

    public function test_the_stored_password_is_a_hash_not_the_password(): void
    {
        $stored = User::where('email', 'student1@example.test')->value('password');
        $this->assertNotSame(self::PASSWORD, $stored);
        $this->assertStringStartsWith('$2y$', $stored);
    }

    public function test_the_stored_token_is_a_hash_not_the_token(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $plain = explode('|', $token, 2)[1];
        $stored = \Illuminate\Support\Facades\DB::table('personal_access_tokens')->value('token');
        $this->assertNotSame($plain, $stored);
        $this->assertSame(hash('sha256', $plain), $stored);
    }

    public function test_wrong_password_and_unknown_email_give_the_same_answer(): void
    {
        $wrongPassword = $this->postJson('/api/auth/login', ['email' => 'student1@example.test', 'password' => 'wrong-password-123']);
        $unknownEmail = $this->postJson('/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrong-password-123']);

        $wrongPassword->assertStatus(401)->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
        $unknownEmail->assertStatus(401)->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
        $this->assertSame($wrongPassword->json('error.message'), $unknownEmail->json('error.message'));
    }

    public function test_login_input_is_validated(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'not-an-email', 'password' => ''])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->postJson('/api/auth/login', ['email' => ['x'], 'password' => str_repeat('a', 500)])
            ->assertStatus(400);
    }

    public function test_too_many_login_attempts_are_blocked(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'student1@example.test', 'password' => 'wrong-password-123'])
                ->assertStatus(401);
        }
        $blocked = $this->postJson('/api/auth/login', ['email' => 'student1@example.test', 'password' => 'wrong-password-123']);
        $blocked->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
        $this->assertNotNull($blocked->headers->get('Retry-After'));
    }

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->useToken(null)->getJson('/api/students/1/progress')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_the_old_demo_role_header_no_longer_works(): void
    {
        $this->useToken(null)->getJson('/api/summaries/progress', ['X-Demo-Role' => 'coordinator'])
            ->assertStatus(401);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->useToken('12345|this-is-not-a-real-token')->getJson('/api/students/1/progress')
            ->assertStatus(401);
        $this->useToken('garbage')->getJson('/api/students/1/progress')
            ->assertStatus(401);
    }

    public function test_an_altered_token_is_rejected(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->getJson('/api/students/1/progress')->assertOk(); // the real one works

        $altered = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');
        $this->useToken($altered)->getJson('/api/students/1/progress')->assertStatus(401);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = User::where('email', 'student1@example.test')->first();
        $expired = $user->createToken('flutter-web', ['student'], now()->subMinute())->plainTextToken;

        $this->useToken($expired)->getJson('/api/students/1/progress')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_logout_makes_the_token_stop_working(): void
    {
        $token = $this->tokenFor('student1@example.test');
        $this->useToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('role', 'student');
        $this->useToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->useToken($token)->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_signing_in_again_replaces_the_old_token(): void
    {
        $first = $this->tokenFor('student1@example.test');
        $second = $this->tokenFor('student1@example.test');
        $this->useToken($first)->getJson('/api/auth/me')->assertStatus(401);
        $this->useToken($second)->getJson('/api/auth/me')->assertOk();
    }

    public function test_errors_use_the_standard_format_with_a_reference_id(): void
    {
        $response = $this->useToken(null)->getJson('/api/students/1/progress');
        $response->assertStatus(401);
        $this->assertNotEmpty($response->json('error.referenceId'));
        $this->assertSame($response->json('error.referenceId'), $response->headers->get('X-Reference-Id'));
    }
}
