<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // student | supervisor | coordinator | adviser
            $table->string('role', 20)->default('student')->after('password');
            // Links a student account to a row in `students` (same type as students.id). Null for staff accounts.
            $table->integer('student_id')->nullable()->unique()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['student_id']);
            $table->dropColumn(['role', 'student_id']);
        });
    }
};
