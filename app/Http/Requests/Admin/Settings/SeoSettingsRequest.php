<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The global SEO defaults the plan asks for -- everything per-page lives on
 * SeoMeta instead (App\Models\Admin\Concerns\HasSeoMeta), which falls back
 * to these when a page doesn't override them.
 */
class SeoSettingsRequest extends FormRequest
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
            'meta_title_suffix' => ['nullable', 'string', 'max:60'],
            'default_og_image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'robots_txt' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
