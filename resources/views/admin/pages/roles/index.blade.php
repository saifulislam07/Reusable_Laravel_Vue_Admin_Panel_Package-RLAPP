@extends('admin::layouts.app')

@section('title', __('Roles'))

@php
    // Built here rather than inline on <x-admin::datatable :headers="[...]">:
    // a format closure returning HTML with double-quoted attributes (class="…")
    // breaks Blade's component-tag parser when it's embedded directly in a
    // colon-attribute expression -- the tag compiler's boundary detection for
    // :headers="…" isn't aware of the quote's PHP-string nesting, so it can
    // end the attribute early and leave the whole tag uncompiled. A plain
    // variable reference like :headers="$roleHeaders" sidesteps that entirely.
    $roleHeaders = [
        [
            'key' => 'name',
            'label' => __('Name'),
            'sortable' => true,
            'format' => function ($role) use ($protectedRole) {
                $badge = $role->name === $protectedRole
                    ? ' <span class="nk-badge nk-badge--primary">'.e(__('Protected')).'</span>'
                    : '';

                return e($role->name).$badge;
            },
        ],
        ['key' => 'permissions_count', 'label' => __('Permissions')],
        ['key' => 'users_count', 'label' => __('Users')],
        [
            'key' => 'created_at',
            'label' => __('Created'),
            'sortable' => true,
            'format' => fn ($role) => e(optional($role->created_at)->format('M j, Y')),
        ],
        [
            'key' => 'actions',
            'label' => '',
            'align' => 'right',
            'format' => fn ($role) => '<a class="nk-btn nk-btn--sm" href="'.e(route('admin.roles.edit', $role)).'">'.e(__('Edit')).'</a>',
        ],
    ];

    $roleBulkActions = [
        [
            'key' => 'delete',
            'label' => __('Delete selected'),
            'variant' => 'danger',
            'url' => route('admin.roles.bulk-destroy'),
            'confirm' => [
                'title' => __('Delete the selected roles?'),
                'text' => __('The :role role is never deleted, even if selected.', ['role' => $protectedRole]),
                'label' => __('Delete'),
            ],
        ],
    ];
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Roles')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Roles')],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('admin.roles.create') }}" class="nk-btn nk-btn--primary">{{ __('New role') }}</a>
        </x-slot:actions>
    </x-admin::page-header>

    <x-admin::card>
        <x-admin::datatable
            :headers="$roleHeaders"
            :rows="$roles"
            :search="true"
            :bulk-actions="$roleBulkActions"
            :empty-message="__('No roles yet.')"
        />
    </x-admin::card>
@endsection
