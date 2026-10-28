<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminAcademicGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_unused_academic_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = AcademicGroup::query()->create(['course' => 'BSCS', 'section' => 'BSCS 1-1']);

        $this->actingAs($admin)
            ->get(route('workspace.page', 'academic-groups'))
            ->assertOk()
            ->assertSee('Courses & sections')
            ->assertSee('BSCS · BSCS 1-1');

        $this->actingAs($admin)->post(route('admin.academic-groups.store'), [
            'course' => 'BSIT',
            'section' => 'BSIT 3-1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.academic-groups.update', $group), [
            'course' => 'BSCS',
            'section' => 'BSCS 1-1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('academic_groups', ['id' => $group->id, 'section' => 'BSCS 1-1']);

        $this->actingAs($admin)->delete(route('admin.academic-groups.destroy', $group))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('academic_groups', ['id' => $group->id]);
    }

    public function test_updating_a_group_keeps_student_and_class_section_labels_in_sync(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = AcademicGroup::query()->create(['course' => 'BSCS', 'section' => 'BSCS 1-1']);
        $student = User::factory()->create([
            'academic_group_id' => $group->id,
            'section' => 'BSCS 1-1',
        ]);
        $schedule = ClassSchedule::query()->create([
            'instructor_id' => User::factory()->create(['role' => 'instructor'])->id,
            'subject_code' => 'CS 101',
            'academic_group_id' => $group->id,
            'section' => 'BSCS 1-1',
            'room_number' => 'Lab 01',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($admin)->patch(route('admin.academic-groups.update', $group), [
            'course' => 'BSCS',
            'section' => 'BSCS 1-1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('BSCS 1-1', $student->fresh()->section);
        $this->assertSame('BSCS 1-1', $schedule->fresh()->section);
        $this->assertSame('BSCS', $schedule->fresh()->academicGroup->course);
    }

    public function test_admin_cannot_delete_a_group_that_is_in_use(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = AcademicGroup::query()->create(['course' => 'BSCS', 'section' => 'BSCS 1-1']);
        User::factory()->create(['academic_group_id' => $group->id]);

        $this->actingAs($admin)
            ->delete(route('admin.academic-groups.destroy', $group))
            ->assertRedirect()
            ->assertSessionHasErrors('academic_group');

        $this->assertDatabaseHas('academic_groups', ['id' => $group->id]);
    }

    public function test_admin_can_import_course_and_section_csv_and_repeated_rows_are_skipped(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT 3-1']);

        $this->actingAs($admin)
            ->post(route('admin.academic-groups.import'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'sections.csv',
                    "course,section\nBSIT,BSIT 3-1\nBSCS,BSCS 2-1\nBSCS,BSCS 2-1\n",
                ),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Import complete: 1 added and 1 already existing or duplicate row(s) skipped.');

        $this->assertDatabaseCount('academic_groups', 2);
        $this->assertDatabaseHas('academic_groups', ['course' => 'BSCS', 'section' => 'BSCS 2-1']);
    }

    public function test_invalid_csv_is_rejected_without_partially_importing_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('workspace.page', 'academic-groups'))
            ->post(route('admin.academic-groups.import'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'sections.csv',
                    "course,section\nBSCS,BSCS 2-1\nBSIT\n",
                ),
            ])
            ->assertRedirect(route('workspace.page', 'academic-groups'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('academic_groups', 0);
    }

    public function test_only_admins_can_manage_academic_groups(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'academic-groups'))
            ->assertForbidden();

        $this->actingAs($instructor)
            ->post(route('admin.academic-groups.store'), ['course' => 'BSCS', 'section' => 'BSCS 1-1'])
            ->assertForbidden();
    }

    public function test_admin_can_download_a_blank_csv_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.academic-groups.template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=academic-groups-template.csv')
            ->assertStreamedContent("course,section\n");
    }

    public function test_admin_must_use_a_section_matching_the_course_and_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('workspace.page', 'academic-groups'))
            ->post(route('admin.academic-groups.store'), [
                'course' => 'BSIT',
                'section' => 'BSCS 3-1',
            ])
            ->assertRedirect(route('workspace.page', 'academic-groups'))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseCount('academic_groups', 0);

        $this->actingAs($admin)
            ->from(route('workspace.page', 'academic-groups'))
            ->post(route('admin.academic-groups.store'), [
                'course' => 'BSIT',
                'section' => 'BSIT-3A',
            ])
            ->assertRedirect(route('workspace.page', 'academic-groups'))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseCount('academic_groups', 0);
    }

    public function test_invalid_csv_sections_reject_the_entire_import(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('workspace.page', 'academic-groups'))
            ->post(route('admin.academic-groups.import'), [
                'file' => UploadedFile::fake()->createWithContent(
                    'sections.csv',
                    "course,section\nBSIT,BSIT 3-1\nBSCS,BSCS 2A\n",
                ),
            ])
            ->assertRedirect(route('workspace.page', 'academic-groups'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('academic_groups', 0);
    }
}
