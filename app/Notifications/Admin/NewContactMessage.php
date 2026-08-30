<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\Admin\ContactMessage;
use Illuminate\Support\Str;

/**
 * Sent to every admin (User::admins()) when a visitor submits the public
 * contact form -- fired from ContactMessageObserver::created(), not from
 * ContactController itself, per the deferral documented on ContactMessage.
 */
class NewContactMessage extends AdminAlert
{
    public function __construct(ContactMessage $message)
    {
        parent::__construct(
            title: __('New contact message'),
            body: __(':name: :excerpt', [
                'name' => $message->name,
                'excerpt' => Str::limit($message->message, 100),
            ]),
            actionUrl: route('admin.contact-messages.show', $message),
            actionLabel: __('View message'),
            icon: 'mail',
        );
    }
}
