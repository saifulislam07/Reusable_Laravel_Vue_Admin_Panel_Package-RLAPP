{{--
    Expects: $googleAnalytics (array, from IntegrationSettings::get('google_analytics'))
--}}
<x-admin::card :title="__('Google Analytics')">
    <p class="nk-field__hint" style="margin: 0 0 1rem;">
        {{ __('Adds the GA4 tag to the public site once enabled.') }}
    </p>

    <form method="POST" action="{{ route('admin.settings.integrations.google-analytics.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="measurement_id"
            :label="__('Measurement ID')"
            :value="$googleAnalytics['measurement_id'] ?? null"
            placeholder="G-XXXXXXXXXX"
        />

        <x-admin::form.checkbox
            name="enabled"
            :label="__('Enable Google Analytics on the public site')"
            :checked="(bool) ($googleAnalytics['enabled'] ?? false)"
        />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
