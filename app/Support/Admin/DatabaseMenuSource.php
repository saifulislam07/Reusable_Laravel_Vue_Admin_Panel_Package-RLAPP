<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Admin\Menu;
use Illuminate\Support\Collection;

/**
 * The 'database' driver for MenuManager (registered in
 * AppServiceProvider::bootAdminKit()) -- converts the `menus` table into the
 * plain array shape MenuItem::fromArray() expects.
 *
 * config('admin-kit.menu.driver') still defaults to 'config': switching a
 * running panel over to this driver means the menus table has to be seeded
 * first (Phase 7's RolePermissionSeeder does this, or build the tree by hand
 * through the new Menus admin page), so it's opt-in via
 * ADMIN_MENU_DRIVER=database rather than a silent default change.
 */
class DatabaseMenuSource
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(): array
    {
        return $this->branch(
            Menu::query()->ordered()->get(),
            null,
        );
    }

    /**
     * @param  Collection<int, Menu>  $menus  Every row, loaded once up front
     *                                        so building the tree costs no
     *                                        further queries at any depth.
     * @return list<array<string, mixed>>
     */
    private function branch(Collection $menus, ?int $parentId): array
    {
        // array_values(), not the collection's own values()->all(): the
        // latter doesn't narrow to list<> for static analysis even though
        // it does produce one at runtime.
        return array_values($menus
            ->where('parent_id', $parentId)
            ->map(fn (Menu $menu): array => [
                'label' => $menu->label,
                'icon' => $menu->icon,
                'route' => $menu->route,
                'url' => $menu->url,
                'permission' => $menu->permission_name,
                'is_active' => $menu->is_active,
                'children' => $this->branch($menus, $menu->id),
            ])
            ->all());
    }
}
