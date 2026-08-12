@extends('admin.layouts.app')

@section('title')
    {{ __('Notification Management') }}
@endsection

@push('style')
<style>
    .breadcrumb-item+.breadcrumb-item::before {
        content: " / " !important;
        color: #6c757d !important;
    }

    .notification-card {
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: none;
    }

    .notification-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
    }

    .notification-type-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .notification-status-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .notification-item {
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
        padding: 1rem;
        border-bottom: 1px solid #e9ecef;
    }

    .notification-item:hover {
        background: #f8f9fa;
        border-left-color: #ea6118;
    }

    .notification-item.type-info { border-left-color: #0d6efd; }
    .notification-item.type-warning { border-left-color: #ffc107; }
    .notification-item.type-success { border-left-color: #198754; }
    .notification-item.type-danger { border-left-color: #dc3545; }
    .notification-item.type-announcement { border-left-color: #0dcaf0; }

    .btn-create-notification {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-create-notification:hover {
        background: linear-gradient(135deg, #d1520e, #b8450c);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        color: white;
    }

    .stats-card {
        border-radius: 10px;
        padding: 1.25rem;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid #e9ecef;
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .table-controls {
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .progress-sm {
        height: 6px;
        border-radius: 3px;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        transition: all 0.2s ease;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    .modal-content {
        border-radius: 10px;
        border: none;
    }

    .modal-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
    }

    .modal-header .btn-close {
        filter: invert(1);
    }

    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        height: 38px;
    }

    .form-control:focus, .form-select:focus {
        border-color: #ea6118;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
    }

    textarea.form-control {
        height: auto;
    }

    .pagination-info {
        background: #e9ecef;
        padding: 8px 15px;
        border-radius: 8px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .total-count {
        font-weight: 600;
        color: #ea6118;
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

    .pagination {
        margin-bottom: 0;
    }

    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        padding: 15px 20px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        border-radius: 0 0 10px 10px;
    }

    .pagination-text {
        color: #6c757d;
        font-size: 14px;
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
            <div class="breadcrumb-title pe-3 title-name">Notification Management</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Notifications</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div id="flash-message" class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div id="flash-error-message" class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 me-3">
                            <i class="material-icons-outlined text-primary">send</i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">{{ $sentCount }}</h5>
                            <small class="text-muted">Sent</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 me-3">
                            <i class="material-icons-outlined text-warning">schedule</i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">{{ $scheduledCount }}</h5>
                            <small class="text-muted">Scheduled</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-secondary bg-opacity-10 me-3">
                            <i class="material-icons-outlined text-secondary">drafts</i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">{{ $draftCount }}</h5>
                            <small class="text-muted">Drafts</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 me-3">
                            <i class="material-icons-outlined text-info">notifications_active</i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">{{ $totalCount }}</h5>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="card">
            <div class="card-body">
                <!-- Table Controls -->
                <div class="table-controls d-flex justify-content-between align-items-end flex-wrap gap-3">
                    <form action="{{ route('admin.notifications.index') }}" method="GET" class="d-flex gap-2 align-items-end flex-wrap">
                        <div>
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select" style="width: 140px;">
                                <option value="">All Status</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Type</label>
                            <select name="type" class="form-select" style="width: 140px;">
                                <option value="">All Types</option>
                                <option value="info" {{ request('type') == 'info' ? 'selected' : '' }}>Info</option>
                                <option value="warning" {{ request('type') == 'warning' ? 'selected' : '' }}>Warning</option>
                                <option value="success" {{ request('type') == 'success' ? 'selected' : '' }}>Success</option>
                                <option value="danger" {{ request('type') == 'danger' ? 'selected' : '' }}>Danger</option>
                                <option value="announcement" {{ request('type') == 'announcement' ? 'selected' : '' }}>Announcement</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Date From</label>
                            <input type="date" name="date_from" class="form-control" style="width: 150px;" value="{{ request('date_from') }}">
                        </div>
                        <div>
                            <label class="form-label small text-muted">Date To</label>
                            <input type="date" name="date_to" class="form-control" style="width: 150px;" value="{{ request('date_to') }}">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-secondary">
                                <i class="material-icons-outlined" style="font-size: 16px;">search</i> Filter
                            </button>
                        </div>
                        @if(request('status') || request('type') || request('date_from') || request('date_to'))
                            <div>
                                <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        @endif
                    </form>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="pagination-info">
                            <i class="material-icons-outlined" style="font-size: 18px;">notifications</i>
                            Total: <span class="total-count">{{ $notifications->total() }}</span>
                        </div>
                        <button type="button" class="btn btn-create-notification" data-bs-toggle="modal" data-bs-target="#createNotificationModal">
                            <i class="material-icons-outlined" style="font-size: 18px;">add_circle</i> Create Notification
                        </button>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="card notification-card">
                    <div class="notification-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-white">
                            <i class="material-icons-outlined me-2">list</i>
                            All Notifications
                        </h5>
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <label class="text-white mb-0" style="white-space: nowrap; opacity: 0.8;">Show</label>
                                <select class="form-select form-select-sm" id="perPageSelect" style="width: 80px;">
                                    <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                    <option value="15" {{ $perPage == 15 ? 'selected' : '' }}>15</option>
                                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                </select>
                                <label class="text-white mb-0" style="white-space: nowrap; opacity: 0.8;">entries</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($notifications->count() > 0)
                            @foreach($notifications as $notification)
                                <div class="notification-item type-{{ $notification->type }}">
                                    <div class="row align-items-center">
                                        <div class="col-md-5">
                                            <div class="d-flex align-items-start">
                                                <div class="me-3">
                                                    <span class="notification-type-badge bg-{{ $notification->type_badge_color }}">
                                                        <i class="material-icons-outlined text-white" style="font-size: 14px; vertical-align: middle;">{{ $notification->type_icon }}</i>
                                                    </span>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-bold">{{ $notification->title }}</h6>
                                                    <p class="text-muted mb-1 small">{{ Str::limit($notification->message, 100) }}</p>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <small class="text-muted">
                                                            <i class="material-icons-outlined" style="font-size: 12px;">schedule</i>
                                                            {{ $notification->created_at->format('M d, Y H:i') }}
                                                        </small>
                                                        @if($notification->scheduled_at)
                                                            <small class="text-info">
                                                                <i class="material-icons-outlined" style="font-size: 12px;">event</i>
                                                                Scheduled: {{ $notification->scheduled_at->format('M d, Y H:i') }}
                                                            </small>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="d-flex flex-column gap-1">
                                                <span class="notification-status-badge bg-{{ $notification->status_badge_color }} text-white d-inline-block" style="width: fit-content;">
                                                    {{ ucfirst($notification->status) }}
                                                </span>
                                                <small class="text-muted">
                                                    Target: {{ ucfirst($notification->target_type) }} | {{ ucfirst($notification->delivery_method) }}
                                                </small>
                                                @if($notification->requires_acknowledgment)
                                                    <small class="text-warning">
                                                        <i class="material-icons-outlined" style="font-size: 12px;">priority_high</i>
                                                        Requires Acknowledgment
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            @if($notification->status === 'sent')
                                                <div class="mb-1">
                                                    <small class="text-muted d-block">Read: {{ $notification->read_percentage }}%</small>
                                                    <div class="progress progress-sm">
                                                        <div class="progress-bar bg-success" style="width: {{ $notification->read_percentage }}%"></div>
                                                    </div>
                                                </div>
                                                @if($notification->requires_acknowledgment)
                                                    <div>
                                                        <small class="text-muted d-block">Acknowledged: {{ $notification->acknowledged_percentage }}%</small>
                                                        <div class="progress progress-sm">
                                                            <div class="progress-bar bg-warning" style="width: {{ $notification->acknowledged_percentage }}%"></div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @else
                                                <small class="text-muted">{{ $notification->total_recipients }} recipients</small>
                                            @endif
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <div class="dropdown">
                                                <button class="action-btn bg-light" data-bs-toggle="dropdown">
                                                    <i class="material-icons-outlined">more_vert</i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('admin.notifications.statistics', $notification->id) }}">
                                                            <i class="material-icons-outlined me-2">analytics</i> View Statistics
                                                        </a>
                                                    </li>
                                                    @if(in_array($notification->status, ['draft', 'scheduled']))
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('admin.notifications.edit', $notification->id) }}">
                                                                <i class="material-icons-outlined me-2">edit</i> Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-success" href="#" onclick="sendNotificationNow({{ $notification->id }})">
                                                                <i class="material-icons-outlined me-2">send</i> Send Now
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if(in_array($notification->status, ['draft', 'scheduled', 'cancelled']))
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="deleteNotification({{ $notification->id }})">
                                                                <i class="material-icons-outlined me-2">delete</i> Delete
                                                            </a>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-5">
                                <i class="material-icons-outlined" style="font-size: 64px; color: #dee2e6;">notifications_off</i>
                                <h5 class="text-muted mt-3">No notifications found</h5>
                                <p class="text-muted">Create your first notification to get started.</p>
                            </div>
                        @endif
                    </div>
                    @if($notifications->hasPages())
                        <div class="pagination-wrapper">
                            <div class="pagination-text">
                                Showing <strong>{{ $notifications->firstItem() }}</strong> to <strong>{{ $notifications->lastItem() }}</strong> of <strong>{{ $notifications->total() }}</strong> entries
                            </div>
                            <nav aria-label="Notifications pagination">
                                <ul class="pagination">
                                    {{-- Previous Page Link --}}
                                    @if ($notifications->onFirstPage())
                                        <li class="page-item disabled">
                                            <span class="page-link"><i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $notifications->previousPageUrl() }}&per_page={{ $perPage }}"><i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous</a>
                                        </li>
                                    @endif

                                    {{-- Pagination Elements --}}
                                    @php
                                        $currentPage = $notifications->currentPage();
                                        $lastPage = $notifications->lastPage();
                                        $start = max(1, $currentPage - 2);
                                        $end = min($lastPage, $currentPage + 2);
                                    @endphp

                                    @if($start > 1)
                                        <li class="page-item"><a class="page-link" href="{{ $notifications->url(1) }}&per_page={{ $perPage }}">1</a></li>
                                        @if($start > 2)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                    @endif

                                    @for($page = $start; $page <= $end; $page++)
                                        @if ($page == $currentPage)
                                            <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                                        @else
                                            <li class="page-item"><a class="page-link" href="{{ $notifications->url($page) }}&per_page={{ $perPage }}">{{ $page }}</a></li>
                                        @endif
                                    @endfor

                                    @if($end < $lastPage)
                                        @if($end < $lastPage - 1)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                        <li class="page-item"><a class="page-link" href="{{ $notifications->url($lastPage) }}&per_page={{ $perPage }}">{{ $lastPage }}</a></li>
                                    @endif

                                    {{-- Next Page Link --}}
                                    @if ($notifications->hasMorePages())
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $notifications->nextPageUrl() }}&per_page={{ $perPage }}">Next <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_right</i></a>
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
    </div>
</main>

<!-- Create Notification Modal -->
<div class="modal fade" id="createNotificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white">
                    <i class="material-icons-outlined me-2">add_alert</i>
                    Create New Notification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.notifications.store') }}" method="POST" id="createNotificationForm">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Enter notification title" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Enter notification message" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="success">Success</option>
                                <option value="danger">Danger</option>
                                <option value="announcement">Announcement</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Delivery Method <span class="text-danger">*</span></label>
                            <select name="delivery_method" class="form-select" required>
                                <option value="web">Web Only</option>
                                <option value="email">Email Only</option>
                                <option value="mobile">Mobile Push Only</option>
                                <option value="both">Both (Email & Web)</option>
                                <option value="web_mobile">Both (Web & Mobile)</option>
                                <option value="all">All (Email, Web & Mobile)</option>
                            </select>
                            <small class="text-muted">Mobile push notifications will be sent to users with the SMS Expert app</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Target <span class="text-danger">*</span></label>
                            <select name="target_type" class="form-select" id="targetType" required>
                                <option value="all">All Customers</option>
                                <option value="specific">Specific Customers</option>
                            </select>
                        </div>
                        <div class="col-md-12" id="customerSelectDiv" style="display: none;">
                            <label class="form-label fw-semibold">Select Customers</label>
                            <select name="customer_ids[]" class="form-select" id="customerSelect" multiple style="height: 150px;">
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">
                                        {{ urldecode($customer->busname ?: $customer->contactname) }} ({{ $customer->contactemail }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple customers</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Schedule (Optional)</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" id="scheduleInput">
                            <small class="text-muted">Leave empty to save as draft or send immediately</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Options</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="requires_acknowledgment" class="form-check-input" id="requiresAck" value="1">
                                <label class="form-check-label" for="requiresAck">
                                    Require Acknowledgment (Show popup on customer dashboard)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="draft" class="btn btn-outline-primary">
                        <i class="material-icons-outlined me-1">save</i> Save as Draft
                    </button>
                    <button type="submit" name="send_now" value="1" class="btn btn-create-notification">
                        <i class="material-icons-outlined me-1">send</i> Send Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.layouts.footer')
@endsection

@push('js')
<script>
    // Auto-hide flash messages
    setTimeout(function() {
        let flashMessage = document.getElementById('flash-message');
        if (flashMessage) flashMessage.style.display = 'none';
    }, 3000);

    setTimeout(function() {
        let flashMessage = document.getElementById('flash-error-message');
        if (flashMessage) flashMessage.style.display = 'none';
    }, 3000);

    document.addEventListener('DOMContentLoaded', function() {
        // Target type toggle
        const targetType = document.getElementById('targetType');
        const customerSelectDiv = document.getElementById('customerSelectDiv');

        targetType.addEventListener('change', function() {
            customerSelectDiv.style.display = this.value === 'specific' ? 'block' : 'none';
        });

        // Per page selection change
        document.getElementById('perPageSelect').addEventListener('change', function() {
            const perPage = this.value;
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', perPage);
            url.searchParams.delete('page'); // Reset to first page when changing per_page
            window.location.href = url.toString();
        });
    });

    function sendNotificationNow(id) {
        if (confirm('Are you sure you want to send this notification now?')) {
            fetch(`/admin/notifications/${id}/send-now`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred');
            });
        }
    }

    function deleteNotification(id) {
        if (confirm('Are you sure you want to delete this notification?')) {
            fetch(`/admin/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred');
            });
        }
    }
</script>
@endpush
