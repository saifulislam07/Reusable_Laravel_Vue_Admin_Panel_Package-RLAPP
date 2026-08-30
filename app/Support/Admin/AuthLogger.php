<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records admin account activity: sign-in/out, and (Phase 9) the
 * self-service changes an admin makes to their own account.
 *
 * Was a thin seam over the plain PSR logger; Phase 18 fills it in with
 * spatie/laravel-activitylog entries (log_name 'auth') without touching any
 * of the callers below.
 */
class AuthLogger
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function loggedIn(Request $request, Authenticatable $user, bool $remember): void
    {
        $this->write('admin.login', $request, ['remember' => $remember], $user);
    }

    public function loggedOut(Request $request, ?Authenticatable $user): void
    {
        $this->write('admin.logout', $request, [], $user);
    }

    public function failed(Request $request, string $email): void
    {
        $this->write('admin.login_failed', $request, ['email' => $email]);
    }

    public function lockedOut(Request $request, string $email, int $availableIn): void
    {
        $this->write('admin.login_lockout', $request, [
            'email' => $email,
            'available_in' => $availableIn,
        ]);
    }

    public function suspended(Request $request, Authenticatable $user): void
    {
        $this->write('admin.login_suspended', $request, [], $user);
    }

    public function passwordResetRequested(Request $request, string $email): void
    {
        $this->write('admin.password_reset_requested', $request, ['email' => $email]);
    }

    public function passwordReset(Request $request, Authenticatable $user): void
    {
        $this->write('admin.password_reset', $request, [], $user);
    }

    /**
     * @param  list<string>  $fields  Which fields actually changed --
     *                                'email' is logged separately from
     *                                'name' since it's the more consequential
     *                                one (account recovery goes there).
     */
    public function profileUpdated(Request $request, Authenticatable $user, array $fields): void
    {
        $this->write('admin.profile_updated', $request, ['fields' => $fields], $user);
    }

    public function passwordChanged(Request $request, Authenticatable $user): void
    {
        $this->write('admin.password_changed', $request, [], $user);
    }

    public function otherDevicesLoggedOut(Request $request, Authenticatable $user): void
    {
        $this->write('admin.other_devices_logged_out', $request, [], $user);
    }

    /**
     * $subject doubles as the causer -- every one of these events either
     * IS the acting user doing something to their own account, or (login
     * failure/lockout/reset-request) happens before there's an
     * authenticated identity to attribute it to at all, in which case both
     * stay null and the entry records as anonymous.
     *
     * @param  array<string, mixed>  $context
     */
    protected function write(string $event, Request $request, array $context = [], ?Authenticatable $subject = null): void
    {
        if (! $this->config->get('admin-kit.auth.log_attempts', true)) {
            return;
        }

        $logger = activity('auth')
            ->event($event)
            ->withProperties($context + [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

        if ($subject instanceof Model) {
            $logger->performedOn($subject)->causedBy($subject);
        }

        $logger->log($this->describe($event));
    }

    private function describe(string $event): string
    {
        return match ($event) {
            'admin.login' => __('Signed in'),
            'admin.logout' => __('Signed out'),
            'admin.login_failed' => __('Login attempt failed'),
            'admin.login_lockout' => __('Login temporarily locked out'),
            'admin.login_suspended' => __('Login blocked: account suspended'),
            'admin.password_reset_requested' => __('Password reset requested'),
            'admin.password_reset' => __('Password reset completed'),
            'admin.profile_updated' => __('Profile updated'),
            'admin.password_changed' => __('Password changed'),
            'admin.other_devices_logged_out' => __('Signed out of other devices'),
            default => $event,
        };
    }
}
