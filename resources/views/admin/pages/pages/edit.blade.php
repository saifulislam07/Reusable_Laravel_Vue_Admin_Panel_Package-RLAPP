@extends('admin::layouts.app')

@section('title', __('Edit page'))

@section('content')
    <x-admin::page-header
        :title="__('Edit page')"
        :subtitle="$page->title"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Pages'), 'url' => route('admin.pages.index')],
            ['label' => $page->title],
        ]"
    />

    <x-admin::card>
        @include('admin::pages.pages.partials.form')
    </x-admin::card>
@endsection
