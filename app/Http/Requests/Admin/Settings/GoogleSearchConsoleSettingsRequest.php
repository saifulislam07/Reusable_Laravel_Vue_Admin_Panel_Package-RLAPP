<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class GoogleSearchConsoleSettingsRequest extends FormRequest
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
            // The "content" value from Search Console's HTML-tag verification
            // method -- <meta name="google-site-verification" content="…">.
            'verification_meta' => ['nullable', 'string', 'max:255'],
        ];
    }
}
