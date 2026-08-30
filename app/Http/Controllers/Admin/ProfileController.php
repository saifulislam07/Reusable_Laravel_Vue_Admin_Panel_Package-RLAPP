<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Admin\AuthLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service only: every authenticated admin manages their own account
 * here regardless of role or permission -- this is not an admin capability
 * over other users (that's UserController, Phase 8), so nothing in this
 * controller is permission-gated.
 */
class ProfileController extends AdminController
{
    public function __construct(private readonly AuthLogger $log) {}

    public function edit(Request $request): View
    {
        return view('admin::pages.profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $emailChanged = $request->validated('email') !== $user->email;

        $user->fill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ]);

        if ($emailChanged) {
            // Nothing in the admin panel currently enforces email
            // verification (unlike the public app's Fortify side), but
            // resetting this keeps the column meaningful if that ever
            // changes, rather than leaving a stale "verified" timestamp
            // against an address nobody has actually confirmed.
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $this->log->profileUpdated($request, $user, ['email']);
        } else {
            $this->log->profileUpdated($request, $user, ['name']);
        }

        return $this->redirectWithSuccess('admin.profile.edit', __('Profile updated.'));
    }

    /**
     * Ends every other session for this account. Requires the current
     * password again -- see AuthenticateSession (admin-kit.route.auth_middleware)
     * for how this actually takes effect on those other sessions, not just
     * this one.
     */
    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        // Named bag: this page also has profile and password forms with
        // their own "current_password" field -- see PasswordUpdateRequest.
        $request->validateWithBag('logoutOtherDevices', [
            'current_password' => ['required', 'current_password:'.config('admin-kit.guard')],
        ]);

        /** @var User $user */
        $user = $request->user();

        Auth::guard((string) config('admin-kit.guard'))
            ->logoutOtherDevices($request->string('current_password')->value());

        $this->log->otherDevicesLoggedOut($request, $user);

        return $this->redirectWithSuccess('admin.profile.edit', __('Signed out of all other sessions.'));
    }
}
