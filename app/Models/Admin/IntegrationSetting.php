<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Database\Factories\Admin\IntegrationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One third-party integration's configuration (SMTP, Google Analytics,
 * Google Search Console, Facebook Messenger, WhatsApp). `data` is encrypted
 * at rest -- see the migration -- so credentials never sit in the DB in
 * plain text.
 *
 * Read/write through App\Support\Admin\IntegrationSettings rather than this
 * model directly: that service adds caching and the "does this integration
 * have enough configured to actually turn on" logic.
 *
 * @property int $id
 * @property string $group
 * @property array<string, mixed> $data
 */
class IntegrationSetting extends Model
{
    /** @use HasFactory<IntegrationSettingFactory> */
    use HasFactory;

    protected $fillable = ['group', 'data'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // The whole blob is encrypted, not just individual sensitive
            // fields -- simpler, and nothing here is meant to be queried by
            // its field values.
            'data' => 'encrypted:array',
        ];
    }
}
