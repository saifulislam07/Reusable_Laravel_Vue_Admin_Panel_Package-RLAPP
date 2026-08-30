<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ContactSettingsRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:1000'],
            // A Google Maps (or similar) embed src -- rendered in an
            // <iframe src="..."> as-is, never as raw stored HTML, so this
            // only ever needs to be a URL, not markup.
            'map_embed' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
