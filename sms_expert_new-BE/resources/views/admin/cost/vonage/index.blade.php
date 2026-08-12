@extends('admin.layouts.modern-app')

@section('title', 'Vonage Cost Management - SMS Expert Admin')

@section('content')
<div class="page-content">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title mb-1">Vonage Cost Management</h1>
                <p class="text-muted mb-0">Manage country-wise and network-wise SMS cost prices for Vonage</p>
            </div>
            <div class="col-auto">
                <span class="badge bg-info">Exchange Rate: {{ number_format($exchangeRate, 4) }} EUR/GBP</span>
            </div>
        </div>
    </div>

    <!-- Bulk Operations Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="material-icons-outlined me-2">file_upload</i>Bulk Operations</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Country Costs -->
                <div class="col-md-6 mb-3 mb-md-0">
                    <h6 class="fw-semibold mb-3">Country Costs</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.cost.vonage.template.country') }}" class="btn btn-outline-primary btn-sm">
                            <i class="material-icons-outlined me-1">download</i> Download Template
                        </a>
                        <a href="{{ route('admin.cost.vonage.export.country') }}" class="btn btn-outline-success btn-sm">
                            <i class="material-icons-outlined me-1">file_download</i> Export Current Data
                        </a>
                        <form id="countryUploadForm" action="{{ route('admin.cost.vonage.import.country') }}" method="POST" enctype="multipart/form-data" class="d-inline">
                            @csrf
                            <input type="file" name="file" accept=".xlsx,.xls" class="d-none" id="countryUpload" onchange="submitCountryUpload()">
                            <label for="countryUpload" class="btn btn-primary btn-sm mb-0" style="cursor: pointer;">
                                <i class="material-icons-outlined me-1">upload</i> Upload Country Costs
                            </label>
                        </form>
                    </div>
                </div>

                <!-- Network Costs -->
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-3">Network Costs</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.cost.vonage.template.network') }}" class="btn btn-outline-primary btn-sm">
                            <i class="material-icons-outlined me-1">download</i> Download Template
                        </a>
                        <a href="{{ route('admin.cost.vonage.export.network') }}" class="btn btn-outline-success btn-sm">
                            <i class="material-icons-outlined me-1">file_download</i> Export Current Data
                        </a>
                        <form id="networkUploadForm" action="{{ route('admin.cost.vonage.import.network') }}" method="POST" enctype="multipart/form-data" class="d-inline">
                            @csrf
                            <input type="file" name="file" accept=".xlsx,.xls" class="d-none" id="networkUpload" onchange="submitNetworkUpload()">
                            <label for="networkUpload" class="btn btn-primary btn-sm mb-0" style="cursor: pointer;">
                                <i class="material-icons-outlined me-1">upload</i> Upload Network Costs
                            </label>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Country Costs Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="material-icons-outlined me-2">public</i>Country Cost Prices</h5>
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" id="searchCountry" placeholder="Search country..." style="width: 200px;">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="countryCostTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Country</th>
                            <th>ISO Code</th>
                            <th>Dial Code</th>
                            <th>Cost (GBP)</th>
                            <th>Cost (EUR)</th>
                            <th>Networks</th>
                            <th>Updated</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countries as $country)
                        <tr class="country-row" data-country-id="{{ $country->id }}" data-iso="{{ $country->iso_code }}">
                            <td>
                                <button class="btn btn-sm btn-link p-0 expand-networks" data-iso="{{ $country->iso_code }}" title="View Networks">
                                    <i class="material-icons-outlined">expand_more</i>
                                </button>
                            </td>
                            <td>{{ $country->name }}</td>
                            <td><span class="badge bg-secondary">{{ $country->iso_code }}</span></td>
                            <td>+{{ $country->dialcode }}</td>
                            <td>
                                <span class="cost-value" data-field="cost_price_gbp">
                                    {{ $country->cost_price_gbp ? number_format($country->cost_price_gbp, 6) : '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="cost-value" data-field="cost_price_eur">
                                    {{ $country->cost_price_eur ? number_format($country->cost_price_eur, 6) : '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info network-count">{{ $networkCounts[$country->iso_code] ?? 0 }}</span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $country->updated_at ? $country->updated_at->format('d M Y H:i') : 'Never' }}</small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-country-cost"
                                        data-country-id="{{ $country->id }}"
                                        data-country-name="{{ $country->name }}"
                                        data-cost-gbp="{{ $country->cost_price_gbp }}"
                                        data-cost-eur="{{ $country->cost_price_eur }}"
                                        title="Edit Cost">
                                    <i class="material-icons-outlined">edit</i>
                                </button>
                            </td>
                        </tr>
                        <tr class="network-row d-none" data-iso="{{ $country->iso_code }}">
                            <td colspan="9" class="p-0">
                                <div class="network-container bg-light p-3" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Networks for {{ $country->name }}</h6>
                                        <button class="btn btn-sm btn-success add-network-btn"
                                                data-country-code="{{ $country->iso_code }}"
                                                data-country-name="{{ $country->name }}">
                                            <i class="material-icons-outlined me-1">add</i> Add Network
                                        </button>
                                    </div>
                                    <div class="network-list">
                                        <div class="text-center py-3">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                            <span class="ms-2">Loading networks...</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Country Cost Modal -->
<div class="modal fade" id="editCountryCostModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Country Cost</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCountryCostForm">
                <div class="modal-body">
                    <input type="hidden" id="editCountryId" name="country_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Country</label>
                        <p id="editCountryName" class="form-control-plaintext"></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editCostGBP" class="form-label">Cost Price (GBP)</label>
                            <input type="number" class="form-control" id="editCostGBP" name="cost_price_gbp" step="0.000001" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editCostEUR" class="form-label">Cost Price (EUR)</label>
                            <input type="number" class="form-control" id="editCostEUR" name="cost_price_eur" step="0.000001" min="0">
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Leave one empty to auto-calculate using exchange rate ({{ number_format($exchangeRate, 4) }})</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Network Cost Modal -->
<div class="modal fade" id="editNetworkCostModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Network Cost</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editNetworkCostForm">
                <div class="modal-body">
                    <input type="hidden" id="editNetworkId" name="network_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Network</label>
                        <p id="editNetworkName" class="form-control-plaintext"></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editNetworkCostGBP" class="form-label">Cost Price (GBP)</label>
                            <input type="number" class="form-control" id="editNetworkCostGBP" name="cost_price_gbp" step="0.000001" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editNetworkCostEUR" class="form-label">Cost Price (EUR)</label>
                            <input type="number" class="form-control" id="editNetworkCostEUR" name="cost_price_eur" step="0.000001" min="0">
                        </div>
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

<!-- Add Network Modal -->
<div class="modal fade" id="addNetworkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Network Cost</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addNetworkForm">
                <div class="modal-body">
                    <input type="hidden" id="addNetworkCountryCode" name="country_code">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Country</label>
                        <p id="addNetworkCountryName" class="form-control-plaintext"></p>
                    </div>
                    <div class="mb-3">
                        <label for="addNetworkCode" class="form-label">Network Code (MCC+MNC)</label>
                        <input type="text" class="form-control" id="addNetworkCode" name="network_code" placeholder="e.g., 23410">
                    </div>
                    <div class="mb-3">
                        <label for="addNetworkName" class="form-label">Network Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="addNetworkName" name="network_name" required placeholder="e.g., O2">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="addNetworkCostGBP" class="form-label">Cost Price (GBP)</label>
                            <input type="number" class="form-control" id="addNetworkCostGBP" name="cost_price_gbp" step="0.000001" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="addNetworkCostEUR" class="form-label">Cost Price (EUR)</label>
                            <input type="number" class="form-control" id="addNetworkCostEUR" name="cost_price_eur" step="0.000001" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Network</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const exchangeRate = {{ $exchangeRate }};

// Search functionality
document.getElementById('searchCountry').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.country-row').forEach(row => {
        const countryName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const isoCode = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        const dialCode = row.querySelector('td:nth-child(4)').textContent.toLowerCase();

        if (countryName.includes(searchTerm) || isoCode.includes(searchTerm) || dialCode.includes(searchTerm)) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
            // Also hide network row
            const iso = row.dataset.iso;
            document.querySelector(`.network-row[data-iso="${iso}"]`).classList.add('d-none');
        }
    });
});

