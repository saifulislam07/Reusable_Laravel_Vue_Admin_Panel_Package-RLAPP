/*
 * NG Admin Kit -- panel runtime.
 *
 * Alpine drives the shell (sidebar rail, mobile drawer, colour scheme) plus
 * the toast queue and confirm dialog. The global `NK` namespace is the single
 * public JS surface: NK.toast(), NK.confirm(), and the shell setters.
 *
 * Shell state lives as data-* attributes on <html>, not on an Alpine-bound
 * element, so the inline boot script in the layout head can restore it before
 * first paint and nothing flashes.
 */

import Alpine from 'alpinejs';
import {
    CategoryScale,
    Chart,
    Filler,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import Quill from 'quill';
import Sortable from 'sortablejs';
import 'quill/dist/quill.snow.css';

// Only the pieces a line chart needs -- a future widget wanting a bar or
// pie chart registers its own controller/element the same way rather than
// pulling in the full chart.js/auto bundle for everyone.
Chart.register(
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Filler,
    Tooltip,
);

const STORAGE_KEY = {
    sidebar: 'nk.sidebar',
    scheme: 'nk.scheme',
    widgetOrder: 'nk.widgetOrder',
};

const root = document.documentElement;

/**
 * localStorage throws in private-mode and sandboxed frames, so every read and
 * write is guarded and falls back to the server-rendered default.
 */
function readStored(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStored(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        /* Preference simply will not persist -- not worth failing over. */
    }
}

const NK = {
    /**
     * 'expanded' | 'collapsed'.
     */
    setSidebar(state) {
        root.setAttribute('data-sidebar', state);
        writeStored(STORAGE_KEY.sidebar, state);
        Alpine.store('nk').sidebar = state;
    },

    /**
     * 'light' | 'dark' | 'system'. 'system' clears the attribute and lets
     * prefers-color-scheme decide, matching the CSS in admin.css.
     */
    setScheme(scheme) {
        if (scheme === 'system') {
            root.removeAttribute('data-nk-scheme');
        } else {
            root.setAttribute('data-nk-scheme', scheme);
        }

        writeStored(STORAGE_KEY.scheme, scheme);
        Alpine.store('nk').scheme = scheme;
    },

    setMobileNav(open) {
        root.setAttribute('data-mobile-nav', open ? 'open' : 'closed');
        Alpine.store('nk').mobileNav = open;
    },

    /**
     * Raise a toast.
     *
     *   NK.toast('success', 'Saved successfully')
     *   NK.toast('danger', 'Could not save', { duration: 0 })  // sticky
     *
     * Returns the toast id so callers can dismiss it early.
     */
    toast(type, message, options = {}) {
        return Alpine.store('nkToast').push(type, message, options);
    },

    dismissToast(id) {
        Alpine.store('nkToast').dismiss(id);
    },

    /**
     * Ask for confirmation. Resolves true/false, and also honours the
     * onConfirm/onCancel callbacks for callers that prefer them.
     *
     *   NK.confirm({ title: 'Delete 3 users?', text: '…' })
     *       .then((ok) => ok && form.submit());
     */
    confirm(options = {}) {
        return Alpine.store('nkConfirm').ask(options);
    },

    /**
     * Open the media library picker. Resolves to the chosen item
     * ({id, name, file_name, thumbnail, url, is_image}) or null if the
     * dialog was cancelled.
     *
     *   NK.pickMedia().then((item) => item && console.log(item.url))
     */
    pickMedia() {
        return Alpine.store('nkMediaPicker').ask();
    },
};

const TOAST_DEFAULT_DURATION = 5000;

/**
 * Defaults come from the layout, which renders the config values; the inline
 * boot script has already reconciled them against localStorage by now.
 */
Alpine.store('nk', {
    sidebar: root.getAttribute('data-sidebar') ?? 'expanded',
    scheme:
        root.getAttribute('data-nk-scheme') ??
        readStored(STORAGE_KEY.scheme) ??
        'system',
    mobileNav: false,

    toggleSidebar() {
        NK.setSidebar(this.sidebar === 'collapsed' ? 'expanded' : 'collapsed');
    },

    toggleMobileNav() {
        NK.setMobileNav(!this.mobileNav);
    },

    closeMobileNav() {
        NK.setMobileNav(false);
    },

    cycleScheme() {
        const order = ['light', 'dark', 'system'];
        NK.setScheme(order[(order.indexOf(this.scheme) + 1) % order.length]);
    },
});

/**
 * Toast queue. The markup lives in <x-admin::toast-container />; this only
 * owns the list and the dismiss timers.
 */
Alpine.store('nkToast', {
    items: [],
    lastId: 0,
    timers: new Map(),

    push(type, message, options = {}) {
        if (!message) {
            return null;
        }

        const id = ++this.lastId;
        const duration = options.duration ?? TOAST_DEFAULT_DURATION;

        this.items.push({
            id,
            type: type || 'info',
            title: options.title ?? null,
            message: String(message),
            duration,
            // Errors interrupt; everything else waits its turn politely.
            assertive: type === 'danger' || type === 'error',
        });

        this.startTimer(id, duration);

        return id;
    },

    dismiss(id) {
        this.clearTimer(id);
        this.items = this.items.filter((toast) => toast.id !== id);
    },

    /**
     * duration 0 (or less) means the toast sticks until dismissed.
     */
    startTimer(id, duration) {
        if (!duration || duration <= 0) {
            return;
        }

        this.clearTimer(id);
        this.timers.set(
            id,
            window.setTimeout(() => this.dismiss(id), duration),
        );
    },

    clearTimer(id) {
        const handle = this.timers.get(id);

        if (handle) {
            window.clearTimeout(handle);
            this.timers.delete(id);
        }
    },

    // Hovering or focusing a toast should not race the reader to the dismiss.
    pause(id) {
        this.clearTimer(id);
    },

    resume(id) {
        const toast = this.items.find((item) => item.id === id);

        if (toast) {
            this.startTimer(id, toast.duration);
        }
    },
});

/**
 * Confirmation dialog. One dialog element is reused for every question, so
 * there is never more than one open at a time.
 */
Alpine.store('nkConfirm', {
    open: false,
    title: '',
    text: '',
    confirmLabel: '',
    cancelLabel: '',
    variant: 'primary',

    resolver: null,
    onConfirm: null,
    onCancel: null,
    previousFocus: null,

    // Seeded by <x-admin::toast-container /> so the wording is translatable
    // rather than hard-coded English in the bundle.
    defaults: {
        title: 'Are you sure?',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
    },

    ask(options = {}) {
        // A second question while one is open cancels the first rather than
        // silently dropping its promise.
        if (this.open) {
            this.settle(false);
        }

        this.title = options.title ?? this.defaults.title;
        this.text = options.text ?? '';
        this.confirmLabel = options.confirmLabel ?? this.defaults.confirmLabel;
        this.cancelLabel = options.cancelLabel ?? this.defaults.cancelLabel;
        this.variant = options.variant ?? 'primary';
        this.onConfirm = options.onConfirm ?? null;
        this.onCancel = options.onCancel ?? null;

        this.previousFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;

        this.open = true;
        root.setAttribute('data-nk-dialog', 'open');

        return new Promise((resolve) => {
            this.resolver = resolve;
        });
    },

    accept() {
        this.settle(true);
    },

    cancel() {
        this.settle(false);
    },

    settle(confirmed) {
        if (!this.open) {
            return;
        }

        const { resolver, onConfirm, onCancel, previousFocus } = this;

        this.open = false;
        this.resolver = null;
        this.onConfirm = null;
        this.onCancel = null;
        this.previousFocus = null;
        root.removeAttribute('data-nk-dialog');

        // Hand focus back to whatever opened the dialog.
        previousFocus?.focus?.();

        if (confirmed) {
            onConfirm?.();
        } else {
            onCancel?.();
        }

        resolver?.(confirmed);
    },
});

/**
 * Media library picker. One dialog, reused everywhere -- same shape as
 * nkConfirm above. The endpoint URL comes from <x-admin::media-picker />'s
 * own data attribute rather than being hard-coded here, so this file has
 * no admin-kit.route.prefix knowledge baked in.
 */
Alpine.store('nkMediaPicker', {
    open: false,
    search: '',
    items: [],
    loading: false,
    nextPageUrl: null,
    resolver: null,

    endpoint() {
        return document.querySelector('[data-media-picker-endpoint]')?.dataset
            .mediaPickerEndpoint;
    },

    ask() {
        if (this.open) {
            this.select(null);
        }

        this.open = true;
        this.search = '';
        this.reload();

        return new Promise((resolve) => {
            this.resolver = resolve;
        });
    },

    reload() {
        this.items = [];
        this.nextPageUrl = null;
        this.fetchPage(this.buildUrl());
    },

    loadMore() {
        if (this.nextPageUrl) {
            this.fetchPage(this.nextPageUrl);
        }
    },

    buildUrl() {
        const url = new URL(this.endpoint(), window.location.origin);

        if (this.search) {
            url.searchParams.set('q', this.search);
        }

        return url.toString();
    },

    async fetchPage(url) {
        if (!url) {
            return;
        }

        this.loading = true;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();

            this.items = [...this.items, ...json.data];
            this.nextPageUrl = json.next_page_url;
        } finally {
            this.loading = false;
        }
    },

    select(item) {
        this.open = false;

        const { resolver } = this;
        this.resolver = null;
        resolver?.(item);
    },

    cancel() {
        this.select(null);
    },
});

