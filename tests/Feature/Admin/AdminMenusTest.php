<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminMenusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('menus.manage', config('admin-kit.guard'));
    }

    private function actingAsManager(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('menus.manage');
        $this->actingAs($user);

        return $user;
    }

    public function test_guests_cannot_reach_any_menus_route()
    {
        $this->get(route('admin.menus.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_menus_manage_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.menus.index'))->assertForbidden();
    }

    public function test_the_index_page_renders_the_tree()
    {
        $this->actingAsManager();

        $settings = Menu::factory()->create(['label' => 'Settings', 'order' => 0]);
        Menu::factory()->create(['label' => 'Integrations', 'parent_id' => $settings->id, 'order' => 0]);

        $this->get(route('admin.menus.index'))
            ->assertOk()
            ->assertSee('nk-menu-tree', escape: false)
            ->assertSee('Settings')
            ->assertSee('Integrations');
    }

    public function test_it_shows_an_empty_state_with_no_menus()
    {
        $this->actingAsManager();

        $this->get(route('admin.menus.index'))->assertSee('No menu items yet.');
    }

    public function test_a_menu_item_can_be_created_with_a_route()
    {
        $this->actingAsManager();

        $this->post(route('admin.menus.store'), [
            'label' => 'Dashboard link',
            'route' => 'admin.dashboard',
        ])->assertRedirect(route('admin.menus.index'));

        $this->assertDatabaseHas('menus', [
            'label' => 'Dashboard link',
            'route' => 'admin.dashboard',
            'url' => null,
        ]);
    }

    public function test_a_menu_item_can_be_created_with_a_raw_url()
    {
        $this->actingAsManager();

        $this->post(route('admin.menus.store'), [
            'label' => 'External',
            'url' => 'https://example.com',
        ])->assertRedirect(route('admin.menus.index'));

        $this->assertDatabaseHas('menus', ['label' => 'External', 'url' => 'https://example.com', 'route' => null]);
    }

    public function test_exactly_one_of_route_or_url_is_required()
    {
        $this->actingAsManager();

        $this->post(route('admin.menus.store'), ['label' => 'Neither'])
            ->assertSessionHasErrors(['route', 'url']);

        $this->post(route('admin.menus.store'), [
            'label' => 'Both',
            'route' => 'admin.dashboard',
            'url' => 'https://example.com',
        ])->assertSessionHasErrors('route');
    }

    public function test_a_route_must_be_a_real_admin_route_name()
    {
        $this->actingAsManager();

        $this->post(route('admin.menus.store'), [
            'label' => 'Fake',
            'route' => 'admin.does-not-exist',
        ])->assertSessionHasErrors('route');
    }

    public function test_new_items_are_appended_after_existing_siblings()
    {
        $this->actingAsManager();

        Menu::factory()->create(['order' => 0]);
        Menu::factory()->create(['order' => 3]);

        $this->post(route('admin.menus.store'), ['label' => 'New', 'url' => '/new']);

        $this->assertDatabaseHas('menus', ['label' => 'New', 'order' => 4]);
    }

    public function test_the_edit_form_prefills_the_current_values()
    {
        $this->actingAsManager();

        $menu = Menu::factory()->create(['label' => 'Existing', 'url' => '/existing']);

        $this->get(route('admin.menus.edit', $menu))
            ->assertOk()
            ->assertSee('value="Existing"', escape: false)
            ->assertSee('value="/existing"', escape: false);
    }

    public function test_a_menu_item_can_be_updated()
    {
        $this->actingAsManager();

        $menu = Menu::factory()->create(['label' => 'Old label', 'url' => '/old']);

        $this->put(route('admin.menus.update', $menu), [
            'label' => 'New label',
            'url' => '/new',
        ])->assertRedirect(route('admin.menus.index'));

        $this->assertSame('New label', $menu->fresh()->label);
        $this->assertSame('/new', $menu->fresh()->url);
    }

    public function test_the_parent_picker_excludes_the_item_itself_and_its_descendants()
    {
        $this->actingAsManager();

        $grandparent = Menu::factory()->create(['label' => 'Grandparent Item']);
        Menu::factory()->create(['label' => 'Descendant Parent', 'parent_id' => $grandparent->id]);
        $parent = Menu::query()->where('label', 'Descendant Parent')->firstOrFail();
        Menu::factory()->create(['label' => 'Descendant Child', 'parent_id' => $parent->id]);
        Menu::factory()->create(['label' => 'Unrelated Item']);

        // Its own descendants must not appear as parent options -- picking
        // one would make Grandparent a descendant of its own child.
        $this->get(route('admin.menus.edit', $grandparent))
            ->assertOk()
            ->assertDontSee('Descendant Parent')
            ->assertDontSee('Descendant Child')
            ->assertSee('Unrelated Item');
    }

    public function test_a_menu_item_cannot_be_parented_to_itself()
    {
        $this->actingAsManager();

        $menu = Menu::factory()->create();

        $this->put(route('admin.menus.update', $menu), [
            'label' => $menu->label,
            'url' => $menu->url,
            'parent_id' => $menu->id,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_deleting_a_menu_item_cascades_to_its_children()
    {
        $this->actingAsManager();

        $parent = Menu::factory()->create();
        $child = Menu::factory()->create(['parent_id' => $parent->id]);

        $this->delete(route('admin.menus.destroy', $parent))
            ->assertRedirect(route('admin.menus.index'));

        $this->assertDatabaseMissing('menus', ['id' => $parent->id]);
        $this->assertDatabaseMissing('menus', ['id' => $child->id]);
    }

    public function test_reorder_persists_new_parent_and_order_for_every_item()
    {
        $this->actingAsManager();

        $a = Menu::factory()->create(['order' => 0]);
        $b = Menu::factory()->create(['order' => 1]);

        $this->postJson(route('admin.menus.reorder'), [
            'items' => [
                ['id' => $b->id, 'parent_id' => null, 'order' => 0],
                ['id' => $a->id, 'parent_id' => $b->id, 'order' => 0],
            ],
        ])->assertOk();

        $this->assertNull($b->fresh()->parent_id);
        $this->assertSame($b->id, $a->fresh()->parent_id);
    }

    public function test_reorder_rejects_an_item_parented_to_itself()
    {
        $this->actingAsManager();

        $menu = Menu::factory()->create();

        $this->postJson(route('admin.menus.reorder'), [
            'items' => [['id' => $menu->id, 'parent_id' => $menu->id, 'order' => 0]],
        ])->assertStatus(422);

        $this->assertNull($menu->fresh()->parent_id);
    }

    public function test_reorder_rejects_an_id_that_does_not_exist()
    {
        $this->actingAsManager();

        $this->postJson(route('admin.menus.reorder'), [
            'items' => [['id' => 999999, 'parent_id' => null, 'order' => 0]],
        ])->assertStatus(422);
    }

    public function test_the_permission_picker_lists_existing_permissions()
    {
        $this->actingAsManager();
        Permission::findOrCreate('reports.export', config('admin-kit.guard'));

        $this->get(route('admin.menus.create'))->assertSee('reports.export');
    }

    public function test_a_menu_item_can_require_a_permission_to_be_visible()
    {
        $this->actingAsManager();
        Permission::findOrCreate('reports.export', config('admin-kit.guard'));

        $this->post(route('admin.menus.store'), [
            'label' => 'Reports',
            'url' => '/reports',
            'permission_name' => 'reports.export',
        ]);

        $this->assertDatabaseHas('menus', ['label' => 'Reports', 'permission_name' => 'reports.export']);
    }
}
