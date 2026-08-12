<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SMS Expert Dashboard' }}</title>
    <!--favicon-->
    <link rel="icon" href="{{ asset('assets/images/auth/smsexpert_favion.png') }}" type="image/png">

    <!--plugins-->
    <link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/metisMenu.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/plugins/metismenu/mm-vertical.css') }}">
    <!--bootstrap css-->
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/plugins/datatable/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons+Outlined" rel="stylesheet">
    <!--main css-->
    <link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/main.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/semi-dark.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/bordered-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sass/responsive.css') }}" rel="stylesheet">
    
    <!-- Modern UI Styles -->
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #4c63d2;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --sidebar-width: 280px;
            --header-height: 70px;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --box-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background: #f8fafc;
        }

        /* Modern Header */
        .top-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            height: var(--header-height);
            box-shadow: var(--box-shadow);
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 1000;
        }

        .top-header .navbar {
            padding: 0 2rem;
        }

        /* Fixed Expanded Sidebar */
        .main-sidebar {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            border-right: 1px solid var(--border-color);
            box-shadow: var(--box-shadow);
            z-index: 1001;
            overflow-y: auto;
        }

        /* Sidebar Header */
        .sidebar-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header img {
            background-color: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 10px 12px;
        }

        /* User Profile Section */
        .sidebar-user {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            text-align: center;
        }

        .sidebar-user img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .sidebar-user h6 {
            margin: 0;
            color: var(--text-primary);
            font-weight: 600;
        }

        .sidebar-user small {
            color: var(--text-secondary);
        }

        /* Navigation Menu */
        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav .metismenu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav .metismenu li {
            margin: 0.25rem 1rem;
        }

        .sidebar-nav .metismenu li a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            border: 1px solid transparent;
            font-weight: 500;
        }

        .sidebar-nav .metismenu li a:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            transform: translateX(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-nav .metismenu li a.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: var(--box-shadow);
        }

        .sidebar-nav .metismenu li a .parent-icon {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            transition: all 0.3s ease;
        }

        .sidebar-nav .metismenu li a:hover .parent-icon,
        .sidebar-nav .metismenu li a.active .parent-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-nav .metismenu li a .menu-title {
            font-size: 0.9rem;
        }

        /* Main Content Area */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            /* padding-top: var(--header-height); */
            background: #f8fafc;
        }

        /* Modern Cards */
        .modern-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .modern-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--box-shadow-lg);
        }

        .gradient-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .gradient-card-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .gradient-card-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .gradient-card-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Modern Buttons */
        .btn-modern {
            border-radius: var(--border-radius);
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.875rem;
        }

        .btn-modern-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-modern-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-lg);
            color: white;
        }

        .btn-outline-modern {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline-modern:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Modern Wallet Display */
        .wallet-display {
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        /* Dashboard Stats Cards */
        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--box-shadow-lg);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-up {
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }
            
            .main-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
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

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

        /* Dark theme adjustments */
        [data-bs-theme="dark"] {
            --light-color: #1e293b;
            --border-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }

        [data-bs-theme="dark"] .main-sidebar {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border-right-color: #334155;
        }

        [data-bs-theme="dark"] .modern-card {
            background: #1e293b;
            border-color: #334155;
        }

        [data-bs-theme="dark"] .stats-card {
            background: #1e293b;
            border-color: #334155;
        }

        /* Additional modern styling */
        .selected-label {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
            color: #ffffff !important;
            border-radius: var(--border-radius) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }

        .btn-buy-online {
            font-size: 14px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }

        .btn-buy-online:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .btn-buy-online {
                font-size: 10px;
            }
        }
    </style>
    @stack('style')
</head>

