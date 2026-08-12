<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0" style="color: white;"><i class="material-icons-outlined me-2" style="vertical-align: middle">history</i>User Activity Logs</h5>
            </div>
            <div class="card-body">
                <!-- Sub-tabs for Customer and Admin -->
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="customer-activity-tab" data-bs-toggle="pill" 
                                data-bs-target="#customer-activity" type="button" role="tab" 
                                onclick="switchUserType('customer')">
                            <i class="material-icons-outlined me-1">person</i> Customer Activities
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-activity-tab" data-bs-toggle="pill" 
                                data-bs-target="#admin-activity" type="button" role="tab"
                                onclick="switchUserType('admin')">
                            <i class="material-icons-outlined me-1">admin_panel_settings</i> Admin Activities
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Customer Activity Tab -->
                    <div class="tab-pane fade show active" id="customer-activity" role="tabpanel" data-user-type="customer">
                        @include('admin.settings.partials.activity_logs_content', ['userType' => 'customer'])
                    </div>

                    <!-- Admin Activity Tab -->
                    <div class="tab-pane fade" id="admin-activity" role="tabpanel" data-user-type="admin">
                        @include('admin.settings.partials.activity_logs_content', ['userType' => 'admin'])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Detail Modal (Shared by both tabs) -->
<div class="modal fade" id="activityDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="material-icons-outlined me-2" style="vertical-align: middle;">info</i>Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <strong>Timestamp:</strong>
                        <p id="modal-timestamp" class="text-muted">-</p>
                    </div>
                    <div class="col-md-6">
                        <strong>User:</strong>
                        <p id="modal-user" class="text-muted">-</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Action:</strong>
                        <p id="modal-action" class="text-muted">-</p>
                    </div>
                    <div class="col-md-6">
                        <strong>HTTP Method:</strong>
                        <p id="modal-method" class="text-muted">-</p>
                    </div>
                    <div class="col-12">
                        <strong>Page Name:</strong>
                        <p id="modal-page" class="text-muted">-</p>
                    </div>
                    <div class="col-12">
                        <strong>Page URL:</strong>
                        <p id="modal-url" class="text-muted text-break">-</p>
                    </div>
                    <div class="col-12">
                        <strong>Description:</strong>
                        <p id="modal-description" class="text-muted">-</p>
                    </div>
                    <div class="col-md-4">
                        <strong>IP Address:</strong>
                        <p id="modal-ip" class="text-muted">-</p>
                    </div>
                    <div class="col-md-4">
                        <strong>Status Code:</strong>
                        <p id="modal-status" class="text-muted">-</p>
                    </div>
                    <div class="col-md-4">
                        <strong>Execution Time:</strong>
                        <p id="modal-execution-time" class="text-muted">-</p>
                    </div>
                    <div class="col-12">
                        <strong>Request Data:</strong>
                        <pre id="modal-request-data" class="bg-light p-3" style="max-height: 200px; overflow-y: auto;">-</pre>
                    </div>
                    <div class="col-12">
                        <strong>Queries Executed:</strong>
                        <div id="modal-queries" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
let currentUserType = 'customer';
let isInitialized = false;
let currentPage = 1;
let perPage = 10;

function switchUserType(type) {
    console.log('Switching to user type:', type);
    currentUserType = type;
    currentPage = 1; // Reset to first page when switching tabs
    if (isInitialized) {
        loadActivityLogs();
        loadStatistics();
        loadFilters();
    }
}

// Get current active tab's elements
function getElements() {
    const prefix = currentUserType;
    return {
        tbody: document.getElementById(`${prefix}-activity-logs-tbody`),
        statTotal: document.getElementById(`${prefix}-stat-total-activities`),
        statUsers: document.getElementById(`${prefix}-stat-unique-users`),
        statQueries: document.getElementById(`${prefix}-stat-total-queries`),
        statFailed: document.getElementById(`${prefix}-stat-failed-requests`),
        statAvg: document.getElementById(`${prefix}-stat-avg-execution`),
        startDate: document.getElementById(`${prefix}-start-date`),
        endDate: document.getElementById(`${prefix}-end-date`),
        userFilter: document.getElementById(`${prefix}-user-filter`),
        actionFilter: document.getElementById(`${prefix}-action-filter`),
        searchInput: document.getElementById(`${prefix}-search-input`),
        paginationInfo: document.getElementById(`${prefix}-pagination-info`),
        paginationNav: document.getElementById(`${prefix}-pagination-nav`)
    };
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing Activity Logs');
    
    // Check if we're on the activity tab initially
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'activity') {
        console.log('Activity tab in URL, initializing...');
        setTimeout(() => initializeActivityLogs(), 100);
    }
    
    // Initialize when clicking on activity tab
    const activityTab = document.querySelector('[href*="tab=activity"]');
    if (activityTab) {
        activityTab.addEventListener('click', function() {
            console.log('Activity tab clicked');
            setTimeout(function() {
                if (!isInitialized) {
                    initializeActivityLogs();
                }
            }, 200);
        });
    }
});

