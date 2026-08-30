{{--
    Shared by create.blade.php and edit.blade.php.

    Expects: $statusOptions (array), $templateOptions (array),
             and optionally $page (editing).
--}}
@php
    $page = $page ?? null;
@endphp

<form
    method="POST"
    action="{{ $page ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
    class="nk-form"
    x-data="{
        title: @js(old('title', $page?->title ?? '')),
        slug: @js(old('slug', $page?->slug ?? '')),
        slugTouched: {{ $page ? 'true' : 'false' }},
        status: @js(old('status', $page?->status?->value ?? 'draft')),
        slugify(value) {
            return value.toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '');
        },
    }"
>
    @csrf
    @if ($page)
        @method('PUT')
    @endif

    <x-admin::form.input
        name="title"
        :label="__('Title')"
        x-model="title"
        @input="if (!slugTouched) slug = slugify(title)"
        required
        autofocus
    />

    <x-admin::form.input
        name="slug"
        :label="__('Slug')"
        x-model="slug"
        @input="slugTouched = true"
        :hint="__('Auto-filled from the title until you edit it directly.')"
        required
    />

    <div class="nk-field">
        <label class="nk-label" for="nk-page-content">{{ __('Content') }}</label>

        <div data-editor>
            <div data-editor-content style="min-height: 320px;"></div>
            <textarea name="content" id="nk-page-content" data-editor-target hidden>{{ old('content', $page?->content) }}</textarea>
        </div>

        @error('content')
            <p class="nk-field__error">{{ $message }}</p>
        @enderror
    </div>

    <div class="nk-form__row">
        <x-admin::form.select
            name="template"
            :label="__('Template')"
            :options="$templateOptions"
            :value="old('template', $page?->template ?? array_key_first($templateOptions))"
        />

        <x-admin::form.select
            name="status"
            :label="__('Status')"
            :options="$statusOptions"
            x-model="status"
        />
    </div>

    <div x-show="status === 'scheduled'" x-cloak>
        <x-admin::form.input
            type="datetime-local"
            name="published_at"
            :label="__('Publish at')"
            :value="old('published_at', $page?->published_at?->format('Y-m-d\TH:i'))"
            :hint="__('Must be in the future.')"
        />
    </div>

    @include('admin::partials.seo-fields', ['seo' => $page?->seoMeta])

    <div class="nk-form__row" style="margin-top: 1rem;">
        <button type="submit" class="nk-btn nk-btn--primary">{{ $page ? __('Save changes') : __('Create page') }}</button>
        <a href="{{ route('admin.pages.index') }}" class="nk-btn">{{ __('Cancel') }}</a>
    </div>
</form>
