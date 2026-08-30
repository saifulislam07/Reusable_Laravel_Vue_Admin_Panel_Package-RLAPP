<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">

<title>@hasSection('title')@yield('title') &middot; @endif{{ config('admin-kit.brand.name') }}</title>

@if ($favicon = config('admin-kit.brand.favicon'))
    <link rel="icon" href="{{ asset($favicon) }}">
@endif

{{-- Theme tokens from config('admin-kit.theme.tokens') -- the only place
     colours are defined, so a project re-brands without touching CSS. --}}
<style id="nk-theme">{!! $nkTheme->styleBlock() !!}</style>

{{-- Restores the viewer's shell preferences before first paint. Kept inline
     and tiny on purpose: anything async here would flash. --}}
<script>
    (function () {
        try {
            var root = document.documentElement;
            var sidebar = localStorage.getItem('nk.sidebar');
            if (sidebar === 'collapsed' || sidebar === 'expanded') {
                root.setAttribute('data-sidebar', sidebar);
            }
            var scheme = localStorage.getItem('nk.scheme');
            if (scheme === 'dark' || scheme === 'light') {
                root.setAttribute('data-nk-scheme', scheme);
            } else if (scheme === 'system') {
                root.removeAttribute('data-nk-scheme');
            }
        } catch (e) {
            /* Storage unavailable -- the server-rendered defaults stand. */
        }
    })();
</script>

@vite(['resources/assets/css/admin.css', 'resources/assets/js/admin.js'])

@stack('styles')
