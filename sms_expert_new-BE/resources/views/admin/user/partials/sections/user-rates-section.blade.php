<div class="card mb-4 border">
    <div class="card-body">
        <h5 class="card-title mb-4">User SMS Rates - Global Margin Configuration</h5>

        {{-- Global Margin Setting Form --}}
        <div class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-12">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Global Margin:</strong> Set one margin percentage that applies to all countries
                        automatically.
                        The system will calculate: <strong>User Rate = Base Cost + (Base Cost × Margin %)</strong>
                    </div>
                </div>
            </div>


            <form method="POST" action="{{ route('admin.user.margin.update', $record->id) }}"
                class="row g-3 align-items-end">
                @csrf
                @method('PUT')
                <div class="col-md-6">
                    <label for="margin_percentage" class="form-label fw-semibold">
                        Global Margin Percentage (%)
                    </label>
                    <div class="input-group">
                        <input type="number" class="form-control form-control-lg" id="margin_percentage"
                            name="margin_percentage" step="0.01" min="0" max="1000"
                            value="{{ old('margin_percentage', $userMargin->margin_percentage ?? 0) }}"
                            placeholder="e.g., 20.00" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <small class="text-muted">
                        This percentage will be added to the base cost for all countries
                    </small>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            {{ old('is_active', $userMargin->is_active ?? 1) ? 'checked' : '' }}>
                        {{-- <label class="form-check-label" for="is_active">
                            Active
                        </label> --}}
                        <label class="form-check-label" for="is_active">
                            {{ isset($userMargin) && $userMargin->is_active ? 'Active' : 'Inactive' }}
                        </label>

                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 btn-sm">
                        <i class="bi bi-save"></i> Save Margin
                    </button>
                </div>
            </form>
        </div>

        {{-- Current Margin Display --}}
        <div
            class="alert alert-{{ isset($userMargin) && $userMargin->is_active ? 'success' : 'warning' }} d-flex justify-content-between align-items-center mb-3">
            <div>
                <strong>Current Margin:</strong>
                <span class="fs-5">{{ number_format($userMargin->margin_percentage ?? 0, 2) }}%</span>
                <span
                    class="badge {{ isset($userMargin) && $userMargin->is_active ? 'bg-success' : 'bg-secondary' }} ms-2">
                    {{ isset($userMargin) && $userMargin->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div>
                <small class="text-muted">
                    Last Updated: {{ $userMargin->updated_at ?? 'Never' }}
                </small>
            </div>
        </div>

        {{-- Search and Filter --}}
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" id="searchCountry"
                    placeholder="Search by country name or dial code...">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="currencyFilter">
                    <option value="">All Currencies</option>
                    <option value="eur">EUR</option>
                    <option value="gbp">GBP</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="exportToCSV()">
                    <i class="bi bi-download"></i> Export CSV
                </button>
            </div>
        </div>

        {{-- All Countries Rate Table --}}
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm" id="countriesTable">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="20%">Country</th>
                        <th width="10%">Dial Code</th>
                        <th width="12%">Base Cost (EUR)</th>
                        <th width="12%">Base Cost (GBP)</th>
                        <th width="12%">Exchange Rate</th>
                        <th width="12%">User Rate (GBP)</th>
                        <th width="12%">Margin Amount</th>
                        <th width="5%">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $marginPercentage = $userMargin->margin_percentage ?? 0;
                        $isActive = isset($userMargin) && $userMargin->is_active ? true : false;

                        // Get SINGLE exchange rate (latest from database)
                        $globalExchangeRate = \Illuminate\Support\Facades\DB::table('country')
                            ->whereNotNull('exchange_rate_eur_to_gbp')
                            ->where('exchange_rate_eur_to_gbp', '>', 0)
                            ->orderBy('updated_at', 'desc')
                            ->value('exchange_rate_eur_to_gbp') ?? 0.85;
                    @endphp
                    @forelse($countries as $index => $country)
                        @php
                            // Get base cost EUR
                            $baseCostEUR = $country->cost_price_eur ?? ($country->cost_per_sms ?? 0);

                            // Use SINGLE global exchange rate for all countries
                            $exchangeRate = $globalExchangeRate;

                            // Convert EUR → GBP using single exchange rate
                            $baseCostGBP = $baseCostEUR * $exchangeRate;

                            // Calculate user rate with margin
                            $marginAmount = $baseCostGBP * ($marginPercentage / 100);
                            $userRate = $baseCostGBP + $marginAmount;
                        @endphp
                        <tr class="country-row" data-country="{{ strtolower($country->name) }}"
                            data-dialcode="{{ $country->dialcode }}">
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $country->name }}</strong>
                                @if ($country->iso_code)
                                    <span class="badge bg-secondary">{{ $country->iso_code }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-primary">+{{ $country->dialcode }}</span>
                            </td>
                            <td class="text-end">€{{ number_format($baseCostEUR, 4) }}</td>
                            <td class="text-end">£{{ number_format($baseCostGBP, 4) }}</td>
                            <td class="text-center">
                                @if ($exchangeRate > 0)
                                    {{ number_format($exchangeRate, 4) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <strong class="text-{{ $isActive ? 'success' : 'muted' }}">
                                    £{{ number_format($userRate, 4) }}
                                </strong>
                            </td>
                            <td class="text-end">
                                <span class="text-success">
                                    +£{{ number_format($marginAmount, 4) }}
                                    <small class="text-muted">({{ number_format($marginPercentage, 2) }}%)</small>
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($baseCostGBP > 0)
                                    <i class="bi bi-check-circle-fill text-success" title="Active"></i>
                                @else
                                    <i class="bi bi-exclamation-circle-fill text-warning" title="No pricing data"></i>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No countries found in the database.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Quick Stats --}}
        <div class="row mt-3">
            <div class="col-md-3">
                <div class="alert alert-light mb-0">
                    <strong>Total Countries:</strong> {{ $countries->count() }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-light mb-0">
                    <strong>Current Margin:</strong> {{ number_format($marginPercentage, 2) }}%
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-light mb-0">
                    <strong>Countries with Pricing:</strong> {{ $countries->where('cost_price_gbp', '>', 0)->count() }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="alert alert-light mb-0">
                    <strong>Status:</strong>
                    <span class="badge {{ $isActive ? 'bg-success' : 'bg-secondary' }}">
                        {{ $isActive ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Calculation Example --}}
        <div class="alert alert-light mt-3">
            <h6 class="alert-heading"><i class="bi bi-calculator"></i> Calculation Example:</h6>
            <p class="mb-0">
                If Base Cost (GBP) = £0.05 and Margin = {{ number_format($marginPercentage, 2) }}%<br>
                Then: User Rate = £0.05 + (£0.05 × {{ number_format($marginPercentage, 2) }}%) =
                £{{ number_format(0.05 + 0.05 * ($marginPercentage / 100), 4) }}
            </p>
        </div>
    </div>
</div>

{{-- JavaScript for Search and Export --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.getElementById('searchCountry');
        const currencyFilter = document.getElementById('currencyFilter');
        const tableRows = document.querySelectorAll('.country-row');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const currency = currencyFilter.value;

            tableRows.forEach(row => {
                const country = row.dataset.country;
                const dialcode = row.dataset.dialcode;
                const matchesSearch = country.includes(searchTerm) || dialcode.includes(searchTerm);

                if (matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('keyup', filterTable);
        currencyFilter.addEventListener('change', filterTable);
    });

    // Export to CSV
    function exportToCSV() {
        const table = document.getElementById('countriesTable');
        const rows = table.querySelectorAll('tr');
        let csv = [];

        rows.forEach(row => {
            const cells = row.querySelectorAll('th, td');
            const rowData = Array.from(cells).map(cell => {
                let text = cell.innerText.trim();
                // Remove special characters for CSV
                text = text.replace(/"/g, '""');
                return `"${text}"`;
            });
            csv.push(rowData.join(','));
        });

        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', 'user_sms_rates_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<style>
    .table-sm td,
    .table-sm th {
        padding: 0.3rem;
        font-size: 0.875rem;
    }

    .country-row:hover {
        background-color: #f8f9fa;
    }

    #searchCountry {
        border: 2px solid #dee2e6;
    }

    #searchCountry:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .alert-light {
        border: 1px solid #dee2e6;
    }
</style>
