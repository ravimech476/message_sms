<!-- Post-Pay Report Tab Content -->
<div class="filter-card p-3">

    <!-- ================= Row 1 ================= -->
    <div class="row g-3 mb-3">

        <!-- Search -->
        <div class="col-12 col-lg-4">
            <label class="form-label">Search</label>
            <input type="text"
                   class="form-control"
                   id="postpay-search"
                   placeholder="Search by customer name...">
        </div>

        <!-- Customer -->
        <div class="col-12 col-lg-8">
            <label class="form-label">Customer</label>
            <select class="form-select customer-select"
                    id="postpay-customer"
                    multiple="multiple">
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">
                        {{ urldecode($customer->busname) }} ({{ $customer->uname }})
                    </option>
                @endforeach
            </select>
        </div>

    </div>


    <!-- ================= Row 2 ================= -->
    <div class="row g-3 align-items-end">

        <!-- Date From -->
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label">Date From</label>
            <input type="date"
                   class="form-control date-box"
                   id="postpay-date-from"
                   value="{{ now()->format('Y-m-d') }}">
        </div>

        <!-- Date To -->
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label">Date To</label>
            <input type="date"
                   class="form-control date-box"
                   id="postpay-date-to"
                   value="{{ now()->format('Y-m-d') }}">
        </div>

        <!-- Search Button -->
        <div class="col-12 col-sm-6 col-lg-3">
            <button type="button"
                    class="btn btn-filter w-100 btn-nowrap"
                    onclick="filterPostPay()">
                <i class="bx bx-search"></i> Search
            </button>
        </div>

        <!-- Reset Button -->
        <div class="col-12 col-sm-6 col-lg-3">
            <button type="button"
                    class="btn btn-reset w-100 btn-nowrap"
                    onclick="resetPostPayFilters()">
                {{-- <i class="material-icons-outlined" style="font-size:18px;">refresh</i> --}}
                Reset
            </button>
        </div>

    </div>

</div>

<!-- Old Code -->
{{-- <div class="filter-card">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" id="postpay-search" placeholder="Search by customer name...">
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label">Customer</label>
            <select class="form-select customer-select" id="postpay-customer" multiple="multiple">
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ urldecode($customer->busname) }} ({{ $customer->uname }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Date From</label>
            <input type="date" class="form-control" id="postpay-date-from">
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label">Date To</label>
            <input type="date" class="form-control" id="postpay-date-to">
        </div>
        <div class="col-12 col-md-2">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-filter" onclick="filterPostPay()">
                    <i class="bx bx-search"></i> Search
                </button>
                <button type="button" class="btn btn-reset" onclick="resetPostPayFilters()">
                    <i class="material-icons-outlined" style="font-size: 18px;">refresh</i>
                </button>
            </div>
        </div>
    </div>
</div> --}}

<!-- Export Buttons and Stats Row -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <button type="button" class="btn btn-export" onclick="exportPostPay(false)">
            <i class="bx bx-download"></i> Export Filtered Data
        </button>
        <button type="button" class="btn btn-export-all" onclick="exportPostPay(true)">
            <i class="bx bx-cloud-download"></i> Export All Data
        </button>
        <div class="stat-badge">
            <i class="bx bx-data"></i>
            <span>Total Records: <strong id="postpay-total-records">0</strong></span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label class="mb-0">Per Page:</label>
        <select class="form-select per-page-select" onchange="changePostPayPerPage(this.value)">
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="250">250</option>
        </select>
    </div>
</div>

<!-- Table Container -->
<div class="table-container position-relative" id="postpay-table-container">
    <div class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-bordered" id="postpay-table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Customer Name</th>
                    <th>Contact Name</th>
                    <th>Username</th>
                    <th class="text-end">Total SMS</th>
                    <th class="text-end">Per SMS Rate</th>
                    <th class="text-end">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" class="no-data">
                        <i class="bx bx-loader-alt bx-spin"></i>
                        Loading data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination-container" id="postpay-pagination"></div>
</div>
