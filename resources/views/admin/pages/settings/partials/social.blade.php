{{--
    Expects: $social (array, key 'social_links' => list<array{platform,url}>)

    A dynamic-length repeater (no migration needed for a new platform, per
    the plan). Existing rows plus a handful of blank ones are always
    rendered as plain inputs, so adding a platform still works with JS
    disabled -- blank rows are dropped server-side, see
    SocialSettingsRequest::prepareForValidation(). With JS on, only a
    couple of blank rows show at first; "Add another" reveals the rest
    (already in the DOM) one at a time rather than needing an AJAX call.
--}}
@php
    $socialLinks = old('social_links', $social['social_links'] ?? []);
    $extraBlankRows = 10;
    $paddedLinks = array_pad($socialLinks, count($socialLinks) + $extraBlankRows, ['platform' => '', 'url' => '']);
    $initiallyVisible = count($socialLinks) + 2;
@endphp

<x-admin::card :title="__('Social links')">
    <p class="nk-field__hint" style="margin: 0 0 1rem;">
        {{ __('Add any platform -- Facebook, Instagram, YouTube, LinkedIn, Flickr, 500px, Shutterstock, Adobe Stock, X/Twitter, Pinterest, or anything else. Rendered anywhere with the social-icons component.') }}
    </p>

    <form
        method="POST"
        action="{{ route('admin.settings.social.update') }}"
        class="nk-form"
        x-data="{ visible: {{ $initiallyVisible }} }"
    >
        @csrf

        @foreach ($paddedLinks as $index => $link)
            <div class="nk-form__row" x-show="visible > {{ $index }}">
                <div style="flex: 4; min-width: 160px;">
                    <x-admin::form.input
                        name="social_links[{{ $index }}][platform]"
                        :label="__('Platform')"
                        :value="$link['platform'] ?? ''"
                        placeholder="{{ __('Platform name') }}"
                    />
                </div>
                <div style="flex: 8; min-width: 220px;">
                    <x-admin::form.input
                        type="url"
                        name="social_links[{{ $index }}][url]"
                        :label="__('URL')"
                        :value="$link['url'] ?? ''"
                        placeholder="{{ __('https://facebook.com/yourpage') }}"
                    />
                </div>
            </div>
        @endforeach

        <button
            type="button"
            class="nk-btn nk-btn--sm"
            style="align-self: flex-start;"
            x-show="visible < {{ count($paddedLinks) }}"
            @click="visible++"
        >{{ __('Add another') }}</button>

        <div class="nk-form__row" style="margin-top: 1rem;">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
