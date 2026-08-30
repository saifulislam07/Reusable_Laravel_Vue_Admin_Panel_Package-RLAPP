@extends('admin::layouts.app')

@section('title', __('New menu item'))

@section('content')
    <x-admin::page-header
        :title="__('New menu item')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Menus'), 'url' => route('admin.menus.index')],
            ['label' => __('New')],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.menus.partials.form')
    </x-admin::card>
@endsection
