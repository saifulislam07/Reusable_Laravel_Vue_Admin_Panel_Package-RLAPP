<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\MediaFolder;
use App\Models\User;
use App\Support\Admin\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The centralized Settings system (Phase 13) and its System tab (Phase 15):
 * General/Contact/Social/SEO/System tabs, the settings() cache, the global
 * setting() helper, and the runtime overrides that point
 * config('admin-kit.brand.*') and config('app.timezone') at whatever's
 * saved -- see SettingsManager::applyRuntimeBrandConfig()/
 * applyRuntimeTimezone(). Maintenance-mode *enforcement* (the middleware
 * that actually blocks the public site) is covered by MaintenanceModeTest.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): SettingsManager
    {
        return $this->app->make(SettingsManager::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('settings.manage', config('admin-kit.guard'));
        Storage::fake((string) config('media-library.disk_name'));

        $user = User::factory()->create();
        $user->givePermissionTo('settings.manage');
        $this->actingAs($user);
    }

    // -- access control ---------------------------------------------------

    public function test_guests_cannot_reach_the_page()
    {
        auth()->logout();

        $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_without_settings_manage_is_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_the_page_renders_with_all_five_tabs()
    {
        $this->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('nk-tabs', escape: false)
            ->assertSee('General')
            ->assertSee('Contact')
            ->assertSee('Social')
            ->assertSee('SEO')
            ->assertSee('System');
    }

    public function test_general_appears_nested_under_settings_in_the_menu()
    {
        $this->get(route('admin.dashboard'))
            ->assertSee('Settings')
            ->assertSee(route('admin.settings.edit'))
            ->assertSee('nk-nav__tree', escape: false);
    }

    // -- General ------------------------------------------------------------

    public function test_general_settings_can_be_saved()
    {
        $this->post(route('admin.settings.general.update'), [
            'site_name' => 'Acme Co',
            'tagline' => 'Widgets for everyone',
            'timezone' => 'Asia/Dhaka',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'general']));

        $this->assertSame('Acme Co', $this->settings()->get('site_name'));
        $this->assertSame('Widgets for everyone', $this->settings()->get('tagline'));
        $this->assertSame('Asia/Dhaka', $this->settings()->get('timezone'));
    }

    public function test_site_name_is_required()
    {
        $this->post(route('admin.settings.general.update'), [
            'tagline' => 'No name here',
        ])->assertSessionHasErrors('site_name');
    }

    public function test_a_logo_can_be_picked_from_the_media_library()
    {
        $media = MediaFolder::default()->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection();

        $this->post(route('admin.settings.general.update'), [
            'site_name' => 'Acme Co',
            'logo_media_id' => $media->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($media->id, $this->settings()->get('logo_media_id'));
    }

    public function test_saving_general_settings_overrides_the_runtime_brand_config()
    {
        $this->post(route('admin.settings.general.update'), [
            'site_name' => 'Acme Co',
            'tagline' => 'Widgets for everyone',
        ]);

        $this->settings()->applyRuntimeBrandConfig();

        $this->assertSame('Acme Co', config('admin-kit.brand.name'));
        $this->assertSame('Widgets for everyone', config('admin-kit.brand.tagline'));
    }

    public function test_the_brand_component_reflects_the_saved_site_name()
    {
        $this->post(route('admin.settings.general.update'), ['site_name' => 'Acme Co']);

        // A real server re-boots (and so re-runs applyRuntimeBrandConfig())
        // on every incoming request; Laravel's test kernel boots once per
        // test method and reuses that instance across every simulated
        // call, so this needs the same manual nudge the previous test uses
        // -- otherwise this assertion would only ever see the config as it
        // stood before the save above ran.
        $this->settings()->applyRuntimeBrandConfig();

        $this->get(route('admin.dashboard'))->assertSee('Acme Co');
    }

    public function test_without_any_saved_settings_the_config_default_brand_name_still_shows()
    {
        $this->get(route('admin.dashboard'))
            ->assertSee((string) config('admin-kit.brand.name'));
    }

    // -- Contact --------------------------------------------------------------

    public function test_contact_settings_can_be_saved()
    {
        $this->post(route('admin.settings.contact.update'), [
            'email' => 'hello@acme.test',
            'phone' => '+1 555 0100',
            'address' => '1 Acme Way',
            'map_embed' => 'https://maps.google.com/embed?x=1',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'contact']));

        $this->assertSame('hello@acme.test', $this->settings()->get('email'));
        $this->assertSame('https://maps.google.com/embed?x=1', $this->settings()->get('map_embed'));
    }

    public function test_contact_fields_are_all_optional()
    {
        $this->post(route('admin.settings.contact.update'), [])
            ->assertSessionHasNoErrors();
    }

    public function test_the_email_must_be_a_valid_address()
    {
        $this->post(route('admin.settings.contact.update'), [
            'email' => 'not-an-email',
        ])->assertSessionHasErrors('email');
    }

    public function test_map_embed_must_be_a_url_not_raw_html()
    {
        $this->post(route('admin.settings.contact.update'), [
            'map_embed' => '<iframe src="javascript:alert(1)"></iframe>',
        ])->assertSessionHasErrors('map_embed');
    }

    // -- Social -----------------------------------------------------------

    public function test_social_links_can_be_saved()
    {
        $this->post(route('admin.settings.social.update'), [
            'social_links' => [
                ['platform' => 'Facebook', 'url' => 'https://facebook.com/acme'],
                ['platform' => 'Pinterest', 'url' => 'https://pinterest.com/acme'],
            ],
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'social']));

        $this->assertSame(
            [
                ['platform' => 'Facebook', 'url' => 'https://facebook.com/acme'],
                ['platform' => 'Pinterest', 'url' => 'https://pinterest.com/acme'],
            ],
            $this->settings()->get('social_links'),
        );
    }

    /**
     * The form always renders a handful of trailing blank rows so a
     * platform can be added with JS disabled -- these must not trip
     * "required" validation on every save that doesn't fill them in.
     */
    public function test_blank_rows_are_dropped_rather_than_rejected()
    {
        $this->post(route('admin.settings.social.update'), [
            'social_links' => [
                ['platform' => 'Facebook', 'url' => 'https://facebook.com/acme'],
                ['platform' => '', 'url' => ''],
                ['platform' => '', 'url' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            [['platform' => 'Facebook', 'url' => 'https://facebook.com/acme']],
            $this->settings()->get('social_links'),
        );
    }

    public function test_a_platform_without_a_url_is_rejected()
    {
        $this->post(route('admin.settings.social.update'), [
            'social_links' => [
                ['platform' => 'Facebook', 'url' => ''],
            ],
        ])->assertSessionHasErrors('social_links.0.url');
    }

    public function test_social_icons_component_renders_saved_links()
    {
        $this->settings()->saveGroup('social', [
            'social_links' => [['platform' => 'Facebook', 'url' => 'https://facebook.com/acme']],
        ]);

        $html = view('admin::components.social-icons')->render();

        $this->assertStringContainsString('Facebook', $html);
        $this->assertStringContainsString('https://facebook.com/acme', $html);
    }

    public function test_social_icons_component_renders_nothing_without_links()
    {
        $this->assertSame('', trim(view('admin::components.social-icons')->render()));
    }

    // -- SEO (Phase 14) -----------------------------------------------------

    public function test_seo_settings_can_be_saved()
    {
        $this->post(route('admin.settings.seo.update'), [
            'meta_title_suffix' => '— Acme Co',
            'robots_txt' => "User-agent: *\nDisallow: /admin/",
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'seo']));

        $this->assertSame('— Acme Co', $this->settings()->get('meta_title_suffix'));
    }

    public function test_seo_fields_are_all_optional()
    {
        $this->post(route('admin.settings.seo.update'), [])->assertSessionHasNoErrors();
    }

    public function test_the_meta_title_suffix_has_a_length_limit()
    {
        $this->post(route('admin.settings.seo.update'), [
            'meta_title_suffix' => str_repeat('a', 61),
        ])->assertSessionHasErrors('meta_title_suffix');
    }

    public function test_a_default_og_image_can_be_picked_from_the_media_library()
    {
        $media = MediaFolder::default()->addMedia(UploadedFile::fake()->image('og.jpg'))->toMediaCollection();

        $this->post(route('admin.settings.seo.update'), [
            'default_og_image_media_id' => $media->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($media->id, $this->settings()->get('default_og_image_media_id'));
    }

    // -- setting() helper / SettingsManager --------------------------------

    public function test_the_global_setting_helper_reads_saved_values()
    {
        $this->settings()->saveGroup('general', ['site_name' => 'Acme Co']);

        $this->assertSame('Acme Co', setting('site_name'));
        $this->assertSame('fallback', setting('does_not_exist', 'fallback'));
    }

    public function test_saving_clears_the_cache_so_a_read_reflects_the_new_value()
    {
        $this->settings()->saveGroup('general', ['site_name' => 'First']);
        $this->assertSame('First', setting('site_name'));

        $this->settings()->saveGroup('general', ['site_name' => 'Second']);
        $this->assertSame('Second', setting('site_name'));
    }

    /**
     * applyRuntimeBrandConfig() runs on every request, HTTP and console --
     * a broken cache entry here must degrade gracefully rather than take
     * the whole boot cycle down with it (this happened for real during
     * development: a stale cache value failed to unserialize and even
     * `artisan cache:clear` itself couldn't boot to fix it).
     */
    public function test_a_broken_cache_entry_does_not_crash_the_settings_read()
    {
        $this->settings()->saveGroup('general', ['site_name' => 'Acme Co']);

        cache()->put('admin-kit.settings.all', 'not a collection, deliberately malformed', now()->addMinute());

        $this->assertSame('Acme Co', $this->settings()->get('site_name'));
    }

    // -- System (Phase 15) --------------------------------------------------

    public function test_system_settings_can_be_saved()
    {
        $this->post(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'maintenance_message' => 'Back soon.',
            'maintenance_allowed_ips' => "127.0.0.1\n10.0.0.5",
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
            'currency_code' => 'BDT',
            'currency_symbol' => '৳',
            'currency_position' => 'suffix',
            'backup_schedule' => 'daily',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'system']));

        $this->assertTrue($this->settings()->get('maintenance_mode'));
        $this->assertSame('Back soon.', $this->settings()->get('maintenance_message'));
        $this->assertSame(['127.0.0.1', '10.0.0.5'], $this->settings()->get('maintenance_allowed_ips'));
        $this->assertSame('d/m/Y', $this->settings()->get('date_format'));
        $this->assertSame('suffix', $this->settings()->get('currency_position'));
    }

    /**
     * maintenance_mode is stored/read as a real boolean (type='boolean'),
     * not the truthy-string PHP would happen to get right by accident --
     * an unchecked checkbox sends nothing at all, which must read back
     * false, not just "falsy".
     */
    public function test_maintenance_mode_round_trips_as_a_real_boolean_not_a_string()
    {
        $this->post(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
        ]);
        $this->assertSame(true, $this->settings()->get('maintenance_mode'));

        $this->post(route('admin.settings.system.update'), [
            // Unchecked: the field is simply absent from the submission.
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
        ]);
        $this->assertSame(false, $this->settings()->get('maintenance_mode'));
    }

    public function test_date_format_and_time_format_are_required()
    {
        $this->post(route('admin.settings.system.update'), [
            'currency_position' => 'prefix',
        ])->assertSessionHasErrors(['date_format', 'time_format']);
    }

    public function test_date_format_must_be_one_of_the_known_options()
    {
        $this->post(route('admin.settings.system.update'), [
            'date_format' => 'not-a-real-format',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
        ])->assertSessionHasErrors('date_format');
    }

    public function test_allowed_ips_must_each_be_a_valid_ip_address()
    {
        $this->post(route('admin.settings.system.update'), [
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'maintenance_allowed_ips' => "127.0.0.1\nnot-an-ip",
        ])->assertSessionHasErrors('maintenance_allowed_ips.1');
    }

    public function test_currency_code_must_be_three_uppercase_letters()
    {
        $this->post(route('admin.settings.system.update'), [
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'currency_code' => 'usd',
        ])->assertSessionHasErrors('currency_code');
    }

    public function test_applying_the_runtime_timezone_updates_app_config()
    {
        $this->settings()->saveGroup('general', ['timezone' => 'Asia/Dhaka']);

        $this->settings()->applyRuntimeTimezone();

        $this->assertSame('Asia/Dhaka', config('app.timezone'));
    }

    public function test_an_invalid_timezone_value_is_ignored()
    {
        $this->settings()->saveGroup('general', ['timezone' => 'Not/A/Real/Zone']);
        $original = config('app.timezone');

        $this->settings()->applyRuntimeTimezone();

        $this->assertSame($original, config('app.timezone'));
    }

    // -- Backups (Phase 19) --------------------------------------------------

    public function test_backup_schedule_and_s3_toggle_can_be_saved()
    {
        $this->post(route('admin.settings.system.update'), [
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'weekly',
            'backup_s3_enabled' => '1',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'system']));

        $this->assertSame('weekly', $this->settings()->get('backup_schedule'));
        $this->assertSame(true, $this->settings()->get('backup_s3_enabled'));
    }

    /**
     * Same unchecked-checkbox story as maintenance_mode: the field is
     * simply absent from the submission when unticked, and must still
     * read back false, not just leave the previous value in place.
     */
    public function test_backup_s3_enabled_round_trips_as_a_real_boolean_not_a_string()
    {
        $this->post(route('admin.settings.system.update'), [
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
            'backup_s3_enabled' => '1',
        ]);
        $this->assertSame(true, $this->settings()->get('backup_s3_enabled'));

        $this->post(route('admin.settings.system.update'), [
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_position' => 'prefix',
            'backup_schedule' => 'daily',
        ]);
        $this->assertSame(false, $this->settings()->get('backup_s3_enabled'));
    }

    public function test_applying_the_runtime_backup_config_adds_s3_only_when_enabled_and_configured()
    {
        config()->set('filesystems.disks.s3.bucket', 'nk-test-bucket');
        config()->set('backup.backup.destination.disks', ['local']);

        $this->settings()->saveGroup('system', ['backup_s3_enabled' => false], ['backup_s3_enabled' => 'boolean']);
        $this->settings()->applyRuntimeBackupConfig();
        $this->assertSame(['local'], config('backup.backup.destination.disks'));

        $this->settings()->saveGroup('system', ['backup_s3_enabled' => true], ['backup_s3_enabled' => 'boolean']);
        $this->settings()->applyRuntimeBackupConfig();
        $this->assertSame(['local', 's3'], config('backup.backup.destination.disks'));

        // Laravel's test container is reused across tests in this process
        // (only the database resets per test) -- without restoring these,
        // 's3' would stay in the destination list, and later tests
        // touching BackupController would try to actually reach a fake S3
        // bucket and stall on the connection timeout.
        config()->set('filesystems.disks.s3.bucket', null);
        config()->set('backup.backup.destination.disks', ['local']);
    }

    public function test_applying_the_runtime_backup_config_ignores_the_toggle_when_s3_has_no_bucket_configured()
    {
        config()->set('filesystems.disks.s3.bucket', null);
        config()->set('backup.backup.destination.disks', ['local']);

        $this->settings()->saveGroup('system', ['backup_s3_enabled' => true], ['backup_s3_enabled' => 'boolean']);
        $this->settings()->applyRuntimeBackupConfig();

        $this->assertSame(['local'], config('backup.backup.destination.disks'));
    }
}
