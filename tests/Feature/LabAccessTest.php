<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\ActivitySession;
use App\Models\ClassSchedule;
use App\Models\MaintenanceTicket;
use App\Models\ScheduleEnrollment;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_sign_in(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_user_can_sign_in_with_their_campus_id(): void
    {
        $student = User::factory()->create(['password' => 'C0mputer!Lab2026']);

        $this->post(route('login.store'), [
            'identity' => $student->student_or_employee_id,
            'password' => 'C0mputer!Lab2026',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($student);
    }

    public function test_workspace_navigation_opens_distinct_pages_for_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('YOUR PROFILE')
            ->assertSee('Profile photo not uploaded')
            ->assertSee('UP NEXT IN THE COMPUTER LAB')
            ->assertDontSee('◫')
            ->assertDontSee('⚒')
            ->assertDontSee('40 STATIONS');

        $this->get(route('workspace.page', 'floor-map'))
            ->assertOk()
            ->assertSee('40 STATIONS')
            ->assertDontSee('name="grid_x"', false)
            ->assertDontSee('name="grid_y"', false)
            ->assertDontSee('name="specs_description"', false)
            ->assertDontSee('Schedule a lab slot');

        $this->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertSee('Classes & schedules')
            ->assertSee('Schedule a lab slot')
            ->assertSee('id="schedule-form"', false)
            ->assertDontSee('Schedule a class')
            ->assertDontSee('href="#schedule-form"', false);

        $this->get(route('workspace.page', 'session-log'))
            ->assertOk()
            ->assertSee('Session log');

        $this->get(route('workspace.page', 'maintenance'))
            ->assertOk()
            ->assertSee('Maintenance queue');

        $this->get(route('workspace.page', 'accounts'))
            ->assertOk()
            ->assertSee('User account management');
    }

    public function test_floor_map_shows_available_pc_count_against_total_terminals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Terminal::query()->create([
            'pc_name' => 'PC-01',
            'grid_x' => 1,
            'grid_y' => 1,
            'status' => 'available',
        ]);
        Terminal::query()->create([
            'pc_name' => 'PC-02',
            'grid_x' => 2,
            'grid_y' => 1,
            'status' => 'maintenance',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.page', 'floor-map'))
            ->assertOk()
            ->assertSee('PCs available')
            ->assertSee('1<span>/2</span>', false);
    }

    public function test_admin_can_change_a_maintenance_terminal_back_to_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $terminal = $this->terminal('PC-01');
        $terminal->update([
            'status' => 'maintenance',
            'grid_x' => 3,
            'grid_y' => 2,
            'specs_description' => 'Existing workstation specifications',
        ]);

        $this->actingAs($admin)
            ->patch(route('terminals.update', $terminal), [
                'pc_name' => 'PC-01',
                'status' => 'available',
                'grid_x' => 1,
                'grid_y' => 1,
                'specs_description' => 'Attempted replacement',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('terminals', [
            'id' => $terminal->id,
            'status' => 'available',
            'grid_x' => 3,
            'grid_y' => 2,
            'specs_description' => 'Existing workstation specifications',
        ]);
    }

    public function test_admin_session_log_shows_current_accounts_and_their_terminals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create([
            'name' => 'Active Student',
            'student_or_employee_id' => '2024-12345-SR-0',
        ]);
        $instructor = User::factory()->create([
            'name' => 'Website-only Instructor',
            'role' => 'instructor',
        ]);
        $terminal = $this->terminal('PC-07');
        $now = now()->timestamp;
        DB::table('sessions')->insert([
            [
                'id' => 'student-web-session',
                'user_id' => $student->id,
                'payload' => '',
                'last_activity' => $now,
            ],
            [
                'id' => 'instructor-web-session',
                'user_id' => $instructor->id,
                'payload' => '',
                'last_activity' => $now,
            ],
            [
                'id' => 'admin-web-session',
                'user_id' => $admin->id,
                'payload' => '',
                'last_activity' => $now,
            ],
        ]);
        ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'purpose' => 'Open lab',
            'login_time' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.page', 'session-log'))
            ->assertOk()
            ->assertSee('Currently logged in')
            ->assertSee('2 active')
            ->assertSee(route('workspace.page', 'active-users'), false)
            ->assertDontSee('Active Student')
            ->assertDontSee('Website-only Instructor');

        $this->get(route('workspace.page', 'active-users'))
            ->assertOk()
            ->assertSee('Students')
            ->assertSee('Faculty')
            ->assertSee('Active Student')
            ->assertSee('2024-12345-SR-0')
            ->assertSee('PC-07')
            ->assertDontSee('Website-only Instructor')
            ->assertDontSee('Lab Administrator')
            ->assertSee('1 active student');

        $this->get(route('workspace.page', ['page' => 'active-users', 'role' => 'instructor']))
            ->assertOk()
            ->assertSee('Website-only Instructor')
            ->assertSee('Not checked into a PC')
            ->assertDontSee('Active Student')
            ->assertDontSee('Lab Administrator')
            ->assertSee('1 active faculty member');
    }

    public function test_admin_session_log_groups_activity_by_date_and_exports_daily_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['name' => 'Daily Log Student']);
        $terminal = $this->terminal('PC-08');
        $date = now()->subDays(2)->toDateString();
        ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'purpose' => 'Open lab',
            'login_time' => $date.' 09:00:00',
            'logout_time' => $date.' 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('workspace.page', 'session-log'))
            ->assertOk()
            ->assertSee(Carbon::parse($date)->format('F j, Y'))
            ->assertSee(route('admin.session-logs.show', $date), false);

        $this->get(route('admin.session-logs.show', $date))
            ->assertOk()
            ->assertSee('Daily Log Student')
            ->assertSee('PC-08')
            ->assertSee('Export CSV');

        $this->get(route('admin.session-logs.export', $date))
            ->assertDownload("session-logs-{$date}.csv");
    }

    public function test_authenticated_workspace_shows_a_sign_out_confirmation_prompt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sign out of PUP - SRComLab?')
            ->assertSee('Stay signed in')
            ->assertSee('data-open-logout-dialog', false)
            ->assertSee(route('logout'));
    }

    public function test_user_can_upload_a_profile_photo_and_see_it_on_the_dashboard(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'student_or_employee_id' => '2026-12345-SR-0',
        ]);

        $response = $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => $this->jpegProfilePhoto(),
            ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('profile-photos/2026-12345-SR-0.jpeg', $user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
        $this->assertSame("\xFF\xD8", substr(Storage::disk('public')->get($user->profile_photo_path), 0, 2));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('profile-photo')
            ->assertSee('data-choose-profile-photo', false)
            ->assertSee('type="button" data-choose-profile-photo', false)
            ->assertSee('storage/'.$user->profile_photo_path);
    }

    public function test_profile_photo_filenames_use_each_account_number(): void
    {
        Storage::fake('public');
        $accounts = [
            ['role' => 'student', 'student_or_employee_id' => '2026-12345-SR-0'],
            ['role' => 'instructor', 'student_or_employee_id' => 'FA-1234-SR-5678'],
            ['role' => 'admin', 'student_or_employee_id' => 'FA-9876-SR-1234'],
        ];

        foreach ($accounts as $account) {
            $user = User::factory()->create($account);

            $this->actingAs($user)
                ->post(route('profile.photo.store'), [
                    'photo' => $this->jpegProfilePhoto(),
                ])
                ->assertRedirect();

            $this->assertSame(
                'profile-photos/'.$account['student_or_employee_id'].'.jpeg',
                $user->fresh()->profile_photo_path,
            );
            Storage::disk('public')->assertExists($user->fresh()->profile_photo_path);
        }
    }

    public function test_replacing_a_profile_photo_keeps_its_numbered_jpeg_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'student_or_employee_id' => '2026-54321-SR-0',
        ]);

        foreach ([1, 2] as $_) {
            $this->actingAs($user)
                ->post(route('profile.photo.store'), [
                    'photo' => $this->jpegProfilePhoto(),
                ])
                ->assertRedirect();
        }

        $user->refresh();
        $this->assertSame('profile-photos/2026-54321-SR-0.jpeg', $user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_profile_photo_upload_rejects_non_image_files_and_oversized_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('profile.photo.store'), [
                'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('photo');

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('profile.photo.store'), [
                'photo' => UploadedFile::fake()->createWithContent(
                    'profile.png',
                    file_get_contents(public_path('images/pup-favicon.png')),
                ),
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('photo');

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('profile.photo.store'), [
                'photo' => UploadedFile::fake()->create('large.jpeg', 2049, 'image/jpeg'),
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function jpegProfilePhoto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'profile.jpeg',
            base64_decode('/9j/4AAQSkZJRgABAgAAAQABAAD//gAQTGF2YzYyLjI4LjEwMQD/2wBDAAgGBgcGBwgICAgICAkJCQoKCgkJCQkKCgoKCgoMDAwKCgoKCgoKDAwMDA0ODQ0NDA0ODg8PDxISEREVFRUZGR//xABMAAEBAAAAAAAAAAAAAAAAAAAABgEBAQAAAAAAAAAAAAAAAAAABgcQAQAAAAAAAAAAAAAAAAAAAAARAQAAAAAAAAAAAAAAAAAAAAD/wAARCAAQABADASIAAhEAAxEA/9oADAMBAAIRAxEAPwCLAE1/f//Z', true),
        );
    }

    public function test_instructor_overview_shows_their_profile_and_upcoming_lab_classes(): void
    {
        $instructor = User::factory()->create([
            'role' => 'instructor',
            'name' => 'Taylor Instructor',
        ]);
        ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'IT 301',
            'section' => 'BSIT 3-1',
            'room_number' => 'ComLab 1',
            'starts_at' => now()->addDay()->setTime(9, 30),
            'ends_at' => now()->addDay()->setTime(10, 30),
        ]);

        $this->actingAs($instructor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Taylor Instructor')
            ->assertSee($instructor->student_or_employee_id)
            ->assertSee('IT 301')
            ->assertSee('BSIT 3-1')
            ->assertSee('ComLab 1')
            ->assertSee('09:30 - 10:30')
            ->assertDontSee('40 STATIONS');

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertSee('09:30 - 10:30');
    }

    public function test_students_cannot_open_admin_only_workspace_pages(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('workspace.page', 'accounts'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('workspace.page', 'maintenance'))
            ->assertForbidden();
    }

    public function test_student_can_check_in_and_cannot_start_a_second_active_session(): void
    {
        $student = User::factory()->create();
        $firstTerminal = $this->terminal('PC-01');
        $secondTerminal = $this->terminal('PC-02');

        $this->actingAs($student)
            ->post(route('terminals.check-in', $firstTerminal), ['purpose' => 'Open lab'])
            ->assertRedirect();

        $this->actingAs($student)
            ->get(route('workspace.page', 'floor-map'))
            ->assertOk()
            ->assertSee("You're checked in at {$firstTerminal->pc_name}", false)
            ->assertSee('>Check out</button>', false);

        $this->assertDatabaseHas('activity_sessions', [
            'user_id' => $student->id,
            'terminal_id' => $firstTerminal->id,
            'purpose' => 'Open lab',
            'logout_time' => null,
        ]);

        $this->actingAs($student)
            ->post(route('terminals.check-in', $secondTerminal))
            ->assertStatus(422);

        $this->assertDatabaseMissing('activity_sessions', ['terminal_id' => $secondTerminal->id]);
    }

    public function test_student_with_unofficial_email_cannot_check_in(): void
    {
        $student = User::factory()->create(['email' => 'student@example.com']);
        $terminal = $this->terminal('PC-09');

        $this->actingAs($student)
            ->post(route('terminals.check-in', $terminal))
            ->assertForbidden();

        $this->assertDatabaseMissing('activity_sessions', ['user_id' => $student->id]);
    }

    public function test_instructor_cannot_use_student_check_in_or_admin_station_management(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $terminal = $this->terminal('PC-03');

        $this->actingAs($instructor)
            ->post(route('terminals.check-in', $terminal))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->patch(route('terminals.update', $terminal), [
                'pc_name' => 'PC-03',
                'grid_x' => 3,
                'grid_y' => 1,
                'status' => 'maintenance',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'status' => 'available']);
    }

    public function test_admin_can_force_release_an_occupied_terminal_and_close_its_active_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-13');
        $session = ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'purpose' => 'Open lab',
            'login_time' => now()->subMinutes(20),
        ]);

        $this->actingAs($admin)
            ->post(route('terminals.force-release', $terminal))
            ->assertRedirect()
            ->assertSessionHas('success', 'PC-13 has been forcefully checked out and marked available.');

        $this->assertNotNull($session->fresh()->logout_time);
        $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'status' => 'available']);
        $this->actingAs($admin)
            ->get(route('workspace.page', 'floor-map'))
            ->assertOk()
            ->assertSee('PC-13')
            ->assertSee('Available');

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("You're checked in at {$terminal->pc_name}");
    }

    public function test_only_admins_can_force_release_a_terminal(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $terminal = $this->terminal('PC-14');
        $session = ActivitySession::query()->create([
            'user_id' => $otherStudent->id,
            'terminal_id' => $terminal->id,
            'purpose' => 'Open lab',
            'login_time' => now()->subMinutes(10),
        ]);

        $this->actingAs($student)
            ->post(route('terminals.force-release', $terminal))
            ->assertForbidden();

        $this->assertNull($session->fresh()->logout_time);
    }

    public function test_instructor_can_schedule_a_class_and_assign_a_student(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-04');
        $academicGroup = AcademicGroup::query()->create(['course' => 'BSCS', 'section' => 'BSCS 2-1']);
        $startsAt = now()->addDay()->setTime(9, 0);

        $this->actingAs($instructor)
            ->post(route('schedules.store'), [
                'subject_code' => 'CS 201',
                'academic_group_id' => $academicGroup->id,
                'room_number' => 'Lab 01',
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->copy()->addHour()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $schedule = ClassSchedule::query()->firstOrFail();
        $student->update([
            'academic_group_id' => $schedule->academic_group_id,
            'section' => $schedule->section,
        ]);
        $this->actingAs($instructor)
            ->post(route('schedules.students.store', $schedule), [
                'student_id' => $student->student_or_employee_id,
                'terminal_id' => $terminal->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
        ]);
    }

    public function test_instructor_cannot_schedule_a_class_from_a_malformed_legacy_section(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $legacyGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT-3A']);
        $startsAt = now()->addDay()->setTime(9, 0);

        $this->actingAs($instructor)
            ->from(route('workspace.page', 'classes'))
            ->post(route('schedules.store'), [
                'subject_code' => 'CS 201',
                'academic_group_id' => $legacyGroup->id,
                'room_number' => 'Lab 01',
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->copy()->addHour()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('academic_group_id');

        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_instructors_cannot_create_overlapping_lab_schedules(): void
    {
        $firstInstructor = User::factory()->create(['role' => 'instructor']);
        $secondInstructor = User::factory()->create(['role' => 'instructor']);
        $startsAt = now()->addDay()->setTime(9, 0);
        ClassSchedule::query()->create([
            'instructor_id' => $firstInstructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
        ]);
        $academicGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT 3-1']);

        $this->actingAs($secondInstructor)
            ->from(route('workspace.page', 'classes'))
            ->post(route('schedules.store'), [
                'subject_code' => 'IT 301',
                'academic_group_id' => $academicGroup->id,
                'room_number' => 'Lab 02',
                'starts_at' => $startsAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
                'ends_at' => $startsAt->copy()->addHours(2)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('class_schedules', 1);
    }

    public function test_instructor_can_cancel_an_upcoming_class_without_attendance(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($instructor)
            ->delete(route('schedules.cancel', $schedule))
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHas('success', 'Class schedule cancelled.');

        $this->assertDatabaseMissing('class_schedules', ['id' => $schedule->id]);
    }

    public function test_instructor_can_start_class_without_waiting_for_attendance(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertSee('Start class')
            ->assertSee('Cancel class');

        $this->actingAs($instructor)
            ->post(route('schedules.start', $schedule))
            ->assertRedirect()
            ->assertSessionHas('success', 'CS 201 has started. Students can check in when ready.');

        $this->assertTrue($schedule->fresh()->starts_at->isPast() || $schedule->fresh()->starts_at->equalTo(now()));
        $this->assertDatabaseCount('schedule_enrollments', 0);
    }

    public function test_instructor_can_dismiss_class_and_view_final_attendance_summary(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $onTimeStudent = User::factory()->create(['name' => 'On Time Student']);
        $lateStudent = User::factory()->create(['name' => 'Late Student']);
        $absentStudent = User::factory()->create(['name' => 'Absent Student']);
        $terminal = $this->terminal('PC-10');
        $startedAt = now()->subHour();
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => $startedAt,
            'ends_at' => now()->addHour(),
        ]);
        foreach ([$onTimeStudent, $lateStudent, $absentStudent] as $student) {
            ScheduleEnrollment::query()->create([
                'schedule_id' => $schedule->id,
                'user_id' => $student->id,
            ]);
        }

        ActivitySession::query()->create([
            'user_id' => $onTimeStudent->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 201',
            'login_time' => $startedAt->copy()->addMinutes(5),
            'logout_time' => $startedAt->copy()->addMinutes(30),
        ]);
        ActivitySession::query()->create([
            'user_id' => $lateStudent->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 201',
            'login_time' => $startedAt->copy()->addMinutes(20),
            'logout_time' => $startedAt->copy()->addMinutes(45),
        ]);

        $this->actingAs($instructor)
            ->post(route('schedules.dismiss', $schedule))
            ->assertRedirect(route('workspace.page', 'session-log'))
            ->assertSessionHas('success', 'CS 201 has been dismissed. The class summary is available in the session log.');

        $this->assertNotNull($schedule->fresh()->dismissed_at);
        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertDontSee('Class dismissed')
            ->assertDontSee('Final attendance')
            ->assertDontSee('Final attendance summaries')
            ->assertDontSee('Absent Student')
            ->assertDontSee('On Time Student')
            ->assertDontSee('Late Student');

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'session-log'))
            ->assertOk()
            ->assertDontSee('THE PAPER LOG IS HISTORY')
            ->assertSee('Completed classes')
            ->assertSee('View class log')
            ->assertSee(route('schedules.attendance.show', $schedule), false)
            ->assertDontSee('Export class CSV')
            ->assertDontSee('Final attendance')
            ->assertDontSee('Class check-ins')
            ->assertDontSee('Open a dismissed class card')
            ->assertDontSee('No open-lab sessions logged');

        $this->actingAs($instructor)
            ->get(route('schedules.attendance.show', $schedule))
            ->assertOk()
            ->assertSee('Export class CSV')
            ->assertSee('Attendance overview')
            ->assertSee('Duration: 1 hr 0 min')
            ->assertSee('Attendees (2)')
            ->assertSee('On Time Student')
            ->assertSee('Student check-ins')
            ->assertSee($onTimeStudent->student_or_employee_id)
            ->assertSee('PC-10')
            ->assertSee('Late (1)')
            ->assertSee('Late Student')
            ->assertSee('Absent (1)')
            ->assertSee('Absent Student');

        $this->actingAs($student)
            ->get(route('schedules.attendance.show', $schedule))
            ->assertForbidden();

        $csvResponse = $this->actingAs($instructor)
            ->get(route('schedules.attendance.export', $schedule))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString($onTimeStudent->student_or_employee_id, $csv);
        $this->assertStringContainsString('On Time Student', $csv);
        $this->assertStringContainsString('Attended', $csv);
        $this->assertStringContainsString('Late', $csv);
        $this->assertStringContainsString('Absent Student', $csv);
        $this->assertStringContainsString('Absent', $csv);
    }

    public function test_admin_session_log_handles_dismissed_classes_with_deactivated_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['name' => 'Deactivated Student']);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'dismissed_at' => now()->subHour(),
        ]);
        ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
        ]);
        $student->delete();

        $this->actingAs($admin)
            ->get(route('workspace.page', 'session-log'))
            ->assertOk()
            ->assertSee('Completed classes')
            ->assertSee($schedule->subject_code);
    }

    public function test_dismissing_a_class_checks_out_its_active_student_sessions(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-11');
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addMinutes(30),
        ]);
        $session = ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 201',
            'login_time' => now()->subMinutes(15),
        ]);

        $this->actingAs($instructor)
            ->post(route('schedules.dismiss', $schedule))
            ->assertRedirect(route('workspace.page', 'session-log'));

        $this->assertNotNull($session->fresh()->logout_time);
        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee("You're checked in at {$terminal->pc_name}");
    }

    public function test_cancelling_a_class_checks_out_its_active_sessions_and_keeps_the_log(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-12');
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);
        $session = ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 201',
            'login_time' => now()->subMinutes(15),
        ]);

        $this->actingAs($instructor)
            ->delete(route('schedules.cancel', $schedule))
            ->assertRedirect(route('workspace.page', 'classes'));

        $this->assertNotNull($session->fresh()->logout_time);
        $this->assertNull($session->fresh()->schedule_id);
        $this->assertDatabaseMissing('class_schedules', ['id' => $schedule->id]);
    }

    public function test_class_cannot_start_early_while_another_class_is_using_the_lab(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $now = now();
        $activeSchedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 101',
            'section' => 'BSCS 1-1',
            'room_number' => 'Lab 01',
            'starts_at' => $now->copy()->subMinutes(30),
            'ends_at' => $now->copy()->addMinutes(30),
        ]);
        $upcomingSchedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => $now->copy()->addHour(),
            'ends_at' => $now->copy()->addHours(2),
        ]);

        $this->actingAs($instructor)
            ->from(route('workspace.page', 'classes'))
            ->post(route('schedules.start', $upcomingSchedule))
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('schedule');

        $this->assertTrue($upcomingSchedule->fresh()->starts_at->isFuture());
        $this->assertDatabaseHas('class_schedules', ['id' => $activeSchedule->id]);
    }

    public function test_instructor_cannot_cancel_another_instructors_class(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $otherInstructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($instructor)
            ->delete(route('schedules.cancel', $schedule))
            ->assertForbidden();

        $this->assertDatabaseHas('class_schedules', ['id' => $schedule->id]);
    }

    public function test_class_with_attendance_cannot_be_cancelled(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-09');
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);
        ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 201',
            'login_time' => now(),
            'logout_time' => now()->addMinutes(30),
        ]);

        $this->actingAs($instructor)
            ->delete(route('schedules.cancel', $schedule))
            ->assertStatus(422);

        $this->assertDatabaseHas('class_schedules', ['id' => $schedule->id]);
    }

    public function test_student_can_check_in_to_their_assigned_class_station_only(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $assignedTerminal = $this->terminal('PC-07');
        $otherTerminal = $this->terminal('PC-08');
        $academicGroup = AcademicGroup::query()->create(['course' => 'BSCS', 'section' => 'BSCS 2-1']);
        $student->update(['academic_group_id' => $academicGroup->id, 'section' => $academicGroup->section]);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'academic_group_id' => $academicGroup->id,
            'room_number' => 'Lab 01',
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addMinutes(45),
        ]);
        ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'terminal_id' => $assignedTerminal->id,
        ]);

        $this->actingAs($student)
            ->post(route('terminals.check-in', $otherTerminal))
            ->assertStatus(422);

        $this->actingAs($student)
            ->post(route('terminals.check-in', $assignedTerminal))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_sessions', [
            'user_id' => $student->id,
            'terminal_id' => $assignedTerminal->id,
            'schedule_id' => $schedule->id,
        ]);
    }

    public function test_student_check_in_during_active_class_assigns_their_station_on_the_class_roster(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-10');
        $academicGroup = AcademicGroup::query()->create(['course' => 'BSCS', 'section' => 'BSCS 2-1']);
        $student->update(['academic_group_id' => $academicGroup->id, 'section' => $academicGroup->section]);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'academic_group_id' => $academicGroup->id,
            'room_number' => 'Lab 01',
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addMinutes(45),
        ]);
        $enrollment = ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
        ]);

        $this->actingAs($student)
            ->post(route('terminals.check-in', $terminal))
            ->assertRedirect();

        $this->assertDatabaseHas('schedule_enrollments', [
            'id' => $enrollment->id,
            'terminal_id' => $terminal->id,
        ]);
        $this->assertDatabaseHas('activity_sessions', [
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
        ]);

        $this->actingAs($instructor)
            ->get(route('schedules.students.index', $schedule))
            ->assertOk()
            ->assertSee('PC-10');
    }

    public function test_fault_report_flags_station_and_admin_can_resolve_it(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $terminal = $this->terminal('PC-05');

        $this->actingAs($student)
            ->post(route('tickets.store'), [
                'terminal_id' => $terminal->id,
                'issue_type' => 'Keyboard',
                'description' => 'A key is stuck.',
            ])
            ->assertRedirect();

        $ticket = MaintenanceTicket::query()->firstOrFail();
        $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'status' => 'maintenance']);

        $this->actingAs($admin)
            ->patch(route('tickets.update', $ticket), ['status' => 'resolved', 'priority' => 'normal'])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenance_tickets', ['id' => $ticket->id, 'status' => 'resolved']);
        $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'status' => 'available']);
    }

    public function test_expiration_command_checks_out_class_sessions_after_class_end(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $terminal = $this->terminal('PC-06');
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 101',
            'section' => 'A',
            'room_number' => 'Lab 01',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);
        ScheduleEnrollment::query()->create(['schedule_id' => $schedule->id, 'user_id' => $student->id]);
        $session = ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 101',
            'login_time' => now()->subHours(2),
        ]);

        $this->artisan('lab:sessions:expire')->assertSuccessful();

        $this->assertDatabaseHas('activity_sessions', [
            'id' => $session->id,
            'logout_time' => now()->toDateTimeString(),
        ]);
    }

    private function terminal(string $name): Terminal
    {
        $position = Terminal::query()->count();

        return Terminal::query()->create([
            'pc_name' => $name,
            'grid_x' => ($position % 8) + 1,
            'grid_y' => intdiv($position, 8) + 1,
            'status' => 'available',
        ]);
    }
}
