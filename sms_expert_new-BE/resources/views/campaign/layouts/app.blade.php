<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Campaign Manager - SMS Expert')</title>
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
    <script>
        const csrfToken = "{{ csrf_token() }}";
    </script>
    <style>
        :root {
            --primary-color: #ea6118;
            --secondary-color: #293b50;
            --sidebar-width: 220px;
            --header-height: 70px;
        }

        html,
        body {
            background: #f8fafc;
            margin: 0;
            padding: 0;
            color: #293b50;
            overflow-x: hidden;
        }

        html {
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        body {
            overflow-y: hidden;
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

        button .material-icons,
        button .material-icons-outlined {
            margin-right: 4px !important;
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
            padding: 1rem;
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
            width: auto;
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

        .toggle-icon-hamburger {
            display: block;
            transition: all 0.3s ease;
        }

        .toggle-icon-close {
            display: none;
            transition: all 0.3s ease;
        }

        .main-sidebar.collapsed .toggle-icon-hamburger {
            display: none;
        }

        .main-sidebar.collapsed .toggle-icon-close {
            display: block;
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
            position: relative;
            overscroll-behavior: contain;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .sidebar-nav.mm-active {
            overflow-y: auto;
        }

        .sidebar-nav.mm-active::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar-nav.mm-active::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .sidebar-nav.mm-active::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        .sidebar-nav.mm-active::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .sidebar-nav.mm-active .ps__rail-y {
            right: 2px !important;
            width: 6px !important;
            background: rgba(255, 255, 255, 0.1) !important;
            border-radius: 4px !important;
        }

        .sidebar-nav.mm-active .ps__thumb-y {
            background: rgba(255, 255, 255, 0.3) !important;
            border-radius: 4px !important;
            width: 6px !important;
        }

        .sidebar-nav.mm-active .ps__rail-y:hover .ps__thumb-y {
            background: rgba(255, 255, 255, 0.5) !important;
        }

        .sidebar-nav:not(.mm-active) .ps__rail-y {
            display: none !important;
        }

        .sidebar-header,
        .sidebar-user {
            position: sticky;
            top: 0;
            z-index: 100;
            background: inherit;
            flex-shrink: 0;
        }

        .sidebar-nav .metismenu {
            height: auto;
            max-height: none;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .main-sidebar>.sidebar-header,
        .main-sidebar>.sidebar-user {
            pointer-events: auto;
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

        .sidebar-nav .metismenu li a.active {
            background: linear-gradient(135deg, #ea6118, #d1520e) !important;
            color: white !important;
        }

        .sidebar-nav .metismenu li a:not(.active) {
            background: transparent;
            transform: translateX(0);
            box-shadow: none;
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
            height: var(--header-height);
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
            height: 70px;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            background: #f8fafc;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--header-height));
            overflow-x: hidden;
            overflow-y: auto;
        }

        .main-wrapper.sidebar-collapsed {
            margin-left: 70px;
        }

        .main-content {
            padding: 2rem;
        }

        /* Disable scrollbar on main content */
        .main-wrapper::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .main-wrapper {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .main-content::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .main-content {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        /* Wallet Display */
        .wallet-displays {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            height: 40px;
            min-width: 80px;
            justify-content: center;
            color: white;
        }

        /* Campaign Manager Badge */
        .campaign-badge {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            height: 40px;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .campaign-badge i {
            margin-right: 6px;
        }

        /* Buy Online Button */
        .btn-buy-online {
            font-size: 14px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 12px;
            padding: 0.5rem 1rem;
            height: 40px;
            display: flex;
            align-items: center;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-buy-online:hover {
            background: white;
            color: #ea6118;
        }

        /* Navigation Items */
        .nav-item.d-flex {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem;
        }

        .nav-link.text-white {
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link.text-white:hover {
            color: #ea6118 !important;
             background: white !important;
        }

        /* Theme Dropdown */
        .dropdown-toggle-nocaret {
            text-decoration: none;
        }

        .dropdown-toggle-nocaret::after {
            display: none;
        }

        .selected-label {
            background: linear-gradient(135deg, #ea6118, #293b50) !important;
            color: #ffffff !important;
            border-radius: 12px !important;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        .nav-right-links {
            display: flex;
            align-items: center;
            gap: 1rem;
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

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 240px;
            }

            .main-sidebar {
                width: var(--sidebar-width);
                transform: translateX(-100%);
                z-index: 1050;
                transition: transform 0.3s ease;
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                background-color: #293b50;
            }

            .main-sidebar.show {
                transform: translateX(0);
            }

            .main-sidebar.show .menu-label {
                display: inline-block !important;
            }

            .main-sidebar:not(.show) .menu-label {
                display: none !important;
            }

            .top-header {
                left: 0;
                width: 100%;
            }

            .main-wrapper {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .campaign-badge {
                display: none !important;
            }
        }

        /* Smooth scrolling */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .table-responsive,
        .modern-table,
        .card-body,
        .overflow-auto {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .table-responsive::-webkit-scrollbar,
        .modern-table::-webkit-scrollbar,
        .card-body::-webkit-scrollbar,
        .overflow-auto::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .main-wrapper::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .main-wrapper {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .main-content>* {
            will-change: transform;
        }

        #main-sidebar.collapsed #hide-logo {
            display: none;
        }

        /* User Info in Header */
        /* .user-info-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        } */

          .user-info-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            height: 42px;
            white-space: nowrap;
        }

        .user-info-header .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ea6118, #d1520e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .user-info-header .user-details {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user-info-header .user-details .name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .user-info-header .user-details .role {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        @media (max-width: 992px) {
            .user-info-header .user-details {
                display: none;
            }
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
                <img src="{{ asset('assets/images/auth/smsexpert_cover.png') }}" alt="SMS Expert" class="sidebar-logo"
                    id="hide-logo">
            </div>
            <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Toggle Sidebar">
                <i id="toggle-icon" class="material-icons-outlined">chevron_left</i>
            </button>
        </div>

        @php
            $userInfo = Session::get('user_info');
            $user_contactname = urldecode($userInfo['contactname'] ?? ($userInfo['username'] ?? 'User'));

            $remainingWallet = 0;
            $userName = $userInfo['contactname'] ?? ($userInfo['username'] ?? 'User');
            if (isset($userInfo['bigid'])) {
                $bigid = $userInfo['bigid'];
                $user = \App\Models\User::where('bigid', $bigid)->first();
                if ($user) {
                    $smsgWallet = $user->smsg_wallet ?? 0;
                    $smsgServer1Sent = $user->smsg_server1_sent ?? 0;
                    $smsgServer2Sent = $user->smsg_server2_sent ?? 0;
                    $remainingWallet = $smsgWallet - $smsgServer1Sent - $smsgServer2Sent;
                    $userName = urldecode($user->contactname ?? ($user->uname ?? 'User'));
                    $dashboard_access = $user->dashboardaccess ?? 'm';
                    $cus_login = $user->uname ?? '';
                }
            }
        @endphp

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <ul class="metismenu" id="sidenav">

                {{-- Campaign Manager --}}
                @if (in_array($dashboard_access, ['m', 'c', 'a', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.dashboard') }}"
                            class="{{ Request::is('campaign/dashboard') ? 'active' : '' }}"
                            style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">home</i>
                            </div>
                            <div class="menu-title">Campaign Manager</div>
                        </a>
                    </li>
                @endif

                {{-- Main Dashboard --}}
                {{-- Main Dashboard --}}
                @if (in_array($dashboard_access, ['m', 'c', 'a', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.dashboard.link', ['username' => $cus_login]) }}"
                            class="{{ Request::is('campaign/main-dashboard') ? 'active' : '' }}"
                            style="border-radius: 15px;" target="_blank">
                            <div class="parent-icon" style="margin: 0px">
                                <i class="material-icons-outlined">dashboard</i>
                            </div>
                            <div class="menu-title">Main Dashboard</div>
                        </a>
                    </li>

                    {{-- <li style="height: 45px;">
                        <a href="{{ route('campaign.main.dashboard') }}"
                            class="{{ Request::is('campaign/main-dashboard') ? 'active' : '' }}"
                            style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i
                                    class="material-icons-outlined">dashboard</i></div>
                            <div class="menu-title">Main Dashboard</div>
                        </a>
                    </li> --}}
                @endif

                {{-- Quick Campaign --}}
                @if (in_array($dashboard_access, ['c', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.quick') }}"
                            class="{{ Request::is('campaign/quick') ? 'active' : '' }}" style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">send</i>
                            </div>
                            <div class="menu-title">Quick Campaign</div>
                        </a>
                    </li>
                @endif

                {{-- Bulk Campaign --}}
                @if (in_array($dashboard_access, ['c', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.upload') }}"
                            class="{{ Request::is('campaign/upload') ? 'active' : '' }}" style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i
                                    class="material-icons-outlined">upload_file</i></div>
                            <div class="menu-title">Bulk Campaign</div>
                        </a>
                    </li>
                @endif

                {{-- Campaigns History --}}
                @if (in_array($dashboard_access, ['c', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.previous.list') }}"
                            class="{{ Request::is('campaign/previous') ? 'active' : '' }}"
                            style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">history</i>
                            </div>
                            <div class="menu-title">Campaigns History</div>
                        </a>
                    </li>
                @endif

                {{-- STOP Blacklist --}}
                @if (in_array($dashboard_access, ['c', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.blacklist') }}"
                            class="{{ Request::is('campaign/blacklist*') ? 'active' : '' }}"
                            style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">block</i>
                            </div>
                            <div class="menu-title">STOP Blacklist</div>
                        </a>
                    </li>
                @endif

                {{-- View Accounts --}}
                @if (in_array($dashboard_access, ['c', 'a', 'mc', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.accounts') }}"
                            class="{{ Request::is('campaign/accounts') ? 'active' : '' }}"
                            style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i
                                    class="material-icons-outlined">people</i></div>
                            <div class="menu-title">View Accounts</div>
                        </a>
                    </li>
                @endif

                {{-- New Sub Account --}}
                @if (in_array($dashboard_access, ['a', 'ca', 'mca']))
                    <li style="height: 45px;">
                        <a href="{{ route('campaign.accounts.add') }}"
                            class="{{ Request::is('campaign/accounts/add') ? 'active' : '' }}"
                            style="border-radius: 15px;">
                            <div class="parent-icon" style="margin: 0px"><i
                                    class="material-icons-outlined">person_add</i></div>
                            <div class="menu-title">New Sub-Account</div>
                        </a>
                    </li>
                @endif

            </ul>
        </nav>
    </aside>

    <!-- Header -->
    <header class="top-header" id="top-header">
        <nav class="navbar navbar-expand align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i class="material-icons-outlined">menu</i>
                </button>

                <!-- Campaign Manager Badge -->
                {{-- <div class="campaign-badge">
                    <i class="material-icons-outlined" style="font-size: 18px;">campaign</i>
                    Campaign Manager
                </div> --}}
            </div>

            <ul class="navbar-nav gap-1 nav-right-links align-items-center">

                <!-- Wallet Display -->
                <li class="nav-item d-flex align-items-center">
                    <div class="wallet-displays me-2">
                        <i class="material-icons-outlined me-1" style="font-size: 16px;">currency_pound</i>
                        <span class="fw-bold">{{ sprintf('%.2f', $remainingWallet) }}</span>
                        {{-- <i class="material-icons-outlined me-1" style="font-size: 16px;">account_balance_wallet</i>
                        <span class="fw-bold">£ {{ sprintf('%.2f', $remainingWallet) }}</span> --}}
                    </div>
                </li>

                <!-- User Info -->
                <li class="nav-item">
                    <div class="user-info-header">
                        <div class="user-avatar">
                            {{ strtoupper(substr($userName, 0, 1)) }}
                        </div>
                        <div class="user-details">
                            <span class="name">{{ ucfirst($userName) }}</span>
                            <span class="role">Campaign User</span>
                        </div>
                    </div>
                </li>

                <!-- Help Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret text-white" href="javascript:;"
                        data-bs-toggle="dropdown" title="Help & Tour" style="width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 12px;">
                        <i class="material-icons-outlined">help_outline</i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px; border: none; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); border-radius: 12px;">
                        <li class="dropdown-header">Help</li>
                        <li>
                            <a class="dropdown-item tour-restart-btn" href="javascript:;" data-tour-restart style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;">
                                <i class="material-icons-outlined" style="color: #ea6118;">tour</i>
                                <span>Take a Tour</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('campaign.download.instructions') }}" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;">
                                <i class="material-icons-outlined" style="color: #ea6118;">description</i>
                                <span>Instructions</span>
                            </a>
                        </li>
                        {{-- Customer user guides — only reachable from the campaign layout, which itself requires customer login --}}
                        <li>
                            <a class="dropdown-item" href="{{ route('customer.userguide.show', 'dashboard') }}" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;">
                                <i class="material-icons-outlined" style="color: #ea6118;">menu_book</i>
                                <span>Dashboard Guide</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('customer.userguide.show', 'campaign') }}" target="_blank" rel="noopener" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;">
                                <i class="material-icons-outlined" style="color: #ea6118;">campaign</i>
                                <span>Campaign Guide</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Theme Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret text-white" href="javascript:;"
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

                <!-- Logout -->
                <li class="nav-item">
                    <a href="{{ route('campaign.logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="nav-link text-white" title="Logout">
                        <i class="material-icons-outlined">power_settings_new</i>
                    </a>
                </li>
            </ul>

            <!-- Hidden logout form -->
            <form id="logout-form" action="{{ route('campaign.logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            @yield('content')
        </div>
    </main>

    <!-- Scripts -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

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
                localStorage.setItem('campaignSidebarCollapsed', collapsed);

                if (logo) logo.style.display = collapsed ? 'none' : 'block';
                icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
            }
        }

        // On load, restore state
        window.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('main-sidebar');
            const header = document.getElementById('top-header');
            const wrapper = document.getElementById('main-wrapper');
            const icon = document.getElementById('toggle-icon');
            const logo = document.getElementById('hide-logo');

            if (localStorage.getItem('campaignSidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                header.classList.add('sidebar-collapsed');
                wrapper.classList.add('sidebar-collapsed');
                icon.textContent = 'chevron_right';
                if (logo) logo.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Perfect Scrollbar for sidebar navigation
            const sidebarNav = document.querySelector('.sidebar-nav');
            if (sidebarNav) {
                const menuContainer = sidebarNav.querySelector('.metismenu');
                if (menuContainer) {
                    const ps = new PerfectScrollbar(sidebarNav, {
                        wheelSpeed: 1,
                        wheelPropagation: false,
                        minScrollbarLength: 20,
                        suppressScrollX: true,
                        useBothWheelAxes: false,
                        swipeEasing: true,
                        handlers: ['click-rail', 'drag-thumb', 'keyboard', 'touch']
                    });

                    function checkScrollNeeded() {
                        const hasOverflow = sidebarNav.scrollHeight > sidebarNav.clientHeight;
                        if (hasOverflow) {
                            sidebarNav.classList.add('mm-active');
                            sidebarNav.classList.add('ps');
                            sidebarNav.classList.add('ps--active-y');
                        } else {
                            sidebarNav.classList.remove('mm-active');
                            sidebarNav.classList.remove('ps--active-y');
                        }
                        ps.update();
                    }

                    checkScrollNeeded();
                    window.addEventListener('resize', checkScrollNeeded);

                    const sidebar = document.getElementById('main-sidebar');
                    if (sidebar) {
                        const observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.type === 'attributes' && mutation.attributeName ===
                                    'class') {
                                    setTimeout(checkScrollNeeded, 100);
                                }
                            });
                        });
                        observer.observe(sidebar, {
                            attributes: true,
                            attributeFilter: ['class']
                        });
                    }

                    sidebarNav.addEventListener('wheel', function(e) {
                        const delta = e.deltaY;
                        const scrollTop = sidebarNav.scrollTop;
                        const scrollHeight = sidebarNav.scrollHeight;
                        const clientHeight = sidebarNav.clientHeight;
                        const hasOverflow = scrollHeight > clientHeight;

                        if (hasOverflow) {
                            const atTop = scrollTop <= 0;
                            const atBottom = scrollTop + clientHeight >= scrollHeight - 1;
                            e.stopPropagation();

                            if ((delta < 0 && atTop) || (delta > 0 && atBottom)) {
                                e.preventDefault();
                            } else {
                                const scrollAmount = delta * 3;
                                sidebarNav.scrollTop += scrollAmount;
                                e.preventDefault();
                            }
                        } else {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    }, {
                        passive: false
                    });

                    sidebarNav.addEventListener('touchmove', function(e) {
                        e.stopPropagation();
                    }, {
                        passive: true
                    });
                }
            }

            // Navigation active state management
            function updateActiveNavigation() {
                const currentPath = window.location.pathname;
                const navLinks = document.querySelectorAll('.sidebar-nav .metismenu li a');

                navLinks.forEach(link => {
                    link.classList.remove('active');
                });

                let exactMatch = null;
                let partialMatch = null;

                navLinks.forEach(link => {
                    try {
                        const linkPath = new URL(link.href).pathname;

                        if (currentPath === linkPath) {
                            exactMatch = link;
                        } else if (linkPath !== '/' && linkPath.length > 1 && currentPath.startsWith(
                                linkPath)) {
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

            window.addEventListener('popstate', function() {
                setTimeout(updateActiveNavigation, 100);
            });

            let isNavigating = false;
            const navLinks = document.querySelectorAll('.sidebar-nav .metismenu li a');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    isNavigating = true;
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');

                    setTimeout(() => {
                        isNavigating = false;
                        updateActiveNavigation();
                    }, 1000);
                });
            });

            setInterval(() => {
                if (!isNavigating) {
                    updateActiveNavigation();
                }
            }, 1000);

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

            // Restore sidebar state
            const sidebarCollapsed = localStorage.getItem('campaignSidebarCollapsed');
            if (sidebarCollapsed === 'true') {
                const sidebar = document.getElementById('main-sidebar');
                const header = document.getElementById('top-header');
                const wrapper = document.getElementById('main-wrapper');

                if (sidebar) sidebar.classList.add('collapsed');
                if (header) header.classList.add('sidebar-collapsed');
                if (wrapper) wrapper.classList.add('sidebar-collapsed');
            }

            console.log('Campaign Manager layout loaded successfully');

            // Enhanced smooth scrolling
            const mainWrapper = document.querySelector('.main-wrapper');
            if (mainWrapper) {
                mainWrapper.style.webkitOverflowScrolling = 'touch';

                let ticking = false;

                function updateScrollPerformance() {
                    if (!ticking) {
                        requestAnimationFrame(() => {
                            ticking = false;
                        });
                        ticking = true;
                    }
                }

                mainWrapper.addEventListener('scroll', updateScrollPerformance, {
                    passive: true
                });

                document.addEventListener('click', function(e) {
                    if (e.target.matches('a[href^="#"]')) {
                        e.preventDefault();
                        const target = document.querySelector(e.target.getAttribute('href'));
                        if (target) {
                            target.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                    }
                });
            }
        });
    </script>

    @stack('js')

    {{-- Tour Guide --}}
    @include('partials.tour.campaign-tour')
</body>

</html>
