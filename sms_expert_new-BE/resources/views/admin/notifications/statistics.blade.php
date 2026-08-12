@extends('admin.layouts.app')

@section('title')
    {{ __('Notification Statistics') }}
@endsection

@push('style')
<style>
    .breadcrumb-item+.breadcrumb-item::before {
        content: " / " !important;
        color: #6c757d !important;
    }

    .stats-card {
        border-radius: 10px;
        padding: 1.5rem;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
        text-align: center;
    }

    .stats-card h3 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .stats-card small {
        color: #6c757d;
    }

    .notification-info-card {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .notification-info-card .notification-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .notification-info-card .notification-message {
        opacity: 0.8;
        font-size: 14px;
        margin-bottom: 15px;
    }

    .notification-info-card .notification-meta {
        padding-top: 15px;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-size: 13px;
        opacity: 0.7;
    }

    .type-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .table thead th {
        background: #293b50;
        color: white;
        font-weight: 500;
        padding: 12px 15px;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 12px 15px;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background-color: rgba(234, 97, 24, 0.05);
    }

    .progress-lg {
        height: 20px;
        border-radius: 10px;
    }

    .progress-lg .progress-bar {
        border-radius: 10px;
    }

    .pagination .page-link {
        color: #293b50;
        border-radius: 5px;
        margin: 0 2px;
        padding: 8px 12px;
    }

    .pagination .page-item.active .page-link {
        background: #ea6118;
        border-color: #ea6118;
    }

    .pagination .page-item.disabled .page-link {
        color: #6c757d;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }

    .pagination-info {
        color: #6c757d;
        font-size: 14px;
    }

    .pagination {
        margin-bottom: 0;
    }

    .form-select-sm {
        padding: 0.25rem 2rem 0.25rem 0.5rem;
        font-size: 14px;
        border-radius: 6px;
    }

    .form-select-sm:focus {
        border-color: #ea6118;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Notification Statistics</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.notifications.index') }}">Notifications</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Statistics</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </button>
            </div>
        </div>

        <!-- Notification Info Card -->
        <div class="notification-info-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="notification-title">{{ $notification->title }}</div>
                    <div class="notification-message">{{ Str::limit($notification->message, 200) }}</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="type-badge bg-{{ $notification->type_badge_color }} text-white">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">{{ $notification->type_icon }}</i>
                            {{ ucfirst($notification->type) }}
                        </span>
                        <span class="status-badge bg-{{ $notification->status_badge_color }} text-white">
                            {{ ucfirst($notification->status) }}
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">{{ $notification->delivery_method == 'web' ? 'language' : ($notification->delivery_method == 'email' ? 'email' : 'all_inclusive') }}</i>
                            {{ ucfirst($notification->delivery_method) }}
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">group</i>
                            {{ ucfirst($notification->target_type) }} Customers
                        </span>
                        @if($notification->requires_acknowledgment)
                            <span class="badge bg-warning text-dark">
                                <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">priority_high</i>
                                Requires Acknowledgment
                            </span>
                        @endif
                    </div>
                </div>
                <div class="text-end">
                    @if(in_array($notification->status, ['draft', 'scheduled']))
                        <a href="{{ route('admin.notifications.edit', $notification->id) }}" class="btn btn-light btn-sm">
                            <i class="material-icons-outlined" style="font-size: 16px;">edit</i> Edit
                        </a>
                    @endif
                </div>
            </div>
            <div class="notification-meta">
                <i class="material-icons-outlined" style="font-size: 14px;">schedule</i>
                Created: {{ $notification->created_at->format('d M Y H:i:s') }}
                @if($notification->sent_at)
                    | <i class="material-icons-outlined" style="font-size: 14px;">send</i>
                    Sent: {{ $notification->sent_at->format('d M Y H:i:s') }}
                @endif
                @if($notification->scheduled_at)
                    | <i class="material-icons-outlined" style="font-size: 14px;">event</i>
                    Scheduled: {{ $notification->scheduled_at->format('d M Y H:i:s') }}
                @endif
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card">
                    <h3 class="text-primary">{{ $notification->total_recipients }}</h3>
                    <small>Total Recipients</small>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card">
                    <h3 class="text-success">{{ $notification->actual_read_count }}</h3>
                    <small>Read ({{ $notification->read_percentage }}%)</small>
                    <div class="progress progress-lg mt-2">
                        <div class="progress-bar bg-success" style="width: {{ $notification->read_percentage }}%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card">
                    <h3 class="text-warning">{{ $notification->actual_acknowledged_count }}</h3>
                    <small>Acknowledged ({{ $notification->acknowledged_percentage }}%)</small>
                    <div class="progress progress-lg mt-2">
                        <div class="progress-bar bg-warning" style="width: {{ $notification->acknowledged_percentage }}%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card">
                    <h3 class="text-info">{{ $emailSentCount }}</h3>
                    <small>Emails Sent</small>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card">
                    <h3 class="text-secondary">{{ $pushSentCount ?? 0 }}</h3>
                    <small>Push Sent</small>
                </div>
            </div>
            <div class="col-md-2 col-sm-6 mb-3">
                <div class="stats-card">
                    <h3 class="text-dark">{{ $notification->recipients()->where('web_delivered', true)->count() }}</h3>
                    <small>Web Delivered</small>
                </div>
            </div>
        </div>

        <!-- Recipients Table -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0">
                        <i class="material-icons-outlined me-2" style="vertical-align: middle;">people</i>
                        Recipients List
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted mb-0" style="white-space: nowrap;">Show</label>
                            <select class="form-select form-select-sm" id="perPageSelect" style="width: 80px;">
                                <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                <option value="15" {{ $perPage == 15 ? 'selected' : '' }}>15</option>
                                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                            </select>
                            <label class="text-muted mb-0" style="white-space: nowrap;">entries</label>
                        </div>
                        <div class="text-muted">
                            Total: <strong>{{ $recipients->total() }}</strong> recipients
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Read</th>
                                <th>Read At</th>
                                @if($notification->requires_acknowledgment)
                                    <th>Acknowledged</th>
                                    <th>Acknowledged At</th>
                                @endif
                                <th>Email Sent</th>
                                <th>Push Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recipients as $index => $recipient)
                                <tr>
                                    <td>{{ $recipients->firstItem() + $index }}</td>
                                    <td>{{ urldecode($recipient->user->busname ?? $recipient->user->contactname ?? 'N/A') }}</td>
                                    <td>{{ $recipient->user->contactemail ?? 'N/A' }}</td>
                                    <td>
                                        @if($recipient->is_read)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($recipient->read_at)
                                            {{ \Carbon\Carbon::parse($recipient->read_at)->format('d M Y H:i:s') }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    @if($notification->requires_acknowledgment)
                                        <td>
                                            @if($recipient->is_acknowledged)
                                                <span class="badge bg-success">Yes</span>
                                            @else
                                                <span class="badge bg-secondary">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($recipient->acknowledged_at)
                                                {{ \Carbon\Carbon::parse($recipient->acknowledged_at)->format('d M Y H:i:s') }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        @if($recipient->email_sent)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($recipient->push_sent)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $notification->requires_acknowledgment ? 9 : 7 }}" class="text-center py-4">
                                        <i class="material-icons-outlined" style="font-size: 48px; color: #ccc;">person_off</i>
                                        <p class="mt-2 text-muted">No recipients found.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($recipients->hasPages())
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing <strong>{{ $recipients->firstItem() }}</strong> to <strong>{{ $recipients->lastItem() }}</strong> of <strong>{{ $recipients->total() }}</strong> entries
                        </div>
                        <nav aria-label="Recipients pagination">
                            <ul class="pagination">
                                {{-- Previous Page Link --}}
                                @if ($recipients->onFirstPage())
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous</span>
                                    </li>
                                @else
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $recipients->previousPageUrl() }}&per_page={{ $perPage }}"><i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous</a>
                                    </li>
                                @endif

                                {{-- Pagination Elements --}}
                                @php
                                    $currentPage = $recipients->currentPage();
                                    $lastPage = $recipients->lastPage();
                                    $start = max(1, $currentPage - 2);
                                    $end = min($lastPage, $currentPage + 2);
                                @endphp

                                @if($start > 1)
                                    <li class="page-item"><a class="page-link" href="{{ $recipients->url(1) }}&per_page={{ $perPage }}">1</a></li>
                                    @if($start > 2)
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    @endif
                                @endif

                                @for($page = $start; $page <= $end; $page++)
                                    @if ($page == $currentPage)
                                        <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                                    @else
                                        <li class="page-item"><a class="page-link" href="{{ $recipients->url($page) }}&per_page={{ $perPage }}">{{ $page }}</a></li>
                                    @endif
                                @endfor

                                @if($end < $lastPage)
                                    @if($end < $lastPage - 1)
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    @endif
                                    <li class="page-item"><a class="page-link" href="{{ $recipients->url($lastPage) }}&per_page={{ $perPage }}">{{ $lastPage }}</a></li>
                                @endif

                                {{-- Next Page Link --}}
                                @if ($recipients->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $recipients->nextPageUrl() }}&per_page={{ $perPage }}">Next <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_right</i></a>
                                    </li>
                                @else
                                    <li class="page-item disabled">
                                        <span class="page-link">Next <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_right</i></span>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>

@include('admin.layouts.footer')
@endsection

@push('js')
<script>
    // Per page selection change
    document.getElementById('perPageSelect').addEventListener('change', function() {
        const perPage = this.value;
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage);
        url.searchParams.delete('page'); // Reset to first page when changing per_page
        window.location.href = url.toString();
    });
</script>
@endpush
