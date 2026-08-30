<?php

declare(strict_types=1);

namespace App\Console\Commands\Admin;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Retention policy for the Activity Log (Phase 18): reads
 * setting('activity_log_retention_days') -- the System tab, saved through
 * the same SettingsManager as everything else there -- rather than
 * config('activitylog.clean_after_days'), so an admin can change it without
 * a deploy. 0 or unset means "keep forever": nothing is purged.
 *
 * Scheduled daily in routes/console.php, same shape as sitemap:generate.
 */
#[Signature('admin:prune-activity-log')]
#[Description('Delete activity log entries older than the configured retention period')]
class PruneActivityLog extends Command
{
    public function handle(): int
    {
        $days = (int) setting('activity_log_retention_days', 0);

        if ($days <= 0) {
            $this->info('Activity log retention is unset -- nothing purged.');

            return self::SUCCESS;
        }

        $deleted = Activity::query()->where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} activity log ".Str::plural('entry', $deleted)." older than {$days} days.");

        return self::SUCCESS;
    }
}
