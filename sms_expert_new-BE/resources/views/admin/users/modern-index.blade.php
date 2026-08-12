@extends('admin.layouts.modern-app')

@section('title', 'User Management - SMS Expert Admin')

@push('styles')
<style>
    .user-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .user-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
    }

    .user-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .status-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .status-suspended {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    .table-modern {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        overflow: hidden;
        border: 1px solid var(--border-light);
    }

    .table-modern thead {
        background: linear-gradient(135deg, var(--light-bg) 0%, #ffffff 100%);
    }

    .table-modern th {
        border: none;
        font-weight: 600;
        color: var(--text-primary);
        padding: 1rem;
    }

    .table-modern td {
        border: none;
        border-bottom: 1px solid var(--border-light);
        padding: 1rem;
        vertical-align: middle;
    }

    .table-modern tbody tr:last-child td {
        border-bottom: none;
    }

    .table-modern tbody tr:hover {
        background: rgba(234, 97, 24, 0.02);
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action {
        padding: 0.5rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-secondary);
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .btn-action:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .filters-section {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--border-light);
        margin-bottom: 2rem;
    }

    .search-box {
        position: relative;
        max-width: 400px;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 3rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        background: var(--card-bg);
        transition: all 0.2s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(234, 97, 24, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    .pagination-modern {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .page-btn {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-secondary);
        border-radius: var(--radius-sm);
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .page-btn:hover,
    .page-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        text-decoration: none;
    }

    .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* User Dashboard Modal */
    .user-dashboard-modal .modal-dialog {
        max-width: 1200px;
    }

    .dashboard-tabs {
        border-bottom: 1px solid var(--border-light);
        margin-bottom: 2rem;
    }

    .dashboard-tab {
        padding: 1rem 1.5rem;
        border: none;
        background: none;
        color: var(--text-secondary);
        font-weight: 500;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
    }

    .dashboard-tab.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }

    .dashboard-content {
        min-height: 400px;
    }

    .mini-stat-card {
        background: var(--light-bg);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        text-align: center;
        border: 1px solid var(--border-light);
    }

    .mini-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.2rem;
        color: white;
    }

    .mini-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .mini-stat-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
</style>
@endpush

@section('content')
<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">User Management</h1>
            <p class="text-muted">Manage customer accounts, permissions, and settings</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="exportUsers()">
                <i class="material-icons-outlined me-1">download</i>
                Export Users
            </button>
            <a href="{{ route('admin.customer.create') }}" class="btn btn-primary-modern">
                <i class="material-icons-outlined me-1">person_add</i>
                Add Customer
            </a>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="stats-grid mb-4">
        <div class="stat-card primary">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">people</i>
                </div>
                <div class="stat-trend trend-up">
                    <i class="material-icons-outlined">trending_up</i>
                    +5.2%
                </div>
            </div>
            <div class="stat-value">{{ number_format($users->total() ?? 0) }}</div>
            <div class="stat-label">Total Users</div>
            <div class="text-muted small">All registered customers</div>
        </div>

        <div class="stat-card success">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">verified_user</i>
                </div>
                <div class="stat-trend trend-up">
                    <i class="material-icons-outlined">trending_up</i>
                    +8.1%
                </div>
            </div>
            <div class="stat-value">{{ number_format($activeUsers ?? 0) }}</div>
            <div class="stat-label">Active Users</div>
            <div class="text-muted small">Currently active accounts</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">schedule</i>
                </div>
                <div class="stat-trend trend-down">
                    <i class="material-icons-outlined">trending_down</i>
                    -2.3%
                </div>
            </div>
            <div class="stat-value">{{ number_format($newUsersThisMonth ?? 0) }}</div>
            <div class="stat-label">New This Month</div>
            <div class="text-muted small">Recently registered users</div>
        </div>

        <div class="stat-card info">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">block</i>
                </div>
                <div class="stat-trend trend-down">
                    <i class="material-icons-outlined">trending_down</i>
                    -1.2%
                </div>
            </div>
            <div class="stat-value">{{ number_format($blockedUsers ?? 0) }}</div>
            <div class="stat-label">Blocked Users</div>
            <div class="text-muted small">Suspended or blocked accounts</div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="search-box">
                    <i class="material-icons-outlined search-icon">search</i>
                    <input type="text" class="search-input" placeholder="Search users..." id="userSearch">
                </div>
            </div>
            <div class="col-md-8">
                <div class="d-flex gap-3 align-items-center">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <select class="form-select" id="accountTypeFilter">
                        <option value="">All Types</option>
                        <option value="prepaid">Prepaid</option>
                        <option value="postpaid">Postpaid</option>
                    </select>
                    <button class="btn btn-outline-secondary" onclick="resetFilters()">
                        <i class="material-icons-outlined">clear</i>
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-modern">
        <table class="table mb-0" id="usersTable">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact Info</th>
                    <th>Account Type</th>
                    <th>SMS Usage</th>
                    <th>Wallet Balance</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="user-avatar" style="background: {{ ['#ea6118', '#10b981', '#3b82f6', '#f59e0b', '#ef4444'][($user->bigid ?? 0) % 5] }}">
                                {{ strtoupper(substr(urldecode($user->contactname ?? 'U'), 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-semibold">{{ urldecode($user->contactname ?? 'N/A') }}</div>
                                <small class="text-muted">ID: {{ $user->bigid ?? 'N/A' }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>
                            <div class="fw-medium">{{ $user->email ?? 'No email' }}</div>
                            <small class="text-muted">{{ $user->telephone ?? 'No phone' }}</small>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-{{ $user->payment_type === 'postpaid' ? 'success' : 'primary' }}">
                            {{ ucfirst($user->payment_type ?? 'prepaid') }}
                        </span>
                    </td>
                    <td>
                        <div class="text-center">
                            <div class="fw-semibold">{{ number_format(($user->smsg_server1_sent ?? 0) + ($user->smsg_server2_sent ?? 0)) }}</div>
                            <small class="text-muted">SMS Sent</small>
                        </div>
                    </td>
                    <td>
                        <div class="text-center">
                            <div class="fw-semibold text-{{ ($user->smsg_wallet ?? 0) > 0 ? 'success' : 'danger' }}">
                                £{{ number_format($user->smsg_wallet ?? 0, 2) }}
                            </div>
                            <small class="text-muted">Balance</small>
                        </div>
                    </td>
                    <td>
                        @php
                            $status = $user->status ?? 'active';
                            $statusClass = $status === 'active' ? 'status-active' : ($status === 'suspended' ? 'status-suspended' : 'status-inactive');
                        @endphp
                        <span class="user-status {{ $statusClass }}">
                            {{ ucfirst($status) }}
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">
                            @if($user->last_login)
                                @if(is_numeric($user->last_login))
                                    {{ \Carbon\Carbon::createFromTimestamp($user->last_login)->diffForHumans() }}
                                @else
                                    {{ \Carbon\Carbon::parse($user->last_login)->diffForHumans() }}
                                @endif
                            @else
                                Never
                            @endif
                        </small>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action" onclick="showUserDashboard({{ $user->bigid }})" title="View Dashboard">
                                <i class="material-icons-outlined">dashboard</i>
                            </button>
                            <a href="{{ route('admin.user.show', $user->bigid) }}" class="btn-action" title="View Profile">
                                <i class="material-icons-outlined">visibility</i>
                            </a>
                            <button class="btn-action" onclick="editUser({{ $user->bigid }})" title="Edit User">
                                <i class="material-icons-outlined">edit</i>
                            </button>
                            <button class="btn-action" onclick="toggleUserStatus({{ $user->bigid }})" title="Toggle Status">
                                <i class="material-icons-outlined">{{ $status === 'active' ? 'block' : 'check_circle' }}</i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="material-icons-outlined fs-1 mb-3">people_outline</i>
                            <p class="mb-0">No users found</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
    <div class="pagination-modern">
        <a href="{{ $users->previousPageUrl() }}" class="page-btn {{ !$users->previousPageUrl() ? 'disabled' : '' }}">
            <i class="material-icons-outlined">chevron_left</i>
        </a>
        
        @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
            <a href="{{ $url }}" class="page-btn {{ $page == $users->currentPage() ? 'active' : '' }}">
                {{ $page }}
            </a>
        @endforeach
        
        <a href="{{ $users->nextPageUrl() }}" class="page-btn {{ !$users->nextPageUrl() ? 'disabled' : '' }}">
            <i class="material-icons-outlined">chevron_right</i>
        </a>
    </div>
    @endif
</div>

<!-- User Dashboard Modal -->
<div class="modal fade user-dashboard-modal" id="userDashboardModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="user-avatar bg-primary" id="modalUserAvatar">U</div>
                    <div>
                        <h5 class="modal-title mb-0" id="modalUserName">User Dashboard</h5>
                        <small class="text-muted" id="modalUserEmail">user@example.com</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="dashboard-tabs">
                    <button class="dashboard-tab active" data-tab="overview">Overview</button>
                    <button class="dashboard-tab" data-tab="sms-usage">SMS Usage</button>
                    <button class="dashboard-tab" data-tab="wallet">Wallet</button>
                    <button class="dashboard-tab" data-tab="activity">Activity Log</button>
                    <button class="dashboard-tab" data-tab="settings">Settings</button>
                </div>
                
                <div class="dashboard-content" id="dashboardContent">
                    <!-- Content will be loaded here -->
                    <div class="text-center py-5">
                        <div class="loading-skeleton" style="height: 200px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality
    const searchInput = document.getElementById('userSearch');
    const statusFilter = document.getElementById('statusFilter');
    const accountTypeFilter = document.getElementById('accountTypeFilter');
    
    // Debounced search
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            filterUsers();
        }, 300);
    });
    
    statusFilter.addEventListener('change', filterUsers);
    accountTypeFilter.addEventListener('change', filterUsers);
    
    // Dashboard tab switching
    const dashboardTabs = document.querySelectorAll('.dashboard-tab');
    dashboardTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            dashboardTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            loadDashboardTab(this.dataset.tab);
        });
    });
});

