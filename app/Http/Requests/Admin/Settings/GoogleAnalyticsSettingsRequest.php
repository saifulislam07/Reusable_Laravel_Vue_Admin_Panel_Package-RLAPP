<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class GoogleAnalyticsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // GA4 measurement IDs look like "G-XXXXXXXXXX".
            'measurement_id' => ['nullable', 'string', 'max:32', 'regex:/^G-[A-Z0-9]+$/i'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
