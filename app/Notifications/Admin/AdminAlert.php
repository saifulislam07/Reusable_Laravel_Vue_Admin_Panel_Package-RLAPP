<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one shape every admin-facing alert takes: a title, a body, and an
 * optional "go look at this" action -- shown in the topbar dropdown /
 * admin::pages.notifications.index (database channel) and mirrored to
 * email (mail channel) so an admin who isn't watching the panel still
 * hears about it.
 *
 * Concrete and constructor-driven rather than abstract so a caller that
 * doesn't need a dedicated subclass can just `new AdminAlert(...)` --
 * SystemAlert/NewContactMessage/NewUserRegistered exist only because they
 * each have a fixed shape worth naming and reusing, not because AdminAlert
 * itself is incomplete.
 */
class AdminAlert extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
        public readonly string $icon = 'bell',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'icon' => $this->icon,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        if ($this->actionUrl !== null) {
            $mail->action($this->actionLabel ?? __('View'), $this->actionUrl);
        }

        return $mail;
    }
}
