{{-- <x-admin::alert variant="success">Saved.</x-admin::alert> --}}
@props(['variant' => 'info', 'icon' => null])

@php
    $icons = [
        'success' => 'shield',
        'warning' => 'bell',
        'danger' => 'bell',
        'info' => 'bell',
    ];
@endphp

<div {{ $attributes->class(['nk-alert', 'nk-alert--'.$variant]) }} role="alert">
    <x-admin::icon :name="$icon ?? ($icons[$variant] ?? 'dot')" class="nk-icon--sm" />
    <div>{{ $slot }}</div>
</div>
