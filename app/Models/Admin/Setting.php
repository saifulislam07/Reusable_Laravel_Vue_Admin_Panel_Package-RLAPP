<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A single key/value row. Read and write through App\Support\Admin\
 * SettingsManager (cached), not this model directly, outside of the seeder
 * and the manager itself.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $group
 * @property string $type
 */
class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value', 'group', 'type'];

    /**
     * SettingsManager::saveGroup() upserts one row per key, so a single tab
     * save produces one activity entry per changed setting -- more granular
     * than "System settings updated", but that's exactly what makes the
     * detail view useful (it names the one field that changed).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('settings')
            ->setDescriptionForEvent(fn (string $event): string => "Setting \"{$this->key}\" {$event}");
    }
}
