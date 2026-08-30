{{--
    Expects: $userCount, $roleCount, $permissionCount, $menuCount (all int)
--}}
<div class="nk-grid">
    <x-admin::stat :label="__('Registered users')" :value="$userCount" icon="users" />
    <x-admin::stat :label="__('Roles')" :value="$roleCount" icon="shield" />
    <x-admin::stat :label="__('Permissions')" :value="$permissionCount" icon="key" />
    <x-admin::stat :label="__('Menu items')" :value="$menuCount" icon="layers" />
</div>
