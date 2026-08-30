{{--
    Expects: $facebookMessenger (array, from IntegrationSettings::get('facebook_messenger'))
--}}
<x-admin::card :title="__('Facebook Messenger')">
    <p class="nk-field__hint" style="margin: 0 0 1rem;">
        {{ __('Adds the Messenger chat plugin to the public site once enabled.') }}
    </p>

    <form method="POST" action="{{ route('admin.settings.integrations.facebook-messenger.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="page_id"
            :label="__('Page ID')"
            :value="$facebookMessenger['page_id'] ?? null"
        />

        <x-admin::form.input
            name="app_id"
            :label="__('App ID')"
            :value="$facebookMessenger['app_id'] ?? null"
        />

        <x-admin::form.checkbox
            name="enabled"
            :label="__('Show the chat plugin on the public site')"
            :checked="(bool) ($facebookMessenger['enabled'] ?? false)"
        />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
