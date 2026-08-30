<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

/**
 * Base class for every admin controller.
 *
 * Holds the flash-message vocabulary the toaster (Phase 4) listens to, so
 * controllers never hand-roll session keys.
 */
abstract class AdminController extends Controller
{
    protected function flashSuccess(string $message): void
    {
        session()->flash('success', $message);
    }

    protected function flashError(string $message): void
    {
        session()->flash('error', $message);
    }

    protected function flashWarning(string $message): void
    {
        session()->flash('warning', $message);
    }

    protected function flashInfo(string $message): void
    {
        session()->flash('info', $message);
    }

    /**
     * Queue an extra toast.
     *
     * The single-value helpers above hold one message per type; use this when
     * a response needs to say several things at once.
     *
     * @param  'success'|'danger'|'warning'|'info'  $type
     */
    protected function flashToast(string $type, string $message): void
    {
        $queued = (array) session()->get('nk_toasts', []);
        $queued[] = ['type' => $type, 'message' => $message];

        session()->flash('nk_toasts', $queued);
    }

    /**
     * Redirect to a named admin route with a success toast attached.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function redirectWithSuccess(string $route, string $message, array $parameters = []): RedirectResponse
    {
        $this->flashSuccess($message);

        return Redirect::route($route, $parameters);
    }

    /**
     * Back to the admin landing page.
     */
    protected function redirectHome(): RedirectResponse
    {
        return Redirect::route((string) config('admin-kit.route.home'));
    }
}
