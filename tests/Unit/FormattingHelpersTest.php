<?php

namespace Tests\Unit;

use App\Support\Admin\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The global format_date()/format_currency() helpers (Phase 15,
 * app/Support/helpers.php) -- both read Settings > System, falling back
 * to sensible defaults until something's saved there.
 */
class FormattingHelpersTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): SettingsManager
    {
        return $this->app->make(SettingsManager::class);
    }

    public function test_format_date_falls_back_to_y_m_d_with_no_saved_setting()
    {
        $date = Carbon::create(2026, 3, 5);

        $this->assertSame('2026-03-05', format_date($date));
    }

    public function test_format_date_respects_the_saved_date_format()
    {
        $this->settings()->saveGroup('system', ['date_format' => 'd/m/Y']);

        $this->assertSame('05/03/2026', format_date(Carbon::create(2026, 3, 5)));
    }

    public function test_format_date_with_time_appends_the_saved_time_format()
    {
        $this->settings()->saveGroup('system', ['date_format' => 'Y-m-d', 'time_format' => 'H:i']);

        $this->assertSame('2026-03-05 14:30', format_date(Carbon::create(2026, 3, 5, 14, 30), withTime: true));
    }

    public function test_format_date_accepts_a_string_and_a_native_datetime()
    {
        $this->assertSame('2026-03-05', format_date('2026-03-05 00:00:00'));
        $this->assertSame('2026-03-05', format_date(new \DateTime('2026-03-05')));
    }

    public function test_format_date_returns_an_empty_string_for_null()
    {
        $this->assertSame('', format_date(null));
    }

    public function test_format_currency_defaults_to_a_dollar_prefix()
    {
        $this->assertSame('$1,234.50', format_currency(1234.5));
    }

    public function test_format_currency_respects_a_saved_symbol_and_suffix_position()
    {
        $this->settings()->saveGroup('system', ['currency_symbol' => '৳', 'currency_position' => 'suffix']);

        $this->assertSame('1,234.50৳', format_currency(1234.5));
    }

    public function test_format_currency_always_shows_two_decimal_places()
    {
        $this->assertSame('$5.00', format_currency(5));
    }
}
