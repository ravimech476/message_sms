@extends('admin.layouts.app')

@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        /* Change breadcrumb separator to "/" */
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
            /* optional grey */
        }

        /* Settings tabs - keep in single row with scroll */
        .nav-tabs.nav-success {
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .nav-tabs.nav-success::-webkit-scrollbar {
            height: 4px;
        }

        .nav-tabs.nav-success::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .nav-tabs.nav-success::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }

        .nav-tabs.nav-success .nav-item {
            flex-shrink: 0;
        }

        .nav-tabs.nav-success .nav-link {
            white-space: nowrap;
        }
    </style>
@endpush

@php
    // Get admin user from session
    $adminUser = Session::get('admin_user');
    $isSuperAdmin = isset($adminUser['role']) && $adminUser['role'] === 'super_admin';
    
    // Get permissions from session or database
    $permissions = $adminUser['permissions'] ?? [];
    
    // Check each permission - Super admin has all permissions
    $canViewSystemLogs = $isSuperAdmin || ($permissions['can_view_system_logs'] ?? false);
    $canViewUserActivity = $isSuperAdmin || ($permissions['can_view_user_activity'] ?? false);
    // System Logs tab re-enabled (permission-gated) so admins can browse/download
    // the date->folder->file log tree. User Activity stays hidden for now.
    $canViewUserActivity = false;
    $canViewEnvVariables = $isSuperAdmin || ($permissions['can_view_env_variables'] ?? false);
    // Hide the "General" (environment variables) tab and its queries on page load.
    $canViewEnvVariables = false;
    $canManageCache = $isSuperAdmin || ($permissions['can_manage_cache'] ?? false);
    $canViewProcessMonitor = $isSuperAdmin || ($permissions['can_view_process_monitor'] ?? false);
    $canViewQueues = $isSuperAdmin || ($permissions['can_view_queues'] ?? false);
    $canManageCustomerSettings = $isSuperAdmin || ($permissions['can_manage_customer_settings'] ?? false);
    $canViewSystemStatus = $isSuperAdmin || ($permissions['can_view_system_status'] ?? false);
    // Hide the "System Status & SMS Traffic" tab and its real-time monitoring queries.
    $canViewSystemStatus = false;
    $canManageServerSettings = $isSuperAdmin || ($permissions['can_manage_server_settings'] ?? false);
    // Raw read-only SQL console — super-admin only (or explicit can_run_queries permission).
    $canRunQueries = $isSuperAdmin || ($permissions['can_run_queries'] ?? false);
    // DLR Status Update — upload a Vonage/Nexmo DLR CSV export → queued to RabbitMQ for processing.
    // Auto-granted to every admin (migration default 1); non-admin users don't have it.
    $canManageDlrUpdate = $isSuperAdmin || ($permissions['can_manage_dlr_update'] ?? false);

    // Determine default tab based on permissions
    $requestedTab = request()->get('tab', '');
    $defaultTab = '';
    
    if ($canViewSystemStatus) {
        $defaultTab = 'system-status';
    } elseif ($canViewSystemLogs) {
        $defaultTab = 'logs';
    } elseif ($canViewUserActivity) {
        $defaultTab = 'activity';
    } elseif ($canViewEnvVariables) {
        $defaultTab = 'general';
    } elseif ($canManageCache) {
        $defaultTab = 'cache';
    } elseif ($canViewProcessMonitor) {
        $defaultTab = 'monitor';
    } elseif ($canManageCustomerSettings) {
        $defaultTab = 'customer-settings';
    } elseif ($canManageServerSettings) {
        $defaultTab = 'server-settings';
    } elseif ($canRunQueries) {
        $defaultTab = 'query';
    } elseif ($canManageDlrUpdate) {
        $defaultTab = 'dlr-update';
    }
    
    // If no tab requested, use default
    if (empty($requestedTab)) {
        $requestedTab = $defaultTab;
    }
    
    // Validate requested tab against permissions
    $tabPermissions = [
        'system-status' => $canViewSystemStatus,
        'logs' => $canViewSystemLogs,
        'activity' => $canViewUserActivity,
        'general' => $canViewEnvVariables,
        'cache' => $canManageCache,
        'monitor' => $canViewProcessMonitor,
        'queues' => $canViewQueues,
        'customer-settings' => $canManageCustomerSettings,
        'server-settings' => $canManageServerSettings,
        'query' => $canRunQueries,
        'dlr-update' => $canManageDlrUpdate,
    ];
    
    // If user doesn't have permission for requested tab, use default
    if (!($tabPermissions[$requestedTab] ?? false)) {
        $requestedTab = $defaultTab;
    }
