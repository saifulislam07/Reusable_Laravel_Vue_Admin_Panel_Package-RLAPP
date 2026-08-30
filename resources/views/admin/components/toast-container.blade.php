{{--
    <x-admin::toast-container />

    Renders the toast region and the single reusable confirm dialog. Sits once
    in the layout; everything else talks to it through NK.toast() / NK.confirm().

    Server-side flash messages are handed over as JSON rather than inline JS so
    the payload is data, never executable, no matter what a message contains.
--}}
@props(['validationMessage' => null])

@php
    $nkFlash = [];

    // The classic single-value keys any controller may set.
    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info', 'status' => 'info'] as $key => $type) {
        if ($message = session($key)) {
            $nkFlash[] = ['type' => $type, 'message' => (string) $message];
        }
    }

    // Multiple toasts in one response, pushed via AdminController::flashToast().
    foreach ((array) session('nk_toasts', []) as $toast) {
        if (! empty($toast['message'])) {
            $nkFlash[] = [
                'type' => (string) ($toast['type'] ?? 'info'),
                'message' => (string) $toast['message'],
            ];
        }
    }

    // One summary toast for a failed form, rather than one per field.
    $nkErrors = $errors ?? null;
    if ($nkErrors && $nkErrors->any() && $validationMessage !== false) {
        $nkFlash[] = [
            'type' => 'danger',
            'message' => $validationMessage ?: trans_choice(
                '{1}There is a problem with one field.|[2,*]There are problems with :count fields.',
                $nkErrors->count(),
                ['count' => $nkErrors->count()],
            ),
        ];
    }

    $nkConfirmLabels = [
        'title' => __('Are you sure?'),
        'confirmLabel' => __('Confirm'),
        'cancelLabel' => __('Cancel'),
    ];
@endphp

<div
    class="nk-toasts"
    role="region"
    aria-label="{{ __('Notifications') }}"
    x-data
    x-init="JSON.parse($refs.flash.textContent).forEach((t) => NK.toast(t.type, t.message))"
>
    <script type="application/json" x-ref="flash">@json($nkFlash)</script>

    <template x-for="toast in $store.nkToast.items" :key="toast.id">
        <div
            class="nk-toast"
            :class="'nk-toast--' + toast.type"
            :role="toast.assertive ? 'alert' : 'status'"
            :aria-live="toast.assertive ? 'assertive' : 'polite'"
            @mouseenter="$store.nkToast.pause(toast.id)"
            @mouseleave="$store.nkToast.resume(toast.id)"
            @focusin="$store.nkToast.pause(toast.id)"
            @focusout="$store.nkToast.resume(toast.id)"
        >
            <span class="nk-toast__icon" aria-hidden="true">
                <template x-if="toast.type === 'success'">
                    <x-admin::icon name="shield" class="nk-icon--sm" />
                </template>
                <template x-if="toast.type === 'danger' || toast.type === 'error'">
                    <x-admin::icon name="alert-triangle" class="nk-icon--sm" />
                </template>
                <template x-if="toast.type === 'warning'">
                    <x-admin::icon name="alert-triangle" class="nk-icon--sm" />
                </template>
                <template x-if="toast.type === 'info'">
                    <x-admin::icon name="bell" class="nk-icon--sm" />
                </template>
            </span>

            <div class="nk-toast__body">
                <strong class="nk-toast__title" x-show="toast.title" x-text="toast.title"></strong>
                <span x-text="toast.message"></span>
            </div>

            <button
                type="button"
                class="nk-toast__close"
                @click="$store.nkToast.dismiss(toast.id)"
                :aria-label="'{{ __('Dismiss') }}'"
            >&times;</button>
        </div>
    </template>
</div>

{{-- One dialog, reused for every question. --}}
<div
    class="nk-modal"
    x-data
    x-init="Object.assign($store.nkConfirm.defaults, JSON.parse($refs.labels.textContent))"
    x-show="$store.nkConfirm.open"
    x-cloak
    @keydown.tab.prevent="$refs.confirmBtn === document.activeElement ? $refs.cancelBtn.focus() : $refs.confirmBtn.focus()"
>
    <script type="application/json" x-ref="labels">@json($nkConfirmLabels)</script>

    <div class="nk-modal__backdrop" @click="$store.nkConfirm.cancel()"></div>

    <div
        class="nk-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="nk-confirm-title"
        aria-describedby="nk-confirm-text"
        x-effect="if ($store.nkConfirm.open) $nextTick(() => $refs.confirmBtn.focus())"
    >
        <h2 class="nk-modal__title" id="nk-confirm-title" x-text="$store.nkConfirm.title"></h2>

        <p class="nk-modal__text" id="nk-confirm-text" x-show="$store.nkConfirm.text" x-text="$store.nkConfirm.text"></p>

        <div class="nk-modal__actions">
            <button
                type="button"
                class="nk-btn"
                x-ref="cancelBtn"
                @click="$store.nkConfirm.cancel()"
                x-text="$store.nkConfirm.cancelLabel"
            ></button>

            <button
                type="button"
                class="nk-btn"
                :class="'nk-btn--' + $store.nkConfirm.variant"
                x-ref="confirmBtn"
                @click="$store.nkConfirm.accept()"
                x-text="$store.nkConfirm.confirmLabel"
            ></button>
        </div>
    </div>
</div>