function initializeActivityLogs() {
    console.log('Initializing Activity Logs for user type:', currentUserType);
    isInitialized = true;
    loadActivityLogs();
    loadStatistics();
    loadFilters();
}

function loadActivityLogs() {
    const filters = getFilters();
    const elements = getElements();
    
    if (!elements.tbody) {
        console.error('Activity logs table body not found for:', currentUserType);
        return;
    }
    
    console.log('Loading activity logs for:', currentUserType, 'with filters:', filters);
    
    // Show loading state
    elements.tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visibly-hidden"></span></div></td></tr>';
    
    // Add pagination parameters
    filters.page = currentPage;
    filters.per_page = perPage;
    
    fetch(`/admin/activity-logs/data?user_type=${currentUserType}&${new URLSearchParams(filters)}`)
        .then(response => {
            console.log('Activity logs response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Activity logs data received:', data);
            if (data.success) {
                renderActivityLogs(data.data, data.pagination);
            } else {
                elements.tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Error loading data: ' + (data.message || 'Unknown error') + '</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading activity logs:', error);
            elements.tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Failed to load activity logs. Please try again.</td></tr>';
        });
}

function loadStatistics() {
    const filters = getFilters();
    const elements = getElements();
    
    console.log('Loading statistics for:', currentUserType, 'with filters:', filters);
    
    fetch(`/admin/activity-logs/statistics?user_type=${currentUserType}&${new URLSearchParams(filters)}`)
        .then(response => {
            console.log('Statistics response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Statistics data received:', data);
            if (data.success && data.statistics) {
                renderStatistics(data.statistics, elements);
            } else {
                console.error('Statistics returned success=false or no data:', data);
            }
        })
        .catch(error => {
            console.error('Error loading statistics:', error);
            // Set default values on error
            if (elements.statTotal) elements.statTotal.textContent = '0';
            if (elements.statUsers) elements.statUsers.textContent = '0';
            if (elements.statQueries) elements.statQueries.textContent = '0';
            if (elements.statFailed) elements.statFailed.textContent = '0';
            if (elements.statAvg) elements.statAvg.textContent = '0 ms';
        });
}

function loadFilters() {
    const elements = getElements();
    console.log('Loading filters for user type:', currentUserType);
    
    // Load actions
    fetch(`/admin/activity-logs/actions?user_type=${currentUserType}`)
        .then(response => response.ok ? response.json() : Promise.reject('Failed'))
        .then(data => {
            console.log('Actions data received:', data);
            if (data.success && elements.actionFilter) {
                const currentValue = elements.actionFilter.value;
                elements.actionFilter.innerHTML = '<option value="all">All Actions</option>';
                data.actions.forEach(action => {
                    const formattedAction = action.replace(/_/g, ' ').toUpperCase();
                    elements.actionFilter.innerHTML += `<option value="${action}">${formattedAction}</option>`;
                });
                if (currentValue && currentValue !== 'all') {
                    elements.actionFilter.value = currentValue;
                }
                console.log('Actions loaded:', data.actions.length);
            }
        })
        .catch(error => console.error('Error loading actions:', error));

    // Load users
    fetch(`/admin/activity-logs/users?user_type=${currentUserType}`)
        .then(response => response.ok ? response.json() : Promise.reject('Failed'))
        .then(data => {
            console.log('Users data received:', data);
            if (data.success && elements.userFilter) {
                const currentValue = elements.userFilter.value;
                elements.userFilter.innerHTML = '<option value="all">All Users</option>';
                data.users.forEach(user => {
                    elements.userFilter.innerHTML += `<option value="${user.id}">${user.name} (${user.ref})</option>`;
                });
                if (currentValue && currentValue !== 'all') {
                    elements.userFilter.value = currentValue;
                }
                console.log('Users loaded:', data.users.length);
            }
        })
        .catch(error => console.error('Error loading users:', error));
}

function getFilters() {
    const elements = getElements();
    return {
        start_date: elements.startDate?.value || '',
        end_date: elements.endDate?.value || '',
        user_id: elements.userFilter?.value || 'all',
        action: elements.actionFilter?.value || 'all',
        search: elements.searchInput?.value || '',
    };
}

function applyFilters() {
    console.log('Applying filters');
    currentPage = 1; // Reset to first page when applying filters
    loadActivityLogs();
    loadStatistics();
}

function clearFilters() {
    console.log('Clearing filters');
    const elements = getElements();
    if (elements.startDate) elements.startDate.value = '';
    if (elements.endDate) elements.endDate.value = '';
    if (elements.userFilter) elements.userFilter.value = 'all';
    if (elements.actionFilter) elements.actionFilter.value = 'all';
    if (elements.searchInput) elements.searchInput.value = '';
    currentPage = 1; // Reset to first page when clearing filters
    loadActivityLogs();
    loadStatistics();
}

function renderActivityLogs(logs, pagination) {
    console.log('Rendering activity logs, count:', logs.length);
    const elements = getElements();
    
    if (!elements.tbody) {
        console.error('Tbody not found');
        return;
    }
    
    elements.tbody.innerHTML = '';

    if (logs.length === 0) {
        elements.tbody.innerHTML = '<tr><td colspan="9" class="text-center">No activity logs found</td></tr>';
        return;
    }

    logs.forEach(log => {
        const row = `
            <tr>
                <td>${formatDateTime(log.created_at)}</td>
                <td><span class="badge bg-secondary">${log.user_ref || 'N/A'}</span></td>
                <td><span class="badge ${getActionBadgeClass(log.action)}">${(log.action || 'unknown').replace(/_/g, ' ')}</span></td>
                <td>${log.page_name || 'N/A'}</td>
                <td><span class="badge bg-info">${log.http_method || 'GET'}</span></td>
                <td><span class="badge ${getStatusBadgeClass(log.response_status)}">${log.response_status || 200}</span></td>
                <td>${log.execution_time_ms || 0} ms</td>
                <td>${log.query_count || 0}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewActivityDetails(${log.id})">
                        <i class="material-icons-outlined" style="font-size: 16px;">visibility</i>
                    </button>
                </td>
            </tr>
        `;
        elements.tbody.innerHTML += row;
    });

    renderPagination(pagination);
    console.log('Activity logs rendered successfully');
}

function renderStatistics(stats, elements) {
    console.log('Rendering statistics:', stats);
    
    if (!elements.statTotal) {
        console.error('Statistics elements not found!');
        return;
    }
    
    elements.statTotal.textContent = (stats.total_activities || 0).toLocaleString();
    elements.statUsers.textContent = (stats.unique_users || 0).toLocaleString();
    elements.statQueries.textContent = (stats.total_queries || 0).toLocaleString();
    elements.statFailed.textContent = (stats.failed_requests || 0).toLocaleString();
    elements.statAvg.textContent = (stats.avg_execution_time || 0).toFixed(2) + ' ms';
    
    console.log('Statistics rendered successfully:', {
        total: elements.statTotal.textContent,
        users: elements.statUsers.textContent,
        queries: elements.statQueries.textContent,
        failed: elements.statFailed.textContent,
        avg: elements.statAvg.textContent
    });
}

function renderPagination(pagination) {
    const elements = getElements();
    
    // Update pagination info
    if (elements.paginationInfo) {
        elements.paginationInfo.innerHTML = `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total || 0} entries`;
    }
    
    // Update pagination navigation
    if (elements.paginationNav && pagination.last_page > 1) {
        let paginationHtml = '<ul class="pagination mb-0">';
        
        // Previous button
        paginationHtml += `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">
                    <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">chevron_left</i>
                </a>
            </li>
        `;
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
        
        // First page
        if (startPage > 1) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changePage(1); return false;">1</a>
                </li>
            `;
            if (startPage > 2) {
                paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        // Middle pages
        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        // Last page
        if (endPage < pagination.last_page) {
            if (endPage < pagination.last_page - 1) {
                paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changePage(${pagination.last_page}); return false;">${pagination.last_page}</a>
                </li>
            `;
        }
        
        // Next button
        paginationHtml += `
            <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">
                    <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">chevron_right</i>
                </a>
            </li>
        `;
        
        paginationHtml += '</ul>';
        elements.paginationNav.innerHTML = paginationHtml;
    } else if (elements.paginationNav) {
        elements.paginationNav.innerHTML = '';
    }
}

