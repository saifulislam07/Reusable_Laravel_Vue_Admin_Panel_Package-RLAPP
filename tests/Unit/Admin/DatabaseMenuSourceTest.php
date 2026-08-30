<?php

namespace Tests\Unit\Admin;

use App\Models\Admin\Menu;
use App\Support\Admin\DatabaseMenuSource;
use App\Support\Admin\MenuManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseMenuSourceTest extends TestCase
{
    use RefreshDatabase;

    private function source(): DatabaseMenuSource
    {
        return $this->app->make(DatabaseMenuSource::class);
    }

    public function test_it_returns_an_empty_list_with_no_menus()
    {
        $this->assertSame([], ($this->source())());
    }

    public function test_it_maps_columns_to_the_shape_menuitem_expects()
    {
        Menu::query()->create([
            'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard',
            'permission_name' => 'dashboard.view', 'is_active' => true, 'order' => 0,
        ]);

        $items = ($this->source())();

        $this->assertSame([
            'label' => 'Dashboard',
            'icon' => 'dashboard',
            'route' => 'admin.dashboard',
            'url' => null,
            'permission' => 'dashboard.view',
            'is_active' => true,
            'children' => [],
        ], $items[0]);
    }

    public function test_it_builds_a_nested_tree()
    {
        $settings = Menu::query()->create(['label' => 'Settings', 'order' => 0]);
        Menu::query()->create(['label' => 'Integrations', 'parent_id' => $settings->id, 'order' => 0, 'url' => '/a']);
        Menu::query()->create(['label' => 'Menus', 'parent_id' => $settings->id, 'order' => 1, 'url' => '/b']);

        $items = ($this->source())();

        $this->assertCount(1, $items);
        $this->assertSame('Settings', $items[0]['label']);
        $this->assertCount(2, $items[0]['children']);
        $this->assertSame('Integrations', $items[0]['children'][0]['label']);
        $this->assertSame('Menus', $items[0]['children'][1]['label']);
    }

    public function test_it_supports_more_than_two_levels()
    {
        $a = Menu::query()->create(['label' => 'A', 'order' => 0, 'url' => '/a']);
        $b = Menu::query()->create(['label' => 'B', 'parent_id' => $a->id, 'order' => 0, 'url' => '/b']);
        Menu::query()->create(['label' => 'C', 'parent_id' => $b->id, 'order' => 0, 'url' => '/c']);

        $items = ($this->source())();

        $this->assertSame('C', $items[0]['children'][0]['children'][0]['label']);
    }

    public function test_siblings_are_ordered_by_the_order_column()
    {
        Menu::query()->create(['label' => 'Second', 'order' => 1, 'url' => '/2']);
        Menu::query()->create(['label' => 'First', 'order' => 0, 'url' => '/1']);

        $items = ($this->source())();

        $this->assertSame(['First', 'Second'], array_column($items, 'label'));
    }

    public function test_inactive_items_are_still_passed_through_unfiltered()
    {
        // Visibility filtering is MenuManager's job (is_active, permission) --
        // the source's only responsibility is an accurate translation of the
        // table, so MenuManager has the real data to filter on.
        Menu::query()->create(['label' => 'Hidden', 'is_active' => false, 'order' => 0, 'url' => '/x']);

        $items = ($this->source())();

        $this->assertCount(1, $items);
        $this->assertFalse($items[0]['is_active']);
    }

    public function test_it_is_usable_as_the_menu_managers_database_driver()
    {
        Menu::query()->create(['label' => 'From DB', 'order' => 0, 'url' => '/db']);

        config()->set('admin-kit.menu.driver', 'database');

        /** @var MenuManager $manager */
        $manager = $this->app->make(MenuManager::class);

        $items = $manager->items();

        $this->assertCount(1, $items);
        $this->assertSame('From DB', $items[0]->label);
    }
}
