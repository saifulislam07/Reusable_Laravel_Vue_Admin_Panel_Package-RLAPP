<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\SettingsManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class AdminErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The branded pages only stand in for the debug screen when debug is off.
        config()->set('app.debug', false);
    }

    /**
     * Register a throwaway route inside the admin prefix.
     */
    private function adminRoute(string $path, callable $handler): string
    {
        $prefix = (string) config('admin-kit.route.prefix');

        Route::middleware('web')->get("{$prefix}/{$path}", $handler);

        return "/{$prefix}/{$path}";
    }

    public function test_an_unknown_admin_url_renders_the_branded_404()
    {
        $this->get('/admin/does-not-exist')
            ->assertNotFound()
            ->assertSee('nk-error', escape: false)
            ->assertSee('Page not found')
            ->assertSee('404');
    }

    public function test_a_403_renders_the_branded_page()
    {
        $url = $this->adminRoute('forbidden', fn () => abort(403));

        $this->get($url)
            ->assertForbidden()
            ->assertSee('Access denied')
            ->assertSee('nk-error__card', escape: false);
    }

    public function test_an_authorization_exception_renders_as_403()
    {
        $url = $this->adminRoute('denied', function (): void {
            throw new AuthorizationException;
        });

        $this->get($url)->assertForbidden()->assertSee('Access denied');
    }

    public function test_a_token_mismatch_renders_as_419()
    {
        $url = $this->adminRoute('stale', function (): void {
            throw new TokenMismatchException;
        });

        $this->get($url)
            ->assertStatus(419)
            ->assertSee('Your session expired');
    }

    public function test_a_429_renders_the_branded_page()
    {
        $url = $this->adminRoute('flooded', fn () => abort(429));

        $this->get($url)->assertStatus(429)->assertSee('Too many requests');
    }

    public function test_a_503_shows_the_configured_maintenance_message()
    {
        $this->app->make(SettingsManager::class)
            ->saveGroup('system', ['maintenance_message' => 'Back at 09:00 UTC.']);

        $url = $this->adminRoute('down', fn () => abort(503));

        $this->get($url)->assertStatus(503)->assertSee('Back at 09:00 UTC.');
    }

    public function test_a_503_falls_back_to_default_copy()
    {
        $url = $this->adminRoute('down-default', fn () => abort(503));

        $this->get($url)->assertStatus(503)->assertSee('down for maintenance', escape: false);
    }

    public function test_an_unhandled_exception_renders_the_branded_500()
    {
        $url = $this->adminRoute('boom', function (): void {
            throw new RuntimeException('kaboom');
        });

        $response = $this->get($url);

        $response->assertStatus(500)->assertSee('Something went wrong on our side');
        // The internal message must not leak onto the page.
        $response->assertDontSee('kaboom');
    }

    public function test_a_status_without_a_page_of_its_own_uses_the_fallback()
    {
        $url = $this->adminRoute('teapot', fn () => abort(418));

        $this->get($url)
            ->assertStatus(418)
            ->assertSee('nk-error', escape: false)
            ->assertSee('418');
    }

    public function test_the_debug_screen_still_wins_in_local_development()
    {
        config()->set('app.debug', true);

        $url = $this->adminRoute('boom-debug', function (): void {
            throw new RuntimeException('kaboom');
        });

        // Laravel's own debug renderer takes over, so the developer still sees
        // the real exception rather than a friendly placeholder.
        $this->get($url)
            ->assertStatus(500)
            ->assertSee('kaboom');
    }

    public function test_the_public_app_keeps_its_own_error_handling()
    {
        $this->get('/definitely-not-a-real-page')
            ->assertNotFound()
            ->assertDontSee('nk-error', escape: false);
    }

    public function test_json_clients_still_get_json()
    {
        $url = $this->adminRoute('json-404', fn () => abort(404));

        $this->getJson($url)
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_the_error_page_offers_the_dashboard_to_signed_in_users()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/does-not-exist')
            ->assertNotFound()
            ->assertSee(route('admin.dashboard'))
            ->assertSee('Back to dashboard');
    }

    public function test_the_error_page_offers_sign_in_to_guests()
    {
        $this->get('/admin/does-not-exist')
            ->assertNotFound()
            ->assertSee(route('admin.login'))
            ->assertSee('Go to sign in');
    }

    public function test_error_pages_carry_no_adminlte_fingerprint()
    {
        $html = $this->get('/admin/does-not-exist')->getContent();

        foreach (['adminlte', 'AdminLTE', 'content-wrapper'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }
}
