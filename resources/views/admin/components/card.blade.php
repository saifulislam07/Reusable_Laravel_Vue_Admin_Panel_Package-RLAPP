{{-- <x-admin::card title="Recent activity">…</x-admin::card> --}}
@props(['title' => null])

<section {{ $attributes->class('nk-card') }}>
    @if ($title || isset($header))
        <div class="nk-card__header">
            @if ($title)
                <h2 class="nk-card__title">{{ $title }}</h2>
            @endif
            {{ $header ?? '' }}
        </div>
    @endif

    <div class="nk-card__body">{{ $slot }}</div>
</section>
