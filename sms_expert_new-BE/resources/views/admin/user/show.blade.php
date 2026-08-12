@extends('admin.layouts.app')
@section('title')
    {{ __('CRM') }}
@endsection

@push('style')
    <style>
        .nav-pills-danger .nav-link.active {
            background-color: #6f42c1 !important;
            color: white !important;
        }
        div.dataTables_wrapper div.dataTables_filter {
            text-align: left !important;
        }
        .btn-smm {
            min-width: 126px;
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .credentials {
            display: inline-block;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
         /* Change breadcrumb separator to "/" */
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
            /* optional grey */
        }
    </style>
@endpush

<?php
$isTracked = $record->trackwallet === 'y';
$trackwalletText = $isTracked ? 'Yes' : 'No';
$actionText = $isTracked ? 'Turn Off' : 'Turn On';
$setValue = $isTracked ? 'n' : 'y';

// Get current admin user for permission check
$adminUser = null;
$adminSession = Session::get('admin_user');
if ($adminSession) {
    $adminUser = \App\Models\User::with('adminPermissions')->find($adminSession['id']);
}

// Helper function to check permission
$canAccess = function($permission) use ($adminUser) {
    // If no admin user found, deny access
    if (!$adminUser) return false;
    
    // Use the model's hasPermission method
    return $adminUser->hasPermission($permission);
};

// Determine first accessible tab
$firstAccessibleTab = 'customer-profile';
$tabOrder = [
    'customer-profile' => 'can_view_customer_profile',
    'customer-rate' => 'can_view_customer_rate',
    'customer-keywords' => 'can_view_customer_keywords',
    'customer-virtual-number' => 'can_view_customer_virtual_numbers',
    'customer-client-note' => 'can_view_customer_notes',
    'customer-wallet-balance' => 'can_view_customer_wallet',
    'customer-current-log' => 'can_view_customer_logs',
    'customer-report-log' => 'can_view_customer_reports',
    'customer-current-flag' => 'can_view_customer_flag',
];

foreach ($tabOrder as $tab => $permission) {
    if ($canAccess($permission)) {
        $firstAccessibleTab = $tab;
        break;
    }
}

// Check if session active tab is accessible, otherwise use first accessible
$activeTab = session('activeTab', $firstAccessibleTab);
if (isset($tabOrder[$activeTab]) && !$canAccess($tabOrder[$activeTab])) {
    $activeTab = $firstAccessibleTab;
}
?>

@section('content')
   <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            {{-- Breadcrumb --}}
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">{{ urldecode($record->busname ?? 'View Customer') }}</div>
                <div class="ps-3">
                     <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Customers</li>
                            <li class="breadcrumb-item active" aria-current="page">View Customer</li>
                        </ol>
                    </nav>
                </div>
                <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                    <button id="backButton" class="btn btn-primary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back
                    </button>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if (session('success'))
                <div id="flash-message" class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div id="flash-error-message" class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-12 col-xl-12 col-xxl-3 d-flex">
                    <div class="col">
                        <div class="card">
                            <div class="card-body">
                                {{-- Tab Navigation --}}
                                <ul class="nav nav-tabs nav-success" role="tablist">
                                    @if($canAccess('can_view_customer_profile'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-profile' ? 'active' : '' }}"
                                            data-bs-toggle="tab" href="#customer-profile" role="tab" aria-selected="{{ $activeTab == 'customer-profile' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-person me-1 fs-6"></i></div>
                                                <div class="tab-title">Profile</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_rate'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-rate' ? 'active' : '' }}"
                                            data-bs-toggle="tab" href="#customer-rate" role="tab" aria-selected="{{ $activeTab == 'customer-rate' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-cash-stack me-1 fs-6"></i></div>
                                                <div class="tab-title">User SMS Rates</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_keywords'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-keywords' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-keywords" role="tab" aria-selected="{{ $activeTab == 'customer-keywords' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-grid me-1 fs-6"></i></div>
                                                <div class="tab-title">Keywords</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_virtual_numbers'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-virtual-number' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-virtual-number" role="tab" aria-selected="{{ $activeTab == 'customer-virtual-number' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-list me-1 fs-6"></i></div>
                                                <div class="tab-title">Virtual Numbers</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_notes'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-client-note' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-client-note" role="tab" aria-selected="{{ $activeTab == 'customer-client-note' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-pencil me-1 fs-6"></i></div>
                                                <div class="tab-title">Client Note</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_wallet'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-wallet-balance' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-wallet-balance" role="tab" aria-selected="{{ $activeTab == 'customer-wallet-balance' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-wallet me-1 fs-6"></i></div>
                                                <div class="tab-title">Wallet Balance</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_logs'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-current-log' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-current-log" role="tab" aria-selected="{{ $activeTab == 'customer-current-log' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-clock-history me-1 fs-6"></i></div>
                                                <div class="tab-title">Current Log</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    @if($canAccess('can_view_customer_flag'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-current-flag' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-current-flag" role="tab" aria-selected="{{ $activeTab == 'customer-current-flag' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                    <i class="bi bi-gear me-1 fs-6"></i>
                                                <div class="tab-title">Migration Flag</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif

                                    {{-- @if($canAccess('can_view_customer_reports'))
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link {{ $activeTab == 'customer-report-log' ? 'active' : '' }}"
                                            data-bs-toggle="pill" href="#customer-report-log" role="tab" aria-selected="{{ $activeTab == 'customer-report-log' ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-center">
                                                <div class="tab-icon"><i class="bi bi-file-earmark-text me-1 fs-6"></i></div>
                                                <div class="tab-title">Reports</div>
                                            </div>
                                        </a>
                                    </li>
                                    @endif --}}
                                </ul>

                                <div class="tab-content" id="danger-pills-tabContent">
                                    {{-- User Actions Card --}}
                                    @include('admin.user.partials.user-actions')

                                    {{-- Tab Panes - Only include if user has permission --}}
                                    @if($canAccess('can_view_customer_profile'))
                                        @include('admin.user.partials.tabs.profile-tab', ['isActive' => $activeTab == 'customer-profile', 'canEdit' => $canAccess('can_edit_customer_profile')])
                                    @endif

                                    @if($canAccess('can_view_customer_rate'))
                                        @include('admin.user.partials.tabs.rate-tab', ['isActive' => $activeTab == 'customer-rate'])
                                    @endif

                                    @if($canAccess('can_view_customer_keywords'))
                                        @include('admin.user.partials.tabs.keywords-tab', ['isActive' => $activeTab == 'customer-keywords', 'canEdit' => $canAccess('can_edit_customer_keywords')])
                                    @endif

                                    @if($canAccess('can_view_customer_virtual_numbers'))
                                        @include('admin.user.partials.tabs.virtual-numbers-tab', ['isActive' => $activeTab == 'customer-virtual-number', 'canEdit' => $canAccess('can_edit_customer_virtual_numbers')])
                                    @endif

                                    @if($canAccess('can_view_customer_notes'))
                                        @include('admin.user.partials.tabs.client-notes-tab', ['isActive' => $activeTab == 'customer-client-note', 'canEdit' => $canAccess('can_edit_customer_notes')])
                                    @endif

                                    @if($canAccess('can_view_customer_wallet'))
                                        @include('admin.user.partials.tabs.wallet-balance-tab', ['isActive' => $activeTab == 'customer-wallet-balance', 'canEdit' => $canAccess('can_edit_customer_wallet')])
                                    @endif

                                    @if($canAccess('can_view_customer_logs'))
                                        @include('admin.user.partials.tabs.current-log-tab', ['isActive' => $activeTab == 'customer-current-log'])
                                    @endif

                                    @if($canAccess('can_view_customer_flag'))
                                        @include('admin.user.partials.tabs.current-flag-tab', ['isActive' => $activeTab == 'customer-current-flag'])
                                    @endif

                                    {{-- @if($canAccess('can_view_customer_reports'))
                                        @include('admin.user.partials.tabs.reports-tab', ['isActive' => $activeTab == 'customer-report-log'])
                                    @endif --}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('admin.layouts.footer')
@endsection

@push('js')
    <script src="{{ asset('assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/plugins/metismenu/metisMenu.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    @include('admin.user.partials.scripts')
@endpush
