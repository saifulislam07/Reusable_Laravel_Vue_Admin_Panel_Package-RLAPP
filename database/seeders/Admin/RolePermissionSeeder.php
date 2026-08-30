<?php

declare(strict_types=1);

namespace Database\Seeders\Admin;

use App\Models\Admin\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Base permissions + the three starter roles the plan names (Super Admin,
 * Admin, Editor), plus a matching seed of the `menus` table so switching
 * ADMIN_MENU_DRIVER to "database" later costs nothing but flipping the env
 * var -- see DatabaseMenuSource.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'dashboard.view',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'menus.manage',
        'roles.manage',
        'permissions.manage',
        'settings.integrations.manage',
        'settings.manage',
        'pages.view',
        'pages.create',
        'pages.update',
        'pages.delete',
        'media.manage',
        'contact-messages.view',
        'contact-messages.manage',
        'contact-messages.delete',
        'activity-log.view',
        'backups.manage',
    ];

    public function run(): void
    {
        $guard = (string) config('admin-kit.guard');

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, $guard);
        }

        // Always kept in sync with every permission that exists -- see
        // AppServiceProvider's Gate::before() hook and RoleController's own
        // enforcement of the same rule when this role is edited later.
        Role::findOrCreate((string) config('admin-kit.roles.protected_role'), $guard)
            ->syncPermissions(Permission::query()->where('guard_name', $guard)->get());

        Role::findOrCreate('Admin', $guard)->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'settings.integrations.manage',
            'settings.manage',
            'pages.view',
            'pages.create',
            'pages.update',
            'pages.delete',
            'media.manage',
            'contact-messages.view',
            'contact-messages.manage',
            'contact-messages.delete',
            'activity-log.view',
            'backups.manage',
        ]);

        // Editor manages content (can write and publish pages, and needs
        // the library to put images in that content) but not delete pages,
        // and stays read-only on Users -- same shape as before, just with
        // the CMS and Media Library added. Can read and reply to contact
        // messages (a content-adjacent task) but not delete them.
        Role::findOrCreate('Editor', $guard)->syncPermissions([
            'dashboard.view',
            'users.view',
            'pages.view',
            'pages.create',
            'pages.update',
            'media.manage',
            'contact-messages.view',
            'contact-messages.manage',
        ]);

        $this->seedMenus();
    }

    /**
     * Mirrors config('admin-kit.menu.items') -- see config/admin-kit.php --
     * one-to-one. Skipped on a re-seed so re-running this command doesn't
     * duplicate every row.
     */
    private function seedMenus(): void
    {
        if (Menu::query()->exists()) {
            return;
        }

        Menu::query()->create(['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'order' => 0]);

        Menu::query()->create([
            'label' => 'Users', 'icon' => 'users', 'route' => 'admin.users.index',
            'order' => 1, 'permission_name' => 'users.view',
        ]);

        Menu::query()->create([
            'label' => 'Pages', 'icon' => 'file-text', 'route' => 'admin.pages.index',
            'order' => 2, 'permission_name' => 'pages.view',
        ]);

        Menu::query()->create([
            'label' => 'Media', 'icon' => 'image', 'route' => 'admin.media.index',
            'order' => 3, 'permission_name' => 'media.manage',
        ]);

        Menu::query()->create([
            'label' => 'Contact Messages', 'icon' => 'mail', 'route' => 'admin.contact-messages.index',
            'order' => 4, 'permission_name' => 'contact-messages.view',
        ]);

        Menu::query()->create([
            'label' => 'Backups', 'icon' => 'database', 'route' => 'admin.backups.index',
            'order' => 5, 'permission_name' => 'backups.manage',
        ]);

        $settings = Menu::query()->create(['label' => 'Settings', 'icon' => 'settings', 'order' => 6]);

        $children = [
            ['label' => 'General', 'route' => 'admin.settings.edit', 'permission_name' => 'settings.manage'],
            ['label' => 'Integrations', 'route' => 'admin.settings.integrations.edit', 'permission_name' => 'settings.integrations.manage'],
            ['label' => 'Menus', 'route' => 'admin.menus.index', 'permission_name' => 'menus.manage'],
            ['label' => 'Roles', 'route' => 'admin.roles.index', 'permission_name' => 'roles.manage'],
            ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'permission_name' => 'permissions.manage'],
            ['label' => 'Activity Log', 'route' => 'admin.activity-log.index', 'permission_name' => 'activity-log.view'],
        ];

        foreach ($children as $order => $child) {
            Menu::query()->create($child + ['parent_id' => $settings->id, 'order' => $order]);
        }
    }
}
