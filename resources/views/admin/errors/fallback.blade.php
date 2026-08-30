{{-- Any status without a page of its own. --}}
@extends('admin::errors.layout')

@section('icon', 'alert-triangle')
@section('title', __('Something went wrong'))
@section('message', __('The request could not be completed. Please try again.'))
