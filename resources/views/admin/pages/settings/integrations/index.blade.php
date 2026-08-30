@extends('admin::layouts.app')

@section('title', __('Integrations'))

@php
    $nkTabs = [
        'smtp' => __('SMTP'),
        'google-analytics' => __('Google Analytics'),
        'google-search-console' => __('Search Console'),
        'facebook-messenger' => __('Messenger'),
        'whatsapp' => __('WhatsApp'),
    ];
    $nkActiveTab = array_key_exists((string) request('tab'), $nkTabs) ? (string) request('tab') : 'smtp';
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Integrations')"
        :subtitle="__('Saved in the database, not just .env — editable without a deploy.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Integrations')],
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

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'smtp'" x-cloak>
            @include('admin::pages.settings.integrations.partials.smtp')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'google-analytics'" x-cloak>
            @include('admin::pages.settings.integrations.partials.google-analytics')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'google-search-console'" x-cloak>
            @include('admin::pages.settings.integrations.partials.google-search-console')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'facebook-messenger'" x-cloak>
            @include('admin::pages.settings.integrations.partials.facebook-messenger')
        </div>

        <div class="nk-tabs__panel" role="tabpanel" x-show="tab === 'whatsapp'" x-cloak>
            @include('admin::pages.settings.integrations.partials.whatsapp')
        </div>
    </div>
@endsection
