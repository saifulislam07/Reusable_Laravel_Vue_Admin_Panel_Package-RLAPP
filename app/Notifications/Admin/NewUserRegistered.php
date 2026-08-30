<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\User;

/**
 * Sent to every other admin when a new admin account is created through
 * UserController::store() -- dispatched explicitly there, not via a global
 * User::created observer, because User is shared with the public Fortify
 * registration flow and a global observer would fire for public signups
 * too.
 */
class NewUserRegistered extends AdminAlert
{
    public function __construct(User $user)
    {
        parent::__construct(
            title: __('New admin user added'),
            body: __(':name (:email)', ['name' => $user->name, 'email' => $user->email]),
            actionUrl: route('admin.users.edit', $user),
            actionLabel: __('View user'),
            icon: 'user',
        );
    }
}
