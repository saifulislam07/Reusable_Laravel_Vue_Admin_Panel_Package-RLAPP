@extends('admin::errors.layout')

@section('icon', 'clock')
@section('title', __('Too many requests'))
@section('message', __('You have made too many requests in a short time. Wait a moment and try again.'))
