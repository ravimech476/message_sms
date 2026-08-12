@extends('admin.layouts.app')
@section('title')
    {{ __('Reports') }}
@endsection

@php
    // Get admin user from session
    $adminUser = Session::get('admin_user');
    $isSuperAdmin = isset($adminUser['role']) && $adminUser['role'] === 'super_admin';

    // Get permissions from session
    $permissions = $adminUser['permissions'] ?? [];

    // Check each permission - Super admin has all permissions
    $canViewPostpayReport = $isSuperAdmin || ($permissions['can_view_postpay_report'] ?? false);
    $canViewDailySmsReport = $isSuperAdmin || ($permissions['can_view_daily_sms_report'] ?? false);
    $canViewMoneyTransferReport = $isSuperAdmin || ($permissions['can_view_money_transfer_report'] ?? false);
    $canViewMonthlySalesReport = $isSuperAdmin || ($permissions['can_view_monthly_sales_report'] ?? false);

    // Determine default tab based on permissions
    $requestedTab = $activeTab ?? 'postpay';
    $defaultTab = '';

    if ($canViewPostpayReport) {
        $defaultTab = 'postpay';
    } elseif ($canViewDailySmsReport) {
        $defaultTab = 'daily-sms';
    } elseif ($canViewMoneyTransferReport) {
        $defaultTab = 'money-transfer';
    } elseif ($canViewMonthlySalesReport) {
        $defaultTab = 'monthly-sales';
    }

    // Validate requested tab against permissions
    $tabPermissions = [
        'postpay' => $canViewPostpayReport,
        'daily-sms' => $canViewDailySmsReport,
        'money-transfer' => $canViewMoneyTransferReport,
        'monthly-sales' => $canViewMonthlySalesReport,
    ];

    // If user doesn't have permission for requested tab, use default
    if (!($tabPermissions[$requestedTab] ?? false)) {
        $requestedTab = $defaultTab;
    }
@endphp

