{{--
    NG Admin Kit -- shared error page.

    Standalone (not the panel shell) so it renders correctly whether or not
    the visitor is signed in, and whether or not the failure happened inside
    the layout itself.

    Sections a status page may define:
      @section('title')    heading, and the browser title
      @section('code')     defaults to the HTTP status
      @section('icon')     icon name from <x-admin::icon>
      @section('message')  supporting copy
      @section('actions')  overrides the default buttons
--}}
@php
    $nkScheme = $nkTheme->colorScheme();
    $nkHomeRoute = (string) config('admin-kit.route.home');
    $nkLoginRoute = (string) config('admin-kit.route.login');
    $nkUser = auth()->guard(config('admin-kit.guard'))->user();
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if ($nkScheme !== 'system') data-nk-scheme="{{ $nkScheme }}" @endif
>
<head>
    @include('admin::layouts.partials.head')
</head>
<body class="nk-body nk-body--error">
    <main class="nk-error">
        <div class="nk-error__card">
            <span class="nk-error__icon">
                <x-admin::icon :name="trim($__env->yieldContent('icon', 'alert-triangle'))" />
            </span>

            <p class="nk-error__code">@yield('code', $status)</p>

            <h1 class="nk-error__title">@yield('title', __('Something went wrong'))</h1>

            <p class="nk-error__message">
                @yield('message', __('An unexpected error occurred. Please try again.'))
            </p>

            <div class="nk-error__actions">
                @hasSection('actions')
                    @yield('actions')
                @else
                    @if ($nkUser && Route::has($nkHomeRoute))
                        <a class="nk-btn nk-btn--primary" href="{{ route($nkHomeRoute) }}">
                            <x-admin::icon name="dashboard" class="nk-icon--sm" />
                            {{ __('Back to dashboard') }}
                        </a>
                    @elseif (Route::has($nkLoginRoute))
                        <a class="nk-btn nk-btn--primary" href="{{ route($nkLoginRoute) }}">
                            <x-admin::icon name="log-out" class="nk-icon--sm" />
                            {{ __('Go to sign in') }}
                        </a>
                    @endif

                    <a class="nk-btn" href="{{ url('/') }}">{{ __('Back to site') }}</a>
                @endif
            </div>
        </div>
    </main>

    @stack('scripts')
</body>
</html>
