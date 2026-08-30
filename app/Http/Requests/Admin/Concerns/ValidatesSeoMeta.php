<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Rule;

/**
 * The `seo.*` fieldset shared by every FormRequest editing a model that
 * `use`s App\Models\Admin\Concerns\HasSeoMeta -- add `...$this->seoMetaRules()`
 * to rules() so a new SEO-enabled resource doesn't have to redeclare this.
 */
trait ValidatesSeoMeta
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function seoMetaRules(): array
    {
        return [
            'seo' => ['sometimes', 'array'],
            // Google truncates a title around ~60 chars and a description
            // around ~160 in search results -- the limits here match that,
            // not an arbitrary column width.
            'seo.meta_title' => ['nullable', 'string', 'max:60'],
            'seo.meta_description' => ['nullable', 'string', 'max:160'],
            'seo.meta_keywords' => ['nullable', 'string', 'max:255'],
            'seo.og_image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'seo.canonical_url' => ['nullable', 'url', 'max:2048'],
            'seo.robots' => ['nullable', Rule::in(['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])],
        ];
    }
}
