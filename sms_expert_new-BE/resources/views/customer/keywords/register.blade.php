@extends('layouts.app')
@section('title', 'Register New Keyword - SMS Expert')

@push('style')
    <style>
        .register-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
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

        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
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

        .alert-info {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
        }

        .alert-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .main-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .main-card::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .card-header-custom {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .card-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body-custom {
            padding: 2rem;
        }

        .info-box {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .info-box p {
            color: #92400e;
            margin: 0;
            line-height: 1.6;
        }

        .info-box strong {
            color: #78350f;
        }

        .step-header {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-box {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-control-custom {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control-custom:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 3px rgba(234, 97, 24, 0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(8, 145, 178, 0.4);
            color: white;
        }

        .btn-register {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.4);
            color: white;
        }

        .btn-register:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .search-result {
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .search-result.available {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border: 2px solid #16a34a;
        }

        .search-result.unavailable {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            border: 2px solid #dc2626;
        }

        .search-result.error {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
        }

        .keyword-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #293b50;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            border: 2px solid #ea6118;
            display: inline-block;
            margin: 1rem 0;
        }

        .register-form {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .success-card {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border: 2px solid #16a34a;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }

        .success-card h3 {
            color: #15803d;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .success-card p {
            color: #166534;
            margin-bottom: 0.5rem;
        }

        .keyword-details {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .keyword-details-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .keyword-details-item:last-child {
            border-bottom: none;
        }

        .keyword-details-label {
            color: #64748b;
            font-weight: 500;
        }

        .keyword-details-value {
            color: #293b50;
            font-weight: 600;
        }

        .no-access-card {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            border: 2px solid #dc2626;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }

        .no-access-card h4 {
            color: #dc2626;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .no-access-card p {
            color: #991b1b;
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endpush

@section('content')
    <div class="register-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">add_circle</i>
                    Register New Keyword
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('keywords') }}">Keywords</a>
                        </li>
                        <li class="breadcrumb-item active">Register</li>
                    </ol>
                </nav>
            </div>
            <a href="{{ route('keywords') }}" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back to Keywords
            </a>
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

        @if (isset($keywordCreated) && $keywordCreated)
            <!-- Success State - Keyword Created -->
            <div class="success-card">
                <i class="material-icons-outlined" style="font-size: 4rem; color: #16a34a;">check_circle</i>
                <h3>New SMS Keyword Created!</h3>
                <p>Congratulations! Your new SMS keyword has been created successfully.</p>
                <p>We have sent you an email to confirm the details.</p>
                
                <div class="keyword-details">
                    <div class="keyword-details-item">
                        <span class="keyword-details-label">Your Keyword:</span>
                        <span class="keyword-details-value text-uppercase">{{ $createdKeyword ?? '' }}</span>
                    </div>
                    <div class="keyword-details-item">
                        <span class="keyword-details-label">Shortcode:</span>
                        <span class="keyword-details-value">{{ $shortcode ?? '60300' }}</span>
                    </div>
                    <div class="keyword-details-item">
                        <span class="keyword-details-label">Expires:</span>
                        <span class="keyword-details-value">{{ $expiryDate ?? '' }}</span>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-muted">Your new keyword is all set up and ready to use. You can configure it from the Manage Keywords page.</p>
                    <p><strong>Try it now!</strong> Send a text with your keyword to {{ $shortcode ?? '60300' }}.</p>
                </div>

                <div class="mt-4">
                    <a href="{{ route('keywords') }}" class="btn btn-register">
                        <i class="material-icons-outlined me-1">settings</i>
                        Manage Keywords
                    </a>
                </div>
            </div>
        @elseif (!$canRegister)
            <!-- No Access State -->
            <div class="no-access-card">
                <i class="material-icons-outlined" style="font-size: 4rem; color: #dc2626;">block</i>
                <h4>Unable to Register Keywords</h4>
                
                @if (!$isLoggedIn)
                    <p>Please log in to register a new keyword.</p>
                @elseif ($keywordsLeft < 1)
                    {{-- OLD SYSTEM parity (infopage_include_detail2.inc:6713) --}}
                    <p>This page is reserved for existing clients who have keywords still to register.</p>
                    <p>You don't appear to have any remaining un-registered keywords.</p>
                    <p>Please email <a href="mailto:care@smsexpert.co.uk">care@smsexpert.co.uk</a> to discuss your account, or upgrade your package to get extra keywords.</p>
                @elseif (!$hasPlatinumAccess)
                    {{-- OLD SYSTEM parity (infopage_include_detail2.inc:6725) --}}
                    <p>This page is reserved for existing Silver, Gold or Platinum clients.</p>
                    <p>Please email <a href="mailto:care@smsexpert.co.uk">care@smsexpert.co.uk</a> to discuss upgrading your account.</p>
                @endif

                <div class="mt-4">
                    <a href="mailto:care@smsexpert.co.uk" class="btn btn-search">
                        <i class="material-icons-outlined me-1">email</i>
                        Contact Support
                    </a>
                </div>
            </div>
        @else
            <!-- Registration Form -->
            <div class="main-card">
                <div class="card-header-custom">
                    <h5 class="card-title">
                        <i class="material-icons-outlined">vpn_key</i>
                        Get Additional Keywords on {{ $codeList }}
                    </h5>
                </div>
                <div class="card-body-custom">
                    <!-- Info Box -->
                    <div class="info-box">
                        <p>
                            <strong>You are 5 minutes away from using your new keyword.</strong><br><br>
                            Please note the following about our shortcodes:<br>
                            &rarr; <strong>{{ $codeList }}</strong> is available to you and can receive SMS from all UK mobiles<br><br>
                            <strong>Keywords remaining:</strong> {{ floor($keywordsLeft) }}
                        </p>
                    </div>

                    <!-- Step 1: Search for Keyword -->
                    <div class="step-header">
                        <i class="material-icons-outlined">search</i>
                        Step 1 - Choose a unique keyword on {{ $codeList }}
                    </div>

                    <div class="search-box">
                        <form id="keywordSearchForm">
                            @csrf
                            <label for="keyword" class="form-label fw-bold mb-2">Enter Keyword</label>
                            <div class="d-flex align-items-center gap-3">
                                <div class="flex-grow-1">
                                    <input type="text" 
                                           class="form-control form-control-custom" 
                                           id="keyword" 
                                           name="keyword" 
                                           placeholder="Enter unique keyword (letters and numbers only)"
                                           pattern="[A-Za-z0-9]+"
                                           maxlength="20"
                                           value="{{ $searchedKeyword ?? '' }}"
                                           required>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-search" id="searchBtn" style="white-space: nowrap;">
                                        <span class="loading-spinner" id="searchSpinner"></span>
                                        <i class="material-icons-outlined me-1">search</i>
                                        Check Availability
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">Only letters and numbers allowed. No spaces or special characters.</small>
                        </form>

                        <!-- Search Result -->
                        <div id="searchResult" style="display: none;"></div>
                    </div>

                    <!-- Step 2: Register Keyword -->
                    <div class="step-header">
                        <i class="material-icons-outlined">how_to_reg</i>
                        Step 2 - Register your new keyword
                    </div>

                    <div class="register-form">
                        <form id="registerKeywordForm" method="POST" action="{{ route('keywords.register.submit') }}">
                            @csrf
                            <input type="hidden" name="keyword" id="registerKeyword" value="">
                            <input type="hidden" name="shortcode" id="registerShortcode" value="{{ $whoisCode1 }}">

                            <div class="text-center mb-4">
                                <p class="text-muted mb-2">Keyword to register:</p>
                                <div class="keyword-display" id="keywordToRegister">
                                    <span id="keywordDisplayText">-</span>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-register" id="registerBtn" disabled>
                                    <span class="loading-spinner" id="registerSpinner"></span>
                                    <i class="material-icons-outlined me-1">check_circle</i>
                                    Register Keyword on {{ $whoisCode1 }}
                                </button>
                            </div>

                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    By clicking "Register Keyword" you agree to our 
                                    <a href="{{ route('contract') }}" target="_blank">terms and conditions</a>.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('keywordSearchForm');
            const registerForm = document.getElementById('registerKeywordForm');
            const searchResult = document.getElementById('searchResult');
            const registerBtn = document.getElementById('registerBtn');
            const keywordInput = document.getElementById('keyword');
            const keywordDisplayText = document.getElementById('keywordDisplayText');
            const registerKeywordInput = document.getElementById('registerKeyword');
            const searchSpinner = document.getElementById('searchSpinner');

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

            // Search form submission
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const keyword = keywordInput.value.trim().toUpperCase();
                    
                    if (!keyword) {
                        showSearchResult('error', 'Please enter a keyword.');
                        return;
                    }

                    // Validate keyword format
                    if (!/^[A-Za-z0-9]+$/.test(keyword)) {
                        showSearchResult('error', 'Keyword can only contain letters and numbers.');
                        return;
                    }

                    // Show loading
                    searchSpinner.style.display = 'inline-block';
                    document.getElementById('searchBtn').disabled = true;

                    // Make AJAX request
                    fetch('{{ route("keywords.check") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ keyword: keyword })
                    })
                    .then(response => response.json())
                    .then(data => {
                        searchSpinner.style.display = 'none';
                        document.getElementById('searchBtn').disabled = false;

                        if (data.success) {
                            if (data.available) {
                                showSearchResult('available', 
                                    `<strong>Great news!</strong> The keyword <strong>${keyword}</strong> is available on ${data.shortcode}.<br><br>
                                    Register it below, or keep searching until you find an even better one...`
                                );
                                
                                // Enable register button
                                registerBtn.disabled = false;
                                keywordDisplayText.textContent = keyword;
                                registerKeywordInput.value = keyword;
                            } else {
                                showSearchResult('unavailable', 
                                    `<strong>Sorry!</strong> The keyword <strong>${keyword}</strong> is not available on ${data.shortcode}.<br><br>
                                    ${data.message || 'Keep searching until you find the one you want...'}`
                                );
                                
                                // Disable register button
                                registerBtn.disabled = true;
                                keywordDisplayText.textContent = '-';
                                registerKeywordInput.value = '';
                            }
                        } else {
                            showSearchResult('error', data.message || 'An error occurred while checking the keyword.');
                            registerBtn.disabled = true;
                        }
                    })
                    .catch(error => {
                        searchSpinner.style.display = 'none';
                        document.getElementById('searchBtn').disabled = false;
                        showSearchResult('error', 'An error occurred. Please try again.');
                        console.error('Error:', error);
                    });
                });
            }

            // Register form submission
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    const keyword = registerKeywordInput.value;
                    if (!keyword) {
                        e.preventDefault();
                        alert('Please search for and select a keyword first.');
                        return;
                    }

                    // Show loading
                    document.getElementById('registerSpinner').style.display = 'inline-block';
                    registerBtn.disabled = true;
                });
            }

            function showSearchResult(type, message) {
                searchResult.style.display = 'block';
                searchResult.className = 'search-result ' + type;
                searchResult.innerHTML = `
                    <div class="d-flex align-items-start">
                        <i class="material-icons-outlined me-2" style="font-size: 1.5rem;">
                            ${type === 'available' ? 'check_circle' : type === 'unavailable' ? 'cancel' : 'warning'}
                        </i>
                        <div>${message}</div>
                    </div>
                `;
            }

            // Auto-uppercase keyword input
            if (keywordInput) {
                keywordInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                });
            }
        });
    </script>
@endpush
