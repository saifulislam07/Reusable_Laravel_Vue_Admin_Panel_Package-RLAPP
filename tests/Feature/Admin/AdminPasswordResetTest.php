<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\Admin\AdminResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin-login');
    }

    public function test_the_forgot_password_screen_renders()
    {
        $this->get(route('admin.password.request'))
            ->assertOk()
            ->assertSee(route('admin.password.email'));
    }

    public function test_it_sends_the_admin_reset_notification_not_the_framework_default()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('admin.password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, AdminResetPassword::class);
        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_the_reset_link_points_at_the_admin_reset_screen()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('admin.password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, AdminResetPassword::class, function (AdminResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user)->render();

            return str_contains($mail, route('admin.password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ]));
        });
    }

    public function test_an_unknown_address_is_not_distinguishable_from_a_known_one()
    {
        Notification::fake();

        $this->post(route('admin.password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_the_reset_screen_renders_with_the_token()
    {
        $this->get(route('admin.password.reset', ['token' => 'a-token', 'email' => 'me@example.com']))
            ->assertOk()
            ->assertSee('a-token', escape: false)
            ->assertSee('me@example.com');
    }

    public function test_a_password_can_be_reset_with_a_valid_token()
    {
        Notification::fake();
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create();

        $this->post(route('admin.password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, AdminResetPassword::class, function (AdminResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect(route('admin.login'));

        Event::assertDispatched(PasswordReset::class);
        $this->assertTrue(Hash::check('a-brand-new-password', $user->fresh()->password));
    }

    public function test_an_invalid_token_is_rejected()
    {
        $user = User::factory()->create(['password' => Hash::make('original')]);

        $this->post(route('admin.password.update'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original', $user->fresh()->password));
    }

    public function test_the_confirmation_must_match()
    {
        $user = User::factory()->create();

        $this->post(route('admin.password.update'), [
            'token' => 'whatever',
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');
    }
}
