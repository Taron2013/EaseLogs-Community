@if ($paginator->hasPages())
    <nav class="easelogs-pagination" role="navigation" aria-label="Pagination">
        <ul class="easelogs-pagination-list">
            @if ($paginator->onFirstPage())
                <li>
                    <span class="easelogs-pagination-disabled" aria-disabled="true">{{ __('pagination.previous') }}</span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="easelogs-pagination-link">{{ __('pagination.previous') }}</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li>
                        <span class="easelogs-pagination-ellipsis" aria-disabled="true">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span class="easelogs-pagination-current" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}" class="easelogs-pagination-link" aria-label="Page {{ $page }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="easelogs-pagination-link">{{ __('pagination.next') }}</a>
                </li>
            @else
                <li>
                    <span class="easelogs-pagination-disabled" aria-disabled="true">{{ __('pagination.next') }}</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
