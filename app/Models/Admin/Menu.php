<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Database\Factories\Admin\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sidebar entry. Read through App\Support\Admin\MenuManager's
 * "database" driver (registered in AdminMenuServiceProvider) rather than
 * directly -- that driver is what converts a tree of these into the
 * MenuItem objects the layout renders.
 *
 * @property int $id
 * @property string $label
 * @property string|null $icon
 * @property string|null $route
 * @property string|null $url
 * @property int|null $parent_id
 * @property int $order
 * @property string|null $permission_name
 * @property bool $is_active
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $fillable = [
        'label', 'icon', 'route', 'url', 'parent_id', 'order', 'permission_name', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    /**
     * @param  Builder<Menu>  $query
     * @return Builder<Menu>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }

    /**
     * @param  Builder<Menu>  $query
     * @return Builder<Menu>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
