@extends('admin.layouts.app')
@section('title', 'CRM')

@push('style')
    <style>
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
        }

        .table-controls {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-add-contract {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-add-contract:hover {
            background: linear-gradient(135deg, #d1520e, #b8450c);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        }

        .contract-type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .type-main {
            background: linear-gradient(135deg, #6f42c1, #5a32a3);
            color: white;
        }

        .type-addendum {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        .type-privacy {
            background: linear-gradient(135deg, #20c997, #17a689);
            color: white;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .customer-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            background: #fff3cd;
            color: #856404;
            display: inline-block;
            margin: 2px;
        }

        .customer-badge.all-customers {
            background: #cfe2ff;
            color: #084298;
        }

        .customer-badge.multiple-customers {
            background: #e2e3e5;
            color: #41464b;
        }

        .customer-list {
            max-width: 200px;
        }

        .customer-list .customer-badge {
            display: block;
            margin-bottom: 3px;
        }

        .more-customers {
            background: #6c757d;
            color: white;
            cursor: pointer;
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

        .action-btns {
            display: flex;
            gap: 5px;
        }

        .action-btns .btn {
            padding: 5px 10px;
            font-size: 14px;
        }

        .section-title {
            background: #293b50;
            color: white;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
            font-weight: 500;
            font-size: 16px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Tooltip for customer list */
        .customer-tooltip {
            position: relative;
            cursor: help;
        }

        .customer-tooltip .tooltip-content {
            display: none;
            position: absolute;
            background: #333;
            color: white;
            padding: 10px;
            border-radius: 8px;
            z-index: 1000;
            min-width: 200px;
            max-width: 300px;
            left: 0;
            top: 100%;
            margin-top: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .customer-tooltip:hover .tooltip-content {
            display: block;
        }

        .tooltip-content .customer-item {
            padding: 3px 0;
            border-bottom: 1px solid #555;
            font-size: 12px;
        }

        .tooltip-content .customer-item:last-child {
            border-bottom: none;
        }
    </style>
@endpush

@section('content')
    <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Manage Contracts</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Contracts</li>
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
            <!--end breadcrumb-->

            <!-- Success Message -->
            {{-- @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="material-icons-outlined align-middle me-2">check_circle</i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif --}}
              <!--end breadcrumb-->
            @if (session('success'))
                <div id="flash-message" class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div id="flash-error-message" class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Table Controls -->
            <div class="card">

                <div class="card-body">
                    <div class="table-controls">
                        <div>
                            <h5 class="mb-0">Contract Management</h5>
                        </div>
                        <div>
                            <a href="{{ route('admin.contracts.create') }}" class="btn btn-add-contract">
                                <i class="material-icons-outlined align-middle" style="font-size: 18px;">add</i>
                                Create New Contract
                            </a>
                        </div>
                    </div>

                    <!-- Main Contracts Table -->
                    <div class="card mb-4">
                        <div class="section-title">
                            <i class="material-icons-outlined align-middle me-2">description</i>
                            Main Client Contracts
                        </div>
                        <div class="card-body">
                            @if ($mainContracts->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                {{-- <th>Type</th> --}}
                                                <th>File</th>
                                                <th>Assigned To</th>
                                                <th>Version</th>
                                                <th>Status</th>
                                                <th>Signatures</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($mainContracts as $contract)
                                                <tr>
                                                    <td><strong>{{ $contract->title }}</strong></td>
                                                    {{-- <td>
                                            <span class="contract-type-badge type-main">Main Contract</span>
                                        </td> --}}
                                                    <td>
                                                        @if ($contract->hasFile())
                                                            <a href="{{ route('admin.contracts.download', $contract->id) }}"
                                                                class="btn btn-sm btn-outline-primary"
                                                                title="Download {{ $contract->file_name }}">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 14px;">download</i>
                                                                {{ strtoupper($contract->file_type) }}
                                                            </a>
                                                            <small
                                                                class="d-block text-muted">{{ $contract->getFileSizeFormatted() }}</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            // Check many-to-many relationship first
                                                            $assignedCustomers = $contract->customers;
                                                            $customerCount = $assignedCustomers->count();

                                                            // Also check legacy customer_id field
                                                            $legacyCustomer = $contract->customer;
                                                        @endphp

                                                        @if ($customerCount > 0)
                                                            {{-- Customers assigned via many-to-many --}}
                                                            @if ($customerCount == 1)
                                                                @php
                                                                    $cust = $assignedCustomers->first();
                                                                    $custName = $cust->contactname
                                                                        ? urldecode(
                                                                            urldecode($cust->contactname),
                                                                        )
                                                                        : ($cust->busname
                                                                            ? urldecode(
                                                                                urldecode($cust->busname),
                                                                            )
                                                                            : $cust->uname);
                                                                @endphp
                                                                <span class="customer-badge">
                                                                    <i class="material-icons-outlined align-middle"
                                                                        style="font-size: 14px;">person</i>
                                                                    {{ $custName }}
                                                                </span>
                                                            @else
                                                                <div class="customer-tooltip">
                                                                    <span class="customer-badge multiple-customers">
                                                                        <i class="material-icons-outlined align-middle"
                                                                            style="font-size: 14px;">group</i>
                                                                        {{ $customerCount }} Customers
                                                                    </span>
                                                                    <div class="tooltip-content">
                                                                        <strong
                                                                            style="display: block; margin-bottom: 8px; border-bottom: 1px solid #555; padding-bottom: 5px;">Assigned
                                                                            Customers:</strong>
                                                                        @foreach ($assignedCustomers as $cust)
                                                                            @php
                                                                                $custName = $cust->contactname
                                                                                    ? urldecode(
                                                                                        str_replace(
                                                                                            '+',
                                                                                            ' ',
                                                                                            $cust->contactname,
                                                                                        ),
                                                                                    )
                                                                                    : ($cust->busname
                                                                                        ? urldecode(
                                                                                            str_replace(
                                                                                                '+',
                                                                                                ' ',
                                                                                                $cust->busname,
                                                                                            ),
                                                                                        )
                                                                                        : $cust->uname);
                                                                            @endphp
                                                                            <div class="customer-item">
                                                                                <i class="material-icons-outlined align-middle"
                                                                                    style="font-size: 12px;">person</i>
                                                                                {{ $custName }}
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @elseif($legacyCustomer)
                                                            {{-- Legacy customer_id field --}}
                                                            @php
                                                                $custName = $legacyCustomer->contactname
                                                                    ? urldecode(
                                                                        str_replace(
                                                                            '+',
                                                                            ' ',
                                                                            $legacyCustomer->contactname,
                                                                        ),
                                                                    )
                                                                    : ($legacyCustomer->busname
                                                                        ? urldecode(
                                                                            str_replace(
                                                                                '+',
                                                                                ' ',
                                                                                $legacyCustomer->busname,
                                                                            ),
                                                                        )
                                                                        : $legacyCustomer->uname);
                                                            @endphp
                                                            <span class="customer-badge">
                                                                <i class="material-icons-outlined align-middle"
                                                                    style="font-size: 14px;">person</i>
                                                                {{ $custName }}
                                                            </span>
                                                        @else
                                                            {{-- No specific customers assigned = All Customers --}}
                                                            <span class="customer-badge all-customers">
                                                                <i class="material-icons-outlined align-middle"
                                                                    style="font-size: 14px;">groups</i>
                                                                All Customers
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>v{{ $contract->version }}</td>
                                                    <td>
                                                        <span
                                                            class="status-badge {{ $contract->is_active ? 'status-active' : 'status-inactive' }}">
                                                            {{ $contract->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-info">{{ $contract->signatures->count() }}</span>
                                                    </td>
                                                    <td>{{ $contract->created_at->format('d M Y') }}</td>
                                                    <td>
                                                        <div class="action-btns">
                                                            <a href="{{ route('admin.contracts.signatures', $contract->id) }}"
                                                                class="btn btn-sm btn-info" title="View Signatures">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 16px;">how_to_reg</i>
                                                            </a>
                                                            <a href="{{ route('admin.contracts.edit', $contract->id) }}"
                                                                class="btn btn-sm btn-primary" title="Edit">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 16px;">edit</i>
                                                            </a>
                                                            <form
                                                                action="{{ route('admin.contracts.toggle-status', $contract->id) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                @method('PUT')
                                                                <button type="submit" class="btn btn-sm btn-warning"
                                                                    title="Toggle Status">
                                                                    <i class="material-icons-outlined"
                                                                        style="font-size: 16px;">power_settings_new</i>
                                                                </button>
                                                            </form>
                                                            <form
                                                                action="{{ route('admin.contracts.destroy', $contract->id) }}"
                                                                method="POST" class="d-inline"
                                                                onsubmit="return confirm('Are you sure you want to delete this contract?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger"
                                                                    title="Delete">
                                                                    <i class="material-icons-outlined"
                                                                        style="font-size: 16px;">delete</i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="empty-state">
                                    <i class="material-icons-outlined">folder_open</i>
                                    <p class="mb-3">No main contracts created yet.</p>
                                    <a href="{{ route('admin.contracts.create') }}" class="btn btn-primary">
                                        {{-- <i class="material-icons-outlined align-middle me-2">add</i> --}}
                                        Create First Contract
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Addendums Table -->
                    <div class="card mb-4">
                        <div class="section-title">
                            <i class="material-icons-outlined align-middle me-2">library_add</i>
                            Addendums to Client Contract
                        </div>
                        <div class="card-body">
                            @if ($addendums->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                {{-- <th>Type</th> --}}
                                                <th>File</th>
                                                <th>Assigned To</th>
                                                <th>Version</th>
                                                <th>Status</th>
                                                <th>Signatures</th>
                                                <th>Requires Signature</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($addendums as $contract)
                                                <tr>
                                                    <td><strong>{{ $contract->title }}</strong></td>
                                                    {{-- <td>
                                            <span class="contract-type-badge type-addendum">Addendum</span>
                                        </td> --}}
                                                    <td>
                                                        @if ($contract->hasFile())
                                                            <a href="{{ route('admin.contracts.download', $contract->id) }}"
                                                                class="btn btn-sm btn-outline-primary"
                                                                title="Download {{ $contract->file_name }}">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 14px;">download</i>
                                                                {{ strtoupper($contract->file_type) }}
                                                            </a>
                                                            <small
                                                                class="d-block text-muted">{{ $contract->getFileSizeFormatted() }}</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            // Check many-to-many relationship first
                                                            $assignedCustomers = $contract->customers;
                                                            $customerCount = $assignedCustomers->count();

                                                            // Also check legacy customer_id field
                                                            $legacyCustomer = $contract->customer;
                                                        @endphp

                                                        @if ($customerCount > 0)
                                                            {{-- Customers assigned via many-to-many --}}
                                                            @if ($customerCount == 1)
                                                                @php
                                                                    $cust = $assignedCustomers->first();
                                                                    $custName = $cust->contactname
                                                                        ? urldecode(
                                                                            urldecode($cust->contactname),
                                                                        )
                                                                        : ($cust->busname
                                                                            ? urldecode(
                                                                                urldecode($cust->busname),
                                                                            )
                                                                            : $cust->uname);
                                                                @endphp
                                                                <span class="customer-badge">
                                                                    <i class="material-icons-outlined align-middle"
                                                                        style="font-size: 14px;">person</i>
                                                                    {{ $custName }}
                                                                </span>
                                                            @else
                                                                <div class="customer-tooltip">
                                                                    <span class="customer-badge multiple-customers">
                                                                        <i class="material-icons-outlined align-middle"
                                                                            style="font-size: 14px;">group</i>
                                                                        {{ $customerCount }} Customers
                                                                    </span>
                                                                    <div class="tooltip-content">
                                                                        <strong
                                                                            style="display: block; margin-bottom: 8px; border-bottom: 1px solid #555; padding-bottom: 5px;">Assigned
                                                                            Customers:</strong>
                                                                        @foreach ($assignedCustomers as $cust)
                                                                            @php
                                                                                $custName = $cust->contactname
                                                                                    ? urldecode(
                                                                                        str_replace(
                                                                                            '+',
                                                                                            ' ',
                                                                                            $cust->contactname,
                                                                                        ),
                                                                                    )
                                                                                    : ($cust->busname
                                                                                        ? urldecode(
                                                                                            str_replace(
                                                                                                '+',
                                                                                                ' ',
                                                                                                $cust->busname,
                                                                                            ),
                                                                                        )
                                                                                        : $cust->uname);
                                                                            @endphp
                                                                            <div class="customer-item">
                                                                                <i class="material-icons-outlined align-middle"
                                                                                    style="font-size: 12px;">person</i>
                                                                                {{ $custName }}
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @elseif($legacyCustomer)
                                                            {{-- Legacy customer_id field --}}
                                                            @php
                                                                $custName = $legacyCustomer->contactname
                                                                    ? urldecode(
                                                                        str_replace(
                                                                            '+',
                                                                            ' ',
                                                                            $legacyCustomer->contactname,
                                                                        ),
                                                                    )
                                                                    : ($legacyCustomer->busname
                                                                        ? urldecode(
                                                                            str_replace(
                                                                                '+',
                                                                                ' ',
                                                                                $legacyCustomer->busname,
                                                                            ),
                                                                        )
                                                                        : $legacyCustomer->uname);
                                                            @endphp
                                                            <span class="customer-badge">
                                                                <i class="material-icons-outlined align-middle"
                                                                    style="font-size: 14px;">person</i>
                                                                {{ $custName }}
                                                            </span>
                                                        @else
                                                            {{-- No specific customers assigned = All Customers --}}
                                                            <span class="customer-badge all-customers">
                                                                <i class="material-icons-outlined align-middle"
                                                                    style="font-size: 14px;">groups</i>
                                                                All Customers
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>v{{ $contract->version }}</td>
                                                    <td>
                                                        <span
                                                            class="status-badge {{ $contract->is_active ? 'status-active' : 'status-inactive' }}">
                                                            {{ $contract->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-info">{{ $contract->signatures->count() }}</span>
                                                    </td>
                                                    <td>
                                                        @if ($contract->requires_signature)
                                                            <span class="badge bg-warning text-dark">Yes</span>
                                                        @else
                                                            <span class="badge bg-secondary">No</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $contract->created_at->format('d M Y') }}</td>
                                                    <td>
                                                        <div class="action-btns">
                                                            <a href="{{ route('admin.contracts.signatures', $contract->id) }}"
                                                                class="btn btn-sm btn-info" title="View Signatures">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 16px;">how_to_reg</i>
                                                            </a>
                                                            <a href="{{ route('admin.contracts.edit', $contract->id) }}"
                                                                class="btn btn-sm btn-primary" title="Edit">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 16px;">edit</i>
                                                            </a>
                                                            <form
                                                                action="{{ route('admin.contracts.toggle-status', $contract->id) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                @method('PUT')
                                                                <button type="submit" class="btn btn-sm btn-warning"
                                                                    title="Toggle Status">
                                                                    <i class="material-icons-outlined"
                                                                        style="font-size: 16px;">power_settings_new</i>
                                                                </button>
                                                            </form>
                                                            <form
                                                                action="{{ route('admin.contracts.destroy', $contract->id) }}"
                                                                method="POST" class="d-inline"
                                                                onsubmit="return confirm('Are you sure you want to delete this addendum?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger"
                                                                    title="Delete">
                                                                    <i class="material-icons-outlined"
                                                                        style="font-size: 16px;">delete</i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="empty-state">
                                    <i class="material-icons-outlined">folder_open</i>
                                    <p class="mb-3">No addendums created yet.</p>
                                    <a href="{{ route('admin.contracts.create') }}" class="btn btn-primary">
                                        {{-- <i class="material-icons-outlined align-middle me-2">add</i> --}}
                                        Create First Addendum
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Privacy Policies Table -->
                    <div class="card mb-4">
                        <div class="section-title" style="background: #28a745;">
                            <i class="material-icons-outlined align-middle me-2">policy</i>
                            Privacy Policies
                        </div>
                        <div class="card-body">
                            @if ($privacyPolicies->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                {{-- <th>Type</th> --}}
                                                <th>File</th>
                                                <th>Assigned To</th>
                                                <th>Version</th>
                                                <th>Status</th>
                                                <th>Signatures</th>
                                                <th>Requires Signature</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($privacyPolicies as $contract)
                                                <tr>
                                                    <td><strong>{{ $contract->title }}</strong></td>
                                                    {{-- <td>
                                            <span class="contract-type-badge type-privacy">Privacy Policy</span>
                                        </td> --}}
                                                    <td>
                                                        @if ($contract->hasFile())
                                                            <a href="{{ route('admin.contracts.download', $contract->id) }}"
                                                                class="btn btn-sm btn-outline-primary"
                                                                title="Download {{ $contract->file_name }}">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 14px;">download</i>
                                                                {{ strtoupper($contract->file_type) }}
                                                            </a>
                                                            <small
                                                                class="d-block text-muted">{{ $contract->getFileSizeFormatted() }}</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            // Check many-to-many relationship first
                                                            $assignedCustomers = $contract->customers;
                                                            $customerCount = $assignedCustomers->count();

                                                            // Also check legacy customer_id field
                                                            $legacyCustomer = $contract->customer;
                                                        @endphp

                                                        @if ($customerCount > 0)
                                                            {{-- Customers assigned via many-to-many --}}
                                                            @if ($customerCount == 1)
                                                                @php
                                                                    $cust = $assignedCustomers->first();
                                                                    $custName = $cust->contactname
                                                                        ? urldecode(
                                                                            urldecode($cust->contactname),
                                                                        )
                                                                        : ($cust->busname
                                                                            ? urldecode(
                                                                                urldecode($cust->busname),
                                                                            )
                                                                            : $cust->uname);
                                                                @endphp
                                                                <span class="customer-badge">
                                                                    <i class="material-icons-outlined align-middle"
                                                                        style="font-size: 14px;">person</i>
                                                                    {{ $custName }}
                                                                </span>
                                                            @else
                                                                <div class="customer-tooltip">
                                                                    <span class="customer-badge multiple-customers">
                                                                        <i class="material-icons-outlined align-middle"
                                                                            style="font-size: 14px;">group</i>
                                                                        {{ $customerCount }} Customers
                                                                    </span>
                                                                    <div class="tooltip-content">
                                                                        <strong
                                                                            style="display: block; margin-bottom: 8px; border-bottom: 1px solid #555; padding-bottom: 5px;">Assigned
                                                                            Customers:</strong>
                                                                        @foreach ($assignedCustomers as $cust)
                                                                            @php
                                                                                $custName = $cust->contactname
                                                                                    ? urldecode(
                                                                                        str_replace(
                                                                                            '+',
                                                                                            ' ',
                                                                                            $cust->contactname,
                                                                                        ),
                                                                                    )
                                                                                    : ($cust->busname
                                                                                        ? urldecode(
                                                                                            str_replace(
                                                                                                '+',
                                                                                                ' ',
                                                                                                $cust->busname,
                                                                                            ),
                                                                                        )
                                                                                        : $cust->uname);
                                                                            @endphp
                                                                            <div class="customer-item">
                                                                                <i class="material-icons-outlined align-middle"
                                                                                    style="font-size: 12px;">person</i>
                                                                                {{ $custName }}
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @elseif($legacyCustomer)
                                                            {{-- Legacy customer_id field --}}
                                                            @php
                                                                $custName = $legacyCustomer->contactname
                                                                    ? urldecode(
                                                                        str_replace(
                                                                            '+',
                                                                            ' ',
                                                                            $legacyCustomer->contactname,
                                                                        ),
                                                                    )
                                                                    : ($legacyCustomer->busname
                                                                        ? urldecode(
                                                                            str_replace(
                                                                                '+',
                                                                                ' ',
                                                                                $legacyCustomer->busname,
                                                                            ),
                                                                        )
                                                                        : $legacyCustomer->uname);
                                                            @endphp
                                                            <span class="customer-badge">
                                                                <i class="material-icons-outlined align-middle"
                                                                    style="font-size: 14px;">person</i>
                                                                {{ $custName }}
                                                            </span>
                                                        @else
                                                            {{-- No specific customers assigned = All Customers --}}
                                                            <span class="customer-badge all-customers">
                                                                <i class="material-icons-outlined align-middle"
                                                                    style="font-size: 14px;">groups</i>
                                                                All Customers
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>v{{ $contract->version }}</td>
                                                    <td>
                                                        <span
                                                            class="status-badge {{ $contract->is_active ? 'status-active' : 'status-inactive' }}">
                                                            {{ $contract->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-info">{{ $contract->signatures->count() }}</span>
                                                    </td>
                                                    <td>
                                                        @if ($contract->requires_signature)
                                                            <span class="badge bg-warning text-dark">Yes</span>
                                                        @else
                                                            <span class="badge bg-secondary">No</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $contract->created_at->format('d M Y') }}</td>
                                                    <td>
                                                        <div class="action-btns">
                                                            <a href="{{ route('admin.contracts.signatures', $contract->id) }}"
                                                                class="btn btn-sm btn-info" title="View Signatures">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 16px;">how_to_reg</i>
                                                            </a>
                                                            <a href="{{ route('admin.contracts.edit', $contract->id) }}"
                                                                class="btn btn-sm btn-primary" title="Edit">
                                                                <i class="material-icons-outlined"
                                                                    style="font-size: 16px;">edit</i>
                                                            </a>
                                                            <form
                                                                action="{{ route('admin.contracts.toggle-status', $contract->id) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                @method('PUT')
                                                                <button type="submit" class="btn btn-sm btn-warning"
                                                                    title="Toggle Status">
                                                                    <i class="material-icons-outlined"
                                                                        style="font-size: 16px;">power_settings_new</i>
                                                                </button>
                                                            </form>
                                                            <form
                                                                action="{{ route('admin.contracts.destroy', $contract->id) }}"
                                                                method="POST" class="d-inline"
                                                                onsubmit="return confirm('Are you sure you want to delete this privacy policy?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger"
                                                                    title="Delete">
                                                                    <i class="material-icons-outlined"
                                                                        style="font-size: 16px;">delete</i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="empty-state">
                                    <i class="material-icons-outlined">policy</i>
                                    <p class="mb-3">No privacy policies created yet.</p>
                                    <a href="{{ route('admin.contracts.create') }}" class="btn btn-primary">
                                        {{-- <i class="material-icons-outlined align-middle me-2">add</i> --}}
                                        Create First Privacy Policy
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('admin.layouts.footer')
@endsection
@push('js')
    <script>

        setTimeout(function() {
            let flashMessage = document.getElementById('flash-message');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }, 2000);

        setTimeout(function() {
            let flashMessage = document.getElementById('flash-error-message');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }, 2000);
    </script>
@endpush

