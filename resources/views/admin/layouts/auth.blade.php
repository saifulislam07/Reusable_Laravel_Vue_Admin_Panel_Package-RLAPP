{{--
    NG Admin Kit -- authentication layout.

    Two variants, chosen by config('admin-kit.theme.auth_layout'):
      'split'    -- branded panel beside the form
      'centered' -- brand above a centred card

    Sections a page may define:
      @section('title')      browser title fragment
      @section('heading')    form heading
      @section('subheading') supporting line under the heading
      @section('content')    the form itself (required)
      @section('aside')      overrides the split panel's body copy
      @section('footer')     small print under the card
--}}
@php
    $nkVariant = config('admin-kit.theme.auth_layout') === 'centered' ? 'centered' : 'split';
    $nkScheme = $nkTheme->colorScheme();
    $nkHomeRoute = (string) config('admin-kit.route.home');
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @if ($nkScheme !== 'system') data-nk-scheme="{{ $nkScheme }}" @endif
>
<head>
    @include('admin::layouts.partials.head')
</head>
<body class="nk-body nk-body--auth">
    <div class="nk-auth nk-auth--{{ $nkVariant }}">
        @if ($nkVariant === 'split')
            <aside class="nk-auth__aside">
                <x-admin::brand class="nk-auth__brand" />

                <div class="nk-auth__aside-body">
                    @hasSection('aside')
                        @yield('aside')
                    @else
                        <h2 class="nk-auth__aside-title">
                            {{ __('Welcome back') }}
                        </h2>
                        <p class="nk-auth__aside-text">
                            {{ __('Sign in to manage content, users and settings from one place.') }}
                        </p>
                    @endif
                </div>

                <p class="nk-auth__aside-foot">
                    &copy; {{ now()->year }} {{ config('admin-kit.brand.name') }}
                </p>
            </aside>
        @endif

        <main class="nk-auth__panel">
            <div class="nk-auth__card">
                @if ($nkVariant === 'centered')
                    <x-admin::brand class="nk-auth__brand nk-auth__brand--stacked" />
                @endif

                <div class="nk-auth__head">
                    <h1 class="nk-auth__title">@yield('heading', __('Sign in'))</h1>
                    @hasSection('subheading')
                        <p class="nk-auth__subtitle">@yield('subheading')</p>
                    @endif
                </div>

                @if (session('status'))
                    <x-admin::alert variant="success">{{ session('status') }}</x-admin::alert>
                @endif

                @if (session('success'))
                    <x-admin::alert variant="success">{{ session('success') }}</x-admin::alert>
                @endif

                {{-- Field-level messages render beside their input; this catches
                     anything not tied to a field. --}}
                @if (($errors ?? null)?->has('__form'))
                    <x-admin::alert variant="danger">{{ $errors->first('__form') }}</x-admin::alert>
                @endif

                @yield('content')
            </div>

            @hasSection('footer')
                <p class="nk-auth__foot">@yield('footer')</p>
            @else
                <p class="nk-auth__foot">
                    @if (Route::has($nkHomeRoute))
                        <a href="{{ url('/') }}">{{ __('Back to site') }}</a>
                    @endif
                </p>
            @endif
        </main>
    </div>

    @stack('scripts')
</body>
</html>
