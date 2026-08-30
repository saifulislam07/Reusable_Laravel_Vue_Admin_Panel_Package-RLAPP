@extends('admin::errors.layout')

@section('icon', 'lock')
@section('title', __('Access denied'))
@section('message', __('You do not have permission to view this page. Ask an administrator if you think this is a mistake.'))
