@extends('admin::errors.layout')

@section('icon', 'refresh')
@section('title', __('Your session expired'))
@section('message', __('For your security the page timed out. Reload and try again — nothing was saved.'))

@section('actions')
    <a class="nk-btn nk-btn--primary" href="{{ url()->current() }}">
        <x-admin::icon name="refresh" class="nk-icon--sm" />
        {{ __('Reload the page') }}
    </a>
@endsection
