<?php

declare(strict_types=1);
use App\Support\Admin\Widgets\StatCardsWidget;
use App\Support\Admin\Widgets\UsersGrowthChartWidget;

/*
|--------------------------------------------------------------------------
| NG Admin Kit
|--------------------------------------------------------------------------
|
| Every value the admin module needs at runtime lives here. Nothing in the
| admin code reads env() directly -- it reads config('admin-kit.*') -- so
| the whole module can be lifted into a package (Phase 20) and republished
| with a single config file.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Panel version
    |----------------------------------------------------------------------
    |
    | Shown in the footer. Becomes the package version at Phase 20.
    |
    */

    'version' => '1.0.0-dev',

    /*
    |----------------------------------------------------------------------
    | Branding
    |----------------------------------------------------------------------
    |
    | Fallbacks used until the Settings module (Phase 13) takes over. Once
    | settings exist, SettingsManager overrides these at runtime.
    |
    */

    'brand' => [
        'name' => env('ADMIN_BRAND_NAME', env('APP_NAME', 'NG Admin')),
        'short_name' => env('ADMIN_BRAND_SHORT_NAME', 'NG'),
        'tagline' => env('ADMIN_BRAND_TAGLINE', 'Admin Panel'),

        // Public-relative paths. Null falls back to the initials mark.
        'logo' => env('ADMIN_BRAND_LOGO'),
        'logo_collapsed' => env('ADMIN_BRAND_LOGO_COLLAPSED'),
        'favicon' => env('ADMIN_BRAND_FAVICON'),
    ],

    /*
    |----------------------------------------------------------------------
    | Routing
    |----------------------------------------------------------------------
    */

    'route' => [
        'prefix' => env('ADMIN_ROUTE_PREFIX', 'admin'),
        'name_prefix' => 'admin.',

        // Applied to every admin route, guests included.
        'middleware' => ['web'],

        // Applied on top of the above once authentication is required.
        // auth.session (scoped to the admin group only, not the whole "web"
        // group) is what makes "log out other devices" (Phase 9) actually
        // terminate other sessions rather than silently doing nothing --
        // Auth::logoutOtherDevices() only rehashes the password; this
        // middleware is what notices the mismatch on a stale session's next
        // request and invalidates it.
        'auth_middleware' => ['auth', 'auth.session'],

        'home' => 'admin.dashboard',
        'login' => 'admin.login',
    ],

    /*
    |----------------------------------------------------------------------
    | Authentication guard
    |----------------------------------------------------------------------
    |
    | Shared with spatie/laravel-permission (Phase 7). Keep both in sync.
    |
    */

    'guard' => env('ADMIN_GUARD', 'web'),

    /*
    |----------------------------------------------------------------------
    | Roles & permissions
    |----------------------------------------------------------------------
    |
    | 'protected_role' ships with every install (RolePermissionSeeder) and
    | always carries every permission -- it can't be deleted or renamed
    | through the admin UI, so there's never a way to lock every admin out
    | of the one role that could undo a mistake.
    |
    */

    'roles' => [
        'protected_role' => env('ADMIN_PROTECTED_ROLE', 'Super Admin'),
    ],

    /*
    |----------------------------------------------------------------------
    | Authentication
    |----------------------------------------------------------------------
    |
    | The admin panel authenticates through its own controllers rather than
    | Fortify/Breeze, so these knobs are independent of the rest of the app.
    |
    */

    'auth' => [
        // Password broker from config/auth.php. Null uses the default.
        'password_broker' => env('ADMIN_PASSWORD_BROKER'),

        // Offer (and honour) the "remember me" checkbox on the login form.
        'remember' => env('ADMIN_AUTH_REMEMBER', true),

        // Login throttling, keyed on email + IP.
        'throttle' => [
            'max_attempts' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => (int) env('ADMIN_LOGIN_DECAY_MINUTES', 1),
        ],

        // Two-factor challenge. The hook exists in AdminAuthController; wiring
        // it to laravel/fortify is a later phase, so this stays false for now.
        'two_factor' => env('ADMIN_TWO_FACTOR', false),

        // Write login/logout/failure lines to the log. Phase 18 redirects this
        // at spatie/laravel-activitylog instead.
        'log_attempts' => env('ADMIN_LOG_LOGIN_ATTEMPTS', true),
    ],

    /*
    |----------------------------------------------------------------------
    | Theme
    |----------------------------------------------------------------------
    |
    | Tokens are emitted as CSS custom properties on :root as --nk-<key>,
    | so a consuming project re-brands the panel by overriding this array
    | -- no stylesheet edits, no rebuild.
    |
    */

    'theme' => [
        // 'split' or 'centered' -- consumed by the auth screens (Phase 2).
        'auth_layout' => env('ADMIN_AUTH_LAYOUT', 'split'),

        // 'expanded' or 'collapsed' -- initial sidebar state.
        'sidebar_default' => env('ADMIN_SIDEBAR_DEFAULT', 'expanded'),

        // 'light', 'dark' or 'system'.
        'color_scheme' => env('ADMIN_COLOR_SCHEME', 'system'),

        'tokens' => [
            'primary' => '#4f46e5',
            'primary-hover' => '#4338ca',
            'primary-soft' => '#eef2ff',
            'primary-contrast' => '#ffffff',

            'sidebar-bg' => '#111827',
            'sidebar-fg' => '#9ca3af',
            'sidebar-fg-active' => '#ffffff',
            'sidebar-active-bg' => '#1f2937',
            'sidebar-width' => '264px',
            'sidebar-width-collapsed' => '76px',

            'topbar-bg' => '#ffffff',
            'topbar-fg' => '#374151',
            'topbar-height' => '62px',

            'body-bg' => '#f4f5f7',
            'surface' => '#ffffff',
            'fg' => '#1f2937',
            'fg-muted' => '#6b7280',
            'border' => '#e5e7eb',

            'success' => '#16a34a',
            'warning' => '#d97706',
            'danger' => '#dc2626',
            'info' => '#0284c7',

            'radius' => '10px',
            'radius-sm' => '6px',
            'font-sans' => "'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
        ],

        // Overrides applied only when the panel renders dark.
        'tokens_dark' => [
            'primary-soft' => '#1e1b4b',
            'topbar-bg' => '#111827',
            'topbar-fg' => '#e5e7eb',
            'body-bg' => '#0b1120',
            'surface' => '#111827',
            'fg' => '#f3f4f6',
            'fg-muted' => '#9ca3af',
            'border' => '#1f2937',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Sidebar menu
    |----------------------------------------------------------------------
    |
    | driver=config reads the tree below. driver=database reads the menus
    | table instead (Phase 7) -- the MenuManager contract is identical, so
    | switching drivers changes nothing in the views.
    |
    | Item keys: label, icon, route (+ route_params), url, permission,
    |            active (extra route patterns), badge, children.
    |
    */

    'menu' => [
        'driver' => env('ADMIN_MENU_DRIVER', 'config'),

        'items' => [
            [
                'label' => 'Dashboard',
                'icon' => 'dashboard',
                'route' => 'admin.dashboard',
            ],
            [
                'label' => 'Users',
                'icon' => 'users',
                'route' => 'admin.users.index',
                'permission' => 'users.view',
            ],
            [
                'label' => 'Pages',
                'icon' => 'file-text',
                'route' => 'admin.pages.index',
                'permission' => 'pages.view',
            ],
            [
                'label' => 'Media',
                'icon' => 'image',
                'route' => 'admin.media.index',
                'permission' => 'media.manage',
            ],
            [
                'label' => 'Contact Messages',
                'icon' => 'mail',
                'route' => 'admin.contact-messages.index',
                'permission' => 'contact-messages.view',
            ],
            [
                'label' => 'Backups',
                'icon' => 'database',
                'route' => 'admin.backups.index',
                'permission' => 'backups.manage',
            ],
            [
                'label' => 'Settings',
                'icon' => 'settings',
                'children' => [
                    [
                        'label' => 'General',
                        'route' => 'admin.settings.edit',
                        'permission' => 'settings.manage',
                    ],
                    [
                        'label' => 'Integrations',
                        'route' => 'admin.settings.integrations.edit',
                        'permission' => 'settings.integrations.manage',
                    ],
                    [
                        'label' => 'Menus',
                        'route' => 'admin.menus.index',
                        'permission' => 'menus.manage',
                    ],
                    [
                        'label' => 'Roles',
                        'route' => 'admin.roles.index',
                        'permission' => 'roles.manage',
                    ],
                    [
                        'label' => 'Permissions',
                        'route' => 'admin.permissions.index',
                        'permission' => 'permissions.manage',
                    ],
                    [
                        'label' => 'Activity Log',
                        'route' => 'admin.activity-log.index',
                        'permission' => 'activity-log.view',
                    ],
                ],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Dashboard widgets (Phase 10)
    |----------------------------------------------------------------------
    |
    | Each entry is an App\Support\Admin\AdminWidget FQCN, rendered on the
    | dashboard in this order (drag-to-reorder overrides this per browser,
    | see initWidgetGrid() in admin.js). A consuming project registers its
    | own widgets here alongside -- or instead of -- these.
    |
    | "Recent activity" and "latest contact messages" widgets are deferred:
    | AuthLogger only writes to the log file until Phase 18 (Spatie
    | activitylog) gives it a queryable table, and ContactMessage doesn't
    | exist until Phase 16.
    |
    */

    'widgets' => [
        StatCardsWidget::class,
        UsersGrowthChartWidget::class,
    ],

    /*
    |----------------------------------------------------------------------
    | CMS (Phase 11)
    |----------------------------------------------------------------------
    |
    | Page::template must be one of these keys (PageRequest validates it).
    | This admin kit only manages the content -- no public route renders a
    | Page yet, so these keys are metadata for whichever consuming project
    | builds that renderer, not something this config wires up itself.
    |
    */

    'cms' => [
        'templates' => [
            'default' => 'Default',
            'full-width' => 'Full width',
            'landing' => 'Landing page',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | SEO (Phase 14)
    |----------------------------------------------------------------------
    |
    | Same "admin manages the content, a consuming project renders it"
    | boundary as CMS above -- {slug} is swapped for Page::slug when
    | GenerateSitemap builds public/sitemap.xml. Override this once a
    | project adds its own public Page route.
    |
    */

    'seo' => [
        'page_url_pattern' => '/{slug}',
    ],
];
