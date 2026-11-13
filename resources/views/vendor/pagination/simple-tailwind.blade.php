@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="ww-pagination-wrap">
        @if ($paginator->onFirstPage())
            <span class="ww-pagination-disabled">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ww-pagination-link">Previous</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ww-pagination-link">Next</a>
        @else
            <span class="ww-pagination-disabled">Next</span>
        @endif
    </nav>
@endif
