<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ConfirmAdminNameChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_name_changes_only_after_confirming_the_email_link(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'first_name' => 'Lab',
            'last_name' => 'Admin',
            'email' => 'admin@pup.edu.ph',
            'email_verified_at' => now(),
        ]);
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('admin.name-change.store'), [
                'first_name' => 'Campus',
                'last_name' => 'Manager',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Check your current email address for a link to confirm the name change.');

        Notification::assertSentTo($admin, ConfirmAdminNameChange::class);
        $notification = Notification::sent($admin, ConfirmAdminNameChange::class)->first();
        $this->assertNotNull($notification);
        $this->assertSame('Lab Admin', $admin->fresh()->name);

        $this->get($notification->url)
            ->assertOk()
            ->assertSee('Confirm your name change.')
            ->assertSee('Campus Manager');
        $this->assertSame('Lab Admin', $admin->fresh()->name);

        $this->post($notification->url)
            ->assertOk()
            ->assertSee('Your name has been updated.')
            ->assertSee('Campus Manager');

        $this->assertSame('Campus', $admin->fresh()->first_name);
        $this->assertSame('Manager', $admin->fresh()->last_name);
        $this->assertSame('Campus Manager', $admin->fresh()->name);
    }

    public function test_admin_name_change_confirmation_link_is_one_time_use(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        Notification::fake();

        $this->actingAs($admin)->post(route('admin.name-change.store'), [
            'first_name' => 'Updated',
            'last_name' => 'Admin',
        ])->assertRedirect();

        $notification = Notification::sent($admin, ConfirmAdminNameChange::class)->first();
        $this->assertNotNull($notification);

        $this->post($notification->url)->assertOk();
        $this->post($notification->url)->assertGone();
    }

    public function test_admin_name_change_rejects_an_invalid_or_expired_signed_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $expiredUrl = URL::temporarySignedRoute(
            'admin.name-change.confirm',
            now()->subMinute(),
            ['token' => 'unknown-token'],
        );

        $this->get($expiredUrl)->assertForbidden();
        $this->get(route('admin.name-change.confirm', ['token' => 'invalid-token']))
            ->assertForbidden();
        $this->assertSame($admin->name, $admin->fresh()->name);
    }

    public function test_non_admin_cannot_request_an_admin_name_change(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('admin.name-change.store'), [
                'first_name' => 'Changed',
                'last_name' => 'Name',
            ])
            ->assertForbidden();
    }
}
