<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasDataTable;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit trail (Phase 18) over the activity_log table --
 * everything spatie/laravel-activitylog records, whether it came from a
 * model's LogsActivity trait (User/Page/ContactMessage/Setting) or a manual
 * activity() call (RoleController/PermissionController/AuthLogger). One
 * listing + detail view for all of it, since it's all the same shape.
 */
class ActivityLogController extends AdminController
{
    use HasDataTable;

    public function index(Request $request): View
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if (filled($causerId = $request->query('causer_id'))) {
            $query->where('causer_id', $causerId)->where('causer_type', User::class);
        }

        if (filled($subjectType = $request->query('subject_type'))) {
            $query->where('subject_type', $subjectType);
        }

        if (filled($from = $request->query('from'))) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (filled($to = $request->query('to'))) {
            $query->whereDate('created_at', '<=', $to);
        }

        $activities = $this->paginateDataTable(
            $query,
            searchable: ['description'],
            sortable: ['created_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        return view('admin::pages.activity-log.index', [
            'activities' => $activities,
            'causerOptions' => $this->causerOptions(),
            'subjectTypeOptions' => $this->subjectTypeOptions(),
            'filters' => $request->only(['causer_id', 'subject_type', 'from', 'to']),
        ]);
    }

    public function show(Activity $activity): View
    {
        $changes = $activity->attribute_changes ?? collect();
        $attributes = (array) ($changes['attributes'] ?? []);
        $old = (array) ($changes['old'] ?? []);

        $diff = [];
        foreach (array_unique([...array_keys($old), ...array_keys($attributes)]) as $key) {
            $diff[$key] = ['old' => $old[$key] ?? null, 'new' => $attributes[$key] ?? null];
        }

        return view('admin::pages.activity-log.show', [
            'activity' => $activity,
            'diff' => $diff,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function causerOptions(): array
    {
        $ids = Activity::query()
            ->whereNotNull('causer_id')
            ->where('causer_type', User::class)
            ->distinct()
            ->pluck('causer_id');

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function subjectTypeOptions(): array
    {
        return Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type): array => [$type => Str::headline(class_basename($type))])
            ->sort()
            ->all();
    }
}
