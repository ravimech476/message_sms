@if ($paginator->hasPages())
    <nav class="mt-2 py-4 w-full">
        <ul class=" flex justify-between">
            {{-- Previous Page Link --}}
            @if (!$paginator->onFirstPage())
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center bg-gray-300 rounded px-3 py-2 hover:bg-gray-600 hover:text-gray-100 cursor-pointer">@lang('pagination.previous')</a></li>
            @endif

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center bg-gray-300 rounded px-3 py-2 hover:bg-gray-600 hover:text-gray-100 cursor-pointer">@lang('pagination.next')</a></li>
            @endif
        </ul>
    </nav>
@endif
