@extends('admin::layouts.auth')

@section('title', __('Sign in'))
@section('heading', __('Sign in'))
@section('subheading', __('Enter your credentials to reach the panel.'))

@section('content')
    <form method="POST" action="{{ route('admin.login.attempt') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="email"
            type="email"
            :label="__('Email address')"
            autocomplete="username"
            autofocus
            required
        />

        <x-admin::form.input
            name="password"
            type="password"
            :label="__('Password')"
            autocomplete="current-password"
            required
        />

        <div class="nk-form__row">
            @if (config('admin-kit.auth.remember'))
                <x-admin::form.checkbox name="remember" :label="__('Remember me')" />
            @else
                <span></span>
            @endif

            <a href="{{ route('admin.password.request') }}">{{ __('Forgot password?') }}</a>
        </div>

        <button type="submit" class="nk-btn nk-btn--primary nk-btn--block">
            {{ __('Sign in') }}
        </button>
    </form>
@endsection
