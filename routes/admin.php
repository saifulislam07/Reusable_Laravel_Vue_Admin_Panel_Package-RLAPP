<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Settings\IntegrationSettingsController;
use App\Http\Controllers\Admin\Settings\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php. Everything the admin panel serves lives
| here so Phase 20 can move the file into the package untouched.
|
*/

Route::prefix((string) config('admin-kit.route.prefix'))
    ->name((string) config('admin-kit.route.name_prefix'))
    ->middleware((array) config('admin-kit.route.middleware'))
    ->group(function (): void {

        Route::middleware('guest')->group(function (): void {
            Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
            Route::get('forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
            Route::get('reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');

            // Per-account throttling lives in LoginRequest; this limiter is a
            // coarse per-IP flood guard on top of it.
            Route::middleware('throttle:admin-login')->group(function (): void {
                Route::post('login', [AdminAuthController::class, 'login'])->name('login.attempt');
                Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
                Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
            });
        });

        Route::middleware((array) config('admin-kit.route.auth_middleware'))
            ->group(function (): void {
                Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

                Route::get('/', DashboardController::class)->name('dashboard');

                // Self-service: every authenticated admin manages their own
                // account here, whatever their role -- deliberately not
                // permission-gated, unlike everything else below.
                Route::prefix('profile')->name('profile.')->group(function (): void {
                    Route::get('/', [ProfileController::class, 'edit'])->name('edit');
                    Route::put('/', [ProfileController::class, 'update'])->name('update');
                    Route::post('logout-other-devices', [ProfileController::class, 'logoutOtherDevices'])->name('logout-other-devices');
                    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
                });

                // Self-service, same shape as Profile above -- every admin
                // reads and clears their own notifications, whatever their
                // role.
                Route::prefix('notifications')->name('notifications.')->group(function (): void {
                    Route::get('/', [NotificationController::class, 'index'])->name('index');
                    Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
                    Route::post('{notification}/read', [NotificationController::class, 'read'])->name('read');
                    Route::post('{notification}/unread', [NotificationController::class, 'unread'])->name('unread');
                });

                // Permission-wise: each action requires its own permission
                // (view/create/update/delete) rather than one blanket check
                // for the whole resource, so a role like Editor can see the
                // list without being able to create or remove anyone.
                Route::prefix('users')->name('users.')->group(function (): void {
                    Route::middleware('checkpermission:users.view')->group(function (): void {
                        Route::get('/', [UserController::class, 'index'])->name('index');
                    });

                    Route::middleware('checkpermission:users.create')->group(function (): void {
                        Route::get('create', [UserController::class, 'create'])->name('create');
                        Route::post('/', [UserController::class, 'store'])->name('store');
                    });

                    Route::middleware('checkpermission:users.update')->group(function (): void {
                        Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
                        Route::put('{user}', [UserController::class, 'update'])->name('update');
                    });

                    Route::middleware('checkpermission:users.delete')->group(function (): void {
                        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
                        Route::post('bulk-delete', [UserController::class, 'bulkDelete'])->name('bulk-destroy');
                    });
                });

                // Read-only browse/select, deliberately not permission-gated
                // beyond plain auth -- the picker is used from *inside*
                // already-gated contexts (Pages, self-service Profile
                // avatar), not a door into the library management page
                // below. See MediaController::picker()'s docblock.
                Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');

                Route::prefix('media')->name('media.')->middleware('checkpermission:media.manage')->group(function (): void {
                    Route::get('/', [MediaController::class, 'index'])->name('index');
                    Route::post('/', [MediaController::class, 'store'])->name('store');
                    Route::delete('{media}', [MediaController::class, 'destroy'])->name('destroy');
                    Route::post('bulk-delete', [MediaController::class, 'bulkDelete'])->name('bulk-destroy');
                    Route::post('folders', [MediaController::class, 'storeFolder'])->name('folders.store');
                    Route::delete('folders/{folder}', [MediaController::class, 'destroyFolder'])->name('folders.destroy');
                });

                // Same per-action permission shape as Users (Phase 8).
                Route::prefix('pages')->name('pages.')->group(function (): void {
                    Route::middleware('checkpermission:pages.view')->group(function (): void {
                        Route::get('/', [PageController::class, 'index'])->name('index');
                    });

                    Route::middleware('checkpermission:pages.create')->group(function (): void {
                        Route::get('create', [PageController::class, 'create'])->name('create');
                        Route::post('/', [PageController::class, 'store'])->name('store');
                    });

                    Route::middleware('checkpermission:pages.update')->group(function (): void {
                        Route::get('{page}/edit', [PageController::class, 'edit'])->name('edit');
                        Route::put('{page}', [PageController::class, 'update'])->name('update');
                    });

                    Route::middleware('checkpermission:pages.delete')->group(function (): void {
                        Route::delete('{page}', [PageController::class, 'destroy'])->name('destroy');
                        Route::post('bulk-delete', [PageController::class, 'bulkDelete'])->name('bulk-destroy');
                    });
                });

                // view/manage/delete rather than Pages' four-way split --
                // messages aren't "created" by an admin, so there's no
                // create action to gate.
                Route::prefix('contact-messages')->name('contact-messages.')->group(function (): void {
                    Route::middleware('checkpermission:contact-messages.view')->group(function (): void {
                        Route::get('/', [ContactMessageController::class, 'index'])->name('index');
                        Route::get('{contactMessage}', [ContactMessageController::class, 'show'])->name('show');
                    });

                    Route::middleware('checkpermission:contact-messages.manage')->group(function (): void {
                        Route::post('{contactMessage}/reply', [ContactMessageController::class, 'reply'])->name('reply');
                        Route::post('bulk-mark-read', [ContactMessageController::class, 'bulkMarkRead'])->name('bulk-mark-read');
                        Route::post('bulk-archive', [ContactMessageController::class, 'bulkArchive'])->name('bulk-archive');
                    });

                    Route::middleware('checkpermission:contact-messages.delete')->group(function (): void {
                        Route::delete('{contactMessage}', [ContactMessageController::class, 'destroy'])->name('destroy');
                        Route::post('bulk-delete', [ContactMessageController::class, 'bulkDelete'])->name('bulk-destroy');
                    });
                });

                Route::prefix('settings')
                    ->name('settings.')
                    ->middleware('checkpermission:settings.manage')
                    ->group(function (): void {
                        Route::get('/', [SettingsController::class, 'edit'])->name('edit');
                        Route::post('general', [SettingsController::class, 'updateGeneral'])->name('general.update');
                        Route::post('contact', [SettingsController::class, 'updateContact'])->name('contact.update');
                        Route::post('social', [SettingsController::class, 'updateSocial'])->name('social.update');
                        Route::post('seo', [SettingsController::class, 'updateSeo'])->name('seo.update');
                        Route::post('system', [SettingsController::class, 'updateSystem'])->name('system.update');
                    });

                Route::prefix('settings/integrations')
                    ->name('settings.integrations.')
                    ->middleware('checkpermission:settings.integrations.manage')
                    ->group(function (): void {
                        Route::get('/', [IntegrationSettingsController::class, 'edit'])->name('edit');
                        Route::post('smtp', [IntegrationSettingsController::class, 'updateSmtp'])->name('smtp.update');
                        Route::post('smtp/test', [IntegrationSettingsController::class, 'testSmtp'])->name('smtp.test');
                        Route::post('google-analytics', [IntegrationSettingsController::class, 'updateGoogleAnalytics'])->name('google-analytics.update');
                        Route::post('google-search-console', [IntegrationSettingsController::class, 'updateGoogleSearchConsole'])->name('google-search-console.update');
                        Route::post('facebook-messenger', [IntegrationSettingsController::class, 'updateFacebookMessenger'])->name('facebook-messenger.update');
                        Route::post('whatsapp', [IntegrationSettingsController::class, 'updateWhatsapp'])->name('whatsapp.update');
                    });

                Route::prefix('menus')->name('menus.')->middleware('checkpermission:menus.manage')->group(function (): void {
                    Route::get('/', [MenuController::class, 'index'])->name('index');
                    Route::get('create', [MenuController::class, 'create'])->name('create');
                    Route::post('/', [MenuController::class, 'store'])->name('store');
                    Route::post('reorder', [MenuController::class, 'reorder'])->name('reorder');
                    Route::get('{menu}/edit', [MenuController::class, 'edit'])->name('edit');
                    Route::put('{menu}', [MenuController::class, 'update'])->name('update');
                    Route::delete('{menu}', [MenuController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('roles')->name('roles.')->middleware('checkpermission:roles.manage')->group(function (): void {
                    Route::get('/', [RoleController::class, 'index'])->name('index');
                    Route::get('create', [RoleController::class, 'create'])->name('create');
                    Route::post('/', [RoleController::class, 'store'])->name('store');
                    Route::post('bulk-delete', [RoleController::class, 'bulkDelete'])->name('bulk-destroy');
                    Route::get('{role}/edit', [RoleController::class, 'edit'])->name('edit');
                    Route::put('{role}', [RoleController::class, 'update'])->name('update');
                    Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('permissions')->name('permissions.')->middleware('checkpermission:permissions.manage')->group(function (): void {
                    Route::get('/', [PermissionController::class, 'index'])->name('index');
                    Route::get('create', [PermissionController::class, 'create'])->name('create');
                    Route::post('/', [PermissionController::class, 'store'])->name('store');
                    Route::post('bulk-delete', [PermissionController::class, 'bulkDelete'])->name('bulk-destroy');
                    Route::delete('{permission}', [PermissionController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('activity-log')->name('activity-log.')->middleware('checkpermission:activity-log.view')->group(function (): void {
                    Route::get('/', [ActivityLogController::class, 'index'])->name('index');
                    Route::get('{activity}', [ActivityLogController::class, 'show'])->name('show');
                });

                Route::prefix('backups')->name('backups.')->middleware('checkpermission:backups.manage')->group(function (): void {
                    Route::get('/', [BackupController::class, 'index'])->name('index');
                    Route::post('run', [BackupController::class, 'run'])->name('run');
                    Route::get('{disk}/{file}/download', [BackupController::class, 'download'])->name('download');
                    Route::delete('{disk}/{file}', [BackupController::class, 'destroy'])->name('destroy');
                });
            });
    });
