<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Admin\AdminKitRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the public site behind Settings > System's maintenance toggle
 * (Phase 15). The admin panel itself is always exempt -- an admin has to
 * be able to reach /admin to turn maintenance mode back off -- and so is
 * any IP on the allow-list.
 *
 * Renders admin::errors.503 directly rather than throwing and letting
 * AdminExceptionRenderer handle it: that renderer only brands *admin*
 * requests (see its own docblock), and the whole point here is showing
 * the same page to public visitors.
 */
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AdminKitRoutes::isAdminRequest($request)) {
            return $next($request);
        }

        if (! setting('maintenance_mode', false)) {
            return $next($request);
        }

        /** @var list<string> $allowedIps */
        $allowedIps = (array) setting('maintenance_allowed_ips', []);

        if (in_array($request->ip(), $allowedIps, true)) {
            return $next($request);
        }

        return response()->view('admin::errors.503', [
            'status' => 503,
            'maintenanceMessage' => setting('maintenance_message'),
        ], 503);
    }
}
