<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\ActivitySession;
use App\Models\ClassSchedule;
use App\Models\ScheduleEnrollment;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_import_verified_students_from_an_official_course_roster(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $academicGroup = $this->academicGroup();
        $student = User::factory()->create([
            'student_or_employee_id' => '2026-54321-SR-0',
            'email' => 'officialstudent@iskolarngbayan.pup.edu.ph',
            'email_verified_at' => now(),
            'academic_group_id' => $academicGroup->id,
        ]);
        $unverifiedStudent = User::factory()->unverified()->create([
            'student_or_employee_id' => '2026-54322-SR-0',
            'email' => 'unverified@iskolarngbayan.pup.edu.ph',
            'academic_group_id' => $academicGroup->id,
        ]);
        $existingButOmittedStudent = User::factory()->create([
            'student_or_employee_id' => '2026-54323-SR-0',
            'email' => 'omitted@iskolarngbayan.pup.edu.ph',
            'academic_group_id' => $academicGroup->id,
        ]);
        $schedule = $this->schedule($instructor);
        $existingEnrollment = ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $existingButOmittedStudent->id,
        ]);
        $activeSession = ActivitySession::query()->create([
            'user_id' => $existingButOmittedStudent->id,
            'terminal_id' => $this->terminal('PC-40')->id,
            'schedule_id' => $schedule->id,
            'purpose' => 'CS 201',
            'login_time' => now()->subMinutes(10),
        ]);

        $this->actingAs($instructor)
            ->post(route('schedules.students.import', $schedule), [
                'roster' => UploadedFile::fake()->createWithContent(
                    'official-roster.csv',
                    "student_number\n{$student->student_or_employee_id}\n{$unverifiedStudent->student_or_employee_id}\n999999\n",
                ),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Roster import complete: 1 verified students matched to the CSV; 1 existing student(s) not in the CSV removed from this class roster.')
            ->assertSessionHas('roster_not_registered', ['999999'])
            ->assertSessionHas('roster_not_eligible', [$unverifiedStudent->student_or_employee_id]);

        $this->assertDatabaseHas('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'source' => 'official_roster_import',
        ]);
        $this->assertDatabaseMissing('schedule_enrollments', ['user_id' => $unverifiedStudent->id]);
        $this->assertDatabaseMissing('schedule_enrollments', ['id' => $existingEnrollment->id]);
        $this->assertNotNull($activeSession->fresh()->logout_time);
        $this->assertDatabaseHas('activity_sessions', [
            'id' => $activeSession->id,
            'schedule_id' => $schedule->id,
        ]);
    }

    private function academicGroup(): AcademicGroup
    {
        return AcademicGroup::query()->firstOrCreate([
            'course' => 'BSCS',
            'section' => 'BSCS 2-1',
        ]);
    }

    public function test_csv_import_does_not_add_registered_students_who_are_not_listed_in_the_file(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $academicGroup = $this->academicGroup();
        $listedStudent = User::factory()->create([
            'student_or_employee_id' => '2026-54321-SR-0',
            'email' => 'listed@iskolarngbayan.pup.edu.ph',
            'academic_group_id' => $academicGroup->id,
        ]);
        $unlistedStudent = User::factory()->create([
            'student_or_employee_id' => '2026-54322-SR-0',
            'email' => 'unlisted@iskolarngbayan.pup.edu.ph',
        ]);
        $schedule = $this->schedule($instructor);

        $this->actingAs($instructor)
            ->post(route('schedules.students.import', $schedule), [
                'roster' => UploadedFile::fake()->createWithContent(
                    'official-roster.csv',
                    "student_number\n{$listedStudent->student_or_employee_id}\n",
                ),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $listedStudent->id,
        ]);
        $this->assertDatabaseMissing('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $unlistedStudent->id,
        ]);
    }

    public function test_csv_import_reports_students_who_belong_to_another_course_or_section(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $classGroup = $this->academicGroup();
        $otherGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT 2-1']);
        $student = User::factory()->create([
            'student_or_employee_id' => '2026-54324-SR-0',
            'email' => 'othergroup@iskolarngbayan.pup.edu.ph',
            'academic_group_id' => $otherGroup->id,
        ]);
        $schedule = $this->schedule($instructor);
        $this->assertSame($classGroup->id, $schedule->academic_group_id);

        $this->actingAs($instructor)
            ->post(route('schedules.students.import', $schedule), [
                'roster' => UploadedFile::fake()->createWithContent(
                    'official-roster.csv',
                    "student_number\n{$student->student_or_employee_id}\n",
                ),
            ])
            ->assertRedirect()
            ->assertSessionHas('roster_wrong_academic_group', [$student->student_or_employee_id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_sample_third_year_it_roster_contains_forty_valid_unique_student_numbers(): void
    {
        $stream = fopen(base_path('sample-3rd-year-it-roster.csv'), 'r');
        $this->assertNotFalse($stream);
        $header = fgetcsv($stream, 4096, ',', '"', '\\');
        $this->assertSame(['student_number', 'student_name', 'program', 'year_level', 'section'], $header);

        $numbers = [];
        while (($row = fgetcsv($stream, 4096, ',', '"', '\\')) !== false) {
            $numbers[] = $row[0];
            $this->assertMatchesRegularExpression(User::STUDENT_NUMBER_PATTERN, $row[0]);
            $this->assertSame('3', $row[3]);
            $this->assertSame('BSIT 3-1', $row[4]);
        }
        fclose($stream);

        $this->assertCount(40, $numbers);
        $this->assertCount(40, array_unique($numbers));
    }

    public function test_instructor_can_only_view_their_own_class_roster_and_attendance_identity(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create([
            'name' => 'Roster Student Name',
            'student_or_employee_id' => '2026-54321-SR-0',
            'email' => 'rosterstudent@iskolarngbayan.pup.edu.ph',
        ]);
        $terminal = Terminal::query()->create([
            'pc_name' => 'PC-01',
            'grid_x' => 1,
            'grid_y' => 1,
            'status' => 'available',
        ]);
        $schedule = $this->schedule($instructor, true);
        ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
        ]);
        ActivitySession::query()->create([
            'user_id' => $student->id,
            'terminal_id' => $terminal->id,
            'schedule_id' => $schedule->id,
            'purpose' => $schedule->subject_code,
            'login_time' => now()->subMinutes(10),
        ]);

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertSee('Students (1)')
            ->assertDontSee('2026-54321-SR-0')
            ->assertDontSee('Roster Student Name');

        $this->actingAs($instructor)
            ->get(route('schedules.students.index', $schedule))
            ->assertOk()
            ->assertSee('Upload roster')
            ->assertSee('class="roster-import-input" type="file"', false)
            ->assertDontSee('Import roster')
            ->assertSee('Roster Student Name')
            ->assertSee('2026-54321-SR-0')
            ->assertSee('PC-01');

        $this->actingAs($instructor)
            ->get(route('schedules.students.show', [$schedule, $student]))
            ->assertOk()
            ->assertSee('Roster Student Name')
            ->assertSee('rosterstudent@iskolarngbayan.pup.edu.ph')
            ->assertSee('Class attendance')
            ->assertSee('1 recorded session')
            ->assertSee('No certificate image on file');

        $this->actingAs($otherInstructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertDontSee('2026-54321-SR-0')
            ->assertDontSee('Roster Student Name');

        $this->actingAs($student)
            ->get(route('schedules.students.index', $schedule))
            ->assertForbidden();
    }

    public function test_instructor_can_view_certificate_image_only_for_a_student_in_their_class(): void
    {
        Storage::fake('local');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);
        $schedule = $this->schedule($instructor);
        $otherSchedule = $this->schedule($otherInstructor);
        $certificatePath = 'registration-certificates/test-certificate.png';
        Storage::disk('local')->put($certificatePath, file_get_contents(public_path('images/pup-favicon.png')));
        $student->update(['registration_certificate_path' => $certificatePath]);
        ScheduleEnrollment::query()->create(['schedule_id' => $schedule->id, 'user_id' => $student->id]);

        $this->actingAs($instructor)
            ->get(route('schedules.students.certificate', [$schedule, $student]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->actingAs($otherInstructor)
            ->get(route('schedules.students.certificate', [$otherSchedule, $student]))
            ->assertNotFound();
    }

    public function test_student_can_only_enroll_verified_school_accounts(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $unverifiedStudent = User::factory()->unverified()->create();
        $schedule = $this->schedule($instructor);

        $this->actingAs($instructor)
            ->from(route('dashboard'))
            ->post(route('schedules.students.store', $schedule), [
                'student_id' => $unverifiedStudent->student_or_employee_id,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $unverifiedStudent->id,
        ]);
    }

    public function test_instructor_can_reassign_an_enrolled_student_to_another_station(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $firstTerminal = $this->terminal('PC-01');
        $secondTerminal = $this->terminal('PC-02');
        $schedule = $this->schedule($instructor);
        $enrollment = ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'terminal_id' => $firstTerminal->id,
        ]);

        $this->actingAs($instructor)
            ->patch(route('schedules.students.station.update', [$schedule, $enrollment]), [
                'terminal_id' => $secondTerminal->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Student station assignment updated.');

        $this->assertDatabaseHas('schedule_enrollments', [
            'id' => $enrollment->id,
            'terminal_id' => $secondTerminal->id,
        ]);

        $this->actingAs($instructor)
            ->get(route('schedules.students.index', $schedule))
            ->assertOk()
            ->assertSee('Update station')
            ->assertSee('PC-02');
    }

    public function test_instructor_cannot_add_a_student_from_another_academic_group(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $classGroup = $this->academicGroup();
        $otherGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT 2-1']);
        $student->update(['academic_group_id' => $otherGroup->id, 'section' => $otherGroup->section]);
        $schedule = $this->schedule($instructor);

        $this->actingAs($instructor)
            ->from(route('schedules.students.index', $schedule))
            ->post(route('schedules.students.store', $schedule), [
                'student_id' => $student->student_or_employee_id,
            ])
            ->assertRedirect(route('schedules.students.index', $schedule))
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('schedule_enrollments', [
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
        ]);
        $this->assertNotSame($classGroup->id, $otherGroup->id);
    }

    public function test_instructor_can_only_change_a_class_to_an_admin_catalog_group(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $schedule = $this->schedule($instructor);
        $newGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT 2-1']);

        $this->actingAs($instructor)
            ->get(route('schedules.students.index', $schedule))
            ->assertOk()
            ->assertSee('Admin-approved course / section')
            ->assertSee($newGroup->label);

        $this->actingAs($instructor)
            ->patch(route('schedules.academic-group.update', $schedule), [
                'academic_group_id' => $newGroup->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'academic_group_id' => $newGroup->id,
            'section' => 'BSIT 2-1',
        ]);
        $this->assertDatabaseCount('academic_groups', 2);

        $this->actingAs($instructor)
            ->patch(route('schedules.academic-group.update', $schedule), [
                'course' => 'BSCS',
                'section' => 'BSCS 9-9',
            ])
            ->assertSessionHasErrors('academic_group_id');

        $this->assertDatabaseCount('academic_groups', 2);
    }

    public function test_instructor_cannot_assign_a_legacy_malformed_group_to_a_class(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $schedule = $this->schedule($instructor);
        $legacyGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT-3A']);

        $this->actingAs($instructor)
            ->from(route('schedules.students.index', $schedule))
            ->patch(route('schedules.academic-group.update', $schedule), [
                'academic_group_id' => $legacyGroup->id,
            ])
            ->assertRedirect(route('schedules.students.index', $schedule))
            ->assertSessionHasErrors('academic_group_id');

        $this->assertSame($this->academicGroup()->id, $schedule->fresh()->academic_group_id);
    }

    public function test_invalid_legacy_groups_are_not_shown_as_class_choices(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $legacyGroup = AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT-3A']);

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertDontSee('value="'.$legacyGroup->id.'"', false);
    }

    public function test_instructor_cannot_reassign_a_station_occupied_by_another_student(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $firstTerminal = $this->terminal('PC-01');
        $occupiedTerminal = $this->terminal('PC-02');
        $schedule = $this->schedule($instructor);
        $enrollment = ScheduleEnrollment::query()->create([
            'schedule_id' => $schedule->id,
            'user_id' => $student->id,
            'terminal_id' => $firstTerminal->id,
        ]);
        ActivitySession::query()->create([
            'user_id' => $otherStudent->id,
            'terminal_id' => $occupiedTerminal->id,
            'purpose' => 'Open lab',
            'login_time' => now()->subMinutes(5),
        ]);

        $this->actingAs($instructor)
            ->patch(route('schedules.students.station.update', [$schedule, $enrollment]), [
                'terminal_id' => $occupiedTerminal->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('schedule_enrollments', [
            'id' => $enrollment->id,
            'terminal_id' => $firstTerminal->id,
        ]);
    }

    private function schedule(User $instructor, bool $active = false): ClassSchedule
    {
        $academicGroup = $this->academicGroup();

        return ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => 'BSCS 2-1',
            'academic_group_id' => $academicGroup->id,
            'room_number' => 'Lab 01',
            'starts_at' => $active ? now()->subMinutes(15) : now()->addDay(),
            'ends_at' => $active ? now()->addMinutes(45) : now()->addDay()->addHour(),
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