/**
 * Declarative confirmation: put data-nk-confirm on a form, link, or submit
 * button and it asks before proceeding. Attributes map to NK.confirm()
 * options.
 *
 *   <form method="POST" data-nk-confirm data-nk-confirm-title="Delete user?">
 *
 * A button variant exists for forms with several submit buttons that each
 * need their own confirmation (e.g. a datatable's bulk-action bar, where
 * every button targets a different formaction on one shared form):
 *
 *   <button type="submit" formaction="/bulk-delete" data-nk-confirm>…</button>
 */
function confirmOptionsFrom(element) {
    const data = element.dataset;

    return {
        title: data.nkConfirmTitle || data.nkConfirm || undefined,
        text: data.nkConfirmText || undefined,
        confirmLabel: data.nkConfirmLabel || undefined,
        cancelLabel: data.nkConfirmCancel || undefined,
        variant: data.nkConfirmVariant || 'danger',
    };
}

document.addEventListener('submit', (event) => {
    // A confirmed button (below) re-submits the form itself; without this
    // guard the form-level handler here would intercept that second submit
    // and ask again.
    if (event.submitter?.dataset.nkConfirm !== undefined) {
        return;
    }

    const form = event.target.closest('form[data-nk-confirm]');

    if (!form || form.dataset.nkConfirmed === '1') {
        return;
    }

    event.preventDefault();

    // Keep the submitter so the re-submission below carries its name/value
    // (and formaction, for a button that overrides the form's own action).
    const submitter = event.submitter;

    NK.confirm(confirmOptionsFrom(form)).then((confirmed) => {
        if (!confirmed) {
            return;
        }

        form.dataset.nkConfirmed = '1';

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter ?? undefined);
        } else {
            form.submit();
        }
    });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-nk-confirm]');

    if (link) {
        event.preventDefault();

        NK.confirm(confirmOptionsFrom(link)).then((confirmed) => {
            if (confirmed) {
                window.location.href = link.href;
            }
        });

        return;
    }

    // A submit button that carries its own data-nk-confirm -- typically one
    // of several buttons on a shared form, each posting to a different
    // formaction (a datatable's bulk-action bar). Confirming re-submits with
    // this exact button as the submitter, so its formaction/formmethod win.
    const button = event.target.closest('button[data-nk-confirm]');

    if (!button || !button.form || button.dataset.nkConfirmed === '1') {
        return;
    }

    event.preventDefault();

    NK.confirm(confirmOptionsFrom(button)).then((confirmed) => {
        if (!confirmed) {
            return;
        }

        button.dataset.nkConfirmed = '1';
        button.form.requestSubmit(button);
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    // The dialog is modal, so it wins the Escape key over the drawer.
    if (Alpine.store('nkConfirm').open) {
        Alpine.store('nkConfirm').cancel();

        return;
    }

    if (Alpine.store('nk').mobileNav) {
        NK.setMobileNav(false);
    }
});

