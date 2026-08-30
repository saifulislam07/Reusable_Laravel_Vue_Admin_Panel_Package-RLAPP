{{--
    Custom paginator view -- passed to $paginator->links('admin::components.pagination').

    Laravel provides $paginator and $elements (a mix of page-number arrays and
    "..." separator strings from Illuminate\Pagination\UrlWindow); this just
    themes that contract with nk- classes instead of Tailwind's.
--}}
@if ($paginator->hasPages())
    <nav class="nk-pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        @if ($paginator->onFirstPage())
            <span class="nk-pagination__link is-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                <x-admin::icon name="chevron-right" class="nk-icon--sm nk-pagination__prev-icon" />
            </span>
        @else
            <a class="nk-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">
                <x-admin::icon name="chevron-right" class="nk-icon--sm nk-pagination__prev-icon" />
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="nk-pagination__separator">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="nk-pagination__link is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="nk-pagination__link" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="nk-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">
                <x-admin::icon name="chevron-right" class="nk-icon--sm" />
            </a>
        @else
            <span class="nk-pagination__link is-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                <x-admin::icon name="chevron-right" class="nk-icon--sm" />
            </span>
        @endif
    </nav>
@endif
