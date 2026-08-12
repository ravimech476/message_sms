@php
    $prefix = $userType ?? 'customer'; // customer or admin
@endphp

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="material-icons-outlined text-primary" style="font-size: 32px;">trending_up</i>
                <h3 class="mt-2 mb-0" id="{{ $prefix }}-stat-total-activities">0</h3>
                <small class="text-muted">Total Activities</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="material-icons-outlined text-success" style="font-size: 32px;">group</i>
                <h3 class="mt-2 mb-0" id="{{ $prefix }}-stat-unique-users">0</h3>
                <small class="text-muted">Unique Users</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="material-icons-outlined text-info" style="font-size: 32px;">storage</i>
                <h3 class="mt-2 mb-0" id="{{ $prefix }}-stat-total-queries">0</h3>
                <small class="text-muted">Total Queries</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="material-icons-outlined text-danger" style="font-size: 32px;">error_outline</i>
                <h3 class="mt-2 mb-0" id="{{ $prefix }}-stat-failed-requests">0</h3>
                <small class="text-muted">Failed Requests</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="material-icons-outlined text-warning" style="font-size: 32px;">speed</i>
                <h3 class="mt-2 mb-0" id="{{ $prefix }}-stat-avg-execution">0 ms</h3>
                <small class="text-muted">Avg Execution Time</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" id="{{ $prefix }}-start-date"
                    onchange="applyFilters()">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" id="{{ $prefix }}-end-date" onchange="applyFilters()">
            </div>
            <div class="col-md-2">
                <label class="form-label">User</label>
                <select class="form-select" id="{{ $prefix }}-user-filter" onchange="applyFilters()">
                    <option value="all">All Users</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Action</label>
                <select class="form-select" id="{{ $prefix }}-action-filter" onchange="applyFilters()">
                    <option value="all">All Actions</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="{{ $prefix }}-search-input" placeholder="Search..."
                    onkeyup="applyFilters()">
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12 text-end">
                <button class="btn btn-primary me-2" onclick="applyFilters()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px;">filter_list</i> Apply Filters
                </button>
                <button class="btn btn-secondary me-2" onclick="clearFilters()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px;">clear</i> Clear
                </button>
                <button class="btn btn-success" onclick="exportActivityLogs()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px;">file_download</i> Export CSV
                </button>
            </div>
        </div>

        {{-- <div class="row mt-3">
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" onclick="applyFilters()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px;">filter_list</i> Apply Filters
                </button>
                <button class="btn btn-secondary" onclick="clearFilters()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px;">clear</i> Clear
                </button>
                <button class="btn btn-success" onclick="exportActivityLogs()">
                    <i class="material-icons-outlined me-1" style="font-size: 18px;">file_download</i> Export CSV
                </button>
            </div>
        </div> --}}
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Page</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Exec Time</th>
                        <th>Queries</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="{{ $prefix }}-activity-logs-tbody">
                    <tr>
                        <td colspan="9" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"></span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div id="{{ $prefix }}-pagination-info" class="text-muted"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 text-muted small">Show:</label>
                    <select class="form-select form-select-sm" id="{{ $prefix }}-per-page"
                        style="width: auto;" onchange="changePerPage(this.value)">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-muted small">entries</span>
                </div>
            </div>
            <nav id="{{ $prefix }}-pagination-nav"></nav>
        </div>
    </div>
</div>
