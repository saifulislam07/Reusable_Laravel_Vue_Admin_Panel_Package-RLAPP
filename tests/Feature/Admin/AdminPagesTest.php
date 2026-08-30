<?php

namespace Tests\Feature\Admin;

use App\Enums\Admin\PageStatus;
use App\Models\Admin\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The Pages (CMS) module: listing (DataTable), full CRUD, slug
 * auto-generation/uniqueness, and Draft/Published/Scheduled status
 * (Phase 11). Every route is permission-gated (pages.view/create/
 * update/delete), same shape as Users (Phase 8).
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages.view', 'pages.create', 'pages.update', 'pages.delete'] as $name) {
            Permission::findOrCreate($name, config('admin-kit.guard'));
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsManager(array $permissions = ['pages.view', 'pages.create', 'pages.update', 'pages.delete']): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $this->actingAs($user);

        return $user;
    }

    // -- access control ---------------------------------------------------

    public function test_guests_cannot_reach_the_pages_module()
    {
        $this->get(route('admin.pages.index'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_pages_view_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.pages.index'))->assertForbidden();
    }

    public function test_pages_view_alone_does_not_grant_create_access()
    {
        $this->actingAsManager(['pages.view']);

        $this->get(route('admin.pages.create'))->assertForbidden();
    }

    // -- listing ------------------------------------------------------------

    public function test_the_index_lists_pages()
    {
        $this->actingAsManager();

        Page::factory()->create(['title' => 'About Us']);

        $this->get(route('admin.pages.index'))
            ->assertOk()
            ->assertSee('About Us');
    }

    // -- create ---------------------------------------------------------------

    public function test_a_page_can_be_created_with_an_auto_generated_slug()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Contact Us',
            'content' => '<p>Reach out any time.</p>',
            'template' => 'default',
            'status' => 'draft',
        ])->assertRedirect(route('admin.pages.index'));

        $page = Page::query()->where('title', 'Contact Us')->firstOrFail();

        $this->assertSame('contact-us', $page->slug);
        $this->assertSame('<p>Reach out any time.</p>', $page->content);
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertNull($page->published_at);
    }

    public function test_a_colliding_auto_generated_slug_is_disambiguated()
    {
        $this->actingAsManager();

        Page::factory()->create(['title' => 'Contact Us', 'slug' => 'contact-us']);

        $this->post(route('admin.pages.store'), [
            'title' => 'Contact Us',
            'template' => 'default',
            'status' => 'draft',
        ])->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseHas('pages', ['title' => 'Contact Us', 'slug' => 'contact-us-2']);
    }

    public function test_an_explicitly_typed_colliding_slug_is_rejected_not_silently_renamed()
    {
        $this->actingAsManager();

        Page::factory()->create(['slug' => 'taken-slug']);

        $this->post(route('admin.pages.store'), [
            'title' => 'Something Else',
            'slug' => 'taken-slug',
            'template' => 'default',
            'status' => 'draft',
        ])->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('pages', ['title' => 'Something Else']);
    }

    public function test_the_slug_must_be_a_valid_slug_format()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Bad Slug',
            'slug' => 'not a valid slug!!',
            'template' => 'default',
            'status' => 'draft',
        ])->assertSessionHasErrors('slug');
    }

    public function test_the_template_must_be_one_of_the_configured_keys()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Bad Template',
            'template' => 'not-a-real-template',
            'status' => 'draft',
        ])->assertSessionHasErrors('template');
    }

    // -- status / published_at ------------------------------------------------

    public function test_publishing_without_a_date_defaults_to_now()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Published Page',
            'template' => 'default',
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $page = Page::query()->where('title', 'Published Page')->firstOrFail();

        $this->assertSame(PageStatus::Published, $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertTrue($page->published_at->diffInSeconds(now()) < 5);
    }

    public function test_scheduling_requires_a_future_publish_date()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Scheduled Page',
            'template' => 'default',
            'status' => 'scheduled',
        ])->assertSessionHasErrors('published_at');

        $this->post(route('admin.pages.store'), [
            'title' => 'Scheduled Page',
            'template' => 'default',
            'status' => 'scheduled',
            'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('published_at');
    }

    public function test_a_page_can_be_scheduled_for_a_future_date()
    {
        $this->actingAsManager();

        $future = now()->addWeek()->startOfMinute();

        $this->post(route('admin.pages.store'), [
            'title' => 'Scheduled Page',
            'template' => 'default',
            'status' => 'scheduled',
            'published_at' => $future->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $page = Page::query()->where('title', 'Scheduled Page')->firstOrFail();

        $this->assertSame(PageStatus::Scheduled, $page->status);
        $this->assertTrue($page->published_at->equalTo($future));
    }

    // -- update ---------------------------------------------------------------

    public function test_a_page_can_be_updated()
    {
        $this->actingAsManager();

        $page = Page::factory()->create(['title' => 'Old Title', 'slug' => 'old-title']);

        $this->put(route('admin.pages.update', $page), [
            'title' => 'New Title',
            'slug' => 'old-title',
            'content' => '<p>Updated.</p>',
            'template' => 'default',
            'status' => 'draft',
        ])->assertRedirect(route('admin.pages.index'));

        $page->refresh();
        $this->assertSame('New Title', $page->title);
        $this->assertSame('<p>Updated.</p>', $page->content);
    }

    public function test_updating_a_page_keeping_its_own_slug_is_not_a_collision()
    {
        $this->actingAsManager();

        $page = Page::factory()->create(['slug' => 'same-slug']);

        $this->put(route('admin.pages.update', $page), [
            'title' => $page->title,
            'slug' => 'same-slug',
            'template' => 'default',
            'status' => 'draft',
        ])->assertSessionHasNoErrors();
    }

    public function test_updating_a_page_to_another_pages_slug_is_rejected()
    {
        $this->actingAsManager();

        Page::factory()->create(['slug' => 'first-page']);
        $second = Page::factory()->create(['slug' => 'second-page']);

        $this->put(route('admin.pages.update', $second), [
            'title' => $second->title,
            'slug' => 'first-page',
            'template' => 'default',
            'status' => 'draft',
        ])->assertSessionHasErrors('slug');
    }

    // -- SEO (Phase 14) -----------------------------------------------------

    public function test_seo_overrides_can_be_saved_alongside_the_page()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'About Us',
            'template' => 'default',
            'status' => 'draft',
            'seo' => [
                'meta_title' => 'About Us — Custom Title',
                'meta_description' => 'A short description.',
                'meta_keywords' => 'about, company',
                'canonical_url' => 'https://example.com/about',
                'robots' => 'noindex,follow',
            ],
        ])->assertSessionHasNoErrors();

        $page = Page::query()->where('title', 'About Us')->firstOrFail();

        $this->assertNotNull($page->seoMeta);
        $this->assertSame('About Us — Custom Title', $page->seoMeta->meta_title);
        $this->assertSame('noindex,follow', $page->seoMeta->robots);
    }

    public function test_a_page_can_be_created_without_any_seo_overrides()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'No SEO Here',
            'template' => 'default',
            'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $page = Page::query()->where('title', 'No SEO Here')->firstOrFail();

        $this->assertNull($page->seoMeta);
    }

    public function test_a_fully_blank_seo_submission_removes_an_existing_override()
    {
        $this->actingAsManager();

        $page = Page::factory()->create();
        $page->syncSeoMeta(['meta_title' => 'Old Title']);
        $this->assertNotNull($page->fresh()->seoMeta);

        $this->put(route('admin.pages.update', $page), [
            'title' => $page->title,
            'slug' => $page->slug,
            'template' => 'default',
            'status' => 'draft',
            'seo' => ['meta_title' => '', 'meta_description' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertNull($page->fresh()->seoMeta);
    }

    public function test_seo_field_lengths_are_validated()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Too Long',
            'template' => 'default',
            'status' => 'draft',
            'seo' => ['meta_title' => str_repeat('a', 61)],
        ])->assertSessionHasErrors('seo.meta_title');
    }

    public function test_seo_robots_must_be_one_of_the_known_combinations()
    {
        $this->actingAsManager();

        $this->post(route('admin.pages.store'), [
            'title' => 'Bad Robots',
            'template' => 'default',
            'status' => 'draft',
            'seo' => ['robots' => 'not-a-real-value'],
        ])->assertSessionHasErrors('seo.robots');
    }

    // -- delete -----------------------------------------------------------

    public function test_a_page_can_be_deleted()
    {
        $this->actingAsManager();

        $page = Page::factory()->create();

        $this->delete(route('admin.pages.destroy', $page))->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_pages_can_be_bulk_deleted()
    {
        $this->actingAsManager();

        $pages = Page::factory()->count(3)->create();

        $this->post(route('admin.pages.bulk-destroy'), [
            'ids' => $pages->pluck('id')->all(),
        ])->assertRedirect(route('admin.pages.index'));

        $this->assertSame(0, Page::query()->count());
    }
}
