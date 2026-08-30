@php
    $nkHomeRoute = (string) config('admin-kit.route.home');
    $nkHomeUrl = Route::has($nkHomeRoute) ? route($nkHomeRoute) : url('/');
@endphp

<aside class="nk-sidebar" id="nk-sidebar">
    <x-admin::brand class="nk-sidebar__brand" :href="$nkHomeUrl" />

    <nav class="nk-sidebar__scroll" aria-label="{{ __('Admin navigation') }}">
        <ul class="nk-nav">
            @forelse ($nkMenu->items() as $nkItem)
                @include('admin::layouts.partials.nav-item', ['item' => $nkItem])
            @empty
                <li class="nk-nav__heading">{{ __('No menu items') }}</li>
            @endforelse
        </ul>
    </nav>

    <div class="nk-sidebar__footer">
        {{ config('admin-kit.brand.name') }} &middot; {{ now()->year }}
    </div>
</aside>
