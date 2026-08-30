{{-- <x-admin::form.input name="email" type="email" :label="__('Email')" required /> --}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
])

@php
    $id = $attributes->get('id') ?? 'nk-'.Str::slug($name, '-');
    $message = $error ?? ($errors ?? null)?->first($name);
@endphp

<div class="nk-field">
    @if ($label)
        <label class="nk-label" for="{{ $id }}">
            {{ $label }}
            @if ($attributes->get('required'))
                <span class="nk-label__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        {{ $attributes->class(['nk-input', 'nk-input--invalid' => (bool) $message])->merge([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'value' => old($name, $value),
        ]) }}
        @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    >

    @if ($message)
        <p class="nk-field__error" id="{{ $id }}-error">{{ $message }}</p>
    @elseif ($hint)
        <p class="nk-field__hint">{{ $hint }}</p>
    @endif
</div>
