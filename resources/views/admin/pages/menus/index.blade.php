@extends('admin::layouts.app')

@section('title', __('Menus'))

@section('content')
    <x-admin::page-header
        :title="__('Menus')"
        :subtitle="__('Drag a row by its handle to reorder or re-nest it — saved instantly, no page reload.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Menus')],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('admin.menus.create') }}" class="nk-btn nk-btn--primary">
                <x-admin::icon name="dashboard" class="nk-icon--sm" />
                {{ __('New menu item') }}
            </a>
        </x-slot:actions>
    </x-admin::page-header>

    <x-admin::card>
        @if ($isEmpty)
            <p class="nk-empty">{{ __('No menu items yet.') }}</p>
        @else
            <ul
                class="nk-menu-tree"
                data-menu-tree="{{ route('admin.menus.reorder') }}"
                data-menu-tree-saved-message="{{ __('Order saved.') }}"
                data-menu-tree-error-message="{{ __('Could not save the new order.') }}"
            >
                @foreach ($tree as $node)
                    @include('admin::pages.menus.partials.tree-item', ['node' => $node])
                @endforeach
            </ul>
        @endif
    </x-admin::card>
@endsection