/**
 * Menu drag-drop reordering (admin::pages.menus.index only -- a no-op
 * everywhere else, since it bails out when the tree root isn't on the page).
 *
 * Markup contract: a root element carrying [data-menu-tree], holding nested
 * <ul>s where each <li data-id="…"> may itself contain one child <ul>.
 * Dragging is restricted to a [data-menu-drag] handle inside each <li> so
 * clicking "Edit"/"Delete" never starts a drag.
 */
function initMenuTree() {
    const root = document.querySelector('[data-menu-tree]');

    if (!root) {
        return;
    }

    const lists = [root, ...root.querySelectorAll('ul')];

    const save = () => {
        const items = [];

        const walk = (listEl, parentId) => {
            [...listEl.children].forEach((li, index) => {
                items.push({
                    id: Number(li.dataset.id),
                    parent_id: parentId,
                    order: index,
                });

                const childList = li.querySelector(':scope > ul');

                if (childList) {
                    walk(childList, Number(li.dataset.id));
                }
            });
        };

        walk(root, null);

        fetch(root.dataset.menuTree, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')
                        ?.content ?? '',
            },
            body: JSON.stringify({ items }),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Save failed');
                }

                NK.toast(
                    'success',
                    root.dataset.menuTreeSavedMessage || 'Order saved.',
                );
            })
            .catch(() => {
                NK.toast(
                    'danger',
                    root.dataset.menuTreeErrorMessage ||
                        'Could not save the new order.',
                );
            });
    };

    lists.forEach((list) => {
        Sortable.create(list, {
            group: 'menu-tree',
            handle: '[data-menu-drag]',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: save,
        });
    });
}

