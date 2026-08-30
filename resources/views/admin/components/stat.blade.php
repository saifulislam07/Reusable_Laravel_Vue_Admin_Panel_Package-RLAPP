{{-- <x-admin::stat label="Users" value="1,204" icon="users" /> --}}
@props(['label', 'value', 'icon' => 'dot'])

<div {{ $attributes->class('nk-card') }}>
    <div class="nk-stat">
        <span class="nk-stat__icon"><x-admin::icon :name="$icon" /></span>
        <div>
            <div class="nk-stat__value">{{ $value }}</div>
            <div class="nk-stat__label">{{ $label }}</div>
        </div>
    </div>
</div>
