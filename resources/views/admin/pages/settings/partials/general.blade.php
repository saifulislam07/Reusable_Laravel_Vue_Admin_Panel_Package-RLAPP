{{--
    Expects: $general (array), $logo (Media|null), $favicon (Media|null),
             $timezoneOptions (array<string, string>)
--}}
<x-admin::card :title="__('General')">
    <form method="POST" action="{{ route('admin.settings.general.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="site_name"
            :label="__('Site name')"
            :value="old('site_name', $general['site_name'] ?? config('admin-kit.brand.name'))"
            required
        />

        <x-admin::form.input
            name="tagline"
            :label="__('Tagline')"
            :value="old('tagline', $general['tagline'] ?? config('admin-kit.brand.tagline'))"
        />

        <div class="nk-form__row">
            <x-admin::form.media-picker name="logo_media_id" :label="__('Logo')" :selected="$logo" />
            <x-admin::form.media-picker name="favicon_media_id" :label="__('Favicon')" :selected="$favicon" />
        </div>

        <x-admin::form.select
            name="timezone"
            :label="__('Timezone')"
            :options="['' => __('— Use the application default —')] + $timezoneOptions"
            :value="old('timezone', $general['timezone'] ?? '')"
        />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
