@extends('admin.layouts.app')

@section('title', 'Cost Management - SMS Expert Admin')

@push('style')
<style>
    .cost-tabs .nav-link {
        color: #293b50;
        font-weight: 500;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 12px 24px;
        background: transparent;
    }
    .cost-tabs .nav-link:hover {
        border-color: transparent;
        border-bottom: 3px solid #ea6118;
        color: #ea6118;
    }
    .cost-tabs .nav-link.active {
        color: #ea6118;
        background-color: transparent;
        border-color: transparent;
        border-bottom: 3px solid #ea6118;
    }
    .cost-tabs .nav-link i {
        font-size: 20px;
        vertical-align: middle;
        margin-right: 8px;
    }
    .tab-content {
        padding-top: 20px;
    }
    .bulk-ops-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .cost-table th {
        background: #293b50;
        color: white;
        font-weight: 500;
        border: none;
    }
    .cost-table td {
        vertical-align: middle;
    }
    .btn-xs {
        padding: 0.15rem 0.35rem;
        font-size: 0.75rem;
    }
    .exchange-rate-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        color: white;
    }
    .exchange-rate-card .rate-value {
        font-size: 24px;
        font-weight: 700;
    }
    .exchange-rate-card .rate-label {
        font-size: 12px;
        opacity: 0.9;
    }
    .badge-manual {
        background-color: #6f42c1;
        color: white;
        font-size: 10px;
    }
    .badge-api {
        background-color: #20c997;
        color: white;
        font-size: 10px;
    }
    .action-btns .btn {
        margin: 1px;
    }
    /* User Guide collapse icon */
    .collapse-icon {
        transition: transform 0.3s ease;
    }
    [data-bs-toggle="collapse"][aria-expanded="true"] .collapse-icon,
    [data-bs-toggle="collapse"]:not(.collapsed) .collapse-icon {
        transform: rotate(180deg);
    }
    #userGuideContent ul {
        padding-left: 20px;
        margin-bottom: 0;
    }
    #userGuideContent ul li {
        margin-bottom: 4px;
    }
    #userGuideContent ul ul {
        margin-top: 4px;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor">
            <div class="breadcrumb-title pe-3 title-name">Cost Management</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Cost</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- User Guide -->
        <div class="card mb-3">
            <div class="card-header bg-white py-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#userGuideContent">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-semibold">
                        <i class="material-icons-outlined me-2" style="vertical-align: middle; color: #ea6118;">help_outline</i>
                        User Guide - Cost Management
                    </h6>
                    <i class="material-icons-outlined collapse-icon">expand_more</i>
                </div>
            </div>
            <div class="collapse" id="userGuideContent">
                <div class="card-body" style="background: #f8f9fa;">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-2"><i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">cell_tower</i> Vonage Tab</h6>
                            <ul class="mb-3" style="font-size: 13px;">
                                <li><strong>Cost (EUR):</strong> Original cost in Euros from Nexmo API</li>
                                <li><strong>Cost (GBP):</strong> Auto-calculated using exchange rate (EUR × Rate)</li>
                                <li><strong>Exchange Rate:</strong> Updated daily via cron at 00:15</li>
                                <li><strong>Source Badge:</strong>
                                    <ul>
                                        <li><span class="badge badge-manual">Manual</span> - Set by admin, API won't override</li>
                                        <li><span class="badge badge-api">API</span> - Fetched from Nexmo API</li>
                                    </ul>
                                </li>
                            </ul>
                            <h6 class="fw-bold text-success mb-2"><i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">upload</i> Import/Export</h6>
                            <ul class="mb-0" style="font-size: 13px;">
                                <li><strong>Template:</strong> Download blank Excel template with headers</li>
                                <li><strong>Export:</strong> Download current prices as Excel file</li>
                                <li><strong>Upload:</strong> Upload Excel to update prices (sets as "Manual")</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-warning mb-2"><i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">cloud_download</i> Nexmo API</h6>
                            <ul class="mb-3" style="font-size: 13px;">
                                <li><strong>Fetch Single:</strong> Click <i class="material-icons-outlined" style="font-size: 14px;">cloud_download</i> to fetch one country</li>
                                <li><strong>Fetch All:</strong> Fetches pricing for all countries at once</li>
                                <li><strong>Highest Network Price:</strong> Uses highest price among all networks in a country</li>
                                <li><strong>Skip Manual:</strong> Countries with "Manual" source are not updated</li>
                                <li><strong>Auto Cron:</strong> Runs daily at 00:30 (after exchange rate update)</li>
                            </ul>
                            <h6 class="fw-bold text-info mb-2"><i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">send</i> Sinch Tab</h6>
                            <ul class="mb-0" style="font-size: 13px;">
                                <li><strong>Cost (GBP):</strong> Direct GBP price (no EUR conversion)</li>
                                <li><strong>Manual Entry:</strong> All prices are entered manually</li>
                                <li><strong>Import/Export:</strong> Same as Vonage (Template, Export, Upload)</li>
                            </ul>
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="fw-bold mb-2"><i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">touch_app</i> Action Buttons</h6>
                            <div class="d-flex flex-wrap gap-4" style="font-size: 13px;">
                                <div><button class="btn btn-xs btn-outline-primary me-1"><i class="material-icons-outlined" style="font-size: 14px;">edit</i></button> Edit price manually</div>
                                <div><button class="btn btn-xs btn-outline-warning me-1"><i class="material-icons-outlined" style="font-size: 14px;">cloud_download</i></button> Fetch from Nexmo API</div>
                                <div><button class="btn btn-xs btn-outline-secondary me-1"><i class="material-icons-outlined" style="font-size: 14px;">lock_open</i></button> Remove "Manual" lock (allow API updates)</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-2"><i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">menu_book</i> Detailed Guides</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="/admin-guides/vonage-cost-guide.html" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="material-icons-outlined me-1" style="font-size: 14px; vertical-align: middle;">cell_tower</i> Vonage Guide
                                </a>
                                <a href="/admin-guides/sinch-cost-guide.html" target="_blank" class="btn btn-sm btn-outline-warning">
                                    <i class="material-icons-outlined me-1" style="font-size: 14px; vertical-align: middle;">send</i> Sinch Guide
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cost Tabs -->
        <div class="card">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs cost-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab == 'vonage' ? 'active' : '' }}" data-bs-toggle="tab" href="#vonageTab" role="tab">
                            <i class="material-icons-outlined">cell_tower</i> Vonage
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab == 'sinch' ? 'active' : '' }}" data-bs-toggle="tab" href="#sinchTab" role="tab">
                            <i class="material-icons-outlined">send</i> Sinch
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Vonage Tab -->
                    <div class="tab-pane fade {{ $activeTab == 'vonage' ? 'show active' : '' }}" id="vonageTab" role="tabpanel">

                        <!-- Exchange Rate Card -->
                        <div class="exchange-rate-card">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="material-icons-outlined" style="font-size: 40px; opacity: 0.8;">currency_exchange</i>
                                </div>
                                <div class="col">
                                    <div class="rate-label">EUR to GBP Exchange Rate</div>
                                    <div class="rate-value">1 EUR = {{ number_format($exchangeRate, 4) }} GBP</div>
                                </div>
                                <div class="col-auto">
                                    <small class="d-block" style="opacity: 0.8;">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">schedule</i>
                                        Updated: {{ $exchangeRateUpdatedAt ?? 'N/A' }}
                                    </small>
                                    <small style="opacity: 0.7;">Auto-updates daily via cron</small>
                                </div>
                            </div>
                        </div>

                        <!-- Vonage Bulk Operations -->
                        <div class="bulk-ops-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3"><i class="material-icons-outlined me-2" style="vertical-align: middle;">file_upload</i>Excel Import/Export</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('admin.cost.vonage.template.country') }}" class="btn btn-outline-primary btn-sm">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">download</i> Template
                                        </a>
                                        <a href="{{ route('admin.cost.vonage.export.country') }}" class="btn btn-outline-success btn-sm">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">file_download</i> Export
                                        </a>
                                        <form id="vonageCountryUploadForm" action="{{ route('admin.cost.vonage.import.country') }}" method="POST" enctype="multipart/form-data" class="d-inline">
                                            @csrf
                                            <input type="file" name="file" accept=".xlsx,.xls" class="d-none" id="vonageCountryUpload" onchange="submitVonageCountryUpload()">
                                            <label for="vonageCountryUpload" class="btn btn-primary btn-sm mb-0" style="cursor: pointer;">
                                                <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">upload</i> Upload
                                            </label>
                                        </form>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info</i>
                                        Manual upload sets prices as "Manual" (won't be overwritten by API)
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-3"><i class="material-icons-outlined me-2" style="vertical-align: middle;">cloud_download</i>Nexmo API</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-warning btn-sm" onclick="fetchAllNexmoPricing()" id="fetchAllBtn">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">sync</i> Fetch All from Nexmo
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">info</i>
                                        Fetches highest network price per country. Skips "Manual" prices.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Vonage Search -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-semibold">Vonage Country Cost Prices</h6>
                            <div class="d-flex align-items-center gap-3">
                                <div>
                                    <span class="badge badge-manual me-1">Manual</span> = Admin set, API won't override
                                    <span class="badge badge-api ms-2 me-1">API</span> = From Nexmo API
                                </div>
                                <input type="text" class="form-control form-control-sm" id="searchVonage" placeholder="Search country..." style="width: 200px;">
                            </div>
                        </div>

                        <!-- Vonage Table -->
                        <div class="table-responsive">
                            <table class="table table-hover cost-table" id="vonageCostTable">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>ISO</th>
                                        <th>Dial Code</th>
                                        <th>Cost (EUR)</th>
                                        <th>Cost (GBP)</th>
                                        <th>Source</th>
                                        <th>Updated</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($countries as $country)
                                    <tr class="vonage-country-row" data-country-id="{{ $country->id }}" data-iso="{{ $country->iso_code }}">
                                        <td>{{ $country->name }}</td>
                                        <td><span class="badge bg-secondary">{{ $country->iso_code }}</span></td>
                                        <td>+{{ $country->dialcode }}</td>
                                        <td>{{ $country->cost_price_eur ? number_format($country->cost_price_eur, 6) : '-' }}</td>
                                        <td>{{ $country->cost_price_gbp ? number_format($country->cost_price_gbp, 6) : '-' }}</td>
                                        <td>
                                            @if($country->price_update_mode === 'manual')
                                                <span class="badge badge-manual">Manual</span>
                                            @elseif($country->price_update_mode === 'api')
                                                <span class="badge badge-api">API</span>
                                            @else
                                                <span class="badge bg-light text-dark">-</span>
                                            @endif
                                        </td>
                                        <td><small class="text-muted">{{ $country->updated_at ? $country->updated_at->format('d M Y') : '-' }}</small></td>
                                        <td class="action-btns">
                                            <button class="btn btn-xs btn-outline-primary edit-vonage-cost" title="Edit manually"
                                                    data-country-id="{{ $country->id }}"
                                                    data-country-name="{{ $country->name }}"
                                                    data-iso-code="{{ $country->iso_code }}"
                                                    data-cost-eur="{{ $country->cost_price_eur }}"
                                                    data-price-mode="{{ $country->price_update_mode }}">
                                                <i class="material-icons-outlined" style="font-size: 14px;">edit</i>
                                            </button>
                                            <button class="btn btn-xs btn-outline-warning fetch-nexmo-single" title="Fetch from Nexmo"
                                                    data-iso-code="{{ $country->iso_code }}"
                                                    data-country-name="{{ $country->name }}">
                                                <i class="material-icons-outlined" style="font-size: 14px;">cloud_download</i>
                                            </button>
                                            @if($country->price_update_mode === 'manual')
                                            <button class="btn btn-xs btn-outline-secondary reset-price-mode" title="Allow API updates"
                                                    data-country-id="{{ $country->id }}"
                                                    data-country-name="{{ $country->name }}">
                                                <i class="material-icons-outlined" style="font-size: 14px;">lock_open</i>
                                            </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sinch Tab -->
                    <div class="tab-pane fade {{ $activeTab == 'sinch' ? 'show active' : '' }}" id="sinchTab" role="tabpanel">
                        <!-- Sinch Bulk Operations -->
                        <div class="bulk-ops-card">
                            <h6 class="fw-semibold mb-3"><i class="material-icons-outlined me-2" style="vertical-align: middle;">file_upload</i>Bulk Operations - Sinch</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('admin.cost.sinch.template') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">download</i> Download Template
                                </a>
                                <a href="{{ route('admin.cost.sinch.export') }}" class="btn btn-outline-success btn-sm">
                                    <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">file_download</i> Export Current Data
                                </a>
                                <form id="sinchUploadForm" action="{{ route('admin.cost.sinch.import') }}" method="POST" enctype="multipart/form-data" class="d-inline">
                                    @csrf
                                    <input type="file" name="file" accept=".xlsx,.xls" class="d-none" id="sinchUpload" onchange="submitSinchUpload()">
                                    <label for="sinchUpload" class="btn btn-primary btn-sm mb-0" style="cursor: pointer;">
                                        <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">upload</i> Upload Sinch Costs
                                    </label>
                                </form>
                            </div>
                        </div>

                        <!-- Sinch Search -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-semibold">Sinch Country Cost Prices</h6>
                            <input type="text" class="form-control form-control-sm" id="searchSinch" placeholder="Search country..." style="width: 200px;">
                        </div>

                        <!-- Sinch Table -->
                        <div class="table-responsive">
                            <table class="table table-hover cost-table" id="sinchCostTable">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>ISO</th>
                                        <th>Dial Code</th>
                                        <th>Cost (GBP)</th>
                                        <th>Updated</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($countries as $country)
                                    <tr class="sinch-country-row" data-country-id="{{ $country->id }}">
                                        <td>{{ $country->name }}</td>
                                        <td><span class="badge bg-secondary">{{ $country->iso_code }}</span></td>
                                        <td>+{{ $country->dialcode }}</td>
                                        <td>{{ $country->sinch_cost_price_gbp ? number_format($country->sinch_cost_price_gbp, 6) : '-' }}</td>
                                        <td><small class="text-muted">{{ $country->sinch_price_updated_at ? \Carbon\Carbon::parse($country->sinch_price_updated_at)->format('d M Y') : '-' }}</small></td>
                                        <td>
                                            <button class="btn btn-xs btn-outline-primary edit-sinch-cost"
                                                    data-country-id="{{ $country->id }}"
                                                    data-country-name="{{ $country->name }}"
                                                    data-cost-gbp="{{ $country->sinch_cost_price_gbp }}">
                                                <i class="material-icons-outlined" style="font-size: 14px;">edit</i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Edit Vonage Country Cost Modal -->
<div class="modal fade" id="editVonageCostModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Vonage Cost (Manual)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editVonageCostForm">
                <div class="modal-body">
                    <input type="hidden" id="editVonageCountryId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Country</label>
                        <p id="editVonageCountryName" class="form-control-plaintext"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cost Price (EUR) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="editVonageCostEUR" step="0.000001" min="0" required>
                        <small class="text-muted">GBP will be auto-calculated using exchange rate ({{ number_format($exchangeRate, 4) }})</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Calculated GBP</label>
                        <input type="text" class="form-control" id="editVonageCostGBPPreview" readonly disabled>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">info</i>
                        <small>Manual updates prevent Nexmo API from overwriting this price. Use "Allow API" button to re-enable API updates.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save (Manual)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Sinch Cost Modal -->
<div class="modal fade" id="editSinchCostModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Sinch Cost</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSinchCostForm">
                <div class="modal-body">
                    <input type="hidden" id="editSinchCountryId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Country</label>
                        <p id="editSinchCountryName" class="form-control-plaintext"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cost Price (GBP) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="editSinchCostGBP" step="0.000001" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.layouts.footer')
@endsection

@push('js')
<script>
const exchangeRate = {{ $exchangeRate }};

// ========== VONAGE FUNCTIONS ==========

// Search Vonage
document.getElementById('searchVonage').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.vonage-country-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.classList.toggle('d-none', !text.includes(searchTerm));
    });
});

