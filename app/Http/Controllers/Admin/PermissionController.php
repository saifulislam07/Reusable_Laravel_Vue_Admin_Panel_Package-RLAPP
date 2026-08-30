<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasBulkActions;
use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Http\Controllers\Admin\Concerns\LogsRoleAndPermissionActivity;
use App\Http\Requests\Admin\PermissionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

/**
 * Permission names are treated as immutable once created -- there is no
 * update() here. Route middleware, seeders and menu items all reference a
 * permission by its exact name string; a rename would silently break every
 * one of those references with nothing to point at what broke.
 */
class PermissionController extends AdminController
{
    use HasBulkActions;
    use HasDataTable;
    use LogsRoleAndPermissionActivity;

    public function index(): View
    {
        $permissions = $this->paginateDataTable(
            Permission::query()->withCount('roles'),
            searchable: ['name'],
            sortable: ['name', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return view('admin::pages.permissions.index', ['permissions' => $permissions]);
    }

    public function create(): View
    {
        return view('admin::pages.permissions.create');
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::query()->create([
            'name' => $request->validated('name'),
            'guard_name' => (string) config('admin-kit.guard'),
        ]);

        $this->logPermissionCreated($permission);

        return $this->redirectWithSuccess('admin.permissions.index', __('Permission created.'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->logPermissionDeleted($permission);
        $permission->delete();

        return $this->redirectWithSuccess('admin.permissions.index', __('Permission deleted.'));
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $query = Permission::query();

        foreach ((clone $query)->whereKey($this->bulkIds($request))->get() as $permission) {
            $this->logPermissionDeleted($permission);
        }

        return $this->bulkDestroy($request, $query, 'admin.permissions.index');
    }
}
