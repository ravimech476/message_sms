@if ($paginator->hasPages())
    <nav>
        <ul class="pagination mb-0">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">&lsaquo;</a>
                </li>
            @endif

            @php
                $currentPage = $paginator->currentPage();
                $lastPage = $paginator->lastPage();
                $showPages = 5; // Number of pages to show at the start
                $endPages = 2;  // Number of pages to show at the end
            @endphp

            {{-- First pages (1 to 5) --}}
            @for ($page = 1; $page <= min($showPages, $lastPage); $page++)
                @if ($page == $currentPage)
                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a></li>
                @endif
            @endfor

            {{-- Show ellipsis if there are more pages --}}
            @if ($lastPage > $showPages + $endPages)
                <li class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>
            @endif

            {{-- Last pages --}}
            @if ($lastPage > $showPages)
                @for ($page = max($showPages + 1, $lastPage - $endPages + 1); $page <= $lastPage; $page++)
                    @if ($page == $currentPage)
                        <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a></li>
                    @endif
                @endfor
            @endif

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">&rsaquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