// Expand/Collapse networks
document.querySelectorAll('.expand-networks').forEach(btn => {
    btn.addEventListener('click', function() {
        const iso = this.dataset.iso;
        const networkRow = document.querySelector(`.network-row[data-iso="${iso}"]`);
        const container = networkRow.querySelector('.network-container');
        const icon = this.querySelector('i');

        if (networkRow.classList.contains('d-none')) {
            networkRow.classList.remove('d-none');
            container.style.display = 'block';
            icon.textContent = 'expand_less';
            loadNetworks(iso);
        } else {
            networkRow.classList.add('d-none');
            container.style.display = 'none';
            icon.textContent = 'expand_more';
        }
    });
});

// Load networks for a country
function loadNetworks(countryCode) {
    const networkList = document.querySelector(`.network-row[data-iso="${countryCode}"] .network-list`);

    fetch(`{{ url('admin/cost/vonage/networks') }}/${countryCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.networks.length > 0) {
                let html = '<table class="table table-sm table-bordered mb-0">';
                html += '<thead><tr><th>Network</th><th>Code</th><th>Cost (GBP)</th><th>Cost (EUR)</th><th>Actions</th></tr></thead>';
                html += '<tbody>';

                data.networks.forEach(network => {
                    html += `<tr>
                        <td>${network.network_name}</td>
                        <td><span class="badge bg-secondary">${network.network_code || '-'}</span></td>
                        <td>${network.cost_price_gbp ? parseFloat(network.cost_price_gbp).toFixed(6) : '-'}</td>
                        <td>${network.cost_price_eur ? parseFloat(network.cost_price_eur).toFixed(6) : '-'}</td>
                        <td>
                            <button class="btn btn-xs btn-outline-primary edit-network-cost"
                                    data-network-id="${network.id}"
                                    data-network-name="${network.network_name}"
                                    data-cost-gbp="${network.cost_price_gbp || ''}"
                                    data-cost-eur="${network.cost_price_eur || ''}">
                                <i class="material-icons-outlined" style="font-size: 14px;">edit</i>
                            </button>
                            <button class="btn btn-xs btn-outline-danger delete-network-cost"
                                    data-network-id="${network.id}"
                                    data-network-name="${network.network_name}">
                                <i class="material-icons-outlined" style="font-size: 14px;">delete</i>
                            </button>
                        </td>
                    </tr>`;
                });

                html += '</tbody></table>';
                networkList.innerHTML = html;

                // Bind edit/delete buttons
                bindNetworkButtons();
            } else {
                networkList.innerHTML = '<p class="text-muted mb-0">No networks found for this country.</p>';
            }
        })
        .catch(error => {
            networkList.innerHTML = '<p class="text-danger mb-0">Failed to load networks.</p>';
            console.error('Error:', error);
        });
}

// Bind network edit/delete buttons
function bindNetworkButtons() {
    document.querySelectorAll('.edit-network-cost').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('editNetworkId').value = this.dataset.networkId;
            document.getElementById('editNetworkName').textContent = this.dataset.networkName;
            document.getElementById('editNetworkCostGBP').value = this.dataset.costGbp || '';
            document.getElementById('editNetworkCostEUR').value = this.dataset.costEur || '';
            new bootstrap.Modal(document.getElementById('editNetworkCostModal')).show();
        });
    });

    document.querySelectorAll('.delete-network-cost').forEach(btn => {
        btn.addEventListener('click', function() {
            const networkId = this.dataset.networkId;
            const networkName = this.dataset.networkName;

            if (confirm(`Are you sure you want to delete network "${networkName}"?`)) {
                deleteNetwork(networkId);
            }
        });
    });
}

// Edit country cost
document.querySelectorAll('.edit-country-cost').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editCountryId').value = this.dataset.countryId;
        document.getElementById('editCountryName').textContent = this.dataset.countryName;
        document.getElementById('editCostGBP').value = this.dataset.costGbp || '';
        document.getElementById('editCostEUR').value = this.dataset.costEur || '';
        new bootstrap.Modal(document.getElementById('editCountryCostModal')).show();
    });
});

// Add network button
document.querySelectorAll('.add-network-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('addNetworkCountryCode').value = this.dataset.countryCode;
        document.getElementById('addNetworkCountryName').textContent = this.dataset.countryName;
        document.getElementById('addNetworkForm').reset();
        document.getElementById('addNetworkCountryCode').value = this.dataset.countryCode;
        new bootstrap.Modal(document.getElementById('addNetworkModal')).show();
    });
});

// Submit edit country form
document.getElementById('editCountryCostForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const countryId = document.getElementById('editCountryId').value;
    const costGBP = document.getElementById('editCostGBP').value;
    const costEUR = document.getElementById('editCostEUR').value;

    fetch(`{{ url('admin/cost/vonage/country') }}/${countryId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            cost_price_gbp: costGBP || null,
            cost_price_eur: costEUR || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('editCountryCostModal')).hide();
            // Update table row
            const row = document.querySelector(`.country-row[data-country-id="${countryId}"]`);
            row.querySelector('[data-field="cost_price_gbp"]').textContent = data.data.cost_price_gbp ? parseFloat(data.data.cost_price_gbp).toFixed(6) : '-';
            row.querySelector('[data-field="cost_price_eur"]').textContent = data.data.cost_price_eur ? parseFloat(data.data.cost_price_eur).toFixed(6) : '-';
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to update cost');
        console.error('Error:', error);
    });
});

