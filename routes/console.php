<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps public/sitemap.xml in step with published/newly-due-scheduled
// Pages -- see App\Console\Commands\Admin\GenerateSitemap.
Schedule::command('sitemap:generate')->daily();

// Activity Log retention (Phase 18) -- see App\Console\Commands\Admin\
// PruneActivityLog, a no-op until an admin sets a retention period on the
// System settings tab.
Schedule::command('admin:prune-activity-log')->daily();

// Backups (Phase 19) -- 'backup_schedule' (System settings tab) is
// disabled/daily/weekly; this always fires the check at the same daily
// tick and lets ->when() decide whether today is actually a backup day, so
// changing the setting takes effect without touching the server's cron.
// BackupEventSubscriber notifies every admin of the outcome either way.
Schedule::command('backup:run')->daily()->when(function (): bool {
    return match (setting('backup_schedule', 'daily')) {
        'daily' => true,
        'weekly' => now()->isMonday(),
        default => false,
    };
});

// Applies config('backup.cleanup.*')'s own retention strategy -- unrelated
// to (and unaffected by) 'backup_schedule' above, so old backups still get
// pruned even if automatic backups are switched off and an admin is only
// running them manually.
Schedule::command('backup:clean')->daily();