@push('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <style>
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
        }

        .report-tabs .nav-link {
            color: #293b50;
            font-weight: 500;
            padding: 12px 24px;
            border: none;
            border-bottom: 3px solid transparent;
            background: transparent;
            transition: all 0.3s ease;
        }

        .report-tabs .nav-link:hover {
            color: #ea6118;
            border-bottom-color: #ea6118;
        }

        .report-tabs .nav-link.active {
            color: #ea6118;
            border-bottom-color: #ea6118;
            background: rgba(234, 97, 24, 0.05);
        }

        .report-tabs .nav-link i {
            margin-right: 8px;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-card .form-label {
            font-weight: 500;
            color: #293b50;
            margin-bottom: 5px;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 8px 15px;
        }

        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
        }

        .btn-filter {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background: linear-gradient(135deg, #d1520e, #b8450c);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-reset:hover {
            background: #5a6268;
            color: white;
        }

        .btn-export {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-export:hover {
            background: #218838;
            color: white;
        }

        .btn-export-all {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-export-all:hover {
            background: #138496;
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table thead th {
            background: #293b50;
            color: white;
            font-weight: 500;
            padding: 12px 15px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: rgba(234, 97, 24, 0.05);
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .pagination-info {
            color: #6c757d;
            font-size: 14px;
        }

        .pagination .page-link {
            color: #293b50;
            border-radius: 5px;
            margin: 0 2px;
        }

        .pagination .page-item.active .page-link {
            background: #ea6118;
            border-color: #ea6118;
        }

        .pagination .page-link:hover {
            background: rgba(234, 97, 24, 0.1);
            color: #ea6118;
        }

        .per-page-select {
            width: 120px;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #293b50, #1f2c3d);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .stat-badge i {
            font-size: 18px;
        }

        .stat-badge strong {
            font-size: 16px;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 10px;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ea6118;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        @media (max-width: 768px) {
            .filter-card .row>div {
                margin-bottom: 15px;
            }

            .pagination-container {
                flex-direction: column;
                align-items: stretch;
            }
        }

        /* Select2 Custom Styles for Multiple Selection */
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 4px 8px;
            min-height: 42px;
        }

        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 5px;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .select2-container--bootstrap-5 .select2-results__option--selected {
            background-color: rgba(234, 97, 24, 0.1);
        }

        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #ea6118;
            color: white;
        }

        /* Modern Tag/Chip Styles for Multiple Selection */
        .select2-container--bootstrap-5 .select2-selection--multiple {
            padding: 4px 8px;
            min-height: 42px;
            max-height: 85px;
            overflow-y: auto;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 4px;
        }

        /* Custom scrollbar for select2 multiple */
        .select2-container--bootstrap-5 .select2-selection--multiple::-webkit-scrollbar {
            width: 6px;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple::-webkit-scrollbar-thumb:hover {
            background: #ea6118;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            border: none;
            border-radius: 20px;
            color: white;
            padding: 4px 10px 4px 12px;
            margin: 2px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(234, 97, 24, 0.3);
            transition: all 0.2s ease;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice:hover {
            background: linear-gradient(135deg, #d1520e, #b8450c);
            box-shadow: 0 3px 6px rgba(234, 97, 24, 0.4);
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__display {
            padding-left: 0;
            padding-right: 0;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            font-size: 16px;
            font-weight: bold;
            margin-left: 4px;
            margin-right: 0;
            padding: 0 2px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: all 0.2s ease;
            order: 2;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
            background: rgba(255, 255, 255, 0.4);
            color: white;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-search {
            margin: 0;
            padding: 0;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-search__field {
            margin-top: 4px;
            font-size: 14px;
        }

        .select2-container--bootstrap-5 .select2-selection--multiple .select2-search__field::placeholder {
            color: #999;
        }

        /* Dropdown styles */
        .select2-container--bootstrap-5 .select2-results__option {
            padding: 10px 15px;
            font-size: 14px;
        }

        .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
            background-color: rgba(234, 97, 24, 0.1);
            color: #ea6118;
        }

        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: #ea6118;
            color: white;
        }

        /* Clear all button styling */
        .select2-container--bootstrap-5 .select2-selection__clear {
            color: #999;
            font-size: 18px;
            margin-right: 5px;
        }

        .select2-container--bootstrap-5 .select2-selection__clear:hover {
            color: #ea6118;
        }

        /* Search box in dropdown */
        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
        }

        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
            outline: none;
        }



        .btn-filter:focus {
            color: white !important;
        }

        /* Prevent date shrinking (fix year hidden) */
        .date-box {
            min-width: 170px;
            flex: 0 0 auto;
        }

        /* Prevent button text wrap (fix "Searc h") */
        .btn-nowrap {
            white-space: nowrap;
        }

        /* same height look */
        /* .form-control,
        .btn {
            height: 40px;
        } */

        /* Select2 full width */
        .select2-container {
            width: 100% !important;
        }
    </style>
@endpush

@section('content')
    <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Reports</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item active" aria-current="page">
                                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Reports</li>
                        </ol>
                    </nav>
                </div>
                <div class="me-2 back-button-container"
                    style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                    <button id="backButton" class="btn btn-primary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back
                    </button>
                </div>
            </div>

            <!-- Flash Messages -->
            @if (session('success'))
                <div id="flash-message" class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div id="flash-error-message" class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <!-- Report Card -->
            @if ($defaultTab)
                <div class="card">
                    <div class="card-body">
                        <!-- Report Tabs -->
                        <ul class="nav nav-tabs report-tabs mb-4" id="reportTabs" role="tablist">
                            @if ($canViewPostpayReport)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $requestedTab == 'postpay' ? 'active' : '' }}"
                                        id="postpay-tab" data-bs-toggle="tab" data-bs-target="#postpay" type="button"
                                        role="tab">
                                        <i class="bx bx-credit-card"></i> Post-Pay Report
                                    </button>
                                </li>
                            @endif
                            @if ($canViewDailySmsReport)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $requestedTab == 'daily-sms' ? 'active' : '' }}"
                                        id="daily-sms-tab" data-bs-toggle="tab" data-bs-target="#daily-sms" type="button"
                                        role="tab">
                                        <i class="bx bx-message-detail"></i> Daily SMS Report
                                    </button>
                                </li>
                            @endif
                            @if ($canViewMoneyTransferReport)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $requestedTab == 'money-transfer' ? 'active' : '' }}"
                                        id="money-transfer-tab" data-bs-toggle="tab" data-bs-target="#money-transfer"
                                        type="button" role="tab">
                                        <i class="bx bx-transfer"></i> Money Transferred Report
                                    </button>
                                </li>
                            @endif
                            @if ($canViewMonthlySalesReport)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $requestedTab == 'monthly-sales' ? 'active' : '' }}"
                                        id="monthly-sales-tab" data-bs-toggle="tab" data-bs-target="#monthly-sales"
                                        type="button" role="tab">
                                        <i class="bx bx-line-chart"></i> Sales Report
                                    </button>
                                </li>
                            @endif
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="reportTabsContent">
                            @if ($canViewPostpayReport)
                                <div class="tab-pane fade {{ $requestedTab == 'postpay' ? 'show active' : '' }}"
                                    id="postpay" role="tabpanel">
                                    @include('admin.reports.partials.generate-form', ['reportType' => 'postpay'])
                                </div>
                            @endif

                            @if ($canViewDailySmsReport)
                                <div class="tab-pane fade {{ $requestedTab == 'daily-sms' ? 'show active' : '' }}"
                                    id="daily-sms" role="tabpanel">
                                    @include('admin.reports.partials.generate-form', ['reportType' => 'daily_sms'])
                                </div>
                            @endif

                            @if ($canViewMoneyTransferReport)
                                <div class="tab-pane fade {{ $requestedTab == 'money-transfer' ? 'show active' : '' }}"
                                    id="money-transfer" role="tabpanel">
                                    @include('admin.reports.partials.generate-form', ['reportType' => 'money_transfer'])
                                </div>
                            @endif

                            @if ($canViewMonthlySalesReport)
                                <div class="tab-pane fade {{ $requestedTab == 'monthly-sales' ? 'show active' : '' }}"
                                    id="monthly-sales" role="tabpanel">
                                    @include('admin.reports.partials.sales-report')
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @include('admin.reports.partials.report-history')
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-warning mb-0">
                            <i class="material-icons-outlined me-2">warning</i>
                            You don't have permission to access any report tabs. Please contact your administrator.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </main>

    @include('admin.layouts.footer')
