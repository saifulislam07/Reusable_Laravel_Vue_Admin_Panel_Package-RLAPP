<?php

namespace Tests\Unit\Admin;

use App\Models\Admin\IntegrationSetting;
use App\Providers\AppServiceProvider;
use App\Support\Admin\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): IntegrationSettings
    {
        return $this->app->make(IntegrationSettings::class);
    }

    public function test_an_unconfigured_group_returns_an_empty_array()
    {
        $this->assertSame([], $this->settings()->get('nothing_here'));
    }

    public function test_set_then_get_round_trips_the_data()
    {
        $this->settings()->set('smtp', ['host' => 'smtp.example.com', 'port' => 587]);

        $this->assertSame(
            ['host' => 'smtp.example.com', 'port' => 587],
            $this->settings()->get('smtp'),
        );
    }

    public function test_set_is_idempotent_per_group()
    {
        $this->settings()->set('smtp', ['host' => 'a']);
        $this->settings()->set('smtp', ['host' => 'b']);

        $this->assertSame(['host' => 'b'], $this->settings()->get('smtp'));
        $this->assertSame(1, IntegrationSetting::query()->where('group', 'smtp')->count());
    }

    public function test_reads_are_cached_until_the_next_write()
    {
        $this->settings()->set('smtp', ['host' => 'cached-value']);
        $this->settings()->get('smtp'); // warms the cache

        // Bypass the service entirely -- a direct model write must not be
        // visible until the cache is invalidated by set(). Going through a
        // loaded model instance (not a raw query-builder update) so the
        // encrypted:array cast still applies -- a bare
        // ->where(...)->update() skips Eloquent casting and would write an
        // unencrypted, unreadable value.
        IntegrationSetting::query()->where('group', 'smtp')->first()->update(['data' => ['host' => 'direct-write']]);

        $this->assertSame(['host' => 'cached-value'], $this->settings()->get('smtp'));

        $this->settings()->set('smtp', ['host' => 'new-value']);

        $this->assertSame(['host' => 'new-value'], $this->settings()->get('smtp'));
    }

    public function test_it_tolerates_the_table_not_existing_yet()
    {
        Schema::drop('integration_settings');

        $this->assertSame([], $this->settings()->get('smtp'));

        // Recreate it so RefreshDatabase can tear the schema down normally.
        Schema::create('integration_settings', function ($table): void {
            $table->id();
            $table->string('group')->unique();
            $table->text('data')->nullable();
            $table->timestamps();
        });
    }

    public function test_enabled_defaults_to_false()
    {
        $this->assertFalse($this->settings()->enabled('whatsapp'));

        $this->settings()->set('whatsapp', ['enabled' => true]);

        $this->assertTrue($this->settings()->enabled('whatsapp'));
    }

    public function test_smtp_configured_reflects_whether_a_host_is_set()
    {
        $this->assertFalse($this->settings()->smtpConfigured());

        $this->settings()->set('smtp', ['host' => 'smtp.example.com']);

        $this->assertTrue($this->settings()->smtpConfigured());
    }

    public function test_the_data_column_is_encrypted_at_rest()
    {
        $this->settings()->set('smtp', ['password' => 'super-secret-password']);

        $rawColumn = (string) DB::table('integration_settings')->where('group', 'smtp')->value('data');

        $this->assertStringNotContainsString('super-secret-password', $rawColumn);

        // But the model still decrypts it transparently.
        $this->assertSame('super-secret-password', $this->settings()->get('smtp')['password']);
    }

    public function test_apply_runtime_mail_config_overrides_the_smtp_mailer()
    {
        $this->settings()->set('smtp', [
            'host' => 'db.smtp.example.com',
            'port' => 2525,
            'username' => 'db-user',
            'password' => 'db-pass',
            'encryption' => 'ssl',
            'from_address' => 'from@example.com',
            'from_name' => 'DB Configured',
        ]);

        $this->settings()->applyRuntimeMailConfig();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('db.smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('db-user', config('mail.mailers.smtp.username'));
        $this->assertSame('db-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('from@example.com', config('mail.from.address'));
        $this->assertSame('DB Configured', config('mail.from.name'));
    }

    public function test_apply_runtime_mail_config_is_a_noop_without_a_saved_host()
    {
        $originalHost = config('mail.mailers.smtp.host');

        $this->settings()->applyRuntimeMailConfig();

        $this->assertSame($originalHost, config('mail.mailers.smtp.host'));
    }

    public function test_the_service_provider_wires_the_override_into_boot()
    {
        // The test suite runs on an in-memory SQLite connection, so a full
        // application reboot (the natural way to prove "this runs on every
        // request") would also discard the database along with it. Instead,
        // re-run the same provider's boot() against the still-live
        // container: it reads whatever IntegrationSettings::get('smtp')
        // returns right now, exactly as it would on a fresh request.
        //
        // Written through the service (not a direct model write) so the
        // cache this test's own setUp() already warmed with "nothing
        // configured" is correctly invalidated -- a real admin's save always
        // goes through the service too.
        $this->settings()->set('smtp', ['host' => 'boot-time.example.com', 'port' => 587]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertSame('boot-time.example.com', config('mail.mailers.smtp.host'));
    }
}
