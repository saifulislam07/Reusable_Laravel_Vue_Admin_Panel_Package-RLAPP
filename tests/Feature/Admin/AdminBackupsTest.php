<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\Admin\BackupAlert;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Backups (Phase 19): the listing/download/delete UI over whatever files
 * live on config('backup.backup.destination.disks'), and the notification
 * bridge from spatie/laravel-backup's own events to BackupAlert. Doesn't
 * exercise the real `backup:run` command -- that shells out to a database
 * dump tool the test environment's sqlite :memory: connection has nothing
 * for -- so BackupController::run() is covered only for its route/
 * permission/redirect behaviour, and the notification pipeline is proven
 * by firing the package's own events directly, exactly as a real run would.
 */
class AdminBackupsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsManager(): User
    {
        Permission::findOrCreate('backups.manage', config('admin-kit.guard'));

        $user = User::factory()->create();
        $user->givePermissionTo('backups.manage');
        $this->actingAs($user);

        return $user;
    }

    private function backupName(): string
    {
        return (string) config('backup.backup.name');
    }

    private function putFakeBackup(string $filename): void
    {
        Storage::disk('local')->put($this->backupName().'/'.$filename, 'not a real zip, just test bytes');
    }

    // -- access control -----------------------------------------------------

    public function test_guests_cannot_reach_backups()
    {
        $this->get(route('admin.backups.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_the_permission_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.backups.index'))->assertForbidden();
    }

    // -- listing --------------------------------------------------------------

    public function test_the_index_lists_backups_on_the_configured_disk()
    {
        Storage::fake('local');
        $this->actingAsManager();

        $filename = now()->format('Y-m-d-H-i-s').'.zip';
        $this->putFakeBackup($filename);

        $this->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee($filename)
            ->assertSee('local');
    }

    public function test_the_index_shows_an_empty_state_when_there_are_no_backups()
    {
        Storage::fake('local');
        $this->actingAsManager();

        $this->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('No backups yet');
    }

    // -- download / delete ----------------------------------------------------

    public function test_a_backup_can_be_downloaded()
    {
        Storage::fake('local');
        $this->actingAsManager();
        $filename = now()->format('Y-m-d-H-i-s').'.zip';
        $this->putFakeBackup($filename);

        $this->get(route('admin.backups.download', ['disk' => 'local', 'file' => $filename]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_downloading_a_missing_backup_is_a_404()
    {
        Storage::fake('local');
        $this->actingAsManager();

        $this->get(route('admin.backups.download', ['disk' => 'local', 'file' => 'does-not-exist.zip']))
            ->assertNotFound();
    }

    public function test_a_backup_can_be_deleted()
    {
        Storage::fake('local');
        $this->actingAsManager();
        $filename = now()->format('Y-m-d-H-i-s').'.zip';
        $this->putFakeBackup($filename);

        $this->delete(route('admin.backups.destroy', ['disk' => 'local', 'file' => $filename]))
            ->assertRedirect(route('admin.backups.index'));

        Storage::disk('local')->assertMissing($this->backupName().'/'.$filename);
    }

    /**
     * $disk is user-controlled route input -- only a disk actually named in
     * config('backup.backup.destination.disks') is reachable, not just any
     * configured filesystem disk (e.g. 'public').
     */
    public function test_a_disk_not_used_for_backups_is_rejected()
    {
        Storage::fake('public');
        $this->actingAsManager();

        $this->get(route('admin.backups.download', ['disk' => 'public', 'file' => 'whatever.zip']))
            ->assertNotFound();
    }

    /**
     * basename() on the route segment stops a crafted filename from
     * escaping the backup directory.
     */
    public function test_path_traversal_in_the_filename_is_blocked()
    {
        Storage::fake('local');
        $this->actingAsManager();
        Storage::disk('local')->put('.env', 'APP_KEY=should-not-be-reachable');

        $this->get(route('admin.backups.download', ['disk' => 'local', 'file' => '..%2F..%2F.env']))
            ->assertNotFound();
    }

    // -- run --------------------------------------------------------------------

    /**
     * Exercises the real `backup:run` command (matching this controller's
     * actual production call), scoped down first so it finishes quickly in
     * a test run: a tiny file source and no database dump, since the test
     * environment's sqlite :memory: connection has no file for a dump tool
     * to read from anyway.
     */
    public function test_running_a_backup_redirects_back_to_the_index()
    {
        Storage::fake('local');
        $this->actingAsManager();
        config()->set('backup.backup.source.databases', []);
        config()->set('backup.backup.source.files.include', [base_path('routes')]);

        $this->post(route('admin.backups.run'))->assertRedirect(route('admin.backups.index'));
    }

    // -- notification bridge (BackupEventSubscriber) ---------------------------

    public function test_a_successful_backup_notifies_every_admin_exactly_once()
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', config('admin-kit.guard')));

        event(new BackupWasSuccessful('local', $this->backupName()));

        // assertSentToTimes, not assertSentTo -- this is the exact
        // assertion that would have caught a real bug found via live smoke
        // test: event auto-discovery (Laravel's default) independently
        // wired up BackupEventSubscriber's handleSuccess()/handleFailure()
        // on top of its own explicit Event::subscribe() registration,
        // silently sending every backup notification twice. assertSentTo
        // alone only checks "at least once" and would have stayed green.
        // Fixed in bootstrap/app.php via ->withEvents(discover: false).
        Notification::assertSentToTimes($admin, BackupAlert::class, 1);
        Notification::assertSentTo(
            $admin,
            BackupAlert::class,
            fn ($notification) => $notification->title === __('Backup completed'),
        );
    }

    public function test_a_failed_backup_notifies_every_admin_exactly_once_with_the_error_message()
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('Admin', config('admin-kit.guard')));

        event(new BackupHasFailed(new Exception('Disk full'), 'local', $this->backupName()));

        Notification::assertSentToTimes($admin, BackupAlert::class, 1);
        Notification::assertSentTo(
            $admin,
            BackupAlert::class,
            fn ($notification) => $notification->title === __('Backup failed') && str_contains($notification->body, 'Disk full'),
        );
    }
}
