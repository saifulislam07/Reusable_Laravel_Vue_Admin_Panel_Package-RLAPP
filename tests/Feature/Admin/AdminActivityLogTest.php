<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\Admin\PruneActivityLog;
use App\Models\Admin\ContactMessage;
use App\Models\Admin\Page;
use App\Models\Admin\Setting;
use App\Models\User;
use App\Support\Admin\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Activity Log (Phase 18): auto-logging via LogsActivity on
 * User/Page/ContactMessage/Setting, manual logging for Role/Permission
 * (vendor models, no trait to attach), the admin listing/detail UI, and
 * the retention-purge command. AuthLogger's own auth-event logging is
 * covered by AdminLoginTest/AdminProfileTest, not duplicated here.
 */
class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsManager(): User
    {
        Permission::findOrCreate('activity-log.view', config('admin-kit.guard'));

        $user = User::factory()->create();
        $user->givePermissionTo('activity-log.view');
        $this->actingAs($user);

        return $user;
    }

    // -- access control -----------------------------------------------------

    public function test_guests_cannot_reach_the_activity_log()
    {
        $this->get(route('admin.activity-log.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_the_permission_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.activity-log.index'))->assertForbidden();
    }

    // -- auto-logging via LogsActivity ---------------------------------------

    public function test_updating_a_user_logs_name_email_and_status_but_never_the_password()
    {
        $this->actingAsManager();
        $target = User::factory()->create(['name' => 'Old Name']);

        $target->update(['name' => 'New Name', 'password' => 'a-new-strong-password']);

        $entry = Activity::query()->where('log_name', 'users')->where('event', 'updated')->where('subject_id', $target->id)->firstOrFail();
        $this->assertSame(['name' => 'New Name'], $entry->attribute_changes['attributes']);
        $this->assertArrayNotHasKey('password', $entry->attribute_changes['attributes']);
    }

    public function test_creating_a_page_is_logged()
    {
        Page::factory()->create(['title' => 'About Us']);

        $entry = Activity::query()->where('log_name', 'pages')->where('event', 'created')->firstOrFail();
        $this->assertSame('About Us', $entry->attribute_changes['attributes']['title']);
    }

    public function test_a_new_contact_message_is_logged()
    {
        $message = ContactMessage::factory()->create(['name' => 'Grace Hopper']);

        $entry = Activity::query()->where('log_name', 'contact-messages')->where('subject_id', $message->id)->firstOrFail();
        $this->assertSame('created', $entry->event);
    }

    public function test_saving_a_setting_group_logs_one_entry_per_changed_key()
    {
        $settings = $this->app->make(SettingsManager::class);

        $settings->saveGroup('general', ['site_name' => 'Acme Co', 'tagline' => 'Widgets']);

        $this->assertSame(2, Activity::query()->where('log_name', 'settings')->count());
        $siteNameSetting = Setting::query()->where('key', 'site_name')->firstOrFail();
        $entry = Activity::query()->where('log_name', 'settings')->where('subject_id', $siteNameSetting->id)->firstOrFail();
        $this->assertSame('Acme Co', $entry->attribute_changes['attributes']['value']);
    }

    // -- manual logging: Role/Permission --------------------------------------

    public function test_creating_updating_and_deleting_a_role_is_logged()
    {
        Permission::findOrCreate('roles.manage', config('admin-kit.guard'));
        Permission::findOrCreate('reports.export', config('admin-kit.guard'));
        $actor = $this->actingAsManager();
        $actor->givePermissionTo('roles.manage');

        $this->post(route('admin.roles.store'), [
            'name' => 'Reporter',
            'permissions' => ['reports.export'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'Reporter')->firstOrFail();
        $created = Activity::query()->where('log_name', 'roles')->where('event', 'created')->where('subject_id', $role->id)->firstOrFail();
        $this->assertSame(['reports.export'], $created->attribute_changes['attributes']['permissions']);
        $this->assertTrue($actor->is($created->causer));

        $this->put(route('admin.roles.update', $role), [
            'name' => 'Reporter',
            'permissions' => [],
        ])->assertRedirect(route('admin.roles.index'));

        $updated = Activity::query()->where('log_name', 'roles')->where('event', 'updated')->where('subject_id', $role->id)->firstOrFail();
        $this->assertSame(['reports.export'], $updated->attribute_changes['old']['permissions']);
        $this->assertSame([], $updated->attribute_changes['attributes']['permissions']);

        $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

        $this->assertSame(1, Activity::query()->where('log_name', 'roles')->where('event', 'deleted')->where('subject_id', $role->id)->count());
    }

    public function test_creating_and_deleting_a_permission_is_logged()
    {
        Permission::findOrCreate('permissions.manage', config('admin-kit.guard'));
        $actor = $this->actingAsManager();
        $actor->givePermissionTo('permissions.manage');

        $this->post(route('admin.permissions.store'), ['name' => 'reports.export'])
            ->assertRedirect(route('admin.permissions.index'));

        $permission = Permission::query()->where('name', 'reports.export')->firstOrFail();
        $this->assertSame(1, Activity::query()->where('log_name', 'permissions')->where('event', 'created')->where('subject_id', $permission->id)->count());

        $this->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.permissions.index'));

        $this->assertSame(1, Activity::query()->where('log_name', 'permissions')->where('event', 'deleted')->where('subject_id', $permission->id)->count());
    }

    // -- listing / filters / detail -------------------------------------------

    public function test_the_index_lists_activity_and_can_be_filtered_by_subject_type()
    {
        $this->actingAsManager();
        Page::factory()->create(['title' => 'Filtered Page']);
        $editedUser = User::factory()->create(['name' => 'Original Name']);
        $editedUser->update(['name' => 'Someone Else']);

        $this->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertSee('Filtered Page')
            ->assertSee('Someone Else');

        $this->get(route('admin.activity-log.index', ['subject_type' => Page::class]))
            ->assertOk()
            ->assertSee('Filtered Page')
            ->assertDontSee('Someone Else');
    }

    public function test_the_index_can_be_filtered_by_causer()
    {
        $actor = $this->actingAsManager();
        $otherAdmin = User::factory()->create();

        // Written directly rather than via two actingAs() sessions --
        // Auth::user() (and so the auto-resolved causer) stays whichever
        // user actingAs() last set for the rest of the test process, which
        // would make both entries below share one causer regardless of who
        // "did" each one.
        Activity::query()->create(['log_name' => 'test', 'description' => 'From this admin', 'causer_type' => User::class, 'causer_id' => $actor->id]);
        Activity::query()->create(['log_name' => 'test', 'description' => 'From the other admin', 'causer_type' => User::class, 'causer_id' => $otherAdmin->id]);

        $this->get(route('admin.activity-log.index', ['causer_id' => $otherAdmin->id]))
            ->assertOk()
            ->assertSee('From the other admin')
            ->assertDontSee('From this admin');
    }

    public function test_the_show_page_renders_the_before_after_diff()
    {
        $this->actingAsManager();
        $target = User::factory()->create(['name' => 'Before Name']);
        $target->update(['name' => 'After Name']);

        $entry = Activity::query()->where('log_name', 'users')->where('event', 'updated')->where('subject_id', $target->id)->firstOrFail();

        $this->get(route('admin.activity-log.show', $entry))
            ->assertOk()
            ->assertSee('Before Name')
            ->assertSee('After Name');
    }

    // -- retention -------------------------------------------------------------

    public function test_the_prune_command_deletes_only_entries_older_than_the_configured_retention()
    {
        $settings = $this->app->make(SettingsManager::class);
        $settings->saveGroup('system', ['activity_log_retention_days' => 30], ['activity_log_retention_days' => 'integer']);

        $old = Activity::query()->create(['log_name' => 'test', 'description' => 'old', 'created_at' => now()->subDays(45)]);
        $recent = Activity::query()->create(['log_name' => 'test', 'description' => 'recent', 'created_at' => now()->subDays(5)]);

        $this->artisan(PruneActivityLog::class)->assertSuccessful();

        $this->assertDatabaseMissing('activity_log', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_log', ['id' => $recent->id]);
    }

    public function test_the_prune_command_does_nothing_when_retention_is_unset()
    {
        Activity::query()->create(['log_name' => 'test', 'description' => 'old', 'created_at' => now()->subYears(5)]);

        $this->artisan(PruneActivityLog::class)->assertSuccessful();

        $this->assertSame(1, Activity::query()->count());
    }
}
