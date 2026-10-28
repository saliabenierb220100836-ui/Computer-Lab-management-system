<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FacultyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_registration_creates_a_separate_unverified_faculty_account(): void
    {
        Notification::fake();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register.faculty'));
        $this->get(route('register.faculty'))
            ->assertOk()
            ->assertSee('@pup.edu.ph')
            ->assertSee('first_name')
            ->assertSee('last_name');

        $this->post(route('register.faculty.store'), $this->registrationData())
            ->assertRedirect(route('verification.notice'));

        $faculty = User::query()->where('student_or_employee_id', 'FA-1234-SR-5678')->firstOrFail();
        $this->assertSame('instructor', $faculty->role);
        $this->assertSame('Jordan', $faculty->first_name);
        $this->assertSame('Rivera', $faculty->last_name);
        $this->assertSame('Jordan Rivera', $faculty->name);
        $this->assertNull($faculty->email_verified_at);
        $this->assertDatabaseHas('faculty', [
            'id' => $faculty->id,
            'first_name' => 'Jordan',
            'last_name' => 'Rivera',
            'email' => 'jordan.rivera@pup.edu.ph',
        ]);
        Notification::assertSentTo($faculty, VerifyEmail::class);
    }

    public function test_faculty_registration_rejects_non_pup_email_and_invalid_employee_number(): void
    {
        $this->from(route('register.faculty'))
            ->post(route('register.faculty.store'), $this->registrationData([
                'email' => 'faculty@example.com',
                'student_or_employee_id' => 'FA-1234',
            ]))
            ->assertRedirect(route('register.faculty'))
            ->assertSessionHasErrors(['email', 'student_or_employee_id']);

        $this->assertDatabaseCount('faculty', 0);
    }

    public function test_faculty_registration_rejects_passwords_outside_the_required_policy(): void
    {
        $this->from(route('register.faculty'))
            ->post(route('register.faculty.store'), $this->registrationData([
                'password' => 'lowercase12345',
                'password_confirmation' => 'lowercase12345',
            ]))
            ->assertRedirect(route('register.faculty'))
            ->assertSessionHasErrors('password');

        $this->from(route('register.faculty'))
            ->post(route('register.faculty.store'), $this->registrationData([
                'password' => 'TooLong!Password2026',
                'password_confirmation' => 'TooLong!Password2026',
            ]))
            ->assertRedirect(route('register.faculty'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('faculty', 0);
    }

    public function test_faculty_registration_requires_all_password_character_types(): void
    {
        foreach (['lowercase12345!', 'UPPERCASE12345!', 'NoNumber!Abcde', 'NoSpecial12345A'] as $password) {
            $this->from(route('register.faculty'))
                ->post(route('register.faculty.store'), $this->registrationData([
                    'password' => $password,
                    'password_confirmation' => $password,
                ]))
                ->assertRedirect(route('register.faculty'))
                ->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('faculty', 0);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jordan',
            'last_name' => 'Rivera',
            'student_or_employee_id' => 'FA-1234-SR-5678',
            'email' => 'jordan.rivera@pup.edu.ph',
            'password' => 'C0mputer!Lab2026',
            'password_confirmation' => 'C0mputer!Lab2026',
        ], $overrides);
    }
}
