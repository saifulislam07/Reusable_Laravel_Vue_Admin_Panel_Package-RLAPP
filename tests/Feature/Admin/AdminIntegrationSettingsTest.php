<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\SmtpTestMail;
use App\Models\User;
use App\Support\Admin\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): IntegrationSettings
    {
        return $this->app->make(IntegrationSettings::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Phase 8 retrofits these routes with permission gating (see
        // routes/admin.php) -- granted here so every existing test in this
        // file keeps exercising the actual feature rather than a 403.
        Permission::findOrCreate(
            'settings.integrations.manage',
            config('admin-kit.guard'),
        );

        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->givePermissionTo('settings.integrations.manage');
        $this->actingAs($user);
    }

    public function test_guests_cannot_reach_the_page()
    {
        auth()->logout();

        $this->get(route('admin.settings.integrations.edit'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_page_renders_with_all_five_tabs()
    {
        $this->get(route('admin.settings.integrations.edit'))
            ->assertOk()
            ->assertSee('nk-tabs', escape: false)
            ->assertSee('SMTP')
            ->assertSee('Google Analytics')
            ->assertSee('Search Console')
            ->assertSee('Messenger')
            ->assertSee('WhatsApp');
    }

    public function test_integrations_appears_nested_under_settings_in_the_menu()
    {
        $this->get(route('admin.dashboard'))
            ->assertSee('Settings')
            ->assertSee('Integrations')
            ->assertSee(route('admin.settings.integrations.edit'))
            ->assertSee('nk-nav__tree', escape: false);
    }

    // -- SMTP -----------------------------------------------------------

    public function test_smtp_settings_can_be_saved()
    {
        $this->post(route('admin.settings.integrations.smtp.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer',
            'password' => 'secret-pass',
            'encryption' => 'tls',
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ])->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'smtp']));

        $this->assertSame([
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer',
            'password' => 'secret-pass',
            'encryption' => 'tls',
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ], $this->settings()->get('smtp'));
    }

    public function test_smtp_requires_a_host_and_a_valid_from_address()
    {
        $this->post(route('admin.settings.integrations.smtp.update'), [
            'host' => '',
            'port' => 587,
            'from_address' => 'not-an-email',
            'from_name' => 'Example',
        ])->assertSessionHasErrors(['host', 'from_address']);
    }

    public function test_leaving_the_password_blank_on_update_keeps_the_existing_one()
    {
        $this->settings()->set('smtp', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer',
            'password' => 'original-password',
            'encryption' => 'tls',
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ]);

        $this->post(route('admin.settings.integrations.smtp.update'), [
            'host' => 'smtp.example.com',
            'port' => 2525, // changed
            'password' => '', // left blank
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ]);

        $saved = $this->settings()->get('smtp');

        $this->assertSame('original-password', $saved['password']);
        $this->assertSame(2525, $saved['port']);
    }

    public function test_providing_a_new_password_overwrites_the_old_one()
    {
        $this->settings()->set('smtp', ['password' => 'old-password']);

        $this->post(route('admin.settings.integrations.smtp.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'password' => 'brand-new-password',
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ]);

        $this->assertSame('brand-new-password', $this->settings()->get('smtp')['password']);
    }

    public function test_the_smtp_password_is_never_rendered_back_into_the_form()
    {
        $this->settings()->set('smtp', ['password' => 'do-not-leak-me']);

        $this->get(route('admin.settings.integrations.edit'))
            ->assertDontSee('do-not-leak-me');
    }

    public function test_the_test_email_button_saves_and_sends_to_the_signed_in_admin()
    {
        Mail::fake();

        $this->post(route('admin.settings.integrations.smtp.test'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'password' => 'secret',
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ])->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'smtp']));

        Mail::assertSent(SmtpTestMail::class, function (SmtpTestMail $mail): bool {
            return $mail->hasTo('admin@example.com');
        });

        // The settings posted alongside the test-email request are saved too.
        $this->assertSame('smtp.example.com', $this->settings()->get('smtp')['host']);
    }

    public function test_the_test_email_cannot_be_redirected_to_an_arbitrary_address()
    {
        Mail::fake();

        $this->post(route('admin.settings.integrations.smtp.test'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
            // Not a real field the form exposes -- proves it has no effect
            // even if someone crafts the request by hand.
            'to' => 'attacker@evil.example.com',
        ]);

        Mail::assertSent(SmtpTestMail::class, function (SmtpTestMail $mail): bool {
            return $mail->hasTo('admin@example.com')
                && ! $mail->hasTo('attacker@evil.example.com');
        });
    }

    public function test_a_send_failure_flashes_an_error_instead_of_crashing()
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->post(route('admin.settings.integrations.smtp.test'), [
            'host' => 'unreachable.example.com',
            'port' => 587,
            'from_address' => 'hello@example.com',
            'from_name' => 'Example Co',
        ]);

        $response->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'smtp']));
        $response->assertSessionHas('error');
    }

    // -- Google Analytics -------------------------------------------------

    public function test_google_analytics_settings_can_be_saved()
    {
        $this->post(route('admin.settings.integrations.google-analytics.update'), [
            'measurement_id' => 'G-ABC123XYZ',
            'enabled' => '1',
        ])->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'google-analytics']));

        $this->assertSame([
            'measurement_id' => 'G-ABC123XYZ',
            'enabled' => true,
        ], $this->settings()->get('google_analytics'));
    }

    public function test_an_unchecked_enabled_box_is_saved_as_false()
    {
        $this->settings()->set('google_analytics', ['measurement_id' => 'G-ABC123XYZ', 'enabled' => true]);

        $this->post(route('admin.settings.integrations.google-analytics.update'), [
            'measurement_id' => 'G-ABC123XYZ',
            // "enabled" omitted entirely, as an unchecked checkbox would be.
        ]);

        $this->assertFalse($this->settings()->get('google_analytics')['enabled']);
    }

    public function test_google_analytics_rejects_a_malformed_measurement_id()
    {
        $this->post(route('admin.settings.integrations.google-analytics.update'), [
            'measurement_id' => 'not-a-valid-id',
        ])->assertSessionHasErrors('measurement_id');
    }

    // -- Google Search Console --------------------------------------------

    public function test_google_search_console_settings_can_be_saved()
    {
        $this->post(route('admin.settings.integrations.google-search-console.update'), [
            'verification_meta' => 'abc123verify',
        ])->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'google-search-console']));

        $this->assertSame(
            ['verification_meta' => 'abc123verify'],
            $this->settings()->get('google_search_console'),
        );
    }

    // -- Facebook Messenger -------------------------------------------------

    public function test_facebook_messenger_settings_can_be_saved()
    {
        $this->post(route('admin.settings.integrations.facebook-messenger.update'), [
            'page_id' => '12345',
            'app_id' => '67890',
            'enabled' => '1',
        ])->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'facebook-messenger']));

        $this->assertSame([
            'page_id' => '12345',
            'app_id' => '67890',
            'enabled' => true,
        ], $this->settings()->get('facebook_messenger'));
    }

    // -- WhatsApp -----------------------------------------------------------

    public function test_whatsapp_settings_can_be_saved()
    {
        $this->post(route('admin.settings.integrations.whatsapp.update'), [
            'number' => '+15551234567',
            'message' => 'Hi! I have a question.',
            'enabled' => '1',
        ])->assertRedirect(route('admin.settings.integrations.edit', ['tab' => 'whatsapp']));

        $this->assertSame([
            'number' => '+15551234567',
            'message' => 'Hi! I have a question.',
            'enabled' => true,
        ], $this->settings()->get('whatsapp'));
    }

    public function test_whatsapp_rejects_a_number_with_letters()
    {
        $this->post(route('admin.settings.integrations.whatsapp.update'), [
            'number' => 'not-a-number',
        ])->assertSessionHasErrors('number');
    }

    // -- cross-cutting --------------------------------------------------

    public function test_guests_cannot_post_to_any_integration_update_route()
    {
        auth()->logout();

        $routes = [
            'admin.settings.integrations.smtp.update',
            'admin.settings.integrations.google-analytics.update',
            'admin.settings.integrations.google-search-console.update',
            'admin.settings.integrations.facebook-messenger.update',
            'admin.settings.integrations.whatsapp.update',
        ];

        foreach ($routes as $route) {
            $this->post(route($route), [])->assertRedirect(route('admin.login'));
        }
    }
}
