<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Admin\IntegrationSetting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;

/**
 * Reads and writes the per-integration settings (SMTP, Google Analytics,
 * Google Search Console, Facebook Messenger, WhatsApp) behind a cache, so
 * the runtime mail-config override and <x-admin::tracking-scripts /> --
 * both of which run on every request -- don't hit the database each time.
 *
 * Every read is defensive about the table not existing yet (a fresh install
 * before `migrate` has run, or a package consumer mid-publish in Phase 20):
 * it degrades to "nothing configured" rather than breaking the boot cycle.
 */
class IntegrationSettings
{
    private const CACHE_PREFIX = 'admin-kit.integration-settings.';

    public function __construct(
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    /**
     * applyRuntimeMailConfig() runs on every request, HTTP and console
     * alike (including artisan commands like cache:clear itself) -- a
     * corrupt or unreadable cached entry must degrade the same way a
     * missing table does, not throw past this point and take the whole
     * boot cycle down with it.
     *
     * @return array<string, mixed>
     */
    public function get(string $group): array
    {
        try {
            return $this->cache->rememberForever(
                self::CACHE_PREFIX.$group,
                fn (): array => $this->readFromDatabase($group),
            );
        } catch (Throwable) {
            return $this->readFromDatabase($group);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function set(string $group, array $data): void
    {
        IntegrationSetting::query()->updateOrCreate(['group' => $group], ['data' => $data]);

        $this->cache->forget(self::CACHE_PREFIX.$group);
    }

    /**
     * Whether $group has its explicit `enabled` flag set, defaulting to
     * false. Used by integrations with an on/off toggle independent of
     * whether their other fields are filled in (Messenger, WhatsApp).
     */
    public function enabled(string $group): bool
    {
        return (bool) ($this->get($group)['enabled'] ?? false);
    }

    /**
     * SMTP has no separate on/off switch -- it's "on" the moment a host has
     * been configured, matching how the runtime mail override decides
     * whether to step in.
     */
    public function smtpConfigured(): bool
    {
        return filled($this->get('smtp')['host'] ?? null);
    }

    /**
     * Points the `smtp` mailer at the saved settings, if any have been
     * configured -- called once at boot (AppServiceProvider) so mail sent
     * anywhere in the app uses them, and again by the "send test email"
     * button so it proves the settings just saved rather than whatever was
     * cached into config at the start of that request.
     */
    public function applyRuntimeMailConfig(): void
    {
        $smtp = $this->get('smtp');

        if (blank($smtp['host'] ?? null)) {
            return;
        }

        $this->config->set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp['host'],
            'mail.mailers.smtp.port' => $smtp['port'] ?? 587,
            'mail.mailers.smtp.username' => $smtp['username'] ?? null,
            'mail.mailers.smtp.password' => $smtp['password'] ?? null,
            'mail.mailers.smtp.encryption' => $smtp['encryption'] ?? null,
            'mail.from.address' => $smtp['from_address'] ?? $this->config->get('mail.from.address'),
            'mail.from.name' => $smtp['from_name'] ?? $this->config->get('mail.from.name'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function readFromDatabase(string $group): array
    {
        try {
            // ?? already short-circuits safely through a null $setting here
            // (PHP's ?? uses isset-like semantics on the whole chain) --
            // ?->data would be redundant on top of it.
            return IntegrationSetting::query()->firstWhere('group', $group)->data ?? [];
        } catch (Throwable) {
            return [];
        }
    }
}
