<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SMS Expert')</title>
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

        body {
            background: #f8fafc;
            margin: 0;
            padding: 0;
            color: #293b50;
        }


        /* Material Icons Fix */
        /*.material-icons-outlined,
         .material-icons {
            font-family: 'Material Icons Outlined', 'Material Icons' !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 20px !important;
            line-height: 1 !important;
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        } */

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
            /* background: linear-gradient(135deg, #ea6118, #293b50); */
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

        /* NEW: Material Icon Toggle Button */
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
            /* transform: scale(1.05); */
        }

        /* Toggle Icons - Show different icon based on state */
        .toggle-icon-hamburger {
            display: block;
            transition: all 0.3s ease;
        }

        .toggle-icon-close {
            display: none;
            transition: all 0.3s ease;
        }

        /* When collapsed, show X icon instead of hamburger */
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

        /* Hide scrollbar by default */
        .sidebar-nav::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        /* Show scrollbar only when mm-active class is present */
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

        /* Perfect scrollbar styles for mm-active state */
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

        /* Hide scrollbar when not mm-active */
        .sidebar-nav:not(.mm-active) .ps__rail-y {
            display: none !important;
        }

        /* Ensure sidebar header and user sections are not scrollable */
        .sidebar-header,
        .sidebar-user {
            position: sticky;
            top: 0;
            z-index: 100;
            background: inherit;
            flex-shrink: 0;
        }

        /* Make only the navigation menu scrollable */
        .sidebar-nav .metismenu {
            height: auto;
            max-height: none;
        }

        /* Prevent scroll from affecting header elements */
        .main-sidebar>.sidebar-header,
        .main-sidebar>.sidebar-user {
            pointer-events: auto;
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
            /* padding: 0.8rem 1rem; */
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

        /* Ensure only one active item at a time */
        .sidebar-nav .metismenu li a.active {
            background: linear-gradient(135deg, #ea6118, #d1520e) !important;
            color: white !important;
        }

        /* Remove active state from non-current items */
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
            /* height: 100%; */
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            /* height: 100vh; */
            background: #f8fafc;
            transition: margin-left 0.3s ease;

        }

        .main-wrapper.sidebar-collapsed {
            margin-left: 70px;
        }

        .main-content {
            padding: 2rem;
            /* min-height: 100%;
            overflow-x: hidden;
            scroll-behavior: smooth; */
        }

        /* Wallet Display - Individual CSS */
        .wallet-displays {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            height: 42px;
            min-width: 100px;
            justify-content: center;
            color: white;
            white-space: nowrap;
        }

        /* Buy Online Button - Individual CSS */
        .btn-buy-online {
            font-size: 14px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 12px;
            padding: 0.5rem 1.25rem;
            height: 42px;
            display: flex;
            align-items: center;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        /* Buy Online Button Hover - Individual CSS */
        .btn-buy-online:hover {
            background: white;
            color: #ea6118;
        }

        /* Wallet Navigation Item - Individual CSS */
        .nav-item.d-flex {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem;
        }

        /* Notification Navigation Link - Individual CSS */
        .nav-link.text-white {
            color: white;
            text-decoration: none;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-link.text-white:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Notification Badge - Individual CSS */
        .notification-badge {
            font-size: 0.65rem;
            padding: 0.2em 0.4em;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            top: -5px !important;
            right: -5px !important;
            left: auto !important;
            transform: none !important;
        }

        /* Notification Dropdown - Individual CSS */
        .notification-dropdown {
            min-width: 350px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
            padding: 0;
        }

        /* Notification Item - Individual CSS */
        .notification-item {
            border: none;
            padding: 1rem !important;
            transition: all 0.2s ease;
        }

        /* Notification Item Hover - Individual CSS */
        .notification-item:hover {
            background: #f8f9fa;
        }

        /* Notification Icon Container - Individual CSS */
        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(234, 97, 24, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Notification Content - Individual CSS */
        .notification-content {
            flex: 1;
        }

        /* Notification Title - Individual CSS */
        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #293b50;
            margin-bottom: 0.25rem;
        }

        /* Notification Text - Individual CSS */
        .notification-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        /* Notification Time - Individual CSS */
        .notification-time {
            font-size: 0.75rem;
            color: #adb5bd;
        }

        /* Theme Dropdown - Individual CSS */
        .dropdown-toggle-nocaret {
            text-decoration: none;
        }

        /* Theme Dropdown After - Individual CSS */
        .dropdown-toggle-nocaret::after {
            display: none;
        }

        /* Selected Theme Label - Individual CSS */
        .selected-label {
            background: linear-gradient(135deg, #ea6118, #293b50) !important;
            color: #ffffff !important;
            border-radius: 12px !important;
        }

        /* Logout Navigation Link - Individual CSS */
        .nav-link.text-white.logout-link {
        transition: all 0.3s ease;
        }
        
        .nav-link.text-white.logout-link:hover {
            color: #ea6118 !important;
        background: white !important;
        }

        /* Material Icons Hover Effect - Individual CSS */
        .nav-link:hover .material-icons-outlined {
            transform: scale(1.1);
            transition: transform 0.2s ease;
        }

        /* Mobile Menu Toggle - Individual CSS */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        /* Top Header Navigation - Individual CSS */
        .top-header .navbar {
            padding: 0 2rem;
        }

        /* Navigation Right Links - Individual CSS */
        .nav-right-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .nav-right-links .nav-item {
            margin: 0;
        }

        /* Enhanced collapsed button styling */
        .main-sidebar.collapsed .sidebar-collapse-btn {
            margin-left: -5px;
        }

        /* Smooth icon transitions */
        .sidebar-collapse-btn i {
            transition: all 0.3s ease;
        }

        /* .sidebar-collapse-btn:hover i {
            transform: rotate(90deg);
        } */

        /* @media (max-width: 768px) {
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
        } */

        @media (max-width: 768px) {
            :root {
                --sidebar-width: 240px;
                /* keep normal width when shown */
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
                /* keep background visible */
            }

            .main-sidebar.show {
                transform: translateX(0);
            }

            /* Ensure icon labels are visible when sidebar is open */
            .main-sidebar.show .menu-label {
                display: inline-block !important;
            }

            /* Hide labels when collapsed */
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
        }

        /* Enhanced smooth scrolling for all elements */
        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Smooth scrolling for scrollable containers - HIDDEN SCROLLBARS */
        .table-responsive,
        .modern-table,
        .card-body,
        .overflow-auto {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            /* Firefox */
            -ms-overflow-style: none;
            /* IE and Edge */
        }

        /* Hide scrollbars for WebKit browsers */
        .table-responsive::-webkit-scrollbar,
        .modern-table::-webkit-scrollbar,
        .card-body::-webkit-scrollbar,
        .overflow-auto::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        /* Optimize scrollbar styling for main content - HIDDEN */
        .main-wrapper::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .main-wrapper::-webkit-scrollbar-track {
            background: transparent;
        }

        .main-wrapper::-webkit-scrollbar-thumb {
            background: transparent;
        }

        .main-wrapper::-webkit-scrollbar-thumb:hover {
            background: transparent;
        }

        /* Hide scrollbar for Firefox */
        .main-wrapper {
            scrollbar-width: none;
        }

        /* Hide scrollbar for IE and Edge */
        .main-wrapper {
            -ms-overflow-style: none;
        }

        /* Optimize performance for smooth scrolling */
        .main-content>* {
            will-change: transform;
        }

        /* Enhanced collapsed button styling */
        .main-sidebar.collapsed .sidebar-collapse-btn {
            margin-left: -5px;
        }

        /* Smooth icon transitions */
        /* .sidebar-collapse-btn i {
            transition: all 0.3s ease;
        } */

        /* .sidebar-collapse-btn:hover i {
            transform: rotate(90deg);
        } */

        /* Hide the logo when sidebar is collapsed */
        #main-sidebar.collapsed #hide-logo {
            display: none;
        }

        /* User Info in Header */
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

        /* Global Modal Z-Index Fix - CRITICAL */
        .modal {
            z-index: 9999 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            outline: 0 !important;
        }

        .modal-backdrop {
            z-index: 9998 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }

        .modal-dialog {
            z-index: 10000 !important;
            pointer-events: auto !important;
            position: relative !important;
            margin: 1.75rem auto !important;
        }

        .modal.show {
            display: block !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }

        .modal-backdrop.show {
            opacity: 1 !important;
        }

        .modal-content {
            pointer-events: auto !important;
            position: relative !important;
            background-color: #fff !important;
            background-clip: padding-box !important;
            border: none !important;
            border-radius: 0.5rem !important;
            outline: 0 !important;
        }

        /* Ensure body doesn't block modal */
        body.modal-open {
            overflow: hidden !important;
            padding-right: 0 !important;
        }

        /* Ensure sidebar doesn't block modal */
        .main-sidebar {
            z-index: 1001 !important;
        }

        /* When modal is open, ensure it's above sidebar */
        body.modal-open .modal,
        body.modal-open .modal-backdrop {
            z-index: 9999 !important;
        }

        body.modal-open .modal-backdrop {
            z-index: 9998 !important;
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
                {{-- <span class="sidebar-title">SMS Expert</span> --}}
            </div>
            <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Toggle Sidebar">
                <i id="toggle-icon" class="material-icons-outlined">chevron_left</i>
            </button>

        </div>

        <!-- User Profile -->
        @php
            $userInfo = Session::get('user_info');
            $user_contactname = urldecode($userInfo['contactname'] ?? 'User');
        @endphp
        {{-- <div class="sidebar-user">
            <img src="{{ asset('assets/images/auth/smsexpertlogotwittersquareblueback.png') }}" alt="User" class="user-logo">
            <h6 class="user-name">{{ ucfirst($user_contactname) }}</h6>
            <small class="user-type">SMS Expert User</small>
        </div> --}}

        <!-- Navigation -->
        <nav class="sidebar-nav">
            <ul class="metismenu" id="sidenav">
                <li style="height: 45px;">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">home</i></div>
                        <div class="menu-title">Dashboard</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('sms_wallet.index') }}"
                        class="{{ request()->routeIs('sms_wallet.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">account_balance_wallet</i></div>
                        <div class="menu-title">SMS Wallet</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('sendsms') }}" class="{{ request()->routeIs('sendsms') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">send</i></div>
                        <div class="menu-title">Send New SMS</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('received-sms') }}"
                        class="{{ request()->routeIs('received-sms') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">cloud_download</i></div>
                        <div class="menu-title">Received SMS</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('sentsms') }}" class="{{ request()->routeIs('sentsms') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">forum</i></div>
                        <div class="menu-title">Sent SMS</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('keywords') }}" class="{{ request()->routeIs('keywords') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">apps</i></div>
                        <div class="menu-title">Keywords</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('numbers.index') }}"
                        class="{{ request()->routeIs('numbers.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">format_list_bulleted</i></div>
                        <div class="menu-title">Numbers</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('groups.index') }}"
                        class="{{ request()->routeIs('groups.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">group</i>
                        </div>
                        <div class="menu-title">Groups</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">account_circle</i></div>
                        <div class="menu-title">Client Profile</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('contracts.index') }}"
                        class="{{ request()->routeIs('contracts.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">assignment</i>
                        </div>
                        <div class="menu-title">Contracts</div>
                    </a>
                </li>

                <li style="height: 45px;">
                    <a href="{{ route('view.invoices') }}"
                        class="{{ request()->routeIs('view.invoices') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">description</i></div>
                        <div class="menu-title">Invoices</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('technical.support') }}"
                        class="{{ request()->routeIs('technical.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">brightness_7</i></div>
                        <div class="menu-title">Technical Docs</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('delivery-receipt') }}"
                        class="{{ request()->routeIs('delivery-receipt') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i
                                class="material-icons-outlined">import_contacts</i></div>
                        <div class="menu-title">Delivery Receipt</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('stopcommand.index') }}"
                        class="{{ request()->routeIs('stopcommand.*') ? 'active' : '' }}"
                        style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">support</i>
                        </div>
                        <div class="menu-title">STOPs/Optouts</div>
                    </a>
                </li>
                <li style="height: 45px;">
                    <a href="{{ route('blacklist.index') }}"
                        class="{{ request()->routeIs('blacklist.*') ? 'active' : '' }}" style="border-radius: 15px;">
                        <div class="parent-icon" style="margin: 0px"><i class="material-icons-outlined">block</i>
                        </div>
                        <div class="menu-title">Blacklist</div>
                    </a>
                </li>
                
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

            <ul class="navbar-nav gap-3 nav-right-links align-items-center">
                @php
                    $userInfo = Session::get('user_info');
                    $remainingWallet = 0;
                    $userName = $userInfo['contactname'] ?? $userInfo['username'] ?? 'User';
                    if (isset($userInfo['bigid'])) {
                        $bigid = $userInfo['bigid'];
                        $user = \App\Models\User::where('bigid', $bigid)->first();
                        if ($user) {
                            $smsgWallet = $user->smsg_wallet ?? 0;
                            $smsgServer1Sent = $user->smsg_server1_sent ?? 0;
                            $smsgServer2Sent = $user->smsg_server2_sent ?? 0;
                            $remainingWallet = $smsgWallet - $smsgServer1Sent - $smsgServer2Sent;
                            $userName = urldecode($user->contactname ?? $user->uname ?? 'User');
                        }
                    }
                @endphp

                <li class="nav-item d-flex align-items-center" style="gap: 0.75rem;">
                    <div class="wallet-displays">
                        <i class="material-icons-outlined me-1" style="font-size: 16px;">currency_pound</i>
                        <span class="fw-bold">{{ sprintf('%.2f', $remainingWallet) }}</span>
                    </div>
                    <a href="{{ route('buysms') }}" class="btn btn-buy-online">Buy Online</a>
                </li>

                <!-- Notifications -->
                @include('partials.customer-notifications')

                <!-- User Info -->
                <li class="nav-item">
                    <div class="user-info-header">
                        <div class="user-avatar">
                            {{ strtoupper(substr($userName, 0, 1)) }}
                        </div>
                        <div class="user-details">
                            <span class="name">{{ ucfirst($userName) }}</span>
                            <span class="role">Dashboard User</span>
                        </div>
                    </div>
                </li>

                <!-- Notifications -->
                {{-- <li class="nav-item">
                    <a class="nav-link text-white position-relative d-flex align-items-center justify-content-center"
                        href="javascript:;" data-bs-toggle="dropdown" style="width: 40px; height: 40px;">
                        <i class="material-icons-outlined">notifications</i>
                        <span class="position-absolute badge rounded-pill bg-danger notification-badge">
                            3
                            <span class="visually-hidden">unread notifications</span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <small class="text-muted">3 new</small>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item notification-item py-3" href="#">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon me-3">
                                        <i class="material-icons-outlined text-success">check_circle</i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">SMS Delivered Successfully</div>
                                        <div class="notification-text">Your message to +44123456789 was delivered</div>
                                        <div class="notification-time">2 minutes ago</div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item notification-item py-3" href="#">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon me-3">
                                        <i class="material-icons-outlined text-primary">account_balance_wallet</i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">Credits Added</div>
                                        <div class="notification-text">£50.00 credits added to your wallet</div>
                                        <div class="notification-time">1 hour ago</div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item notification-item py-3" href="#">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon me-3">
                                        <i class="material-icons-outlined text-warning">warning</i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title">Low Credit Warning</div>
                                        <div class="notification-text">Your SMS credits are running low</div>
                                        <div class="notification-time">3 hours ago</div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <a class="dropdown-item text-center py-2" href="#">
                                <small>View All Notifications</small>
                            </a>
                        </li>
                    </ul>
                </li> --}}

                <!-- Help Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret text-white help-menu-trigger" href="javascript:;"
                        data-bs-toggle="dropdown" title="Help & Tour">
                        <i class="material-icons-outlined">help_outline</i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end help-dropdown-menu">
                        <li class="dropdown-header">Help</li>
                        <li>
                            <a class="dropdown-item help-dropdown-item tour-restart-btn" href="javascript:;" data-tour-restart>
                                <i class="material-icons-outlined">tour</i>
                                <span>Take a Tour</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item help-dropdown-item" href="{{ route('technical.support') }}">
                                <i class="material-icons-outlined">description</i>
                                <span>Documentation</span>
                            </a>
                        </li>
                        {{-- Customer user guides — only reachable from the customer layout, which itself requires customer login --}}
                        <li>
                            <a class="dropdown-item help-dropdown-item" href="{{ route('customer.userguide.show', 'dashboard') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">menu_book</i>
                                <span>Dashboard Guide</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item help-dropdown-item" href="{{ route('customer.userguide.show', 'campaign') }}" target="_blank" rel="noopener">
                                <i class="material-icons-outlined">campaign</i>
                                <span>Campaign Guide</span>
                            </a>
                        </li>
                    </ul>
                </li>

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

                <li class="nav-item">
                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                         class="nav-link text-white" title="Logout">
                        {{-- class="nav-link text-white logout-link" title="Logout"> --}}
                        <i class="material-icons-outlined">power_settings_new</i>
                    </a>
                </li>
            </ul>

            <!-- Hidden logout form -->
            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
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

            // Save state to localStorage
            if (sidebar) {
                const collapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', collapsed);

                // Hide/show logo
                if (logo) logo.style.display = collapsed ? 'none' : 'block';

                // Switch icon
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

            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                header.classList.add('sidebar-collapsed');
                wrapper.classList.add('sidebar-collapsed');
                icon.textContent = 'chevron_right';
                if (logo) logo.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Perfect Scrollbar for sidebar navigation only
            const sidebarNav = document.querySelector('.sidebar-nav');
            if (sidebarNav) {
                // Create a more specific container for scrolling
                const menuContainer = sidebarNav.querySelector('.metismenu');
                if (menuContainer) {
                    // Apply Perfect Scrollbar to the menu container, not the entire nav
                    const ps = new PerfectScrollbar(sidebarNav, {
                        wheelSpeed: 1,
                        wheelPropagation: false,
                        minScrollbarLength: 20,
                        suppressScrollX: true,
                        useBothWheelAxes: false,
                        swipeEasing: true,
                        handlers: ['click-rail', 'drag-thumb', 'keyboard', 'touch']
                    });

                    // Add mm-active class when content overflows
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
                        // Update Perfect Scrollbar
                        ps.update();
                    }

                    // Check on load and resize
                    checkScrollNeeded();
                    window.addEventListener('resize', checkScrollNeeded);

                    // Check when sidebar is collapsed/expanded
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

                    // Enhanced scroll event handling for better containment
                    sidebarNav.addEventListener('wheel', function(e) {
                        const delta = e.deltaY;
                        const scrollTop = sidebarNav.scrollTop;
                        const scrollHeight = sidebarNav.scrollHeight;
                        const clientHeight = sidebarNav.clientHeight;

                        // Check if content actually needs scrolling
                        const hasOverflow = scrollHeight > clientHeight;

                        if (hasOverflow) {
                            // Check if we're at the top or bottom
                            const atTop = scrollTop <= 0;
                            const atBottom = scrollTop + clientHeight >= scrollHeight - 1;

                            // Always stop propagation for sidebar scroll events
                            e.stopPropagation();

                            // Allow scrolling within bounds, prevent beyond boundaries
                            if ((delta < 0 && atTop) || (delta > 0 && atBottom)) {
                                e.preventDefault();
                            } else {
                                // Allow normal wheel scrolling
                                const scrollAmount = delta * 3; // Adjust scroll speed
                                sidebarNav.scrollTop += scrollAmount;
                                e.preventDefault();
                            }
                        } else {
                            // No overflow, prevent any scrolling
                            e.preventDefault();
                            e.stopPropagation();
                        }
                    }, {
                        passive: false
                    });

                    // Also enable wheel scrolling for the entire sidebar
                    const mainSidebar = document.getElementById('main-sidebar');
                    if (mainSidebar) {
                        mainSidebar.addEventListener('wheel', function(e) {
                            // Only handle if the scroll is over the navigation area
                            const isOverNav = e.target.closest('.sidebar-nav');
                            if (isOverNav) {
                                // Let the sidebar-nav handler take care of it
                                return;
                            }

                            // If scrolling over header/user area, redirect to nav
                            const hasOverflow = sidebarNav.scrollHeight > sidebarNav.clientHeight;
                            if (hasOverflow) {
                                const delta = e.deltaY;
                                const scrollTop = sidebarNav.scrollTop;
                                const scrollHeight = sidebarNav.scrollHeight;
                                const clientHeight = sidebarNav.clientHeight;

                                const atTop = scrollTop <= 0;
                                const atBottom = scrollTop + clientHeight >= scrollHeight - 1;

                                e.stopPropagation();

                                if (!((delta < 0 && atTop) || (delta > 0 && atBottom))) {
                                    const scrollAmount = delta * 3;
                                    sidebarNav.scrollTop += scrollAmount;
                                }
                                e.preventDefault();
                            }
                        }, {
                            passive: false
                        });
                    }

                    // Prevent touch scroll propagation
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

                // Remove active class from all navigation items
                navLinks.forEach(link => {
                    link.classList.remove('active');
                });

                // Find the exact matching link
                let exactMatch = null;
                let partialMatch = null;

                navLinks.forEach(link => {
                    try {
                        const linkPath = new URL(link.href).pathname;

                        // Exact match has priority
                        if (currentPath === linkPath) {
                            exactMatch = link;
                        }
                        // Partial match for nested routes (but not root)
                        else if (linkPath !== '/' && linkPath.length > 1 && currentPath.startsWith(
                                linkPath)) {
                            partialMatch = link;
                        }
                    } catch (e) {
                        // Handle any URL parsing errors
                        console.warn('Error parsing link URL:', link.href);
                    }
                });

                // Apply active class to the best match
                const activeLink = exactMatch || partialMatch;
                if (activeLink) {
                    activeLink.classList.add('active');
                }

                console.log('Current path:', currentPath, 'Active link:', activeLink?.textContent?.trim());
            }

            // Update navigation on page load
            updateActiveNavigation();

            // Listen for back/forward button events
            window.addEventListener('popstate', function() {
                setTimeout(updateActiveNavigation, 100);
            });

            // Handle navigation clicks
            let isNavigating = false;
            const navLinks = document.querySelectorAll('.sidebar-nav .metismenu li a');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    isNavigating = true;

                    // Remove active class from all links immediately
                    navLinks.forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');

                    console.log('Navigation clicked:', this.textContent.trim());

                    // Reset navigation flag after page loads
                    setTimeout(() => {
                        isNavigating = false;
                        updateActiveNavigation();
                    }, 1000);
                });
            });

            // Back button functionality for pages
            // document.addEventListener('click', function(e) {
            //     if (e.target.id === 'backButton' || e.target.closest('#backButton')) {
            //         e.preventDefault();
            //         isNavigating = true;
            //         window.history.back();
            //         // Update navigation after going back
            //         setTimeout(() => {
            //             isNavigating = false;
            //             updateActiveNavigation();
            //         }, 500);
            //     }
            // });

            // Listen for URL changes and update navigation (but not during active navigation)
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
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
            if (sidebarCollapsed === 'true') {
                const sidebar = document.getElementById('main-sidebar');
                const header = document.getElementById('top-header');
                const wrapper = document.getElementById('main-wrapper');

                if (sidebar) sidebar.classList.add('collapsed');
                if (header) header.classList.add('sidebar-collapsed');
                if (wrapper) wrapper.classList.add('sidebar-collapsed');
            }

            console.log('SMS Expert layout loaded successfully');

            // Fix modal backdrop issues - prevent multiple backdrops
            document.addEventListener('hidden.bs.modal', function(event) {
                // Remove any stray backdrops when modal is closed
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length > 1) {
                    // Remove all but keep one if a modal is still open
                    const openModals = document.querySelectorAll('.modal.show');
                    if (openModals.length === 0) {
                        backdrops.forEach(backdrop => backdrop.remove());
                    } else {
                        for (let i = 1; i < backdrops.length; i++) {
                            backdrops[i].remove();
                        }
                    }
                } else if (document.querySelectorAll('.modal.show').length === 0) {
                    // No modals open, remove all backdrops
                    backdrops.forEach(backdrop => backdrop.remove());
                }
                // Remove modal-open class from body if no modals are open
                if (document.querySelectorAll('.modal.show').length === 0) {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            });

            // Ensure modal is properly initialized
            document.addEventListener('show.bs.modal', function(event) {
                // Remove any stray backdrops before showing new modal
                const existingBackdrops = document.querySelectorAll('.modal-backdrop');
                if (existingBackdrops.length > 0 && document.querySelectorAll('.modal.show').length === 0) {
                    existingBackdrops.forEach(backdrop => backdrop.remove());
                }
            });

            // Move all modals to body level to prevent z-index issues
            document.querySelectorAll('.modal').forEach(function(modal) {
                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
            });

            // Also handle dynamically created modals
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 && node.classList && node.classList.contains('modal')) {
                            if (node.parentElement !== document.body) {
                                document.body.appendChild(node);
                            }
                        }
                    });
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });

            // Enhanced smooth scrolling for main content
            const mainWrapper = document.querySelector('.main-wrapper');
            if (mainWrapper) {
                // Add momentum scrolling for iOS
                mainWrapper.style.webkitOverflowScrolling = 'touch';

                // Optimize scroll performance
                let ticking = false;

                function updateScrollPerformance() {
                    if (!ticking) {
                        requestAnimationFrame(() => {
                            // Smooth scroll optimization
                            ticking = false;
                        });
                        ticking = true;
                    }
                }

                mainWrapper.addEventListener('scroll', updateScrollPerformance, {
                    passive: true
                });

                // Add smooth scroll to anchor links
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

    <!-- Back Button Script Customer -->
    <script>
        const backButtonEl = document.getElementById('backButton');
        if (backButtonEl) backButtonEl.addEventListener('click', function() {
            const dashboardUrl = '/dashboard';

            // Restricted URLs (exact match only)
            const restrictedRoutes = [
                '/sms_wallet',
                '/sendsms',
                '/received-sms',
                '/sentsms',
                '/keywords',
                '/numbers',
                '/groups',
                '/profile',
                '/view_invoices',
                '/support',
                '/delivery-receipt',
                '/stop',
                '/blacklist',
                '/contracts',
                '/buysms'
            ];

            try {
                // Normalize current path (remove trailing slash)
                const currentPath = window.location.pathname.replace(/\/$/, '');

                // Exact match check
                if (restrictedRoutes.includes(currentPath)) {
                    window.location.href = dashboardUrl;
                } else {
                    // Non-restricted → normal back if possible, fallback to dashboard
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = dashboardUrl;
                    }
                }
            } catch (err) {
                // On any error → safe fallback
                window.location.href = dashboardUrl;
            }
        });
    </script>




    {{-- <script>
        (function() {
            const currentPath = window.location.pathname;
            const excludedPaths = ["/", "/login", "/logout"];
            const navType = performance.getEntriesByType("navigation")[0]?.type;
            const isBackOrReload = navType === "back_forward" || navType === "reload";

            let navStack = JSON.parse(sessionStorage.getItem("navStack") || "[]");
            let stackIndex = Number(sessionStorage.getItem("navStackIndex") || "0");

            // If empty stack, initialize with current path
            if (navStack.length === 0 && !excludedPaths.includes(currentPath)) {
                navStack.push(currentPath);
                stackIndex = navStack.length - 1;
            }

            // Add current path if not duplicate or excluded
            if (!isBackOrReload && !excludedPaths.includes(currentPath)) {
                const last = navStack[navStack.length - 1];
                if (last !== currentPath) {
                    navStack.push(currentPath);
                    stackIndex = navStack.length - 1;
                }
            }

            // Store stack and index
            sessionStorage.setItem("navStack", JSON.stringify(navStack));
            sessionStorage.setItem("navStackIndex", stackIndex.toString());
        })();

        // Back Button Logic
        document.getElementById("backButton")?.addEventListener("click", function() {
            let navStack = JSON.parse(sessionStorage.getItem("navStack") || "[]");
            let stackIndex = Number(sessionStorage.getItem("navStackIndex") || "0");

            if (stackIndex > 0) {
                stackIndex -= 1;
                sessionStorage.setItem("navStackIndex", stackIndex.toString());
                window.location.href = navStack[stackIndex];
            } else {
                // If index is 0 or empty, fallback to dashboard
                window.location.href = "/admin/dashboard";
            }
        });

        // Navigation Stack Handler
        // (function() {
        //     const currentUrl = window.location.pathname + window.location.search; // include query params
        //     const excludedPaths = ["/login", "/logout"]; // pages to ignore

        //     if (excludedPaths.includes(window.location.pathname)) return;

        //     // Get current nav stack
        //     let navStack = JSON.parse(sessionStorage.getItem("navStack") || "[]");

        //     // Avoid consecutive duplicates
        //     if (navStack.length === 0 || navStack[navStack.length - 1] !== currentUrl) {
        //         navStack.push(currentUrl);
        //     }

        //     // Save back to session
        //     sessionStorage.setItem("navStack", JSON.stringify(navStack));
        // })();

        // // Back Button Logic
        // document.getElementById("backButton")?.addEventListener("click", function() {
        //     let navStack = JSON.parse(sessionStorage.getItem("navStack") || "[]");

        //     if (navStack.length > 1) {
        //         // Remove current page
        //         navStack.pop();
        //         const previousPage = navStack[navStack.length - 1];
        //         sessionStorage.setItem("navStack", JSON.stringify(navStack));
        //         window.location.href = previousPage;
        //     } else {
        //         // Fallback to dashboard if no previous pages
        //         window.location.href = "/admin/dashboard";
        //     }
        // });
    </script> --}}

    @stack('js')

    {{-- Tour Guide --}}
    @include('partials.tour.customer-tour')
</body>

</html>
