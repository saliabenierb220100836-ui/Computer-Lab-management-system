<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\ActivitySession;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_are_stored_in_separate_role_tables(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $faculty = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        $this->assertTrue(Schema::hasTable('administrators'));
        $this->assertTrue(Schema::hasTable('faculty'));
        $this->assertTrue(Schema::hasTable('students'));
        $this->assertTrue(Schema::hasColumn('administrators', 'first_name'));
        $this->assertTrue(Schema::hasColumn('administrators', 'last_name'));
        $this->assertTrue(Schema::hasColumn('faculty', 'first_name'));
        $this->assertTrue(Schema::hasColumn('faculty', 'last_name'));
        $this->assertTrue(Schema::hasColumn('students', 'first_name'));
        $this->assertTrue(Schema::hasColumn('students', 'last_name'));
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertDatabaseHas('administrators', ['id' => $admin->id]);
        $this->assertDatabaseHas('faculty', ['id' => $faculty->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_admin_can_create_update_and_deactivate_student_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $academicGroup = $this->academicGroup();
        Storage::fake('local');
        $terminal = Terminal::query()->create([
            'pc_name' => 'PC-01',
            'grid_x' => 1,
            'grid_y' => 1,
            'status' => 'available',
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'first_name' => 'New',
            'last_name' => 'PUP Student',
            'student_or_employee_id' => '2026-99999-SR-0',
            'email' => 'newstudent@iskolarngbayan.pup.edu.ph',
            'role' => 'student',
            'academic_group_id' => $academicGroup->id,
            'certificate' => UploadedFile::fake()->createWithContent(
                'certificate.png',
                file_get_contents(public_path('images/pup-favicon.png')),
            ),
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ])->assertRedirect();

        $student = User::query()->where('student_or_employee_id', '2026-99999-SR-0')->firstOrFail();
        $this->assertSame(
            'registration-certificates/2026-99999-SR-0.png',
            $student->registration_certificate_path,
        );
        Storage::disk('local')->assertExists($student->registration_certificate_path);
        $this->assertNotNull($student->email_verified_at);
        $this->assertSame('New', $student->first_name);
        $this->assertSame('New PUP Student', $student->name);
        $this->assertSame('PUP Student', $student->last_name);

        $this->actingAs($admin)->patch(route('admin.users.update', $student), [
            'first_name' => 'Updated',
            'last_name' => 'Student',
            'student_or_employee_id' => '2026-99998-SR-0',
            'email' => 'updatedstudent@iskolarngbayan.pup.edu.ph',
            'role' => 'student',
            'academic_group_id' => $academicGroup->id,
            'password' => 'Change!2026Abcd',
            'password_confirmation' => 'Change!2026Abcd',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('Start!2026Abcd', $student->fresh()->password));

        $session = ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'purpose' => 'Open lab',
            'login_time' => now()->subMinutes(20),
        ]);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $student))->assertRedirect();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertDatabaseHas('activity_sessions', [
            'id' => $session->id,
            'logout_time' => now()->toDateTimeString(),
        ]);
        $this->assertSame('Updated Student', $session->fresh()->user->name);
    }

    public function test_admin_cannot_create_a_student_without_a_certificate_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $academicGroup = $this->academicGroup();
        Storage::fake('local');

        $this->actingAs($admin)
            ->from(route('workspace.page', 'accounts'))
            ->post(route('admin.users.store'), [
                'first_name' => 'New',
                'last_name' => 'Student',
                'student_or_employee_id' => '2026-99996-SR-0',
                'email' => 'newstudent@iskolarngbayan.pup.edu.ph',
                'role' => 'student',
                'academic_group_id' => $academicGroup->id,
                'password' => 'Start!2026Abcd',
                'password_confirmation' => 'Start!2026Abcd',
            ])
            ->assertRedirect(route('workspace.page', 'accounts'))
            ->assertSessionHasErrors('certificate');

        $this->assertDatabaseMissing('students', ['student_or_employee_id' => '2026-99996-SR-0']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_admin_gets_a_friendly_prompt_when_the_certificate_request_is_too_large(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withServerVariables(['CONTENT_LENGTH' => (string) (13 * 1024 * 1024)])
            ->post(route('admin.users.store'))
            ->assertStatus(413)
            ->assertSee('That file is too large.')
            ->assertSee('no larger than 10 MB')
            ->assertSee(route('workspace.page', 'accounts'));
    }

    public function test_admin_cannot_create_a_student_with_a_non_school_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $academicGroup = $this->academicGroup();

        $this->actingAs($admin)->from(route('dashboard'))->post(route('admin.users.store'), [
            'first_name' => 'Outside',
            'last_name' => 'Student',
            'student_or_employee_id' => '2026-99998-SR-0',
            'email' => 'student@example.com',
            'role' => 'student',
            'academic_group_id' => $academicGroup->id,
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ])->assertRedirect(route('dashboard'))->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('students', ['student_or_employee_id' => '2026-99998']);
    }

    public function test_admin_cannot_assign_a_malformed_legacy_section_to_a_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $legacyGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT-3A']);

        $this->actingAs($admin)
            ->from(route('dashboard'))
            ->post(route('admin.users.store'), [
                'name' => 'Legacy Group Student',
                'student_or_employee_id' => '2026-99997-SR-0',
                'email' => 'legacygroup@iskolarngbayan.pup.edu.ph',
                'role' => 'student',
                'academic_group_id' => $legacyGroup->id,
                'password' => 'Start!2026Abcd',
                'password_confirmation' => 'Start!2026Abcd',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('academic_group_id');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_admin_account_numbers_follow_the_selected_student_or_employee_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $academicGroup = $this->academicGroup();
        $studentData = [
            'first_name' => 'Another',
            'last_name' => 'Student',
            'student_or_employee_id' => '2026-01234',
            'email' => 'anotherstudent@iskolarngbayan.pup.edu.ph',
            'role' => 'student',
            'academic_group_id' => $academicGroup->id,
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ];

        $this->actingAs($admin)
            ->from(route('dashboard'))
            ->post(route('admin.users.store'), $studentData)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('student_or_employee_id');

        $this->assertDatabaseMissing('students', ['email' => $studentData['email']]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Instructor',
            'student_or_employee_id' => 'FA-1234-SR-5678',
            'email' => 'NEWINSTRUCTOR@PUP.EDU.PH',
            'role' => 'instructor',
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ])->assertRedirect();

        $this->assertDatabaseHas('faculty', [
            'student_or_employee_id' => 'FA-1234-SR-5678',
            'email' => 'newinstructor@pup.edu.ph',
        ]);

        $instructor = User::query()->where('student_or_employee_id', 'FA-1234-SR-5678')->firstOrFail();
        $this->actingAs($admin)->from(route('dashboard'))->patch(route('admin.users.update', $instructor), [
            'name' => 'New Instructor',
            'student_or_employee_id' => 'FA-1234-SR-5678',
            'email' => 'instructor@example.edu',
            'role' => 'instructor',
        ])->assertRedirect(route('dashboard'))->assertSessionHasErrors('email');

        $this->actingAs($admin)->patch(route('admin.users.update', $instructor), [
            'name' => 'New Instructor',
            'student_or_employee_id' => 'FA-1234-SR-5678',
            'email' => 'updatedinstructor@pup.edu.ph',
            'role' => 'instructor',
            'password' => 'Change!2026Abcd',
            'password_confirmation' => 'Change!2026Abcd',
        ])->assertRedirect();

        $this->assertDatabaseHas('faculty', [
            'id' => $instructor->id,
            'email' => 'updatedinstructor@pup.edu.ph',
        ]);
        $this->assertTrue(Hash::check('Start!2026Abcd', $instructor->fresh()->password));
    }

    public function test_admin_cannot_change_an_account_role_when_lab_history_depends_on_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $terminal = Terminal::query()->create([
            'pc_name' => 'PC-01',
            'grid_x' => 1,
            'grid_y' => 1,
            'status' => 'available',
        ]);
        ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'purpose' => 'Open lab',
            'login_time' => now()->subMinutes(20),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $student), [
                'name' => $student->name,
                'student_or_employee_id' => 'FA-1234-SR-5678',
                'email' => 'updatedinstructor@pup.edu.ph',
                'role' => 'instructor',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('faculty', ['id' => $student->id]);
    }

    public function test_admin_cannot_create_employee_or_faculty_with_non_pup_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('dashboard'))->post(route('admin.users.store'), [
            'name' => 'New Instructor',
            'student_or_employee_id' => 'FA-1234-SR-5678',
            'email' => 'instructor@example.edu',
            'role' => 'instructor',
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('email')
            ->assertSessionHasErrorsIn('default', 'email');

        $this->assertDatabaseMissing('faculty', ['student_or_employee_id' => 'FA-1234-SR-5678']);
    }

    private function academicGroup(): AcademicGroup
    {
        return AcademicGroup::query()->firstOrCreate([
            'course' => 'BSIT',
            'section' => 'BSIT 3-1',
        ]);
    }

    public function test_instructor_cannot_access_admin_account_management(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($instructor)->post(route('admin.users.store'), [
            'name' => 'Unauthorized',
            'student_or_employee_id' => '2026-99997-SR-0',
            'email' => 'unauthorized@example.edu',
            'role' => 'student',
            'password' => 'Start!2026Abcd',
            'password_confirmation' => 'Start!2026Abcd',
        ])->assertForbidden();
    }

    public function test_admin_dashboard_shows_account_management_and_instructor_dashboard_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($admin)
            ->get(route('workspace.page', 'accounts'))
            ->assertOk()
            ->assertSee('User account management')
            ->assertSee('Create an instructor or student account')
            ->assertSee('Students')
            ->assertSee('Faculty')
            ->assertSee('Certificate of registration image')
            ->assertSee('class="account-certificate-field"', false)
            ->assertSee('data-admin-account-form', false);

        $this->get(route('workspace.page', ['page' => 'accounts', 'role' => 'instructor']))
            ->assertOk()
            ->assertSee('Faculty')
            ->assertSee('faculty');

        $this->actingAs($instructor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('User account management');

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'accounts'))
            ->assertForbidden();
    }
}
