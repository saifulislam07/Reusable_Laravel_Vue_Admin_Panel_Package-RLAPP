<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Request;

/**
 * Turns any Eloquent query into a searchable, sortable, paginated listing
 * driven entirely by the query string -- no JS required to page through
 * results, matching the "AJAX-free first" convention from the plan.
 *
 * Every admin listing controller composes this the same way:
 *
 *   $users = $this->paginateDataTable(
 *       User::query(),
 *       searchable: ['name', 'email'],
 *       sortable: ['name', 'email', 'created_at'],
 *       defaultSort: 'created_at',
 *       defaultDirection: 'desc',
 *   );
 *
 * The matching <x-admin::datatable> component reads its sort/search/page
 * state back out of the request, so the two never need to be told about
 * each other's internals -- they agree only on query-string key names, and
 * $name namespaces those keys when a page needs more than one table.
 */
trait HasDataTable
{
    /**
     * @param  list<string>  $searchable  Columns searched with a multi-column OR LIKE. Empty disables search.
     * @param  list<string>  $sortable  Allow-list of columns a visitor may sort by. Anything else falls back to $defaultSort.
     * @return LengthAwarePaginator<int, *>
     */
    protected function paginateDataTable(
        Builder $query,
        array $searchable = [],
        array $sortable = [],
        string $defaultSort = '',
        string $defaultDirection = 'asc',
        int $perPage = 15,
        string $name = '',
    ): LengthAwarePaginator {
        $key = static fn (string $suffix): string => $name !== '' ? "{$name}_{$suffix}" : $suffix;

        $this->applyDataTableSearch($query, $searchable, (string) Request::query($key('q'), ''));
        $this->applyDataTableSort($query, $sortable, $defaultSort, $defaultDirection, $key);

        $requestedPerPage = (int) Request::query($key('per_page'), $perPage);
        $perPage = $requestedPerPage > 0 ? min($requestedPerPage, 100) : $perPage;

        return $query
            ->paginate($perPage, ['*'], $key('page'))
            ->withQueryString();
    }

    /**
     * @param  list<string>  $searchable
     */
    protected function applyDataTableSearch(Builder $query, array $searchable, string $term): void
    {
        if ($searchable === [] || trim($term) === '') {
            return;
        }

        // A literal "%" or "_" typed by the visitor is matched as a SQL
        // wildcard rather than the character itself. That's the same
        // trade-off every plain search box makes (it is not a security
        // concern -- $term is still bound as a parameter, never concatenated
        // into SQL); avoiding it needs a LIKE ... ESCAPE clause, which has no
        // fluent-builder API and would mean raw SQL built from a
        // caller-supplied column name.
        $query->where(function (Builder $inner) use ($searchable, $term): void {
            foreach ($searchable as $column) {
                $inner->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    /**
     * @param  list<string>  $sortable
     * @param  callable(string): string  $key
     */
    protected function applyDataTableSort(Builder $query, array $sortable, string $defaultSort, string $defaultDirection, callable $key): void
    {
        if ($sortable === []) {
            return;
        }

        $column = (string) Request::query($key('sort'), $defaultSort);

        // An unrecognised column falls back to the default rather than
        // erroring or silently sorting by an unintended column.
        if (! in_array($column, $sortable, true)) {
            $column = in_array($defaultSort, $sortable, true) ? $defaultSort : $sortable[0];
        }

        if ($column === '') {
            return;
        }

        $direction = Request::query($key('direction'), $defaultDirection);
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
