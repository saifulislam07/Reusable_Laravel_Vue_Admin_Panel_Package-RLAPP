<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_admin_panel()
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
    }

    public function test_authenticated_users_see_the_admin_shell()
    {
        $this->actingAs(User::factory()->create(['name' => 'Ada Lovelace']));

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('nk-sidebar', escape: false);
        $response->assertSee('nk-topbar', escape: false);
        $response->assertSee('Ada Lovelace');
    }

    public function test_the_topbar_shows_initials_without_an_avatar_and_the_real_image_once_one_is_set()
    {
        $this->actingAs($user = User::factory()->create(['name' => 'Ada Lovelace']));

        $this->get(route('admin.dashboard'))
            ->assertSee('<span class="nk-avatar">AL</span>', escape: false);

        Storage::fake((string) config('media-library.disk_name'));
        $media = MediaFolder::default()->addMedia(UploadedFile::fake()->image('ada.jpg'))->toMediaCollection();
        $user->update(['avatar_media_id' => $media->id]);

        // actingAs() sets the resolved user on the guard once; it doesn't
        // re-fetch from the DB on every subsequent request within the same
        // test the way a real browser's next request would, so re-assert
        // with the refreshed model to pick up the avatar just set above.
        $this->actingAs($user->fresh());

        $this->get(route('admin.dashboard'))
            ->assertSee('<span class="nk-avatar"><img src="', escape: false)
            ->assertDontSee('<span class="nk-avatar">AL</span>', escape: false);
    }

    public function test_the_current_menu_item_is_marked_active()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))
            ->assertSee('class="nk-nav__link is-active"', escape: false)
            ->assertSee('aria-current="page"', escape: false);
    }

    public function test_nested_menu_items_render_as_a_collapsible_tree()
    {
        config()->set('admin-kit.menu.items', [
            [
                'label' => 'Content',
                'icon' => 'file-text',
                'children' => [
                    ['label' => 'Pages', 'url' => '/admin/pages', 'badge' => '3'],
                ],
            ],
        ]);

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))
            ->assertSee('nk-nav__tree', escape: false)
            ->assertSee('Content')
            ->assertSee('Pages')
            ->assertSee('nk-badge nk-badge--primary', escape: false);
    }

    public function test_menu_items_the_user_lacks_permission_for_are_hidden()
    {
        config()->set('admin-kit.menu.items', [
            ['label' => 'Visible item', 'url' => '/a'],
            ['label' => 'Secret item', 'url' => '/b', 'permission' => 'manage users'],
        ]);

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))
            ->assertSee('Visible item')
            ->assertDontSee('Secret item');
    }

    public function test_the_route_prefix_comes_from_config()
    {
        $this->assertSame(
            url(config('admin-kit.route.prefix')),
            route('admin.dashboard'),
        );
    }

    public function test_theme_tokens_are_rendered_as_css_variables()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.dashboard'))
            ->assertSee('--nk-primary:'.config('admin-kit.theme.tokens.primary'), escape: false)
            ->assertSee('--nk-sidebar-bg:', escape: false);
    }

    public function test_the_panel_carries_no_adminlte_fingerprint()
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('admin.dashboard'))->getContent();

        foreach (['adminlte', 'AdminLTE', 'main-sidebar', 'content-wrapper'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }
}
