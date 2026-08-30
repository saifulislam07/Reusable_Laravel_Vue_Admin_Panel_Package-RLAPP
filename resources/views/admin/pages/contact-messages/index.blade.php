@extends('admin::layouts.app')

@section('title', __('Contact Messages'))

@php
    // Built here, not inline on <x-admin::datatable :headers="[...]"> -- see
    // roles/index.blade.php for why a format closure returning raw HTML with
    // double-quoted attributes breaks Blade's component-tag parser when it's
    // embedded directly in a colon-attribute expression.
    $messageHeaders = [
        [
            'key' => 'name',
            'label' => __('From'),
            'sortable' => true,
            'format' => fn ($message) => '<a href="'.e(route('admin.contact-messages.show', $message)).'">'.e($message->name).'</a><br><span style="color: var(--nk-fg-muted); font-size: 12px;">'.e($message->email).'</span>',
        ],
        ['key' => 'subject', 'label' => __('Subject')],
        [
            'key' => 'status',
            'label' => __('Status'),
            'sortable' => true,
            'format' => function ($message) {
                $variant = $message->status->badgeVariant();
                $label = $message->status->label();

                return '<span class="nk-badge nk-badge--'.$variant.'">'.e($label).'</span>';
            },
        ],
        [
            'key' => 'created_at',
            'label' => __('Received'),
            'sortable' => true,
            'format' => fn ($message) => e(optional($message->created_at)->format('M j, Y')),
        ],
        [
            'key' => 'actions',
            'label' => '',
            'align' => 'right',
            'format' => fn ($message) => '<a class="nk-btn nk-btn--sm" href="'.e(route('admin.contact-messages.show', $message)).'">'.e(__('View')).'</a>',
        ],
    ];

    $messageBulkActions = [
        [
            'key' => 'read',
            'label' => __('Mark as read'),
            'url' => route('admin.contact-messages.bulk-mark-read'),
        ],
        [
            'key' => 'archive',
            'label' => __('Archive'),
            'url' => route('admin.contact-messages.bulk-archive'),
        ],
        [
            'key' => 'delete',
            'label' => __('Delete selected'),
            'variant' => 'danger',
            'url' => route('admin.contact-messages.bulk-destroy'),
            'confirm' => [
                'title' => __('Delete the selected messages?'),
                'text' => __('This cannot be undone.'),
                'label' => __('Delete'),
            ],
        ],
    ];
@endphp

@section('content')
    <x-admin::page-header
        :title="__('Contact Messages')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('Contact Messages')],
        ]"
    />

    <x-admin::card>
        <form method="GET" class="nk-form__row" style="margin-bottom: 1rem; justify-content: flex-start;">
            <x-admin::form.select
                name="status"
                :label="__('Filter by status')"
                :options="['' => __('All statuses')] + $statusOptions"
                :value="$currentStatus"
                onchange="this.form.submit()"
            />
        </form>

        <x-admin::datatable
            :headers="$messageHeaders"
            :rows="$messages"
            :search-placeholder="__('Search name, email, or message…')"
            :bulk-actions="$messageBulkActions"
            :empty-message="__('No contact messages yet.')"
        />
    </x-admin::card>
@endsection
