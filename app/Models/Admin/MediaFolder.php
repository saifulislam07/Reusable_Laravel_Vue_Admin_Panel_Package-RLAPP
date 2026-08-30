<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Database\Factories\Admin\MediaFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The owning model behind the central media library: every uploaded file
 * is Media attached to one of these (medialibrary has no "no folder"
 * concept, so uploads with no folder chosen go to DEFAULT_NAME, which is
 * auto-provisioned and can't be deleted -- see MediaController).
 *
 * @property int $id
 * @property string $name
 */
class MediaFolder extends Model implements HasMedia
{
    /** @use HasFactory<MediaFolderFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const DEFAULT_NAME = 'Uncategorized';

    protected $fillable = ['name'];

    public static function default(): self
    {
        return static::query()->firstOrCreate(['name' => self::DEFAULT_NAME]);
    }

    public function isDefault(): bool
    {
        return $this->name === self::DEFAULT_NAME;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->nonQueued()
            ->width(200)
            ->height(200);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->width(800);
    }
}
