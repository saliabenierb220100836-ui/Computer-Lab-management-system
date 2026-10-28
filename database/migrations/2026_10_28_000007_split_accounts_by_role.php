<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('maintenance_tickets');
        Schema::dropIfExists('activity_sessions');
        Schema::dropIfExists('schedule_enrollments');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        Schema::create('account_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_id')->default(0);
        });
        DB::table('account_sequences')->insert(['id' => 1, 'next_id' => 0]);

        foreach (['administrators', 'faculty', 'students'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('id')->primary();
                $table->string('name', 120);
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->string('student_or_employee_id', 50)->unique();
                $table->string('profile_photo_path')->nullable();
                $table->string('section', 60)->nullable();
                $table->string('registration_certificate_path')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }

        $this->createAccountDirectoryView();
        $this->createLabTables();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS account_directory');
        Schema::dropIfExists('maintenance_tickets');
        Schema::dropIfExists('activity_sessions');
        Schema::dropIfExists('schedule_enrollments');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('administrators');
        Schema::dropIfExists('faculty');
        Schema::dropIfExists('students');
        Schema::dropIfExists('account_sequences');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('student_or_employee_id', 50)->unique();
            $table->string('role')->default('student');
            $table->string('profile_photo_path')->nullable();
            $table->string('section', 60)->nullable();
            $table->string('registration_certificate_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        $this->createLabTables(legacyUserReferences: true);
    }

    private function createAccountDirectoryView(): void
    {
        $columns = 'id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, registration_certificate_path, deleted_at, created_at, updated_at';
        DB::statement(
            "CREATE VIEW account_directory AS SELECT {$columns}, 'admin' AS role FROM administrators UNION ALL SELECT {$columns}, 'instructor' AS role FROM faculty UNION ALL SELECT {$columns}, 'student' AS role FROM students"
        );
    }

    private function createLabTables(bool $legacyUserReferences = false): void
    {
        Schema::create('class_schedules', function (Blueprint $table) use ($legacyUserReferences) {
            $table->id();
            if ($legacyUserReferences) {
                $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('instructor_id');
            }
            $table->string('subject_code');
            $table->string('section');
            $table->string('room_number')->default('Lab 01');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['room_number', 'starts_at', 'ends_at']);
        });

        Schema::create('schedule_enrollments', function (Blueprint $table) use ($legacyUserReferences) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('class_schedules')->cascadeOnDelete();
            if ($legacyUserReferences) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained('students')->restrictOnDelete();
            }
            $table->foreignId('terminal_id')->nullable()->constrained('terminals')->nullOnDelete();
            $table->string('source')->default('instructor_assigned');
            $table->timestamps();
            $table->unique(['schedule_id', 'user_id']);
            $table->unique(['schedule_id', 'terminal_id']);
        });

        Schema::create('activity_sessions', function (Blueprint $table) use ($legacyUserReferences) {
            $table->id();
            if ($legacyUserReferences) {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained('students')->restrictOnDelete();
            }
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->string('purpose')->default('Open lab');
            $table->dateTime('login_time');
            $table->dateTime('logout_time')->nullable();
            $table->timestamps();
            $table->index(['terminal_id', 'logout_time']);
            $table->index(['user_id', 'logout_time']);
        });

        Schema::create('maintenance_tickets', function (Blueprint $table) use ($legacyUserReferences) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('terminals')->cascadeOnDelete();
            if ($legacyUserReferences) {
                $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('reported_by');
            }
            $table->string('issue_type');
            $table->text('description');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }
};
