<?php

declare(strict_types=1);

namespace App\Support\Admin\Widgets;

use App\Models\User;
use App\Support\Admin\AdminWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;

/**
 * New users per month, last 6 months. Grouped in PHP rather than with a
 * driver-specific DATE_FORMAT/strftime SQL function, so this behaves
 * identically on every database the panel might ship against (MySQL in
 * this project's .env, SQLite in tests).
 */
class UsersGrowthChartWidget implements AdminWidget
{
    private const MONTHS = 6;

    public function key(): string
    {
        return 'users-growth-chart';
    }

    public function title(): string
    {
        return __('New users');
    }

    public function size(): string
    {
        return 'lg';
    }

    public function render(): View
    {
        $months = collect(range(self::MONTHS - 1, 0))
            ->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $counts = User::query()
            ->where('created_at', '>=', $months->first())
            ->get(['created_at'])
            ->countBy(fn (User $user) => $user->created_at?->format('Y-m') ?? 'unknown');

        return ViewFacade::make('admin::widgets.users-growth-chart', [
            'labels' => $months->map(fn ($month) => $month->translatedFormat('M'))->all(),
            'data' => $months->map(fn ($month) => $counts->get($month->format('Y-m'), 0))->all(),
        ]);
    }
}
