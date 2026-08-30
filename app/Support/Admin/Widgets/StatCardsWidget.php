<?php

declare(strict_types=1);

namespace App\Support\Admin\Widgets;

use App\Models\User;
use App\Support\Admin\AdminWidget;
use App\Support\Admin\MenuItem;
use App\Support\Admin\MenuManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View as ViewFacade;
use Spatie\Permission\Models\Permission;

/**
 * The panel's own headline counters -- users, roles, permissions, menu
 * items. Moved here from DashboardController (Phase 1), which only ever
 * carried them to prove the layout against live data ahead of this phase.
 */
class StatCardsWidget implements AdminWidget
{
    public function __construct(private readonly MenuManager $menu) {}

    public function key(): string
    {
        return 'stat-cards';
    }

    public function title(): string
    {
        return __('Overview');
    }

    public function size(): string
    {
        return 'full';
    }

    public function render(): View
    {
        return ViewFacade::make('admin::widgets.stat-cards', [
            'userCount' => User::query()->count(),
            // Roles/permissions are seeded in Phase 7 -- the tables may not
            // exist yet (e.g. before the first migration run).
            'roleCount' => Schema::hasTable('roles')
                ? Schema::getConnection()->table('roles')->count()
                : 0,
            'permissionCount' => Schema::hasTable('permissions')
                ? Permission::query()->count()
                : 0,
            'menuCount' => $this->countMenu($this->menu->items()),
        ]);
    }

    /**
     * @param  list<MenuItem>  $items
     */
    private function countMenu(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            $count += 1 + $this->countMenu($item->children);
        }

        return $count;
    }
}
