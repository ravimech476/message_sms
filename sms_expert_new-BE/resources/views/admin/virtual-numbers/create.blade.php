@extends('admin.layouts.app')

@section('title', 'Add New Virtual Number')

@push('style')
    <style>
        .number-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .number-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .number-card.selected {
            border-color: #0d6efd;
            background-color: #e7f3ff;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.show {
            display: flex;
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
                <div class="breadcrumb-title pe-3 title-name">Add New Virtual Number</div>
                <div class="ps-3">
                       <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0" style="background: none;">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Virtual Numbers</li>
                            <li class="breadcrumb-item active" aria-current="page">Add New Virtual Number</li>
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
                <div class="breadcrumb-title pe-3">Add New Virtual Number</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('admin.virtual-numbers.index') }}">Virtual Numbers</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Add New</li>
                        </ol>
                    </nav>
                </div>
            </div> --}}

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" id="flash-message" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show"  id="flash-error-message" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Search Form --}}
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-search me-2"></i>Search Available Numbers
                    </h5>
                    <form id="searchForm">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label for="provider" class="form-label">Provider <span class="text-danger">*</span></label>
                                <select class="form-select" id="provider" name="provider" required>
                                    <option value="nexmo" selected>Nexmo</option>
                                    <option value="sinch">Sinch</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="country" class="form-label">Country Code <span class="text-danger">*</span></label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="">Select Country</option>
                                    <option value="GB">GB - United Kingdom</option>
                                    <option value="US">US - United States</option>
                                    <option value="CA">CA - Canada</option>
                                    <option value="AU">AU - Australia</option>
                                    <option value="DE">DE - Germany</option>
                                    <option value="FR">FR - France</option>
                                    <option value="ES">ES - Spain</option>
                                    <option value="IT">IT - Italy</option>
                                    <option value="NL">NL - Netherlands</option>
                                    <option value="BE">BE - Belgium</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="type" class="form-label">Number Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="mobile-lvn">Mobile LVN</option>
                                    <option value="landline">Landline</option>
                                    <option value="landline-toll-free">Toll Free</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="features" class="form-label">Features</label>
                                <select class="form-select" id="features" name="features">
                                    <option value="SMS,VOICE">SMS + Voice</option>
                                    <option value="SMS">SMS Only</option>
                                    <option value="VOICE">Voice Only</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i> Search Numbers
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- UK Mobile Numbers Warning for Sinch --}}
                    <div id="ukSinchWarning" class="alert alert-warning mt-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Important:</strong> UK mobile numbers from Sinch require compliance documentation (KYC) to be uploaded and approved in your
                        <a href="https://dashboard.sinch.com" target="_blank" class="alert-link">Sinch Dashboard</a> before purchase.
                        If you haven't uploaded the required documents, purchases will fail with a "not available" error.
                    </div>
                </div>
            </div>

            {{-- Manual Add Form --}}
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-plus-circle me-2"></i>Manually Add Number (Already Purchased from Provider)
                    </h5>
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Use this form to add numbers you have <strong>already purchased directly</strong> from the
                        <a href="https://dashboard.nexmo.com" target="_blank" class="alert-link">Nexmo Dashboard</a> or
                        <a href="https://dashboard.sinch.com" target="_blank" class="alert-link">Sinch Dashboard</a>.
                        This will NOT call the provider's purchase API - it only adds the number to your local database.
                    </div>
                    <form id="manualAddForm" method="POST" action="{{ route('admin.virtual-numbers.store-manual') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="manual_msisdn" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="manual_msisdn" name="msisdn"
                                       placeholder="e.g. 447123456789" required minlength="10" maxlength="15">
                                <small class="text-muted">Enter without + prefix (e.g., 447123456789)</small>
                            </div>
                            <div class="col-md-2">
                                <label for="manual_provider" class="form-label">Provider <span class="text-danger">*</span></label>
                                <select class="form-select" id="manual_provider" name="provider" required>
                                    <option value="nexmo">Nexmo</option>
                                    <option value="sinch">Sinch</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="manual_country" class="form-label">Country <span class="text-danger">*</span></label>
                                <select class="form-select" id="manual_country" name="country" required>
                                    <option value="">Select</option>
                                    <option value="GB">GB - United Kingdom</option>
                                    <option value="US">US - United States</option>
                                    <option value="CA">CA - Canada</option>
                                    <option value="AU">AU - Australia</option>
                                    <option value="DE">DE - Germany</option>
                                    <option value="FR">FR - France</option>
                                    <option value="ES">ES - Spain</option>
                                    <option value="IT">IT - Italy</option>
                                    <option value="NL">NL - Netherlands</option>
                                    <option value="BE">BE - Belgium</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="manual_type" class="form-label">Number Type</label>
                                <select class="form-select" id="manual_type" name="type">
                                    <option value="mobile-lvn">Mobile LVN</option>
                                    <option value="landline">Landline</option>
                                    <option value="landline-toll-free">Toll Free</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="manual_features" class="form-label">Features</label>
                                <select class="form-select" id="manual_features" name="features">
                                    <option value="SMS,VOICE">SMS + Voice</option>
                                    <option value="SMS">SMS Only</option>
                                    <option value="VOICE">Voice Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="configure_webhook" name="configure_webhook" value="1" checked>
                                    <label class="form-check-label" for="configure_webhook">
                                        <strong>Configure Webhook for Incoming SMS</strong>
                                    </label>
                                    <small class="d-block text-muted">
                                        This will call the provider's API to set up the inbound SMS webhook URL.
                                    </small>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <small>
                                            <strong>Nexmo Webhook:</strong> <code>{{ $nexmoWebhookUrl }}</code><br>
                                            <strong>Sinch Webhook:</strong> <code>{{ $sinchWebhookUrl }}</code>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to add this number manually? Make sure you have already purchased this number from the provider dashboard.');">
                                    <i class="bi bi-plus-circle me-1"></i> Add Number Manually
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Search Results --}}
            <div id="searchResults" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-list-ul me-2"></i>Available Numbers
                        </h5>
                        <div id="numbersList" class="row g-3">
                            <!-- Numbers will be loaded here -->
                        </div>
                        <div id="noResults" style="display: none;" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted">No numbers found matching your criteria.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Purchase Form --}}
            <form id="purchaseForm" method="POST" action="{{ route('admin.virtual-numbers.store') }}" style="display: none;">
                @csrf
                <input type="hidden" name="country" id="purchase_country">
                <input type="hidden" name="msisdn" id="purchase_msisdn">
                <input type="hidden" name="provider" id="purchase_provider">
            </form>
        </div>
    </main>

    @include('admin.layouts.footer')

    {{-- Loading Overlay --}}
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-light mt-3">Searching available numbers...</p>
        </div>
    </div>
