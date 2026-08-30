@extends('admin::layouts.app')

@section('title', __('New role'))

@section('content')
    <x-admin::page-header
        :title="__('New role')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Roles'), 'url' => route('admin.roles.index')],
            ['label' => __('New')],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.roles.partials.form')
    </x-admin::card>
@endsection
