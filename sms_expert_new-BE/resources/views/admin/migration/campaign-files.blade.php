@extends('admin.layouts.app')

@section('title', 'Campaign File Migration')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Campaign File Migration</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="#">Migration</a></li>
                        <li class="breadcrumb-item active">Campaign Files</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Status Cards -->
    <div class="row">
        <div class="col-md-6">
            <div class="card {{ $oldServer && $oldServer->last_test_status == 'success' ? 'border-success' : 'border-warning' }}">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-server fa-2x {{ $oldServer && $oldServer->last_test_status == 'success' ? 'text-success' : 'text-warning' }}"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1">Old Server</h5>
                            @if($oldServer && $oldServer->host)
                                <p class="mb-0 text-muted">{{ $oldServer->host }}:{{ $oldServer->port }}</p>
                                <span class="badge {{ $oldServer->last_test_status == 'success' ? 'bg-success' : 'bg-warning' }}">
                                    {{ $oldServer->last_test_status == 'success' ? 'Connected' : 'Not Tested' }}
                                </span>
                            @else
                                <p class="mb-0 text-danger">Not configured</p>
                                <a href="{{ route('admin.settings.server') }}" class="btn btn-sm btn-outline-primary mt-1">Configure</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card {{ $newServer && $newServer->last_test_status == 'success' ? 'border-success' : 'border-warning' }}">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-server fa-2x {{ $newServer && $newServer->last_test_status == 'success' ? 'text-success' : 'text-warning' }}"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1">New Server</h5>
                            @if($newServer && $newServer->host)
                                <p class="mb-0 text-muted">{{ $newServer->host }}:{{ $newServer->port }}</p>
                                <span class="badge {{ $newServer->last_test_status == 'success' ? 'bg-success' : 'bg-warning' }}">
                                    {{ $newServer->last_test_status == 'success' ? 'Connected' : 'Not Tested' }}
                                </span>
                            @else
                                <p class="mb-0 text-danger">Not configured</p>
                                <a href="{{ route('admin.settings.server') }}" class="btn btn-sm btn-outline-primary mt-1">Configure</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Migration Actions -->
    <div class="row">
        <!-- Old to New Migration -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-arrow-right me-2"></i>Old to New Migration
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Copy campaign files from the old server to the new server for migrated customers.</p>

                    <div class="alert alert-info">
                        <i class="fas fa-users me-2"></i>
                        <strong>{{ $usersReadyForOldToNew }}</strong> users eligible for file migration
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Users</label>
                        <select class="form-select" id="old-to-new-selection">
                            <option value="all">All eligible users ({{ $usersReadyForOldToNew }})</option>
                            <option value="selected">Select specific users</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="old-to-new-users-container">
                        <label class="form-label">Search and Select Users</label>
                        <input type="text" class="form-control mb-2" id="old-to-new-search" placeholder="Search by username, name, email...">
                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;" id="old-to-new-users-list">
                            <small class="text-muted">Type to search users...</small>
                        </div>
                        <div id="old-to-new-selected" class="mt-2"></div>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="startMigration('old_to_new')"
                        {{ (!$oldServer || $oldServer->last_test_status != 'success') ? 'disabled' : '' }}>
                        <i class="fas fa-play me-1"></i> Start Migration
                    </button>
                </div>
            </div>
        </div>

        <!-- New to Old Migration -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-arrow-left me-2"></i>New to Old Migration
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Copy campaign files from the new server back to the old server (rollback).</p>

                    <div class="alert alert-warning">
                        <i class="fas fa-users me-2"></i>
                        <strong>{{ $usersReadyForNewToOld }}</strong> users eligible for file migration
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Users</label>
                        <select class="form-select" id="new-to-old-selection">
                            <option value="all">All eligible users ({{ $usersReadyForNewToOld }})</option>
                            <option value="selected">Select specific users</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="new-to-old-users-container">
                        <label class="form-label">Search and Select Users</label>
                        <input type="text" class="form-control mb-2" id="new-to-old-search" placeholder="Search by username, name, email...">
                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;" id="new-to-old-users-list">
                            <small class="text-muted">Type to search users...</small>
                        </div>
                        <div id="new-to-old-selected" class="mt-2"></div>
                    </div>

                    <button type="button" class="btn btn-warning" onclick="startMigration('new_to_old')"
                        {{ (!$newServer || $newServer->last_test_status != 'success') ? 'disabled' : '' }}>
                        <i class="fas fa-play me-1"></i> Start Migration
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Status -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Queue Status
                        <button class="btn btn-sm btn-outline-secondary float-end" onclick="refreshQueueStatus()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </h5>
                </div>
                <div class="card-body">
                    <div id="queue-status">
                        <div class="text-center py-3">
                            <i class="fas fa-spinner fa-spin"></i> Loading queue status...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Migration Batches -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Recent Migration Batches
                    </h5>
                </div>
                <div class="card-body">
                    @if($recentBatches->isEmpty())
                        <p class="text-muted text-center py-3">No migration batches yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch ID</th>
                                        <th>Direction</th>
                                        <th>Started</th>
                                        <th>Total Files</th>
                                        <th>Completed</th>
                                        <th>Skipped</th>
                                        <th>Failed</th>
                                        <th>Pending</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentBatches as $batch)
                                    <tr>
                                        <td><code>{{ $batch->migration_batch_id }}</code></td>
                                        <td>
                                            @if($batch->direction == 'old_to_new')
                                                <span class="badge bg-primary">Old → New</span>
                                            @else
                                                <span class="badge bg-warning text-dark">New → Old</span>
                                            @endif
                                        </td>
                                        <td>{{ $batch->started_at ? \Carbon\Carbon::parse($batch->started_at)->format('d M Y, H:i') : '-' }}</td>
                                        <td>{{ $batch->total_files }}</td>
                                        <td><span class="text-success">{{ $batch->completed_count }}</span></td>
                                        <td><span class="text-info">{{ $batch->skipped_count }}</span></td>
                                        <td><span class="text-danger">{{ $batch->failed_count }}</span></td>
                                        <td>
                                            @if($batch->pending_count > 0)
                                                <span class="badge bg-warning">{{ $batch->pending_count }}</span>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.migration.campaign-files.batch', $batch->migration_batch_id) }}"
                                               class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Migration Progress Modal -->
