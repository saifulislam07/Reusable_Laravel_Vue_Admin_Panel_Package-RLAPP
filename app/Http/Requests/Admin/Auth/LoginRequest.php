<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Auth;

use App\Models\User;
use App\Support\Admin\AuthLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and performs an admin login attempt.
 *
 * Throttling is keyed on email + IP and configured through
 * admin-kit.auth.throttle, independent of Fortify's own `login` limiter so
 * the two front doors cannot lock each other out.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Attempt to log the user in, or fail validation.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');
        $guard = Auth::guard($this->guard());

        // validate(), not attempt(): checks the password without starting a
        // session, so a suspended account can be told apart from a wrong
        // password before anything is logged in.
        if (! $guard->validate($credentials)) {
            RateLimiter::hit($this->throttleKey(), $this->decaySeconds());

            app(AuthLogger::class)->failed($this, (string) $this->input('email'));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();

        if ($user->isSuspended()) {
            RateLimiter::hit($this->throttleKey(), $this->decaySeconds());

            app(AuthLogger::class)->suspended($this, $user);

            throw ValidationException::withMessages([
                // Deliberately specific: this is only reachable with a
                // correct password, so the account already isn't a secret
                // from whoever is asking -- being vague here would only
                // frustrate a legitimate suspended admin.
                'email' => __('This account has been suspended.'),
            ]);
        }

        $guard->login($user, $this->remember());

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Whether the session should be remembered, honouring the config switch
     * so a project can turn the feature off entirely.
     */
    public function remember(): bool
    {
        return (bool) config('admin-kit.auth.remember', true)
            && $this->boolean('remember');
    }

    public function guard(): string
    {
        return (string) config('admin-kit.guard', 'web');
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('email')).'|'.$this->ip().'|admin'
        );
    }

    /**
     * @throws ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts())) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        Event::dispatch(new Lockout($this));

        app(AuthLogger::class)->lockedOut($this, (string) $this->input('email'), $seconds);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    protected function maxAttempts(): int
    {
        return max(1, (int) config('admin-kit.auth.throttle.max_attempts', 5));
    }

    protected function decaySeconds(): int
    {
        return max(1, (int) config('admin-kit.auth.throttle.decay_minutes', 1)) * 60;
    }
}
