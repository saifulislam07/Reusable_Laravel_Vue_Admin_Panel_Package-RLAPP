<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The "dynamic key-value list" the plan asks for: an arbitrary-length list
 * of {platform, url} pairs rather than one column per named platform, so a
 * new platform needs no migration -- see SettingsManager::saveGroup(),
 * which stores the whole list as one 'json'-typed setting.
 */
class SocialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The form always renders a few trailing blank rows (so a platform can
     * be added with JS disabled -- see admin::pages.settings.partials.
     * social) that stay blank on most submits; drop them here rather than
     * making platform/url "required" reject an admin who didn't touch them.
     */
    protected function prepareForValidation(): void
    {
        /** @var array<array-key, mixed> $rawLinks */
        $rawLinks = (array) $this->input('social_links', []);

        $links = collect($rawLinks)
            ->filter(fn (mixed $link): bool => filled(is_array($link) ? ($link['platform'] ?? null) : null)
                || filled(is_array($link) ? ($link['url'] ?? null) : null))
            ->values()
            ->all();

        $this->merge(['social_links' => $links]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'social_links' => ['sometimes', 'array'],
            'social_links.*.platform' => ['required', 'string', 'max:60'],
            'social_links.*.url' => ['required', 'url', 'max:2048'],
        ];
    }
}
