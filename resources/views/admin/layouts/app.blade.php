{{--
    NG Admin Kit -- master layout.

    Sections a page may define:
      @section('title')        browser title fragment
      @section('content')      the page body (required)
      @push('styles')          extra <link>/<style> in the head
      @push('scripts')         extra <script> before </body>

    Shell state (sidebar rail, mobile drawer, colour scheme) lives on <html>
    as data-* attributes; see layouts.partials.head for the boot script.
--}}
@php
    $nkSidebarDefault = $nkTheme->sidebarStartsCollapsed() ? 'collapsed' : 'expanded';
    $nkScheme = $nkTheme->colorScheme();
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-sidebar="{{ $nkSidebarDefault }}"
    data-mobile-nav="closed"
    @if ($nkScheme !== 'system') data-nk-scheme="{{ $nkScheme }}" @endif
>
<head>
    @include('admin::layouts.partials.head')
</head>
<body class="nk-body">
    <div class="nk-app">
        @include('admin::layouts.partials.sidebar')

        <button
            type="button"
            class="nk-scrim"
            x-data
            @click="$store.nk.closeMobileNav()"
        >
            <span class="nk-visually-hidden">{{ __('Close navigation') }}</span>
        </button>

        <div class="nk-main">
            @include('admin::layouts.partials.topbar')

            <main class="nk-content" id="nk-content">
                @yield('content')
            </main>

            @include('admin::layouts.partials.footer')
        </div>
    </div>

    <x-admin::toast-container />
    <x-admin::media-picker />

    @stack('scripts')
</body>
</html>
