@extends('layouts.app')
@section('title', 'Received SMS - SMS Expert')

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

        .receivedsms-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .receivedsms-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .receivedsms-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .receivedsms-card:hover {
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

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
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

        .filter-section {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
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

        /* .action-buttons {
                    display: flex;
                    gap: 1rem;
                    justify-content: flex-end;
                    flex-wrap: wrap;
                } */
        .action-buttons {
            display: flex;
            gap: 1rem;
            /* justify-content: flex-end; */
            flex-wrap: wrap;
        }
    </style>
@endpush

@section('content')
    <div class="receivedsms-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">mail</i>
                    Received SMS
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Received SMS</li>
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
        <div class="receivedsms-card">
            <form id="perPageForm" action="{{ route('received-sms') }}" method="POST">
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

                        <!-- All Filters in One Row: Keyword, Start Date, End Date, Actions -->
                        <div class="filter-section">
                            <div class="d-flex gap-2 align-items-end flex-nowrap" style="overflow-x: auto;">
                                <!-- Keyword & Virtual Number -->
                                <div class="flex-shrink-0">
                                    <label class="form-label fw-semibold theme-label-color mb-1" style="font-size: 0.85rem;">
                                        Keyword & Virtual Number
                                    </label>
                                    <select name="selectedtagg" id="selectedtagg" class="form-select form-select-sm" style="min-width: 180px;">
                                        <option value="All Incoming">All Incoming Messages</option>
                                        @foreach ($itaggs as $itagg)
                                            <option value="{{ $itagg['id'] }}"
                                                {{ $itagg['id'] == ($selectedtagg ?? '') ? 'selected' : '' }}>
                                                {{ $itagg['keyword'] }} ({{ $itagg['number'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Start Date & Time -->
                                <div class="flex-shrink-0">
                                    <label class="form-label fw-semibold theme-label-color mb-1" style="font-size: 0.85rem;">
                                        Start Date & Time
                                    </label>
                                    <div class="d-flex gap-1 align-items-center">
                                        <input type="date" class="form-control form-control-sm" name="start_date"
                                            value="{{ old('start_date', $start_date ?? '') }}" style="width: 140px;">
                                        <select name="start_hh" class="form-select form-select-sm" style="width: 58px;">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('start_hh', $start_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}
                                                </option>
                                            @endfor
                                        </select>
                                        <span>:</span>
                                        <select name="start_mm" class="form-select form-select-sm" style="width: 58px;">
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
                                <div class="flex-shrink-0">
                                    <label class="form-label fw-semibold theme-label-color mb-1" style="font-size: 0.85rem;">
                                        End Date & Time
                                    </label>
                                    <div class="d-flex gap-1 align-items-center">
                                        <input type="date" class="form-control form-control-sm" name="end_date"
                                            value="{{ old('end_date', $end_date ?? '') }}" style="width: 140px;">
                                        <select name="end_hh" class="form-select form-select-sm" style="width: 58px;">
                                            @for ($i = 0; $i < 24; $i++)
                                                @php $hour = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                                <option value="{{ $hour }}"
                                                    {{ old('end_hh', $end_hh ?? '') === $hour ? 'selected' : '' }}>
                                                    {{ $hour }}
                                                </option>
                                            @endfor
                                        </select>
                                        <span>:</span>
                                        <select name="end_mm" class="form-select form-select-sm" style="width: 58px;">
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
                                <div class="d-flex gap-2 align-items-end flex-shrink-0">
                                    <button type="submit" value="search" class="btn btn-primary btn-sm">
                                        <i class="material-icons-outlined" style="font-size: 16px;">search</i>
                                        Search
                                    </button>
                                    <input type="hidden" name="export_url" value="{{ $export ?? '' }}" id="export_url">
                                    <button type="button" id="downloadCsvBtn" class="export-button btn-sm"
                                        @if (!isset($data) || empty($data)) disabled style="opacity: 0.6; cursor: not-allowed;" @endif>
                                        <i class="material-icons-outlined" style="font-size: 16px;">download</i>
                                        Export
                                    </button>
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
                        <div class="stats-label">Total Received Messages</div>
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
                                <tr>
                                    <th>
                                        <i class="material-icons-outlined">person</i>
                                        From
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">message</i>
                                        Message
                                    </th>
                                    <th>
                                        <i class="material-icons-outlined">schedule</i>
                                        Date Received
                                    </th>
                                    <th class="text-center">
                                        <i class="material-icons-outlined">reply</i>
                                        Auto-Response
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $record)
                                    <tr>
                                        <td>
                                            <strong>{{ $record['source'] ?? 'Unknown' }}</strong>
                                        </td>
                                        <td>
                                            <span class="message-preview"
                                                onclick="showDetailsPage('{{ $record['id'] ?? '' }}','{{ $record['recieved'] ?? '' }}','{{ $record['source'] ?? '' }}','{{ $record['dest'] ?? '' }}','{{ $record['msg'] ?? '' }}')">
                                                {{ Str::limit(urldecode($record['msg'] ?? ''), 50) }}
                                            </span>
                                        </td>
                                        <td>
                                            <i class="material-icons-outlined">access_time</i>
                                            {{ $record['recieved'] ?? 'Unknown' }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">
                                                <i class="material-icons-outlined">close</i>
                                                No SMS sent
                                            </span>
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
                                        @if ($start > 2)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
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
                                        @if ($end < ($totalPages ?? 1) - 1)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
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
                            <i class="material-icons-outlined">inbox</i>
                            <h4>No Messages Found</h4>
                            <p>No received SMS messages match your search criteria. Try adjusting your filters or date
                                range.</p>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <!-- SMS Details Modal -->
    <div class="modal fade" id="Sentsmsdetails" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined">info</i>
                        Received SMS Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <strong>
                                <i class="material-icons-outlined">schedule</i>
                                Date Received:
                            </strong>
                            <div id="Date_Submitted" class="mt-1"></div>
                        </div>
                        <div class="col-md-6">
                            <strong>
                                <i class="material-icons-outlined">person</i>
                                Sender:
                            </strong>
                            <div id="Send_at_time" class="mt-1"></div>
                        </div>
                        <div class="col-md-12">
                            <strong>
                                <i class="material-icons-outlined">phone</i>
                                Sent to Number:
                            </strong>
                            <div id="Date_Finalised" class="mt-1"></div>
                        </div>
                        <div class="col-md-12">
                            <strong>
                                <i class="material-icons-outlined">message</i>
                                Inbound SMS Message:
                            </strong>
                            <div id="Message1" class="mt-2 p-3 bg-light rounded"></div>
                        </div>
                        <div class="col-md-12">
                            <strong>
                                <i class="material-icons-outlined">reply</i>
                                Auto-Response SMS:
                            </strong>
                            <div id="Message2" class="mt-2 p-3 bg-light rounded text-muted">
                                (No auto-response SMS was sent)
                            </div>
                        </div>
                    </div>
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
    <div class="modal fade" id="Sentsms" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
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

                    <p>Text entered into the search field will be used to partially match your log text, so typing
                        <strong>client1</strong> will match your log text of <strong>log this message was sent on behalf of
                            client1.</strong>
                    </p>
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
            const cards = document.querySelectorAll('.section-card, .receivedsms-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });


        function submitPageForm() {
            const value = document.getElementById('selPage').value;
            submitPagination(value);
        }

        function submitPerPageForm() {
            document.getElementById('perPageForm').submit();
        }

        function submitPagination(page) {
            const form = document.getElementById('perPageForm');

            // search inside form only (safer)
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
        //     document.getElementById('perPageForm').submit();
        // }

        // function submitPagination(page) {
        //     const form = document.getElementById('perPageForm');
        //     const pageInput = document.querySelector('input[name="page"]');
        //     if (pageInput) {
        //         pageInput.value = page;
        //     }
        //     form.submit();
        // }

        function showDetailsPage(id, date, source, dest, msg) {
            document.getElementById('Date_Submitted').textContent = date;
            document.getElementById('Send_at_time').textContent = source;
            document.getElementById('Date_Finalised').textContent = dest;
            document.getElementById('Message1').textContent = decodeURIComponent(msg);

            const modal = new bootstrap.Modal(document.getElementById('Sentsmsdetails'));
            modal.show();
        }

        console.log('Received SMS page loaded successfully!');
    </script>
@endpush
