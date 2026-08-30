@extends('admin::layouts.app')

@section('title', __('Notifications'))

@section('content')
    <x-admin::page-header
        :title="__('Notifications')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Notifications')],
        ]"
    >
        @if ($notifications->contains(fn ($notification) => $notification->read_at === null))
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="nk-btn nk-btn--sm">{{ __('Mark all as read') }}</button>
                </form>
            </x-slot:actions>
        @endif
    </x-admin::page-header>

    <x-admin::card>
        @forelse ($notifications as $notification)
            <div class="nk-notif-row @if (! $notification->read_at) is-unread @endif">
                <span class="nk-notif-row__icon">
                    <x-admin::icon :name="$notification->data['icon'] ?? 'bell'" />
                </span>

                <div class="nk-notif-row__body">
                    <strong>{{ $notification->data['title'] ?? __('Notification') }}</strong>
                    <p>{{ $notification->data['body'] ?? '' }}</p>
                    <span class="nk-notif-row__time">{{ $notification->created_at?->diffForHumans() }}</span>
                </div>

                <div class="nk-notif-row__actions">
                    @if (! empty($notification->data['action_url']))
                        <a class="nk-btn nk-btn--sm" href="{{ $notification->data['action_url'] }}">{{ $notification->data['action_label'] ?? __('View') }}</a>
                    @endif

                    @if ($notification->read_at)
                        <form method="POST" action="{{ route('admin.notifications.unread', $notification->id) }}">
                            @csrf
                            <button type="submit" class="nk-btn nk-btn--sm nk-btn--ghost">{{ __('Mark as unread') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="nk-btn nk-btn--sm nk-btn--ghost">{{ __('Mark as read') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="nk-empty">{{ __('No notifications yet.') }}</p>
        @endforelse
    </x-admin::card>

    {{ $notifications->onEachSide(1)->links('admin::components.pagination') }}
@endsection
