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

        foreach (['administrators', 'faculty', 'students'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('first_name', 120)->nullable()->after('name');
                $table->string('last_name', 120)->nullable()->after('first_name');
            });

            DB::table($tableName)
                ->select(['id', 'name'])
                ->orderBy('id')
                ->chunkById(200, function ($accounts) use ($tableName): void {
                    foreach ($accounts as $account) {
                        $parts = preg_split('/\s+/', trim((string) $account->name), 2) ?: [];
                        DB::table($tableName)
                            ->where('id', $account->id)
                            ->update([
                                'first_name' => $parts[0] ?? '',
                                'last_name' => $parts[1] ?? '',
                            ]);
                    }
                });
        }

        $this->createAccountDirectoryView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS account_directory');

        foreach (['administrators', 'faculty', 'students'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['first_name', 'last_name']);
            });
        }

        $this->createPreviousAccountDirectoryView();
    }

    private function createAccountDirectoryView(): void
    {
        DB::statement(
            "CREATE VIEW account_directory AS SELECT id, name, first_name, last_name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, NULL AS academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'admin' AS role FROM administrators UNION ALL SELECT id, name, first_name, last_name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, NULL AS academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'instructor' AS role FROM faculty UNION ALL SELECT id, name, first_name, last_name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'student' AS role FROM students"
        );
    }

    private function createPreviousAccountDirectoryView(): void
    {
        $columns = 'id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, registration_certificate_path, deleted_at, created_at, updated_at';
        DB::statement(
            "CREATE VIEW account_directory AS SELECT id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, NULL AS academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'admin' AS role FROM administrators UNION ALL SELECT id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, NULL AS academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'instructor' AS role FROM faculty UNION ALL SELECT id, name, email, email_verified_at, password, remember_token, student_or_employee_id, profile_photo_path, section, academic_group_id, registration_certificate_path, deleted_at, created_at, updated_at, 'student' AS role FROM students"
        );
    }
};
