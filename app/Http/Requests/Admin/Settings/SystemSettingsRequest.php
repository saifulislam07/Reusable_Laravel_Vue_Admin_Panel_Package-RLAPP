<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One IP (or CIDR-free address) per line in the textarea becomes an
     * array before validation, matching how SettingsManager::saveGroup()
     * stores a list ('json' type) -- see SettingsController::updateSystem().
     */
    protected function prepareForValidation(): void
    {
        $ips = collect(explode("\n", (string) $this->input('maintenance_allowed_ips', '')))
            ->map(fn (string $ip): string => trim($ip))
            ->filter()
            ->values()
            ->all();

        $this->merge(['maintenance_allowed_ips' => $ips]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'maintenance_allowed_ips' => ['array'],
            'maintenance_allowed_ips.*' => ['ip'],

            'date_format' => ['required', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M, Y', 'F j, Y'])],
            'time_format' => ['required', Rule::in(['H:i', 'h:i A'])],

            'currency_code' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'currency_symbol' => ['nullable', 'string', 'max:5'],
            'currency_position' => ['required', Rule::in(['prefix', 'suffix'])],

            'activity_log_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],

            'backup_schedule' => ['required', Rule::in(['disabled', 'daily', 'weekly'])],
            'backup_s3_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
