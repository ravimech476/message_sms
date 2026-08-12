@extends('admin.layouts.app')

@section('title')
    {{ __('CRM') }}
@endsection

@push('style')
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .country-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.25rem;
        }
        .feature-badge {
            display: inline-block;
            padding: 0.15rem 0.4rem;
            font-size: 0.75rem;
            margin: 0.1rem;
            border-radius: 0.2rem;
        }
        .number-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 1rem;
        }
        .stats-card {
            border-left: 4px solid;
        }
        .pagination {
            margin-bottom: 0;
        }
        .page-link {
            color: #0d6efd;
        }
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        /* Select2 Custom Styles */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 0.375rem 0.75rem;
        }
        /* Change breadcrumb separator to "/" */
        .breadcrumb-item+.breadcrumb-item::before {
            content: " / " !important;
            color: #6c757d !important;
            /* optional grey */
        }
    </style>
@endpush

@section('content')
   <main class="main-wrapper" id="main-wrapper">
        <div class="main-content">
             <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
                <div class="breadcrumb-title pe-3 title-name">Virtual Numbers</div>
                <div class="ps-3">
                       <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Virtual Numbers</li>
                        </ol>
                    </nav>
                    {{-- <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><i class="bx bx-home-alt"></i>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page"><a
                                    href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        </ol>
                    </nav> --}}
                </div>
                <!-- Back Button -->
                <div class="me-2 back-button-container"
                    style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                    <button id="backButton" class="btn btn-primary btn-sm">
                        <i class="bx bx-arrow-back"></i> Back
                    </button>
                </div>
            </div>
            {{-- <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Virtual Numbers</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Virtual Numbers </li>
                        </ol>
                    </nav>
                </div>
            </div> --}}

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error') || $error)
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') ?? $error }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Statistics Cards --}}
            {{-- <div class="row mb-4">
                <div class="col-12 col-lg-3 col-md-6">
                    <div class="card stats-card" style="border-left-color: #0d6efd;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="mb-0 text-secondary">Total Numbers</p>
                                    <h4 class="my-1 text-primary">{{ number_format($totalCount) }}</h4>
                                </div>
                                <div class="widget-icon bg-primary text-white">
                                    <i class="bi bi-telephone-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-lg-3 col-md-6">
                    <div class="card stats-card" style="border-left-color: #198754;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="mb-0 text-secondary">With SMS</p>
                                    <h4 class="my-1 text-success">
                                        {{ collect($virtualNumbers)->filter(fn($n) => is_array($n->features) && in_array('SMS', $n->features))->count() }}
                                    </h4>
                                </div>
                                <div class="widget-icon bg-success text-white">
                                    <i class="bi bi-chat-dots-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-lg-3 col-md-6">
                    <div class="card stats-card" style="border-left-color: #0dcaf0;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="mb-0 text-secondary">With Voice</p>
                                    <h4 class="my-1 text-info">
                                        {{ collect($virtualNumbers)->filter(fn($n) => is_array($n->features) && in_array('VOICE', $n->features))->count() }}
                                    </h4>
                                </div>
                                <div class="widget-icon bg-info text-white">
                                    <i class="bi bi-mic-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-lg-3 col-md-6">
                    <div class="card stats-card" style="border-left-color: #198754;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <p class="mb-0 text-secondary">Active Numbers</p>
                                    <h4 class="my-1 text-success">
                                        {{ collect($virtualNumbers)->filter(fn($n) => $n->is_active)->count() }}
                                    </h4>
                                </div>
                                <div class="widget-icon bg-success text-white">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> --}}

            {{-- Virtual Numbers Table --}}
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul me-2"></i>Virtual Numbers List - ({{ number_format($totalCount) }})
                        </h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('admin.virtual-numbers.create') }}" class="btn btn-success btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Add New Number
                            </a>
                            <form action="{{ route('admin.virtual-numbers.sync') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-arrow-repeat me-1"></i> Sync
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Search and Filters --}}
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <form action="{{ route('admin.virtual-numbers.index') }}" method="GET" class="d-flex gap-2">
                                <input type="text" name="search" class="form-control"
                                    placeholder="Search by Number, Country, or Type..."
                                    value="{{ $search }}">
                                <select name="operator" class="form-select" style="width: 150px;" onchange="this.form.submit()">
                                    <option value="">All Operators</option>
                                    <option value="Nexmo" {{ $operator == 'Nexmo' ? 'selected' : '' }}>Nexmo</option>
                                    <option value="Sinch" {{ $operator == 'Sinch' ? 'selected' : '' }}>Sinch</option>
                                </select>
                                <select name="customer" id="customerFilterSelect" class="form-select" style="width: 260px;">
                                    <option value="">All Customers</option>
                                    @foreach($customerList ?? [] as $cust)
                                        <option value="{{ $cust->bigid }}" {{ $customer == $cust->bigid ? 'selected' : '' }}>
                                            {{ $cust->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <select name="status" class="form-select" style="width: 160px;" onchange="this.form.submit()">
                                    <option value="">All Numbers</option>
                                    <option value="available" {{ ($status ?? '') == 'available' ? 'selected' : '' }}>Available</option>
                                    <option value="assigned"  {{ ($status ?? '') == 'assigned'  ? 'selected' : '' }}>Assigned</option>
                                </select>
                                <input type="hidden" name="per_page" value="{{ $perPage }}">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i>
                                </button>
                                @if($search || $operator || $customer || ($status ?? ''))
                                    <a href="{{ route('admin.virtual-numbers.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                @endif
                            </form>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="{{ route('admin.virtual-numbers.export', ['search' => $search, 'operator' => $operator, 'customer' => $customer, 'status' => $status ?? '']) }}"
                               class="btn btn-success btn-sm me-2" title="Export current filter to Excel/CSV">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
                            </a>
                            <form action="{{ route('admin.virtual-numbers.index') }}" method="GET" class="d-inline-flex align-items-center gap-2">
                                <input type="hidden" name="search" value="{{ $search }}">
                                <input type="hidden" name="operator" value="{{ $operator }}">
                                <input type="hidden" name="customer" value="{{ $customer }}">
                                <input type="hidden" name="status" value="{{ $status ?? '' }}">
                                <label class="mb-0">Show:</label>
                                <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                    <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                </select>
                                <label class="mb-0">entries</label>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle" style="width:100%">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th>Number (MSISDN)</th>
                                    <th>Operator</th>
                                    <th>User</th>
                                    <th>Country</th>
                                    <th>Type</th>
                                    <th>Features</th>
                                    <!-- <th>Status</th> -->
                                    <th>MO HTTP URL</th>
                                    <th>Last Synced</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($virtualNumbers as $number)
                                    <tr>
                                        <td class="number-cell">{{ $number->msisdn ?? 'N/A' }}</td>
                                        <td class="text-center">
                                            @php
                                                $operatorName = $number->operator ?? 'N/A';
                                                $operatorClass = 'bg-secondary';
                                                if ($operatorName === 'Nexmo') {
                                                    $operatorClass = 'bg-primary';
                                                } elseif ($operatorName === 'Sinch') {
                                                    $operatorClass = 'bg-success';
                                                }
                                            @endphp
                                            <span class="badge {{ $operatorClass }}">{{ $operatorName }}</span>
                                        </td>
                                        <td class="number-cell">{{ urldecode($number->busname ?? '') ?: 'N/A' }}</td>
                                        <td class="text-center">
                                            <span class="country-badge bg-primary text-white">
                                                {{ $number->country ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info">
                                                {{ ucwords(str_replace('-', ' ', $number->type ?? 'N/A')) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @if (is_array($number->features) && count($number->features) > 0)
                                                @foreach ($number->features as $feature)
                                                    <span class="feature-badge 
                                                        {{ $feature === 'SMS' ? 'bg-success text-white' : '' }}
                                                        {{ $feature === 'VOICE' ? 'bg-warning text-dark' : '' }}
                                                        {{ $feature === 'MMS' ? 'bg-danger text-white' : '' }}">
                                                        {{ $feature }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>
                                       <!--  <td class="text-center">
                                            @if($number->is_active)
                                                <span class="badge bg-success status-badge">Active</span>
                                            @else
                                                <span class="badge bg-danger status-badge">Inactive</span>
                                            @endif
                                        </td> -->
                                        <td>
                                            @if (!empty($number->mo_http_url))
                                                <a href="{{ $number->mo_http_url }}" target="_blank" 
                                                   class="text-truncate d-inline-block" style="max-width: 200px;"
                                                   title="{{ $number->mo_http_url }}">
                                                    {{ Str::limit($number->mo_http_url, 30) }}
                                                </a>
                                            @else
                                                <span class="text-muted">Not configured</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($number->last_synced_at)
                                                <small>{{ $number->last_synced_at->diffForHumans() }}</small>
                                            @else
                                                <span class="text-muted">Never</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                @if(!empty($number->busname) && $number->busname != 'N/A')
                                                    <button type="button" class="btn btn-outline-warning btn-reassign" 
                                                        data-number="{{ $number->msisdn }}"
                                                        data-current-user="{{ urldecode($number->busname ?? '') }}"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#assignModal"
                                                        title="Reassign to another user">
                                                        <i class="bi bi-arrow-left-right"></i> Reassign
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-outline-success btn-assign" 
                                                        data-number="{{ $number->msisdn }}"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#assignModal"
                                                        title="Assign to a user">
                                                        <i class="bi bi-person-plus"></i> Assign
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-outline-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailsModal{{ $number->id }}">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger"
                                                    onclick="confirmDelete({{ $number->id }}, '{{ $number->msisdn }}')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>

                                            {{-- Details Modal --}}
                                            <div class="modal fade" id="detailsModal{{ $number->id }}" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Number Details - {{ $number->msisdn }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>MSISDN:</strong> {{ $number->msisdn }}</p>
                                                                    <p><strong>Country:</strong> {{ $number->country }}</p>
                                                                    <p><strong>Type:</strong> {{ $number->type }}</p>
                                                                    <p><strong>Status:</strong> 
                                                                        @if($number->is_active)
                                                                            <span class="badge bg-success">Active</span>
                                                                        @else
                                                                            <span class="badge bg-danger">Inactive</span>
                                                                        @endif
                                                                    </p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Features:</strong> {{ is_array($number->features) ? implode(', ', $number->features) : 'None' }}</p>
                                                                    <p><strong>Created:</strong> {{ $number->created_at->format('Y-m-d H:i:s') }}</p>
                                                                    <p><strong>Last Synced:</strong> {{ $number->last_synced_at ? $number->last_synced_at->format('Y-m-d H:i:s') : 'Never' }}</p>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h6>Webhooks</h6>
                                                            <p><strong>MO HTTP URL:</strong> {{ $number->mo_http_url ?? 'Not configured' }}</p>
                                                            
                                                            @if(is_array($number->limitations) && count($number->limitations) > 0)
                                                                <hr>
                                                                <h6>Limitations</h6>
                                                                <ul class="list-group">
                                                                    @foreach ($number->limitations as $limitation)
                                                                        <li class="list-group-item">
                                                                            <strong>Feature:</strong> {{ $limitation['feature'] ?? 'N/A' }}<br>
                                                                            <strong>Type:</strong> {{ $limitation['type'] ?? 'N/A' }}<br>
                                                                            <strong>Reach:</strong> {{ $limitation['reach'] ?? 'N/A' }}
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            No virtual numbers found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Info and Links --}}
                    @if($totalCount > 0)
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <p class="text-muted mb-0">
                                    Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ number_format($totalCount) }} entries
                                    @if($search || $operator || $customer || ($status ?? ''))
                                        <span class="badge bg-info">Filtered</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-end">
                                    {{ $paginator->appends(['search' => $search, 'per_page' => $perPage, 'operator' => $operator, 'customer' => $customer, 'status' => $status ?? ''])->links('pagination::bootstrap-5') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    @include('admin.layouts.footer')

    {{-- Assign/Reassign Modal --}}
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignModalLabel">Assign Virtual Number</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assignForm">
                        <input type="hidden" id="assignNumber" name="number">
                        <input type="hidden" id="assignType" value="assign">
                        
                        <div class="mb-3">
                            <label class="form-label">Virtual Number</label>
                            <input type="text" class="form-control" id="displayNumber" readonly>
                        </div>
                        
                        <div class="mb-3" id="currentUserDiv" style="display: none;">
                            <label class="form-label">Current User</label>
                            <input type="text" class="form-control" id="currentUser" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="selectUser" class="form-label">Select User <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectUser" name="user_bigid" required>
                                <option value="">-- Select User --</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSubmitAssign">Assign</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Form --}}
    <form id="deleteForm" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('js')
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Customer filter dropdown with search (Select2). Auto-submits on change.
            if ($('#customerFilterSelect').length) {
                $('#customerFilterSelect').select2({
                    placeholder: 'All Customers',
                    allowClear: true,
                    width: '260px',
                    theme: 'bootstrap-5'
                }).on('change', function() {
                    this.form.submit();
                });
            }

            // Auto-hide flash messages
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);

            // Load users when modal opens
            $('#assignModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var number = button.data('number');
                var currentUser = button.data('current-user');
                var modal = $(this);
                
                // Set number
                modal.find('#assignNumber').val(number);
                modal.find('#displayNumber').val(number);
                
                // Check if it's assign or reassign
                if (button.hasClass('btn-reassign')) {
                    modal.find('#assignType').val('reassign');
                    modal.find('.modal-title').text('Reassign Virtual Number');
                    modal.find('#btnSubmitAssign').text('Reassign');
                    modal.find('#currentUserDiv').show();
                    modal.find('#currentUser').val(currentUser);
                } else {
                    modal.find('#assignType').val('assign');
                    modal.find('.modal-title').text('Assign Virtual Number');
                    modal.find('#btnSubmitAssign').text('Assign');
                    modal.find('#currentUserDiv').hide();
                }
                
                // Load users
                loadUsers();
            });

            // Destroy Select2 when modal is closed
            $('#assignModal').on('hidden.bs.modal', function () {
                if ($('#selectUser').hasClass('select2-hidden-accessible')) {
                    $('#selectUser').select2('destroy');
                }
            });

            // Submit assign/reassign
            $('#btnSubmitAssign').on('click', function() {
                var type = $('#assignType').val();
                var number = $('#assignNumber').val();
                var userBigid = $('#selectUser').val();
                
                if (!userBigid) {
                    alert('Please select a user');
                    return;
                }
                
                var url = type === 'assign' 
                    ? '{{ route("admin.virtual-numbers.assign") }}' 
                    : '{{ route("admin.virtual-numbers.reassign") }}';
                
                // Disable button
                $(this).prop('disabled', true).text('Processing...');
                
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        number: number,
                        user_bigid: userBigid
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert(response.message || 'Operation failed');
                            $('#btnSubmitAssign').prop('disabled', false).text(type === 'assign' ? 'Assign' : 'Reassign');
                        }
                    },
                    error: function(xhr) {
                        var message = 'Operation failed';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        alert(message);
                        $('#btnSubmitAssign').prop('disabled', false).text(type === 'assign' ? 'Assign' : 'Reassign');
                    }
                });
            });
        });

        function loadUsers() {
            $.ajax({
                url: '{{ route("admin.virtual-numbers.get-users") }}',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        var select = $('#selectUser');
                        select.empty();
                        select.append('<option value="">-- Select User --</option>');
                        
                        $.each(response.users, function(index, user) {
                            select.append(
                                $('<option></option>')
                                    .attr('value', user.bigid)
                                    .text(user.busname + ' (' + user.contactemail + ')')
                            );
                        });
                        
                        // Initialize Select2 with search functionality
                        select.select2({
                            theme: 'bootstrap-5',
                            dropdownParent: $('#assignModal'),
                            placeholder: '-- Select User --',
                            allowClear: true,
                            width: '100%',
                            matcher: function(params, data) {
                                // If there are no search terms, return all data
                                if ($.trim(params.term) === '') {
                                    return data;
                                }

                                // Do not display the item if there is no 'text' property
                                if (typeof data.text === 'undefined') {
                                    return null;
                                }

                                // `params.term` is the user's search term
                                var term = params.term.toLowerCase();
                                var text = data.text.toLowerCase();

                                // Check if the text contains the term
                                if (text.indexOf(term) > -1) {
                                    return data;
                                }

                                // Return `null` if the term should not be displayed
                                return null;
                            }
                        });
                    }
                },
                error: function() {
                    alert('Failed to load users');
                }
            });
        }

        function confirmDelete(id, msisdn) {
            if (confirm(`Are you sure you want to cancel and delete number ${msisdn}? This action cannot be undone and will also cancel the number from the provider (Nexmo/Sinch).`)) {
                const form = document.getElementById('deleteForm');
                form.action = `/admin/virtual-numbers/${id}`;
                form.submit();
            }
        }
    </script>
@endpush
