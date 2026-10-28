<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicGroupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicGroupRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_request_a_missing_section_and_admin_can_approve_it(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($instructor)
            ->post(route('academic-group-requests.store'), [
                'course' => ' bsit ',
                'section' => ' bsit 3-1 ',
                'reason' => 'Needed for the next semester.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $groupRequest = AcademicGroupRequest::query()->firstOrFail();
        $this->assertSame('BSIT', $groupRequest->course);
        $this->assertSame('BSIT 3-1', $groupRequest->section);
        $this->assertSame('pending', $groupRequest->status);
        $this->assertDatabaseCount('academic_groups', 0);

        $this->actingAs($admin)
            ->get(route('workspace.page', 'academic-groups'))
            ->assertOk()
            ->assertSee('BSIT 3-1')
            ->assertSee($instructor->name);

        $this->actingAs($admin)
            ->post(route('admin.academic-group-requests.approve', $groupRequest))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('academic_groups', ['course' => 'BSIT', 'section' => 'BSIT 3-1']);
        $this->assertDatabaseHas('academic_group_requests', [
            'id' => $groupRequest->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_instructor_can_see_request_status_and_admin_can_reject_with_a_note(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $groupRequest = AcademicGroupRequest::query()->create([
            'requested_by' => $instructor->id,
            'course' => 'BSCS',
            'section' => 'BSCS 2-1',
            'reason' => 'For a new class.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.academic-group-requests.reject', $groupRequest), [
                'review_note' => 'Please confirm this section with the department.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('academic_group_requests', [
            'id' => $groupRequest->id,
            'status' => 'rejected',
            'review_note' => 'Please confirm this section with the department.',
        ]);

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertSee('Your section requests')
            ->assertSee('Rejected');

        $this->assertDatabaseCount('academic_groups', 0);
    }

    public function test_instructors_cannot_request_a_section_that_is_already_in_the_catalog(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        AcademicGroup::query()->create(['course' => 'BSIT', 'section' => 'BSIT 3-1']);

        $this->actingAs($instructor)
            ->from(route('workspace.page', 'classes'))
            ->post(route('academic-group-requests.store'), [
                'course' => 'BSIT',
                'section' => 'BSIT 3-1',
            ])
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseCount('academic_group_requests', 0);
    }

    public function test_instructor_cannot_submit_duplicate_pending_request(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        AcademicGroupRequest::query()->create([
            'requested_by' => $instructor->id,
            'course' => 'BSCS',
            'section' => 'BSCS 2-1',
        ]);

        $this->actingAs($instructor)
            ->from(route('workspace.page', 'classes'))
            ->post(route('academic-group-requests.store'), [
                'course' => 'BSCS',
                'section' => 'BSCS 2-1',
            ])
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseCount('academic_group_requests', 1);
    }

    public function test_instructor_request_must_follow_the_course_section_format(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($instructor)
            ->from(route('workspace.page', 'classes'))
            ->post(route('academic-group-requests.store'), [
                'course' => 'BSIT',
                'section' => 'BSCS 3-1',
            ])
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('section');

        $this->assertDatabaseCount('academic_group_requests', 0);
    }

    public function test_only_admins_can_approve_or_reject_section_requests(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $anotherInstructor = User::factory()->create(['role' => 'instructor']);
        $groupRequest = AcademicGroupRequest::query()->create([
            'requested_by' => $instructor->id,
            'course' => 'BSCS',
            'section' => 'BSCS 2-1',
        ]);

        $this->actingAs($anotherInstructor)
            ->post(route('admin.academic-group-requests.approve', $groupRequest))
            ->assertForbidden();
        $this->actingAs($anotherInstructor)
            ->post(route('admin.academic-group-requests.reject', $groupRequest))
            ->assertForbidden();

        $this->assertSame('pending', $groupRequest->fresh()->status);
        $this->assertDatabaseCount('academic_groups', 0);
    }
}
