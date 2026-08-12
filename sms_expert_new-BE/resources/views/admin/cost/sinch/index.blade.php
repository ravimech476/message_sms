@extends('admin.layouts.modern-app')

@section('title', 'Sinch Cost Management - SMS Expert Admin')

@section('content')
<div class="page-content">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title mb-1">Sinch Cost Management</h1>
                <p class="text-muted mb-0">Manage country-wise SMS cost prices for Sinch</p>
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
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.cost.sinch.template') }}" class="btn btn-outline-primary">
                    <i class="material-icons-outlined me-1">download</i> Download Template
                </a>
                <a href="{{ route('admin.cost.sinch.export') }}" class="btn btn-outline-success">
                    <i class="material-icons-outlined me-1">file_download</i> Export Current Data
                </a>
                <form id="sinchUploadForm" action="{{ route('admin.cost.sinch.import') }}" method="POST" enctype="multipart/form-data" class="d-inline">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.xls" class="d-none" id="sinchUpload" onchange="submitSinchUpload()">
                    <label for="sinchUpload" class="btn btn-primary mb-0" style="cursor: pointer;">
                        <i class="material-icons-outlined me-1">upload</i> Upload Sinch Costs
                    </label>
                </form>
            </div>
        </div>
    </div>

    <!-- Country Costs Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="material-icons-outlined me-2">public</i>Sinch Country Cost Prices</h5>
            <div class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" id="searchCountry" placeholder="Search country..." style="width: 200px;">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="sinchCostTable">
                    <thead>
                        <tr>
                            <th>Country</th>
                            <th>ISO Code</th>
                            <th>Dial Code</th>
                            <th>Sinch Cost (GBP)</th>
                            <th>Sinch Cost (EUR)</th>
                            <th>Updated</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countries as $country)
                        <tr class="country-row" data-country-id="{{ $country->id }}" data-iso="{{ $country->iso_code }}">
                            <td>{{ $country->name }}</td>
                            <td><span class="badge bg-secondary">{{ $country->iso_code }}</span></td>
                            <td>+{{ $country->dialcode }}</td>
                            <td>
                                <span class="cost-value" data-field="sinch_cost_price_gbp">
                                    {{ $country->sinch_cost_price_gbp ? number_format($country->sinch_cost_price_gbp, 6) : '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="cost-value" data-field="sinch_cost_price_eur">
                                    {{ $country->sinch_cost_price_eur ? number_format($country->sinch_cost_price_eur, 6) : '-' }}
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">{{ $country->sinch_price_updated_at ? \Carbon\Carbon::parse($country->sinch_price_updated_at)->format('d M Y H:i') : 'Never' }}</small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-sinch-cost"
                                        data-country-id="{{ $country->id }}"
                                        data-country-name="{{ $country->name }}"
                                        data-cost-gbp="{{ $country->sinch_cost_price_gbp }}"
                                        data-cost-eur="{{ $country->sinch_cost_price_eur }}"
                                        title="Edit Cost">
                                    <i class="material-icons-outlined">edit</i>
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
                    <input type="hidden" id="editCountryId" name="country_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Country</label>
                        <p id="editCountryName" class="form-control-plaintext"></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editSinchCostGBP" class="form-label">Sinch Cost (GBP)</label>
                            <input type="number" class="form-control" id="editSinchCostGBP" name="sinch_cost_price_gbp" step="0.000001" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSinchCostEUR" class="form-label">Sinch Cost (EUR)</label>
                            <input type="number" class="form-control" id="editSinchCostEUR" name="sinch_cost_price_eur" step="0.000001" min="0">
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
@endsection

@push('scripts')
<script>
const exchangeRate = {{ $exchangeRate }};

// Search functionality
document.getElementById('searchCountry').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.country-row').forEach(row => {
        const countryName = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
        const isoCode = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const dialCode = row.querySelector('td:nth-child(3)').textContent.toLowerCase();

        if (countryName.includes(searchTerm) || isoCode.includes(searchTerm) || dialCode.includes(searchTerm)) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });
});

// Edit Sinch cost
document.querySelectorAll('.edit-sinch-cost').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editCountryId').value = this.dataset.countryId;
        document.getElementById('editCountryName').textContent = this.dataset.countryName;
        document.getElementById('editSinchCostGBP').value = this.dataset.costGbp || '';
        document.getElementById('editSinchCostEUR').value = this.dataset.costEur || '';
        new bootstrap.Modal(document.getElementById('editSinchCostModal')).show();
    });
});

// Submit edit form
document.getElementById('editSinchCostForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const countryId = document.getElementById('editCountryId').value;
    const costGBP = document.getElementById('editSinchCostGBP').value;
    const costEUR = document.getElementById('editSinchCostEUR').value;

    fetch(`{{ url('admin/cost/sinch/country') }}/${countryId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            sinch_cost_price_gbp: costGBP || null,
            sinch_cost_price_eur: costEUR || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('editSinchCostModal')).hide();
            // Update table row
            const row = document.querySelector(`.country-row[data-country-id="${countryId}"]`);
            row.querySelector('[data-field="sinch_cost_price_gbp"]').textContent = data.data.sinch_cost_price_gbp ? parseFloat(data.data.sinch_cost_price_gbp).toFixed(6) : '-';
            row.querySelector('[data-field="sinch_cost_price_eur"]').textContent = data.data.sinch_cost_price_eur ? parseFloat(data.data.sinch_cost_price_eur).toFixed(6) : '-';
        } else {
            showToast('error', data.message);
        }
    })
    .catch(error => {
        showToast('error', 'Failed to update cost');
        console.error('Error:', error);
    });
});

// File upload handler
function submitSinchUpload() {
    const form = document.getElementById('sinchUploadForm');
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
    document.getElementById('sinchUpload').value = '';
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
@endpush
