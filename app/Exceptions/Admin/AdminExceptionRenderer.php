<?php

declare(strict_types=1);

namespace App\Exceptions\Admin;

use App\Support\Admin\AdminKitRoutes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders branded error pages, but only for admin-panel requests.
 *
 * Registered from bootstrap/app.php. Returning null hands the exception back
 * to Laravel untouched, which is what keeps the public app free to have its
 * own error handling.
 */
class AdminExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?Response
    {
        if (! AdminKitRoutes::isAdminRequest($request)) {
            return null;
        }

        // API-ish clients get Laravel's JSON problem response, not HTML.
        if ($request->expectsJson()) {
            return null;
        }

        $status = $this->statusFor($e);

        if ($status === null) {
            return null;
        }

        // Keep the stack-trace page in local development: swallowing a 500
        // behind a pretty screen while debugging is actively unhelpful.
        if ($status >= 500 && config('app.debug')) {
            return null;
        }

        return response()->view(
            $this->viewFor($status),
            $this->dataFor($status, $e),
            $status,
            $e instanceof HttpExceptionInterface ? $e->getHeaders() : [],
        );
    }

    /**
     * The HTTP status this exception should render as, or null to let Laravel
     * handle it its own way.
     *
     * Render callbacks run before Laravel maps exceptions to responses, so the
     * unmapped types are matched here explicitly.
     */
    protected function statusFor(Throwable $e): ?int
    {
        return match (true) {
            // These are redirects, not error pages.
            $e instanceof AuthenticationException,
            $e instanceof ValidationException,
            $e instanceof HttpResponseException => null,

            $e instanceof AuthorizationException => $e->status() ?? 403,
            $e instanceof ModelNotFoundException,
            $e instanceof RecordsNotFoundException => 404,
            $e instanceof TokenMismatchException => 419,

            $e instanceof HttpExceptionInterface => $e->getStatusCode(),

            default => 500,
        };
    }

    /**
     * A per-status view when one exists, otherwise the shared fallback.
     */
    protected function viewFor(int $status): string
    {
        return View::exists("admin::errors.{$status}")
            ? "admin::errors.{$status}"
            : 'admin::errors.fallback';
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataFor(int $status, Throwable $e): array
    {
        return [
            'status' => $status,
            'exception' => $e,
            'maintenanceMessage' => $status === 503
                ? setting('maintenance_message')
                : null,
        ];
    }
}
