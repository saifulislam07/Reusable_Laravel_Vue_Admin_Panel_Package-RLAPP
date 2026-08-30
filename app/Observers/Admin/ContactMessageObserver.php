<?php

declare(strict_types=1);

namespace App\Observers\Admin;

use App\Models\Admin\ContactMessage;
use App\Models\User;
use App\Notifications\Admin\NewContactMessage;
use Illuminate\Support\Facades\Notification;

/**
 * The hook ContactMessage's own docblock promised Phase 17 would add:
 * Eloquent already fires `eloquent.created: ContactMessage` on every public
 * submission (ContactController::store()), so nothing on that side had to
 * change.
 */
class ContactMessageObserver
{
    public function created(ContactMessage $contactMessage): void
    {
        Notification::send(User::query()->admins()->get(), new NewContactMessage($contactMessage));
    }
}
