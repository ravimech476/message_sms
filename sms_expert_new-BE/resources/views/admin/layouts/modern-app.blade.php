<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SMS Expert Admin')</title>
    
    <!--favicon-->
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">

    <!--plugins-->
    <link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/mm-vertical.css') }}">
    <link href="{{ asset('assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    
    <!--bootstrap css-->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    
    <!--fonts-->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!--main css-->
    <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/semi-dark.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/bordered-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/responsive.css') }}" rel="stylesheet">

    <!-- Modern Admin Styles -->
    <style>
        :root {
            --primary-color: #ea6118;
            --primary-light: #ff8a50;
            --primary-dark: #d1520e;
            --secondary-color: #293B50;
            --secondary-light: #3a4d66;
            --accent-color: #1e2a3a;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --border-light: #f3f4f6;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-primary);
        }

        /* Modern Header */
        .top-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-light);
            padding: 0.75rem 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .top-header .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        /* Modern Sidebar */
        .sidebar-wrapper {
            width: 280px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-right: 1px solid var(--border-light);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-wrapper.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            text-align: center;
        }

        .sidebar-logo {
            max-width: 180px;
            height: auto;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(234, 97, 24, 0.1);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .nav-text {
            flex: 1;
            white-space: nowrap;
        }

        .nav-badge {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Dropdown Menu */
        .nav-dropdown {
            background: transparent;
        }

        .nav-dropdown-toggle::after {
            margin-left: auto;
            transition: transform 0.2s ease;
        }

        .nav-dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(90deg);
        }

        .nav-dropdown-menu {
            background: var(--light-bg);
            border-radius: var(--radius-md);
            margin: 0.5rem 0;
            padding: 0.5rem;
            border: 1px solid var(--border-light);
        }

        .nav-dropdown-item {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            margin: 0.25rem 0;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-wrapper.expanded {
            margin-left: 70px;
        }

        .main-content {
            padding: 2rem;
        }

        /* Modern Cards */
        .modern-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header-modern {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .card-title-modern {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-subtitle-modern {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient);
        }

        .stat-card.primary { --gradient: linear-gradient(90deg, var(--primary-color), var(--primary-light)); }
        .stat-card.success { --gradient: linear-gradient(90deg, var(--success-color), #34d399); }
        .stat-card.warning { --gradient: linear-gradient(90deg, var(--warning-color), #fbbf24); }
        .stat-card.info { --gradient: linear-gradient(90deg, var(--info-color), #60a5fa); }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--icon-bg);
            color: var(--icon-color);
            font-size: 1.5rem;
        }

        .stat-card.primary .stat-icon { 
            --icon-bg: rgba(234, 97, 24, 0.1); 
            --icon-color: var(--primary-color); 
        }
        .stat-card.success .stat-icon { 
            --icon-bg: rgba(16, 185, 129, 0.1); 
            --icon-color: var(--success-color); 
        }
        .stat-card.warning .stat-icon { 
            --icon-bg: rgba(245, 158, 11, 0.1); 
            --icon-color: var(--warning-color); 
        }
        .stat-card.info .stat-icon { 
            --icon-bg: rgba(59, 130, 246, 0.1); 
            --icon-color: var(--info-color); 
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: var(--trend-bg);
            color: var(--trend-color);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .trend-up { 
            --trend-bg: rgba(16, 185, 129, 0.1); 
            --trend-color: var(--success-color); 
        }
        .trend-down { 
            --trend-bg: rgba(239, 68, 68, 0.1); 
            --trend-color: var(--danger-color); 
        }

        /* Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Notifications */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* Search Bar */
        .search-modern {
            position: relative;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
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

        /* Dark Theme Overrides */
        [data-bs-theme="dark"] {
            --card-bg: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-muted: #9ca3af;
            --border-color: #374151;
            --border-light: #374151;
            --light-bg: #111827;
        }

        [data-bs-theme="dark"] .sidebar-wrapper {
            background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
        }

        [data-bs-theme="dark"] .top-header {
            background: rgba(31, 41, 55, 0.95);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar-wrapper {
                transform: translateX(-100%);
            }
            
            .sidebar-wrapper.mobile-open {
                transform: translateX(0);
            }
            
            .main-wrapper {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-value {
                font-size: 2rem;
            }
        }

        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: var(--radius-md);
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in-right {
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
    </style>
    @stack('styles')
</head>

<body>
    <!--start header-->
    @include('admin.layouts.modern-header')
    <!--end header-->

    <!--start sidebar-->
    @include('admin.layouts.modern-sidebar')
    <!--end sidebar-->

    <!--start main wrapper-->
    <main class="main-wrapper" id="mainWrapper">
        <div class="main-content">
            @yield('content')
        </div>
    </main>
    <!--end main wrapper-->

    <!--bootstrap js-->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    
    <!--plugins-->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Modern Admin Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainWrapper = document.getElementById('mainWrapper');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainWrapper.classList.toggle('expanded');
                    
                    // Save state
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }
            
            // Restore sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainWrapper.classList.add('expanded');
            }
            
            // Mobile sidebar toggle
            const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
            if (mobileSidebarToggle) {
                mobileSidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });
            }
            
            // Close mobile sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    if (!sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('mobile-open');
                    }
                }
            });
            
            // Theme switcher
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    
                    // Update icon
                    const icon = themeToggle.querySelector('i');
                    icon.textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
                });
            }
            
            // Restore theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            
            // Animate stats on load
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(element => {
                const finalValue = element.textContent.replace(/[^\d.-]/g, '');
                if (finalValue && !isNaN(finalValue)) {
                    animateCounter(element, finalValue);
                }
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
        
        // Counter animation function
        function animateCounter(element, finalValue) {
            let currentValue = 0;
            const increment = Math.ceil(finalValue / 100);
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= finalValue) {
                    currentValue = finalValue;
                    clearInterval(timer);
                }
                
                const originalText = element.textContent;
                if (originalText.includes('£')) {
                    element.textContent = '£' + new Intl.NumberFormat().format(currentValue);
                } else if (originalText.includes('%')) {
                    element.textContent = currentValue + '%';
                } else {
                    element.textContent = new Intl.NumberFormat().format(currentValue);
                }
            }, 20);
        }
        
        // Global notification function
        function showNotification(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: var(--shadow-lg);
                border: none;
                border-radius: var(--radius-md);
            `;
            
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, duration);
        }
    </script>
    @stack('scripts')
</body>
</html>