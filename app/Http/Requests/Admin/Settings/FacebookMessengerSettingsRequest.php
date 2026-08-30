<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class FacebookMessengerSettingsRequest extends FormRequest
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
            'page_id' => ['nullable', 'string', 'max:64'],
            'app_id' => ['nullable', 'string', 'max:64'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
