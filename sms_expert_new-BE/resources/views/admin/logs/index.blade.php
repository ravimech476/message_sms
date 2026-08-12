@extends('admin.layouts.app')

@section('title', 'System Logs')

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

    .filter-card {
        margin-bottom: 20px;
    }

    .no-logs {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }

    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .btn-group-actions {
        display: flex;
        gap: 10px;
    }
</style>
@endpush

@section('content')
 <main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">System Logs</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        @if(isset($error))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="page-header">
            <h3 class="mb-0"><i class="material-icons-outlined">article</i> System Logs</h3>
            <div class="btn-group-actions">
                @if(!empty($selectedDate))
                <a href="{{ route('admin.logs.download', ['date' => $selectedDate]) }}" class="btn btn-primary">
                    <i class="material-icons-outlined">download</i> Download
                </a>
                <form method="POST" action="{{ route('admin.logs.delete', ['date' => $selectedDate]) }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this log file?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="material-icons-outlined">delete</i> Delete
                    </button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.logs.clear-all') }}" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete ALL log files? This action cannot be undone!');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="material-icons-outlined">delete_sweep</i> Clear All Logs
                    </button>
                </form>
            </div>
        </div>

        <!-- Statistics Card -->
        @if(!empty($logs))
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3">
                    <h6>Total Logs</h6>
                    <h3>{{ $totalLogs }}</h3>
                </div>
                <div class="col-md-3">
                    <h6>Selected Date</h6>
                    <h3>{{ date('M d, Y', strtotime($selectedDate)) }}</h3>
                </div>
                <div class="col-md-3">
                    <h6>Log Level</h6>
                    <h3>{{ $selectedLevel === 'all' ? 'All Levels' : strtoupper($selectedLevel) }}</h3>
                </div>
                <div class="col-md-3">
                    <h6>Available Dates</h6>
                    <h3>{{ count($availableDates) }}</h3>
                </div>
            </div>
        </div>
        @endif

        <!-- Filter Card -->
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.logs.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label for="date" class="form-label">Select Date</label>
                            <select name="date" id="date" class="form-select">
                                @forelse($availableDates as $date)
                                    <option value="{{ $date }}" {{ $selectedDate == $date ? 'selected' : '' }}>
                                        {{ date('F d, Y', strtotime($date)) }}
                                    </option>
                                @empty
                                    <option value="">No logs available</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="level" class="form-label">Log Level</label>
                            <select name="level" id="level" class="form-select">
                                <option value="all" {{ $selectedLevel == 'all' ? 'selected' : '' }}>All Levels</option>
                                <option value="error" {{ $selectedLevel == 'error' ? 'selected' : '' }}>Error</option>
                                <option value="warning" {{ $selectedLevel == 'warning' ? 'selected' : '' }}>Warning</option>
                                <option value="info" {{ $selectedLevel == 'info' ? 'selected' : '' }}>Info</option>
                                <option value="debug" {{ $selectedLevel == 'debug' ? 'selected' : '' }}>Debug</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="search" class="form-label">Search in Logs</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Search for keywords..." value="{{ $search }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="material-icons-outlined">search</i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Display -->
        <div class="card">
            <div class="card-body">
                @if(empty($logs) && empty($availableDates))
                <div class="no-logs">
                    <i class="material-icons-outlined" style="font-size: 64px;">info</i>
                    <h4>No Log Files Found</h4>
                    <p>There are currently no log files in the system.</p>
                </div>
                @elseif(empty($logs))
                <div class="no-logs">
                    <i class="material-icons-outlined" style="font-size: 64px;">search_off</i>
                    <h4>No Logs Found</h4>
                    <p>No log entries found for the selected filters.</p>
                    <a href="{{ route('admin.logs.index') }}" class="btn btn-primary mt-3">Clear Filters</a>
                </div>
                @else
                <div class="logs-container">
                    @foreach($logs as $log)
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

                <!-- Pagination -->
                @if($totalLogs > $perPage)
                <div class="mt-4">
                    <nav aria-label="Log pagination">
                        <ul class="pagination justify-content-center">
                            @php
                                $totalPages = ceil($totalLogs / $perPage);
                                $currentPage = request('page', 1);
                            @endphp
                            
                            @if($currentPage > 1)
                            <li class="page-item">
                                <a class="page-link" href="{{ route('admin.logs.index', ['date' => $selectedDate, 'level' => $selectedLevel, 'search' => $search, 'page' => $currentPage - 1]) }}">
                                    Previous
                                </a>
                            </li>
                            @endif

                            @for($i = 1; $i <= $totalPages; $i++)
                            <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                <a class="page-link" href="{{ route('admin.logs.index', ['date' => $selectedDate, 'level' => $selectedLevel, 'search' => $search, 'page' => $i]) }}">
                                    {{ $i }}
                                </a>
                            </li>
                            @endfor

                            @if($currentPage < $totalPages)
                            <li class="page-item">
                                <a class="page-link" href="{{ route('admin.logs.index', ['date' => $selectedDate, 'level' => $selectedLevel, 'search' => $search, 'page' => $currentPage + 1]) }}">
                                    Next
                                </a>
                            </li>
                            @endif
                        </ul>
                    </nav>
                    <p class="text-center text-muted">
                        Showing {{ (($currentPage - 1) * $perPage) + 1 }} to {{ min($currentPage * $perPage, $totalLogs) }} of {{ $totalLogs }} entries
                    </p>
                </div>
                @endif
                @endif
            </div>
        </div>
    </div>
</main>
@endsection

@push('js')
<script>
    // Auto-refresh option
    let autoRefreshInterval = null;
    
    function toggleAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
            alert('Auto-refresh disabled');
        } else {
            autoRefreshInterval = setInterval(() => {
                location.reload();
            }, 30000); // Refresh every 30 seconds
            alert('Auto-refresh enabled (30 seconds)');
        }
    }
</script>
@endpush
