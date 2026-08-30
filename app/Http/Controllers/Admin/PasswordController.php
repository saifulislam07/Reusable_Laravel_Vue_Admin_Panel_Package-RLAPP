<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PasswordUpdateRequest;
use App\Models\User;
use App\Support\Admin\AuthLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PasswordController extends AdminController
{
    public function __construct(private readonly AuthLogger $log) {}

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $newPassword = $request->validated('password');

        $user->forceFill(['password' => $newPassword])->save();

        // A password change is exactly the situation "log out other
        // devices" (the dedicated button on this same page) exists for --
        // doing it automatically here means a session hijacked before the
        // change can't keep riding the old one out.
        Auth::guard((string) config('admin-kit.guard'))->logoutOtherDevices($newPassword);

        $this->log->passwordChanged($request, $user);

        return $this->redirectWithSuccess('admin.profile.edit', __('Password updated.'));
    }
}
