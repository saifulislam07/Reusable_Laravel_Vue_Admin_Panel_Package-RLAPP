<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Http\Request;

/**
 * Small helpers for code that has to reason about the admin route group from
 * outside it -- chiefly bootstrap/app.php, which runs before the container is
 * a comfortable place to resolve things from.
 */
final class AdminKitRoutes
{
    /**
     * Does this request belong to the admin panel?
     */
    public static function isAdminRequest(Request $request): bool
    {
        $prefix = trim((string) config('admin-kit.route.prefix'), '/');

        if ($prefix === '') {
            return true;
        }

        return $request->is($prefix, $prefix.'/*');
    }

    public static function login(): string
    {
        return route((string) config('admin-kit.route.login'));
    }

    public static function home(): string
    {
        return route((string) config('admin-kit.route.home'));
    }
}
