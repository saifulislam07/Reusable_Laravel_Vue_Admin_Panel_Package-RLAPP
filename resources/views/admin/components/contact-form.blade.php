{{--
    <x-admin::contact-form />

    A droppable contact form (Phase 16) -- embed this on any public page a
    consuming project builds; it always posts to the same App\Http\
    Controllers\ContactController@store, so there is nothing else to wire
    up. Pass :action to point it somewhere else (e.g. a page-specific
    endpoint that also does something extra with the submission).
--}}
@props(['action' => null])

<form method="POST" action="{{ $action ?? route('contact.store') }}" class="nk-form">
    @csrf

    @if (session('status'))
        <x-admin::alert variant="success">{{ session('status') }}</x-admin::alert>
    @endif

    <x-admin::form.input name="name" :label="__('Name')" :value="old('name')" required />
    <x-admin::form.input name="email" type="email" :label="__('Email')" :value="old('email')" required />
    <x-admin::form.input name="phone" :label="__('Phone')" :value="old('phone')" />
    <x-admin::form.input name="subject" :label="__('Subject')" :value="old('subject')" />
    <x-admin::form.textarea name="message" :label="__('Message')" :value="old('message')" required />

    {{--
        Honeypot: hidden with CSS (not the `hidden` attribute -- some bots
        specifically skip anything marked hidden="hidden" to dodge exactly
        this trick, but still fill in every visible-looking field) and kept
        out of the tab order and off screen readers, so no real visitor
        ever notices or fills it in. A submission that does gets rejected
        by ContactFormRequest's `prohibited` rule on this field.
    --}}
    <div style="position: absolute; left: -9999px;" aria-hidden="true">
        <label for="nk-contact-website">{{ __('Leave this field blank') }}</label>
        <input type="text" name="website" id="nk-contact-website" tabindex="-1" autocomplete="off">
    </div>

    <div class="nk-form__row">
        <button type="submit" class="nk-btn nk-btn--primary">{{ __('Send message') }}</button>
    </div>
</form>
