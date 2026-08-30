<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-service, like ProfileController: every authenticated admin manages
 * their own notification history regardless of role, so nothing here is
 * permission-gated.
 *
 * Every lookup goes through $request->user()->notifications() rather than
 * route-model-binding a bare DatabaseNotification -- that relation is
 * already scoped to the current notifiable, which is what stops one admin
 * from reading or mutating another admin's notification by guessing its
 * UUID.
 */
class NotificationController extends AdminController
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('admin::pages.notifications.index', ['notifications' => $notifications]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $actionUrl = $record->data['action_url'] ?? null;

        return $actionUrl ? redirect()->to($actionUrl) : redirect()->route('admin.notifications.index');
    }

    public function unread(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsUnread();

        return redirect()->route('admin.notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }
}
