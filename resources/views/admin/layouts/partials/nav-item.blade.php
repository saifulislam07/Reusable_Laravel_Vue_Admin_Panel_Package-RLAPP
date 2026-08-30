{{--
    One sidebar row. Recurses into itself for sub-trees.

    Expects: $item (App\Support\Admin\MenuItem)
--}}
@php
    $isActive = $item->isActive();
    $badgeClass = $item->badge !== null
        ? 'nk-badge nk-badge--'.($item->badgeVariant ?: 'primary')
        : null;
@endphp

<li>
    @if ($item->hasChildren())
        <div x-data="{ open: {{ $isActive ? 'true' : 'false' }} }">
            <button
                type="button"
                @class(['nk-nav__link', 'is-active' => $isActive])
                @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                aria-expanded="{{ $isActive ? 'true' : 'false' }}"
            >
                <x-admin::icon :name="$item->icon ?? 'dot'" />
                <span class="nk-nav__label">{{ $item->label }}</span>
                @if ($badgeClass)
                    <span class="{{ $badgeClass }}">{{ $item->badge }}</span>
                @endif
                <x-admin::icon name="chevron-right" class="nk-icon--sm nk-nav__caret" />
            </button>

            <ul class="nk-nav__tree" x-show="open" @unless ($isActive) style="display: none" @endunless>
                @foreach ($item->children as $child)
                    @include('admin::layouts.partials.nav-item', ['item' => $child])
                @endforeach
            </ul>
        </div>
    @else
        <a
            href="{{ $item->url ?? '#' }}"
            @class(['nk-nav__link', 'is-active' => $isActive])
            @if ($isActive) aria-current="page" @endif
        >
            <x-admin::icon :name="$item->icon ?? 'dot'" />
            <span class="nk-nav__label">{{ $item->label }}</span>
            @if ($badgeClass)
                <span class="{{ $badgeClass }}">{{ $item->badge }}</span>
            @endif
        </a>
    @endif
</li>
