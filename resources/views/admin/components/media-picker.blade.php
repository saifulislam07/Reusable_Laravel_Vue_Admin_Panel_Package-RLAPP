{{--
    <x-admin::media-picker />

    One reusable dialog, rendered once in the layout (same shape as the
    confirm dialog in <x-admin::toast-container />) -- any field opens it
    via NK.pickMedia(), which returns a Promise resolving to the chosen
    item ({id, name, file_name, thumbnail, url, is_image}) or null on
    cancel. See <x-admin::form.media-picker> for the per-field wrapper
    that wires a hidden input up to it, and initEditors() in admin.js for
    how the CMS editor's image button uses it directly.
--}}
<div
    class="nk-modal nk-media-picker"
    data-media-picker-endpoint="{{ route('admin.media.picker') }}"
    x-data
    x-show="$store.nkMediaPicker.open"
    x-cloak
    @keydown.escape.window="$store.nkMediaPicker.open && $store.nkMediaPicker.cancel()"
>
    <div class="nk-modal__backdrop" @click="$store.nkMediaPicker.cancel()"></div>

    <div class="nk-modal__dialog nk-media-picker__dialog" role="dialog" aria-modal="true" aria-labelledby="nk-media-picker-title">
        <h2 class="nk-modal__title" id="nk-media-picker-title">{{ __('Choose a file') }}</h2>

        <div class="nk-datatable__search" style="margin: 0.75rem 0;">
            <x-admin::icon name="search" class="nk-icon--sm" />
            <input
                type="search"
                x-model="$store.nkMediaPicker.search"
                @input.debounce.400ms="$store.nkMediaPicker.reload()"
                placeholder="{{ __('Search filename…') }}"
                aria-label="{{ __('Search') }}"
            >
        </div>

        <template x-if="$store.nkMediaPicker.loading && $store.nkMediaPicker.items.length === 0">
            <p class="nk-empty">{{ __('Loading…') }}</p>
        </template>

        <template x-if="!$store.nkMediaPicker.loading && $store.nkMediaPicker.items.length === 0">
            <p class="nk-empty">{{ __('No files found.') }}</p>
        </template>

        <div class="nk-media-grid">
            <template x-for="item in $store.nkMediaPicker.items" :key="item.id">
                <button type="button" class="nk-media-tile nk-media-tile--pickable" @click="$store.nkMediaPicker.select(item)">
                    <template x-if="item.thumbnail">
                        <img :src="item.thumbnail" :alt="item.file_name">
                    </template>
                    <template x-if="!item.thumbnail">
                        <div class="nk-media-tile__placeholder">
                            <x-admin::icon name="file-text" />
                        </div>
                    </template>
                    <div class="nk-media-tile__name" x-text="item.file_name"></div>
                </button>
            </template>
        </div>

        <div class="nk-modal__actions">
            <button
                type="button"
                class="nk-btn nk-btn--sm"
                x-show="$store.nkMediaPicker.nextPageUrl"
                x-cloak
                @click="$store.nkMediaPicker.loadMore()"
            >{{ __('Load more') }}</button>

            <button type="button" class="nk-btn" @click="$store.nkMediaPicker.cancel()">{{ __('Cancel') }}</button>
        </div>
    </div>
</div>
