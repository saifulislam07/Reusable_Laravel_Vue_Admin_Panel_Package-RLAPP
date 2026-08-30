@extends('admin::layouts.app')

@section('title', __('New page'))

@section('content')
    <x-admin::page-header
        :title="__('New page')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Pages'), 'url' => route('admin.pages.index')],
            ['label' => __('New')],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.pages.partials.form')
    </x-admin::card>
@endsection
