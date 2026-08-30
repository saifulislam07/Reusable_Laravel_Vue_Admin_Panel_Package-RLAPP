<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Enums\Admin\PageStatus;
use App\Models\Admin\Concerns\HasSeoMeta;
use Database\Factories\Admin\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A single piece of static content (title + rich-text body), managed
 * entirely from the admin panel. No public route renders these yet --
 * Phase 11's brief is the admin CRUD; a consuming project decides how
 * (and whether) to expose them publicly.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property PageStatus $status
 * @property string $template
 * @property Carbon|null $published_at
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasSeoMeta;
    use LogsActivity;

    protected $fillable = [
        'title', 'slug', 'content', 'status', 'template', 'published_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pages')
            ->setDescriptionForEvent(fn (string $event): string => "Page \"{$this->title}\" {$event}");
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Slugify $title, then disambiguate with -2, -3, ... until it doesn't
     * collide -- used when an admin leaves the slug field blank rather than
     * rejecting the save outright. A slug they typed themselves that
     * collides is a validation error instead (PageRequest); this is only
     * for the "generate one for me" path.
     */
    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'page';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