<body>
    <!--start fixed sidebar-->
    <aside class="main-sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
          
        </div>
        
        <!-- User Profile -->
        @php
            $userInfo = Session::get('user_info');
            $user_contactname = urldecode($userInfo['contactname'] ?? 'User');
        @endphp
        
        <div class="sidebar-user">
            <img src="{{ asset('assets/images/auth/smsexpertlogotwittersquareblueback.png') }}" alt="User Avatar">
            <h6>{{ ucfirst($user_contactname) }}</h6>
            <small>SMS Expert User</small>
        </div>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
            <ul class="metismenu" id="sidenav">
                <li><a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">home</i></div>
                    <div class="menu-title">Dashboard</div>
                </a></li>
                <li><a href="{{ route('sms_wallet.index') }}" class="{{ request()->routeIs('sms_wallet.*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">account_balance_wallet</i></div>
                    <div class="menu-title">SMS Wallet</div>
                </a></li>
                <li><a href="{{ route('sendsms') }}" class="{{ request()->routeIs('sendsms') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">send</i></div>
                    <div class="menu-title">Send New SMS</div>
                </a></li>
                <li><a href="{{ route('received-sms') }}" class="{{ request()->routeIs('received-sms') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">cloud_download</i></div>
                    <div class="menu-title">Received SMS</div>
                </a></li>
                <li><a href="{{ route('sentsms') }}" class="{{ request()->routeIs('sentsms') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">forum</i></div>
                    <div class="menu-title">Sent SMS</div>
                </a></li>
                <li><a href="{{ route('keywords') }}" class="{{ request()->routeIs('keywords') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">apps</i></div>
                    <div class="menu-title">Keywords</div>
                </a></li>
                <li><a href="{{ route('numbers.index') }}" class="{{ request()->routeIs('numbers.*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">format_list_bulleted</i></div>
                    <div class="menu-title">Numbers</div>
                </a></li>
                <li><a href="{{ route('groups.index') }}" class="{{ request()->routeIs('groups.*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">group</i></div>
                    <div class="menu-title">Groups</div>
                </a></li>
                <li><a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">account_circle</i></div>
                    <div class="menu-title">Client Profile</div>
                </a></li>
                <li><a href="{{ route('contract') }}" class="{{ request()->routeIs('contract') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">border_color</i></div>
                    <div class="menu-title">Contract</div>
                </a></li>
                <li><a href="{{ route('view.invoices') }}" class="{{ request()->routeIs('view.invoices') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">description</i></div>
                    <div class="menu-title">Invoices</div>
                </a></li>
                <li><a href="{{ route('technical.support') }}" class="{{ request()->routeIs('technical.*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">brightness_7</i></div>
                    <div class="menu-title">Technical Docs</div>
                </a></li>
                <li><a href="{{ route('delivery-receipt') }}" class="{{ request()->routeIs('delivery-receipt') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">import_contacts</i></div>
                    <div class="menu-title">Delivery Receipt</div>
                </a></li>
                <li><a href="{{ route('stopcommand.index') }}" class="{{ request()->routeIs('stopcommand.*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">support</i></div>
                    <div class="menu-title">STOPs/Optouts</div>
                </a></li>
                <li><a href="{{ route('blacklist.index') }}" class="{{ request()->routeIs('blacklist.*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="material-icons-outlined">block</i></div>
                    <div class="menu-title">Blacklist</div>
                </a></li>
            </ul>
        </nav>
    </aside>
    <!--end fixed sidebar-->

    <!--start header-->
    <header class="top-header">
        <nav class="navbar navbar-expand align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i class="material-icons-outlined">menu</i>
                </button>
                {{-- <h5 class="mb-0 text-white fw-bold">SMS Expert Dashboard</h5> --}}
            </div>
            
            <ul class="navbar-nav gap-1 nav-right-links align-items-center">
                @php
                    $userInfo = Session::get('user_info');
                    if (isset($userInfo['bigid'])) {
                        $bigid = $userInfo['bigid'];
                        $user = \App\Models\User::where('bigid', $bigid)->first();
                        if ($user) {
                            $smsgWallet = $user->smsg_wallet;
                            $smsgServer1Sent = $user->smsg_server1_sent;
                            $smsgServer2Sent = $user->smsg_server2_sent;
                            $remainingWallet = $smsgWallet - $smsgServer1Sent - $smsgServer2Sent;
                        }
                    }
                @endphp
                
                <li class="nav-item dropdown d-flex justify-content-start align-items-center" id="wallet-item">
                    <div class="wallet-display me-2">
                        <span class="fw-bold">£{{ sprintf('%.2f', $remainingWallet ?? 0) }}</span>
                    </div>
                    <a href="{{ route('buysms') }}" class="btn btn-buy-online">Buy Online</a>
                </li>

                <li class="nav-item dropdown dropdown-language">
                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret text-white" href="javascript:;" data-bs-toggle="dropdown">
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

                <li class="nav-item dropdown">
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" 
                       class="nav-link text-white">
                        <i class="material-icons-outlined">power_settings_new</i>
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
            </ul>
        </nav>
    </header>
    <!--end header-->

    <!--start main content-->
    <main class="main-wrapper">
        {{ $slot }}
    </main>
    <!--end main content-->

    <!--bootstrap js-->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.main-sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.main-sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Theme management
            const themeOptions = document.querySelectorAll('input[name="theme-options"]');
            const htmlElement = document.documentElement;
            const defaultTheme = 'LightTheme';

            function updateTheme(themeId) {
                themeOptions.forEach(function(radio) {
                    const label = document.querySelector(`label[for="${radio.id}"]`);
                    label.classList.remove('selected-label');
                });

                const selectedLabel = document.querySelector(`label[for="${themeId}"]`);
                if (selectedLabel) {
                    selectedLabel.classList.add('selected-label');
                }

                switch (themeId) {
                    case 'LightTheme':
                        htmlElement.setAttribute('data-bs-theme', 'light');
                        break;
                    case 'DarkTheme':
                        htmlElement.setAttribute('data-bs-theme', 'dark');
                        break;
                    case 'SemiDarkTheme':
                        htmlElement.setAttribute('data-bs-theme', 'semi-dark');
                        break;
                    case 'BoderedTheme':
                        htmlElement.setAttribute('data-bs-theme', 'bordered-theme');
                        break;
                    default:
                        htmlElement.setAttribute('data-bs-theme', 'light');
                }
            }

            // Load saved theme
            const savedTheme = localStorage.getItem('selectedTheme');
            if (savedTheme) {
                const radioToSelect = document.getElementById(savedTheme);
                if (radioToSelect) {
                    radioToSelect.checked = true;
                    updateTheme(savedTheme);
                }
            } else {
                const defaultRadio = document.getElementById(defaultTheme);
                if (defaultRadio) {
                    defaultRadio.checked = true;
                    updateTheme(defaultTheme);
                }
            }

            // Theme change listeners
            themeOptions.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    updateTheme(radio.id);
                    localStorage.setItem('selectedTheme', radio.id);
                });
            });

            // Add fade-in animation to main content
            const mainWrapper = document.querySelector('.main-wrapper');
            if (mainWrapper) {
                mainWrapper.classList.add('fade-in');
            }
        });
    </script>

    @stack('js')
</body>
</html>