{{--
    @include('admin::partials.seo-fields', ['seo' => $page->seoMeta])

    Shared by every form editing a model that `use`s
    App\Models\Admin\Concerns\HasSeoMeta (Page today) -- expects $seo, the
    model's current SeoMeta row or null. Field names are all `seo[...]`,
    matching App\Http\Requests\Admin\Concerns\ValidatesSeoMeta's rules and
    HasSeoMeta::syncSeoMeta()'s expected shape.

    A native <details> disclosure, not Alpine -- stays open/closed and
    submits identically whether or not JS ever runs.
--}}
<details class="nk-card" @if ($seo) open @endif style="margin-top: 1rem;">
    <summary class="nk-card__header" style="cursor: pointer;">
        <h2 class="nk-card__title">{{ __('SEO') }}</h2>
    </summary>

    <div class="nk-card__body">
        {{--
            :error is passed explicitly on every field here: Laravel keys
            nested-array validation errors with dot notation (seo.meta_title)
            regardless of the input's bracket-syntax `name`, so the form
            components' own $errors->first($name) lookup would never match.
        --}}
        <x-admin::form.input
            name="seo[meta_title]"
            :label="__('Meta title')"
            :value="old('seo.meta_title', $seo?->meta_title)"
            :error="$errors->first('seo.meta_title')"
            :hint="__('Falls back to the title above, plus the suffix set in Settings > SEO, when left blank.')"
        />

        <x-admin::form.textarea
            name="seo[meta_description]"
            :label="__('Meta description')"
            :value="old('seo.meta_description', $seo?->meta_description)"
            :error="$errors->first('seo.meta_description')"
        />

        <x-admin::form.input
            name="seo[meta_keywords]"
            :label="__('Meta keywords')"
            :value="old('seo.meta_keywords', $seo?->meta_keywords)"
            :error="$errors->first('seo.meta_keywords')"
        />

        <x-admin::form.media-picker
            name="seo[og_image_media_id]"
            :label="__('OpenGraph / social image')"
            :selected="$seo?->ogImage"
            :hint="__('Falls back to the default OG image set in Settings > SEO when left blank.')"
        />

        <x-admin::form.input
            name="seo[canonical_url]"
            type="url"
            :label="__('Canonical URL')"
            :value="old('seo.canonical_url', $seo?->canonical_url)"
            :error="$errors->first('seo.canonical_url')"
            :hint="__('Leave blank to use this page\'s own URL.')"
        />

        <x-admin::form.select
            name="seo[robots]"
            :label="__('Robots')"
            :options="[
                '' => __('— Default (index, follow) —'),
                'index,follow' => 'index, follow',
                'noindex,follow' => 'noindex, follow',
                'index,nofollow' => 'index, nofollow',
                'noindex,nofollow' => 'noindex, nofollow',
            ]"
            :value="old('seo.robots', $seo?->robots)"
        />
    </div>
</details>
