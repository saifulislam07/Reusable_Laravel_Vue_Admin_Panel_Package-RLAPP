{{--
    Expects: $seo (array), $defaultOgImage (Media|null)
--}}
<x-admin::card :title="__('SEO')">
    <form method="POST" action="{{ route('admin.settings.seo.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="meta_title_suffix"
            :label="__('Meta title suffix')"
            :value="old('meta_title_suffix', $seo['meta_title_suffix'] ?? null)"
            :hint="__('Appended to every page\'s title when it doesn\'t set its own SEO meta title -- e.g. \"— Acme Co\".')"
        />

        <x-admin::form.media-picker
            name="default_og_image_media_id"
            :label="__('Default OpenGraph image')"
            :selected="$defaultOgImage"
            :hint="__('Used for any page that doesn\'t set its own OG image.')"
        />

        <x-admin::form.textarea
            name="robots_txt"
            :label="__('robots.txt')"
            :value="old('robots_txt', $seo['robots_txt'] ?? "User-agent: *\nDisallow: /admin/")"
            :hint="__('Served live at /robots.txt.')"
            rows="6"
        />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