function changePage(page) {
    if (page < 1) return;
    currentPage = page;
    loadActivityLogs();
    
    // Scroll to top of table
    const elements = getElements();
    if (elements.tbody) {
        elements.tbody.closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function changePerPage(newPerPage) {
    console.log('Changing per page to:', newPerPage);
    perPage = parseInt(newPerPage);
    currentPage = 1; // Reset to first page when changing items per page
    loadActivityLogs();
}

function viewActivityDetails(id) {
    console.log('Viewing activity details for ID:', id);
    fetch(`/admin/activity-logs/${id}`)
        .then(response => response.json())
        .then(data => {
            console.log('Activity detail received:', data);
            if (data.success) {
                showActivityModal(data.log);
            }
        })
        .catch(error => console.error('Error loading activity details:', error));
}

function showActivityModal(log) {
    console.log('Showing modal for log:', log);
    const modalElement = document.getElementById('activityDetailModal');
    
    if (!modalElement) {
        console.error('Modal element not found!');
        return;
    }
    
    const modal = new bootstrap.Modal(modalElement);
    
    document.getElementById('modal-timestamp').textContent = formatDateTime(log.created_at);
    document.getElementById('modal-user').textContent = log.user_ref;
    document.getElementById('modal-action').textContent = log.action;
    document.getElementById('modal-page').textContent = log.page_name;
    document.getElementById('modal-url').textContent = log.page_url;
    document.getElementById('modal-method').textContent = log.http_method;
    document.getElementById('modal-description').textContent = log.description || 'N/A';
    document.getElementById('modal-ip').textContent = log.ip_address;
    document.getElementById('modal-status').textContent = log.response_status;
    document.getElementById('modal-execution-time').textContent = log.execution_time_ms + ' ms';
    
    document.getElementById('modal-request-data').textContent = JSON.stringify(log.request_data, null, 2);
    
    const queriesDiv = document.getElementById('modal-queries');
    if (log.queries_executed) {
        const queries = typeof log.queries_executed === 'string' ? JSON.parse(log.queries_executed) : log.queries_executed;
        queriesDiv.innerHTML = queries.map((q, i) => 
            `<div class="mb-2">
                <strong>Query ${i + 1}:</strong> (${q.time_ms} ms)
                <pre class="bg-light p-2 mt-1">${q.query || q.note || 'N/A'}</pre>
            </div>`
        ).join('');
    } else {
        queriesDiv.innerHTML = '<p class="text-muted">No queries executed</p>';
    }
    
    modal.show();
    console.log('Modal shown');
}

function exportActivityLogs() {
    const filters = getFilters();
    window.location.href = `/admin/activity-logs/export?user_type=${currentUserType}&${new URLSearchParams(filters)}`;
}

function formatDateTime(datetime) {
    if (!datetime) return 'N/A';
    return new Date(datetime).toLocaleString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function getActionBadgeClass(action) {
    if (!action) return 'bg-secondary';
    const classes = {
        'login': 'bg-success',
        'logout': 'bg-secondary',
        'send_sms': 'bg-primary',
        'create': 'bg-info',
        'update': 'bg-warning',
        'delete': 'bg-danger',
        'view': 'bg-info',
    };
    for (let key in classes) {
        if (action.toLowerCase().includes(key)) return classes[key];
    }
    return 'bg-secondary';
}

function getStatusBadgeClass(status) {
    if (!status) return 'bg-secondary';
    if (status >= 200 && status < 300) return 'bg-success';
    if (status >= 300 && status < 400) return 'bg-info';
    if (status >= 400 && status < 500) return 'bg-warning';
    if (status >= 500) return 'bg-danger';
    return 'bg-secondary';
}
</script>
@endpush
