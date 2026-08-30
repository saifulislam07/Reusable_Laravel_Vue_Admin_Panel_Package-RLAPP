<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasBulkActions;
use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Http\Controllers\Admin\Concerns\LogsRoleAndPermissionActivity;
use App\Http\Requests\Admin\RoleRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends AdminController
{
    use HasBulkActions;
    use HasDataTable;
    use LogsRoleAndPermissionActivity;

    public function index(): View
    {
        $roles = $this->paginateDataTable(
            Role::query()->withCount(['permissions', 'users']),
            searchable: ['name'],
            sortable: ['name', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return view('admin::pages.roles.index', [
            'roles' => $roles,
            'protectedRole' => $this->protectedRole(),
        ]);
    }

    public function create(): View
    {
        return view('admin::pages.roles.create', [
            'permissionGroups' => $this->permissionGroups(),
            'assignedPermissions' => [],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->validated('name'),
            'guard_name' => (string) config('admin-kit.guard'),
        ]);

        $role->syncPermissions((array) $request->validated('permissions', []));

        $this->logRoleCreated($role);

        return $this->redirectWithSuccess('admin.roles.index', __('Role created.'));
    }

    public function edit(Role $role): View
    {
        return view('admin::pages.roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
            'assignedPermissions' => $role->permissions->pluck('name')->all(),
            'isProtected' => $role->name === $this->protectedRole(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        // Captured before update(): renaming the protected role away from
        // its known name would slip it past this exact check on every
        // later request, silently disabling its own protection.
        $wasProtected = $role->name === $this->protectedRole();
        $before = $this->roleSnapshot($role);

        if ($wasProtected) {
            // Name is not attacker-controlled at this point -- it's this
            // request's own $this->protectedRole(), not $request input --
            // so the protected role can never be renamed away from it, and
            // always carries every permission that exists.
            $role->update(['name' => $this->protectedRole()]);
            $role->syncPermissions(Permission::query()->where('guard_name', $role->guard_name)->get());
        } else {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions((array) $request->validated('permissions', []));
        }

        $this->logRoleUpdated($role->fresh(), $before);

        return $this->redirectWithSuccess('admin.roles.index', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === $this->protectedRole()) {
            $this->flashError(__('The :role role cannot be deleted.', ['role' => $this->protectedRole()]));

            return redirect()->route('admin.roles.index');
        }

        $this->logRoleDeleted($role);
        $role->delete();

        return $this->redirectWithSuccess('admin.roles.index', __('Role deleted.'));
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $query = Role::query()->where('name', '!=', $this->protectedRole());

        foreach ((clone $query)->whereKey($this->bulkIds($request))->get() as $role) {
            $this->logRoleDeleted($role);
        }

        return $this->bulkDestroy($request, $query, 'admin.roles.index');
    }

    private function protectedRole(): string
    {
        return (string) config('admin-kit.roles.protected_role');
    }

    /**
     * Every permission, grouped by the module prefix before its first dot
     * ("users.view" -> "users") -- the checkbox matrix renders one card per
     * group.
     *
     * The outer collection is the base Support\Collection, not another
     * Eloquent one: Eloquent\Collection's value type is constrained to
     * Model, which a collection of (non-Model) grouped sub-collections
     * can't satisfy, even though that's genuinely what groupBy() returns
     * here at runtime.
     *
     * @return Collection<string, EloquentCollection<int, Permission>>
     */
    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->where('guard_name', config('admin-kit.guard'))
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => Str::before($permission->name, '.'));
    }
}
