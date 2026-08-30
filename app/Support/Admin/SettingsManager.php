<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Admin\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Site-wide branding/contact/social settings (Phase 13) -- distinct from
 * IntegrationSettings (Phase 6), which is SMTP/Analytics/Messenger/WhatsApp.
 * One cached read of the whole table backs both the flat setting($key)
 * helper and group()'s per-tab view.
 *
 * The plan asks for "tagged cache"; a plain cache key is used instead --
 * Cache::tags() only works on Redis/Memcached-style stores and throws on
 * the database/file drivers this project (and most consumers of this kit)
 * actually run, which is exactly why IntegrationSettings avoided it too.
 * A single small cached table plus forget-on-save gets the same result
 * (nothing stale after a save) without that driver assumption.
 */
class SettingsManager
{
    private const CACHE_KEY = 'admin-kit.settings.all';

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        foreach ($this->rows() as $row) {
            if ($row['key'] === $key) {
                return $this->cast($row['value'], $row['type']);
            }
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $values = [];

        foreach ($this->rows() as $row) {
            if ($row['group'] === $group) {
                $values[$row['key']] = $this->cast($row['value'], $row['type']);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $types  Per-key override ('integer',
     *                                        'json'); anything not listed
     *                                        is inferred (array -> 'json',
     *                                        otherwise 'string').
     */
    public function saveGroup(string $group, array $values, array $types = []): void
    {
        foreach ($values as $key => $value) {
            $type = $types[$key] ?? (is_array($value) ? 'json' : 'string');

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['group' => $group, 'type' => $type, 'value' => $this->serialize($value, $type)],
            );
        }

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Points config('admin-kit.brand.*') at the saved General settings, if
     * any have been filled in -- called once at boot so every existing
     * brand-reading view (<x-admin::brand />, the favicon <link>, ...)
     * picks this up with no changes of its own, same shape as
     * IntegrationSettings::applyRuntimeMailConfig().
     */
    public function applyRuntimeBrandConfig(): void
    {
        $general = $this->group('general');

        if ($general === []) {
            return;
        }

        if (filled($general['site_name'] ?? null)) {
            $this->config->set('admin-kit.brand.name', $general['site_name']);
        }

        if (filled($general['tagline'] ?? null)) {
            $this->config->set('admin-kit.brand.tagline', $general['tagline']);
        }

        if (filled($general['logo_media_id'] ?? null)) {
            $this->config->set('admin-kit.brand.logo', $this->mediaUrl((int) $general['logo_media_id']));
        }

        if (filled($general['favicon_media_id'] ?? null)) {
            $this->config->set('admin-kit.brand.favicon', $this->mediaUrl((int) $general['favicon_media_id']));
        }
    }

    /**
     * The 'timezone' setting lives under group('general') (Phase 13's
     * "General" tab already asked for one) rather than duplicating it
     * under group('system') just because Phase 15 mentions timezone too --
     * this is what actually makes that one setting do something: called
     * once at boot, it's what Phase 15 means by "applied ... at
     * bootstrap (with cache)". date_default_timezone_set() takes effect
     * immediately for every now()/Carbon::now() call for the rest of the
     * request, even though PHP's own bootstrap-time timezone init has
     * already run by the time a service provider boots.
     */
    public function applyRuntimeTimezone(): void
    {
        $timezone = $this->get('timezone');

        if (blank($timezone) || ! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        $this->config->set('app.timezone', $timezone);
        date_default_timezone_set($timezone);
    }

    /**
     * Adds 's3' to backup.backup.destination.disks (Phase 19) when an admin
     * has switched the System tab's toggle on -- only if the disk is
     * actually usable, i.e. AWS_BUCKET is filled in .env. Credentials
     * themselves stay env-only (config/filesystems.php's existing 's3'
     * disk), matching the plan's "configurable via Settings or .env": the
     * on/off switch is a Setting, the secrets are not.
     */
    public function applyRuntimeBackupConfig(): void
    {
        if (! $this->get('backup_s3_enabled', false)) {
            return;
        }

        if (blank($this->config->get('filesystems.disks.s3.bucket'))) {
            return;
        }

        $disks = (array) $this->config->get('backup.backup.destination.disks', ['local']);

        if (! in_array('s3', $disks, true)) {
            $disks[] = 's3';
        }

        $this->config->set('backup.backup.destination.disks', $disks);
    }

    /**
     * Caches a plain array, not a Collection -- besides sidestepping a
     * PHPStan/Collection generic-covariance friction on the map() below,
     * it also means the database/file cache driver never has to unserialize
     * a Collection object back out (a corrupt or version-mismatched
     * serialized object is exactly how a bad cache entry could throw here
     * and, with applyRuntimeBrandConfig() running on every single request
     * including artisan commands like cache:clear itself, take the entire
     * boot cycle down with it). Falls back to a live, uncached read on any
     * failure regardless.
     *
     * @return list<array{key: string, value: string|null, group: string, type: string}>
     */
    private function rows(): array
    {
        try {
            return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => $this->readFromDatabase());
        } catch (Throwable) {
            return $this->readFromDatabase();
        }
    }

    /**
     * @return list<array{key: string, value: string|null, group: string, type: string}>
     */
    private function readFromDatabase(): array
    {
        try {
            return array_values(Setting::query()->get(['key', 'value', 'group', 'type'])
                ->map(fn (Setting $setting): array => [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'group' => $setting->group,
                    'type' => $setting->type,
                ])
                ->all());
        } catch (Throwable) {
            // Table doesn't exist yet -- a fresh install before `migrate`,
            // or a package consumer mid-publish (Phase 20).
            return [];
        }
    }

    private function mediaUrl(int $mediaId): ?string
    {
        return Media::query()->find($mediaId)?->getUrl();
    }

    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => $value !== null ? (int) $value : null,
            'boolean' => $value !== null ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : false,
            'json' => $value !== null ? (json_decode($value, true) ?? []) : [],
            default => $value,
        };
    }

    private function serialize(mixed $value, string $type): ?string
    {
        return match ($type) {
            'json' => json_encode($value) ?: '[]',
            'boolean' => $value ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };
    }
}
