<?php

declare(strict_types=1);

use App\Support\Admin\SettingsManager;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /**
     * Read a Phase 13 site setting -- setting('site_name'), setting('email').
     * Distinct from Laravel's config()/env(): this reads admin-editable,
     * cached, database-backed values, not the codebase's own config files.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsManager::class)->get($key, $default);
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date using Settings > System's date_format (Phase 15),
     * falling back to Y-m-d until one is saved. Pass $withTime to append
     * time_format too.
     */
    function format_date(Carbon|DateTimeInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof Carbon
            ? $date
            : ($date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date));

        $format = (string) setting('date_format', 'Y-m-d');

        if ($withTime) {
            $format .= ' '.(string) setting('time_format', 'H:i');
        }

        return $carbon->format($format);
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format an amount using Settings > System's currency_symbol and
     * currency_position (Phase 15), falling back to a plain "$" prefix
     * until they're saved.
     */
    function format_currency(int|float $amount): string
    {
        $symbol = (string) setting('currency_symbol', '$');
        $formatted = number_format((float) $amount, 2);

        return setting('currency_position', 'prefix') === 'suffix'
            ? "{$formatted}{$symbol}"
            : "{$symbol}{$formatted}";
    }
}
