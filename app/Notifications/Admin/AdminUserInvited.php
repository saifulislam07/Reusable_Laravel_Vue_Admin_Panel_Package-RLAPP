<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin creates a new user via "Send an invite email" instead
 * of setting a password directly (UserController::store()). The new account
 * is created with an unusable random password; this reuses the password
 * broker's own token mechanism -- the same one AdminResetPassword uses -- so
 * the "set your password" link goes through the exact same reset flow the
 * user would use later if they ever forgot it.
 */
class AdminUserInvited extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        $brand = (string) config('admin-kit.brand.name', config('app.name'));

        return (new MailMessage)
            ->subject(__('You have been invited to :brand', ['brand' => $brand]))
            ->view('admin::mail.user-invited', [
                'brand' => $brand,
                'url' => $this->setPasswordUrl($notifiable),
                'expiresInMinutes' => $this->expiresInMinutes(),
                'greetingName' => $this->greetingName($notifiable),
            ]);
    }

    protected function setPasswordUrl(CanResetPassword $notifiable): string
    {
        return route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    protected function greetingName(CanResetPassword $notifiable): ?string
    {
        if (! $notifiable instanceof Model) {
            return null;
        }

        $name = $notifiable->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    protected function expiresInMinutes(): int
    {
        $broker = config('admin-kit.auth.password_broker') ?: config('auth.defaults.passwords');

        return (int) config("auth.passwords.{$broker}.expire", 60);
    }
}
