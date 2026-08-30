<?php

namespace App\Providers;

use App\Listeners\Admin\BackupEventSubscriber;
use App\Models\Admin\ContactMessage;
use App\Models\User;
use App\Observers\Admin\ContactMessageObserver;
use App\Support\Admin\DatabaseMenuSource;
use App\Support\Admin\IntegrationSettings;
use App\Support\Admin\MenuManager;
use App\Support\Admin\SettingsManager;
use App\Support\Admin\Theme;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerAdminKit();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->bootAdminKit();

        // Public contact-form endpoint (Phase 16) -- kept out of
        // bootAdminKit() since, unlike everything else there, this isn't
        // part of the admin-kit package Phase 20 extracts.
        RateLimiter::for(
            'contact-form',
            fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->ip()),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Bind the admin-kit services.
     *
     * Kept in its own method so Phase 20 can move it wholesale into the
     * package's AdminKitServiceProvider.
     */
    protected function registerAdminKit(): void
    {
        $this->app->singleton(Theme::class, fn ($app): Theme => new Theme($app->make(Config::class)));

        $this->app->singleton(MenuManager::class, fn ($app): MenuManager => new MenuManager(
            $app->make(Config::class),
            $app->make(AuthFactory::class),
        ));

        $this->app->singleton(IntegrationSettings::class, fn ($app): IntegrationSettings => new IntegrationSettings(
            $app->make(Cache::class),
            $app->make(Config::class),
        ));

        $this->app->singleton(SettingsManager::class, fn ($app): SettingsManager => new SettingsManager(
            $app->make(Cache::class),
            $app->make(Config::class),
        ));
    }

    /**
     * Wire the admin views up: `admin::` namespace plus the data every
     * admin layout needs.
     */
    protected function bootAdminKit(): void
    {
        View::addNamespace('admin', resource_path('views/admin'));

        // Coarse per-IP flood guard on the admin auth endpoints. The precise,
        // per-account throttle lives in LoginRequest.
        RateLimiter::for(
            'admin-login',
            fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()),
        );

        View::composer(['admin::layouts.*', 'admin::components.*', 'admin::errors.*'], function ($view): void {
            /** @var User|null $nkNotifiable */
            $nkNotifiable = auth()->guard(config('admin-kit.guard'))->user();

            $view->with([
                'nkTheme' => $this->app->make(Theme::class),
                'nkMenu' => $this->app->make(MenuManager::class),
                // Phase 17: topbar bell dropdown. Defensive because these
                // partials also render on guest-facing pages (login,
                // password reset) where there is no authenticated user.
                'nkUnreadNotificationsCount' => $nkNotifiable?->unreadNotifications()->count() ?? 0,
                'nkRecentNotifications' => $nkNotifiable?->notifications()->latest()->limit(5)->get() ?? collect(),
            ]);
        });

        // Phase 17: notify every admin (User::admins()) when a visitor
        // submits the public contact form. See ContactMessageObserver's
        // docblock and ContactMessage's own -- this closes the hook that
        // model was already built to expect.
        ContactMessage::observe(ContactMessageObserver::class);

        // 'database' is opt-in (config('admin-kit.menu.driver') still
        // defaults to 'config') -- see DatabaseMenuSource's docblock.
        $this->app->make(MenuManager::class)->extend(
            'database',
            $this->app->make(DatabaseMenuSource::class),
        );

        // The protected role (config('admin-kit.roles.protected_role'))
        // always passes every permission check, including checkpermission
        // route middleware and MenuManager's visibility check -- both go
        // through $user->can(), and Gate::before() runs ahead of it. This is
        // what makes deleting every Permission row, or a bug in a specific
        // permission's wiring, unable to lock every admin out at once: there
        // is always at least one role nothing can strip access from.
        Gate::before(fn (User $user): ?bool => $user->hasRole((string) config('admin-kit.roles.protected_role')) ? true : null);

        // Runs on every request so mail sent anywhere in the app -- password
        // resets, the test-email button, anything else -- goes through the
        // admin-configured SMTP once it exists. No-ops (falls back to
        // config/mail.php / .env) until an admin actually saves SMTP
        // settings, and IntegrationSettings::get() itself tolerates the
        // table not existing yet.
        //
        // Incompatible with `php artisan config:cache`: that command bakes
        // whatever this resolves to *right now* into a static file, which
        // defeats "editable without a deploy." Not a concern in local dev;
        // worth a README callout once this ships as a package (Phase 20).
        $this->app->make(IntegrationSettings::class)->applyRuntimeMailConfig();

        // Same idea, for branding: <x-admin::brand /> and the favicon
        // <link> already read config('admin-kit.brand.*') and don't need
        // to change -- this just points those keys at the saved General
        // settings (Phase 13) when any exist. Same config:cache caveat as
        // applyRuntimeMailConfig() above.
        $this->app->make(SettingsManager::class)->applyRuntimeBrandConfig();

        // Phase 15: date_default_timezone_set() from the saved 'timezone'
        // setting, so now()/Carbon::now() reflect it for the rest of the
        // request. No-ops when nothing's been saved yet.
        $this->app->make(SettingsManager::class)->applyRuntimeTimezone();

        // Phase 19: adds the 's3' disk to backup.backup.destination.disks
        // when an admin has switched it on and AWS_BUCKET is actually set.
        // No-ops otherwise (stays 'local' only).
        $this->app->make(SettingsManager::class)->applyRuntimeBackupConfig();

        // Phase 19: BackupWasSuccessful/BackupHasFailed -> every admin,
        // through the same AdminAlert system as Phase 17 -- see
        // BackupEventSubscriber's docblock for why this replaces the
        // package's own built-in notifications instead of running
        // alongside them.
        Event::subscribe(BackupEventSubscriber::class);
    }
}
