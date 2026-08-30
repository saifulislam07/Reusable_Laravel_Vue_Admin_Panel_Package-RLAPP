{{--
    <x-admin::form.media-picker name="avatar_media_id" :label="__('Avatar')" :selected="$user->avatar" />

    A single-item field wired up to the global <x-admin::media-picker />
    dialog. $selected is an optional Spatie Media instance (the current
    value, for editing).
--}}
@props(['name', 'label' => null, 'selected' => null, 'hint' => null, 'error' => null])

@php
    $id = 'nk-'.Str::slug($name, '-');
    $message = $error ?? ($errors ?? null)?->first($name);
@endphp

<div
    class="nk-field"
    x-data="{
        id: {{ $selected?->id ?? 'null' }},
        thumbnail: @js($selected?->hasGeneratedConversion('thumbnail') ? $selected->getUrl('thumbnail') : null),
        fileName: @js($selected?->file_name),
        pick() {
            NK.pickMedia().then((item) => {
                if (!item) return;
                this.id = item.id;
                this.thumbnail = item.thumbnail;
                this.fileName = item.file_name;
            });
        },
        clear() {
            this.id = null;
            this.thumbnail = null;
            this.fileName = null;
        },
    }"
>
    @if ($label)
        <span class="nk-label">{{ $label }}</span>
    @endif

    <input type="hidden" name="{{ $name }}" :value="id" id="{{ $id }}">

    <div class="nk-media-picker-field">
        <div class="nk-media-picker-field__preview" x-show="thumbnail" x-cloak>
            <img :src="thumbnail" :alt="fileName">
        </div>

        <div class="nk-media-picker-field__actions">
            <button type="button" class="nk-btn nk-btn--sm" @click="pick()">
                <span x-text="id ? @js(__('Change')) : @js(__('Choose file'))"></span>
            </button>
            <button type="button" class="nk-btn nk-btn--sm nk-btn--ghost" x-show="id" x-cloak @click="clear()">
                {{ __('Remove') }}
            </button>
        </div>
    </div>

    @if ($message)
        <p class="nk-field__error">{{ $message }}</p>
    @elseif ($hint)
        <p class="nk-field__hint">{{ $hint }}</p>
    @endif
</div>
