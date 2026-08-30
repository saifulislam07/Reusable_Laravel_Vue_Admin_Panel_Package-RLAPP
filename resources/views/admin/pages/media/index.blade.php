@extends('admin::layouts.app')

@section('title', __('Media'))

@php
    $nkCarry = request()->except(['q', 'page', 'folder']);
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Media')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Media')],
        ]"
    />

    <div
        class="nk-media-layout"
        x-data="{
            checked: [],
            mediaIds: @js(collect($media->items())->map(fn ($item) => (string) $item->id)->all()),
            get allChecked() { return this.mediaIds.length > 0 && this.checked.length === this.mediaIds.length },
            toggleAll(on) { this.checked = on ? [...this.mediaIds] : [] },
            newFolderOpen: false,
            dragging: false,
        }"
    >
        <aside class="nk-card nk-media-folders">
            <div class="nk-card__header">
                <h2 class="nk-card__title">{{ __('Folders') }}</h2>
                <button type="button" class="nk-btn nk-btn--sm" @click="newFolderOpen = !newFolderOpen">
                    {{ __('New') }}
                </button>
            </div>

            <div class="nk-card__body">
                <form
                    method="POST"
                    action="{{ route('admin.media.folders.store') }}"
                    class="nk-form"
                    x-show="newFolderOpen"
                    x-cloak
                    style="margin-bottom: 1rem;"
                >
                    @csrf
                    <x-admin::form.input name="name" :label="__('Folder name')" required autofocus />
                    <button type="submit" class="nk-btn nk-btn--sm nk-btn--primary">{{ __('Create') }}</button>
                </form>

                <ul class="nk-media-folders__list">
                    <li>
                        <a href="{{ route('admin.media.index') }}" @class(['is-active' => ! $currentFolder])>
                            {{ __('All files') }}
                        </a>
                    </li>

                    @foreach ($folders as $folder)
                        <li>
                            <a
                                href="{{ route('admin.media.index', ['folder' => $folder->id]) }}"
                                @class(['is-active' => $currentFolder === $folder->id])
                            >
                                {{ $folder->name }}
                            </a>

                            @unless ($folder->isDefault())
                                <form method="POST" action="{{ route('admin.media.folders.destroy', $folder) }}" data-nk-confirm="{{ __('Delete this folder?') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="nk-media-folders__delete" aria-label="{{ __('Delete folder') }}">&times;</button>
                                </form>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        <div class="nk-media-main">
            <x-admin::card>
                <form
                    method="POST"
                    action="{{ route('admin.media.store') }}"
                    enctype="multipart/form-data"
                    class="nk-dropzone"
                    :class="{ 'is-dragging': dragging }"
                    x-ref="dzForm"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dragging = false; $refs.dzInput.files = $event.dataTransfer.files; $refs.dzForm.requestSubmit()"
                >
                    @csrf
                    <input type="hidden" name="folder_id" value="{{ $currentFolder }}">

                    <x-admin::icon name="image" class="nk-icon--sm" />
                    <p>{{ __('Drag files here, or') }}</p>
                    <label class="nk-btn nk-btn--sm">
                        {{ __('Browse') }}
                        <input type="file" name="files[]" multiple hidden x-ref="dzInput" @change="$refs.dzForm.requestSubmit()">
                    </label>

                    {{-- Auto-submits via @change above once JS runs; stays a
                    real, clickable submit button so choosing files still
                    uploads them with JS disabled. --}}
                    <button type="submit" class="nk-btn nk-btn--sm nk-btn--primary">{{ __('Upload') }}</button>

                    @error('files')
                        <p class="nk-field__error">{{ $message }}</p>
                    @enderror
                    @error('files.*')
                        <p class="nk-field__error">{{ $message }}</p>
                    @enderror
                </form>

                <div class="nk-datatable__toolbar" style="margin-top: 1rem;">
                    <form method="GET" class="nk-datatable__search" role="search">
                        @foreach ($nkCarry as $paramKey => $paramValue)
                            <input type="hidden" name="{{ $paramKey }}" value="{{ $paramValue }}">
                        @endforeach
                        @if ($currentFolder)
                            <input type="hidden" name="folder" value="{{ $currentFolder }}">
                        @endif

                        <x-admin::icon name="search" class="nk-icon--sm" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search filename…') }}" aria-label="{{ __('Search') }}">
                        <button type="submit" class="nk-btn nk-btn--sm">{{ __('Search') }}</button>
                    </form>

                    <span class="nk-datatable__count">
                        {{ trans_choice('{0}No files|{1}:count file|[2,*]:count files', $media->total()) }}
                    </span>
                </div>

                <div class="nk-datatable__bulkbar" x-show="checked.length > 0" x-cloak>
                    <span x-text="checked.length + ' {{ __('selected') }}'"></span>

                    <button
                        type="submit"
                        form="nk-media-bulk-form"
                        formaction="{{ route('admin.media.bulk-destroy') }}"
                        class="nk-btn nk-btn--sm nk-btn--danger"
                        data-nk-confirm="{{ __('Delete the selected files?') }}"
                    >{{ __('Delete selected') }}</button>
                </div>

                <form id="nk-media-bulk-form" method="POST">
                    @csrf
                    <template x-for="id in checked" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                </form>

                <div class="nk-media-grid">
                    @forelse ($media as $item)
                        <div class="nk-media-tile">
                            <label class="nk-media-tile__check">
                                <input type="checkbox" value="{{ $item->id }}" x-model="checked" aria-label="{{ __('Select file') }}">
                            </label>

                            @if ($item->hasGeneratedConversion('thumbnail'))
                                <img src="{{ $item->getUrl('thumbnail') }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <div class="nk-media-tile__placeholder">
                                    <x-admin::icon name="file-text" />
                                </div>
                            @endif

                            <div class="nk-media-tile__name" title="{{ $item->file_name }}">{{ $item->file_name }}</div>

                            <form method="POST" action="{{ route('admin.media.destroy', $item) }}" class="nk-media-tile__delete" data-nk-confirm="{{ __('Delete this file?') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" aria-label="{{ __('Delete') }}">&times;</button>
                            </form>
                        </div>
                    @empty
                        <p class="nk-empty">{{ __('No files here yet -- drag some in above.') }}</p>
                    @endforelse
                </div>

                {{ $media->onEachSide(1)->links('admin::components.pagination') }}
            </x-admin::card>
        </div>
    </div>
@endsection
