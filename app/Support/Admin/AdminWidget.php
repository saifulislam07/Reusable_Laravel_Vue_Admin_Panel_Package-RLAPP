<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Contracts\View\View;

/**
 * A self-contained dashboard tile: a widget owns its own data fetching and
 * its own markup (typically wrapped in `<x-admin::card>` internally), so
 * WidgetManager only has to instantiate and render it.
 *
 * Register implementations in config('admin-kit.widgets') -- each consuming
 * project adds its own by listing its FQCN there, exactly like a menu item
 * or a database menu source.
 */
interface AdminWidget
{
    /**
     * Stable identifier used for the drag-to-reorder layout stored in the
     * browser (see initWidgetGrid() in admin.js) -- must not change once a
     * widget has shipped, or a saved order silently drops it.
     */
    public function key(): string;

    public function title(): string;

    /**
     * Grid sizing hint consumed by the dashboard view: 'sm', 'md', 'lg', or
     * 'full' (spans the whole row).
     */
    public function size(): string;

    public function render(): View;
}