function filterUsers() {
    const search = document.getElementById('userSearch').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const accountType = document.getElementById('accountTypeFilter').value.toLowerCase();
    
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        const userName = row.querySelector('.fw-semibold')?.textContent.toLowerCase() || '';
        const userEmail = row.querySelector('.fw-medium')?.textContent.toLowerCase() || '';
        const userStatus = row.querySelector('.user-status')?.textContent.toLowerCase() || '';
        const userAccountType = row.querySelector('.badge')?.textContent.toLowerCase() || '';
        
        const matchesSearch = userName.includes(search) || userEmail.includes(search);
        const matchesStatus = !status || userStatus.includes(status);
        const matchesAccountType = !accountType || userAccountType.includes(accountType);
        
        if (matchesSearch && matchesStatus && matchesAccountType) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('userSearch').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('accountTypeFilter').value = '';
    filterUsers();
}

function showUserDashboard(userId) {
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('userDashboardModal'));
    modal.show();
    
    // Load user data
    loadUserDashboardData(userId);
}

function loadUserDashboardData(userId) {
    // Simulate loading user data
    fetch(`/admin/user/${userId}/dashboard`)
        .then(response => response.json())
        .then(data => {
            // Update modal header
            document.getElementById('modalUserName').textContent = data.name || 'User Dashboard';
            document.getElementById('modalUserEmail').textContent = data.email || 'user@example.com';
            document.getElementById('modalUserAvatar').textContent = (data.name || 'U').charAt(0).toUpperCase();
            
            // Load overview tab by default
            loadDashboardTab('overview', data);
        })
        .catch(error => {
            console.error('Error loading user dashboard:', error);
            showNotification('Error loading user dashboard', 'danger');
        });
}

