<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_a_class_subject_and_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = $this->schedule(User::factory()->create(['role' => 'instructor']));
        $startsAt = now()->addDays(3)->startOfHour();
        $endsAt = $startsAt->copy()->addHour();

        $this->actingAs($admin)
            ->patch(route('schedules.update', $schedule), [
                'subject_code' => 'CS 302',
                'starts_at' => $startsAt->format('Y-m-d\TH:i'),
                'ends_at' => $endsAt->format('Y-m-d\TH:i'),
                'edit_schedule_id' => $schedule->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('CS 302', $schedule->fresh()->subject_code);
        $this->assertSame($startsAt->toDateTimeString(), $schedule->fresh()->starts_at->toDateTimeString());
        $this->assertSame($endsAt->toDateTimeString(), $schedule->fresh()->ends_at->toDateTimeString());
    }

    public function test_instructor_can_edit_their_own_class_but_not_another_instructors_class(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $schedule = $this->schedule($instructor);
        $newStart = now()->addDays(2)->startOfHour();

        $this->actingAs($instructor)
            ->get(route('workspace.page', 'classes'))
            ->assertOk()
            ->assertSee('Edit class')
            ->assertSee(route('schedules.update', $schedule));

        $this->actingAs($instructor)
            ->patch(route('schedules.update', $schedule), [
                'subject_code' => 'CS 303',
                'starts_at' => $newStart->format('Y-m-d\TH:i'),
                'ends_at' => $newStart->copy()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('CS 303', $schedule->fresh()->subject_code);

        $otherSchedule = $this->schedule(User::factory()->create(['role' => 'instructor']));
        $this->actingAs($instructor)
            ->patch(route('schedules.update', $otherSchedule), [
                'subject_code' => 'CS 304',
                'starts_at' => now()->addDays(4)->startOfHour()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(4)->startOfHour()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();

        $this->assertSame('CS 201', $otherSchedule->fresh()->subject_code);
    }

    public function test_class_cannot_be_rescheduled_into_another_classes_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstSchedule = $this->schedule(User::factory()->create(['role' => 'instructor']));
        $conflictingSchedule = $this->schedule(
            User::factory()->create(['role' => 'instructor']),
            $firstSchedule->starts_at->copy()->addDays(2),
        );

        $this->actingAs($admin)
            ->from(route('workspace.page', 'classes'))
            ->patch(route('schedules.update', $firstSchedule), [
                'subject_code' => 'CS 305',
                'starts_at' => $conflictingSchedule->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $conflictingSchedule->ends_at->format('Y-m-d\TH:i'),
                'edit_schedule_id' => $firstSchedule->id,
            ])
            ->assertRedirect(route('workspace.page', 'classes'))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame('CS 201', $firstSchedule->fresh()->subject_code);
    }

    private function schedule(User $instructor, ?\DateTimeInterface $startsAt = null): ClassSchedule
    {
        $group = AcademicGroup::query()->firstOrCreate([
            'course' => 'BSCS',
            'section' => 'BSCS 2-1',
        ]);
        $startsAt ??= now()->addDay()->startOfHour();

        return ClassSchedule::query()->create([
            'instructor_id' => $instructor->id,
            'subject_code' => 'CS 201',
            'section' => $group->section,
            'academic_group_id' => $group->id,
            'room_number' => 'Lab 01',
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+1 hour'),
        ]);
    }
}
