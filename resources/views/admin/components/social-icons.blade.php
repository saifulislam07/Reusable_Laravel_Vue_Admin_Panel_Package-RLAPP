{{--
    <x-admin::social-icons />

    Renders the active links from Settings > Social (Phase 13) anywhere --
    the public site, the admin footer, wherever. Platform names are
    arbitrary (the plan asks for a "dynamic key-value list" so a new one
    never needs a migration), so this renders a generic external-link glyph
    plus the platform's own name rather than per-brand logos -- accurate
    brand marks for an open-ended, admin-typed platform list isn't
    something this can guarantee, and a wrong logo is worse than a plain
    one.
--}}
@props(['class' => null])

@php
    $links = collect(app(\App\Support\Admin\SettingsManager::class)->group('social')['social_links'] ?? [])
        ->filter(fn ($link) => filled($link['url'] ?? null));
@endphp

@if ($links->isNotEmpty())
    <ul {{ $attributes->class(['nk-social-icons', $class]) }}>
        @foreach ($links as $link)
            <li>
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $link['platform'] ?? '' }}">
                    <x-admin::icon name="external" class="nk-icon--sm" />
                    <span>{{ $link['platform'] ?? $link['url'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
