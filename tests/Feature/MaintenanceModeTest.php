<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Admin\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App\Http\Middleware\CheckMaintenanceMode (Phase 15): gates the public
 * site behind Settings > System's maintenance toggle. The admin route
 * saving that setting is covered by AdminSettingsTest; this covers the
 * middleware actually enforcing it.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): SettingsManager
    {
        return $this->app->make(SettingsManager::class);
    }

    private function enableMaintenance(array $overrides = []): void
    {
        $this->settings()->saveGroup('system', array_merge([
            'maintenance_mode' => true,
        ], $overrides), ['maintenance_mode' => 'boolean', 'maintenance_allowed_ips' => 'json']);
    }

    public function test_the_public_site_works_normally_when_maintenance_mode_is_off()
    {
        $this->get('/')->assertOk();
    }

    public function test_the_public_site_returns_503_during_maintenance()
    {
        $this->enableMaintenance();

        $this->get('/')->assertStatus(503)->assertSee('nk-error', escape: false);
    }

    public function test_the_maintenance_message_is_shown()
    {
        $this->enableMaintenance(['maintenance_message' => 'Back at 09:00 UTC.']);

        $this->get('/')->assertSee('Back at 09:00 UTC.');
    }

    public function test_a_default_message_shows_when_none_is_saved()
    {
        $this->enableMaintenance();

        $this->get('/')->assertSee('down for maintenance', escape: false);
    }

    public function test_the_admin_panel_is_always_exempt()
    {
        $this->enableMaintenance();

        $this->get(route('admin.login'))->assertOk();
    }

    public function test_an_authenticated_admin_can_still_reach_the_dashboard_during_maintenance()
    {
        $this->actingAs(User::factory()->create());
        $this->enableMaintenance();

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_an_allow_listed_ip_bypasses_maintenance_mode()
    {
        $this->enableMaintenance(['maintenance_allowed_ips' => ['127.0.0.1']]);

        $this->get('/')->assertOk();
    }

    public function test_an_ip_not_on_the_allow_list_is_still_blocked()
    {
        $this->enableMaintenance(['maintenance_allowed_ips' => ['203.0.113.5']]);

        $this->get('/')->assertStatus(503);
    }

    public function test_robots_txt_is_blocked_too_since_its_not_under_the_admin_prefix()
    {
        $this->enableMaintenance();

        $this->get('/robots.txt')->assertStatus(503);
    }
}