// Submit edit network form
document.getElementById('editNetworkCostForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const networkId = document.getElementById('editNetworkId').value;
    const costGBP = document.getElementById('editNetworkCostGBP').value;
    const costEUR = document.getElementById('editNetworkCostEUR').value;

    fetch(`{{ url('admin/cost/vonage/network') }}/${networkId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            cost_price_gbp: costGBP || null,
            cost_price_eur: costEUR || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('editNetworkCostModal')).hide();
            // Reload networks
            const countryCode = document.querySelector('.network-row:not(.d-none)')?.dataset.iso;
            if (countryCode) loadNetworks(countryCode);
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to update network cost');
        console.error('Error:', error);
    });
});

// Submit add network form
document.getElementById('addNetworkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = {
        country_code: document.getElementById('addNetworkCountryCode').value,
        network_code: document.getElementById('addNetworkCode').value,
        network_name: document.getElementById('addNetworkName').value,
        cost_price_gbp: document.getElementById('addNetworkCostGBP').value || null,
        cost_price_eur: document.getElementById('addNetworkCostEUR').value || null
    };

    fetch('{{ route("admin.cost.vonage.network.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('addNetworkModal')).hide();
            // Reload networks
            loadNetworks(formData.country_code);
            // Update network count
            const countBadge = document.querySelector(`.country-row[data-iso="${formData.country_code}"] .network-count`);
            if (countBadge) countBadge.textContent = parseInt(countBadge.textContent) + 1;
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to add network');
        console.error('Error:', error);
    });
});

// Delete network
function deleteNetwork(networkId) {
    fetch(`{{ url('admin/cost/vonage/network') }}/${networkId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            // Reload networks
            const countryCode = document.querySelector('.network-row:not(.d-none)')?.dataset.iso;
            if (countryCode) {
                loadNetworks(countryCode);
                // Update network count
                const countBadge = document.querySelector(`.country-row[data-iso="${countryCode}"] .network-count`);
                if (countBadge) countBadge.textContent = Math.max(0, parseInt(countBadge.textContent) - 1);
            }
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to delete network');
        console.error('Error:', error);
    });
}

// File upload handlers
function submitCountryUpload() {
    const form = document.getElementById('countryUploadForm');
    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            if (data.errors && data.errors.length > 0) {
                console.log('Import errors:', data.errors);
            }
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to upload file');
        console.error('Error:', error);
    });

    // Reset file input
    document.getElementById('countryUpload').value = '';
}

function submitNetworkUpload() {
    const form = document.getElementById('networkUploadForm');
    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            if (data.errors && data.errors.length > 0) {
                console.log('Import errors:', data.errors);
            }
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to upload file');
        console.error('Error:', error);
    });

    // Reset file input
    document.getElementById('networkUpload').value = '';
}

// Toast notification
function showToast(type, message) {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1100';
        document.body.appendChild(toastContainer);
    }

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();

    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}
</script>

<style>
.btn-xs {
    padding: 0.15rem 0.35rem;
    font-size: 0.75rem;
}
.network-container {
    border-top: 2px solid var(--primary-color);
}
.expand-networks i {
    transition: transform 0.2s ease;
}
</style>
@endpush