@endphp

@section('content')
    <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Settings</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Settings</li>
                        </ol>
                    </nav>
                </div>
                <!-- Back Button -->
                <div class="me-2 back-button-container"
                    style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                    <button id="backButton" class="btn btn-primary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back
                    </button>
                </div>
            </div>
            <!--end breadcrumb-->

        @if($defaultTab)
        <div class="card">
            <div class="card-body">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs nav-success" role="tablist">
                    @if($canViewSystemStatus)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'system-status' ? 'active' : '' }}" 
                           href="{{ route('admin.settings', ['tab' => 'system-status']) }}" 
                           role="tab" 
                           aria-selected="{{ $requestedTab == 'system-status' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">analytics</i></div>
                                <div class="tab-title">System Status</div>
                            </div>
                        </a>
                    </li>
                    @endif

                    @if($canViewSystemLogs)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'logs' ? 'active' : '' }}" 
                           href="{{ route('admin.settings', ['tab' => 'logs']) }}" 
                           role="tab" 
                           aria-selected="{{ $requestedTab == 'logs' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">article</i></div>
                                <div class="tab-title">System Logs</div>
                            </div>
                        </a>
                    </li>
                    @endif
                    
                    @if($canViewUserActivity)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'activity' ? 'active' : '' }}" 
                           href="{{ route('admin.settings', ['tab' => 'activity']) }}" 
                           role="tab" 
                           aria-selected="{{ $requestedTab == 'activity' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">history</i></div>
                                <div class="tab-title">User Activity</div>
                            </div>
                        </a>
                    </li>
                    @endif
                    
                    @if($canViewEnvVariables)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'general' ? 'active' : '' }}" 
                           href="{{ route('admin.settings', ['tab' => 'general']) }}" 
                           role="tab" 
                           aria-selected="{{ $requestedTab == 'general' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">tune</i></div>
                                <div class="tab-title">Environment Variables</div>
                            </div>
                        </a>
                    </li>
                    @endif
                    
                    @if($canManageCache)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'cache' ? 'active' : '' }}" 
                           href="{{ route('admin.settings', ['tab' => 'cache']) }}" 
                           role="tab" 
                           aria-selected="{{ $requestedTab == 'cache' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">cached</i></div>
                                <div class="tab-title">Cache Management</div>
                            </div>
                        </a>
                    </li>
                    @endif
                    
                    @if($canViewProcessMonitor)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'monitor' ? 'active' : '' }}" 
                           href="{{ route('admin.settings', ['tab' => 'monitor']) }}" 
                           role="tab" 
                           aria-selected="{{ $requestedTab == 'monitor' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">monitor_heart</i></div>
                                <div class="tab-title">Process Monitor</div>
                            </div>
                        </a>
                    </li>
                    @endif

                    @if($canViewQueues)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'queues' ? 'active' : '' }}"
                           href="{{ route('admin.settings', ['tab' => 'queues']) }}"
                           role="tab"
                           aria-selected="{{ $requestedTab == 'queues' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">stacked_bar_chart</i></div>
                                <div class="tab-title">Queues</div>
                            </div>
                        </a>
                    </li>
                    @endif

                    @if($canManageCustomerSettings)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'customer-settings' ? 'active' : '' }}"
                           href="{{ route('admin.settings', ['tab' => 'customer-settings']) }}"
                           role="tab"
                           aria-selected="{{ $requestedTab == 'customer-settings' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">people_alt</i></div>
                                <div class="tab-title">Customer Settings</div>
                            </div>
                        </a>
                    </li>
                    @endif

                    @if($canManageServerSettings)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'server-settings' ? 'active' : '' }}"
                           href="{{ route('admin.settings', ['tab' => 'server-settings']) }}"
                           role="tab"
                           aria-selected="{{ $requestedTab == 'server-settings' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">dns</i></div>
                                <div class="tab-title">Server Settings</div>
                            </div>
                        </a>
                    </li>
                    @endif

                    @if($canRunQueries)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'query' ? 'active' : '' }}"
                           href="{{ route('admin.settings', ['tab' => 'query']) }}"
                           role="tab"
                           aria-selected="{{ $requestedTab == 'query' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">terminal</i></div>
                                <div class="tab-title">Query</div>
                            </div>
                        </a>
                    </li>
                    @endif

                    @if($canManageDlrUpdate)
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $requestedTab == 'dlr-update' ? 'active' : '' }}"
                           href="{{ route('admin.settings', ['tab' => 'dlr-update']) }}"
                           role="tab"
                           aria-selected="{{ $requestedTab == 'dlr-update' ? 'true' : 'false' }}">
                            <div class="d-flex align-items-center">
                                <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">cloud_upload</i></div>
                                <div class="tab-title">DLR Status Update</div>
                            </div>
                        </a>
                    </li>
                    @endif
                </ul>

                <!-- Tab content -->
                <div class="tab-content py-3">
                    @if($canViewSystemStatus)
                    <!-- System Status Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'system-status' ? 'show active' : '' }}" id="system-status" role="tabpanel">
                        @include('admin.settings.partials.system_status')
                    </div>
                    @endif

                    @if($canViewSystemLogs)
                    <!-- System Logs Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'logs' ? 'show active' : '' }}" id="logs" role="tabpanel">
                        @include('admin.settings.partials.logs')
                    </div>
                    @endif
                    
                    @if($canViewUserActivity)
                    <!-- User Activity Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'activity' ? 'show active' : '' }}" id="activity" role="tabpanel">
                        @include('admin.settings.partials.activity_logs')
                    </div>
                    @endif
                    
                    @if($canViewEnvVariables)
                    <!-- General Settings Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'general' ? 'show active' : '' }}" id="general" role="tabpanel">
                        @include('admin.settings.partials.general')
                    </div>
                    @endif

                    @if($canManageCache)
                    <!-- Cache Management Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'cache' ? 'show active' : '' }}" id="cache" role="tabpanel">
                        @include('admin.settings.partials.cache')
                    </div>
                    @endif

                    @if($canViewProcessMonitor)
                    <!-- Process Monitor Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'monitor' ? 'show active' : '' }}" id="monitor" role="tabpanel">
                        @include('admin.settings.partials.monitor')
                    </div>
                    @endif

                    @if($canViewQueues)
                    <!-- Queues Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'queues' ? 'show active' : '' }}" id="queues" role="tabpanel">
                        @include('admin.settings.partials.queues')
                    </div>
                    @endif

                    @if($canManageCustomerSettings)
                    <!-- Customer Settings Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'customer-settings' ? 'show active' : '' }}" id="customer-settings" role="tabpanel">
                        @include('admin.settings.partials.customer_settings')
                    </div>
                    @endif

                    @if($canManageServerSettings)
                    <!-- Server Settings Tab -->
                    <div class="tab-pane fade {{ $requestedTab == 'server-settings' ? 'show active' : '' }}" id="server-settings" role="tabpanel">
                        @include('admin.settings.partials.server_settings')
                    </div>
                    @endif

                    @if($canRunQueries)
                    <!-- Query Tab (read-only SQL console) -->
                    <div class="tab-pane fade {{ $requestedTab == 'query' ? 'show active' : '' }}" id="query" role="tabpanel">
                        @include('admin.settings.partials.query')
                    </div>
                    @endif

                    @if($canManageDlrUpdate)
                    <!-- DLR Status Update Tab (upload Vonage/Nexmo DLR CSV → RabbitMQ) -->
                    <div class="tab-pane fade {{ $requestedTab == 'dlr-update' ? 'show active' : '' }}" id="dlr-update" role="tabpanel">
                        @include('admin.settings.partials.dlr_update')
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    <i class="material-icons-outlined me-2">warning</i>
                    You don't have permission to access any settings tabs. Please contact your administrator.
                </div>
            </div>
        </div>
        @endif
        </div>
    </main>
@endsection
