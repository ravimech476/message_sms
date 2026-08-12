@extends('admin.layouts.app')

@section('title', 'View Notification')

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Notification Details</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0" style="background: none;">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.notifications.index') }}" class="text-decoration-none">Notifications</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">View</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                @if(in_array($notification->status, ['draft', 'scheduled']))
                    <a href="{{ route('admin.notifications.edit', $notification->id) }}" class="btn btn-warning me-2">
                        <i class="material-icons-outlined me-1" style="vertical-align: middle;">edit</i>
                        Edit
                    </a>
                    <button type="button" class="btn btn-success" onclick="sendNow({{ $notification->id }})">
                        <i class="material-icons-outlined me-1" style="vertical-align: middle;">send</i>
                        Send Now
                    </button>
                @endif
            </div>
        </div>
        <!--end breadcrumb-->

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <!-- Notification Content -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-{{ $notification->type == 'info' ? 'info' : ($notification->type == 'warning' ? 'warning' : ($notification->type == 'success' ? 'success' : 'danger')) }} text-white">
                        <h5 class="mb-0">{{ $notification->title }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            {!! nl2br(e($notification->message)) !!}
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted">Type</small>
                                <div><span class="badge {{ $notification->type_badge_class }}">{{ ucfirst($notification->type) }}</span></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Priority</small>
                                <div><span class="badge {{ $notification->priority_badge_class }}">{{ ucfirst($notification->priority) }}</span></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Status</small>
                                <div><span class="badge {{ $notification->status_badge_class }}">{{ ucfirst($notification->status) }}</span></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Target</small>
                                <div><span class="badge bg-dark">{{ $notification->target_type == 'all' ? 'All Customers' : 'Specific' }}</span></div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">Delivery Methods</small>
                                <div>
                                    @if($notification->send_web)
                                        <span class="badge bg-primary me-1"><i class="material-icons-outlined" style="font-size: 12px;">web</i> Web</span>
                                    @endif
                                    @if($notification->send_email)
                                        <span class="badge bg-secondary me-1"><i class="material-icons-outlined" style="font-size: 12px;">email</i> Email</span>
                                    @endif
                                    @if($notification->requires_acknowledgement)
                                        <span class="badge bg-warning text-dark"><i class="material-icons-outlined" style="font-size: 12px;">verified</i> Requires Ack</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Scheduled At</small>
                                <div>{{ $notification->scheduled_at ? $notification->scheduled_at->format('d M Y H:i') : 'Not scheduled' }}</div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Sent At</small>
                                <div>{{ $notification->sent_at ? $notification->sent_at->format('d M Y H:i') : 'Not sent' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recipients List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="material-icons-outlined me-2" style="vertical-align: middle;">people</i>
                            Recipients ({{ $notification->recipients->count() }})
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light position-sticky top-0">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Email</th>
                                        <th width="80">Read</th>
                                        <th width="80">Ack</th>
                                        <th width="80">Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($notification->recipients as $recipient)
                                        <tr>
                                            <td>
                                                <strong>{{ urldecode($recipient->user->busname ?? $recipient->user->contactname ?? 'N/A') }}</strong>
                                                <br><small class="text-muted">{{ $recipient->user->uname ?? '' }}</small>
                                            </td>
                                            <td>{{ $recipient->user->contactemail ?? 'N/A' }}</td>
                                            <td>
                                                @if($recipient->is_read)
                                                    <span class="badge bg-success">Yes</span>
                                                    <br><small>{{ $recipient->read_at?->format('d/m H:i') }}</small>
                                                @else
                                                    <span class="badge bg-secondary">No</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($notification->requires_acknowledgement)
                                                    @if($recipient->is_acknowledged)
                                                        <span class="badge bg-success">Yes</span>
                                                        <br><small>{{ $recipient->acknowledged_at?->format('d/m H:i') }}</small>
                                                    @else
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-secondary">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($notification->send_email)
                                                    @if($recipient->email_sent)
                                                        <span class="badge bg-success">Sent</span>
                                                    @else
                                                        <span class="badge bg-secondary">Pending</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-secondary">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No recipients</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Statistics -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="material-icons-outlined me-2" style="vertical-align: middle;">analytics</i>
                            Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-primary mb-0">{{ $notification->recipients->count() }}</h3>
                                    <small class="text-muted">Total Recipients</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-success mb-0">{{ $notification->recipients->where('is_read', true)->count() }}</h3>
                                    <small class="text-muted">Read</small>
                                </div>
                            </div>
                            @if($notification->requires_acknowledgement)
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-warning mb-0">{{ $notification->recipients->where('is_acknowledged', true)->count() }}</h3>
                                    <small class="text-muted">Acknowledged</small>
                                </div>
                            </div>
                            @endif
                            @if($notification->send_email)
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-info mb-0">{{ $notification->recipients->where('email_sent', true)->count() }}</h3>
                                    <small class="text-muted">Emails Sent</small>
                                </div>
                            </div>
                            @endif
                        </div>

                        @if($notification->recipients->count() > 0)
                        <div class="mt-3">
                            <label class="small text-muted">Read Rate</label>
                            <div class="progress" style="height: 25px;">
                                @php
                                    $readPercent = round(($notification->recipients->where('is_read', true)->count() / $notification->recipients->count()) * 100);
                                @endphp
                                <div class="progress-bar bg-success" style="width: {{ $readPercent }}%">{{ $readPercent }}%</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Meta Info -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="material-icons-outlined me-2" style="vertical-align: middle;">info</i>
                            Meta Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Created By</td>
                                <td>{{ $notification->creator->name ?? 'System' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Created At</td>
                                <td>{{ $notification->created_at->format('d M Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Updated At</td>
                                <td>{{ $notification->updated_at->format('d M Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('scripts')
<script>
function sendNow(id) {
    if (!confirm('Are you sure you want to send this notification now?')) return;
    
    fetch(`{{ url('admin/notifications') }}/${id}/send-now`, {
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
            alert(data.message || 'Failed to send notification');
        }
    });
}
</script>
@endpush
