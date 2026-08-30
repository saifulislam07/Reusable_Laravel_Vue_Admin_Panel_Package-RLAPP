{{--
    Shared by create.blade.php and edit.blade.php.

    Expects: $parentOptions (Collection<Menu>), $routeOptions (array),
             $permissionOptions (array), and optionally $menu (editing).
--}}
@php
    $menu = $menu ?? null;
    $linkType = old('route', $menu?->route) ? 'route' : 'url';
@endphp

<form
    method="POST"
    action="{{ $menu ? route('admin.menus.update', $menu) : route('admin.menus.store') }}"
    class="nk-form"
    x-data="{ linkType: @js($linkType) }"
>
    @csrf
    @if ($menu)
        @method('PUT')
    @endif

    <x-admin::form.input name="label" :label="__('Label')" :value="old('label', $menu?->label)" required autofocus />

    <x-admin::form.input
        name="icon"
        :label="__('Icon')"
        :value="old('icon', $menu?->icon)"
        :hint="__('Optional. One of the panel\'s icon names — dashboard, users, settings, file-text, image, mail, bell, and more. Unknown names fall back to a plain dot.')"
    />

    <div class="nk-field">
        <span class="nk-label">{{ __('Link') }}</span>

        <div class="nk-form__row" style="justify-content: flex-start; gap: 1.25rem;">
            <label class="nk-checkbox">
                <input type="radio" name="link_type" value="route" x-model="linkType">
                <span>{{ __('A named route') }}</span>
            </label>
            <label class="nk-checkbox">
                <input type="radio" name="link_type" value="url" x-model="linkType">
                <span>{{ __('A URL') }}</span>
            </label>
        </div>
    </div>

    <div x-show="linkType === 'route'" x-cloak>
        <x-admin::form.select
            name="route"
            :label="__('Route')"
            :options="['' => __('Select a route…')] + $routeOptions"
            :value="old('route', $menu?->route)"
        />
    </div>

    <div x-show="linkType === 'url'" x-cloak>
        <x-admin::form.input
            name="url"
            :label="__('URL')"
            :value="old('url', $menu?->url)"
            placeholder="https://…  or  /path"
        />
    </div>

    <x-admin::form.select
        name="parent_id"
        :label="__('Parent')"
        :options="['' => __('— Top level —')] + $parentOptions->pluck('label', 'id')->all()"
        :value="old('parent_id', $menu?->parent_id)"
    />

    <x-admin::form.select
        name="permission_name"
        :label="__('Required permission')"
        :options="['' => __('— Visible to everyone —')] + $permissionOptions->all()"
        :value="old('permission_name', $menu?->permission_name)"
    />

    <x-admin::form.checkbox
        name="is_active"
        :label="__('Active')"
        :checked="old('is_active', $menu?->is_active ?? true)"
    />

    <div class="nk-form__row">
        <button type="submit" class="nk-btn nk-btn--primary">{{ $menu ? __('Save changes') : __('Create menu item') }}</button>
        <a href="{{ route('admin.menus.index') }}" class="nk-btn">{{ __('Cancel') }}</a>
    </div>
</form>
