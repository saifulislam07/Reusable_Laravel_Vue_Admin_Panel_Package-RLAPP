<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin-login');
    }

    public function test_the_login_screen_renders()
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('nk-auth', escape: false)
            ->assertSee(route('admin.password.request'));
    }

    public function test_guests_hitting_the_panel_are_sent_to_the_admin_login_not_the_app_login()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_the_public_app_still_redirects_to_its_own_login()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_user_can_sign_in()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'correct-horse',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_issues_a_remember_cookie()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'correct-horse',
            'remember' => '1',
        ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertCookie($this->recallerName());
    }

    public function test_remember_me_is_ignored_when_the_feature_is_disabled()
    {
        config()->set('admin-kit.auth.remember', false);

        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'correct-horse',
            'remember' => '1',
        ])->assertCookieMissing($this->recallerName());

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_bad_password_is_rejected()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_per_account_after_the_configured_attempts()
    {
        config()->set('admin-kit.auth.throttle.max_attempts', 3);

        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email' => $user->email,
                'password' => 'wrong',
            ]);
        }

        // The 4th attempt is refused even though the password is now correct.
        $response = $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'correct-horse',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'seconds',
            (string) session('errors')->first('email'),
        );
    }

    public function test_the_throttle_key_is_scoped_to_the_account()
    {
        config()->set('admin-kit.auth.throttle.max_attempts', 2);

        $victim = User::factory()->create(['password' => Hash::make('correct-horse')]);
        $other = User::factory()->create(['password' => Hash::make('correct-horse')]);

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email' => $other->email,
                'password' => 'wrong',
            ]);
        }

        // Locking out one account must not lock out another from the same IP.
        $this->post(route('admin.login.attempt'), [
            'email' => $victim->email,
            'password' => 'correct-horse',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($victim);
    }

    public function test_signed_in_users_are_bounced_off_the_login_screen()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_user_can_sign_out()
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_the_login_screen_honours_the_centered_layout_variant()
    {
        config()->set('admin-kit.theme.auth_layout', 'centered');

        $this->get(route('admin.login'))
            ->assertSee('nk-auth--centered', escape: false)
            ->assertDontSee('nk-auth__aside', escape: false);
    }

    public function test_the_login_screen_honours_the_split_layout_variant()
    {
        config()->set('admin-kit.theme.auth_layout', 'split');

        $this->get(route('admin.login'))
            ->assertSee('nk-auth--split', escape: false)
            ->assertSee('nk-auth__aside', escape: false);
    }

    public function test_successful_and_failed_attempts_are_logged()
    {
        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'correct-horse',
        ]);

        $this->assertSame(1, Activity::query()->where('log_name', 'auth')->where('event', 'admin.login_failed')->count());

        $loginEntry = Activity::query()->where('log_name', 'auth')->where('event', 'admin.login')->firstOrFail();
        $this->assertTrue($user->is($loginEntry->causer));
        $this->assertTrue($user->is($loginEntry->subject));
    }

    public function test_attempt_logging_can_be_switched_off()
    {
        config()->set('admin-kit.auth.log_attempts', false);

        $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

        $this->post(route('admin.login.attempt'), [
            'email' => $user->email,
            'password' => 'correct-horse',
        ]);

        $this->assertSame(0, Activity::query()->where('log_name', 'auth')->count());
    }

    /**
     * The cookie Laravel writes for a remembered session.
     */
    private function recallerName(): string
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard(config('admin-kit.guard'));

        return $guard->getRecallerName();
    }
}
