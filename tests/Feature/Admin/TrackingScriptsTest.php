<?php

namespace Tests\Feature\Admin;

use App\Support\Admin\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * <x-admin::tracking-scripts /> -- rendered directly here, and once more
 * through the real public "/" route to prove it is actually wired into
 * resources/views/app.blade.php, not just a component that exists in
 * isolation.
 */
class TrackingScriptsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): IntegrationSettings
    {
        return $this->app->make(IntegrationSettings::class);
    }

    private function render(): string
    {
        return view('admin::components.tracking-scripts')->render();
    }

    public function test_it_renders_nothing_when_no_integration_is_configured()
    {
        $this->assertSame('', trim($this->render()));
    }

    public function test_google_analytics_requires_both_enabled_and_a_measurement_id()
    {
        $this->settings()->set('google_analytics', ['measurement_id' => 'G-ABC123', 'enabled' => false]);
        $this->assertStringNotContainsString('googletagmanager', $this->render());

        $this->settings()->set('google_analytics', ['measurement_id' => '', 'enabled' => true]);
        $this->assertStringNotContainsString('googletagmanager', $this->render());

        $this->settings()->set('google_analytics', ['measurement_id' => 'G-ABC123', 'enabled' => true]);
        $html = $this->render();
        $this->assertStringContainsString('googletagmanager.com/gtag/js?id=G-ABC123', $html);
        $this->assertStringContainsString("gtag('config', \"G-ABC123\")", $html);
    }

    public function test_search_console_verification_needs_no_separate_enabled_flag()
    {
        $this->settings()->set('google_search_console', ['verification_meta' => 'verify-token']);

        $this->assertStringContainsString(
            '<meta name="google-site-verification" content="verify-token">',
            $this->render(),
        );
    }

    public function test_messenger_requires_enabled_and_a_page_id()
    {
        $this->settings()->set('facebook_messenger', ['page_id' => '999', 'enabled' => false]);
        $this->assertStringNotContainsString('fb-customerchat', $this->render());

        $this->settings()->set('facebook_messenger', ['page_id' => '999', 'enabled' => true]);
        $html = $this->render();
        $this->assertStringContainsString('page_id="999"', $html);
        $this->assertStringContainsString('xfbml.customerchat.js', $html);
    }

    public function test_messenger_app_id_is_optional()
    {
        $this->settings()->set('facebook_messenger', ['page_id' => '999', 'enabled' => true]);

        $this->assertStringNotContainsString('appId:', $this->render());

        $this->settings()->set('facebook_messenger', ['page_id' => '999', 'app_id' => '111', 'enabled' => true]);

        $this->assertStringContainsString('appId: "111"', $this->render());
    }

    public function test_whatsapp_requires_enabled_and_a_number()
    {
        $this->settings()->set('whatsapp', ['number' => '15551234567', 'enabled' => false]);
        $this->assertStringNotContainsString('wa.me', $this->render());

        $this->settings()->set('whatsapp', ['number' => '', 'enabled' => true]);
        $this->assertStringNotContainsString('wa.me', $this->render());

        $this->settings()->set('whatsapp', ['number' => '15551234567', 'enabled' => true]);
        $this->assertStringContainsString('https://wa.me/15551234567', $this->render());
    }

    public function test_whatsapp_strips_formatting_characters_from_the_number()
    {
        $this->settings()->set('whatsapp', ['number' => '+1 (555) 123-4567', 'enabled' => true]);

        $this->assertStringContainsString('https://wa.me/15551234567', $this->render());
    }

    public function test_whatsapp_message_is_url_encoded()
    {
        $this->settings()->set('whatsapp', [
            'number' => '15551234567',
            'message' => 'Hi! Got a question & need help.',
            'enabled' => true,
        ]);

        $this->assertStringContainsString(
            'text='.rawurlencode('Hi! Got a question & need help.'),
            $this->render(),
        );
    }

    public function test_whatsapp_message_cannot_inject_html_into_the_page()
    {
        $this->settings()->set('whatsapp', [
            'number' => '15551234567',
            'message' => '<script>alert(1)</script>',
            'enabled' => true,
        ]);

        // It must appear only inside the URL-encoded href, never as literal,
        // executable markup.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->render());
    }

    public function test_it_is_actually_wired_into_the_public_layout()
    {
        $this->settings()->set('google_search_console', ['verification_meta' => 'end-to-end-token']);

        $this->get('/')
            ->assertOk()
            ->assertSee('end-to-end-token', escape: false);
    }

    public function test_the_public_layout_renders_nothing_extra_when_unconfigured()
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('wa.me', escape: false)
            ->assertDontSee('fb-customerchat', escape: false)
            ->assertDontSee('googletagmanager', escape: false);
    }
}
