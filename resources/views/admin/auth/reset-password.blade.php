@extends('admin::layouts.auth')

@section('title', __('Reset password'))
@section('heading', __('Choose a new password'))
@section('subheading', __('Pick something you have not used before.'))

@section('aside')
    <h2 class="nk-auth__aside-title">{{ __('Almost there') }}</h2>
    <p class="nk-auth__aside-text">
        {{ __('Setting a new password signs you out of any other sessions.') }}
    </p>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.password.update') }}" class="nk-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-admin::form.input
            name="email"
            type="email"
            :label="__('Email address')"
            :value="$email"
            autocomplete="username"
            required
        />

        <x-admin::form.input
            name="password"
            type="password"
            :label="__('New password')"
            autocomplete="new-password"
            autofocus
            required
        />

        <x-admin::form.input
            name="password_confirmation"
            type="password"
            :label="__('Confirm new password')"
            autocomplete="new-password"
            required
        />

        <button type="submit" class="nk-btn nk-btn--primary nk-btn--block">
            {{ __('Reset password') }}
        </button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('admin.login') }}">{{ __('Back to sign in') }}</a>
@endsection