// Edit Vonage country cost
document.querySelectorAll('.edit-vonage-cost').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editVonageCountryId').value = this.dataset.countryId;
        document.getElementById('editVonageCountryName').textContent = this.dataset.countryName + ' (' + this.dataset.isoCode + ')';
        document.getElementById('editVonageCostEUR').value = this.dataset.costEur || '';
        updateGbpPreview();
        new bootstrap.Modal(document.getElementById('editVonageCostModal')).show();
    });
});

// Update GBP preview when EUR changes
document.getElementById('editVonageCostEUR').addEventListener('input', updateGbpPreview);

function updateGbpPreview() {
    const eurValue = parseFloat(document.getElementById('editVonageCostEUR').value) || 0;
    const gbpValue = eurValue * exchangeRate;
    document.getElementById('editVonageCostGBPPreview').value = gbpValue ? gbpValue.toFixed(6) + ' GBP' : '';
}

document.getElementById('editVonageCostForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const countryId = document.getElementById('editVonageCountryId').value;
    const costEur = document.getElementById('editVonageCostEUR').value;

    fetch(`{{ url('admin/cost/vonage/country') }}/${countryId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({
            cost_price_eur: costEur || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('editVonageCostModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', data.message);
        }
    });
});

// Fetch single country from Nexmo API
document.querySelectorAll('.fetch-nexmo-single').forEach(btn => {
    btn.addEventListener('click', function() {
        const isoCode = this.dataset.isoCode;
        const countryName = this.dataset.countryName;

        if (!confirm(`Fetch pricing for ${countryName} from Nexmo API?`)) return;

        this.disabled = true;
        this.innerHTML = '<i class="material-icons-outlined" style="font-size: 14px;">sync</i>';

        fetch(`{{ url('admin/cost/vonage/fetch-nexmo') }}/${isoCode}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', data.message);
                this.disabled = false;
                this.innerHTML = '<i class="material-icons-outlined" style="font-size: 14px;">cloud_download</i>';
            }
        })
        .catch(err => {
            showToast('error', 'Failed to fetch: ' + err.message);
            this.disabled = false;
            this.innerHTML = '<i class="material-icons-outlined" style="font-size: 14px;">cloud_download</i>';
        });
    });
});

