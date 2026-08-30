@extends('admin::layouts.app')

@section('title', __('Edit user'))

@section('content')
    <x-admin::page-header
        :title="__('Edit user')"
        :subtitle="$user->name"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Users'), 'url' => route('admin.users.index')],
            ['label' => $user->name],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.users.partials.form')
    </x-admin::card>
@endsection
