<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin - SMS Expert')</title>
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined&display=block" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons&display=block" rel="stylesheet">

    <!-- CSS Files -->
    <link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/metismenu/metisMenu.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/metismenu/mm-vertical.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/semi-dark.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/bordered-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/responsive.css') }}" rel="stylesheet">

    <style>
        :root {
            --primary-color: #ea6118;
            --secondary-color: #293b50;
            --sidebar-width: 220px;
            --header-height: 70px;
        }

        body {
            background: #f8fafc;
            margin: 0;
            padding: 0;
            color: #293b50;
        }

        .material-icons,
        .material-icons-outlined {
            font-family: 'Material Icons Outlined', 'Material Icons' !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 20px !important;
            line-height: 1 !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
            vertical-align: middle !important;
            position: relative;
            top: -1px;
        }

        /* Sidebar Styles */
        .main-sidebar {
            background: linear-gradient(180deg, #293b50 0%, #1f2c3d 100%);
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 6px -1px rgba(41, 59, 80, 0.1);
            z-index: 1001;
            overflow: hidden;
            transition: width 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .main-sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            color: white;
            padding: 7px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .sidebar-logo {
            height: 35px;
            width: 150px !important;
            margin-right: 1rem;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 5px;
        }

        .sidebar-title {
            font-size: 1.1rem;
            font-weight: 600;
            transition: opacity 0.3s ease;
        }

        .sidebar-collapse-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-collapse-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .sidebar-user {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            flex-shrink: 0;
        }

        .user-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid #ea6118;
            margin-bottom: 0.5rem;
        }

        .user-name {
            margin: 0;
            color: white;
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .user-type {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        .sidebar-nav .metismenu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav .metismenu li {
            margin: 0.1rem 1rem;
        }

        .sidebar-nav .metismenu li a {
            display: flex;
            align-items: center;
            padding: 0.8rem 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.85rem;
            position: relative;
        }

        .sidebar-nav .metismenu li a:hover,
        .sidebar-nav .metismenu li a.active {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            transform: translateX(0);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        }

        .sidebar-nav .metismenu li a .parent-icon {
            background: rgba(234, 97, 24, 0.2);
            border-radius: 8px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
            flex-shrink: 0;
        }

        .sidebar-nav .metismenu li a:hover .parent-icon,
        .sidebar-nav .metismenu li a.active .parent-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ---- "Other Links" collapsible parent + submenu (metisMenu) ---- */
        /* Parent row: same height as the flat menu items */
        .sidebar-nav .metismenu > li > a.has-arrow {
            height: 45px;
            padding-top: 0;
            padding-bottom: 0;
            padding-right: 2.2rem;
        }
        /* Arrow indicator on the right */
        .sidebar-nav .metismenu a.has-arrow::after {
            content: "";
            position: absolute;
            right: 1rem;
            top: 50%;
            width: 0.5rem;
            height: 0.5rem;
            border-right: 2px solid rgba(255, 255, 255, 0.7);
            border-bottom: 2px solid rgba(255, 255, 255, 0.7);
            transform: translateY(-70%) rotate(45deg);
            transition: transform 0.3s ease;
        }
        .sidebar-nav .metismenu li.mm-active > a.has-arrow::after,
        .sidebar-nav .metismenu a.has-arrow[aria-expanded="true"]::after {
            transform: translateY(-30%) rotate(-135deg);
        }
        /* metisMenu marks the open parent .mm-active (default = blue) and the link keeps
           focus after a click — force both back to the sidebar theme, but never override
           the orange :hover state. */
        .sidebar-nav .metismenu li.mm-active > a.has-arrow:not(:hover),
        .sidebar-nav .metismenu li a:focus:not(:hover):not(.active) {
            background: transparent;
            color: rgba(255, 255, 255, 0.9);
            box-shadow: none;
            outline: none;
        }
        .sidebar-nav .metismenu li.mm-active > a.has-arrow:not(:hover) .parent-icon,
        .sidebar-nav .metismenu li a:focus:not(:hover):not(.active) .parent-icon {
            background: rgba(234, 97, 24, 0.2);
        }
        /* Submenu container: transparent, no default white background */
        .sidebar-nav .metismenu li ul {
            list-style: none;
            padding: 0;
            margin: 0.1rem 0 0.2rem 0.5rem;
            background: transparent;
        }
        .sidebar-nav .metismenu li ul li {
            margin: 0.15rem 0.5rem;
        }
        /* Submenu links: smaller, themed to match the dark sidebar */
        .sidebar-nav .metismenu li ul li a {
            height: 40px;
            padding: 0 0.5rem 0 0.75rem;
            font-size: 0.8rem;
            background: transparent;
            color: rgba(255, 255, 255, 0.85);
        }
        .sidebar-nav .metismenu li ul li a:hover,
        .sidebar-nav .metismenu li ul li a.active {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: #fff;
        }
        .sidebar-nav .metismenu li ul li a .parent-icon {
            width: 26px;
            height: 26px;
            margin-right: 0.6rem;
        }
        .sidebar-nav .metismenu li ul li a .parent-icon i {
            font-size: 16px;
        }

        .menu-title {
            font-size: 0.9rem;
            white-space: nowrap;
        }

        /* Collapsed States */
        .main-sidebar.collapsed .sidebar-title,
        .main-sidebar.collapsed .user-name,
        .main-sidebar.collapsed .user-type,
        .main-sidebar.collapsed .menu-title {
            display: none;
        }

        .main-sidebar.collapsed .sidebar-user {
            padding: 0.5rem;
        }

        .main-sidebar.collapsed .metismenu li a {
            padding: 1rem 0.75rem;
            justify-content: center;
        }

        .main-sidebar.collapsed .parent-icon {
            margin-right: 0;
        }

        /* Header */
        .top-header {
            background: #293B50;
            /* height: var(--header-height); */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 1000;
            transition: left 0.3s ease;
        }

        .top-header.sidebar-collapsed {
            left: 70px;
        }

        .top-header .navbar {
            padding: 0 2rem;
            /* height: 70px; */
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            background: #f8fafc;
            transition: margin-left 0.3s ease;
        }

        .main-wrapper.sidebar-collapsed {
            margin-left: 70px;
        }

        .main-content {
            padding: 2rem;
        }

        /* Navigation Items */
        .nav-right-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-link.text-white {
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link.text-white:hover {
            color: #ea6118 !important;
        }

        .dropdown-toggle-nocaret::after {
            display: none;
        }

        .selected-label {
            background: linear-gradient(135deg, #ea6118, #293b50) !important;
            color: #ffffff !important;
            border-radius: 12px !important;
        }

        /* Mobile Menu */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        .main-sidebar.collapsed .sidebar-collapse-btn {
            margin-left: -5px;
        }

        .sidebar-collapse-btn i {
            transition: all 0.3s ease;
        }

        /* .sidebar-collapse-btn:hover i {
            transform: rotate(90deg);
        } */

        .main-sidebar.collapsed #hide-logo {
            display: none;
        }

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }

            .main-sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }

            .main-sidebar.show {
                transform: translateX(0);
            }

            .top-header {
                left: 0;
            }

            .main-wrapper {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }
        }

        /* Mini Sidebar (kept for user profile offcanvas) */
        .mini-sidebar {
            display: none;
        }

        /* Logout Circle */
        .logout-circle {
            color: #fff;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
            text-decoration: none;
        }

        .logout-circle:hover {
            color: #ea6118;
            background-color: rgba(234, 97, 24, 0.1);
        }
    </style>
    @stack('style')
</head>

<body>
    <!-- Sidebar -->
    <aside class="main-sidebar" id="main-sidebar">
        <!-- Header -->
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <img src="{{ asset('assets/images/auth/smsexpert_cover-3.png') }}" alt="SMS Expert" class="sidebar-logo"
                    id="hide-logo">
            </div>
            <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Toggle Sidebar">
                <i id="toggle-icon" class="material-icons-outlined">chevron_left</i>
            </button>
        </div>

        <!-- User Profile (Optional for admin) -->
        @php
            $userInfo = Session::get('user_info');
            $user_contactname = urldecode($userInfo['contactname'] ?? 'Admin');
            
            // Get current admin user for permission checking
            $adminSession = Session::get('admin_user');
            $currentAdminUser = null;
            if ($adminSession) {
                $currentAdminUser = \App\Models\User::with('adminPermissions')->find($adminSession['id']);
            }
            
            // Helper function to check permission
            $canAccessMenu = function($permission) use ($currentAdminUser) {
                // If no admin user found, deny access (require login)
                if (!$currentAdminUser) return false;
                
                // Use the model's hasPermission method which handles super_admin and permissions
                return $currentAdminUser->hasPermission($permission);
            };
        @endphp

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <ul class="metismenu" id="sidenav">
                @if($canAccessMenu('can_view_dashboard'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">home</i></div>
                        <div class="menu-title">Dashboard</div>
                    </a>
                </li>
                @endif
                
                @if($canAccessMenu('can_manage_admin_users'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.admin-users.index') }}" class="{{ request()->routeIs('admin.admin-users.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">admin_panel_settings</i></div>
                        <div class="menu-title">Admin Users</div>
                    </a>
                </li>
                @endif
                
                @if($canAccessMenu('can_view_customers'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users') || request()->routeIs('admin.user.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">group</i></div>
                        <div class="menu-title">Customers</div>
                    </a>
                </li>
                @endif
                
                @if($canAccessMenu('can_view_customer_emails'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.client.emails') }}" class="{{ request()->routeIs('admin.client.emails') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">email</i></div>
                        <div class="menu-title">Customer Emails</div>
                    </a>
                </li>
                @endif
                @if($canAccessMenu('can_manage_contracts'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.contracts.index') }}" class="{{ request()->routeIs('admin.contracts.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">assignment</i></div>
                        <div class="menu-title">Customer Contract</div>
                    </a>
                </li>
                @endif
                
                @if($canAccessMenu('can_view_virtual_numbers'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.virtual-numbers.index') }}" class="{{ request()->routeIs('admin.virtual-numbers.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">phone</i></div>
                        <div class="menu-title">Virtual Numbers</div>
                    </a>
                </li>
                @endif
                 @if($canAccessMenu('can_manage_notifications'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.notifications.index') }}" class="{{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">notifications</i></div>
                        <div class="menu-title">Notifications</div>
                    </a>
                </li>
                @endif
                
                @if($canAccessMenu('can_view_reports'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') && !request()->routeIs('admin.reports.daemon') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">assessment</i></div>
                        <div class="menu-title">Reports</div>
                    </a>
                </li>
                @endif

                @if($canAccessMenu('can_view_daemon_report'))
                <li class="{{ request()->routeIs('admin.reports.daemon') ? 'mm-active' : '' }}">
                    <a href="javascript:;" class="has-arrow" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">link</i></div>
                        <div class="menu-title">Other Links</div>
                    </a>
                    <ul>
                        <li>
                            <a href="{{ route('admin.reports.daemon') }}" class="{{ request()->routeIs('admin.reports.daemon') ? 'active' : '' }}" style="border-radius: 15px;">
                                <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">dns</i></div>
                                <div class="menu-title">Livebeat</div>
                            </a>
                        </li>
                    </ul>
                </li>
                @endif

                @if($canAccessMenu('can_manage_cost'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.cost.index') }}" class="{{ request()->routeIs('admin.cost.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">paid</i></div>
                        <div class="menu-title">Cost</div>
                    </a>
                </li>
                @endif

                @if($canAccessMenu('can_manage_global_pricing'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.global-pricing.index') }}" class="{{ request()->routeIs('admin.global-pricing.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">sell</i></div>
                        <div class="menu-title">Global Pricing</div>
                    </a>
                </li>
                @endif

                @if($canAccessMenu('can_view_settings'))
                <li style="height: 45px;">
                    <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">settings</i></div>
                        <div class="menu-title">Settings</div>
                    </a>
                </li>
                @endif

            </ul>
        </nav>
    </aside>

    <!-- Header -->
    <header class="top-header" id="top-header">
        <nav class="navbar navbar-expand align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i class="material-icons-outlined">menu</i>
                </button>
            </div>

            <ul class="navbar-nav gap-1 nav-right-links align-items-center">
                <!-- Admin User Info -->
                <li class="nav-item d-flex align-items-center me-2">
                    <div class="d-flex align-items-center" style="gap: 10px;">
                        <div class="admin-avatar" style="width: 35px; height: 35px; background: linear-gradient(135deg, #ea6118, #d1520e); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                            {{ strtoupper(substr(urldecode($adminSession['name'] ?? 'A'), 0, 1)) }}
                        </div>
                        <div class="admin-info d-none d-md-block">
                            <div style="color: white; font-weight: 600; font-size: 14px; line-height: 1.2;">
                                {{ urldecode($adminSession['name'] ?? 'Admin') }}
                            </div>
                            <div style="font-size: 11px; line-height: 1.2;">
                                @php
                                    $roleBadgeColors = [
                                        'super_admin' => 'background: linear-gradient(135deg, #dc3545, #c82333);',
                                        'admin' => 'background: linear-gradient(135deg, #6f42c1, #5a32a3);',
                                        'manager' => 'background: linear-gradient(135deg, #17a2b8, #138496);',
                                        'staff' => 'background: linear-gradient(135deg, #28a745, #218838);',
                                    ];
                                    $roleLabels = [
                                        'super_admin' => 'Super Admin',
                                        'admin' => 'Admin',
                                        'manager' => 'Manager',
                                        'staff' => 'Staff',
                                    ];
                                    $currentRole = $adminSession['role'] ?? 'admin';
                                @endphp
                                <span style="{{ $roleBadgeColors[$currentRole] ?? $roleBadgeColors['admin'] }} color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px;">
                                    {{ $roleLabels[$currentRole] ?? 'Admin' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </li>

                {{-- Help Menu — Dashboard/Campaign guides link to the public static files; Admin/Migration/reports are gated on $adminSession (this layout also renders on the pre-login admin screen) --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;"
                        data-bs-toggle="dropdown" title="Help">
                        <i class="material-icons-outlined">help_outline</i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">Help</li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ url('/userguide/userguide-dashboard.html') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">menu_book</i>
                                <span>Dashboard Guide</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ url('/userguide/userguide-campaign.html') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">campaign</i>
                                <span>Campaign Guide</span>
                            </a>
                        </li>
                        {{-- Admin Guide & Migration Guide are staff-only — hidden until an admin is logged in --}}
                        @if($adminSession)
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.userguide.show', 'admin') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">admin_panel_settings</i>
                                <span>Admin Guide</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.userguide.show', 'migration') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">sync_alt</i>
                                <span>Migration Guide</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.userguide.show', 'performance') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">insights</i>
                                <span>Performance Report</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('admin.userguide.show', 'benchmark') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">speed</i>
                                <span>Benchmark Report</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;"
                        data-bs-toggle="dropdown">
                        <i class="material-icons-outlined">tune</i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">Customise Theme</li>
                        <li>
                            <input type="radio" class="btn-check" name="theme-options" id="LightTheme" checked>
                            <label class="dropdown-item d-flex align-items-center py-2" for="LightTheme">
                                <span class="material-icons-outlined">light_mode</span>
                                <span class="ms-2">Light</span>
                            </label>
                        </li>
                        <li>
                            <input type="radio" class="btn-check" name="theme-options" id="DarkTheme">
                            <label class="dropdown-item d-flex align-items-center py-2" for="DarkTheme">
                                <span class="material-icons-outlined">dark_mode</span>
                                <span class="ms-2">Dark</span>
                            </label>
                        </li>
                        <li>
                            <input type="radio" class="btn-check" name="theme-options" id="SemiDarkTheme">
                            <label class="dropdown-item d-flex align-items-center py-2" for="SemiDarkTheme">
                                <span class="material-icons-outlined">contrast</span>
                                <span class="ms-2">Semi Dark</span>
                            </label>
                        </li>
                        <li>
                            <input type="radio" class="btn-check" name="theme-options" id="BoderedTheme">
                            <label class="dropdown-item d-flex align-items-center py-2" for="BoderedTheme">
                                <span class="material-icons-outlined">border_style</span>
                                <span class="ms-2">Bordered</span>
                            </label>
                        </li>
                    </ul>
                </li>

                {{-- Self-service account dropdown — currently just Change Password, but a good slot for future profile actions --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="javascript:;"
                        data-bs-toggle="dropdown" title="My Account">
                        <i class="material-icons-outlined">account_circle</i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">My Account</li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2"
                               href="{{ route('admin.profile.change-password') }}">
                                <i class="material-icons-outlined">lock_reset</i>
                                <span>Change Password</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="{{ route('admin.logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="nav-link logout-circle">
                        <i class="material-icons-outlined">power_settings_new</i>
                    </a>
                    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    {{-- <main class="main-wrapper" id="main-wrapper">
        <div class="main-content"> --}}
            @yield('content')
        {{-- </div>
    </main> --}}

    <!--user details offcanvas-->
    <div class="offcanvas offcanvas-start w-260" data-bs-scroll="true" tabindex="-1" id="offcanvasUserDetails">
        <div class="offcanvas-body">
            <div class="user-wrapper">
                <div class="text-center p-3 bg-light rounded">
                    <img src="{{ asset('assets/images/auth/smsexpertlogotwittersquareblueback.png') }}"
                        style="border-radius: 50% !important;" width="120" height="100" alt="">
                    <h5 class="user-name mb-0 fw-bold" style="word-wrap: break-word;">
                        {{ ucfirst(urldecode($user_contactname ?? 'Admin')) }}</h5>
                </div>
                <div class="list-group list-group-flush mt-3 profil-menu fw-bold">
                    <a href="{{ route('admin.dashboard') }}"
                        class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-top"><i
                            class="material-icons-outlined">person_outline</i>Dashboard</a>
                    <a href="{{ route('admin.profile.change-password') }}"
                        class="list-group-item list-group-item-action d-flex align-items-center gap-2"><i
                            class="material-icons-outlined">lock_reset</i>Change Password</a>
                    <a href="{{ route('admin.logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form-2').submit();"
                        class="list-group-item list-group-item-action d-flex align-items-center gap-2 border-bottom"><i
                            class="material-icons-outlined">power_settings_new</i>Logout</a>
                    <form id="logout-form-2" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
        <div class="offcanvas-footer p-3 border-top">
            <div class="text-center">
                <button type="button" class="btn d-flex align-items-center gap-2" data-bs-dismiss="offcanvas"
                    style="color: #fff"><i class="material-icons-outlined">close</i><span>Close Sidebar</span></button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/plugins/peity/jquery.peity.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/apexchart/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/apexchart/apex-custom-chart.js') }}"></script>
    <script>
        $(".data-attributes span").peity("donut")
    </script>

    <script>
        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        }

        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('main-sidebar');
            const header = document.getElementById('top-header');
            const wrapper = document.getElementById('main-wrapper');
            const icon = document.getElementById('toggle-icon');
            const logo = document.getElementById('hide-logo');

            if (sidebar) sidebar.classList.toggle('collapsed');
            if (header) header.classList.toggle('sidebar-collapsed');
            if (wrapper) wrapper.classList.toggle('sidebar-collapsed');

            if (sidebar) {
                const collapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', collapsed);
                if (logo) logo.style.display = collapsed ? 'none' : 'block';
                icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
            }
        }

        // Restore state on load
        window.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('main-sidebar');
            const header = document.getElementById('top-header');
            const wrapper = document.getElementById('main-wrapper');
            const icon = document.getElementById('toggle-icon');
            const logo = document.getElementById('hide-logo');

            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                header.classList.add('sidebar-collapsed');
                wrapper.classList.add('sidebar-collapsed');
                icon.textContent = 'chevron_right';
                if (logo) logo.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Perfect Scrollbar for sidebar
            const sidebarNav = document.querySelector('.sidebar-nav');
            if (sidebarNav) {
                new PerfectScrollbar(sidebarNav, {
                    wheelSpeed: 1,
                    wheelPropagation: false,
                    minScrollbarLength: 20
                });
            }

            // Active navigation management
            function updateActiveNavigation() {
                const currentPath = window.location.pathname;
                const navLinks = document.querySelectorAll('.sidebar-nav .metismenu li a');

                navLinks.forEach(link => link.classList.remove('active'));

                let exactMatch = null;
                let partialMatch = null;

                navLinks.forEach(link => {
                    try {
                        const linkPath = new URL(link.href).pathname;
                        if (currentPath === linkPath) {
                            exactMatch = link;
                        } else if (linkPath !== '/' && linkPath.length > 1 && currentPath.startsWith(linkPath)) {
                            partialMatch = link;
                        }
                    } catch (e) {
                        console.warn('Error parsing link URL:', link.href);
                    }
                });

                const activeLink = exactMatch || partialMatch;
                if (activeLink) {
                    activeLink.classList.add('active');
                }
            }

            updateActiveNavigation();
            window.addEventListener('popstate', () => setTimeout(updateActiveNavigation, 100));

            // Close sidebar on mobile when clicking outside
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('main-sidebar');
                const toggle = document.querySelector('.mobile-menu-toggle');

                if (window.innerWidth <= 768 && sidebar) {
                    if (!sidebar.contains(event.target) && (!toggle || !toggle.contains(event.target))) {
                        sidebar.classList.remove('show');
                    }
                }
            });

            // Theme management
            const themeOptions = document.querySelectorAll('input[name="theme-options"]');

            function updateTheme(themeId) {
                themeOptions.forEach(radio => {
                    const label = document.querySelector(`label[for="${radio.id}"]`);
                    if (label) label.classList.remove('selected-label');
                });

                const selectedLabel = document.querySelector(`label[for="${themeId}"]`);
                if (selectedLabel) selectedLabel.classList.add('selected-label');

                const themes = {
                    'LightTheme': 'light',
                    'DarkTheme': 'dark',
                    'SemiDarkTheme': 'semi-dark',
                    'BoderedTheme': 'bordered-theme'
                };

                document.documentElement.setAttribute('data-bs-theme', themes[themeId] || 'light');
            }

            const savedTheme = localStorage.getItem('selectedTheme') || 'LightTheme';
            const radioToSelect = document.getElementById(savedTheme);
            if (radioToSelect) {
                radioToSelect.checked = true;
                updateTheme(savedTheme);
            }

            themeOptions.forEach(radio => {
                radio.addEventListener('change', function() {
                    updateTheme(radio.id);
                    localStorage.setItem('selectedTheme', radio.id);
                });
            });
        });
    </script>

    <script>
        document.getElementById('backButton').addEventListener('click', function() {
            const dashboardUrl = '/admin/dashboard';
            const restrictedUrls = [
                '/admin/customers',
                '/customer/emails',
                '/admin/virtual-numbers',
                '/admin/settings',
                '/admin/reports',
                '/admin/admin-users',
                '/admin/contracts'
            ];

            try {
                const currentPath = window.location.pathname.replace(/\/$/, '');
                if (restrictedUrls.includes(currentPath)) {
                    window.location.href = dashboardUrl;
                } else {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = dashboardUrl;
                    }
                }
            } catch (err) {
                window.location.href = dashboardUrl;
            }
        });
    </script>

    @stack('js')
</body>
</html>