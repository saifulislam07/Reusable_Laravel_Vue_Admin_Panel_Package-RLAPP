@extends('admin::layouts.app')

@section('title', __('Message from :name', ['name' => $message->name]))

@section('content')
    <x-admin::page-header
        :title="$message->subject ?: __('(No subject)')"
        :subtitle="__(':name — :email', ['name' => $message->name, 'email' => $message->email])"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Contact Messages'), 'url' => route('admin.contact-messages.index')],
            ['label' => $message->name],
        ]"
    >
        <x-slot:actions>
            <span class="nk-badge nk-badge--{{ $message->status->badgeVariant() }}">{{ $message->status->label() }}</span>
        </x-slot:actions>
    </x-admin::page-header>

    <div class="nk-grid" style="grid-template-columns: 2fr 1fr;">
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <x-admin::card :title="__('Message')">
                <p style="white-space: pre-line;">{{ $message->message }}</p>
            </x-admin::card>

            @if ($message->status === \App\Enums\Admin\ContactMessageStatus::Replied)
                <x-admin::card :title="__('Your reply')">
                    <p style="white-space: pre-line;">{{ $message->reply_message }}</p>
                    <p class="nk-field__hint" style="margin-top: 0.75rem;">
                        {{ __('Sent :date', ['date' => optional($message->replied_at)->format('M j, Y g:i A')]) }}
                    </p>
                </x-admin::card>
            @else
                <x-admin::card :title="__('Reply')">
                    <form method="POST" action="{{ route('admin.contact-messages.reply', $message) }}" class="nk-form">
                        @csrf

                        <x-admin::form.textarea
                            name="reply"
                            :label="__('Your reply')"
                            :value="old('reply')"
                            :hint="__('Sent to :email as an email.', ['email' => $message->email])"
                            rows="6"
                            required
                        />

                        <div class="nk-form__row">
                            <button type="submit" class="nk-btn nk-btn--primary">{{ __('Send reply') }}</button>
                        </div>
                    </form>
                </x-admin::card>
            @endif
        </div>

        <x-admin::card :title="__('Details')">
            <dl class="nk-detail-list">
                <dt>{{ __('Name') }}</dt>
                <dd>{{ $message->name }}</dd>

                <dt>{{ __('Email') }}</dt>
                <dd><a href="mailto:{{ $message->email }}">{{ $message->email }}</a></dd>

                @if ($message->phone)
                    <dt>{{ __('Phone') }}</dt>
                    <dd>{{ $message->phone }}</dd>
                @endif

                <dt>{{ __('IP address') }}</dt>
                <dd>{{ $message->ip ?: __('Unknown') }}</dd>

                <dt>{{ __('Received') }}</dt>
                <dd>{{ optional($message->created_at)->format('M j, Y g:i A') }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" data-nk-confirm="{{ __('Delete this message?') }}" style="margin-top: 1rem;">
                @csrf
                @method('DELETE')
                <button type="submit" class="nk-btn nk-btn--danger">{{ __('Delete message') }}</button>
            </form>
        </x-admin::card>
    </div>
@endsection
