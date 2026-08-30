@extends('admin::layouts.app')

@section('title', __('My profile'))

@section('content')
    <x-admin::page-header
        :title="__('My profile')"
        :subtitle="$user->name"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('My profile')],
        ]"
    />

    <div class="nk-grid">
        <x-admin::card :title="__('Profile information')">
            <form method="POST" action="{{ route('admin.profile.update') }}" class="nk-form">
                @csrf
                @method('PUT')

                <x-admin::form.input name="name" :label="__('Name')" :value="old('name', $user->name)" required autofocus />

                <x-admin::form.input
                    name="email"
                    type="email"
                    :label="__('Email')"
                    :value="old('email', $user->email)"
                    required
                />

                <x-admin::form.input
                    name="current_password"
                    type="password"
                    :label="__('Current password')"
                    autocomplete="current-password"
                    :hint="__('Only needed if you change your email address.')"
                />

                <div class="nk-form__row">
                    <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save changes') }}</button>
                </div>
            </form>
        </x-admin::card>

        <x-admin::card :title="__('Change password')">
            <form method="POST" action="{{ route('admin.profile.password.update') }}" class="nk-form">
                @csrf
                @method('PUT')

                <x-admin::form.input
                    name="current_password"
                    type="password"
                    :label="__('Current password')"
                    autocomplete="current-password"
                    :error="($errors ?? null)?->getBag('updatePassword')?->first('current_password')"
                    required
                />

                <x-admin::form.input
                    name="password"
                    type="password"
                    :label="__('New password')"
                    autocomplete="new-password"
                    :error="($errors ?? null)?->getBag('updatePassword')?->first('password')"
                    required
                />

                <x-admin::form.input
                    name="password_confirmation"
                    type="password"
                    :label="__('Confirm new password')"
                    autocomplete="new-password"
                    required
                />

                <p class="nk-field__hint">
                    {{ __('Changing your password automatically signs out every other session on your account.') }}
                </p>

                <div class="nk-form__row">
                    <button type="submit" class="nk-btn nk-btn--primary">{{ __('Update password') }}</button>
                </div>
            </form>
        </x-admin::card>

        <x-admin::card :title="__('Sessions')">
            <p class="nk-field__hint" style="margin: 0 0 1rem;">
                {{ __('If you believe your account has been used on another device or browser you don\'t recognize, sign it out below.') }}
            </p>

            <form method="POST" action="{{ route('admin.profile.logout-other-devices') }}" class="nk-form">
                @csrf

                <x-admin::form.input
                    name="current_password"
                    type="password"
                    :label="__('Current password')"
                    autocomplete="current-password"
                    :error="($errors ?? null)?->getBag('logoutOtherDevices')?->first('current_password')"
                    required
                />

                <div class="nk-form__row">
                    <button type="submit" class="nk-btn">{{ __('Log out other devices') }}</button>
                </div>
            </form>
        </x-admin::card>
    </div>
@endsection
