<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\Menu;
use Database\Seeders\Admin\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function runRoleSeeder(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function test_it_creates_the_base_permission_set()
    {
        $this->runRoleSeeder();

        $this->assertSame(
            [
                'activity-log.view', 'backups.manage', 'contact-messages.delete', 'contact-messages.manage', 'contact-messages.view',
                'dashboard.view', 'media.manage', 'menus.manage', 'pages.create', 'pages.delete', 'pages.update',
                'pages.view', 'permissions.manage', 'roles.manage', 'settings.integrations.manage', 'settings.manage',
                'users.create', 'users.delete', 'users.update', 'users.view',
            ],
            Permission::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_it_creates_the_three_starter_roles()
    {
        $this->runRoleSeeder();

        $this->assertSame(
            ['Admin', 'Editor', 'Super Admin'],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_the_protected_role_has_every_permission()
    {
        $this->runRoleSeeder();

        $superAdmin = Role::query()->where('name', config('admin-kit.roles.protected_role'))->firstOrFail();

        $this->assertSame(
            Permission::query()->orderBy('name')->pluck('name')->all(),
            $superAdmin->permissions->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_admin_and_editor_get_a_narrower_permission_set()
    {
        $this->runRoleSeeder();

        $admin = Role::query()->where('name', 'Admin')->firstOrFail();
        $editor = Role::query()->where('name', 'Editor')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [
                'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.delete',
                'settings.integrations.manage', 'settings.manage', 'pages.view', 'pages.create', 'pages.update',
                'pages.delete', 'media.manage', 'contact-messages.view', 'contact-messages.manage', 'contact-messages.delete',
                'activity-log.view', 'backups.manage',
            ],
            $admin->permissions->pluck('name')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [
                'dashboard.view', 'users.view', 'pages.view', 'pages.create', 'pages.update', 'media.manage',
                'contact-messages.view', 'contact-messages.manage',
            ],
            $editor->permissions->pluck('name')->all(),
        );

        // Editor can see users but not delete them, and can write pages but not delete them.
        $this->assertFalse($editor->hasPermissionTo('users.delete'));
        $this->assertFalse($editor->hasPermissionTo('pages.delete'));
        $this->assertFalse($editor->hasPermissionTo('contact-messages.delete'));
        $this->assertFalse($editor->hasPermissionTo('activity-log.view'));
        $this->assertFalse($editor->hasPermissionTo('backups.manage'));
    }

    public function test_it_seeds_the_menus_table_matching_the_config_driven_menu()
    {
        $this->runRoleSeeder();

        $this->assertSame(13, Menu::query()->count());

        $settings = Menu::query()->where('label', 'Settings')->firstOrFail();
        $this->assertNull($settings->parent_id);

        $children = Menu::query()->where('parent_id', $settings->id)->orderBy('order')->pluck('label')->all();
        $this->assertSame(['General', 'Integrations', 'Menus', 'Roles', 'Permissions', 'Activity Log'], $children);
    }

    public function test_re_seeding_does_not_duplicate_permissions_roles_or_menus()
    {
        $this->runRoleSeeder();
        $this->runRoleSeeder();

        $this->assertSame(20, Permission::query()->count());
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(13, Menu::query()->count());
    }
}
