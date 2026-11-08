<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Base class for API security tests.
 *
 * SAFETY: every test runs on a throw-away in-memory SQLite database that this class builds itself.
 * It never touches the real MySQL database (no RefreshDatabase, no migrate:fresh).
 */
abstract class ApiTestCase extends TestCase
{
    protected const PASSWORD = 'correct-horse-battery-1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'service_hours.require_https' => false,
            'cache.default' => 'array', // login rate-limit counters must not leak between tests
        ]);
        DB::purge('sqlite');

        $this->createTables();
        $this->seedData();
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role', 20)->default('student');
            $t->integer('student_id')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('students', function (Blueprint $t) {
            $t->increments('id');
            $t->string('full_name');
            $t->decimal('required_hours', 6, 2)->default(40);
            $t->date('deadline')->nullable();
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('student_id');
            $t->string('name');
            $t->string('organization');
            $t->date('activity_date');
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->increments('id');
            $t->string('notif_key', 150)->unique();
            $t->string('type', 50);
            $t->integer('student_id');
            $t->string('reference_id', 100);
            $t->string('message', 500)->nullable();
            $t->string('status')->default('PENDING');
            $t->integer('attempts')->default(0);
            $t->string('last_error', 100)->nullable();
            $t->dateTime('next_attempt_at')->nullable();
            $t->dateTime('handled_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
        Schema::create('submissions', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('activity_id');
            $t->decimal('hours', 5, 2);
            $t->date('service_date');
            $t->string('evidence_note', 500);
            $t->string('status')->default('SUBMITTED');
            $t->string('reject_reason', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }

    private function seedData(): void
    {
        DB::table('students')->insert([
            ['id' => 1, 'full_name' => 'Sample Student One', 'required_hours' => 40, 'deadline' => '2026-12-31'],
            ['id' => 2, 'full_name' => 'Sample Student Two', 'required_hours' => 40, 'deadline' => '2026-12-31'],
        ]);
        DB::table('activities')->insert([
            ['id' => 1, 'student_id' => 1, 'name' => 'Tree Planting', 'organization' => 'Sample Council', 'activity_date' => '2026-11-01'],
            ['id' => 2, 'student_id' => 2, 'name' => 'Beach Clean-up', 'organization' => 'Sample Youth', 'activity_date' => '2026-11-01'],
        ]);
        DB::table('submissions')->insert([
            ['id' => 1, 'activity_id' => 1, 'hours' => 4, 'service_date' => '2026-11-02', 'evidence_note' => 'Sheet', 'status' => 'SUBMITTED'],
            ['id' => 2, 'activity_id' => 1, 'hours' => 3, 'service_date' => '2026-11-03', 'evidence_note' => 'Sheet', 'status' => 'VERIFIED'],
        ]);

        $this->makeUser('student1@example.test', 'student', 1);
        $this->makeUser('student2@example.test', 'student', 2);
        $this->makeUser('supervisor@example.test', 'supervisor');
        $this->makeUser('coordinator@example.test', 'coordinator');
        $this->makeUser('adviser@example.test', 'adviser');
    }

    protected function makeUser(string $email, string $role, ?int $studentId = null): User
    {
        return User::create([
            'name' => 'Test ' . $role,
            'email' => $email,
            'password' => self::PASSWORD,
            'role' => $role,
            'student_id' => $studentId,
        ]);
    }

    /** Signs in through the real login endpoint and returns the Bearer token. */
    protected function tokenFor(string $email): string
    {
        $this->useToken(null);
        $response = $this->postJson('/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        $response->assertOk();

        return (string) $response->json('token');
    }

    /**
     * Sends the next request with the given token (or none). Laravel remembers the user it found for the
     * previous request, so we reset that, otherwise a later request could silently reuse an earlier user.
     */
    protected function useToken(?string $token): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        if ($token !== null) {
            $this->withToken($token);
        }

        return $this;
    }
}
