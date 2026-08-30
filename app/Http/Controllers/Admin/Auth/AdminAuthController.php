<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\Auth\LoginRequest;
use App\Support\Admin\AuthLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Session login/logout for the admin panel.
 *
 * Intentionally independent of Fortify: the panel owns its own screens,
 * throttle key and redirect targets so the two can diverge freely.
 */
class AdminAuthController extends AdminController
{
    public function __construct(private readonly AuthLogger $log) {}

    public function showLoginForm(): View
    {
        return view('admin::auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var Authenticatable $user */
        $user = Auth::guard($request->guard())->user();

        if ($challenge = $this->twoFactorChallenge($request, $user)) {
            return $challenge;
        }

        $this->log->loggedIn($request, $user, $request->remember());

        return redirect()->intended($this->redirectTo());
    }

    public function logout(Request $request): RedirectResponse
    {
        $guard = (string) config('admin-kit.guard', 'web');
        $user = Auth::guard($guard)->user();

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->log->loggedOut($request, $user);

        $this->flashSuccess(__('You have been signed out.'));

        return redirect()->route((string) config('admin-kit.route.login'));
    }

    /**
     * Extension point for a second factor.
     *
     * Returning a RedirectResponse here diverts the user to a challenge
     * screen instead of completing the login. Left unimplemented on purpose:
     * config('admin-kit.auth.two_factor') is the switch, and wiring it to
     * laravel/fortify's TwoFactorAuthenticatable is a later phase.
     */
    protected function twoFactorChallenge(Request $request, Authenticatable $user): ?RedirectResponse
    {
        if (! config('admin-kit.auth.two_factor', false)) {
            return null;
        }

        return null;
    }

    protected function redirectTo(): string
    {
        return route((string) config('admin-kit.route.home'));
    }
}
