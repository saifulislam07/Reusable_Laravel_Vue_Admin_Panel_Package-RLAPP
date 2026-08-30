{{--
    Shared by create.blade.php and edit.blade.php.

    Expects: $permissionGroups (Collection<string, Collection<Permission>>),
             $assignedPermissions (list<string>), and optionally $role,
             $isProtected (editing).
--}}
@php
    $role = $role ?? null;
    $isProtected = $isProtected ?? false;
    $checked = old('permissions', $assignedPermissions);
@endphp

@if ($isProtected)
    {{-- Name is fixed and it always carries every permission (see
         RoleController::update()) -- nothing here is actually editable, so
         there's no form: a disabled field is never submitted by the
         browser, which would only make "Save" silently fail validation. --}}
    <x-admin::alert variant="info">
        {{ __('The :role role always carries every permission and cannot be renamed or deleted.', ['role' => $role->name]) }}
    </x-admin::alert>

    <div class="nk-form__row">
        <a href="{{ route('admin.roles.index') }}" class="nk-btn">{{ __('Back') }}</a>
    </div>
@else
    <form method="POST" action="{{ $role ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="nk-form">
        @csrf
        @if ($role)
            @method('PUT')
        @endif

        <x-admin::form.input name="name" :label="__('Name')" :value="old('name', $role?->name)" required autofocus />

        <div class="nk-field">
            <span class="nk-label">{{ __('Permissions') }}</span>
        </div>

        <div class="nk-grid">
            @foreach ($permissionGroups as $group => $permissions)
                <x-admin::card :title="\Illuminate\Support\Str::headline($group)">
                    @foreach ($permissions as $permission)
                        <x-admin::form.checkbox
                            name="permissions[]"
                            :value="$permission->name"
                            :label="$permission->name"
                            :checked="in_array($permission->name, $checked, true)"
                            style="margin-bottom: 0.5rem;"
                        />
                    @endforeach
                </x-admin::card>
            @endforeach
        </div>

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ $role ? __('Save changes') : __('Create role') }}</button>
            <a href="{{ route('admin.roles.index') }}" class="nk-btn">{{ __('Cancel') }}</a>
        </div>
    </form>
@endif
