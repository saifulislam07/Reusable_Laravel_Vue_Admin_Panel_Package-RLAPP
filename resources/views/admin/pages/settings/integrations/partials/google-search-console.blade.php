{{--
    Expects: $googleSearchConsole (array, from IntegrationSettings::get('google_search_console'))
--}}
<x-admin::card :title="__('Google Search Console')">
    <p class="nk-field__hint" style="margin: 0 0 1rem;">
        {{ __('Paste the "content" value from Search Console\'s HTML tag verification method — the meta tag is added to the public site automatically once this is set.') }}
    </p>

    <form method="POST" action="{{ route('admin.settings.integrations.google-search-console.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="verification_meta"
            :label="__('Verification content')"
            :value="$googleSearchConsole['verification_meta'] ?? null"
            placeholder="abcDEF123…"
        />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
