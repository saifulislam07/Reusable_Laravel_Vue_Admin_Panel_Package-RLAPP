{{--
    Expects: $smtp (array, from IntegrationSettings::get('smtp'))
--}}
<x-admin::card :title="__('SMTP')">
    <p class="nk-field__hint" style="margin: 0 0 1rem;">
        {{ __('Once a host is saved here, all outgoing mail — password resets, the test email below, anything else — goes through it instead of the .env mailer.') }}
    </p>

    <form method="POST" action="{{ route('admin.settings.integrations.smtp.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input name="host" :label="__('Host')" :value="$smtp['host'] ?? null" required />

        <x-admin::form.input
            name="port"
            type="number"
            :label="__('Port')"
            :value="$smtp['port'] ?? 587"
            required
        />

        <x-admin::form.input name="username" :label="__('Username')" :value="$smtp['username'] ?? null" />

        <x-admin::form.input
            name="password"
            type="password"
            :label="__('Password')"
            autocomplete="new-password"
            :hint="filled($smtp['password'] ?? null) ? __('Currently set — leave blank to keep it.') : null"
        />

        <x-admin::form.select
            name="encryption"
            :label="__('Encryption')"
            :options="['' => __('None'), 'tls' => 'TLS', 'ssl' => 'SSL']"
            :value="$smtp['encryption'] ?? 'tls'"
        />

        <x-admin::form.input
            name="from_address"
            type="email"
            :label="__('From address')"
            :value="$smtp['from_address'] ?? null"
            required
        />

        <x-admin::form.input name="from_name" :label="__('From name')" :value="$smtp['from_name'] ?? null" required />

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>

            <button
                type="submit"
                formaction="{{ route('admin.settings.integrations.smtp.test') }}"
                class="nk-btn"
            >{{ __('Save and send test email') }}</button>
        </div>
    </form>
</x-admin::card>
