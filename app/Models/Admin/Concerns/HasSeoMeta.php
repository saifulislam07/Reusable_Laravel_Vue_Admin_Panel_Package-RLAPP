<?php

declare(strict_types=1);

namespace App\Models\Admin\Concerns;

use App\Models\Admin\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

/**
 * Attaches one polymorphic SeoMeta row to a model (Page today; any future
 * content type gets the same fields for free by adding this trait plus a
 * `seo` fieldset to its form -- no migration of its own). Pairs with
 * <x-admin::seo-meta :model="$page" />, which reads seoMeta and falls back
 * to the model's own title/Settings' SEO defaults when it's blank.
 */
trait HasSeoMeta
{
    /**
     * @return MorphOne<SeoMeta, $this>
     */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * A fully-blank submission removes the row entirely rather than
     * leaving an all-null one behind -- keeps "no override" meaning
     * exactly that, not a row full of nulls.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncSeoMeta(array $data): void
    {
        if (Collection::make($data)->filter(fn (mixed $value): bool => filled($value))->isEmpty()) {
            $this->seoMeta()->delete();

            return;
        }

        $this->seoMeta()->updateOrCreate([], $data);
    }
}
