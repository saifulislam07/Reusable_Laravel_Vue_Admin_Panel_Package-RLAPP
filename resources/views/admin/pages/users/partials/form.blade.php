{{--
    Shared by create.blade.php and edit.blade.php.

    Expects: $statusOptions (array), $roleGroups (Collection<Role>),
             $permissionGroups (Collection<string, Collection<Permission>>),
             $assignedRoles (list<string>), $assignedPermissions (list<string>),
             and optionally $user (editing).
--}}
@php
    $user = $user ?? null;
    $checkedRoles = old('roles', $assignedRoles);
    $checkedPermissions = old('permissions', $assignedPermissions);
@endphp

<form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}" class="nk-form">
    @csrf
    @if ($user)
        @method('PUT')
    @endif

    <x-admin::form.media-picker name="avatar_media_id" :label="__('Avatar')" :selected="$user?->avatar" />

    <x-admin::form.input name="name" :label="__('Name')" :value="old('name', $user?->name)" required autofocus />

    <x-admin::form.input
        name="email"
        type="email"
        :label="__('Email')"
        :value="old('email', $user?->email)"
        required
    />

    <x-admin::form.input
        name="password"
        type="password"
        :label="__('Password')"
        autocomplete="new-password"
        :hint="$user ? __('Leave blank to keep the current password.') : null"
        :required="! $user"
    />

    @unless ($user)
        <x-admin::form.checkbox
            name="invite"
            :label="__('Send an invite email instead — the password above is ignored and the user sets their own.')"
            :checked="old('invite', false)"
        />
    @endunless

    <x-admin::form.select
        name="status"
        :label="__('Status')"
        :options="$statusOptions"
        :value="old('status', $user?->status?->value ?? \App\Enums\Admin\UserStatus::Active->value)"
    />

    <div class="nk-field">
        <span class="nk-label">{{ __('Roles') }}</span>
    </div>

    <div class="nk-form__row" style="flex-wrap: wrap; justify-content: flex-start; gap: 0.5rem 1.25rem;">
        @forelse ($roleGroups as $role)
            <x-admin::form.checkbox
                name="roles[]"
                :value="$role->name"
                :label="$role->name"
                :checked="in_array($role->name, $checkedRoles, true)"
            />
        @empty
            <p class="nk-field__hint">{{ __('No roles exist yet.') }}</p>
        @endforelse
    </div>

    <div class="nk-field">
        <span class="nk-label">{{ __('Direct permissions') }}</span>
        <p class="nk-field__hint">
            {{ __('Granted straight to this user, on top of anything their roles already allow.') }}
        </p>
    </div>

    <div class="nk-grid">
        @foreach ($permissionGroups as $group => $permissions)
            <x-admin::card :title="\Illuminate\Support\Str::headline($group)">
                @foreach ($permissions as $permission)
                    <x-admin::form.checkbox
                        name="permissions[]"
                        :value="$permission->name"
                        :label="$permission->name"
                        :checked="in_array($permission->name, $checkedPermissions, true)"
                        style="margin-bottom: 0.5rem;"
                    />
                @endforeach
            </x-admin::card>
        @endforeach
    </div>

    <div class="nk-form__row">
        <button type="submit" class="nk-btn nk-btn--primary">{{ $user ? __('Save changes') : __('Create user') }}</button>
        <a href="{{ route('admin.users.index') }}" class="nk-btn">{{ __('Cancel') }}</a>
    </div>
</form>
