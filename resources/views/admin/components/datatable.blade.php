{{--
    <x-admin::datatable
        :headers="[
            ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
            ['key' => 'email', 'label' => __('Email'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Status'), 'format' => fn ($row) => '<span class=\"nk-badge\">'.e($row->status).'</span>'],
        ]"
        :rows="$users"
        :bulk-actions="[
            ['key' => 'delete', 'label' => __('Delete selected'), 'variant' => 'danger', 'url' => route('admin.users.bulk-destroy'), 'confirm' => true],
        ]"
    />

    Props:
      headers        list<array{key: string, label: string, sortable?: bool, align?: 'left'|'right'|'center', format?: callable}>
      rows           LengthAwarePaginator -- what paginateDataTable() returns
      rowKey         attribute used for the checkbox value, default 'id'
      name           query-string namespace; must match paginateDataTable()'s $name
      search         show the search box, default true when $rows->total() might use it
      searchPlaceholder
      bulkActions    list<array{key: string, label: string, url: string, variant?: string, confirm?: bool|array}>
      emptyMessage

    A header's `format` receives the row and returns raw HTML -- it is
    responsible for escaping anything it interpolates (use e()). Without
    `format`, the cell is `data_get($row, $header['key'])`, auto-escaped.
--}}
@props([
    'headers',
    'rows',
    'rowKey' => 'id',
    'name' => '',
    'search' => true,
    'searchPlaceholder' => null,
    'bulkActions' => [],
    'emptyMessage' => null,
])

@php
    $nkKey = fn (string $suffix): string => $name !== '' ? "{$name}_{$suffix}" : $suffix;
    $nkSort = (string) request($nkKey('sort'), '');
    $nkDirection = request($nkKey('direction'), 'asc') === 'desc' ? 'desc' : 'asc';
    $nkSearchTerm = (string) request($nkKey('q'), '');
    $nkHasBulk = $bulkActions !== [];
    $nkColspan = count($headers) + ($nkHasBulk ? 1 : 0);
    // Random rather than $name-derived: guarantees the bulk form's id is
    // unique even across two unnamed tables on the same page.
    $nkBulkFormId = 'nk-datatable-bulk-'.Str::random(8);

    // Every other active query param is carried along as a hidden field so
    // search/sort links never drop the visitor's current page state.
    $nkCarryParams = request()->except([$nkKey('q'), $nkKey('page')]);
@endphp

<div
    class="nk-datatable"
    x-data="{
        checked: [],
        rowIds: @js(collect($rows->items())->map(fn ($row) => (string) data_get($row, $rowKey))->all()),
        get allChecked() { return this.rowIds.length > 0 && this.checked.length === this.rowIds.length },
        toggleAll(on) { this.checked = on ? [...this.rowIds] : [] },
    }"
>
    <div class="nk-datatable__toolbar">
        @if ($search)
            <form method="GET" class="nk-datatable__search" role="search">
                @foreach ($nkCarryParams as $paramKey => $paramValue)
                    @foreach (Arr::wrap($paramValue) as $paramItem)
                        <input type="hidden" name="{{ is_int($paramKey) ? $paramKey.'[]' : $paramKey }}" value="{{ $paramItem }}">
                    @endforeach
                @endforeach

                <x-admin::icon name="search" class="nk-icon--sm" />
                <input
                    type="search"
                    name="{{ $nkKey('q') }}"
                    value="{{ $nkSearchTerm }}"
                    placeholder="{{ $searchPlaceholder ?? __('Search…') }}"
                    aria-label="{{ __('Search') }}"
                >
                <button type="submit" class="nk-btn nk-btn--sm">{{ __('Search') }}</button>
                @if ($nkSearchTerm !== '')
                    <a
                        class="nk-btn nk-btn--sm nk-btn--ghost"
                        href="{{ request()->fullUrlWithQuery([$nkKey('q') => null, $nkKey('page') => null]) }}"
                    >{{ __('Clear') }}</a>
                @endif
            </form>
        @endif

        <span class="nk-datatable__count">
            {{ trans_choice('{0}No results|{1}:count result|[2,*]:count results', $rows->total()) }}
        </span>
    </div>

    @if ($nkHasBulk)
        <div class="nk-datatable__bulkbar" x-show="checked.length > 0" x-cloak>
            <span x-text="checked.length + ' {{ __('selected') }}'"></span>

            @foreach ($bulkActions as $action)
                @php
                    $confirm = $action['confirm'] ?? false;
                @endphp
                <button
                    type="submit"
                    form="{{ $nkBulkFormId }}"
                    formaction="{{ $action['url'] }}"
                    formmethod="POST"
                    class="nk-btn nk-btn--sm nk-btn--{{ $action['variant'] ?? 'default' }}"
                    @if ($confirm)
                        data-nk-confirm
                        @if (is_array($confirm))
                            @if (! empty($confirm['title'])) data-nk-confirm-title="{{ $confirm['title'] }}" @endif
                            @if (! empty($confirm['text'])) data-nk-confirm-text="{{ $confirm['text'] }}" @endif
                            @if (! empty($confirm['label'])) data-nk-confirm-label="{{ $confirm['label'] }}" @endif
                        @endif
                    @endif
                >{{ $action['label'] }}</button>
            @endforeach
        </div>
    @endif

    @if ($nkHasBulk)
        <form id="{{ $nkBulkFormId }}" method="POST">
            @csrf
            <template x-for="id in checked" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
        </form>
    @endif

    <div class="nk-table-scroll">
        <table class="nk-table">
            <thead>
                <tr>
                    @if ($nkHasBulk)
                        <th class="nk-table__check">
                            <input
                                type="checkbox"
                                :checked="allChecked"
                                @change="toggleAll($event.target.checked)"
                                aria-label="{{ __('Select all rows') }}"
                            >
                        </th>
                    @endif

                    @foreach ($headers as $header)
                        <th class="@if (($header['align'] ?? 'left') !== 'left') nk-table__cell--{{ $header['align'] }} @endif">
                            @if ($header['sortable'] ?? false)
                                @php
                                    $isCurrent = $nkSort === $header['key'];
                                    $nextDirection = $isCurrent && $nkDirection === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <a
                                    class="nk-table__sort"
                                    href="{{ request()->fullUrlWithQuery([$nkKey('sort') => $header['key'], $nkKey('direction') => $nextDirection, $nkKey('page') => null]) }}"
                                >
                                    {{ $header['label'] }}
                                    @if ($isCurrent)
                                        <x-admin::icon
                                            name="chevron-down"
                                            class="nk-icon--sm nk-table__sort-icon @if ($nkDirection === 'desc') is-desc @endif"
                                        />
                                    @endif
                                </a>
                            @else
                                {{ $header['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @if ($nkHasBulk)
                            <td class="nk-table__check">
                                <input
                                    type="checkbox"
                                    value="{{ data_get($row, $rowKey) }}"
                                    x-model="checked"
                                    aria-label="{{ __('Select row') }}"
                                >
                            </td>
                        @endif

                        @foreach ($headers as $header)
                            <td class="@if (($header['align'] ?? 'left') !== 'left') nk-table__cell--{{ $header['align'] }} @endif">
                                @if (isset($header['format']))
                                    {!! $header['format']($row) !!}
                                @else
                                    {{ data_get($row, $header['key']) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $nkColspan }}" class="nk-empty">
                            {{ $emptyMessage ?? __('No records found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->onEachSide(1)->links('admin::components.pagination') }}
</div>
