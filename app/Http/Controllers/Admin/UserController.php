<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\UserStatus;
use App\Http\Controllers\Admin\Concerns\HasBulkActions;
use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Notifications\Admin\AdminUserInvited;
use App\Notifications\Admin\NewUserRegistered;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends AdminController
{
    use HasBulkActions;
    use HasDataTable;

    public function index(Request $request): View
    {
        $query = User::query()->with('avatar');

        if (filled($role = $request->query('role'))) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        $users = $this->paginateDataTable(
            $query,
            searchable: ['name', 'email'],
            sortable: ['name', 'email', 'created_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        return view('admin::pages.users.index', [
            'users' => $users,
            'roleOptions' => Role::query()->orderBy('name')->pluck('name', 'name'),
            'currentRole' => (string) $role,
        ]);
    }

    public function create(): View
    {
        return view('admin::pages.users.create', $this->formData());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $sendInvite = $request->sendInvite();

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'status' => $request->validated('status'),
            'avatar_media_id' => $request->validated('avatar_media_id'),
            // An invited user never actually authenticates with this --
            // they set their own via the emailed link before it could ever
            // be used, matching how CreateNewUser onboards a normal signup.
            'password' => $sendInvite ? Str::password(40) : $request->validated('password'),
        ]);

        $this->syncAccess($user, $request);

        // Dispatched here, not via a global User::created observer, since
        // User is shared with the public Fortify registration flow -- an
        // observer would fire for public signups too. Excludes the acting
        // admin themselves.
        Notification::send(
            User::query()->admins()->whereKeyNot($request->user()?->getKey())->get(),
            new NewUserRegistered($user),
        );

        if ($sendInvite) {
            $this->sendInvite($user);

            return $this->redirectWithSuccess('admin.users.index', __('User created and invited.'));
        }

        return $this->redirectWithSuccess('admin.users.index', __('User created.'));
    }

    public function edit(User $user): View
    {
        return view('admin::pages.users.edit', ['user' => $user] + $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'status' => $request->validated('status'),
            'avatar_media_id' => $request->validated('avatar_media_id'),
        ];

        // Blank means "leave the current password alone" -- it is never
        // echoed back into the form, so there is nothing for the admin to
        // have intentionally cleared.
        if (filled($request->validated('password'))) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        $this->syncAccess($user, $request);

        return $this->redirectWithSuccess('admin.users.index', __('User updated.'));
    }

    /**
     * A signed-in admin can never delete their own account this way --
     * doing so would either lock them out mid-request or, worse, look like
     * it worked and then fail confusingly on the next request.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            $this->flashError(__('You cannot delete your own account.'));

            return redirect()->route('admin.users.index');
        }

        $user->delete();

        return $this->redirectWithSuccess('admin.users.index', __('User deleted.'));
    }

    /**
     * Named distinctly from the HasBulkActions::bulkDestroy() helper it
     * calls: a class method can't share a name with a trait method it
     * invokes on $this without shadowing (and recursing into) itself.
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        return $this->bulkDestroy(
            $request,
            User::query()->whereKeyNot($request->user()?->getKey()),
            'admin.users.index',
        );
    }

    private function syncAccess(User $user, UserRequest $request): void
    {
        $user->syncRoles((array) $request->validated('roles', []));
        $user->syncPermissions((array) $request->validated('permissions', []));
    }

    private function sendInvite(User $user): void
    {
        $token = Password::broker((string) config('admin-kit.auth.password_broker'))->createToken($user);

        $user->notify(new AdminUserInvited($token));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?User $editing = null): array
    {
        return [
            'statusOptions' => UserStatus::options(),
            'roleGroups' => Role::query()->orderBy('name')->get(),
            'permissionGroups' => $this->permissionGroups(),
            'assignedRoles' => $editing?->roles->pluck('name')->all() ?? [],
            // ->permissions, not ->getAllPermissions(): the latter also
            // includes permissions inherited through a role, which this
            // checkbox matrix must not pre-tick -- ticking it would
            // give the user that permission directly too, permanently,
            // even after the role that used to grant it is removed.
            'assignedPermissions' => $editing?->permissions->pluck('name')->all() ?? [],
        ];
    }

    /**
     * Every permission, grouped by the module prefix before its first dot
     * ("users.view" -> "users") -- see RoleController::permissionGroups()
     * for why the outer collection is the base Support\Collection, not
     * another Eloquent one.
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
