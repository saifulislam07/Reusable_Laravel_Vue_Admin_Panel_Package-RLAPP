<?php

use App\Exceptions\Admin\AdminExceptionRenderer;
use App\Http\Middleware\Admin\CheckPermission;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Admin\AdminKitRoutes;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    // Off, matching this codebase's explicit-registration convention
    // everywhere else (routes, providers, observers) -- with it on
    // (Laravel's default), Laravel auto-wires any app/Listeners method
    // whose single parameter is a typed Event, which double-registers
    // App\Listeners\Admin\BackupEventSubscriber's handleSuccess()/
    // handleFailure() on top of its own explicit Event::subscribe() call
    // in AppServiceProvider::bootAdminKit() -- silently duplicating every
    // backup notification (found via a live smoke test, not caught by the
    // automated suite, which never asserted an exact notification count).
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Admin panel routes -- self-contained, see routes/admin.php.
            require __DIR__.'/../routes/admin.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // The admin panel has its own front door, so unauthenticated admin
        // requests must not be bounced to the public app's login screen.
        $middleware->redirectGuestsTo(
            fn (Request $request): string => AdminKitRoutes::isAdminRequest($request)
                ? AdminKitRoutes::login()
                : route('login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request): string => AdminKitRoutes::isAdminRequest($request)
                ? AdminKitRoutes::home()
                : '/dashboard',
        );

        $middleware->web(
            // Runs before anything else so a maintenance-mode visitor
            // never reaches session/auth/Inertia handling for a response
            // they're not going to see anyway.
            prepend: [CheckMaintenanceMode::class],
            append: [
                HandleAppearance::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        $middleware->alias([
            'checkpermission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded error pages for the admin panel only -- see the renderer,
        // which returns null for every non-admin request.
        $exceptions->render(new AdminExceptionRenderer);
    })->create();
