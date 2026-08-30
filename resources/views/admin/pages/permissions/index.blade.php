@extends('admin::layouts.app')

@section('title', __('Permissions'))

@section('content')
    <x-admin::page-header
        :title="__('Permissions')"
        :subtitle="__('Names are permanent once created — route middleware, seeders, and menu items all reference them by exact string.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Permissions')],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('admin.permissions.create') }}" class="nk-btn nk-btn--primary">{{ __('New permission') }}</a>
        </x-slot:actions>
    </x-admin::page-header>

    <x-admin::card>
        <x-admin::datatable
            :headers="[
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'roles_count', 'label' => __('Roles')],
                [
                    'key' => 'created_at',
                    'label' => __('Created'),
                    'sortable' => true,
                    'format' => fn ($permission) => e(optional($permission->created_at)->format('M j, Y')),
                ],
            ]"
            :rows="$permissions"
            :search="true"
            :bulk-actions="[
                [
                    'key' => 'delete',
                    'label' => __('Delete selected'),
                    'variant' => 'danger',
                    'url' => route('admin.permissions.bulk-destroy'),
                    'confirm' => [
                        'title' => __('Delete the selected permissions?'),
                        'text' => __('Anything gated by one of these — a route, a menu item — becomes unreachable for everyone except the protected role.'),
                        'label' => __('Delete'),
                    ],
                ],
            ]"
            :empty-message="__('No permissions yet.')"
        />
    </x-admin::card>
@endsection