function loadDashboardTab(tab, userData = null) {
    const content = document.getElementById('dashboardContent');
    
    switch(tab) {
        case 'overview':
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-3">
                        <div class="mini-stat-card">
                            <div class="mini-stat-icon bg-primary">
                                <i class="material-icons-outlined">send</i>
                            </div>
                            <div class="mini-stat-value">15,420</div>
                            <div class="mini-stat-label">SMS Sent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mini-stat-card">
                            <div class="mini-stat-icon bg-success">
                                <i class="material-icons-outlined">account_balance_wallet</i>
                            </div>
                            <div class="mini-stat-value">£250.00</div>
                            <div class="mini-stat-label">Wallet Balance</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mini-stat-card">
                            <div class="mini-stat-icon bg-info">
                                <i class="material-icons-outlined">trending_up</i>
                            </div>
                            <div class="mini-stat-value">£1,580</div>
                            <div class="mini-stat-label">Total Spent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mini-stat-card">
                            <div class="mini-stat-icon bg-warning">
                                <i class="material-icons-outlined">schedule</i>
                            </div>
                            <div class="mini-stat-value">2 hours ago</div>
                            <div class="mini-stat-label">Last Active</div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="modern-card">
                            <div class="card-header-modern">
                                <h6 class="card-title-modern">SMS Usage Trend</h6>
                            </div>
                            <div class="p-3">
                                <canvas id="userSmsChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="modern-card">
                            <div class="card-header-modern">
                                <h6 class="card-title-modern">Account Details</h6>
                            </div>
                            <div class="p-3">
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Account Type</label>
                                    <div class="fw-semibold">Prepaid</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Registration Date</label>
                                    <div class="fw-semibold">March 15, 2024</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Status</label>
                                    <div><span class="user-status status-active">Active</span></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Payment Method</label>
                                    <div class="fw-semibold">Credit Card ending ****1234</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'sms-usage':
            content.innerHTML = `
                <div class="modern-card">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern">SMS Usage Statistics</h6>
                    </div>
                    <div class="p-3">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Messages Sent</th>
                                        <th>Delivery Rate</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2024-07-31</td>
                                        <td>250</td>
                                        <td>98.5%</td>
                                        <td>£12.50</td>
                                        <td><span class="badge bg-success">Delivered</span></td>
                                    </tr>
                                    <tr>
                                        <td>2024-07-30</td>
                                        <td>180</td>
                                        <td>97.2%</td>
                                        <td>£9.00</td>
                                        <td><span class="badge bg-success">Delivered</span></td>
                                    </tr>
                                    <tr>
                                        <td>2024-07-29</td>
                                        <td>320</td>
                                        <td>99.1%</td>
                                        <td>£16.00</td>
                                        <td><span class="badge bg-success">Delivered</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'wallet':
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="modern-card">
                            <div class="card-header-modern">
                                <h6 class="card-title-modern">Wallet Balance</h6>
                            </div>
                            <div class="p-3 text-center">
                                <div class="h2 text-success mb-3">£250.00</div>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary">Add Credit</button>
                                    <button class="btn btn-outline-secondary">Transaction History</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="modern-card">
                            <div class="card-header-modern">
                                <h6 class="card-title-modern">Recent Transactions</h6>
                            </div>
                            <div class="p-3">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                                <th>Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>2024-07-31</td>
                                                <td>SMS Campaign - Marketing</td>
                                                <td class="text-danger">-£12.50</td>
                                                <td>£250.00</td>
                                            </tr>
                                            <tr>
                                                <td>2024-07-30</td>
                                                <td>Wallet Top-up</td>
                                                <td class="text-success">+£100.00</td>
                                                <td>£262.50</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'activity':
            content.innerHTML = `
                <div class="modern-card">
                    <div class="card-header-modern">
                        <h6 class="card-title-modern">Activity Log</h6>
                    </div>
                    <div class="p-3">
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon bg-primary text-white">
                                    <i class="material-icons-outlined">login</i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">User logged in</div>
                                    <div class="activity-description">IP: 192.168.1.100</div>
                                    <div class="activity-time">2 hours ago</div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon bg-success text-white">
                                    <i class="material-icons-outlined">send</i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">SMS campaign sent</div>
                                    <div class="activity-description">250 messages delivered</div>
                                    <div class="activity-time">3 hours ago</div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon bg-info text-white">
                                    <i class="material-icons-outlined">payment</i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">Payment processed</div>
                                    <div class="activity-description">£100.00 wallet top-up</div>
                                    <div class="activity-time">1 day ago</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'settings':
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="modern-card">
                            <div class="card-header-modern">
                                <h6 class="card-title-modern">Account Settings</h6>
                            </div>
                            <div class="p-3">
                                <div class="mb-3">
                                    <label class="form-label">Account Status</label>
                                    <select class="form-select">
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Account Type</label>
                                    <select class="form-select">
                                        <option value="prepaid">Prepaid</option>
                                        <option value="postpaid">Postpaid</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMS Rate (per message)</label>
                                    <input type="number" class="form-control" value="0.05" step="0.01">
                                </div>
                                <button class="btn btn-primary">Update Settings</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="modern-card">
                            <div class="card-header-modern">
                                <h6 class="card-title-modern">Security Settings</h6>
                            </div>
                            <div class="p-3">
                                <div class="mb-3">
                                    <label class="form-label">IP Restrictions</label>
                                    <textarea class="form-control" rows="3" placeholder="Enter allowed IP addresses (one per line)"></textarea>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="requireEmailVerification">
                                        <label class="form-check-label" for="requireEmailVerification">
                                            Require email verification for login
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable2FA">
                                        <label class="form-check-label" for="enable2FA">
                                            Enable two-factor authentication
                                        </label>
                                    </div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary">Update Security</button>
                                    <button class="btn btn-outline-warning">Reset Password</button>
                                    <button class="btn btn-outline-danger">Force Logout</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
    }
}

function editUser(userId) {
    // Redirect to edit user page or show edit modal
    window.location.href = `/admin/user/${userId}/edit`;
}

function toggleUserStatus(userId) {
    if (confirm('Are you sure you want to toggle this user\'s status?')) {
        fetch(`/admin/user/${userId}/toggle-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('User status updated successfully', 'success');
                location.reload();
            } else {
                showNotification('Failed to update user status', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred', 'danger');
        });
    }
}

function exportUsers() {
    showNotification('Exporting users...', 'info');
    window.location.href = '/admin/users/export';
}
</script>
@endpush