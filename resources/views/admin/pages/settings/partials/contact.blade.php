{{--
    Expects: $contact (array)
--}}
<x-admin::card :title="__('Contact')">
    <form method="POST" action="{{ route('admin.settings.contact.update') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="email"
            type="email"
            :label="__('Email')"
            :value="old('email', $contact['email'] ?? null)"
        />

        <x-admin::form.input name="phone" :label="__('Phone')" :value="old('phone', $contact['phone'] ?? null)" />

        <x-admin::form.textarea
            name="address"
            :label="__('Address')"
            :value="old('address', $contact['address'] ?? null)"
        />

        <x-admin::form.input
            name="map_embed"
            type="url"
            :label="__('Map embed URL')"
            :value="old('map_embed', $contact['map_embed'] ?? null)"
            :hint="__('The src URL from a Google Maps (or similar) embed -- rendered in an iframe, not pasted as raw HTML.')"
        />

        @if (filled($contact['map_embed'] ?? null))
            <div class="nk-field">
                <span class="nk-label">{{ __('Preview') }}</span>
                <iframe src="{{ $contact['map_embed'] }}" style="width: 100%; height: 260px; border: 0; border-radius: var(--nk-radius-sm);" loading="lazy"></iframe>
            </div>
        @endif

        <div class="nk-form__row">
            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-admin::card>
