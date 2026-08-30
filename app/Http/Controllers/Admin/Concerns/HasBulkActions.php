<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bulk-delete handling for admin listing controllers. Requires the flash
 * helpers from AdminController, so this is meant to be composed on top of
 * it, not used standalone.
 *
 * The confirmation prompt is handled client-side (Phase 4's
 * data-nk-confirm), not here -- by the time this trait runs, the visitor has
 * already agreed.
 */
trait HasBulkActions
{
    /**
     * Delete the rows named in the request's `ids[]` field.
     *
     * @param  Builder  $query  Pre-scoped by the caller -- e.g. excluding
     *                          rows that must never be bulk-deleted, such
     *                          as the signed-in user's own account.
     */
    protected function bulkDestroy(Request $request, Builder $query, string $redirectRoute): RedirectResponse
    {
        $ids = $this->bulkIds($request);

        if ($ids === []) {
            $this->flashError(__('Select at least one row.'));

            return redirect()->route($redirectRoute);
        }

        $deleted = (clone $query)->whereKey($ids)->delete();

        if ($deleted > 0) {
            $this->flashSuccess(trans_choice(
                '{1}1 record deleted.|[2,*]:count records deleted.',
                $deleted,
                ['count' => $deleted],
            ));
        }

        $skipped = count($ids) - $deleted;

        if ($skipped > 0) {
            $this->flashWarning(trans_choice(
                '{1}1 selected row could not be deleted.|[2,*]:count selected rows could not be deleted.',
                $skipped,
                ['count' => $skipped],
            ));
        }

        return redirect()->route($redirectRoute);
    }

    /**
     * @return list<int|string>
     */
    protected function bulkIds(Request $request): array
    {
        $ids = (array) $request->input('ids', []);

        return array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''),
        ));
    }
}
