<aside class="sidebar-wrapper" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="d-flex align-items-center justify-content-between">
            <img src="{{ asset('assets/images/auth/smsexpert_cover.png') }}" 
                 class="sidebar-logo" alt="SMS Expert" id="sidebarLogo">
            <button class="btn btn-sm btn-outline-secondary d-none d-lg-block" id="sidebarToggle">
                <i class="material-icons-outlined">menu</i>
            </button>
        </div>
        <div class="sidebar-user mt-3 d-none" id="sidebarUser">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                    {{ strtoupper(substr($user_contactname ?? 'A', 0, 1)) }}
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small">{{ $user_contactname ?? 'Admin' }}</div>
                    <small class="text-muted">Administrator</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav flex-column" id="sidebarNav">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" 
                   href="{{ route('admin.dashboard') }}">
                    <i class="nav-icon material-icons-outlined">dashboard</i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <!-- User Management -->
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.users*') ? 'active' : '' }}" 
                   href="#" data-bs-toggle="collapse" data-bs-target="#userManagementMenu" 
                   aria-expanded="{{ request()->routeIs('admin.users*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">people</i>
                    <span class="nav-text">User Management</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.users*') ? 'show' : '' }}" id="userManagementMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.users') ? 'active' : '' }}" 
                               href="{{ route('admin.users') }}">
                                <i class="nav-icon material-icons-outlined">group</i>
                                <span class="nav-text">All Users</span>
                                <span class="nav-badge">{{ $totalUsers ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.customer.create') ? 'active' : '' }}" 
                               href="{{ route('admin.customer.create') }}">
                                <i class="nav-icon material-icons-outlined">person_add</i>
                                <span class="nav-text">Add Customer</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.users.active') ? 'active' : '' }}" 
                               href="{{ route('admin.users.active') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">verified_user</i>
                                <span class="nav-text">Active Users</span>
                                <span class="nav-badge">{{ $activeUsers ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.users.blocked') ? 'active' : '' }}" 
                               href="{{ route('admin.users.blocked') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">block</i>
                                <span class="nav-text">Blocked Users</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Admin Users & Roles -->
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.admin-users*') || request()->routeIs('admin.roles*') ? 'active' : '' }}" 
                   href="#" data-bs-toggle="collapse" data-bs-target="#adminUsersMenu" 
                   aria-expanded="{{ request()->routeIs('admin.admin-users*') || request()->routeIs('admin.roles*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">admin_panel_settings</i>
                    <span class="nav-text">Admin Users</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.admin-users*') || request()->routeIs('admin.roles*') ? 'show' : '' }}" id="adminUsersMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.admin-users.index') ? 'active' : '' }}" 
                               href="{{ route('admin.admin-users.index') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">supervisor_account</i>
                                <span class="nav-text">All Admin Users</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.roles.index') ? 'active' : '' }}" 
                               href="{{ route('admin.roles.index') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">security</i>
                                <span class="nav-text">Roles & Permissions</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.admin-users.create') ? 'active' : '' }}" 
                               href="{{ route('admin.admin-users.create') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">person_add_alt</i>
                                <span class="nav-text">Add Admin User</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Client Emails -->
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.client.emails*') ? 'active' : '' }}" 
                   href="#" data-bs-toggle="collapse" data-bs-target="#emailsMenu" 
                   aria-expanded="{{ request()->routeIs('admin.client.emails*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">email</i>
                    <span class="nav-text">Client Emails</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.client.emails*') ? 'show' : '' }}" id="emailsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.client.emails') ? 'active' : '' }}" 
                               href="{{ route('admin.client.emails') }}">
                                <i class="nav-icon material-icons-outlined">inbox</i>
                                <span class="nav-text">All Client Emails</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('email.form') ? 'active' : '' }}" 
                               href="{{ route('email.form') }}">
                                <i class="nav-icon material-icons-outlined">create</i>
                                <span class="nav-text">Compose Email</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.email.templates') ? 'active' : '' }}" 
                               href="{{ route('admin.email.templates') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">description</i>
                                <span class="nav-text">Email Templates</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.*report*') ? 'active' : '' }}" 
                   href="#" data-bs-toggle="collapse" data-bs-target="#reportsMenu" 
                   aria-expanded="{{ request()->routeIs('admin.*report*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">assessment</i>
                    <span class="nav-text">Reports</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.*report*') ? 'show' : '' }}" id="reportsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.reports.dashboard') ? 'active' : '' }}" 
                               href="{{ route('admin.reports.dashboard') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">dashboard</i>
                                <span class="nav-text">Reports Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.export.postpay') ? 'active' : '' }}" 
                               href="{{ route('admin.export.postpay') }}">
                                <i class="nav-icon material-icons-outlined">credit_card</i>
                                <span class="nav-text">Post Pay Customers</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.export.daily_sms') ? 'active' : '' }}" 
                               href="{{ route('admin.export.daily_sms') }}">
                                <i class="nav-icon material-icons-outlined">sms</i>
                                <span class="nav-text">Daily SMS Report</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.export.money_transferred') ? 'active' : '' }}" 
                               href="{{ route('admin.export.money_transferred') }}">
                                <i class="nav-icon material-icons-outlined">account_balance</i>
                                <span class="nav-text">Money Transfer Report</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.monthly-report') ? 'active' : '' }}" 
                               href="{{ route('admin.monthly-report') }}">
                                <i class="nav-icon material-icons-outlined">calendar_month</i>
                                <span class="nav-text">Monthly Report</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.daily-report') ? 'active' : '' }}" 
                               href="{{ route('admin.daily-report') }}">
                                <i class="nav-icon material-icons-outlined">today</i>
                                <span class="nav-text">Daily Report</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.analytics') ? 'active' : '' }}"
                               href="{{ route('admin.analytics') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">analytics</i>
                                <span class="nav-text">Analytics</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Other Links -->
            @php
                $otherLinksAdminUser = session('admin_user');
                $otherLinksIsSuper = isset($otherLinksAdminUser['role']) && $otherLinksAdminUser['role'] === 'super_admin';
                $canViewLivebeat = $otherLinksIsSuper || ($otherLinksAdminUser['permissions']['can_view_daemon_report'] ?? false);
            @endphp
            @if($canViewLivebeat)
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.reports.daemon') ? 'active' : '' }}"
                   href="#" data-bs-toggle="collapse" data-bs-target="#otherLinksMenu"
                   aria-expanded="{{ request()->routeIs('admin.reports.daemon') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">link</i>
                    <span class="nav-text">Other Links</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.reports.daemon') ? 'show' : '' }}" id="otherLinksMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.reports.daemon') ? 'active' : '' }}"
                               href="{{ route('admin.reports.daemon') }}">
                                <i class="nav-icon material-icons-outlined">dns</i>
                                <span class="nav-text">Livebeat</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif

            <!-- Cost Management -->
            @php
                $canManageCost = $sidebarIsSuperAdmin || ($sidebarPermissions['can_manage_cost'] ?? false);
            @endphp
            @if($canManageCost)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.cost.*') ? 'active' : '' }}"
                   href="{{ route('admin.cost.index') }}">
                    <i class="nav-icon material-icons-outlined">paid</i>
                    <span class="nav-text">Cost</span>
                </a>
            </li>
            @endif

            <!-- Settings -->
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.settings*') ? 'active' : '' }}"
                   href="#" data-bs-toggle="collapse" data-bs-target="#settingsMenu"
                   aria-expanded="{{ request()->routeIs('admin.settings*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">settings</i>
                    <span class="nav-text">Settings</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.settings*') ? 'show' : '' }}" id="settingsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.index') ? 'active' : '' }}" 
                               href="{{ route('admin.settings.index') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">tune</i>
                                <span class="nav-text">General Settings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.smpp') ? 'active' : '' }}" 
                               href="{{ route('admin.settings.smpp') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">router</i>
                                <span class="nav-text">SMPP Configuration</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.nexmo') ? 'active' : '' }}" 
                               href="{{ route('admin.settings.nexmo') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">api</i>
                                <span class="nav-text">Nexmo Settings</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.smtp') ? 'active' : '' }}" 
                               href="{{ route('admin.settings.smtp') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">mark_email_read</i>
                                <span class="nav-text">SMTP Configuration</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.system-logs') ? 'active' : '' }}" 
                               href="{{ route('admin.settings.system-logs') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">bug_report</i>
                                <span class="nav-text">System Logs</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.system-status') ? 'active' : '' }}" 
                               href="{{ route('admin.settings.system-status') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">monitor_heart</i>
                                <span class="nav-text">System Status</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.backup') ? 'active' : '' }}"
                               href="{{ route('admin.settings.backup') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">backup</i>
                                <span class="nav-text">Backup & Restore</span>
                            </a>
                        </li>
                        @php
                            $sidebarAdminUser = Session::get('admin_user');
                            $sidebarIsSuperAdmin = isset($sidebarAdminUser['role']) && $sidebarAdminUser['role'] === 'super_admin';
                            $sidebarPermissions = $sidebarAdminUser['permissions'] ?? [];
                            $canManageServerSettings = $sidebarIsSuperAdmin || ($sidebarPermissions['can_manage_server_settings'] ?? false);
                        @endphp
                        @if($canManageServerSettings)
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.settings.server') || request()->get('tab') == 'server-settings' ? 'active' : '' }}"
                               href="{{ route('admin.settings', ['tab' => 'server-settings']) }}">
                                <i class="nav-icon material-icons-outlined">dns</i>
                                <span class="nav-text">Server Settings</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>

            <!-- Migration -->
            @if($canManageServerSettings)
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.migration*') ? 'active' : '' }}"
                   href="#" data-bs-toggle="collapse" data-bs-target="#migrationMenu"
                   aria-expanded="{{ request()->routeIs('admin.migration*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">sync_alt</i>
                    <span class="nav-text">Migration</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.migration*') ? 'show' : '' }}" id="migrationMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.migration.campaign-files') ? 'active' : '' }}"
                               href="{{ route('admin.migration.campaign-files') }}">
                                <i class="nav-icon material-icons-outlined">folder_copy</i>
                                <span class="nav-text">Campaign Files</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif

            <!-- Notifications -->
            <li class="nav-item">
                <a class="nav-link nav-dropdown-toggle {{ request()->routeIs('admin.notifications*') ? 'active' : '' }}" 
                   href="#" data-bs-toggle="collapse" data-bs-target="#notificationsMenu" 
                   aria-expanded="{{ request()->routeIs('admin.notifications*') ? 'true' : 'false' }}">
                    <i class="nav-icon material-icons-outlined">notifications</i>
                    <span class="nav-text">Notifications</span>
                    <i class="material-icons-outlined ms-auto">keyboard_arrow_right</i>
                </a>
                <div class="collapse nav-dropdown-menu {{ request()->routeIs('admin.notifications*') ? 'show' : '' }}" id="notificationsMenu">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.notifications.index') ? 'active' : '' }}" 
                               href="{{ route('admin.notifications.index') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">inbox</i>
                                <span class="nav-text">All Notifications</span>
                                <span class="nav-badge">{{ $unreadNotifications ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.notifications.compose') ? 'active' : '' }}" 
                               href="{{ route('admin.notifications.compose') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">add_alert</i>
                                <span class="nav-text">Send Notification</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.notifications.email') ? 'active' : '' }}" 
                               href="{{ route('admin.notifications.email') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">email</i>
                                <span class="nav-text">Email Notifications</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.notifications.sms') ? 'active' : '' }}" 
                               href="{{ route('admin.notifications.sms') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">sms</i>
                                <span class="nav-text">SMS Notifications</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link nav-dropdown-item {{ request()->routeIs('admin.notifications.scheduled') ? 'active' : '' }}" 
                               href="{{ route('admin.notifications.scheduled') ?? '#' }}">
                                <i class="nav-icon material-icons-outlined">schedule</i>
                                <span class="nav-text">Scheduled</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Separator -->
            <li class="nav-separator">
                <hr class="my-3">
            </li>

            <!-- System Monitoring -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.monitoring*') ? 'active' : '' }}" 
                   href="{{ route('admin.monitoring.index') ?? '#' }}">
                    <i class="nav-icon material-icons-outlined">monitor_heart</i>
                    <span class="nav-text">System Monitor</span>
                    <span class="nav-badge bg-success">Online</span>
                </a>
            </li>

            <!-- Cron Logs -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.cron-logs*') ? 'active' : '' }}" 
                   href="{{ route('admin.cron-logs.index') }}">
                    <i class="nav-icon material-icons-outlined">schedule</i>
                    <span class="nav-text">Cron Job Logs</span>
                </a>
            </li>

            <!-- Activity Logs -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.activity*') ? 'active' : '' }}" 
                   href="{{ route('admin.activity.index') ?? '#' }}">
                    <i class="nav-icon material-icons-outlined">history</i>
                    <span class="nav-text">Activity Logs</span>
                </a>
            </li>

            <!-- Outstanding Invoices -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.outstanding.invoices') ? 'active' : '' }}" 
                   href="{{ route('admin.outstanding.invoices') }}">
                    <i class="nav-icon material-icons-outlined">receipt_long</i>
                    <span class="nav-text">Outstanding Invoices</span>
                    <span class="nav-badge bg-warning">{{ $outstandingInvoices ?? 0 }}</span>
                </a>
            </li>

            <!-- Security -->
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.ip.address.restriction') ? 'active' : '' }}" 
                   href="{{ route('admin.ip.address.restriction') }}">
                    <i class="nav-icon material-icons-outlined">security</i>
                    <span class="nav-text">IP Restrictions</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-3 border-top mt-auto">
        <div class="d-flex align-items-center gap-2 text-center">
            <div class="status-indicator status-online"></div>
            <small class="text-muted">System Online</small>
        </div>
        <div class="mt-2">
            <small class="text-muted d-block">SMS Expert Admin v2.0</small>
            <small class="text-muted">© {{ date('Y') }} All rights reserved</small>
        </div>
    </div>
</aside>

<style>
    .nav-separator hr {
        border-color: var(--border-light);
        opacity: 0.5;
    }

    .sidebar-footer {
        background: rgba(0, 0, 0, 0.02);
        border-top: 1px solid var(--border-light);
    }

    [data-bs-theme="dark"] .sidebar-footer {
        background: rgba(255, 255, 255, 0.02);
    }

    .nav-badge {
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
        border-radius: 10px;
        font-weight: 600;
        background: var(--danger-color);
        color: white;
        min-width: 18px;
        text-align: center;
    }

    .nav-badge.bg-success {
        background: var(--success-color) !important;
    }

    .nav-badge.bg-warning {
        background: var(--warning-color) !important;
    }

    .nav-badge.bg-info {
        background: var(--info-color) !important;
    }

    /* Collapsed sidebar styles */
    .sidebar-wrapper.collapsed .nav-text,
    .sidebar-wrapper.collapsed .nav-badge,
    .sidebar-wrapper.collapsed .ms-auto,
    .sidebar-wrapper.collapsed .sidebar-user,
    .sidebar-wrapper.collapsed .sidebar-footer {
        display: none;
    }

    .sidebar-wrapper.collapsed .sidebar-logo {
        max-width: 40px;
    }

    .sidebar-wrapper.collapsed .nav-link {
        justify-content: center;
        padding: 0.75rem;
    }

    .sidebar-wrapper.collapsed .nav-dropdown-menu {
        display: none !important;
    }

    /* Dropdown animation */
    .nav-dropdown-menu {
        transition: all 0.3s ease;
    }

    .nav-dropdown-toggle[aria-expanded="true"] .ms-auto {
        transform: rotate(90deg);
        transition: transform 0.2s ease;
    }

    /* Hover effects */
    .nav-link:hover .nav-icon {
        transform: scale(1.1);
        transition: transform 0.2s ease;
    }

    .nav-link.active .nav-icon {
        animation: bounce 0.6s ease;
    }

    @keyframes bounce {
        0%, 20%, 53%, 80%, 100% {
            transform: translate3d(0,0,0);
        }
        40%, 43% {
            transform: translate3d(0, -8px, 0);
        }
        70% {
            transform: translate3d(0, -4px, 0);
        }
        90% {
            transform: translate3d(0, -2px, 0);
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle dropdown toggles
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('data-bs-target');
            const target = document.querySelector(targetId);
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Close other dropdowns
            dropdownToggles.forEach(otherToggle => {
                if (otherToggle !== this) {
                    const otherTargetId = otherToggle.getAttribute('data-bs-target');
                    const otherTarget = document.querySelector(otherTargetId);
                    otherToggle.setAttribute('aria-expanded', 'false');
                    otherTarget.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            this.setAttribute('aria-expanded', !isExpanded);
            target.classList.toggle('show');
        });
    });

    // Auto-expand active dropdown
    const activeDropdownItems = document.querySelectorAll('.nav-dropdown-item.active');
    activeDropdownItems.forEach(item => {
        const dropdown = item.closest('.nav-dropdown-menu');
        if (dropdown) {
            dropdown.classList.add('show');
            const toggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }
    });
});
</script>