<?php

declare(strict_types=1);

namespace App\Listeners\Admin;

use App\Models\User;
use App\Notifications\Admin\BackupAlert;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * Bridges spatie/laravel-backup's own events to the admin notification
 * system (Phase 19) -- registered via Event::subscribe() in
 * AppServiceProvider::bootAdminKit() rather than the package's own
 * mail-only notifications (disabled in config/backup.php).
 */
class BackupEventSubscriber
{
    public function handleSuccess(BackupWasSuccessful $event): void
    {
        Notification::send(
            User::query()->admins()->get(),
            new BackupAlert(true, __('Backup completed successfully on disk ":disk".', ['disk' => $event->diskName])),
        );
    }

    public function handleFailure(BackupHasFailed $event): void
    {
        Notification::send(
            User::query()->admins()->get(),
            new BackupAlert(false, __('Backup failed on disk ":disk": :message', [
                'disk' => $event->diskName ?? __('unknown'),
                'message' => $event->exception->getMessage(),
            ])),
        );
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(BackupWasSuccessful::class, [self::class, 'handleSuccess']);
        $events->listen(BackupHasFailed::class, [self::class, 'handleFailure']);
    }
}
