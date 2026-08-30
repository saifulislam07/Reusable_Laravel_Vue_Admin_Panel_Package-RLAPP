@extends('admin::layouts.app')

@section('title', __('New user'))

@section('content')
    <x-admin::page-header
        :title="__('New user')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Users'), 'url' => route('admin.users.index')],
            ['label' => __('New')],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.users.partials.form')
    </x-admin::card>
@endsection
