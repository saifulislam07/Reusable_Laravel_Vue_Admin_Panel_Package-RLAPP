{{--
    <x-admin::brand />

    Logo + wordmark, driven entirely by config('admin-kit.brand.*') with .env
    fallbacks. Settings (Phase 13) later overrides those config values, so this
    component never needs to change.
--}}
@props(['href' => null, 'collapsedLogo' => false])

@php
    $name = (string) config('admin-kit.brand.name', 'Admin');
    $tagline = config('admin-kit.brand.tagline');
    $logo = $collapsedLogo
        ? (config('admin-kit.brand.logo_collapsed') ?: config('admin-kit.brand.logo'))
        : config('admin-kit.brand.logo');

    $initials = (string) config('admin-kit.brand.short_name') ?: Str::of($name)
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
        ->implode('');

    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes }}>
    <span class="nk-brand__mark">
        @if ($logo)
            <img src="{{ asset($logo) }}" alt="{{ $name }}">
        @else
            {{ $initials }}
        @endif
    </span>

    <span class="nk-brand__text">
        <span class="nk-brand__name">{{ $name }}</span>
        @if ($tagline)
            <span class="nk-brand__tagline">{{ $tagline }}</span>
        @endif
    </span>
</{{ $tag }}>
