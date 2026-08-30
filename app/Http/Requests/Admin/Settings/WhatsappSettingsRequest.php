<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class WhatsappSettingsRequest extends FormRequest
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
            // Digits only (with an optional leading +) -- wa.me links need
            // the number in international format, no spaces or punctuation.
            'number' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
            'message' => ['nullable', 'string', 'max:500'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
