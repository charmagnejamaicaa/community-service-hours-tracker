<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates FICTIONAL demo accounts (example.test addresses). The password comes from .env
 * (DEMO_USER_PASSWORD) so no password is ever written in the code or committed to Git.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('service_hours.demo_user_password');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_USER_PASSWORD in .env first (at least 12 characters), then run the seeder again.');
        }

        $accounts = [
            ['student1@example.test', 'Sample Student One', 'student', 1],
            ['student2@example.test', 'Sample Student Two', 'student', 2],
            ['supervisor@example.test', 'Sample Supervisor', 'supervisor', null],
            ['coordinator@example.test', 'Sample Coordinator', 'coordinator', null],
            ['adviser@example.test', 'Sample Adviser', 'adviser', null],
        ];

        foreach ($accounts as [$email, $name, $role, $studentId]) {
            if ($studentId !== null && ! DB::table('students')->where('id', $studentId)->exists()) {
                $this->command?->warn("Student $studentId does not exist; skipped $email.");

                continue;
            }
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => $role, 'student_id' => $studentId]
            );
        }

        $this->command?->info('Demo accounts are ready (see docs/security-note.md). Password: the value of DEMO_USER_PASSWORD.');
    }
}
