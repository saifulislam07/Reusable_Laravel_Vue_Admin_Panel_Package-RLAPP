@extends('admin::layouts.app')

@section('title', __('Settings'))

@php
    $nkTabs = [
        'general' => __('General'),
        'contact' => __('Contact'),
        'social' => __('Social'),
        'seo' => __('SEO'),
        'system' => __('System'),
    ];
    $nkActiveTab = array_key_exists((string) request('tab'), $nkTabs) ? (string) request('tab') : 'general';
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Settings')"
        :subtitle="__('Branding, contact details, and social links -- used anywhere in the panel and, once a project renders them, the public site too.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Settings')],
        ]"
    />

    <div class="nk-tabs" x-data="{ tab: @js($nkActiveTab) }">
        <div class="nk-tabs__nav" role="tablist">
            @foreach ($nkTabs as $nkKey => $nkLabel)
                <button
                    type="button"
                    role="tab"
                    class="nk-tabs__link"
                    :class="{ 'is-active': tab === '{{ $nkKey }}' }"
                    :aria-selected="tab === '{{ $nkKey }}' ? 'true' : 'false'"
                    @click="tab = '{{ $nkKey }}'"
                >{{ $nkLabel }}</button>
            @endforeach
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'general'" x-cloak>
            @include('admin::pages.settings.partials.general')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'contact'" x-cloak>
            @include('admin::pages.settings.partials.contact')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'social'" x-cloak>
            @include('admin::pages.settings.partials.social')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'seo'" x-cloak>
            @include('admin::pages.settings.partials.seo')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'system'" x-cloak>
            @include('admin::pages.settings.partials.system')
        </div>
    </div>
@endsection
