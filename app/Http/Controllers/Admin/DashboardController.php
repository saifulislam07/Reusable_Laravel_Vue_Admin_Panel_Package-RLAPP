<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Support\Admin\WidgetManager;
use Illuminate\Contracts\View\View;

class DashboardController extends AdminController
{
    /**
     * The admin landing page: a config-driven grid of AdminWidget
     * instances (Phase 10). Registered in config('admin-kit.widgets').
     */
    public function __invoke(WidgetManager $widgets): View
    {
        return view('admin::pages.dashboard', [
            'widgets' => $widgets->all(),
        ]);
    }
}
