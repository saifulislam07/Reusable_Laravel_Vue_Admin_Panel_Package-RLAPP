<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Illuminate\Support\Facades\Route;

/**
 * Named admin routes a menu item can safely point at -- GET routes under
 * the admin name prefix that take no parameters, since a menu link has to
 * resolve to a fixed URL with nothing to fill a {parameter} in at render
 * time.
 */
class MenuRouteOptions
{
    /**
     * @return array<string, string> route name => route name (a plain list
     *                               would work just as well; kept as a map
     *                               so the <select> can use it directly as
     *                               value => label without a second pass)
     */
    public static function all(): array
    {
        $prefix = (string) config('admin-kit.route.name_prefix');

        $names = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, string $name): bool => str_starts_with($name, $prefix)
                && in_array('GET', $route->methods(), true)
                && $route->parameterNames() === []
            )
            ->keys()
            ->sort()
            ->values();

        return $names->combine($names)->all();
    }
}
