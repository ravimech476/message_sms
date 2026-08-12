@extends('admin.layouts.app')
@section('title')
    {{ __('CRM') }}
@endsection
@push('style')
    <style>
        .button19 {
            background-color: green;
            color: white;
            font-size: 12px;
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
        }

        .search-form .form-control {
            width: 280px;
            height: 38px;
        }

        .search-form .btn {
            height: 38px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .right-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .right-controls .form-select {
            width: 150px;
            height: 38px;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            background-color: #e9ecef;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
        }

        .pagination {
            margin-bottom: 0;
        }

        .page-link {
            padding: 0.375rem 0.75rem;
        }

        @media (max-width: 992px) {
            .table-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-form .form-control {
                width: 100%;
            }

            .search-form .btn {
                width: 100%;
                justify-content: center;
            }

            .right-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .right-controls .form-select {
                width: 100%;
            }

            .pagination-info {
                justify-content: center;
            }
        }

        @media (min-width: 993px) {
            .search-form {
                flex-wrap: nowrap;
            }
        }
    </style>
@endpush

@section('content')
    <!--start main wrapper-->
    <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">
                    Customers
                    @if(($filter ?? 'migrated') === 'migrated')
                        <small style="color: white;">(Migrated)</small>
                    @else
                        <small style="color: white;">(Pending Migration)</small>
                    @endif
                </div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item active" aria-current="page"><a
                                    href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Customers</li>
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
            <div class="card">
                <div class="card-body">
                    <div class="col-12 mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-auto">
                                <a href="{{ route('admin.customer.create') }}">
                                    <button class="btn btn-primary w-100">Add Customer</button>
                                </a>
                            </div>
                            <div class="col-12 col-md-auto">
                                <div class="btn-group" role="group" aria-label="Migration filter">
                                    <a href="{{ route('admin.users', ['filter' => 'migrated', 'per_page' => $perPage]) }}"
                                       class="btn {{ ($filter ?? 'migrated') === 'migrated' ? 'btn-success' : 'btn-outline-success' }}">
                                        <i class="bx bx-check-circle"></i> Migrated
                                        <span class="badge bg-white text-success">{{ $migratedCount ?? 0 }}</span>
                                    </a>
                                    <a href="{{ route('admin.users', ['filter' => 'not_migrated', 'per_page' => $perPage]) }}"
                                       class="btn {{ ($filter ?? 'migrated') === 'not_migrated' ? 'btn-warning' : 'btn-outline-warning' }}">
                                        <i class="bx bx-transfer-alt"></i> Migrate
                                        <span class="badge bg-white text-warning">{{ $notMigratedCount ?? 0 }}</span>
                                    </a>
                                </div>
                            </div>
                            <div class="col-12 col-md-auto">
                                @if(($filter ?? 'migrated') === 'migrated')
                                    <span class="badge bg-success fs-6"><i class="bx bx-check-circle"></i> Showing Migrated Customers</span>
                                @else
                                    <span class="badge bg-warning text-dark fs-6"><i class="bx bx-transfer-alt"></i> Showing Non-Migrated Customers</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Search and Per Page Controls -->
                    <div class="table-controls">
                        <form action="{{ route('admin.users') }}" method="GET" class="search-form" id="searchForm">
                            <input type="text" name="search" class="form-control"
                                placeholder="Search by business name,contact name,email,username (min 5 characters)" value="{{ $search ?? '' }}">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-search"></i> Search
                            </button>
                            @if (!empty($search))
                                <a href="{{ route('admin.users', ['filter' => $filter ?? 'migrated']) }}" class="btn btn-secondary">
                                    <i class="bx bx-x"></i> Clear
                                </a>
                            @endif
                            <input type="hidden" name="per_page" id="perPageHidden" value="{{ $perPage ?? 25 }}">
                            <input type="hidden" name="filter" id="filterHidden" value="{{ $filter ?? 'migrated' }}">
                        </form>
                        <div class="right-controls">
                            <div class="pagination-info">
                                <i class="bx bx-user me-2"></i>
                                <span>Total: <strong>{{ $totalCount ?? 0 }}</strong> customers</span>
                            </div>
                            <select class="form-select" id="perPageSelect" onchange="changePerPage(this.value)">
                                <option value="25" {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}>25 per page</option>
                                <option value="50" {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}>50 per page</option>
                                <option value="100" {{ ($perPage ?? 25) == 100 ? 'selected' : '' }}>100 per page
                                </option>
                                <option value="250" {{ ($perPage ?? 25) == 250 ? 'selected' : '' }}>250 per page
                                </option>
                            </select>
                        </div>
                    </div>

                    {{-- Bulk Migration Controls (only show for non-migrated filter) --}}
                    @if(($filter ?? 'migrated') === 'not_migrated')
                    <div class="bulk-migration-controls mb-3 p-3 bg-light rounded border" id="bulkMigrationControls">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-muted">
                                    <i class="bx bx-check-square"></i>
                                    Selected: <strong id="selectedCount">0</strong> customers
                                </span>
                                <button type="button" class="btn btn-success btn-sm" id="bulkMigrateBtn" disabled onclick="openBulkMigrate()">
                                    <i class="bx bx-transfer-alt"></i> Migrate Selected
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="showMigrateConfirm('all')">
                                    <i class="bx bx-check-double"></i> Migrate All ({{ $totalCount ?? 0 }})
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="openMigrationGuide()">
                                    <i class="bx bx-help-circle"></i> Migration Guide
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectAll()">
                                    <i class="bx bx-select-multiple"></i> <span id="selectAllText">Select All on Page</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Custom Modal for Migration Confirmation --}}
                    <div id="migrationModal" class="custom-modal-overlay" style="display: none;">
                        <div class="custom-modal custom-modal-lg">
                            <div class="custom-modal-header">
                                <h5 id="migrationModalTitle">Confirm Migration</h5>
                                <button type="button" class="custom-modal-close" onclick="closeMigrationModal()">&times;</button>
                            </div>
                            <div class="custom-modal-body" id="migrationModalBody">
                                <!-- Dynamic content -->
                            </div>
                            <div class="custom-modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeMigrationModal()">Cancel</button>
                                <button type="button" class="btn btn-success" id="confirmMigrateBtn" onclick="confirmMigration()">
                                    <i class="bx bx-check"></i> Confirm Migration
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Parent / Sub-account Migration Modal --}}
                    <div id="parentMigrateModal" class="custom-modal-overlay" style="display: none;">
                        <div class="custom-modal">
                            <div class="custom-modal-header">
                                <h5 id="pmTitle">Migrate Account</h5>
                                <button type="button" class="custom-modal-close" onclick="closeParentMigrate()">&times;</button>
                            </div>
                            <div class="custom-modal-body">
                                <p class="text-muted mb-2">
                                    Choose what to migrate.
                                    <a href="#" onclick="openMigrationGuide(); return false;">What do these mean?</a>
                                </p>
                                <div id="pmOptions" class="mb-3">
                                    <label class="d-block mb-1"><input type="radio" name="pmOption" value="parent_only"> Migrate only the parent account</label>
                                    <label class="d-block mb-1"><input type="radio" name="pmOption" value="selected_subs"> Migrate only selected sub-account(s)</label>
                                    <label class="d-block mb-1"><input type="radio" name="pmOption" value="all_subs"> Migrate all sub-accounts</label>
                                    <label class="d-block mb-1"><input type="radio" name="pmOption" value="parent_all_subs" checked> Migrate parent + all sub-accounts</label>
                                    <label class="d-block mb-1"><input type="radio" name="pmOption" value="parent_selected_subs"> Migrate parent + selected sub-account(s)</label>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Accounts (<span id="pmCount">0</span>)</small>
                                    <small><a href="#" onclick="pmCheckVisible(true); return false;">Check all</a> · <a href="#" onclick="pmCheckVisible(false); return false;">Uncheck all</a></small>
                                </div>
                                <div id="pmAccounts" style="max-height: 320px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px;"><!-- account checkboxes built by JS --></div>
                                <div class="form-check mt-3">
                                    <input type="checkbox" id="pmMigrateFiles" class="form-check-input" checked>
                                    <label class="form-check-label" for="pmMigrateFiles">Also migrate campaign files</label>
                                </div>
                            </div>
                            <div class="custom-modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeParentMigrate()">Cancel</button>
                                <button type="button" class="btn btn-success" id="pmConfirmBtn" onclick="confirmParentMigrate()">
                                    <i class="bx bx-check"></i> Migrate
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Migration Guide Modal --}}
                    <div id="migrationGuideModal" class="custom-modal-overlay" style="display: none;">
                        <div class="custom-modal">
                            <div class="custom-modal-header">
                                <h5>Migration Guide — Parent &amp; Sub-Accounts</h5>
                                <button type="button" class="custom-modal-close" onclick="closeMigrationGuide()">&times;</button>
                            </div>
                            <div class="custom-modal-body">
                                <p>Sub-accounts are shown nested under their parent (master) account. When you click <strong>Migrate</strong> on a master, pick one of these options:</p>
                                <ol class="mb-3">
                                    <li><strong>Only the parent account</strong> — moves just the master to the new system. Sub-accounts stay on the old system.</li>
                                    <li><strong>Only selected sub-account(s)</strong> — migrates just the sub-accounts you tick. The parent is left as-is.</li>
                                    <li><strong>All sub-accounts</strong> — migrates every sub-account of this parent, but not the parent itself.</li>
                                    <li><strong>Parent + all sub-accounts</strong> — migrates the whole group (master and every sub-account) in one go.</li>
                                    <li><strong>Parent + selected sub-account(s)</strong> — migrates the parent plus only the sub-accounts you tick.</li>
                                </ol>
                                <p class="mb-2"><strong>Notes:</strong></p>
                                <ul class="mb-0">
                                    <li>Accounts already on the new system show an <span class="badge bg-success">Migrated</span> badge and are skipped (you can't re-migrate them).</li>
                                    <li>“Selected” options let you tick individual sub-accounts; the other options set the ticks automatically.</li>
                                    <li>Migration sets the account to the new system, stamps the migration time, repoints inbound (MO) webhooks, and — if ticked — queues campaign-file migration in the background.</li>
                                    <li>Bulk migration (the toolbar buttons) migrates each selected account; tick a parent and its sub-accounts to move them together.</li>
                                </ul>
                            </div>
                            <div class="custom-modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeMigrationGuide()">Close</button>
                            </div>
                        </div>
                    </div>

                    {{-- Custom Toast for Notifications --}}
                    <div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 99999;"></div>

                    <style>
                        .custom-modal-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            z-index: 99998;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .custom-modal {
                            background: white;
                            border-radius: 12px;
                            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                            max-width: 500px;
                            width: 90%;
                            animation: slideIn 0.3s ease-out;
                        }
                        .custom-modal.custom-modal-lg {
                            max-width: 720px;
                        }
                        @keyframes slideIn {
                            from { transform: translateY(-50px); opacity: 0; }
                            to { transform: translateY(0); opacity: 1; }
                        }
                        .custom-modal-header {
                            padding: 20px;
                            border-bottom: 1px solid #e9ecef;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            background: #f8f9fa;
                            border-radius: 12px 12px 0 0;
                        }
                        .custom-modal-header h5 {
                            margin: 0;
                            font-weight: 600;
                        }
                        .custom-modal-close {
                            background: none;
                            border: none;
                            font-size: 1.5rem;
                            cursor: pointer;
                            color: #6c757d;
                        }
                        .custom-modal-body {
                            padding: 25px;
                        }
                        .custom-modal-footer {
                            padding: 15px 20px;
                            border-top: 1px solid #e9ecef;
                            display: flex;
                            justify-content: flex-end;
                            gap: 10px;
                            background: #f8f9fa;
                            border-radius: 0 0 12px 12px;
                        }
                        .migration-info-box {
                            background: #d1e7dd;
                            border: 1px solid #badbcc;
                            border-radius: 8px;
                            padding: 15px;
                            margin-bottom: 15px;
                        }
                        .migration-warning-box {
                            background: #fff3cd;
                            border: 1px solid #ffecb5;
                            border-radius: 8px;
                            padding: 15px;
                        }
                    </style>

                    <div class="table-responsive">
                        <table id="customer_all_view" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                                <tr class="text-center">
                                    @if(($filter ?? 'migrated') === 'not_migrated')
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="handleSelectAll(this)">
                                    </th>
                                    @endif
                                    <th>User Name</th>
                                    <th>Type</th>
                                    <th>Contact Name</th>
                                    <th>Business Name</th>
                                    <th>Email</th>
                                    <th>Account Type</th>
                                    <th>Migration Status</th>
                                    <th>Options</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($userData as $record)
                                    <tr class="text-center">
                                        @if(($filter ?? 'migrated') === 'not_migrated')
                                        <td>
                                            <input type="checkbox" class="form-check-input customer-checkbox"
                                                   value="{{ $record->id }}"
                                                   data-username="{{ $record->uname }}"
                                                   onchange="updateSelectedCount()">
                                        </td>
                                        @endif
                                        <td>
                                            {{ $record->uname ?? '' }}
                                        </td>
                                        @php
                                            $raw = $record->customer_type ?? '';
                                            $cleaned = preg_replace('/[^a-zA-Z]/', '', trim($raw));
                                            $type = strtolower($cleaned);

                                            // Default to prepaid if type is empty or not valid
                                            if (!in_array($type, ['prepaid', 'postpaid'])) {
                                                $type = 'prepaid';
                                            }

                                            // Define background color
                                            $bgColor = $type === 'prepaid' ? 'green' : '#333399';
                                        @endphp
                                        <td>
                                            <button class="button19"
                                                style="background-color: {{ $bgColor }}; color: white;">
                                                {{ ucfirst($type) }}
                                            </button>
                                        </td>

                                        <td>
                                            {{ urldecode($record->contactname ?? '') }}
                                        </td>
                                        <td>
                                            {{ urldecode($record->busname ?? '') }}
                                        </td>
                                        <td>
                                            <a href="mailto:{{ $record->contactemail ?? '' }}" target="_blank"
                                                onclick="window.open(this.href, '_blank'); return false;">
                                                {{ $record->contactemail ?? '' }}
                                            </a>
                                        </td>
                                        <td>
                                            @if(!empty($record->is_sub))
                                                <span class="badge bg-info text-dark">Sub Account</span>
                                            @elseif(!empty($record->is_master))
                                                <button type="button" class="btn btn-sm btn-outline-primary toggle-subs"
                                                        data-target="subs-{{ $record->id }}" aria-expanded="false">
                                                    <i class="bx bx-chevron-right toggle-caret"></i>
                                                    <span class="badge bg-primary">Master</span>
                                                    <span class="badge bg-light text-dark">{{ $record->sub_accounts->count() }} sub</span>
                                                </button>
                                            @else
                                                <span class="badge bg-primary">Master</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(($record->migration_flag ?? null) === 'new')
                                                <span class="badge bg-success"><i class="bx bx-check-circle"></i> Migrated</span>
                                            @else
                                                <span class="badge bg-warning text-dark"><i class="bx bx-time"></i> Pending</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.user.show', ['id' => $record->id]) }}"
                                                class="btn btn-warning btn-sm">View</a>
                                            @if(!empty($record->is_master))
                                                @php
                                                    $parentMigrated = ($record->migration_flag ?? null) === 'new';
                                                    $subsArr = $record->sub_accounts->map(fn ($s) => [
                                                        'id'       => $s->id,
                                                        'uname'    => $s->uname,
                                                        'busname'  => urldecode($s->busname ?? ''),
                                                        'migrated' => (($s->migration_flag ?? null) === 'new'),
                                                    ])->values();
                                                    $anyUnmigrated = !$parentMigrated
                                                        || $record->sub_accounts->contains(fn ($s) => (($s->migration_flag ?? null) !== 'new'));
                                                @endphp
                                                @if($anyUnmigrated)
                                                    <button type="button" class="btn btn-success btn-sm mt-1"
                                                            onclick='openParentMigrate(this)'
                                                            data-parent-id="{{ $record->id }}"
                                                            data-parent-uname="{{ $record->uname }}"
                                                            data-parent-busname="{{ urldecode($record->busname ?? '') }}"
                                                            data-parent-migrated="{{ $parentMigrated ? '1' : '0' }}"
                                                            data-subs='@json($subsArr)'>
                                                        <i class="bx bx-transfer-alt"></i> Migrate
                                                    </button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>

                                    {{-- Tree: nested sub-accounts shown indented beneath their master --}}
                                    @if(!empty($record->is_master) && $record->sub_accounts->count())
                                        @foreach($record->sub_accounts as $sub)
                                            <tr class="text-center table-light sub-account-row subs-{{ $record->id }}" style="display: none;">
                                                @if(($filter ?? 'migrated') === 'not_migrated')
                                                    <td></td>
                                                @endif
                                                <td class="text-start ps-4">
                                                    <span class="d-inline-flex align-items-center gap-1" style="white-space: nowrap;">
                                                        <span class="text-muted">&#8627;</span>{{ $sub->uname }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="button19" style="background-color: green; color: white;">Prepaid</button>
                                                </td>
                                                <td>{{ urldecode($sub->contactname ?? '') }}</td>
                                                <td>{{ urldecode($sub->busname ?? '') }}</td>
                                                <td>
                                                    <a href="mailto:{{ $sub->contactemail ?? '' }}" target="_blank"
                                                        onclick="window.open(this.href, '_blank'); return false;">
                                                        {{ $sub->contactemail ?? '' }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark">Sub Account</span>
                                                </td>
                                                <td>
                                                    @if(($sub->migration_flag ?? null) === 'new')
                                                        <span class="badge bg-success"><i class="bx bx-check-circle"></i> Migrated</span>
                                                    @else
                                                        <span class="badge bg-warning text-dark"><i class="bx bx-time"></i> Pending</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('admin.user.show', ['id' => $sub->id]) }}"
                                                        class="btn btn-warning btn-sm">View</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ ($filter ?? 'migrated') === 'not_migrated' ? '9' : '8' }}" class="text-center py-4">
                                            <i class="bx bx-inbox fs-1 text-muted d-block mb-2"></i>
                                            <p class="text-muted mb-0">No customers found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if (isset($userData) && $userData->hasPages())
                        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-3">
                            <div class="text-muted">
                                Showing {{ $userData->firstItem() ?? 0 }} to {{ $userData->lastItem() ?? 0 }} of
                                {{ $userData->total() }} entries
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
                                    {{-- Previous Page Link --}}
                                    @if ($userData->onFirstPage())
                                        <li class="page-item disabled">
                                            <span class="page-link">Previous</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="{{ $userData->previousPageUrl() }}&per_page={{ $perPage }}&search={{ $search }}&filter={{ $filter ?? 'migrated' }}"
                                                rel="prev">Previous</a>
                                        </li>
                                    @endif

                                    {{-- Pagination Elements --}}
                                    @php
                                        $currentPage = $userData->currentPage();
                                        $lastPage = $userData->lastPage();
                                        $start = max(1, $currentPage - 2);
                                        $end = min($lastPage, $currentPage + 2);
                                    @endphp

                                    {{-- First Page --}}
                                    @if ($start > 1)
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="{{ $userData->url(1) }}&per_page={{ $perPage }}&search={{ $search }}&filter={{ $filter ?? 'migrated' }}">1</a>
                                        </li>
                                        @if ($start > 2)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                    @endif

                                    {{-- Page Numbers --}}
                                    @for ($page = $start; $page <= $end; $page++)
                                        @if ($page == $currentPage)
                                            <li class="page-item active">
                                                <span class="page-link">{{ $page }}</span>
                                            </li>
                                        @else
                                            <li class="page-item">
                                                <a class="page-link"
                                                    href="{{ $userData->url($page) }}&per_page={{ $perPage }}&search={{ $search }}&filter={{ $filter ?? 'migrated' }}">{{ $page }}</a>
                                            </li>
                                        @endif
                                    @endfor

                                    {{-- Last Page --}}
                                    @if ($end < $lastPage)
                                        @if ($end < $lastPage - 1)
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        @endif
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="{{ $userData->url($lastPage) }}&per_page={{ $perPage }}&search={{ $search }}&filter={{ $filter ?? 'migrated' }}">{{ $lastPage }}</a>
                                        </li>
                                    @endif

                                    {{-- Next Page Link --}}
                                    @if ($userData->hasMorePages())
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="{{ $userData->nextPageUrl() }}&per_page={{ $perPage }}&search={{ $search }}&filter={{ $filter ?? 'migrated' }}"
                                                rel="next">Next</a>
                                        </li>
                                    @else
                                        <li class="page-item disabled">
                                            <span class="page-link">Next</span>
                                        </li>
                                    @endif
                                </ul>
                            </nav>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>
    <!--end main wrapper-->
    <!-- Footer -->
    @include('admin.layouts.footer')
    <!-- End Footer -->
