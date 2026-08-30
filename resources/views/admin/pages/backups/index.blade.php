@extends('admin::layouts.app')

@section('title', __('Backups'))

@section('content')
    <x-admin::page-header
        :title="__('Backups')"
        :subtitle="__('Full database + file backups, stored on :disks.', ['disks' => implode(', ', (array) config('backup.backup.destination.disks', ['local']))])"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Backups')],
        ]"
    >
        <x-slot:actions>
            <a href="{{ route('admin.settings.edit', ['tab' => 'system']) }}" class="nk-btn nk-btn--sm">
                {{ __('Schedule settings') }}
            </a>

            <form method="POST" action="{{ route('admin.backups.run') }}">
                @csrf
                <button type="submit" class="nk-btn nk-btn--primary">
                    <x-admin::icon name="refresh" class="nk-icon--sm" />
                    {{ __('Run backup now') }}
                </button>
            </form>
        </x-slot:actions>
    </x-admin::page-header>

    <x-admin::card>
        @if ($backups->isEmpty())
            <p class="nk-empty">{{ __('No backups yet -- click "Run backup now" to create the first one.') }}</p>
        @else
            <p class="nk-field__hint" style="margin-bottom: 1rem;">
                {{ __(':count backups, :size total.', ['count' => $backups->count(), 'size' => Number::fileSize($totalSizeBytes)]) }}
            </p>

            <div class="nk-table-scroll">
                <table class="nk-table">
                    <thead>
                        <tr>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('Disk') }}</th>
                            <th>{{ __('Size') }}</th>
                            <th>{{ __('File') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td>{{ $backup['date']->format('M j, Y g:i A') }}</td>
                                <td>{{ $backup['disk'] }}</td>
                                <td>{{ Number::fileSize($backup['sizeBytes']) }}</td>
                                <td>{{ $backup['file'] }}</td>
                                <td class="nk-table__cell--right">
                                    <a
                                        class="nk-btn nk-btn--sm"
                                        href="{{ route('admin.backups.download', ['disk' => $backup['disk'], 'file' => $backup['file']]) }}"
                                    >{{ __('Download') }}</a>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.backups.destroy', ['disk' => $backup['disk'], 'file' => $backup['file']]) }}"
                                        style="display: inline;"
                                        data-nk-confirm="{{ __('Delete this backup? This cannot be undone.') }}"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="nk-btn nk-btn--sm nk-btn--danger">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin::card>
@endsection