@endsection

@push('js')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Legacy report-table & export JS disabled — those immediate tables/exports were
        // replaced by the per-tab Generate form + shared Report History. Kept for reference.
        if (false) {
        // Flash message auto-hide
        setTimeout(function() {
            let flashMessage = document.getElementById('flash-message');
            if (flashMessage) flashMessage.style.display = 'none';
        }, 3000);

        setTimeout(function() {
            let flashMessage = document.getElementById('flash-error-message');
            if (flashMessage) flashMessage.style.display = 'none';
        }, 3000);

        // Customer data for dropdowns
        const customers = @json($customers);

        // Initialize Select2 for customer dropdowns
        $(document).ready(function() {
            // $('.customer-select').select2({
            //     theme: 'bootstrap-5',
            //     placeholder: 'Select customers...',
            //     allowClear: true,
            //     width: '100%',
            //     multiple: true,
            //     closeOnSelect: false,
            //     templateSelection: function(data) {
            //         if (!data.id) return data.text;
            //         // Truncate long names for tags
            //         var text = data.text;
            //         if (text.length > 25) {
            //             text = text.substring(0, 22) + '...';
            //         }
            //         return text;
            //     }
            // });

            $('.customer-select').select2({
                theme: 'bootstrap-5',
                placeholder: 'Select customers...',
                allowClear: true,
                width: '100%',
                multiple: true,
                closeOnSelect: false
            });




            // Date validation for all report tabs
            setupDateValidation('postpay');
            setupDateValidation('daily-sms');
            setupDateValidation('money-transfer');

            // Load initial data for active tab
            const activeTab = '{{ $requestedTab }}';
            if (activeTab === 'postpay') {
                loadPostPayData();
            } else if (activeTab === 'daily-sms') {
                loadDailySmsData();
            } else if (activeTab === 'money-transfer') {
                loadMoneyTransferData();
            }
        });

        // Setup date validation for a specific tab
        function setupDateValidation(prefix) {
            const dateFrom = document.getElementById(`${prefix}-date-from`);
            const dateTo = document.getElementById(`${prefix}-date-to`);

            if (dateFrom && dateTo) {
                // When Date From changes, set min for Date To
                dateFrom.addEventListener('change', function() {
                    if (this.value) {
                        dateTo.min = this.value;
                        // If Date To is before Date From, clear it
                        if (dateTo.value && dateTo.value < this.value) {
                            dateTo.value = '';
                        }
                    } else {
                        dateTo.min = '';
                    }
                });

                // When Date To changes, set max for Date From
                dateTo.addEventListener('change', function() {
                    if (this.value) {
                        dateFrom.max = this.value;
                        // If Date From is after Date To, clear it
                        if (dateFrom.value && dateFrom.value > this.value) {
                            dateFrom.value = '';
                        }
                    } else {
                        dateFrom.max = '';
                    }
                });
            }
        }

        // Tab change handler
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            const targetId = $(e.target).data('bs-target');
            if (targetId === '#postpay') {
                loadPostPayData();
            } else if (targetId === '#daily-sms') {
                loadDailySmsData();
            } else if (targetId === '#money-transfer') {
                loadMoneyTransferData();
            }
        });

        // ==================== POST-PAY REPORT ====================
        let postpayCurrentPage = 1;
        let postpayPerPage = 25;

        function loadPostPayData(page = 1) {
            postpayCurrentPage = page;
            const container = $('#postpay-table-container');
            container.find('.loading-overlay').show();

            const params = {
                page: page,
                per_page: postpayPerPage,
                search: $('#postpay-search').val(),
                customer_ids: $('#postpay-customer').val(),
                date_from: $('#postpay-date-from').val(),
                date_to: $('#postpay-date-to').val()
            };

            $.get('{{ route('admin.reports.postpay.data') }}', params, function(response) {
                renderPostPayTable(response);
                renderPagination('postpay', response);
                container.find('.loading-overlay').hide();
            }).fail(function() {
                container.find('.loading-overlay').hide();
                alert('Error loading data. Please try again.');
            });
        }

        function renderPostPayTable(response) {
            const tbody = $('#postpay-table tbody');
            tbody.empty();

            if (response.data.length === 0) {
                tbody.html('<tr><td colspan="7" class="no-data"><i class="bx bx-inbox"></i>No data found</td></tr>');
                $('#postpay-total-records').text('0');
                return;
            }

            $('#postpay-total-records').text(response.total);

            response.data.forEach((item, index) => {
                const row = `
                <tr>
                    <td>${response.from + index}</td>
                    <td>${item.customer_name}</td>
                    <td>${item.contact_name}</td>
                    <td>${item.username}</td>
                    <td class="text-end">${item.total_sms}</td>
                    <td class="text-end">£${item.per_sms_rate}</td>
                    <td class="text-end">£${item.total_cost}</td>
                </tr>
            `;
                tbody.append(row);
            });
        }

        function filterPostPay() {
            loadPostPayData(1);
        }

        function resetPostPayFilters() {
            const today = '{{ now()->format('Y-m-d') }}';
            $('#postpay-search').val('');
            $('#postpay-customer').val('').trigger('change');
            $('#postpay-date-from').val(today).removeAttr('max');
            $('#postpay-date-to').val(today).removeAttr('min');
            loadPostPayData(1);
        }

        function exportPostPay(exportAll = false) {
            let params = new URLSearchParams();

            if (!exportAll) {
                params.append('search', $('#postpay-search').val());
                const customerIds = $('#postpay-customer').val();
                if (customerIds && customerIds.length > 0) {
                    customerIds.forEach(id => params.append('customer_ids[]', id));
                }
                params.append('date_from', $('#postpay-date-from').val());
                params.append('date_to', $('#postpay-date-to').val());
            }

            window.location.href = '{{ route('admin.reports.postpay.export') }}?' + params.toString();
        }

        // ==================== DAILY SMS REPORT ====================
        let dailySmsCurrentPage = 1;
        let dailySmsPerPage = 25;

        function loadDailySmsData(page = 1) {
            dailySmsCurrentPage = page;
            const container = $('#daily-sms-table-container');
            container.find('.loading-overlay').show();

            const params = {
                page: page,
                per_page: dailySmsPerPage,
                search: $('#daily-sms-search').val(),
                customer_ids: $('#daily-sms-customer').val(),
                date_from: $('#daily-sms-date-from').val(),
                date_to: $('#daily-sms-date-to').val()
            };

            $.get('{{ route('admin.reports.daily-sms.data') }}', params, function(response) {
                renderDailySmsTable(response);
                renderPagination('daily-sms', response);
                container.find('.loading-overlay').hide();
            }).fail(function() {
                container.find('.loading-overlay').hide();
                alert('Error loading data. Please try again.');
            });
        }

        function renderDailySmsTable(response) {
            const tbody = $('#daily-sms-table tbody');
            tbody.empty();

            if (response.data.length === 0) {
                tbody.html('<tr><td colspan="8" class="no-data"><i class="bx bx-inbox"></i>No data found</td></tr>');
                $('#daily-sms-total-records').text('0');
                return;
            }

            $('#daily-sms-total-records').text(response.total);

            response.data.forEach((item, index) => {
                const row = `
                <tr>
                    <td>${response.from + index}</td>
                    <td>${item.customer_name}</td>
                    <td>${item.contact_name}</td>
                    <td>${item.username}</td>
                    <td class="text-end">${item.total_sms}</td>
                    <td class="text-end">£${item.cost_price}</td>
                    <td class="text-end">£${item.user_price}</td>
                    <td class="text-end">£${item.profit}</td>
                </tr>
            `;
                tbody.append(row);
            });
        }

        function filterDailySms() {
            loadDailySmsData(1);
        }

        function resetDailySmsFilters() {
            let today = new Date().toISOString().split('T')[0];

            $('#daily-sms-search').val('');
            $('#daily-sms-customer').val('').trigger('change');
            $('#daily-sms-date-from').val(today).removeAttr('max');
            $('#daily-sms-date-to').val(today).removeAttr('min');
            // $('#daily-sms-date-from').val('').removeAttr('max');
            // $('#daily-sms-date-to').val('').removeAttr('min');
            loadDailySmsData(1);
        }

        function exportDailySms(exportAll = false) {
            let params = new URLSearchParams();

            if (!exportAll) {
                params.append('search', $('#daily-sms-search').val());
                const customerIds = $('#daily-sms-customer').val();
                if (customerIds && customerIds.length > 0) {
                    customerIds.forEach(id => params.append('customer_ids[]', id));
                }
                params.append('date_from', $('#daily-sms-date-from').val());
                params.append('date_to', $('#daily-sms-date-to').val());
            }

            window.location.href = '{{ route('admin.reports.daily-sms.export') }}?' + params.toString();
        }

        // ==================== MONEY TRANSFERRED REPORT ====================
        let moneyTransferCurrentPage = 1;
        let moneyTransferPerPage = 25;

        function loadMoneyTransferData(page = 1) {
            moneyTransferCurrentPage = page;
            const container = $('#money-transfer-table-container');
            container.find('.loading-overlay').show();

            const params = {
                page: page,
                per_page: moneyTransferPerPage,
                search: $('#money-transfer-search').val(),
                customer_ids: $('#money-transfer-customer').val(),
                date_from: $('#money-transfer-date-from').val(),
                date_to: $('#money-transfer-date-to').val()
            };

            $.get('{{ route('admin.reports.money-transfer.data') }}', params, function(response) {
                renderMoneyTransferTable(response);
                renderPagination('money-transfer', response);
                container.find('.loading-overlay').hide();
            }).fail(function() {
                container.find('.loading-overlay').hide();
                alert('Error loading data. Please try again.');
            });
        }

        function renderMoneyTransferTable(response) {
            const tbody = $('#money-transfer-table tbody');
            tbody.empty();

            if (response.data.length === 0) {
                tbody.html('<tr><td colspan="8" class="no-data"><i class="bx bx-inbox"></i>No data found</td></tr>');
                $('#money-transfer-total-records').text('0');
                return;
            }

            $('#money-transfer-total-records').text(response.total);

            response.data.forEach((item, index) => {
                const row = `
                <tr>
                    <td>${response.from + index}</td>
                    <td>${item.date_time}</td>
                    <td class="text-end">£${item.amount}</td>
                    <td>${item.lead_account}</td>
                    <td>${item.transferred_from}</td>
                    <td>${item.transferred_to}</td>
                    <td>${item.transferred_by}</td>
                    <td>${item.ip_address}</td>
                </tr>
            `;
                tbody.append(row);
            });
        }

        function filterMoneyTransfer() {
            loadMoneyTransferData(1);
        }

        function resetMoneyTransferFilters() {
            $('#money-transfer-search').val('');
            $('#money-transfer-customer').val('').trigger('change');
            $('#money-transfer-date-from').val('').removeAttr('max');
            $('#money-transfer-date-to').val('').removeAttr('min');
            loadMoneyTransferData(1);
        }

        function exportMoneyTransfer(exportAll = false) {
            let params = new URLSearchParams();

            if (!exportAll) {
                params.append('search', $('#money-transfer-search').val());
                const customerIds = $('#money-transfer-customer').val();
                if (customerIds && customerIds.length > 0) {
                    customerIds.forEach(id => params.append('customer_ids[]', id));
                }
                params.append('date_from', $('#money-transfer-date-from').val());
                params.append('date_to', $('#money-transfer-date-to').val());
            }

            window.location.href = '{{ route('admin.reports.money-transfer.export') }}?' + params.toString();
        }

        // ==================== PAGINATION ====================
        function renderPagination(prefix, response) {
            const container = $(`#${prefix}-pagination`);
            container.empty();

            if (response.last_page <= 1) return;

            let html = '<nav><ul class="pagination mb-0">';

            // Previous
            if (response.current_page > 1) {
                html +=
                    `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="load${capitalizeFirst(prefix.replace('-', ''))}Data(${response.current_page - 1})">Previous</a></li>`;
            } else {
                html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
            }

            // Page numbers
            const start = Math.max(1, response.current_page - 2);
            const end = Math.min(response.last_page, response.current_page + 2);

            if (start > 1) {
                html +=
                    `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="load${capitalizeFirst(prefix.replace('-', ''))}Data(1)">1</a></li>`;
                if (start > 2) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            for (let i = start; i <= end; i++) {
                if (i === response.current_page) {
                    html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                } else {
                    html +=
                        `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="load${capitalizeFirst(prefix.replace('-', ''))}Data(${i})">${i}</a></li>`;
                }
            }

            if (end < response.last_page) {
                if (end < response.last_page - 1) html +=
                    '<li class="page-item disabled"><span class="page-link">...</span></li>';
                html +=
                    `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="load${capitalizeFirst(prefix.replace('-', ''))}Data(${response.last_page})">${response.last_page}</a></li>`;
            }

            // Next
            if (response.current_page < response.last_page) {
                html +=
                    `<li class="page-item"><a class="page-link" href="javascript:void(0)" onclick="load${capitalizeFirst(prefix.replace('-', ''))}Data(${response.current_page + 1})">Next</a></li>`;
            } else {
                html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
            }

            html += '</ul></nav>';

            // Pagination info
            html +=
                `<div class="pagination-info">Showing ${response.from || 0} to ${response.to || 0} of ${response.total} entries</div>`;

            container.html(html);
        }

        function capitalizeFirst(str) {
            return str.split('-').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('');
        }

        // Per page change handlers
        function changePostPayPerPage(value) {
            postpayPerPage = parseInt(value);
            loadPostPayData(1);
        }

        function changeDailySmsPerPage(value) {
            dailySmsPerPage = parseInt(value);
            loadDailySmsData(1);
        }

        function changeMoneyTransferPerPage(value) {
            moneyTransferPerPage = parseInt(value);
            loadMoneyTransferData(1);
        }

        // Function mappings for pagination
        function loadPostpayData(page) {
            loadPostPayData(page);
        }

        function loadDailysmsData(page) {
            loadDailySmsData(page);
        }

        function loadMoneytransferData(page) {
            loadMoneyTransferData(page);
        }
        } // end disabled legacy report block
    </script>
@endpush
