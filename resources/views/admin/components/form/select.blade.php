{{-- <x-admin::form.select name="encryption" :label="__('Encryption')" :options="['' => __('None'), 'tls' => 'TLS', 'ssl' => 'SSL']" /> --}}
@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'hint' => null,
    'error' => null,
])

@php
    $id = $attributes->get('id') ?? 'nk-'.Str::slug($name, '-');
    $message = $error ?? ($errors ?? null)?->first($name);
    $selected = old($name, $value);
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

    <select
        {{ $attributes->class(['nk-input', 'nk-select', 'nk-input--invalid' => (bool) $message])->merge([
            'id' => $id,
            'name' => $name,
        ]) }}
        @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    >
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if ($message)
        <p class="nk-field__error" id="{{ $id }}-error">{{ $message }}</p>
    @elseif ($hint)
        <p class="nk-field__hint">{{ $hint }}</p>
    @endif
</div>
