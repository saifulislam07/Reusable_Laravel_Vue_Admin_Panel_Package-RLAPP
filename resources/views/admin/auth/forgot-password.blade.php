@extends('admin::layouts.auth')

@section('title', __('Forgot password'))
@section('heading', __('Forgot your password?'))
@section('subheading', __('Enter your email and we will send you a reset link.'))

@section('aside')
    <h2 class="nk-auth__aside-title">{{ __('Locked out?') }}</h2>
    <p class="nk-auth__aside-text">
        {{ __('Reset links are single-use and expire shortly after they are sent.') }}
    </p>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.password.email') }}" class="nk-form">
        @csrf

        <x-admin::form.input
            name="email"
            type="email"
            :label="__('Email address')"
            autocomplete="username"
            autofocus
            required
        />

        <button type="submit" class="nk-btn nk-btn--primary nk-btn--block">
            {{ __('Email reset link') }}
        </button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('admin.login') }}">{{ __('Back to sign in') }}</a>
@endsection
