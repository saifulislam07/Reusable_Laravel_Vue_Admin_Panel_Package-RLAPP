@extends('admin::layouts.app')

@section('title', __('Edit menu item'))

@section('content')
    <x-admin::page-header
        :title="__('Edit menu item')"
        :subtitle="$menu->label"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Menus'), 'url' => route('admin.menus.index')],
            ['label' => $menu->label],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.menus.partials.form')
    </x-admin::card>
@endsection
