<?php

declare(strict_types=1);

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * checkpermission:menus.manage
 *
 * A thin wrapper over $user->can() rather than reaching for Spatie's own
 * `permission` middleware directly: this is the one place a project can
 * change what "denied" means (a redirect instead of a 403, a different
 * guard lookup, ...) without patching a vendor package.
 *
 * The Super Admin bypass lives in AppServiceProvider's Gate::before() hook,
 * not here -- so it applies uniformly to $user->can() everywhere (menu
 * visibility included), not just requests that pass through this
 * middleware.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user((string) config('admin-kit.guard'));

        abort_unless($user?->can($permission), 403);

        return $next($request);
    }
}
