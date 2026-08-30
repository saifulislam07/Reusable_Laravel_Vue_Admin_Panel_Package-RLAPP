@extends('admin::layouts.app')

@section('title', __('Activity detail'))

@section('content')
    <x-admin::page-header
        :title="$activity->description"
        :subtitle="optional($activity->created_at)->format('M j, Y g:i A')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Activity Log'), 'url' => route('admin.activity-log.index')],
            ['label' => __('Detail')],
        ]"
    />

    <div class="nk-grid" style="grid-template-columns: 2fr 1fr;">
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            @if ($diff !== [])
                <x-admin::card :title="__('Changes')">
                    <div class="nk-table-scroll">
                        <table class="nk-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Field') }}</th>
                                    <th>{{ __('Before') }}</th>
                                    <th>{{ __('After') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($diff as $field => $values)
                                    <tr>
                                        <td>{{ $field }}</td>
                                        <td>{{ is_scalar($values['old']) || $values['old'] === null ? $values['old'] : json_encode($values['old']) }}</td>
                                        <td>{{ is_scalar($values['new']) || $values['new'] === null ? $values['new'] : json_encode($values['new']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin::card>
            @endif

            @if ($activity->properties->isNotEmpty())
                <x-admin::card :title="__('Properties')">
                    <pre class="nk-code-block">{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </x-admin::card>
            @endif

            @if ($diff === [] && $activity->properties->isEmpty())
                <x-admin::card>
                    <p class="nk-empty">{{ __('No additional details recorded for this entry.') }}</p>
                </x-admin::card>
            @endif
        </div>

        <x-admin::card :title="__('Details')">
            <dl class="nk-detail-list">
                <dt>{{ __('Log') }}</dt>
                <dd>{{ $activity->log_name ?? __('default') }}</dd>

                <dt>{{ __('Event') }}</dt>
                <dd>{{ $activity->event ?? '—' }}</dd>

                <dt>{{ __('Causer') }}</dt>
                <dd>{{ $activity->causer?->name ?? __('System') }}</dd>

                @if ($activity->subject_type)
                    <dt>{{ __('Subject') }}</dt>
                    <dd>{{ Str::headline(class_basename($activity->subject_type)) }} #{{ $activity->subject_id }}</dd>
                @endif
            </dl>
        </x-admin::card>
    </div>
@endsection
