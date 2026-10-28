<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS account_directory');

        Schema::create('academic_groups', function (Blueprint $table) {
            $table->id();
            $table->string('course', 80);
            $table->string('section', 60);
            $table->timestamps();
            $table->unique(['course', 'section']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('academic_group_id')->nullable()->after('section')
                ->constrained('academic_groups')->restrictOnDelete();
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->foreignId('academic_group_id')->nullable()->after('section')
                ->constrained('academic_groups')->restrictOnDelete();
        });

        $columns = 'id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, registration_certificate_path, deleted_at, created_at, updated_at';
        DB::statement(
            "CREATE VIEW account_directory AS SELECT id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, NULL AS academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'admin' AS role FROM administrators UNION ALL SELECT id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, NULL AS academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'instructor' AS role FROM faculty UNION ALL SELECT id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'student' AS role FROM students"
        );
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS account_directory');

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_group_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_group_id');
        });

        Schema::dropIfExists('academic_groups');

        $columns = 'id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, registration_certificate_path, deleted_at, created_at, updated_at';
        DB::statement(
            "CREATE VIEW account_directory AS SELECT {$columns}, 'admin' AS role FROM administrators UNION ALL SELECT {$columns}, 'instructor' AS role FROM faculty UNION ALL SELECT {$columns}, 'student' AS role FROM students"
        );
    }
};
