{{-- <x-admin::form.textarea name="message" :label="__('Message')" /> --}}
@props([
    'name',
    'label' => null,
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

    <textarea
        {{ $attributes->class(['nk-input', 'nk-textarea', 'nk-input--invalid' => (bool) $message])->merge([
            'id' => $id,
            'name' => $name,
        ]) }}
        @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    >{{ old($name, $value) }}</textarea>

    @if ($message)
        <p class="nk-field__error" id="{{ $id }}-error">{{ $message }}</p>
    @elseif ($hint)
        <p class="nk-field__hint">{{ $hint }}</p>
    @endif
</div>
