@extends('admin::layouts.app')

@section('title', __('Activity Log'))

@php
    $activityHeaders = [
        [
            'key' => 'created_at',
            'label' => __('When'),
            'sortable' => true,
            'format' => fn ($activity) => '<a href="'.e(route('admin.activity-log.show', $activity)).'">'.e(optional($activity->created_at)->format('M j, Y g:i A')).'</a>',
        ],
        [
            'key' => 'description',
            'label' => __('Activity'),
            'format' => fn ($activity) => e($activity->description),
        ],
        [
            'key' => 'causer',
            'label' => __('By'),
            'format' => fn ($activity) => $activity->causer ? e($activity->causer->name) : '<span style="color: var(--nk-fg-muted);">'.e(__('System')).'</span>',
        ],
        [
            'key' => 'subject_type',
            'label' => __('Subject'),
            'format' => fn ($activity) => $activity->subject_type ? e(Str::headline(class_basename($activity->subject_type))) : '—',
        ],
    ];
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Activity Log')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Activity Log')],
        ]"
    />

    <x-admin::card>
        <form method="GET" class="nk-form__row" style="margin-bottom: 1rem; justify-content: flex-start; gap: 1rem; flex-wrap: wrap;">
            <x-admin::form.select
                name="causer_id"
                :label="__('User')"
                :options="['' => __('Everyone')] + $causerOptions"
                :value="$filters['causer_id'] ?? ''"
                onchange="this.form.submit()"
            />

            <x-admin::form.select
                name="subject_type"
                :label="__('Subject type')"
                :options="['' => __('All types')] + $subjectTypeOptions"
                :value="$filters['subject_type'] ?? ''"
                onchange="this.form.submit()"
            />

            <x-admin::form.input type="date" name="from" :label="__('From')" :value="$filters['from'] ?? ''" onchange="this.form.submit()" />
            <x-admin::form.input type="date" name="to" :label="__('To')" :value="$filters['to'] ?? ''" onchange="this.form.submit()" />

            @if (array_filter($filters))
                <a class="nk-btn nk-btn--sm nk-btn--ghost" style="align-self: flex-end;" href="{{ route('admin.activity-log.index') }}">{{ __('Clear filters') }}</a>
            @endif
        </form>

        <x-admin::datatable
            :headers="$activityHeaders"
            :rows="$activities"
            :search-placeholder="__('Search description…')"
            :empty-message="__('No activity recorded yet.')"
        />
    </x-admin::card>
@endsection
