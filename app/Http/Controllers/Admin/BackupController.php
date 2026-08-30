<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Lists, downloads, deletes, and manually triggers backups (Phase 19) --
 * the files themselves live on whichever disks config('backup.backup.
 * destination.disks') names, not in a database table, so this reads the
 * filesystem directly through spatie/laravel-backup's own BackupDestination
 * rather than HasDataTable (which needs an Eloquent query). Notifications
 * on success/failure are handled separately by BackupEventSubscriber.
 */
class BackupController extends AdminController
{
    public function index(): View
    {
        $backups = $this->backups();

        return view('admin::pages.backups.index', [
            'backups' => $backups,
            'totalSizeBytes' => $backups->sum('sizeBytes'),
        ]);
    }

    /**
     * Runs synchronously, matching this panel's AJAX-free, no-queue
     * convention (GenerateSitemap, PruneActivityLog) -- a full backup of a
     * small-to-medium site finishes well within a normal request timeout.
     */
    public function run(): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('backup:run');

            if ($exitCode === 0) {
                $this->flashSuccess(__('Backup completed.'));
            } else {
                $this->flashError(__('Backup finished with errors. See the activity log or backup notification for details.'));
            }
        } catch (Throwable $e) {
            $this->flashError(__('Backup failed: :error', ['error' => $e->getMessage()]));
        }

        return redirect()->route('admin.backups.index');
    }

    public function download(string $disk, string $file): StreamedResponse
    {
        $path = $this->pathFor($disk, $file);

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path);
    }

    public function destroy(string $disk, string $file): RedirectResponse
    {
        $path = $this->pathFor($disk, $file);

        Storage::disk($disk)->delete($path);

        return $this->redirectWithSuccess('admin.backups.index', __('Backup deleted.'));
    }

    /**
     * Two guards before either route touches the filesystem: $disk must be
     * one of the disks this app actually backs up to (not an arbitrary
     * configured disk like 'public'), and basename() on $file stops a
     * crafted "../../.env" from reaching outside the backup directory.
     * Both segments are user-controlled route input.
     */
    private function pathFor(string $disk, string $file): string
    {
        abort_unless(
            in_array($disk, (array) config('backup.backup.destination.disks', ['local']), true),
            404,
        );

        return $this->backupName().'/'.basename($file);
    }

    private function backupName(): string
    {
        return (string) config('backup.backup.name');
    }

    /**
     * Built as a plain array first and wrapped in collect() only at the
     * end, not chained through ->flatMap()->map() -- Larastan can't trace
     * the generic type through BackupDestination::backups()'s BackupCollection
     * subclass, the same kind of Collection-generic friction noted on
     * RoleController::permissionGroups() and MenuController::tree().
     *
     * @return Collection<int, array{disk: string, file: string, sizeBytes: float, date: Carbon}>
     */
    private function backups(): Collection
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $rows = [];

        foreach ($disks as $disk) {
            $disk = (string) $disk;
            $destination = BackupDestination::create($disk, $this->backupName());

            if ($destination->connectionError !== null) {
                continue;
            }

            foreach ($destination->backups() as $backup) {
                $rows[] = [
                    'disk' => $disk,
                    'file' => basename($backup->path()),
                    'sizeBytes' => $backup->sizeInBytes(),
                    'date' => $backup->date(),
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return collect($rows);
    }
}