// Fetch all countries from Nexmo API
function fetchAllNexmoPricing() {
    if (!confirm('Fetch pricing for all countries from Nexmo API?\n\nNote: Countries with "Manual" prices will be skipped.')) return;

    const btn = document.getElementById('fetchAllBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">sync</i> Fetching...';

    fetch(`{{ route('admin.cost.vonage.fetch-nexmo-all') }}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast('error', data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">sync</i> Fetch All from Nexmo';
        }
    })
    .catch(err => {
        showToast('error', 'Failed to fetch: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">sync</i> Fetch All from Nexmo';
    });
}

// Reset price mode to allow API updates
document.querySelectorAll('.reset-price-mode').forEach(btn => {
    btn.addEventListener('click', function() {
        const countryId = this.dataset.countryId;
        const countryName = this.dataset.countryName;

        if (!confirm(`Allow API updates for ${countryName}?\n\nThis will remove the "Manual" lock and allow Nexmo API to update this price.`)) return;

        fetch(`{{ url('admin/cost/vonage/country') }}/${countryId}/reset-mode`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message);
            }
        });
    });
});

// ========== SINCH FUNCTIONS ==========

// Search Sinch
document.getElementById('searchSinch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.sinch-country-row').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.classList.toggle('d-none', !text.includes(searchTerm));
    });
});

// Edit Sinch cost
document.querySelectorAll('.edit-sinch-cost').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editSinchCountryId').value = this.dataset.countryId;
        document.getElementById('editSinchCountryName').textContent = this.dataset.countryName;
        document.getElementById('editSinchCostGBP').value = this.dataset.costGbp || '';
        new bootstrap.Modal(document.getElementById('editSinchCostModal')).show();
    });
});

document.getElementById('editSinchCostForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const countryId = document.getElementById('editSinchCountryId').value;
    fetch(`{{ url('admin/cost/sinch/country') }}/${countryId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({
            sinch_cost_price_gbp: document.getElementById('editSinchCostGBP').value || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('editSinchCostModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', data.message);
        }
    });
});

// ========== FILE UPLOAD FUNCTIONS ==========

function submitVonageCountryUpload() {
    const form = document.getElementById('vonageCountryUploadForm');
    const formData = new FormData(form);
    fetch(form.action, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
    .then(response => response.json())
    .then(data => {
        showToast(data.success ? 'success' : 'error', data.message);
        if (data.success) setTimeout(() => location.reload(), 1500);
    });
    document.getElementById('vonageCountryUpload').value = '';
}

function submitSinchUpload() {
    const form = document.getElementById('sinchUploadForm');
    const formData = new FormData(form);
    fetch(form.action, { method: 'POST', body: formData, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
    .then(response => response.json())
    .then(data => {
        showToast(data.success ? 'success' : 'error', data.message);
        if (data.success) setTimeout(() => location.reload(), 1500);
    });
    document.getElementById('sinchUpload').value = '';
}

// ========== TOAST ==========

function showToast(type, message) {
    const toastHtml = `<div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
        <div class="d-flex"><div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1100';
        document.body.appendChild(container);
    }
    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = container.lastElementChild;
    new bootstrap.Toast(toastEl, { delay: 3000 }).show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}
</script>
@endpush
