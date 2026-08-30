<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_page_renders()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('admin.profile.edit'))->assertRedirect(route('admin.login'));
    }

    public function test_the_name_can_be_updated_without_a_current_password()
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => 'New Name',
                'email' => $user->email,
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_changing_the_email_requires_the_current_password()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => $user->name,
                'email' => 'new-address@example.com',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_changing_the_email_with_the_correct_password_resets_verification_and_updates_it()
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-horse'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => $user->name,
                'email' => 'new-address@example.com',
                'current_password' => 'correct-horse',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('new-address@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_the_email_must_be_unique()
    {
        $other = User::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => $user->name,
                'email' => $other->email,
                'current_password' => 'correct-horse',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_updating_the_name_only_is_still_allowed_when_keeping_the_same_email()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('admin.profile.update'), [
                'name' => 'Same Email Name',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_profile_updates_are_logged()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)->put(route('admin.profile.update'), [
            'name' => $user->name,
            'email' => 'new-address@example.com',
            'current_password' => 'correct-horse',
        ]);

        $entry = Activity::query()->where('log_name', 'auth')->where('event', 'admin.profile_updated')->firstOrFail();
        $this->assertSame(['email'], $entry->properties['fields']);
        $this->assertTrue($user->is($entry->causer));
    }

    public function test_the_password_can_be_changed()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'correct-horse',
                'password' => 'a-new-strong-password',
                'password_confirmation' => 'a-new-strong-password',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-new-strong-password', $user->fresh()->password));
    }

    public function test_the_password_cannot_be_changed_with_the_wrong_current_password()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);
        $originalHash = $user->password;

        $this->actingAs($user)
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'wrong',
                'password' => 'a-new-strong-password',
                'password_confirmation' => 'a-new-strong-password',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_the_new_password_must_be_confirmed()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'correct-horse',
                'password' => 'a-new-strong-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_changing_the_password_forces_a_rehash_which_invalidates_stale_sessions()
    {
        // logoutOtherDevices() -- which a password change also triggers --
        // works by force-rehashing the password column. Any other session
        // still carrying the old hash in "password_hash_web" then fails
        // AuthenticateSession's comparison on its next request. See
        // test_a_stale_password_hash_in_the_session_is_logged_out below for
        // that second half of the mechanism.
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);
        $originalHash = $user->password;

        $this->actingAs($user)->put(route('admin.profile.password.update'), [
            'current_password' => 'correct-horse',
            'password' => 'a-new-strong-password',
            'password_confirmation' => 'a-new-strong-password',
        ]);

        $this->assertNotSame($originalHash, $user->fresh()->password);
    }

    public function test_password_changes_are_logged()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)->put(route('admin.profile.password.update'), [
            'current_password' => 'correct-horse',
            'password' => 'a-new-strong-password',
            'password_confirmation' => 'a-new-strong-password',
        ]);

        $this->assertSame(1, Activity::query()->where('log_name', 'auth')->where('event', 'admin.password_changed')->count());
    }

    public function test_logging_out_other_devices_requires_the_current_password()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);
        $originalHash = $user->password;

        $this->actingAs($user)
            ->post(route('admin.profile.logout-other-devices'), [
                'current_password' => 'wrong',
            ])
            ->assertSessionHasErrorsIn('logoutOtherDevices', 'current_password');

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_logging_out_other_devices_rehashes_the_password_and_keeps_the_current_session_authenticated()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);
        $originalHash = $user->password;

        $response = $this->actingAs($user)->post(route('admin.profile.logout-other-devices'), [
            'current_password' => 'correct-horse',
        ]);

        $response->assertRedirect(route('admin.profile.edit'))->assertSessionHasNoErrors();
        $this->assertNotSame($originalHash, $user->fresh()->password);

        // The current device's own session must survive the rehash --
        // only *other* sessions (still holding the old hash) are meant
        // to be logged out.
        $this->assertAuthenticatedAs($user);
        $this->get(route('admin.profile.edit'))->assertOk();
    }

    public function test_logging_out_other_devices_is_logged()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user)->post(route('admin.profile.logout-other-devices'), [
            'current_password' => 'correct-horse',
        ]);

        $this->assertSame(1, Activity::query()->where('log_name', 'auth')->where('event', 'admin.other_devices_logged_out')->count());
    }

    public function test_a_stale_password_hash_in_the_session_is_logged_out()
    {
        // This is the other half of "log out other devices": once
        // logoutOtherDevices() rehashes the password, any session that
        // isn't refreshed (i.e. a different device, mid-request) is still
        // carrying the *old* hash under this session key. AuthenticateSession
        // compares it against the user's current password on every request
        // and signs the session out on a mismatch -- this only fires because
        // admin-kit.route.auth_middleware includes 'auth.session'.
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->actingAs($user);
        session(['password_hash_'.config('admin-kit.guard') => 'a-stale-and-mismatched-hash']);

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }
}
