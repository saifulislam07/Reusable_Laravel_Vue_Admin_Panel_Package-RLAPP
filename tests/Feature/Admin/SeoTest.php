<?php

namespace Tests\Feature\Admin;

use App\Enums\Admin\PageStatus;
use App\Models\Admin\MediaFolder;
use App\Models\Admin\Page;
use App\Support\Admin\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 14: the <x-admin::seo-meta /> component, GenerateSitemap, and the
 * live /robots.txt route. Per-page SEO field CRUD lives in
 * AdminPagesTest; the SEO tab in Settings lives in AdminSettingsTest.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): SettingsManager
    {
        return $this->app->make(SettingsManager::class);
    }

    protected function tearDown(): void
    {
        File::delete(public_path('sitemap.xml'));

        parent::tearDown();
    }

    // -- <x-admin::seo-meta /> -------------------------------------------

    public function test_it_falls_back_to_the_models_own_title_with_no_override()
    {
        $page = Page::factory()->create(['title' => 'About Us']);

        $html = view('admin::components.seo-meta', ['model' => $page])->render();

        $this->assertStringContainsString('<title>About Us</title>', $html);
        $this->assertStringContainsString('content="index,follow"', $html);
    }

    public function test_it_prefers_an_explicit_seo_meta_title_over_the_fallback()
    {
        $page = Page::factory()->create(['title' => 'About Us']);
        $page->syncSeoMeta(['meta_title' => 'A Totally Different Title']);

        $html = view('admin::components.seo-meta', ['model' => $page->fresh(['seoMeta'])])->render();

        $this->assertStringContainsString('<title>A Totally Different Title</title>', $html);
        $this->assertStringNotContainsString('About Us', $html);
    }

    public function test_the_fallback_title_gets_the_settings_suffix_but_an_explicit_title_does_not()
    {
        $this->settings()->saveGroup('seo', ['meta_title_suffix' => '— Acme Co']);

        $fallback = Page::factory()->create(['title' => 'About Us']);
        $explicit = Page::factory()->create(['title' => 'Contact']);
        $explicit->syncSeoMeta(['meta_title' => 'Reach Out']);

        $fallbackHtml = view('admin::components.seo-meta', ['model' => $fallback])->render();
        $explicitHtml = view('admin::components.seo-meta', ['model' => $explicit->fresh(['seoMeta'])])->render();

        $this->assertStringContainsString('<title>About Us — Acme Co</title>', $fallbackHtml);
        $this->assertStringContainsString('<title>Reach Out</title>', $explicitHtml);
    }

    public function test_description_and_keywords_only_render_when_set()
    {
        $bare = Page::factory()->create();
        $html = view('admin::components.seo-meta', ['model' => $bare])->render();
        $this->assertStringNotContainsString('name="description"', $html);
        $this->assertStringNotContainsString('name="keywords"', $html);

        $described = Page::factory()->create();
        $described->syncSeoMeta(['meta_description' => 'A description.', 'meta_keywords' => 'a, b']);
        $html = view('admin::components.seo-meta', ['model' => $described->fresh(['seoMeta'])])->render();
        $this->assertStringContainsString('name="description" content="A description."', $html);
        $this->assertStringContainsString('name="keywords" content="a, b"', $html);
    }

    public function test_og_image_falls_back_to_the_settings_default()
    {
        Storage::fake((string) config('media-library.disk_name'));
        $media = MediaFolder::default()->addMedia(UploadedFile::fake()->image('og.jpg'))->toMediaCollection();
        $this->settings()->saveGroup('seo', ['default_og_image_media_id' => $media->id], ['default_og_image_media_id' => 'integer']);

        $page = Page::factory()->create();
        $html = view('admin::components.seo-meta', ['model' => $page])->render();

        $this->assertStringContainsString('og:image', $html);
        $this->assertStringContainsString('summary_large_image', $html);
    }

    public function test_without_any_og_image_the_twitter_card_falls_back_to_summary()
    {
        $page = Page::factory()->create();

        $html = view('admin::components.seo-meta', ['model' => $page])->render();

        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringContainsString('name="twitter:card" content="summary"', $html);
    }

    // -- sitemap:generate --------------------------------------------------

    public function test_it_writes_only_published_pages_to_the_sitemap()
    {
        Page::factory()->published()->create(['slug' => 'published-page']);
        Page::factory()->create(['slug' => 'draft-page']);
        Page::factory()->scheduled()->create(['slug' => 'future-page']);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $xml = File::get(public_path('sitemap.xml'));
        $this->assertStringContainsString('published-page', $xml);
        $this->assertStringNotContainsString('draft-page', $xml);
        $this->assertStringNotContainsString('future-page', $xml);
    }

    public function test_a_scheduled_page_whose_time_has_passed_is_included()
    {
        Page::factory()->create([
            'slug' => 'now-due',
            'status' => PageStatus::Scheduled,
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $this->assertStringContainsString('now-due', File::get(public_path('sitemap.xml')));
    }

    public function test_the_sitemap_is_valid_xml_with_no_pages()
    {
        $this->artisan('sitemap:generate')->assertSuccessful();

        $xml = simplexml_load_string(File::get(public_path('sitemap.xml')));

        $this->assertNotFalse($xml);
        $this->assertCount(0, $xml->url ?? []);
    }

    public function test_the_page_url_pattern_is_config_driven()
    {
        config()->set('admin-kit.seo.page_url_pattern', '/pages/{slug}');

        Page::factory()->published()->create(['slug' => 'about']);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $this->assertStringContainsString('/pages/about', File::get(public_path('sitemap.xml')));
    }

    // -- /robots.txt --------------------------------------------------------

    public function test_robots_txt_serves_the_default_when_nothing_is_saved()
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow: /admin/', false);
    }

    public function test_robots_txt_reflects_a_saved_value_immediately()
    {
        $this->settings()->saveGroup('seo', ['robots_txt' => "User-agent: *\nDisallow: /secret/"]);

        $this->get('/robots.txt')->assertSee('Disallow: /secret/', false);
    }
}