@endsection

@push('js')
    <script>
        var currentProvider = 'nexmo';

        $(document).ready(function() {
            // Show/hide UK Sinch warning based on provider and country selection
            function updateUkWarning() {
                const provider = $('#provider').val();
                const country = $('#country').val();
                if (provider === 'sinch' && country === 'GB') {
                    $('#ukSinchWarning').slideDown();
                } else {
                    $('#ukSinchWarning').slideUp();
                }
            }

            // Bind change events for provider and country
            $('#provider, #country').on('change', updateUkWarning);

            // Auto-detect country from phone number for manual form
            $('#manual_msisdn').on('input', function() {
                const msisdn = $(this).val().replace(/\D/g, '');
                const countryPrefixes = {
                    '44': 'GB',   // United Kingdom
                    '1': 'US',    // United States / Canada (will default to US)
                    '61': 'AU',   // Australia
                    '49': 'DE',   // Germany
                    '33': 'FR',   // France
                    '34': 'ES',   // Spain
                    '39': 'IT',   // Italy
                    '31': 'NL',   // Netherlands
                    '32': 'BE',   // Belgium
                };

                // Check for matching country prefix
                for (const [prefix, country] of Object.entries(countryPrefixes)) {
                    if (msisdn.startsWith(prefix)) {
                        $('#manual_country').val(country);
                        break;
                    }
                }
            });

            // Update webhook URL display based on provider selection (manual form)
            const nexmoWebhookUrl = '{{ $nexmoWebhookUrl }}';
            const sinchWebhookUrl = '{{ $sinchWebhookUrl }}';

            $('#manual_provider').on('change', function() {
                const provider = $(this).val();
                let webhookUrl = (provider === 'sinch') ? sinchWebhookUrl : nexmoWebhookUrl;
                $('#webhookUrlDisplay').text(webhookUrl);
            });

            // Search form submission
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();

                const provider = $('#provider').val();
                const country = $('#country').val();
                const type = $('#type').val();
                const features = $('#features').val();

                if (!country) {
                    alert('Please select a country');
                    return;
                }

                currentProvider = provider;

                // Show loading overlay
                $('#loadingOverlay').addClass('show');

                // Make AJAX request
                $.ajax({
                    url: '{{ route('admin.virtual-numbers.search') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        provider: provider,
                        country: country,
                        type: type,
                        features: features
                    },
                    success: function(response) {
                        $('#loadingOverlay').removeClass('show');

                        if (response.success && response.numbers && response.numbers.length > 0) {
                            displayNumbers(response.numbers, country, provider);
                            $('#searchResults').show();
                            $('#noResults').hide();
                        } else {
                            $('#searchResults').show();
                            $('#numbersList').empty();
                            $('#noResults').show();
                        }
                    },
                    error: function(xhr) {
                        $('#loadingOverlay').removeClass('show');
                        var message = 'Error searching for numbers. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        alert(message);
                        console.error(xhr);
                    }
                });
            });

            // Display numbers
            function displayNumbers(numbers, country, provider) {
                const numbersList = $('#numbersList');
                numbersList.empty();

                const providerLabel = provider === 'sinch' ? 'Sinch' : 'Nexmo';
                const providerBadge = provider === 'sinch' ? 'bg-info' : 'bg-primary';

                numbers.forEach(function(number) {
                    const features = number.features ? number.features.join(', ') : 'N/A';
                    const type = number.type ? number.type.replace(/-/g, ' ').toUpperCase() : 'N/A';
                    const currency = number.currency || 'GBP';
                    const currencySymbol = currency === 'GBP' ? '£' : (currency === 'EUR' ? '€' : '$');

                    // Format costs
                    let costDisplay = 'Contact support';
                    if (number.cost) {
                        costDisplay = `${currencySymbol}${parseFloat(number.cost).toFixed(2)} / month`;
                        if (number.setup_cost) {
                            costDisplay += ` + ${currencySymbol}${parseFloat(number.setup_cost).toFixed(2)} setup`;
                        }
                    }

                    // Documentation required badge
                    const docRequired = number.supporting_documentation_required
                        ? '<span class="badge bg-warning text-dark ms-2">Docs Required</span>'
                        : '';

                    const card = `
                        <div class="col-md-6 col-lg-4">
                            <div class="number-card p-3" onclick="selectNumber('${country}', '${number.msisdn}', '${provider}')">
                                <h5 class="mb-2">${number.msisdn}${docRequired}</h5>
                                <p class="mb-1"><strong>Provider:</strong> <span class="badge ${providerBadge}">${providerLabel}</span></p>
                                <p class="mb-1"><strong>Country:</strong> ${number.country}</p>
                                <p class="mb-1"><strong>Type:</strong> ${type}</p>
                                <p class="mb-1"><strong>Features:</strong>
                                    ${number.features.map(f => `<span class="badge bg-${f === 'SMS' ? 'success' : 'warning'}">${f}</span>`).join(' ')}
                                </p>
                                <p class="mb-0"><strong>Cost:</strong> ${costDisplay}</p>
                                <button type="button" class="btn btn-sm btn-primary mt-2 w-100">
                                    <i class="bi bi-cart-plus me-1"></i> Buy This Number
                                </button>
                            </div>
                        </div>
                    `;
                    numbersList.append(card);
                });
            }
        });

        // Select and purchase number
        function selectNumber(country, msisdn, provider) {
            const providerLabel = provider === 'sinch' ? 'Sinch' : 'Nexmo';

            // Build confirmation message
            let confirmMsg = `Are you sure you want to purchase number ${msisdn} from ${providerLabel}?\n\nThis will be added to your account and webhook will be configured automatically.`;

            // Add warning for UK numbers from Sinch
            if (provider === 'sinch' && country === 'GB') {
                confirmMsg += '\n\nIMPORTANT: UK mobile numbers from Sinch require compliance documentation (KYC) to be uploaded and approved in your Sinch Dashboard before purchase. If documentation is not uploaded, this purchase will fail.';
            }

            if (confirm(confirmMsg)) {
                $('#purchase_country').val(country);
                $('#purchase_msisdn').val(msisdn);
                $('#purchase_provider').val(provider);
                $('#loadingOverlay').addClass('show');
                $('#purchaseForm').submit();
            }
        }

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
