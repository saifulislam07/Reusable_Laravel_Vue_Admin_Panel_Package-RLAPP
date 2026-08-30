@extends('admin::layouts.app')

@section('title', __('Pages'))

@php
    // Built here, not inline on <x-admin::datatable :headers="[...]"> -- see
    // roles/index.blade.php for why a format closure returning raw HTML with
    // double-quoted attributes breaks Blade's component-tag parser when it's
    // embedded directly in a colon-attribute expression.
    $pageHeaders = [
        ['key' => 'title', 'label' => __('Title'), 'sortable' => true],
        ['key' => 'slug', 'label' => __('Slug'), 'sortable' => false],
        [
            'key' => 'status',
            'label' => __('Status'),
            'sortable' => true,
            'format' => function ($page) {
                $variant = $page->status->badgeVariant();
                $label = $page->status->label();

                return '<span class="nk-badge nk-badge--'.$variant.'">'.e($label).'</span>';
            },
        ],
        [
            'key' => 'updated_at',
            'label' => __('Updated'),
            'sortable' => true,
            'format' => fn ($page) => e(optional($page->updated_at)->format('M j, Y')),
        ],
        [
            'key' => 'actions',
            'label' => '',
            'align' => 'right',
            'format' => fn ($page) => '<a class="nk-btn nk-btn--sm" href="'.e(route('admin.pages.edit', $page)).'">'.e(__('Edit')).'</a>',
        ],
    ];

    $pageBulkActions = [
        [
            'key' => 'delete',
            'label' => __('Delete selected'),
            'variant' => 'danger',
            'url' => route('admin.pages.bulk-destroy'),
            'confirm' => [
                'title' => __('Delete the selected pages?'),
                'text' => __('This cannot be undone.'),
                'label' => __('Delete'),
            ],
        ],
    ];
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Pages')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Pages')],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('admin.pages.create') }}" class="nk-btn nk-btn--primary">{{ __('New page') }}</a>
        </x-slot:actions>
    </x-admin::page-header>

    <x-admin::card>
        <x-admin::datatable
            :headers="$pageHeaders"
            :rows="$pages"
            :search-placeholder="__('Search title or slug…')"
            :bulk-actions="$pageBulkActions"
            :empty-message="__('No pages match your search.')"
        />
    </x-admin::card>
@endsection
