@extends('admin::layouts.app')

@section('title', __('Edit role'))

@section('content')
    <x-admin::page-header
        :title="__('Edit role')"
        :subtitle="$role->name"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Roles'), 'url' => route('admin.roles.index')],
            ['label' => $role->name],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.roles.partials.form')
    </x-admin::card>
@endsection
