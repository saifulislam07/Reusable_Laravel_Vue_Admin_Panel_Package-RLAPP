<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\Auth\ForgotPasswordRequest;
use App\Notifications\Admin\AdminResetPassword;
use App\Support\Admin\AuthLogger;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends AdminController
{
    public function __construct(private readonly AuthLogger $log) {}

    public function show(): View
    {
        return view('admin::auth.forgot-password');
    }

    /**
     * Issue a reset token and mail the panel's own notification.
     *
     * The response never distinguishes a known address from an unknown one,
     * so this endpoint cannot be used to enumerate admin accounts.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');

        $status = Password::broker($this->broker())->sendResetLink(
            ['email' => $email],
            // Sending the notification ourselves keeps the admin mail template
            // separate from whatever the public app uses.
            fn (CanResetPassword $user, string $token) => $user->notify(new AdminResetPassword($token)),
        );

        $this->log->passwordResetRequested($request, $email);

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }

    protected function broker(): ?string
    {
        return config('admin-kit.auth.password_broker');
    }
}
