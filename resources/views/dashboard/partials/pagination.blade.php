@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="dash-pagination" aria-label="{{ __('dashboard.pagination_label') }}">
        @if ($paginator->total() > 0)
            <p class="dash-pagination-summary">
                {{ __('dashboard.pagination_showing', [
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                    'total' => $paginator->total(),
                ]) }}
            </p>
        @endif

        @if ($paginator->hasPages())
            <div class="dash-pagination-links">
                @if ($paginator->onFirstPage())
                    <span class="dash-pagination-btn is-disabled">{{ __('dashboard.pagination_previous') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="dash-pagination-btn">{{ __('dashboard.pagination_previous') }}</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="dash-pagination-ellipsis">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="dash-pagination-btn is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="dash-pagination-btn">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="dash-pagination-btn">{{ __('dashboard.pagination_next') }}</a>
                @else
                    <span class="dash-pagination-btn is-disabled">{{ __('dashboard.pagination_next') }}</span>
                @endif
            </div>
        @endif
    </nav>
@endif
