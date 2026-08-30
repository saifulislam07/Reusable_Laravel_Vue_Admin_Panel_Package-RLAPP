<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Admin\PageStatus;
use App\Http\Requests\Admin\Concerns\ValidatesSeoMeta;
use App\Models\Admin\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    use ValidatesSeoMeta;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank slug is filled in from the title before validation runs, so
     * "leave it blank and let the panel figure it out" never trips the
     * required/unique rules below. A slug the admin actually typed is left
     * alone -- if it collides, that's a validation error asking them to
     * change it, not something to silently rewrite.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('slug') || ! $this->filled('title')) {
            return;
        }

        /** @var Page|null $editing */
        $editing = $this->route('page');

        $this->merge([
            'slug' => Page::generateUniqueSlug((string) $this->input('title'), $editing?->id),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Page|null $editing */
        $editing = $this->route('page');
        $isScheduled = $this->input('status') === PageStatus::Scheduled->value;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('pages', 'slug')->ignore($editing?->id),
            ],
            'content' => ['nullable', 'string'],
            'template' => ['required', Rule::in(array_keys((array) config('admin-kit.cms.templates', [])))],
            'status' => ['required', Rule::enum(PageStatus::class)],

            // Scheduled needs a future date to mean anything; Published
            // defaults to "now" in the controller when left blank, and
            // Draft has no publish date at all.
            'published_at' => array_filter([
                Rule::requiredIf($isScheduled),
                'nullable',
                'date',
                $isScheduled ? 'after:now' : null,
            ]),

            ...$this->seoMetaRules(),
        ];
    }
}
