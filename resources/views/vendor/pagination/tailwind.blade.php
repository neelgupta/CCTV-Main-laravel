@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="ww-pagination-wrap">
        @if ($paginator->onFirstPage())
            <span class="ww-pagination-disabled">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ww-pagination-link">Previous</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="ww-pagination-disabled">...</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="ww-pagination-current">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="ww-pagination-link">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ww-pagination-link">Next</a>
        @else
            <span class="ww-pagination-disabled">Next</span>
        @endif
    </nav>
@endif
