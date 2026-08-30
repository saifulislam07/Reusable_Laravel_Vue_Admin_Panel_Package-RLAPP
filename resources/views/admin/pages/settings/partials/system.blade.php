{{--
    Expects: $system (array)
--}}
@php
    $dateFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M, Y', 'F j, Y'];
    $dateFormatOptions = collect($dateFormats)->mapWithKeys(fn ($format) => [$format => now()->format($format).' ('.$format.')'])->all();

    $timeFormats = ['H:i', 'h:i A'];
    $timeFormatOptions = collect($timeFormats)->mapWithKeys(fn ($format) => [$format => now()->format($format).' ('.$format.')'])->all();

    $allowedIps = old('maintenance_allowed_ips', $system['maintenance_allowed_ips'] ?? []);
    $allowedIpsText = is_array($allowedIps) ? implode("\n", $allowedIps) : (string) $allowedIps;
@endphp

{{--
    One form for the whole tab (matching General/Contact/Social/SEO) --
    date_format/time_format/currency_position are "required" in
    SystemSettingsRequest, so a separate Maintenance-only form would fail
    validation every time someone just toggles the checkbox.
--}}
<form method="POST" action="{{ route('admin.settings.system.update') }}" class="nk-form">
    @csrf

    <x-admin::card :title="__('Maintenance')">
        <x-admin::form.checkbox
            name="maintenance_mode"
            :label="__('Put the public site into maintenance mode')"
            :checked="(bool) ($system['maintenance_mode'] ?? false)"
        />

        <x-admin::form.textarea
            name="maintenance_message"
            :label="__('Maintenance message')"
            :value="old('maintenance_message', $system['maintenance_message'] ?? null)"
            :hint="__('Shown on the 503 page. The admin panel itself is never blocked -- you can always sign in to turn this back off.')"
        />

        <x-admin::form.textarea
            name="maintenance_allowed_ips"
            :label="__('Allowed IP addresses')"
            :value="$allowedIpsText"
            :hint="__('One per line. These visitors bypass maintenance mode too.')"
            rows="3"
        />
    </x-admin::card>

    <x-admin::card :title="__('Formats')" style="margin-top: 1rem;">
        <div class="nk-form__row" style="align-items: flex-start;">
            <div style="flex: 1; min-width: 200px;">
                <x-admin::form.select
                    name="date_format"
                    :label="__('Date format')"
                    :options="$dateFormatOptions"
                    :value="old('date_format', $system['date_format'] ?? 'Y-m-d')"
                />
            </div>

            <div style="flex: 1; min-width: 200px;">
                <x-admin::form.select
                    name="time_format"
                    :label="__('Time format')"
                    :options="$timeFormatOptions"
                    :value="old('time_format', $system['time_format'] ?? 'H:i')"
                />
            </div>
        </div>

        <div class="nk-form__row" style="align-items: flex-start;">
            <div style="flex: 1; min-width: 160px;">
                <x-admin::form.input
                    name="currency_code"
                    :label="__('Currency code')"
                    :value="old('currency_code', $system['currency_code'] ?? null)"
                    placeholder="USD"
                    :hint="__('ISO 4217, e.g. USD, EUR, BDT.')"
                />
            </div>

            <div style="flex: 1; min-width: 160px;">
                <x-admin::form.input
                    name="currency_symbol"
                    :label="__('Currency symbol')"
                    :value="old('currency_symbol', $system['currency_symbol'] ?? '$')"
                    placeholder="$"
                />
            </div>

            <div style="flex: 1; min-width: 160px;">
                <x-admin::form.select
                    name="currency_position"
                    :label="__('Symbol position')"
                    :options="['prefix' => __('Prefix ($100)'), 'suffix' => __('Suffix (100$)')]"
                    :value="old('currency_position', $system['currency_position'] ?? 'prefix')"
                />
            </div>
        </div>
    </x-admin::card>

    <x-admin::card :title="__('Activity Log retention')" style="margin-top: 1rem;">
        <div style="max-width: 260px;">
            <x-admin::form.input
                type="number"
                name="activity_log_retention_days"
                :label="__('Keep activity log entries for (days)')"
                :value="old('activity_log_retention_days', $system['activity_log_retention_days'] ?? '')"
                placeholder="365"
                min="0"
                :hint="__('Purged daily by the scheduler. Leave blank or 0 to keep entries forever.')"
            />
        </div>
    </x-admin::card>

    <x-admin::card :title="__('Backups')" style="margin-top: 1rem;">
        <div style="max-width: 260px;">
            <x-admin::form.select
                name="backup_schedule"
                :label="__('Automatic schedule')"
                :options="['disabled' => __('Disabled'), 'daily' => __('Daily'), 'weekly' => __('Weekly (Monday)')]"
                :value="old('backup_schedule', $system['backup_schedule'] ?? 'daily')"
            />
        </div>

        <x-admin::form.checkbox
            name="backup_s3_enabled"
            :label="__('Also store backups on S3')"
            :checked="(bool) ($system['backup_s3_enabled'] ?? false)"
            :disabled="! $s3Available"
        />
        <p class="nk-field__hint">
            @if ($s3Available)
                {{ __('Backups are copied to the local disk and to S3.') }}
            @else
                {{ __('Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, and AWS_BUCKET in .env to enable this.') }}
            @endif
        </p>
    </x-admin::card>

    <div class="nk-form__row" style="margin-top: 1rem;">
        <button type="submit" class="nk-btn nk-btn--primary">{{ __('Save') }}</button>
    </div>
</form>
