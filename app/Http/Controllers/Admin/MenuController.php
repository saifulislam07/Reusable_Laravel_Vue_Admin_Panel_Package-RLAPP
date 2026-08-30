<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\MenuRequest;
use App\Models\Admin\Menu;
use App\Support\Admin\MenuManager;
use App\Support\Admin\MenuRouteOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

/**
 * CRUD + drag-drop ordering for the `menus` table -- the 'database' driver
 * behind App\Support\Admin\MenuManager (see DatabaseMenuSource). Everything
 * here except reorder() is a plain full-page form, matching the panel's
 * AJAX-free convention; reorder() is the one deliberate exception, since a
 * full reload after every drag would defeat the point of drag-and-drop.
 */
class MenuController extends AdminController
{
    public function index(MenuManager $menuManager): View
    {
        $menus = Menu::query()->ordered()->get();

        return view('admin::pages.menus.index', [
            'tree' => $this->tree($menus, null),
            'isEmpty' => $menus->isEmpty(),
        ]);
    }

    public function create(): View
    {
        return view('admin::pages.menus.create', $this->formData());
    }

    public function store(MenuRequest $request): RedirectResponse
    {
        Menu::query()->create($request->forSave() + [
            'order' => $this->nextOrder($request->validated('parent_id')),
        ]);

        return $this->redirectWithSuccess('admin.menus.index', __('Menu item created.'));
    }

    public function edit(Menu $menu): View
    {
        return view('admin::pages.menus.edit', ['menu' => $menu] + $this->formData($menu));
    }

    public function update(MenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->forSave());

        return $this->redirectWithSuccess('admin.menus.index', __('Menu item updated.'));
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return $this->redirectWithSuccess('admin.menus.index', __('Menu item deleted.'));
    }

    /**
     * Persists a drag-drop reorder: the whole tree, flattened, each row
     * carrying its new parent and position among its siblings.
     */
    public function reorder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('menus', 'id')],
            'items.*.parent_id' => ['nullable', 'integer', Rule::exists('menus', 'id')],
            'items.*.order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($payload['items'] as $item) {
            if ((int) $item['id'] === (int) ($item['parent_id'] ?? 0)) {
                return response()->json(['message' => __('A menu item cannot be its own parent.')], 422);
            }
        }

        DB::transaction(function () use ($payload): void {
            foreach ($payload['items'] as $item) {
                Menu::query()->whereKey($item['id'])->update([
                    'parent_id' => $item['parent_id'],
                    'order' => $item['order'],
                ]);
            }
        });

        return response()->json(['message' => __('Order saved.')]);
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return list<array{menu: Menu, children: array<int, mixed>}>
     */
    private function tree(Collection $menus, ?int $parentId): array
    {
        // array_values(), not the collection's own values()->all(): the
        // latter doesn't narrow to list<> for static analysis even though
        // it does produce one at runtime.
        return array_values($menus
            ->where('parent_id', $parentId)
            ->map(fn (Menu $menu): array => [
                'menu' => $menu,
                'children' => $this->tree($menus, $menu->id),
            ])
            ->all());
    }

    private function nextOrder(?int $parentId): int
    {
        return 1 + (int) Menu::query()->where('parent_id', $parentId)->max('order');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Menu $editing = null): array
    {
        $menus = Menu::query()->ordered()->get();

        // A menu can't become a descendant of itself, so it -- and anything
        // already under it -- is excluded from its own parent picker.
        $excluded = $editing
            ? [$editing->id, ...$this->descendantIds($menus, $editing->id)]
            : [];

        return [
            'parentOptions' => $menus->reject(fn (Menu $menu): bool => in_array($menu->id, $excluded, true)),
            'routeOptions' => MenuRouteOptions::all(),
            'permissionOptions' => Permission::query()->orderBy('name')->pluck('name', 'name'),
        ];
    }

    /**
     * @param  Collection<int, Menu>  $menus  Every row, loaded once so this
     *                                        costs no extra queries at any
     *                                        depth.
     * @return list<int>
     */
    private function descendantIds(Collection $menus, int $parentId): array
    {
        $childIds = $menus->where('parent_id', $parentId)->pluck('id')->all();

        return array_values(array_merge(
            $childIds,
            ...array_map(fn (int $id): array => $this->descendantIds($menus, $id), $childIds),
        ));
    }
}
