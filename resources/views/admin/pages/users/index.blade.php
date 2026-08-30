@extends('admin::layouts.app')

@section('title', __('Users'))

@php
    // Built here rather than inline on <x-admin::datatable :headers="[...]">
    // -- see roles/index.blade.php for why a format closure returning raw
    // HTML with double-quoted attributes breaks Blade's component-tag
    // parser when it's embedded directly in a colon-attribute expression.
    $userHeaders = [
        [
            'key' => 'avatar',
            'label' => '',
            'format' => fn ($user) => $user->avatarUrl()
                ? '<span class="nk-avatar"><img src="'.e($user->avatarUrl()).'" alt=""></span>'
                : '<span class="nk-avatar">'.e(Str::of($user->name)->explode(' ')->filter()->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('')).'</span>',
        ],
        ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
        ['key' => 'email', 'label' => __('Email'), 'sortable' => true],
        [
            'key' => 'status',
            'label' => __('Status'),
            'format' => function ($user) {
                $variant = $user->status->badgeVariant();
                $label = $user->status->label();

                return '<span class="nk-badge nk-badge--'.$variant.'">'.e($label).'</span>';
            },
        ],
        [
            'key' => 'roles',
            'label' => __('Roles'),
            'format' => fn ($user) => e($user->roles->pluck('name')->implode(', ')),
        ],
        [
            'key' => 'created_at',
            'label' => __('Joined'),
            'sortable' => true,
            'format' => fn ($user) => e(optional($user->created_at)->format('M j, Y')),
        ],
        [
            'key' => 'actions',
            'label' => '',
            'align' => 'right',
            'format' => fn ($user) => '<a class="nk-btn nk-btn--sm" href="'.e(route('admin.users.edit', $user)).'">'.e(__('Edit')).'</a>',
        ],
    ];

    $userBulkActions = [
        [
            'key' => 'delete',
            'label' => __('Delete selected'),
            'variant' => 'danger',
            'url' => route('admin.users.bulk-destroy'),
            'confirm' => [
                'title' => __('Delete the selected users?'),
                'text' => __('This cannot be undone.'),
                'label' => __('Delete'),
            ],
        ],
    ];
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Users')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Users')],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="nk-btn nk-btn--primary">{{ __('New user') }}</a>
        </x-slot:actions>
    </x-admin::page-header>

    <x-admin::card>
        @if ($roleOptions->isNotEmpty())
            <form method="GET" class="nk-form__row" style="margin-bottom: 1rem; justify-content: flex-start;">
                <x-admin::form.select
                    name="role"
                    :label="__('Filter by role')"
                    :options="['' => __('All roles')] + $roleOptions->all()"
                    :value="$currentRole"
                    onchange="this.form.submit()"
                />
            </form>
        @endif

        <x-admin::datatable
            :headers="$userHeaders"
            :rows="$users"
            :search-placeholder="__('Search name or email…')"
            :bulk-actions="$userBulkActions"
            :empty-message="__('No users match your search.')"
        />
    </x-admin::card>
@endsection
