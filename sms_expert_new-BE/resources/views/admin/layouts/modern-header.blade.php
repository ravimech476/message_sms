<header class="top-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <!-- Mobile Menu Toggle -->
            <button class="btn btn-outline-secondary d-lg-none me-3" id="mobileSidebarToggle" type="button">
                <i class="material-icons-outlined">menu</i>
            </button>

            <!-- Brand/Logo for mobile -->
            <div class="navbar-brand d-lg-none">
                <img src="{{ asset('assets/images/auth/smsexpert_cover.png') }}" height="32" alt="SMS Expert">
            </div>

            <!-- Search Bar -->
            <div class="search-modern flex-grow-1 me-4">
                <div class="position-relative">
                    <i class="material-icons-outlined search-icon">search</i>
                    <input type="text" class="search-input" placeholder="Search users, reports, settings..." id="globalSearch">
                </div>
            </div>

            <!-- Header Actions -->
            <div class="d-flex align-items-center gap-3">
                <!-- Theme Toggle -->
                <button class="btn btn-outline-secondary" id="themeToggle" data-bs-toggle="tooltip" title="Toggle Theme">
                    <i class="material-icons-outlined">dark_mode</i>
                </button>

                <!-- Quick Stats -->
                <div class="d-none d-md-flex align-items-center gap-3 text-sm">
                    <div class="text-center">
                        <div class="fw-bold text-primary">{{ number_format($todayStats['sms_sent'] ?? 0) }}</div>
                        <div class="text-muted small">Today's SMS</div>
                    </div>
                    <div class="vr"></div>
                    <div class="text-center">
                        <div class="fw-bold text-success">{{ $activeUsers ?? 0 }}</div>
                        <div class="text-muted small">Active Users</div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown" data-bs-toggle="tooltip" title="Notifications">
                        <i class="material-icons-outlined">notifications</i>
                        <span class="notification-badge" id="notificationCount">{{ $unreadNotifications ?? 0 }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg" style="width: 350px;">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0">Notifications</h6>
                            <small class="text-muted">You have {{ $unreadNotifications ?? 0 }} unread notifications</small>
                        </div>
                        <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
                            <!-- Notifications will be loaded here -->
                            <div class="p-3 text-center text-muted">
                                <i class="material-icons-outlined fs-2 mb-2">notifications_none</i>
                                <p class="mb-0">No new notifications</p>
                            </div>
                        </div>
                        <div class="p-2 border-top text-center">
                            <a href="{{ route('admin.notifications.index') ?? '#' }}" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dropdown">
                    <button class="btn btn-primary-modern" data-bs-toggle="dropdown" data-bs-toggle="tooltip" title="Quick Actions">
                        <i class="material-icons-outlined">add</i>
                        <span class="d-none d-md-inline ms-1">Quick Actions</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg">
                        <h6 class="dropdown-header">Quick Actions</h6>
                        <a class="dropdown-item" href="{{ route('admin.customer.create') ?? '#' }}">
                            <i class="material-icons-outlined me-2">person_add</i>Add Customer
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.notifications.compose') ?? '#' }}">
                            <i class="material-icons-outlined me-2">email</i>Send Notification
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.reports.generate') ?? '#' }}">
                            <i class="material-icons-outlined me-2">assessment</i>Generate Report
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="{{ route('admin.settings.index') ?? '#' }}">
                            <i class="material-icons-outlined me-2">settings</i>System Settings
                        </a>
                    </div>
                </div>

                <!-- System Status Indicator -->
                <div class="d-none d-lg-block">
                    <div class="d-flex align-items-center gap-2">
                        <div class="status-indicator status-online" data-bs-toggle="tooltip" title="All systems operational"></div>
                        <small class="text-muted">System Status</small>
                    </div>
                </div>

                <!-- Admin Profile -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <div class="avatar-sm">
                            <i class="material-icons-outlined">account_circle</i>
                        </div>
                        <div class="d-none d-md-block text-start">
                            <div class="fw-semibold">{{ $user_contactname ?? 'Admin' }}</div>
                            <small class="text-muted">Administrator</small>
                        </div>
                        <i class="material-icons-outlined">keyboard_arrow_down</i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg">
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-md bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                                    {{ strtoupper(substr($user_contactname ?? 'A', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $user_contactname ?? 'Admin' }}</div>
                                    <small class="text-muted">System Administrator</small>
                                </div>
                            </div>
                        </div>
                        <a class="dropdown-item" href="{{ route('admin.profile') ?? '#' }}">
                            <i class="material-icons-outlined me-2">person</i>My Profile
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.settings.account') ?? '#' }}">
                            <i class="material-icons-outlined me-2">settings</i>Account Settings
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.activity.log') ?? '#' }}">
                            <i class="material-icons-outlined me-2">history</i>Activity Log
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="{{ route('admin.logout') }}" 
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="material-icons-outlined me-2">logout</i>Logout
                        </a>
                        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<style>
    .avatar-sm {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--light-bg);
        color: var(--text-secondary);
    }

    .avatar-md {
        width: 48px;
        height: 48px;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .status-online {
        background: var(--success-color);
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        animation: pulse 2s infinite;
    }

    .status-warning {
        background: var(--warning-color);
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }

    .status-error {
        background: var(--danger-color);
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }

    .notification-list .dropdown-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-light);
    }

    .notification-list .dropdown-item:last-child {
        border-bottom: none;
    }

    .notification-list .dropdown-item.unread {
        background: rgba(234, 97, 24, 0.05);
        border-left: 3px solid var(--primary-color);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Global Search
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        globalSearch.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            // Implement global search functionality
            console.log('Searching for:', query);
        });
    }

    // Load notifications
    loadNotifications();
    
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    
    // System status check
    checkSystemStatus();
    setInterval(checkSystemStatus, 60000); // Check every minute
});

function loadNotifications() {
    // Fetch notifications via AJAX
    fetch('/admin/api/notifications')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.unread_count);
            renderNotifications(data.notifications);
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationCount');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

function renderNotifications(notifications) {
    const container = document.querySelector('.notification-list');
    if (!container) return;
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="p-3 text-center text-muted">
                <i class="material-icons-outlined fs-2 mb-2">notifications_none</i>
                <p class="mb-0">No new notifications</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = notifications.map(notification => `
        <a href="#" class="dropdown-item ${notification.read_at ? '' : 'unread'}" data-id="${notification.id}">
            <div class="d-flex align-items-start gap-3">
                <div class="notification-icon">
                    <i class="material-icons-outlined text-${notification.type === 'error' ? 'danger' : 'primary'}">${notification.icon || 'info'}</i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${notification.title}</div>
                    <p class="text-muted small mb-1">${notification.message}</p>
                    <small class="text-muted">${notification.created_at}</small>
                </div>
            </div>
        </a>
    `).join('');
}

function checkSystemStatus() {
    fetch('/admin/api/system-status')
        .then(response => response.json())
        .then(data => {
            const indicator = document.querySelector('.status-indicator');
            if (indicator) {
                indicator.className = `status-indicator status-${data.status}`;
                indicator.setAttribute('data-original-title', data.message);
            }
        })
        .catch(error => {
            console.error('Error checking system status:', error);
        });
}
</script>