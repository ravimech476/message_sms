@push('style')
    <style>
        .log-entry {
            margin-bottom: 15px;
            border-left: 4px solid #ddd;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .log-entry.ERROR {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }

        .log-entry.WARNING {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }

        .log-entry.INFO {
            border-left-color: #17a2b8;
            background-color: #d1ecf1;
        }

        .log-entry.DEBUG {
            border-left-color: #6c757d;
            background-color: #e2e3e5;
        }

        .log-level {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
            margin-right: 10px;
        }

        .log-level.ERROR {
            background-color: #dc3545;
            color: white;
        }

        .log-level.WARNING {
            background-color: #ffc107;
            color: #000;
        }

        .log-level.INFO {
            background-color: #17a2b8;
            color: white;
        }

        .log-level.DEBUG {
            background-color: #6c757d;
            color: white;
        }

        .log-timestamp {
            color: #6c757d;
            font-size: 13px;
        }

        .log-message {
            margin-top: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .filter-card-inline {
            margin-bottom: 20px;
        }

        .no-logs {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .stats-card-inline {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .btn-group-actions-inline {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .material-icons,
        .material-icons-outlined {
            font-family: 'Material Icons Outlined', 'Material Icons' !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 20px !important;
            line-height: 1 !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            vertical-align: middle !important;
            position: relative;
            top: -1px;
        }

        button .material-icons,
        button .material-icons-outlined {
            margin-right: 4px !important;
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 14px;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .per-page-selector label {
            font-size: 14px;
            color: #6c757d;
            margin: 0;
        }

        .per-page-selector select {
            width: auto;
            padding: 5px 10px;
            font-size: 14px;
        }

        .pagination {
            margin: 0;
        }

        .pagination .page-link {
            padding: 8px 14px;
            font-size: 14px;
        }

        .pagination .page-item.disabled .page-link {
            color: #adb5bd;
        }

        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }

        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .pagination-controls {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
@endpush

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Logs Details</h5>
    <div class="btn-group-actions-inline">
        @if (!empty($selectedDate ?? null))
            <a href="{{ route('admin.logs.download', ['date' => $selectedDate]) }}" class="btn btn-sm btn-primary">
                <i class="material-icons-outlined">download</i> Download
            </a>
            <a href="{{ route('admin.logs.download-all', ['date' => $selectedDate]) }}" class="btn btn-sm btn-success"
                title="Download the {{ $selectedDate }} log folder (all worker logs &amp; subfolders) as a single ZIP">
                <i class="material-icons-outlined">folder_zip</i> Download ZIP
            </a>
            <form method="POST" action="{{ route('admin.logs.delete', ['date' => $selectedDate]) }}"
                style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this log file?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">
                    <i class="material-icons-outlined">delete</i> Delete
                </button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.logs.clear-all') }}" style="display:inline;"
            onsubmit="return confirm('Are you sure you want to delete ALL log files? This action cannot be undone!');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="material-icons-outlined">delete_sweep</i> Clear All
            </button>
        </form>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" id="flash-message" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" id="flash-error-message" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Browse & Download Log Files: date -> folder -> file -->
<div class="card filter-card-inline" id="logBrowserCard">
    <div class="card-body">
        <h6 class="mb-3"><i class="material-icons-outlined">folder_open</i> Browse &amp; Download Log Files</h6>
        <div class="row align-items-end g-2">
            <div class="col-md-4">
                <label class="form-label" for="logBrowseDate">1. Select Date</label>
                <select id="logBrowseDate" class="form-select form-select-sm">
                    <option value="">Loading dates…</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="logBrowseFolder">2. Select Folder</label>
                <select id="logBrowseFolder" class="form-select form-select-sm" disabled>
                    <option value="">— pick a date first —</option>
                </select>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-sm table-hover align-middle mb-0" id="logFilesTable" style="display:none;">
                <thead class="table-light">
                    <tr>
                        <th>File</th>
                        <th style="width:120px;">Size</th>
                        <th style="width:180px;">Last Modified</th>
                        <th style="width:90px;" class="text-end">Download</th>
                    </tr>
                </thead>
                <tbody id="logFilesBody"></tbody>
            </table>
            <div id="logFilesLoading" class="text-muted small py-3" style="display:none;">Loading…</div>
            <div id="logFilesEmpty" class="text-muted small py-3" style="display:none;">No files in this folder.</div>
        </div>
    </div>
</div>

<!-- Statistics -->
@if (!empty($logs ?? []))
    <div class="stats-card-inline">
        <div class="row">
            <div class="col-md-3">
                <h6 class="text-dark">Total Logs</h6>
                <h3 class="text-white">{{ number_format($totalLogs ?? 0) }}</h3>
            </div>
            <div class="col-md-3">
                <h6 class="text-dark">Selected Date</h6>
                <h3 class="text-white">{{ date('M d, Y', strtotime($selectedDate ?? now())) }}</h3>
            </div>
            <div class="col-md-3">
                <h6 class="text-dark">Log Level</h6>
                <h3 class="text-white">{{ ($selectedLevel ?? 'all') === 'all' ? 'All Levels' : strtoupper($selectedLevel ?? 'all') }}</h3>
            </div>
            <div class="col-md-3">
                <h6 class="text-dark">Available Dates</h6>
                <h3 class="text-white">{{ count($availableDates ?? []) }}</h3>
            </div>
        </div>
    </div>
@endif

<!-- Filter -->
<div class="card filter-card-inline">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.settings') }}" id="logFilterForm">
            <input type="hidden" name="tab" value="logs">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="date" class="form-label">Select Date</label>
                    <select name="date" id="date" class="form-select form-select-sm">
                        @forelse($availableDates ?? [] as $date)
                            <option value="{{ $date }}"
                                {{ ($selectedDate ?? '') == $date ? 'selected' : '' }}>
                                {{ date('F d, Y', strtotime($date)) }}
                            </option>
                        @empty
                            <option value="">No logs available</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="level" class="form-label">Log Level</label>
                    <select name="level" id="level" class="form-select form-select-sm">
                        <option value="all" {{ ($selectedLevel ?? 'all') == 'all' ? 'selected' : '' }}>All Levels</option>
                        <option value="error" {{ ($selectedLevel ?? '') == 'error' ? 'selected' : '' }}>Error</option>
                        <option value="warning" {{ ($selectedLevel ?? '') == 'warning' ? 'selected' : '' }}>Warning</option>
                        <option value="info" {{ ($selectedLevel ?? '') == 'info' ? 'selected' : '' }}>Info</option>
                        <option value="debug" {{ ($selectedLevel ?? '') == 'debug' ? 'selected' : '' }}>Debug</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="search" class="form-label">Search in Logs</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm"
                        placeholder="Search for keywords..." value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="material-icons-outlined">search</i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Logs Display -->
@if (empty($logs ?? []) && empty($availableDates ?? []))
    <div class="no-logs">
        <i class="material-icons-outlined" style="font-size: 64px;">info</i>
        <h4>No Log Files Found</h4>
        <p>There are currently no log files in the system.</p>
    </div>
@elseif(empty($logs ?? []))
    <div class="no-logs">
        <i class="material-icons-outlined" style="font-size: 64px;">search_off</i>
        <h4>No Logs Found</h4>
        <p>No log entries found for the selected filters.</p>
        <a href="{{ route('admin.settings') }}?tab=logs" class="btn btn-primary mt-3">Clear Filters</a>
    </div>
@else
    @php
        // Pagination variables - calculate once, use everywhere
        $perPage = $perPage ?? 50;
        $currentPage = (int) request('page', 1);
        $totalPages = (int) ceil(($totalLogs ?? 0) / $perPage);
        $startRecord = ($currentPage - 1) * $perPage + 1;
        $endRecord = min($currentPage * $perPage, $totalLogs ?? 0);
        
        // Calculate which 10 pages to show
        $maxPagesToShow = 10;
        
        if ($totalPages <= $maxPagesToShow) {
            $startPage = 1;
            $endPage = $totalPages;
        } else {
            $halfMax = (int) floor($maxPagesToShow / 2);
            
            if ($currentPage <= $halfMax) {
                $startPage = 1;
                $endPage = $maxPagesToShow;
            } elseif ($currentPage > ($totalPages - $halfMax)) {
                $startPage = $totalPages - $maxPagesToShow + 1;
                $endPage = $totalPages;
            } else {
                $startPage = $currentPage - $halfMax;
                $endPage = $currentPage + $halfMax - 1;
            }
        }
        
        // Build page URL helper
        $buildPageUrl = function($page) {
            return route('admin.settings', array_merge(request()->query(), ['tab' => 'logs', 'page' => $page]));
        };
    @endphp

    <div class="logs-container mt-3">
        @foreach ($logs ?? [] as $log)
            <div class="log-entry {{ $log['level'] }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="log-level {{ $log['level'] }}">{{ $log['level'] }}</span>
                        <span class="log-timestamp">{{ $log['timestamp'] }}</span>
                        <span class="badge bg-secondary">{{ $log['environment'] }}</span>
                    </div>
                </div>
                <div class="log-message">{{ $log['message'] }}</div>
            </div>
        @endforeach
    </div>

    <!-- Bottom Pagination -->
    @if ($totalPages > 1)
        <div class="pagination-container">
            <div class="pagination-info">
                Showing <strong>{{ $startRecord }}</strong> to <strong>{{ $endRecord }}</strong> of <strong>{{ number_format($totalLogs ?? 0) }}</strong> entries
                (Page {{ $currentPage }} of {{ $totalPages }})
            </div>
            
            <div class="pagination-controls">
                <div class="per-page-selector">
                    <label for="perPage">Show:</label>
                    <select id="perPage" class="form-select form-select-sm" onchange="changePerPage(this.value)">
                        <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        <option value="200" {{ $perPage == 200 ? 'selected' : '' }}>200</option>
                    </select>
                </div>

                <nav aria-label="Log pagination">
                    <ul class="pagination pagination-sm mb-0">
                        {{-- First Page --}}
                        <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $currentPage > 1 ? $buildPageUrl(1) : '#' }}" title="First">
                                <i class="material-icons-outlined" style="font-size: 16px !important;">first_page</i>
                            </a>
                        </li>

                        {{-- Previous Page --}}
                        <li class="page-item {{ $currentPage == 1 ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $currentPage > 1 ? $buildPageUrl($currentPage - 1) : '#' }}" title="Previous">
                                <i class="material-icons-outlined" style="font-size: 16px !important;">chevron_left</i>
                            </a>
                        </li>

                        {{-- Page Numbers - Only show 10 at a time --}}
                        @for ($i = $startPage; $i <= $endPage; $i++)
                            <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                <a class="page-link" href="{{ $buildPageUrl($i) }}">{{ $i }}</a>
                            </li>
                        @endfor

                        {{-- Next Page --}}
                        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $currentPage < $totalPages ? $buildPageUrl($currentPage + 1) : '#' }}" title="Next">
                                <i class="material-icons-outlined" style="font-size: 16px !important;">chevron_right</i>
                            </a>
                        </li>

                        {{-- Last Page --}}
                        <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                            <a class="page-link" href="{{ $currentPage < $totalPages ? $buildPageUrl($totalPages) : '#' }}" title="Last">
                                <i class="material-icons-outlined" style="font-size: 16px !important;">last_page</i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    @endif
@endif

@push('js')
    <script>
        // Auto-hide flash messages
        setTimeout(function() {
            let flashMessage = document.getElementById('flash-message');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }, 2000);

        setTimeout(function() {
            let flashMessage = document.getElementById('flash-error-message');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }, 2000);

        // Change per page function
        function changePerPage(perPage) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
    </script>
@endpush

@push('js')
    <script>
        // Log file browser: date -> folder -> file -> download
        (function () {
            const datesUrl = @json(route('admin.logs.dates'));
            const foldersUrl = @json(route('admin.logs.folders'));
            const filesUrl = @json(route('admin.logs.files'));
            const downloadUrl = @json(route('admin.logs.download-file'));

            const dateSel = document.getElementById('logBrowseDate');
            const folderSel = document.getElementById('logBrowseFolder');
            const table = document.getElementById('logFilesTable');
            const body = document.getElementById('logFilesBody');
            const loading = document.getElementById('logFilesLoading');
            const empty = document.getElementById('logFilesEmpty');

            if (!dateSel) return;

            const esc = (s) => String(s).replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));

            function resetFiles() {
                body.innerHTML = '';
                table.style.display = 'none';
                empty.style.display = 'none';
                loading.style.display = 'none';
            }

            // 1. Populate dates
            fetch(datesUrl)
                .then(r => r.json())
                .then(d => {
                    const dates = d.dates || [];
                    if (!dates.length) {
                        dateSel.innerHTML = '<option value="">No log dates found</option>';
                        return;
                    }
                    dateSel.innerHTML = '<option value="">— select date —</option>' +
                        dates.map(x => `<option value="${esc(x)}">${esc(x)}</option>`).join('');
                })
                .catch(() => { dateSel.innerHTML = '<option value="">Failed to load dates</option>'; });

            // 2. Date -> folders
            dateSel.addEventListener('change', function () {
                resetFiles();
                folderSel.disabled = true;
                folderSel.innerHTML = '<option value="">Loading…</option>';
                const date = dateSel.value;
                if (!date) {
                    folderSel.innerHTML = '<option value="">— pick a date first —</option>';
                    return;
                }
                fetch(foldersUrl + '?date=' + encodeURIComponent(date))
                    .then(r => r.json())
                    .then(d => {
                        const folders = d.folders || [];
                        if (!folders.length) {
                            folderSel.innerHTML = '<option value="">No folders for this date</option>';
                            return;
                        }
                        folderSel.innerHTML = '<option value="">— select folder —</option>' +
                            folders.map(f => `<option value="${esc(f.key)}">${esc(f.label)} (${f.count})</option>`).join('');
                        folderSel.disabled = false;
                    })
                    .catch(() => { folderSel.innerHTML = '<option value="">Failed to load folders</option>'; });
            });

            // 3. Folder -> files
            folderSel.addEventListener('change', function () {
                resetFiles();
                const date = dateSel.value;
                const folder = folderSel.value;
                if (!date || !folder) return;
                loading.style.display = 'block';
                fetch(filesUrl + '?date=' + encodeURIComponent(date) + '&folder=' + encodeURIComponent(folder))
                    .then(r => r.json())
                    .then(d => {
                        loading.style.display = 'none';
                        const files = d.files || [];
                        if (!files.length) { empty.style.display = 'block'; return; }
                        body.innerHTML = files.map(f => {
                            const dl = downloadUrl +
                                '?date=' + encodeURIComponent(date) +
                                '&folder=' + encodeURIComponent(folder) +
                                '&file=' + encodeURIComponent(f.name);
                            return '<tr>' +
                                '<td><i class="material-icons-outlined" style="font-size:16px!important;">description</i> ' + esc(f.name) + '</td>' +
                                '<td>' + esc(f.size) + '</td>' +
                                '<td>' + esc(f.modified) + '</td>' +
                                '<td class="text-end"><a class="btn btn-sm btn-primary" href="' + dl + '" title="Download">' +
                                '<i class="material-icons-outlined" style="font-size:16px!important;">download</i></a></td>' +
                                '</tr>';
                        }).join('');
                        table.style.display = 'table';
                    })
                    .catch(() => { loading.style.display = 'none'; empty.style.display = 'block'; });
            });
        })();
    </script>
@endpush
