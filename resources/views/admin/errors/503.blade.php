@extends('admin::errors.layout')

@section('icon', 'wrench')
@section('title', __('We are down for maintenance'))

@section('message')
    {{-- Phase 15 swaps this for setting('maintenance_message'). --}}
    {{ $maintenanceMessage ?: __('We are making some improvements and will be back shortly. Thanks for your patience.') }}
@endsection