@endsection
@push('js')
    <script>
        function changePerPage(value) {
            document.getElementById('perPageHidden').value = value;
            document.getElementById('searchForm').submit();
        }

        // Master/Sub-account tree: expand/collapse a master's sub-account rows.
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.toggle-subs');
            if (!btn) return;

            const targetClass = btn.getAttribute('data-target'); // e.g. "subs-123"
            const rows = document.querySelectorAll('tr.' + targetClass);
            const expanded = btn.getAttribute('aria-expanded') === 'true';

            rows.forEach(function (row) {
                row.style.display = expanded ? 'none' : '';
            });

            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');

            const caret = btn.querySelector('.toggle-caret');
            if (caret) {
                caret.classList.toggle('bx-chevron-right', expanded);
                caret.classList.toggle('bx-chevron-down', !expanded);
            }
        });

        // ---- Parent / Sub-account migration modal (shared by bulk + per-master) ----
        let pmGroups = [];   // [{ parentId, parentUname, parentBusname, parentMigrated, subs:[...] }]

        function escapeHtml(str) {
            return String(str == null ? '' : str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function pmGroupFromButton(btn) {
            return {
                parentId: btn.getAttribute('data-parent-id'),
                parentUname: btn.getAttribute('data-parent-uname') || '',
                parentBusname: btn.getAttribute('data-parent-busname') || '',
                parentMigrated: btn.getAttribute('data-parent-migrated') === '1',
                subs: JSON.parse(btn.getAttribute('data-subs') || '[]')
            };
        }

        // Per-master "Migrate" button → single group
        function openParentMigrate(btn) {
            pmGroups = [pmGroupFromButton(btn)];
            pmShow();
        }

        // Bulk "Migrate Selected" → one group per ticked customer (with its subs)
        function openBulkMigrate() {
            const selected = Array.from(document.querySelectorAll('.customer-checkbox:checked'));
            if (selected.length === 0) {
                showToast('Please select at least one customer to migrate.', 'warning');
                return;
            }
            pmGroups = selected.map(function (cb) {
                const row = cb.closest('tr');
                const migBtn = row ? row.querySelector('[data-subs]') : null; // present on masters
                if (migBtn) return pmGroupFromButton(migBtn);
                return {  // standalone / orphan account (no sub-accounts)
                    parentId: cb.value,
                    parentUname: cb.dataset.username || cb.value,
                    parentBusname: cb.dataset.username || cb.value,
                    parentMigrated: false,
                    subs: []
                };
            });
            pmShow();
        }

        function pmShow() {
            const title = document.getElementById('pmTitle');
            if (pmGroups.length === 1) {
                const g = pmGroups[0];
                title.textContent = 'Migrate: ' + (g.parentBusname || g.parentUname) + ' (' + g.parentUname + ')';
            } else {
                title.textContent = 'Migrate ' + pmGroups.length + ' accounts (with sub-accounts)';
            }
            buildPmAccounts();
            const def = document.querySelector('input[name="pmOption"][value="parent_all_subs"]');
            if (def) def.checked = true;
            applyPmOption();
            document.getElementById('parentMigrateModal').style.display = 'flex';
        }

        function buildPmAccounts() {
            let html = '';
            pmGroups.forEach(function (g) {
                const hasSubs = g.subs && g.subs.length > 0;
                const pDisabled = g.parentMigrated ? 'disabled' : '';
                const pBadge = g.parentMigrated ? ' <span class="badge bg-success">Migrated</span>' : '';
                const pSuffix = hasSubs ? ' <span class="text-muted">(parent)</span>' : '';
                html += `<div class="form-check mt-2">
                    <input type="checkbox" class="form-check-input pm-acc" data-role="parent" data-migrated="${g.parentMigrated ? '1' : '0'}" value="${g.parentId}" id="pm-acc-${g.parentId}" ${pDisabled}>
                    <label class="form-check-label fw-bold" for="pm-acc-${g.parentId}">${escapeHtml(g.parentBusname || g.parentUname)}${pSuffix}${pBadge}</label>
                </div>`;

                (g.subs || []).forEach(function (s) {
                    const sDisabled = s.migrated ? 'disabled' : '';
                    const sBadge = s.migrated ? ' <span class="badge bg-success">Migrated</span>' : '';
                    html += `<div class="form-check ms-4">
                        <input type="checkbox" class="form-check-input pm-acc" data-role="sub" data-migrated="${s.migrated ? '1' : '0'}" value="${s.id}" id="pm-acc-${s.id}" ${sDisabled}>
                        <label class="form-check-label" for="pm-acc-${s.id}">&#8627; ${escapeHtml(s.busname || s.uname)} <span class="text-muted">(${escapeHtml(s.uname)})</span>${sBadge}</label>
                    </div>`;
                });
            });
            document.getElementById('pmAccounts').innerHTML = html;
            const cntEl = document.getElementById('pmCount');
            if (cntEl) cntEl.textContent = document.querySelectorAll('#pmAccounts .pm-acc').length;
        }

        // Check / uncheck all selectable (non-locked, non-migrated) accounts
        function pmCheckVisible(state) {
            document.querySelectorAll('#pmAccounts .pm-acc').forEach(function (cb) {
                if (cb.disabled) return;
                cb.checked = state;
            });
        }

        function applyPmOption() {
            const opt = (document.querySelector('input[name="pmOption"]:checked') || {}).value;
            const parentCbs = Array.from(document.querySelectorAll('.pm-acc[data-role="parent"]'));
            const subCbs = Array.from(document.querySelectorAll('.pm-acc[data-role="sub"]'));

            parentCbs.forEach(function (cb) {
                if (cb.getAttribute('data-migrated') === '1') { cb.disabled = true; cb.checked = false; return; }
                if (opt === 'parent_only' || opt === 'parent_all_subs') {
                    cb.checked = true;  cb.disabled = true;    // parent included, locked
                } else if (opt === 'parent_selected_subs') {
                    cb.checked = false; cb.disabled = false;   // admin chooses (parent optional)
                } else { // selected_subs or all_subs → parent not included
                    cb.checked = false; cb.disabled = true;
                }
            });

            subCbs.forEach(function (cb) {
                if (cb.getAttribute('data-migrated') === '1') { cb.disabled = true; cb.checked = false; return; }
                if (opt === 'selected_subs' || opt === 'parent_selected_subs') {
                    cb.checked = false; cb.disabled = false;   // admin picks
                } else if (opt === 'all_subs' || opt === 'parent_all_subs') {
                    cb.checked = true;  cb.disabled = true;     // locked to preset
                } else { // parent_only
                    cb.checked = false; cb.disabled = true;
                }
            });
        }

        function confirmParentMigrate() {
            const ids = Array.from(document.querySelectorAll('.pm-acc'))
                .filter(cb => cb.checked && cb.getAttribute('data-migrated') !== '1')
                .map(cb => cb.value);

            if (ids.length === 0) {
                showToast('Nothing to migrate — select at least one account.', 'warning');
                return;
            }

            const btn = document.getElementById('pmConfirmBtn');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Migrating...';

            const migrateFiles = document.getElementById('pmMigrateFiles').checked;

            fetch('{{ route("admin.users.bulk-migrate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ customer_ids: ids, migrate_campaign_files: migrateFiles })
            })
            .then(r => r.json())
            .then(data => {
                closeParentMigrate();
                if (data.success) {
                    showToast(data.message, 'success');
                    if (data.file_migration_queued && data.file_migration_batch_id) {
                        setTimeout(() => showToast('Campaign files migrating in background. Batch ID: ' + data.file_migration_batch_id, 'info'), 1000);
                    }
                    setTimeout(() => window.location.reload(), 2500);
                } else {
                    showToast('Error: ' + (data.message || 'Failed to migrate'), 'error');
                    btn.disabled = false; btn.innerHTML = orig;
                }
            })
            .catch(err => {
                console.error(err);
                closeParentMigrate();
                showToast('Failed to migrate. Please try again.', 'error');
                btn.disabled = false; btn.innerHTML = orig;
            });
        }

        function closeParentMigrate() {
            document.getElementById('parentMigrateModal').style.display = 'none';
            pmGroups = [];
        }

        function openMigrationGuide() {
            document.getElementById('migrationGuideModal').style.display = 'flex';
        }
        function closeMigrationGuide() {
            document.getElementById('migrationGuideModal').style.display = 'none';
        }

        // Re-apply checkbox presets when the chosen option changes
        document.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'pmOption') applyPmOption();
        });

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

        // Bulk Migration Functions
        let pendingMigrationType = null;

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.customer-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;

            const bulkBtn = document.getElementById('bulkMigrateBtn');
            if (bulkBtn) {
                bulkBtn.disabled = count === 0;
            }

            // Update select all checkbox state
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const allCheckboxes = document.querySelectorAll('.customer-checkbox');
            if (selectAllCheckbox && allCheckboxes.length > 0) {
                selectAllCheckbox.checked = checkboxes.length === allCheckboxes.length;
                selectAllCheckbox.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
            }
        }

        function handleSelectAll(checkbox) {
            const allCheckboxes = document.querySelectorAll('.customer-checkbox');
            allCheckboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = !selectAllCheckbox.checked;
                handleSelectAll(selectAllCheckbox);
            }
        }

        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.customer-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function getSelectedUsernames() {
            const checkboxes = document.querySelectorAll('.customer-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.dataset.username);
        }

        // Show migration confirmation modal
        function showMigrateConfirm(type) {
            pendingMigrationType = type;
            const modal = document.getElementById('migrationModal');
            const modalBody = document.getElementById('migrationModalBody');
            const totalCount = {{ $totalCount ?? 0 }};

            if (type === 'selected') {
                const selectedCheckboxes = Array.from(document.querySelectorAll('.customer-checkbox:checked'));

                if (selectedCheckboxes.length === 0) {
                    showToast('Please select at least one customer to migrate.', 'warning');
                    return;
                }

                // Build the account checklist: each selected customer, plus (for
                // masters) ALL of their sub-accounts. Everything not already
                // migrated is CHECKED by default — untick any you don't want.
                let rowsHtml = '';
                selectedCheckboxes.forEach(function (cb) {
                    const id = cb.value;
                    const uname = cb.dataset.username || id;
                    const row = cb.closest('tr');
                    const migBtn = row ? row.querySelector('[data-subs]') : null; // present on masters
                    const parentMigrated = migBtn ? (migBtn.getAttribute('data-parent-migrated') === '1') : false;
                    const busname = migBtn ? (migBtn.getAttribute('data-parent-busname') || uname) : uname;

                    const pBadge = parentMigrated ? ' <span class="badge bg-success">Migrated</span>' : '';
                    rowsHtml += `<div class="form-check">
                        <input class="form-check-input migrate-acc" type="checkbox" value="${id}" data-migrated="${parentMigrated ? '1' : '0'}" id="mig-acc-${id}" ${parentMigrated ? 'disabled' : 'checked'}>
                        <label class="form-check-label fw-bold" for="mig-acc-${id}">${escapeHtml(busname)} <span class="text-muted">(${escapeHtml(uname)})</span>${pBadge}</label>
                    </div>`;

                    if (migBtn) {
                        const subs = JSON.parse(migBtn.getAttribute('data-subs') || '[]');
                        subs.forEach(function (s) {
                            const sBadge = s.migrated ? ' <span class="badge bg-success">Migrated</span>' : '';
                            rowsHtml += `<div class="form-check ms-4">
                                <input class="form-check-input migrate-acc" type="checkbox" value="${s.id}" data-migrated="${s.migrated ? '1' : '0'}" id="mig-acc-${s.id}" ${s.migrated ? 'disabled' : 'checked'}>
                                <label class="form-check-label" for="mig-acc-${s.id}">&#8627; ${escapeHtml(s.busname || s.uname)} <span class="text-muted">(${escapeHtml(s.uname)})</span>${sBadge}</label>
                            </div>`;
                        });
                    }
                });

                modalBody.innerHTML = `
                    <div class="migration-warning-box">
                        <strong><i class="bx bx-info-circle"></i> Review accounts to migrate</strong>
                        <div class="mb-0 mt-1 small">Sub-accounts are shown under their parent and <strong>checked by default</strong> — untick any you don't want. Only ticked accounts will be migrated.</div>
                    </div>
                    <div class="mt-3" style="max-height: 320px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px;">
                        ${rowsHtml}
                    </div>
                    <div class="mt-3 p-3 border rounded bg-light">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="migrateCampaignFiles" checked>
                            <label class="form-check-label" for="migrateCampaignFiles">
                                <strong><i class="bx bx-folder-open"></i> Migrate Campaign Files</strong>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Copy campaign CSV files from old server to new server in background (via RabbitMQ)
                        </small>
                    </div>
                `;
            } else {
                if (totalCount === 0) {
                    showToast('No customers to migrate.', 'warning');
                    return;
                }

                modalBody.innerHTML = `
                    <div class="migration-info-box">
                        <h6><i class="bx bx-group"></i> Migrate All Pending Customers</h6>
                        <p class="mb-0 mt-2">You are about to migrate <strong>${totalCount}</strong> customer(s) to the new system.</p>
                    </div>
                    <div class="migration-warning-box">
                        <strong><i class="bx bx-error"></i> Warning:</strong>
                        <ul class="mb-0 mt-2">
                            <li>This will migrate ALL pending customers at once</li>
                            <li>This action affects ${totalCount} customer(s)</li>
                            <li>Please ensure you want to proceed</li>
                        </ul>
                    </div>
                    <div class="mt-3 p-3 border rounded bg-light">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="migrateCampaignFiles" checked>
                            <label class="form-check-label" for="migrateCampaignFiles">
                                <strong><i class="bx bx-folder-open"></i> Migrate Campaign Files</strong>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Copy campaign CSV files from old server to new server in background (via RabbitMQ)
                        </small>
                    </div>
                `;
            }

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeMigrationModal() {
            const modal = document.getElementById('migrationModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
            pendingMigrationType = null;
        }

        function confirmMigration() {
            if (!pendingMigrationType) return;

            let ids;
            if (pendingMigrationType === 'selected') {
                // Only the ticked, not-already-migrated accounts (parents + subs)
                ids = Array.from(document.querySelectorAll('.migrate-acc:checked'))
                    .filter(cb => cb.getAttribute('data-migrated') !== '1')
                    .map(cb => cb.value);
                if (ids.length === 0) {
                    showToast('Select at least one account to migrate.', 'warning');
                    return;
                }
            } else {
                ids = 'all';
            }

            const confirmBtn = document.getElementById('confirmMigrateBtn');
            const originalText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Migrating...';

            const migrateCampaignFilesCheckbox = document.getElementById('migrateCampaignFiles');
            const migrateCampaignFiles = migrateCampaignFilesCheckbox ? migrateCampaignFilesCheckbox.checked : true;

            fetch('{{ route("admin.users.bulk-migrate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    customer_ids: ids,
                    migrate_campaign_files: migrateCampaignFiles
                })
            })
            .then(response => response.json())
            .then(data => {
                closeMigrationModal();
                if (data.success) {
                    showToast(data.message, 'success');
                    // Show additional info about file migration if queued
                    if (data.file_migration_queued && data.file_migration_batch_id) {
                        setTimeout(() => {
                            showToast('Campaign files are being migrated in background. Batch ID: ' + data.file_migration_batch_id, 'info');
                        }, 1000);
                    }
                    setTimeout(() => window.location.reload(), 2500);
                } else {
                    showToast('Error: ' + (data.message || 'Failed to migrate customers'), 'error');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                closeMigrationModal();
                showToast('Failed to migrate customers. Please try again.', 'error');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            });
        }

        // Custom Toast Notification
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            const bgColors = {
                success: '#198754',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#0dcaf0'
            };
            const textColor = type === 'warning' ? '#000' : '#fff';
            const icons = {
                success: '<i class="bx bx-check-circle"></i>',
                error: '<i class="bx bx-x-circle"></i>',
                warning: '<i class="bx bx-error"></i>',
                info: '<i class="bx bx-info-circle"></i>'
            };

            toast.style.cssText = `
                background: ${bgColors[type] || bgColors.info};
                color: ${textColor};
                padding: 15px 20px;
                border-radius: 8px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 300px;
                max-width: 450px;
                animation: toastSlideIn 0.3s ease-out;
            `;

            toast.innerHTML = `
                <span style="font-size: 1.25rem;">${icons[type] || icons.info}</span>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: ${textColor}; cursor: pointer; font-size: 1.25rem; padding: 0;">&times;</button>
            `;

            container.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.style.animation = 'toastSlideOut 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Add toast animation styles
        if (!document.getElementById('toastStyles')) {
            const style = document.createElement('style');
            style.id = 'toastStyles';
            style.textContent = `
                @keyframes toastSlideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes toastSlideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        // Close modal on overlay click
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('migrationModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeMigrationModal();
                    }
                });
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('migrationModal');
                if (modal && modal.style.display === 'flex') {
                    closeMigrationModal();
                }
            }
        });
    </script>
@endpush
