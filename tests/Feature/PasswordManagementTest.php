<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_change_their_password_after_signing_in(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('password.change'))
            ->assertOk()
            ->assertSee('Current password');

        $this->actingAs($student)
            ->put(route('password.change.update'), [
                'current_password' => 'C0mputer!Lab2026',
                'password' => 'Change!2026Abcd',
                'password_confirmation' => 'Change!2026Abcd',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('Change!2026Abcd', $student->fresh()->password));
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->from(route('password.change'))
            ->put(route('password.change.update'), [
                'current_password' => 'incorrect-current-password',
                'password' => 'Change!2026Abcd',
                'password_confirmation' => 'Change!2026Abcd',
            ])
            ->assertRedirect(route('password.change'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('C0mputer!Lab2026', $student->fresh()->password));
    }

    public function test_unverified_users_must_verify_their_email_before_changing_password(): void
    {
        $student = User::factory()->unverified()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('password.change'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_forgot_password_sends_a_reset_link_and_reset_updates_separate_account_tables(): void
    {
        Notification::fake();
        $student = User::factory()->create(['role' => 'student']);
        $faculty = User::factory()->create(['role' => 'instructor']);

        foreach ([$student, $faculty] as $account) {
            $this->post(route('password.email'), ['email' => $account->email])
                ->assertRedirect()
                ->assertSessionHas('status');

            $token = null;
            Notification::assertSentTo($account, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            });
            $this->assertIsString($token);

            $newPassword = 'Reset!2026Abcd';
            $this->post(route('password.update'), [
                'token' => $token,
                'email' => $account->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])->assertRedirect(route('login'))->assertSessionHas('status');

            $this->assertTrue(Hash::check($newPassword, $account->fresh()->password));
        }
    }

    public function test_login_page_offers_password_recovery(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Email password-reset link');
    }

    public function test_login_rejects_passwords_that_do_not_match_the_password_policy(): void
    {
        $student = User::factory()->create();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'identity' => $student->email,
                'password' => 'weakpassword',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_password_change_rejects_passwords_longer_than_sixteen_characters(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->from(route('password.change'))
            ->put(route('password.change.update'), [
                'current_password' => 'C0mputer!Lab2026',
                'password' => 'TooLong!Password2026',
                'password_confirmation' => 'TooLong!Password2026',
            ])
            ->assertRedirect(route('password.change'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('C0mputer!Lab2026', $student->fresh()->password));
    }
}