/**
 * Dashboard widgets (Phase 10, admin::pages.dashboard only).
 *
 * initCharts() turns every `<canvas data-chart='{...}'>` into a Chart.js
 * chart -- the JSON is just `{type, labels, datasets}`, so any future
 * widget gets a chart for free by rendering a canvas with that attribute,
 * no JS changes needed here. Colour comes from the *current* --nk-primary
 * theme token, read at init time, so a re-brand needs no changes here
 * either.
 *
 * initWidgetGrid() makes [data-widget-grid] drag-reorderable via the same
 * handle-based Sortable pattern as the menu tree, and remembers the order
 * in localStorage per browser -- there is no per-user, server-persisted
 * layout (deferred, see plan.md Phase 10).
 */
function initCharts() {
    const primary = getComputedStyle(document.documentElement)
        .getPropertyValue('--nk-primary')
        .trim();

    document.querySelectorAll('[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartReady) {
            return;
        }

        canvas.dataset.chartReady = '1';

        const { type, labels, datasets } = JSON.parse(canvas.dataset.chart);

        new Chart(canvas, {
            type,
            data: {
                labels,
                datasets: datasets.map((dataset) => ({
                    borderColor: primary,
                    backgroundColor: primary,
                    pointBackgroundColor: primary,
                    tension: 0.35,
                    fill: false,
                    ...dataset,
                })),
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: datasets.length > 1 } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    });
}

function initWidgetGrid() {
    const grid = document.querySelector('[data-widget-grid]');

    if (!grid) {
        return;
    }

    const widgets = [...grid.querySelectorAll(':scope > [data-widget-key]')];
    const storedOrder = (readStored(STORAGE_KEY.widgetOrder) || '').split(',');

    if (storedOrder.length > 1) {
        storedOrder
            .map((key) => widgets.find((el) => el.dataset.widgetKey === key))
            .filter(Boolean)
            .forEach((el) => grid.appendChild(el));
    }

    Sortable.create(grid, {
        handle: '[data-widget-drag]',
        animation: 150,
        onEnd: () => {
            const order = [
                ...grid.querySelectorAll(':scope > [data-widget-key]'),
            ].map((el) => el.dataset.widgetKey);

            writeStored(STORAGE_KEY.widgetOrder, order.join(','));
        },
    });
}

/**
 * CMS rich-text field (Phase 11, admin::pages.pages.partials.form).
 *
 * Markup contract: [data-editor] wraps a mount point plus the plain
 * <textarea data-editor-target> that actually submits with the form -- so
 * with JS disabled this degrades to an ordinary textarea, not a blank
 * field. The toolbar's image button opens the media library picker
 * (Phase 12) rather than Quill's own default, which would embed a
 * base64 copy of the file straight into the content.
 */
function initEditors() {
    document.querySelectorAll('[data-editor]').forEach((wrapper) => {
        const target = wrapper.querySelector('[data-editor-target]');
        const mount = wrapper.querySelector('[data-editor-content]');

        if (!target || !mount || wrapper.dataset.editorReady) {
            return;
        }

        wrapper.dataset.editorReady = '1';

        const quill = new Quill(mount, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline', 'link', 'image'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'clean'],
                    ],
                    handlers: {
                        image() {
                            NK.pickMedia().then((item) => {
                                if (!item) {
                                    return;
                                }

                                const range = quill.getSelection(true);
                                quill.insertEmbed(
                                    range.index,
                                    'image',
                                    item.url,
                                    'user',
                                );
                                quill.setSelection(range.index + 1);
                            });
                        },
                    },
                },
            },
        });

        if (target.value) {
            quill.clipboard.dangerouslyPasteHTML(target.value);
        }

        target.hidden = true;

        const sync = () => {
            target.value = quill.getSemanticHTML();
        };

        quill.on('text-change', sync);
        wrapper.closest('form')?.addEventListener('submit', sync);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initMenuTree();
    initCharts();
    initWidgetGrid();
    initEditors();
});

window.NK = NK;
window.Alpine = Alpine;

Alpine.start();
