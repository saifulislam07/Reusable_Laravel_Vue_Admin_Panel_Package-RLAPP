<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\Settings\ContactSettingsRequest;
use App\Http\Requests\Admin\Settings\GeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\SeoSettingsRequest;
use App\Http\Requests\Admin\Settings\SocialSettingsRequest;
use App\Http\Requests\Admin\Settings\SystemSettingsRequest;
use App\Models\User;
use App\Notifications\Admin\SystemAlert;
use App\Support\Admin\SettingsManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One page, five tabs (General, Contact, Social, SEO, System) -- see
 * admin::pages.settings.index. Same "each tab posts to its own action,
 * all redirect back with ?tab= set" shape as IntegrationSettingsController.
 */
class SettingsController extends AdminController
{
    public function __construct(private readonly SettingsManager $settings) {}

    public function edit(): View
    {
        $general = $this->settings->group('general');
        $seo = $this->settings->group('seo');

        return view('admin::pages.settings.index', [
            'general' => $general,
            'contact' => $this->settings->group('contact'),
            'social' => $this->settings->group('social'),
            'seo' => $seo,
            'system' => $this->settings->group('system'),
            'logo' => filled($general['logo_media_id'] ?? null) ? Media::query()->find($general['logo_media_id']) : null,
            'favicon' => filled($general['favicon_media_id'] ?? null) ? Media::query()->find($general['favicon_media_id']) : null,
            'defaultOgImage' => filled($seo['default_og_image_media_id'] ?? null) ? Media::query()->find($seo['default_og_image_media_id']) : null,
            'timezoneOptions' => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()),
            's3Available' => filled(config('filesystems.disks.s3.bucket')),
        ]);
    }

    public function updateGeneral(GeneralSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveGroup('general', $request->validated(), [
            'logo_media_id' => 'integer',
            'favicon_media_id' => 'integer',
        ]);

        return $this->redirectToTab('general', __('General settings saved.'));
    }

    public function updateContact(ContactSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveGroup('contact', $request->validated());

        return $this->redirectToTab('contact', __('Contact settings saved.'));
    }

    public function updateSocial(SocialSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveGroup('social', [
            'social_links' => $request->validated('social_links', []),
        ]);

        return $this->redirectToTab('social', __('Social links saved.'));
    }

    public function updateSeo(SeoSettingsRequest $request): RedirectResponse
    {
        $this->settings->saveGroup('seo', $request->validated(), [
            'default_og_image_media_id' => 'integer',
        ]);

        return $this->redirectToTab('seo', __('SEO settings saved.'));
    }

    public function updateSystem(SystemSettingsRequest $request): RedirectResponse
    {
        $wasEnabled = (bool) ($this->settings->group('system')['maintenance_mode'] ?? false);
        $isEnabled = $request->boolean('maintenance_mode');

        // An unchecked checkbox isn't submitted at all, so 'sometimes' in
        // SystemSettingsRequest leaves it out of validated() entirely --
        // saveGroup() would then just leave the previous value in place
        // instead of turning maintenance mode back off. $request->boolean()
        // always resolves to true/false regardless of whether the field
        // was present.
        $this->settings->saveGroup('system', [
            ...$request->validated(),
            'maintenance_mode' => $isEnabled,
            // Same unchecked-checkbox story as maintenance_mode above.
            'backup_s3_enabled' => $request->boolean('backup_s3_enabled'),
        ], [
            'maintenance_mode' => 'boolean',
            'maintenance_allowed_ips' => 'json',
            'activity_log_retention_days' => 'integer',
            'backup_s3_enabled' => 'boolean',
        ]);

        // A real trigger for SystemAlert (Phase 17), fired only on the
        // false -> true transition so other admins learn the site is down
        // for visitors, not on every unrelated System-tab save.
        if (! $wasEnabled && $isEnabled) {
            Notification::send(
                User::query()->admins()->whereKeyNot($request->user()?->getKey())->get(),
                new SystemAlert(__('Maintenance mode has been turned on.'), route('admin.settings.edit', ['tab' => 'system']), __('View settings')),
            );
        }

        return $this->redirectToTab('system', __('System settings saved.'));
    }

    private function redirectToTab(string $tab, string $message): RedirectResponse
    {
        $this->flashSuccess($message);

        return redirect()->route('admin.settings.edit', ['tab' => $tab]);
    }
}
