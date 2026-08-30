{{--
    Expects: $whatsapp (array, from IntegrationSettings::get('whatsapp'))
--}}
<x-admin::card :title="__('WhatsApp')">
    <p class="nk-field__hint" style="margin: 0 0 1rem;">
        {{ __('Adds a floating WhatsApp button to the public site once enabled.') }}
    </p>

    <form method="POST" action="{{ route('admin.settings.integrations.whatsapp.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="number"
            :label="__('WhatsApp number')"
            :value="$whatsapp['number'] ?? null"
            placeholder="+15551234567"
            :hint="__('International format, digits only.')"
        />

        <x-admin::form.textarea
            name="message"
            :label="__('Default message')"
            :value="$whatsapp['message'] ?? __('Hi! I have a question.')"
        />

        <x-admin::form.checkbox
            name="enabled"
            :label="__('Show the floating WhatsApp button on the public site')"
            :checked="(bool) ($whatsapp['enabled'] ?? false)"
        />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
