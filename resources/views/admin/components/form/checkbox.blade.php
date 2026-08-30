{{-- <x-admin::form.checkbox name="remember" :label="__('Remember me')" /> --}}
@props(['name', 'label' => null, 'value' => '1', 'checked' => false])

@php
    $id = $attributes->get('id') ?? 'nk-'.Str::slug($name, '-');
@endphp

<label class="nk-checkbox" for="{{ $id }}">
    <input
        type="checkbox"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @checked(old($name, $checked))
        {{ $attributes->except(['id']) }}
    >
    <span>{{ $label ?? $slot }}</span>
</label>
