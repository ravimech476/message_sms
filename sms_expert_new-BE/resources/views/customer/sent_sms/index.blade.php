@extends('layouts.app')
@section('title', 'Sent SMS - SMS Expert')

@push('style')
    <style>
        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .sentsms-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .sentsms-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .sentsms-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .sentsms-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 2rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .filter-section {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .filter-title {
            color: #293b50;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group {
            display: grid;
            gap: 0.75rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            background: rgba(234, 97, 24, 0.05);
        }

        .form-check-input:checked {
            background-color: #ea6118;
            border-color: #ea6118;
        }

        .form-check-label {
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            margin: 0;
        }

        .form-label {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control,
        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
            outline: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .breadcrumb-container {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .breadcrumb {
            margin: 0;
            background: transparent;
        }

        .breadcrumb-item a {
            color: #ea6118;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #64748b;
        }

        .breadcrumb-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .back-button {
            background: linear-gradient(135deg, #64748b, #475569);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
            color: white;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .time-inputs {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .time-inputs .form-control,
        .time-inputs .form-select {
            min-width: 80px;
            flex: 1;
        }

        .data-table {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: #293b50;
            font-weight: 700;
            padding: 1.5rem 1rem;
            border: none;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #475569;
        }

        .table tbody tr:hover {
            background: #f8fafc;
            transform: translateX(2px);
            transition: all 0.2s ease;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .message-preview {
            cursor: pointer;
            color: #ea6118;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .message-preview:hover {
            color: #d1520e;
            text-decoration: underline;
        }

        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-delivered {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .status-failed {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .status-progress {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
        }

        /* OLD SYSTEM compatible status icons */
        .status-icon {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 14px;
            cursor: pointer;
            border: 2px solid;
        }

        .status-icon-delivered {
            background-color: #16a34a;
            border-color: #16a34a;
            color: white;
        }

        .status-icon-failed {
            background-color: #dc2626;
            border-color: #dc2626;
            color: white;
        }

        .status-icon-transit {
            background-color: transparent;
            border-color: #64748b;
            color: #64748b;
        }

        .status-icon-unknown {
            background-color: #f59e0b;
            border-color: #f59e0b;
            color: white;
        }

        .status-icon-pending {
            background-color: transparent;
            border-color: #f59e0b;
            color: #f59e0b;
        }

        /* Payment type icons (OLD SYSTEM: wallet vs premium) */
        .payment-type-icon {
            width: 20px;
            height: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 4px;
            cursor: pointer;
        }

        .payment-type-wallet {
            background-color: #3b82f6;
            color: white;
        }

        .payment-type-premium {
            background-color: #8b5cf6;
            color: white;
        }

        .pagination-container {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .pagination-controls {
            display: flex;
            justify-content: between;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .pagination-info {
            color: #64748b;
            font-weight: 500;
            text-align: center;
            flex: 1;
        }

        .pagination .page-link {
            color: #ea6118;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover {
            background: #ea6118;
            color: white;
            border-color: #ea6118;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border-color: #ea6118;
            color: white;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        /* Fix modal z-index issues - CRITICAL */
        .modal {
            z-index: 9999 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
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
        }

        .modal-content {
            pointer-events: auto !important;
        }

        .modal.show {
            display: block !important;
        }

        .modal-backdrop.show {
            opacity: 1 !important;
        }

        .modal-header {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-title {
            color: white !important;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 1.5rem;
        }

        .btn-close {
            filter: invert(1);
        }

        .stats-summary {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ea6118;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .no-data i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .export-button {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .export-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.4);
            color: white;
        }

        /*
                                        .action-buttons {
                                            display: flex;
                                            gap: 1rem;
                                            justify-content: flex-end;
                                            flex-wrap: wrap;
                                            margin-top: 2rem;
                                        } */

        .action-buttons {
            display: flex;
            gap: 1rem;
            /* justify-content: flex-end; */
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .info-icon {
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s ease;
            margin-left: 0.5rem;
        }

        .info-icon:hover {
            color: #ea6118;
            transform: scale(1.1);
        }

        .detail-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .detail-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .detail-table td:first-child {
            font-weight: 600;
            color: #293b50;
            width: 30%;
        }

        .detail-table tr:last-child td {
            border-bottom: none;
        }
    </style>
@endpush

@section('content')
    <div class="sentsms-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">send</i>
                    Sent SMS
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Sent SMS</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="alert alert-success" id="flash-message">
                <i class="material-icons-outlined">check_circle</i>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" id="flash-error-message">
                <i class="material-icons-outlined">error</i>
                {{ session('error') }}
            </div>
        @endif

        <!-- Main Content -->
        <div class="sentsms-card">
            <form id="perPageForm" action="{{ route('sentsms') }}" method="POST">
                @csrf

                <!-- Filters Section -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <i class="material-icons-outlined">filter_list</i>
                            Search & Filter Options
                        </h5>
                    </div>
                    <div class="section-content">

                        <div class="filter-grid">
                            <!-- SMS Source Filter -->
                            <div class="filter-section">
                                <div class="filter-title">
                                    <i class="material-icons-outlined">source</i>
                                    Show SMS Sent By
                                </div>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="chkitagg" id="chkitagg" class="form-check-input"
                                            {{ old('chkitagg', $chkitagg ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkitagg">
                                            <i class="material-icons-outlined">smart_toy</i>
                                            The SMS Expert System
                                        </label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="chkcontrolpanel" id="chkcontrolpanel"
                                            class="form-check-input"
                                            {{ old('chkcontrolpanel', $chkcontrolpanel ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkcontrolpanel">
                                            <i class="material-icons-outlined">dashboard</i>
                                            The Control Panel
                                        </label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="chkapi" id="chkapi" class="form-check-input"
                                            {{ old('chkapi', $chkapi ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkapi">
                                            <i class="material-icons-outlined">api</i>
                                            The SMS APIs
                                        </label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="chkemail" id="chkemail" class="form-check-input"
                                            {{ old('chkemail', $chkemail ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkemail">
                                            <i class="material-icons-outlined">email</i>
                                            Email XML→SMS Gateway
                                        </label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="chkmobyclip" id="chkmobyclip" class="form-check-input"
                                            {{ old('chkmobyclip', $chkmobyclip ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="chkmobyclip">
                                            <i class="material-icons-outlined">phone_android</i>
                                            Mobyclip
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Filters -->
                            <div class="filter-section">
                                <div class="filter-title">
                                    <i class="material-icons-outlined">tune</i>
                                    Advanced Filters
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">route</i>
                                        Routes
                                    </label>
                                    <select name="selRoutes" id="selRoutes" class="form-select">
                                        @php
                                            $routesOptions = [
                                                'all' => 'Standard and Premium Routes',
                                                'standard' => 'Standard Routes only',
                                                'premium' => 'Premium Routes only',
                                            ];
                                            $selectedRoute = $selRoutes ?? old('selRoutes', 'all');
                                        @endphp
                                        @foreach ($routesOptions as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ $selectedRoute === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">info</i>
                                        Delivery Status
                                    </label>
                                    <select name="selDelivery" id="selDelivery" class="form-select">
                                        @php
                                            $deliveryOptions = [
                                                'all' => 'All Messages',
                                                'delivered' => 'Delivered Messages only',
                                                'failed' => 'Failed Messages only',
                                                'pending' => 'Pending Messages only',
                                                'lost_notification' => 'Lost Notification',
                                            ];
                                        @endphp
                                        @foreach ($deliveryOptions as $value => $label)
                                            <option value="{{ $value }}"
                                                {{ old('selDelivery', $selDelivery ?? 'all') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">phone</i>
                                        Sent To (Mobile Number)
                                    </label>
                                    <input type="text" id="mobile" name="mobile" class="form-control"
                                        value="{{ $mobile ?? '' }}" placeholder="Enter mobile number...">
                                </div>

                                <div>
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">search</i>
                                        Match Message Log
                                        <i class="material-icons-outlined info-icon" data-bs-toggle="modal"
                                            data-bs-target="#Sentsms">help</i>
                                    </label>
                                    <input type="text" id="logMatch" name="logMatch" class="form-control"
                                        value="{{ $logMatch ?? '' }}" placeholder="Enter log text to match...">
                                </div>
                            </div>
                        </div>

                        {{-- <!-- Date Range Section -->
                        <div class="filter-section">
                            <div class="filter-title">
                                <i class="material-icons-outlined">date_range</i>
                                Date Range
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">event</i>
                                        Start Date & Time
                                    </label>
                                    <div class="time-inputs">
                                        <input type="date" class="form-control" name="start_date"
                                            value="{{ old('start_date', $start_date ?? '') }}">

                                        <select name="start_hh" class="form-select">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('start_hh', $start_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}:00
                                                </option>
                                            @endfor
                                        </select>

                                        <select name="start_mm" class="form-select">
                                            @for ($i = 0; $i < 60; $i += 5)
                                                @php $minute = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $minute }}"
                                                    {{ old('start_mm', $start_mm ?? '') === $minute ? 'selected' : '' }}>
                                                    :{{ $minute }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">event</i>
                                        End Date & Time
                                    </label>
                                    <div class="time-inputs">
                                        <input type="date" class="form-control" name="end_date"
                                            value="{{ old('end_date', $end_date ?? '') }}">

                                        <select name="end_hh" class="form-select">
                                            @for ($i = 0; $i <= 23; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('end_hh', $end_hh ?? '') == $hour ? 'selected' : '' }}>
                                                    {{ $hour }}:00
                                                </option>
                                            @endfor
                                        </select>

                                        <select name="end_mm" class="form-select">
                                            @for ($i = 0; $i <= 55; $i += 5)
                                                @php $minute = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $minute }}"
                                                    {{ old('end_mm', $end_mm ?? '') == $minute ? 'selected' : '' }}>
                                                    :{{ $minute }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div> --}}

                        <!-- Date Range Section -->
                        {{-- <div class="filter-section">
                            <div class="row g-3 align-items-end">
                                <!-- Start Date & Time -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">event</i>
                                        Start Date & Time
                                    </label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="date" class="form-control" name="start_date"
                                            value="{{ old('start_date', $start_date ?? '') }}" style="max-width: 160px;">
                                        <select name="start_hh" class="form-select" style="max-width: 70px;">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('start_hh', $start_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}
                                                </option>
                                            @endfor
                                        </select>
                                        <span class="text-muted">:</span>
                                        <select name="start_mm" class="form-select" style="max-width: 70px;">
                                            @for ($i = 0; $i < 60; $i += 5)
                                                @php $minute = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $minute }}"
                                                    {{ old('start_mm', $start_mm ?? '') === $minute ? 'selected' : '' }}>
                                                    {{ $minute }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <!-- End Date & Time -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">event</i>
                                        End Date & Time
                                    </label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="date" class="form-control" name="end_date"
                                            value="{{ old('end_date', $end_date ?? '') }}" style="max-width: 160px;">
                                        <select name="end_hh" class="form-select" style="max-width: 70px;">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('end_hh', $end_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}
                                                </option>
                                            @endfor
                                        </select>
                                        <span class="text-muted">:</span>
                                        <select name="end_mm" class="form-select" style="max-width: 70px;">
                                            @for ($i = 0; $i < 60; $i += 5)
                                                @php $minute = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $minute }}"
                                                    {{ old('end_mm', $end_mm ?? '') === $minute ? 'selected' : '' }}>
                                                    {{ $minute }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">touch_app</i>
                                        Actions
                                    </label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="submit" name="submit" value="search" id="submit"
                                            class="btn btn-primary">
                                            <i class="material-icons-outlined">search</i>
                                            Search Messages
                                        </button>

                                        <input type="hidden" name="export_url" value="{{ $export ?? '' }}"
                                            id="export_url">
                                        <button type="button" id="downloadCsvBtn" class="export-button"
                                            @if (!isset($data) || empty($data)) disabled style="opacity: 0.6; cursor: not-allowed;" @endif>
                                            <i class="material-icons-outlined">download</i>
                                            Export CSV
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div> --}}


                        <!-- Date Range & Actions - All in One Row -->
                        <div class="filter-section">
                            <div class="row g-3 align-items-end">
                                <!-- Start Date & Time -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">event</i>
                                        Start Date & Time
                                    </label>
                                    <div class="d-flex gap-1 align-items-center">
                                        <input type="date" class="form-control" name="start_date"
                                            value="{{ old('start_date', $start_date ?? '') }}" style="min-width: 150px;">
                                        <select name="start_hh" class="form-select" style="width: 65px;">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('start_hh', $start_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}
                                                </option>
                                            @endfor
                                        </select>
                                        <span>:</span>
                                        <select name="start_mm" class="form-select" style="width: 65px;">
                                            @for ($i = 0; $i < 60; $i += 5)
                                                @php $minute = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $minute }}"
                                                    {{ old('start_mm', $start_mm ?? '') === $minute ? 'selected' : '' }}>
                                                    {{ $minute }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <!-- End Date & Time -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold theme-label-color">
                                        <i class="material-icons-outlined">event</i>
                                        End Date & Time
                                    </label>
                                    <div class="d-flex gap-1 align-items-center">
                                        <input type="date" class="form-control" name="end_date"
                                            value="{{ old('end_date', $end_date ?? '') }}" style="min-width: 150px;">
                                        <select name="end_hh" class="form-select" style="width: 65px;">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('end_hh', $end_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}
                                                </option>
                                            @endfor
                                        </select>
                                        <span>:</span>
                                        <select name="end_mm" class="form-select" style="width: 65px;">
                                            @for ($i = 0; $i < 60; $i += 5)
                                                @php $minute = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $minute }}"
                                                    {{ old('end_mm', $end_mm ?? '') === $minute ? 'selected' : '' }}>
                                                    {{ $minute }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-md-4">
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="submit" value="search" class="btn btn-primary">
                                            <i class="material-icons-outlined">search</i>
                                            Search
                                        </button>
                                        <input type="hidden" name="export_url" value="{{ $export ?? '' }}" id="export_url">
                                        <button type="button" id="downloadCsvBtn" class="export-button"
                                            @if (!isset($data) || empty($data)) disabled style="opacity: 0.6; cursor: not-allowed;" @endif>
                                            <i class="material-icons-outlined">download</i>
                                            Export CSV
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Results Section -->
                @if (isset($data) && !empty($data))
                    <!-- Statistics Summary -->
                    <div class="stats-summary">
                        <div class="stats-number">{{ number_format($totalRecords ?? 0) }}</div>
                        <div class="stats-label">Total Sent Messages</div>
                    </div>

                    <!-- Pagination Controls (Top) -->
                    <div class="pagination-container">
                        <div class="pagination-controls">
                            <!-- Page Selection -->
                            <div>
                                <label for="selPage" class="form-label">
                                    <i class="material-icons-outlined">pages</i>
                                    Page:
                                </label>
                                <select id="selPage" onchange="submitPageForm()" class="form-select"
                                    style="width: auto; display: inline-block;">
                                    @for ($i = 1; $i <= ($totalPages ?? 1); $i++)
                                        <option value="{{ $i }}"
                                            {{ $i == ($currentPage ?? 1) ? 'selected' : '' }}>
                                            {{ $i }} of {{ $totalPages ?? 1 }}
                                        </option>
                                    @endfor
                                </select>
                                <input type="hidden" name="perPage" value="{{ $perPage ?? 20 }}">
                            </div>

                            <!-- Records Info -->
                            <div class="pagination-info">
                                <i class="material-icons-outlined">info</i>
                                Viewing {{ (($currentPage ?? 1) - 1) * ($perPage ?? 20) + 1 }} to
                                {{ min(($currentPage ?? 1) * ($perPage ?? 20), $totalRecords ?? 0) }}
                                of {{ number_format($totalRecords ?? 0) }} messages
                            </div>

                            <!-- Records per Page -->
                            <div>
                                <label for="perPage" class="form-label">
                                    <i class="material-icons-outlined">view_list</i>
                                    Per Page:
                                </label>
                                <select name="perPage" id="perPage" onchange="submitPerPageForm()" class="form-select"
                                    style="width: auto; display: inline-block;">
                                    @foreach ($perPageOptions ?? [20, 50, 100] as $option)
                                        <option value="{{ $option }}"
                                            {{ $option == ($perPage ?? 20) ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="page" value="1">
                            </div>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="data-table">
                        <table class="table">
                            <thead>
                                <tr class="text-center">
                                    <th>
                                        <i class="material-icons-outlined">phone</i>
                                        To
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">message</i>
                                        Message
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">schedule</i>
                                        Sent
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">person</i>
                                        By
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">info</i>
                                        Status
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">visibility</i>
                                        View
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $record)
                                    @php
                                        $thetimestr = $record['timesent'];
                                        $nicedate =
                                            $thetimestr > 0
                                                ? date(
                                                    'jS M Y H:i',
                                                    mktime(
                                                        substr($thetimestr, 8, 2),
                                                        substr($thetimestr, 10, 2),
                                                        substr($thetimestr, 12, 2),
                                                        substr($thetimestr, 4, 2),
                                                        substr($thetimestr, 6, 2),
                                                        substr($thetimestr, 0, 4),
                                                    ),
                                                )
                                                : 'In progress';
                                        $delivery_status = getDeliveryStatus($record, $statusIndicator_byref);
                                        $tableName = $record['table_name'];
                                        $id = $record['id'];

                                        // Determine status badge class
                                        $statusClass = 'status-progress';
                                        if (stripos($statusIndicator_byref, 'delivered') !== false) {
                                            $statusClass = 'status-delivered';
                                        } elseif (
                                            stripos($statusIndicator_byref, 'failed') !== false ||
                                            stripos($statusIndicator_byref, 'error') !== false
                                        ) {
                                            $statusClass = 'status-failed';
                                        } elseif (stripos($statusIndicator_byref, 'pending') !== false) {
                                            $statusClass = 'status-pending';
                                        }
                                    @endphp
                                    <tr class="text-center">
                                        <td>
                                            <strong>{{ $record['mobnum'] ?? 'Unknown' }}</strong>
                                        </td>
                                        @php
                                            $filterParams = http_build_query(request()->except(['_token']));
                                            $detailUrl = route('sentsms.show', ['tableName' => $tableName, 'id' => $id]) . ($filterParams ? '?' . $filterParams : '');
                                        @endphp
                                        <td>
                                            <a href="{{ $detailUrl }}"
                                               class="message-preview" style="text-decoration: none;">
                                                {{ Str::limit(decodeSmsTextForDisplay($record['text'] ?? ''), 50) }}
                                            </a>
                                        </td>
                                        {{-- <td>
                                            <i class="material-icons-outlined">access_time</i>
                                            {{ $nicedate }}
                                        </td> --}}
                                        <td>
                                            @php
                                                // Use timesent (actual send time) instead of deliverytime2 (delivery receipt time)
                                                // timesent is stored in Europe/London timezone, format: YYYYMMDDHHmmss
                                                $timesent = $record['timesent'] ?? null;
                                                if ($timesent && $timesent !== '00000000000000' && strlen($timesent) >= 14) {
                                                    // Parse the YYYYMMDDHHmmss format
                                                    $sentTime = \Carbon\Carbon::createFromFormat('YmdHis', $timesent, 'Europe/London');
                                                    // No seconds in the list "Sent" column (minute precision).
                                                    $sentTimeFormatted = $sentTime->format('D jS M y H:i');
                                                } else {
                                                    $sentTimeFormatted = 'N/A';
                                                }
                                            @endphp
                                            {{ $sentTimeFormatted }}
                                        </td>

                                        <td>
                                            {{ sanitiseStringForUserDisplay($record['initiator'] ?? 'System') }}
                                        </td>
                                        @php
                                            // OLD SYSTEM compatible status display
                                            // Status indicators: DELIVERED, SENT, FAILED, IN TRANSIT, LOST_NOTIFICATION
                                            $deliverystatus2 = $record['deliverystatus2'] ?? '';
                                            $sentstatus = $record['sentstatus'] ?? '';
                                            $requestedRoute = $record['requested_route'] ?? 0;

                                            // Determine status using OLD SYSTEM logic
                                            // Default: In Transit - empty circle
                                            $statusIcon = 'status-icon-transit';
                                            $statusIconMaterial = ''; // Empty circle for transit
                                            $statusTooltip = 'This message is currently in transit';
                                            $statusLabel = 'In Transit';

                                            // Was it a premium rate message that has been sent?
                                            if ($requestedRoute >= 10000 && $sentstatus == 'ok') {
                                                $statusIcon = 'status-icon-unknown';
                                                $statusIconMaterial = 'help';
                                                $statusTooltip = 'Sent, but unknown delivery';
                                                $statusLabel = 'Sent';
                                            }

                                            // Check for pending
                                            if ($sentstatus == 'pending' || empty($deliverystatus2)) {
                                                $statusIcon = 'status-icon-pending';
                                                $statusIconMaterial = ''; // Empty circle for pending
                                                $statusTooltip = 'This message is pending';
                                                $statusLabel = 'Pending';
                                            }

                                            // Check for failure - Red circle with X
                                            // OLD SYSTEM uses 'Non Delivered' (capital letters), also check lowercase for backward compatibility
                                            if ($sentstatus == 'fail' || $deliverystatus2 == 'Non Delivered' || strtolower($deliverystatus2) == 'failed') {
                                                $statusIcon = 'status-icon-failed';
                                                $statusIconMaterial = 'close';
                                                $statusTooltip = 'This message failed';
                                                $statusLabel = 'Failed';
                                            }

                                            // Check for success/delivery - Green circle with tick
                                            // OLD SYSTEM uses 'Delivered' (capital D), also check lowercase for backward compatibility
                                            if ($deliverystatus2 == 'Delivered' || strtolower($deliverystatus2) == 'delivered') {
                                                $statusIcon = 'status-icon-delivered';
                                                $statusIconMaterial = 'check';
                                                $statusTooltip = 'This message was successfully delivered';
                                                $statusLabel = 'Delivered';
                                            }

                                            // Check for lost notification
                                            if ($deliverystatus2 == 'Lost Notification') {
                                                $statusIcon = 'status-icon-unknown';
                                                $statusIconMaterial = 'help';
                                                $statusTooltip = 'Unknown Delivery - notification was unavailable';
                                                $statusLabel = 'Lost Notification';
                                            }

                                            // Catch-all for a non-empty deliverystatus2 the cases above didn't handle.
                                            // OLD SYSTEM parity: 'Unknown' (reason code 6 = final status unknown),
                                            // 'acked' and the 'buffered*' states are NOT final — OLD shows these as
                                            // "In Transit". So keep the "In Transit" label (with a visible icon, never
                                            // blank), and only show the raw value for a genuinely unrecognised FINAL state.
                                            if ($statusLabel === 'In Transit' && !empty($deliverystatus2)) {
                                                if (deliveryStatus2DisplayLabel($deliverystatus2) === 'In Transit') {
                                                    // Non-final -> stays "In Transit" (matches OLD), with a visible icon.
                                                    $statusIcon = 'status-icon-pending';
                                                    $statusIconMaterial = 'schedule';
                                                    $statusTooltip = 'In transit - final delivery status not yet known (' . $deliverystatus2 . ')';
                                                    $statusLabel = 'In Transit';
                                                } else {
                                                    // Genuinely unrecognised FINAL status -> show it (never blank).
                                                    $statusIcon = 'status-icon-unknown';
                                                    $statusIconMaterial = 'help';
                                                    $statusTooltip = 'Delivery status: ' . ucfirst($deliverystatus2);
                                                    $statusLabel = ucfirst($deliverystatus2);
                                                }
                                            }

                                            // Payment type indicator (OLD SYSTEM: wallet vs premium)
                                            // Routes < 10000 are standard/wallet, >= 10000 are premium
                                            if ($requestedRoute < 10000) {
                                                $paymentTypeClass = 'payment-type-wallet';
                                                $paymentTypeIcon = 'account_balance_wallet';
                                                $paymentTypeTooltip = 'Wallet payment SMS message type';
                                            } else {
                                                $paymentTypeClass = 'payment-type-premium';
                                                $paymentTypeIcon = 'star';
                                                $paymentTypeTooltip = 'Premium rate message type';
                                            }
                                        @endphp

                                        <td>
                                            <span class="status-icon {{ $statusIcon }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $statusTooltip }}">
                                                @if($statusIconMaterial)
                                                    <i class="material-icons-outlined" style="font-size: 14px; pointer-events: none;">{{ $statusIconMaterial }}</i>
                                                @endif
                                            </span>
                                            <span class="payment-type-icon {{ $paymentTypeClass }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $paymentTypeTooltip }}">
                                                <i class="material-icons-outlined" style="font-size: 12px; pointer-events: none;">{{ $paymentTypeIcon }}</i>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ $detailUrl }}"
                                               class="btn btn-sm btn-outline-primary" style="border-radius: 8px;"
                                               data-bs-toggle="tooltip" title="View Details">
                                                <i class="material-icons-outlined" style="font-size: 16px;">visibility</i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Pagination -->
                    <div class="pagination-container">
                        <div class="d-flex justify-content-between align-items-center">

                            <div class="pagination-info">
                                Page {{ $currentPage ?? 1 }} of {{ $totalPages ?? 1 }}
                            </div>

                            <nav>
                                <ul class="pagination">

                                    @php
                                        $start = max(1, ($currentPage ?? 1) - 2);
                                        $end = min($totalPages ?? 1, ($currentPage ?? 1) + 2);
                                    @endphp

                                    @if ($start > 1)
                                        <li class="page-item">
                                            <a href="javascript:void(0)" onclick="submitPagination(1)"
                                                class="page-link">1</a>
                                        </li>
                                    @endif

                                    @for ($i = $start; $i <= $end; $i++)
                                        <li class="page-item {{ $i == ($currentPage ?? 1) ? 'active' : '' }}">
                                            <a href="javascript:void(0)" onclick="submitPagination({{ $i }})"
                                                class="page-link">
                                                {{ $i }}
                                            </a>
                                        </li>
                                    @endfor

                                    @if ($end < ($totalPages ?? 1))
                                        <li class="page-item">
                                            <a href="javascript:void(0)"
                                                onclick="submitPagination({{ $totalPages ?? 1 }})" class="page-link">
                                                {{ $totalPages ?? 1 }}
                                            </a>
                                        </li>
                                    @endif

                                </ul>
                            </nav>

                        </div>
                    </div>


                    {{-- <div class="pagination-container">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="pagination-info">
                                Page {{ $currentPage ?? 1 }} of {{ $totalPages ?? 1 }}
                            </div>

                            <nav>
                                <ul class="pagination">
                                    @php
                                        $start = max(1, ($currentPage ?? 1) - 2);
                                        $end = min($totalPages ?? 1, ($currentPage ?? 1) + 2);
                                    @endphp

                                    @if ($start > 1)
                                        <li class="page-item">
                                                <a href="#" onclick="submitPagination(1)" class="page-link">1</a>
                                        </li>
                                        @if ($start > 2)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                    @endif

                                    @for ($i = $start; $i <= $end; $i++)
                                        <li class="page-item {{ $i == ($currentPage ?? 1) ? 'active' : '' }}">
                                            <a href="#" onclick="submitPagination({{ $i }})"
                                                class="page-link">{{ $i }}</a>
                                        </li>
                                    @endfor

                                    @if ($end < ($totalPages ?? 1))
                                        @if ($end < ($totalPages ?? 1) - 1)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                        <li class="page-item">
                                            <a href="#" class="page-link"
                                                onclick="submitPagination({{ $totalPages ?? 1 }})">{{ $totalPages ?? 1 }}</a>
                                        </li>
                                    @endif
                                </ul>
                            </nav>
                        </div>
                    </div> --}}
                @else
                    <!-- No Data State -->
                    <div class="section-card">
                        <div class="no-data">
                            <i class="material-icons-outlined">outbox</i>
                            <h4>No Messages Found</h4>
                            <p>No sent SMS messages match your search criteria. Try adjusting your filters or date range.
                            </p>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <!-- SMS Details Modal -->
    <div class="modal fade" id="Sentsmsdetails" tabindex="-1" aria-hidden="true" data-bs-backdrop="true"
        data-bs-keyboard="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined">info</i>
                        Sent SMS Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="detail-table">
                        <tr>
                            <td><i class="material-icons-outlined">schedule</i> Date Submitted</td>
                            <td id="Date_Submitted"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">send</i> Send at Time</td>
                            <td id="Send_at_time"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">done</i> Date Finalised</td>
                            <td id="Date_Finalised"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">person</i> Sender</td>
                            <td id="Sender"></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top: 1.5rem;">
                                <strong><i class="material-icons-outlined">message</i> Message Content</strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div id="Message"
                                    style="background: #f8fafc; padding: 1rem; border-radius: 8px; border-left: 4px solid #ea6118;">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">phone</i> Sent To</td>
                            <td id="Sent_To"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">source</i> Sent By</td>
                            <td id="Sent_By"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">paid</i> Recipient Cost</td>
                            <td id="Recipient_Cost"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">payment</i> Cost to You</td>
                            <td id="Cost_to_You"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">done</i> Delivery Status</td>
                            <td id="Delivery_Status" style="text-transform: uppercase;"></td>
                        </tr>
                        <tr id="Upstream_Reason_Row" style="display:none;">
                            <td><i class="material-icons-outlined">report</i> Reason</td>
                            <td id="Upstream_Reason" style="font-style: italic;"></td>
                        </tr>
                        <tr>
                            <td><i class="material-icons-outlined">info</i> Message Status</td>
                            <td id="Message_Status" style="text-transform: uppercase;"></td>
                        </tr>
                        <tr id="User_Defined_Row" style="display:none;">
                            <td><i class="material-icons-outlined">edit_note</i> User defined log info</td>
                            <td id="User_Defined" style="word-break: break-all;"></td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined">close</i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Modal -->
    <div class="modal fade" id="Sentsms" tabindex="-1" aria-hidden="true" data-bs-backdrop="true"
        data-bs-keyboard="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined">help</i>
                        Message Log Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold mb-3">Match Message Log Form Element</h6>

                    <p>When you send SMS messages via the iTAGG APIs you can specify a log value for the parameter
                        "userdef". It has no effect on the message itself but it allows you to group messages together.</p>

                    <div class="alert alert-info">
                        <strong>Log Format:</strong> The log text MUST start with the word "log" followed by a space
                        followed by the text you wish to use to log the message. The log text must be URL encoded.
                    </div>

                    <p>Text entered into this field will be used to partially match your log text, so typing
                        <strong>client1</strong> into this box will match your log text of <strong>log this message was sent
                            on behalf of client1.</strong>
                    </p>

                    <p>See the API documentation in the Downloads area for full details.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined">close</i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            setTimeout(function() {
                const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
                flashMessages.forEach(msg => {
                    if (msg) {
                        msg.style.opacity = '0';
                        msg.style.transform = 'translateY(-20px)';
                        setTimeout(() => msg.style.display = 'none', 300);
                    }
                });
            }, 4000);

            // CSV Download functionality
            const downloadBtn = document.getElementById('downloadCsvBtn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const csvUrl = document.getElementById('export_url').value;
                    if (csvUrl) {
                        const a = document.createElement('a');
                        a.href = csvUrl;
                        a.download = csvUrl.split('/').pop();
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }
                });
            }

            // Smooth animations
            const cards = document.querySelectorAll('.section-card, .sentsms-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Initialize Bootstrap tooltips for status and payment type icons
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        function submitPageForm() {
            const page = document.getElementById('selPage').value;
            submitPagination(page);
        }

        function submitPerPageForm() {
            const form = document.getElementById('perPageForm');

            let pageInput = form.querySelector('input[name="page"]');
            if (pageInput) pageInput.value = 1;

            form.submit();
        }

        function submitPagination(page) {
            const form = document.getElementById('perPageForm');

            let pageInput = form.querySelector('input[name="page"]');

            if (!pageInput) {
                pageInput = document.createElement('input');
                pageInput.type = 'hidden';
                pageInput.name = 'page';
                form.appendChild(pageInput);
            }

            pageInput.value = page;

            form.submit(); 
        }



        // function submitPageForm() {
        //     const value = document.getElementById('selPage').value;
        //     submitPagination(value);
        // }

        // function submitPerPageForm() {
        //     document.getElementById('submit').click();
        // }

        // function submitPagination(page) {
        //     const form = document.getElementById('perPageForm');
        //     const pageInput = document.querySelector('input[name="page"]');
        //     if (pageInput) {
        //         pageInput.value = page;
        //     } else {
        //         const hiddenInput = document.createElement('input');
        //         hiddenInput.type = 'hidden';
        //         hiddenInput.name = 'page';
        //         hiddenInput.value = page;
        //         form.appendChild(hiddenInput);
        //     }
        //     document.getElementById('submit').click();
        // }

        function showDetailsPage(tableName, id) {
            const modal = new bootstrap.Modal(document.getElementById('Sentsmsdetails'));
            modal.show();

            const fields = ['Date_Submitted', 'Send_at_time', 'Date_Finalised', 'Sender', 'Message', 'Sent_To', 'Sent_By',
                'Recipient_Cost', 'Cost_to_You', 'Delivery_Status', 'Message_Status', 'Delivery_Time',
                'Upstream_Reason', 'User_Defined'
            ];
            fields.forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    element.innerHTML = '<i class="loading-spinner"></i> Loading...';
                }
            });

            fetch('{{ route('sentSmsDetails') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        tableName: tableName,
                        id: id
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Mapping for Delivery Status
                    // const statusMap = {
                    //     'pending': 'Pending',
                    //     'ok': 'Completed',
                    //     'fail': 'Failed',
                    //     'tomorrowonward': 'Scheduled'
                    // };

                    const statusMap = {
                        'pending': 'Pending',
                        'tomorrowonward': 'Scheduled',
                        'accepted': 'Accepted',
                        'delivered': 'Delivered',
                        'buffered': 'Buffered',
                        'expired': 'Expired',
                        'failed': 'Failed',
                        'rejected': 'Rejected',
                        'unknown': 'Unknown'
                    };

                    const rawStatus = data.Delivery_Status || '';
                    const mappedStatus = statusMap[rawStatus] || (rawStatus.charAt(0).toUpperCase() + rawStatus.slice(
                        1));

                    const deliveryEl = document.getElementById('Delivery_Status');
                    if (deliveryEl) {
                        deliveryEl.textContent = mappedStatus;
                    }

                    // Fill other fields
                    const otherFields = ['Date_Submitted', 'Send_at_time', 'Date_Finalised', 'Sender', 'Message',
                        'Sent_To', 'Sent_By', 'Message_Status', 'Delivery_Time'
                    ];
                    otherFields.forEach(field => {
                        const el = document.getElementById(field);
                        if (el) {
                            el.textContent = data[field] || 'N/A';
                        }
                    });

                    // Reason — shown under Delivery Status only when upstream_errormessage is set.
                    // Wrapped in single quotes to match OLD SYSTEM "Reason: '...'" format.
                    const reasonEl = document.getElementById('Upstream_Reason');
                    const reasonRow = document.getElementById('Upstream_Reason_Row');
                    if (data.Upstream_Reason && String(data.Upstream_Reason).trim() !== '') {
                        if (reasonEl) reasonEl.textContent = "'" + data.Upstream_Reason + "'";
                        if (reasonRow) reasonRow.style.display = '';
                    } else {
                        if (reasonRow) reasonRow.style.display = 'none';
                    }

                    // User defined log info — only show row when there's something to display.
                    const userdefEl = document.getElementById('User_Defined');
                    const userdefRow = document.getElementById('User_Defined_Row');
                    if (data.User_Defined && String(data.User_Defined).trim() !== '') {
                        if (userdefEl) userdefEl.textContent = data.User_Defined;
                        if (userdefRow) userdefRow.style.display = '';
                    } else {
                        if (userdefRow) userdefRow.style.display = 'none';
                    }

                    // Convert cost fields from pounds to pence (OLD SYSTEM format)
                    const costFields = ['Recipient_Cost', 'Cost_to_You'];
                    costFields.forEach(field => {
                        const el = document.getElementById(field);
                        if (el && data[field]) {
                            // Extract numeric value from string like "£ 0.0749"
                            const costStr = data[field].toString().replace(/[£\s]/g, '');
                            const costInPounds = parseFloat(costStr);
                            if (!isNaN(costInPounds)) {
                                const costInPence = (costInPounds * 100).toFixed(2);
                                el.textContent = costInPence + 'p';
                            } else {
                                el.textContent = data[field] || 'N/A';
                            }
                        } else if (el) {
                            el.textContent = 'N/A';
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading SMS details:', error);
                    fields.forEach(field => {
                        const element = document.getElementById(field);
                        if (element) {
                            element.textContent = 'Error loading data';
                        }
                    });
                });
        }


        // function showDetailsPage(tableName, id) {
        //     const modal = new bootstrap.Modal(document.getElementById('Sentsmsdetails'));
        //     modal.show();

        //     // Show loading state
        //     const fields = ['Date_Submitted', 'Send_at_time', 'Date_Finalised', 'Sender', 'Message', 'Sent_To', 'Sent_By',
        //         'Recipient_Cost', 'Cost_to_You', 'Delivery_Status'
        //     ];
        //     fields.forEach(field => {
        //         document.getElementById(field).innerHTML = '<i class="loading-spinner"></i> Loading...';
        //     });

        //     fetch('{{ route('sentSmsDetails') }}', {
        //             method: 'POST',
        //             headers: {
        //                 'Content-Type': 'application/json',
        //                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        //             },
        //             body: JSON.stringify({
        //                 tableName: tableName,
        //                 id: id
        //             })
        //         })
        //         .then(response => response.json())
        //         .then(data => {
        //             document.getElementById('Date_Submitted').textContent = data.Date_Submitted || 'N/A';
        //             document.getElementById('Send_at_time').textContent = data.Send_at_time || 'N/A';
        //             document.getElementById('Date_Finalised').textContent = data.Date_Finalised || 'N/A';
        //             document.getElementById('Sender').textContent = data.Sender || 'N/A';
        //             document.getElementById('Message').textContent = data.Message || 'N/A';
        //             document.getElementById('Sent_To').textContent = data.Sent_To || 'N/A';
        //             document.getElementById('Sent_By').textContent = data.Sent_By || 'N/A';
        //             document.getElementById('Recipient_Cost').textContent = data.Recipient_Cost || 'N/A';
        //             document.getElementById('Cost_to_You').textContent = data.Cost_to_You || 'N/A';
        //             document.getElementById('Delivery_Status').textContent = data.Delivery_Status || 'N/A';
        //         })
        //         .catch(error => {
        //             console.error('Error loading SMS details:', error);
        //             fields.forEach(field => {
        //                 document.getElementById(field).textContent = 'Error loading data';
        //             });
        //         });
        // }

        // console.log('Sent SMS page loaded successfully!');

        // Move modals to body level to fix z-index issues
        document.querySelectorAll('.modal').forEach(function(modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });

        // Fix modal backdrop
        document.addEventListener('show.bs.modal', function(event) {
            // Clean up any stray backdrops
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());
        });

        document.addEventListener('hidden.bs.modal', function(event) {
            // Remove all backdrops when modal closes
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    </script>
@endpush
