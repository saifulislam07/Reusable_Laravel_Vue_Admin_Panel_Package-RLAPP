<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

/**
 * Sent to every admin (User::admins()) when a backup run finishes --
 * success or failure -- fired by App\Listeners\Admin\BackupEventSubscriber
 * from spatie/laravel-backup's own BackupWasSuccessful/BackupHasFailed
 * events (Phase 19). This is the "Notification on backup success/failure"
 * bullet, wired through the same AdminAlert system as everything else
 * rather than the package's own mail-only notifications.
 */
class BackupAlert extends AdminAlert
{
    public function __construct(bool $successful, string $body)
    {
        parent::__construct(
            title: $successful ? __('Backup completed') : __('Backup failed'),
            body: $body,
            actionUrl: route('admin.backups.index'),
            actionLabel: __('View backups'),
            icon: $successful ? 'database' : 'alert-triangle',
        );
    }
}
