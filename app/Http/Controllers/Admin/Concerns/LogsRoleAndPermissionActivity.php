<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Support\Facades\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role and Permission are spatie/laravel-permission's own models, not ones
 * this app owns -- there's nowhere to put a LogsActivity trait + logOnly()
 * the way User/Page/ContactMessage/Setting get it (Phase 18). These write
 * the same shape of activity_log row by hand instead, through the same
 * activity() helper, so the listing/detail UI treats every entry uniformly
 * regardless of which path produced it.
 */
trait LogsRoleAndPermissionActivity
{
    protected function logRoleCreated(Role $role): void
    {
        activity('roles')
            ->causedBy(Request::user())
            ->performedOn($role)
            ->event('created')
            ->withChanges(['attributes' => $this->roleSnapshot($role)])
            ->log("Role \"{$role->name}\" created");
    }

    /**
     * @param  array<string, mixed>  $before
     */
    protected function logRoleUpdated(Role $role, array $before): void
    {
        activity('roles')
            ->causedBy(Request::user())
            ->performedOn($role)
            ->event('updated')
            ->withChanges(['old' => $before, 'attributes' => $this->roleSnapshot($role)])
            ->log("Role \"{$role->name}\" updated");
    }

    protected function logRoleDeleted(Role $role): void
    {
        activity('roles')
            ->causedBy(Request::user())
            ->performedOn($role)
            ->event('deleted')
            ->withChanges(['old' => $this->roleSnapshot($role)])
            ->log("Role \"{$role->name}\" deleted");
    }

    protected function logPermissionCreated(Permission $permission): void
    {
        activity('permissions')
            ->causedBy(Request::user())
            ->performedOn($permission)
            ->event('created')
            ->withChanges(['attributes' => ['name' => $permission->name]])
            ->log("Permission \"{$permission->name}\" created");
    }

    protected function logPermissionDeleted(Permission $permission): void
    {
        activity('permissions')
            ->causedBy(Request::user())
            ->performedOn($permission)
            ->event('deleted')
            ->withChanges(['old' => ['name' => $permission->name]])
            ->log("Permission \"{$permission->name}\" deleted");
    }

    /**
     * @return array{name: string, permissions: list<string>}
     */
    private function roleSnapshot(Role $role): array
    {
        // array_values(), not the collection's own values()->all(): the
        // latter doesn't narrow to list<> for static analysis even though
        // it does produce one at runtime -- see MenuController::tree()'s
        // docblock for the same note.
        return [
            'name' => $role->name,
            'permissions' => array_values($role->permissions->pluck('name')->sort()->all()),
        ];
    }
}