<div class="modal fade" id="migrationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Migration Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="migration-progress">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                    <p>Starting migration...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let selectedUsers = {
    'old_to_new': [],
    'new_to_old': []
};
let searchTimeout;

// Toggle user selection container
document.getElementById('old-to-new-selection').addEventListener('change', function() {
    document.getElementById('old-to-new-users-container').classList.toggle('d-none', this.value === 'all');
});

document.getElementById('new-to-old-selection').addEventListener('change', function() {
    document.getElementById('new-to-old-users-container').classList.toggle('d-none', this.value === 'all');
});

// Search users
['old-to-new', 'new-to-old'].forEach(function(prefix) {
    document.getElementById(prefix + '-search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const direction = prefix.replace(/-/g, '_');
        searchTimeout = setTimeout(() => searchUsers(direction, this.value), 300);
    });
});

function searchUsers(direction, query) {
    const prefix = direction.replace(/_/g, '-');
    const listContainer = document.getElementById(prefix + '-users-list');

    if (query.length < 2) {
        listContainer.innerHTML = '<small class="text-muted">Type at least 2 characters to search...</small>';
        return;
    }

    listContainer.innerHTML = '<small class="text-muted"><i class="fas fa-spinner fa-spin"></i> Searching...</small>';

    fetch('{{ route("admin.migration.campaign-files.search-users") }}?search=' + encodeURIComponent(query) + '&direction=' + direction)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                listContainer.innerHTML = data.data.map(user => `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${user.bigid}"
                            id="user-${direction}-${user.bigid}"
                            onchange="toggleUser('${direction}', '${user.bigid}', '${user.uname}')"
                            ${selectedUsers[direction].includes(user.bigid) ? 'checked' : ''}>
                        <label class="form-check-label" for="user-${direction}-${user.bigid}">
                            <strong>${user.uname}</strong> - ${user.contactname || user.busname || 'N/A'}
                        </label>
                    </div>
                `).join('');
            } else {
                listContainer.innerHTML = '<small class="text-muted">No users found</small>';
            }
        });
}

function toggleUser(direction, bigid, uname) {
    const index = selectedUsers[direction].indexOf(bigid);
    if (index === -1) {
        selectedUsers[direction].push(bigid);
    } else {
        selectedUsers[direction].splice(index, 1);
    }
    updateSelectedDisplay(direction);
}

function updateSelectedDisplay(direction) {
    const prefix = direction.replace(/_/g, '-');
    const container = document.getElementById(prefix + '-selected');
    container.innerHTML = selectedUsers[direction].length > 0
        ? `<span class="badge bg-primary">${selectedUsers[direction].length} user(s) selected</span>`
        : '';
}

function startMigration(direction) {
    const prefix = direction.replace(/_/g, '-');
    const selection = document.getElementById(prefix + '-selection').value;

    const data = {
        direction: direction,
        user_selection: selection,
        user_bigids: selection === 'selected' ? selectedUsers[direction] : []
    };

    if (selection === 'selected' && selectedUsers[direction].length === 0) {
        alert('Please select at least one user');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('migrationModal'));
    modal.show();

    fetch('{{ route("admin.migration.campaign-files.start") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.getElementById('migration-progress').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Migration Started!</strong><br>
                    Batch ID: <code>${result.data.batch_id}</code><br>
                    Users: ${result.data.user_count}<br>
                    <small>The migration will run in the background. Refresh this page to see progress.</small>
                </div>
            `;
            setTimeout(() => location.reload(), 3000);
        } else {
            document.getElementById('migration-progress').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>Failed:</strong> ${result.message}
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('migration-progress').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-times-circle me-2"></i>
                <strong>Error:</strong> ${error.message}
            </div>
        `;
    });
}

function refreshQueueStatus() {
    const container = document.getElementById('queue-status');
    container.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    fetch('{{ route("admin.migration.campaign-files.queue-status") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = `
                    <div class="row text-center">
                        <div class="col-md-4">
                            <h3 class="mb-0">${data.data.queue_depth}</h3>
                            <small class="text-muted">Jobs in Queue</small>
                        </div>
                    </div>
                `;
            } else {
                container.innerHTML = '<div class="alert alert-warning mb-0">Unable to fetch queue status</div>';
            }
        });
}

// Initial load
refreshQueueStatus();
</script>
@endpush
