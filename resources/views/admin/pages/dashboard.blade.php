@extends('admin::layouts.app')

@section('title', __('Dashboard'))

@section('content')
    <x-admin::page-header :title="__('Dashboard')" :subtitle="__('Drag a widget by its handle to rearrange the layout.')" />

    <div class="nk-widget-grid" data-widget-grid>
        @foreach ($widgets as $widget)
            <div class="nk-widget nk-widget--{{ $widget->size() }}" data-widget-key="{{ $widget->key() }}">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 0.35rem;">
                    <span class="nk-widget__drag" data-widget-drag title="{{ __('Drag to reorder') }}">
                        <x-admin::icon name="menu" size="16" />
                    </span>
                </div>
                {{ $widget->render() }}
            </div>
        @endforeach
    </div>

    <div class="nk-grid" style="margin-top: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
        <x-admin::card :title="__('Theme tokens')">
            <p>
                {{ __('Every colour below comes from config/admin-kit.php. Override that array to re-brand the panel — no stylesheet edits.') }}
            </p>
            <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                @foreach (['primary', 'success', 'warning', 'danger', 'info'] as $swatch)
                    <span class="nk-badge nk-badge--{{ $swatch }}">{{ $swatch }}</span>
                @endforeach
            </div>
        </x-admin::card>

        <x-admin::card :title="__('Toaster & confirm')">
            <p>
                {{ __('Two global helpers: NK.toast(type, message) and NK.confirm(options). Any form or link carrying data-nk-confirm asks before it proceeds.') }}
            </p>

            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                @foreach (['success', 'danger', 'warning', 'info'] as $variant)
                    <button
                        type="button"
                        class="nk-btn nk-btn--sm"
                        onclick="NK.toast('{{ $variant }}', @js(__('This is a :variant toast.', ['variant' => $variant])))"
                    >{{ $variant }}</button>
                @endforeach

                <button
                    type="button"
                    class="nk-btn nk-btn--sm nk-btn--danger"
                    onclick="NK.confirm({
                        title: @js(__('Delete this record?')),
                        text: @js(__('This cannot be undone.')),
                        confirmLabel: @js(__('Delete')),
                        variant: 'danger',
                    }).then((ok) => ok && NK.toast('success', @js(__('Confirmed.'))))"
                >{{ __('Confirm dialog') }}</button>
            </div>
        </x-admin::card>
    </div>
@endsection
