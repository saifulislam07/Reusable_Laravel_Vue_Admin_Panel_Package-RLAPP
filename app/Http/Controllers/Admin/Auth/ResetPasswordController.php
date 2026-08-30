<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\Auth\ResetPasswordRequest;
use App\Support\Admin\AuthLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends AdminController
{
    public function __construct(private readonly AuthLogger $log) {}

    public function show(Request $request, string $token): View
    {
        return view('admin::auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker($this->broker())->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            // The broker hands back whatever the user provider resolves: an
            // Eloquent model that is both authenticatable and resettable.
            function (Authenticatable&CanResetPassword&Model $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                Event::dispatch(new PasswordReset($user));

                $this->log->passwordReset($request, $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        $this->flashSuccess(__('Your password has been reset. You can sign in now.'));

        return redirect()->route((string) config('admin-kit.route.login'));
    }

    protected function broker(): ?string
    {
        return config('admin-kit.auth.password_broker');
    }
}
