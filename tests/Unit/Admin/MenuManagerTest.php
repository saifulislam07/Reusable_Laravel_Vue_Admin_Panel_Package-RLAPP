<?php

namespace Tests\Unit\Admin;

use App\Support\Admin\MenuManager;
use Tests\TestCase;

class MenuManagerTest extends TestCase
{
    private function manager(array $items): MenuManager
    {
        config()->set('admin-kit.menu.driver', 'config');
        config()->set('admin-kit.menu.items', $items);

        return $this->app->make(MenuManager::class);
    }

    public function test_it_builds_a_tree_from_config()
    {
        $items = $this->manager([
            [
                'label' => 'Content',
                'icon' => 'file-text',
                'children' => [
                    ['label' => 'Pages', 'url' => '/admin/pages'],
                ],
            ],
        ])->items();

        $this->assertCount(1, $items);
        $this->assertSame('Content', $items[0]->label);
        $this->assertTrue($items[0]->hasChildren());
        $this->assertSame('Pages', $items[0]->children[0]->label);
    }

    public function test_it_hides_items_the_guest_user_lacks_permission_for()
    {
        $items = $this->manager([
            ['label' => 'Public', 'url' => '/a'],
            ['label' => 'Restricted', 'url' => '/b', 'permission' => 'manage users'],
        ])->items();

        $this->assertCount(1, $items);
        $this->assertSame('Public', $items[0]->label);
    }

    public function test_it_drops_a_parent_once_every_child_is_filtered_away()
    {
        $items = $this->manager([
            [
                'label' => 'System',
                'children' => [
                    ['label' => 'Roles', 'url' => '/roles', 'permission' => 'manage roles'],
                ],
            ],
        ])->items();

        $this->assertSame([], $items);
    }

    public function test_it_respects_the_is_active_flag()
    {
        $items = $this->manager([
            ['label' => 'Hidden', 'url' => '/a', 'is_active' => false],
            ['label' => 'Shown', 'url' => '/b', 'is_active' => true],
        ])->items();

        $this->assertCount(1, $items);
        $this->assertSame('Shown', $items[0]->label);
    }

    public function test_a_registered_driver_replaces_the_config_source()
    {
        config()->set('admin-kit.menu.driver', 'database');

        $manager = $this->app->make(MenuManager::class);
        $manager->extend('database', fn (): array => [
            ['label' => 'From DB', 'url' => '/db'],
        ]);

        $items = $manager->items();

        $this->assertCount(1, $items);
        $this->assertSame('From DB', $items[0]->label);
    }

    public function test_it_resolves_urls_from_route_names()
    {
        $items = $this->manager([
            ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
        ])->items();

        $this->assertSame(route('admin.dashboard'), $items[0]->url);
    }
}
