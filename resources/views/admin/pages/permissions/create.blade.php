@extends('admin::layouts.app')

@section('title', __('New permission'))

@section('content')
    <x-admin::page-header
        :title="__('New permission')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Permissions'), 'url' => route('admin.permissions.index')],
            ['label' => __('New')],
        ]"
    />

    <x-admin::card>
        <form method="POST" action="{{ route('admin.permissions.store') }}" class="nk-form">
            @csrf

            <x-admin::form.input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                required
                autofocus
                placeholder="module.action"
                :hint="__('Convention used throughout the panel: the module name, a dot, then the action — e.g. reports.export.')"
            />

            <div class="nk-form__row">
                <button type="submit" class="nk-btn nk-btn--primary">{{ __('Create permission') }}</button>
                <a href="{{ route('admin.permissions.index') }}" class="nk-btn">{{ __('Cancel') }}</a>
            </div>
        </form>
    </x-admin::card>
@endsection
