<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password-reset mail for the admin panel.
 *
 * Kept separate from Laravel's built-in ResetPassword notification so the
 * panel's branding and reset URL never bleed into the public app's emails.
 */
class AdminResetPassword extends Notification
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
            ->subject(__(':brand — reset your password', ['brand' => $brand]))
            ->view('admin::mail.reset-password', [
                'brand' => $brand,
                'url' => $this->resetUrl($notifiable),
                'expiresInMinutes' => $this->expiresInMinutes(),
                'greetingName' => $this->greetingName($notifiable),
            ]);
    }

    protected function resetUrl(CanResetPassword $notifiable): string
    {
        return route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * A display name for the greeting line, when the notifiable has one.
     *
     * CanResetPassword promises only an email address, so anything friendlier
     * is best-effort.
     */
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
