{{--
    One row in the menu tree. Recurses into its own children.

    Expects: $node = ['menu' => Menu, 'children' => list<node>]
--}}
@php
    $menu = $node['menu'];
    $children = $node['children'];
    $target = $menu->route ? (\Illuminate\Support\Facades\Route::has($menu->route) ? route($menu->route) : null) : $menu->url;
@endphp

<li data-id="{{ $menu->id }}">
    <div class="nk-menu-row @unless ($menu->is_active) is-inactive @endunless">
        <button type="button" class="nk-menu-row__drag" data-menu-drag aria-label="{{ __('Drag to reorder') }}">
            <x-admin::icon name="menu" class="nk-icon--sm" />
        </button>

        <x-admin::icon :name="$menu->icon ?? 'dot'" class="nk-icon--sm" />

        <span class="nk-menu-row__label">{{ $menu->label }}</span>

        @unless ($menu->is_active)
            <span class="nk-badge">{{ __('Inactive') }}</span>
        @endunless

        @if ($menu->permission_name)
            <span class="nk-badge nk-badge--info">{{ $menu->permission_name }}</span>
        @endif

        <span class="nk-menu-row__target">{{ $target ?? $menu->route ?? $menu->url }}</span>

        <span class="nk-menu-row__actions">
            <a class="nk-btn nk-btn--sm nk-btn--ghost" href="{{ route('admin.menus.edit', $menu) }}">{{ __('Edit') }}</a>

            <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}" data-nk-confirm data-nk-confirm-title="{{ __('Delete :label?', ['label' => $menu->label]) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="nk-btn nk-btn--sm nk-btn--ghost">{{ __('Delete') }}</button>
            </form>
        </span>
    </div>

    @if ($children !== [])
        <ul class="nk-menu-tree__children">
            @foreach ($children as $child)
                @include('admin::pages.menus.partials.tree-item', ['node' => $child])
            @endforeach
        </ul>
    @else
        {{-- An empty (but present) list is what lets a leaf item accept a
             drop and become a parent -- SortableJS needs somewhere to put it. --}}
        <ul class="nk-menu-tree__children nk-menu-tree__children--empty"></ul>
    @endif
</li>
