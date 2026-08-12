@extends('layouts.app')

@section('title', 'Contracts - SMS Expert')

@push('style')
    <style>
        /* Contracts Container - Same as SMS Wallet */
        .contracts-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        /* Breadcrumb Container - Same design as SMS Wallet */
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
            font-size: 1.1rem;
        }

        /* Back Button - Same as SMS Wallet */
        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        /* Description Card */
        .description-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            position: relative;
        }

        .description-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .description-content {
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .description-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            min-height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ea6118, #d1520e);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white !important;
            font-size: 26px !important;
            flex-shrink: 0;
            line-height: 1;
        }

        .description-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0 0 0.25rem 0;
            line-height: 1.4;
        }

        .description-text {
            color: #64748b;
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Modern Card - Updated to match SMS Wallet */
        .modern-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }

        .modern-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #ff8a50);
        }

        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Card Headers */
        .card-header-custom {
            background: linear-gradient(135deg, #293b50 0%, #1f2c3d 100%);
            color: white;
            padding: 1rem 1.5rem;
            border: none;
        }

        .card-header-custom h5 {
            color: #ffffff !important;
            display: flex;
            align-items: center;
            margin: 0;
        }

        .card-header-custom i {
            color: #ffffff !important;
        }

        .card-header-orange {
            background: linear-gradient(135deg, #ea6118 0%, #d1520e 100%);
        }

        /* Contract Items */
        .contract-item-modern {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .contract-item-modern:last-child {
            border-bottom: none;
        }

        .contract-item-modern:hover {
            background: #f8fafc;
        }

        /* Signature Badges */
        .signature-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-signed {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .badge-no-signature {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        /* Buttons */
        .btn-view-contract {
            background: linear-gradient(135deg, #293b50, #1f2c3d);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-view-contract:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(41, 59, 80, 0.3);
            color: white;
        }

        .btn-sign-contract {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-sign-contract:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.4);
            color: white;
        }

        /* Alert styling */
        .alert {
            border-radius: 12px;
            border: none;
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 1rem;
        }

        /* Contract Stats */
        .contract-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            flex: 1;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #293b50;
        }

        .stat-card .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }

        .stat-card.signed {
            border-left: 4px solid #16a34a;
        }

        .stat-card.pending {
            border-left: 4px solid #f59e0b;
        }

        .stat-card.total {
            border-left: 4px solid #ea6118;
        }
    </style>
@endpush

@section('content')
    <div class="contracts-container">
        <!-- Breadcrumb Header - Same design as SMS Wallet -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Contracts</div> &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Contracts</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Page Description Card -->
        <div class="description-card">
            <div class="description-content">
                <i class="material-icons-outlined description-icon">assignment</i>
                <div>
                    <h5 class="description-title">Contract Management</h5>
                    <p class="description-text">View and manage your client contracts, addendums and agreements. Sign contracts electronically with your digital signature.</p>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="flash-message">
                <i class="material-icons-outlined align-middle me-2">check_circle</i>
                {{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="flash-message">
                <i class="material-icons-outlined align-middle me-2">error</i>
                {{ session('error') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (!empty($resignReason))
            <div class="modern-card" style="border-left: 5px solid #ea6118; margin-bottom: 24px;">
                <div class="card-header-custom card-header-orange">
                    <h5><i class="material-icons-outlined">gavel</i> Contract Re-signature Required</h5>
                </div>
                <div style="padding: 20px;">
                    <p style="margin-bottom: 6px;"><strong>For the following reason you must read and re-sign the contract before continuing...</strong></p>
                    <p style="color:#ea6118; font-weight:600; margin-bottom:18px;">&rarr; {{ $resignReason }}</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('customer.contracts.resign') }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label"><b>Full signee name</b></label>
                                <input type="text" name="signee_name" class="form-control" value="{{ old('signee_name') }}" placeholder="Firstname Surname" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><b>Email address (of signee)</b></label>
                                <input type="email" name="signee_email" class="form-control" value="{{ old('signee_email') }}" placeholder="you@example.com" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><b>Position (e.g. Director)</b></label>
                                <input type="text" name="signee_position" class="form-control" value="{{ old('signee_position') }}" placeholder="Director" required>
                            </div>
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="iagree" value="yes" id="resignAgree" required>
                            <label class="form-check-label" for="resignAgree">
                                I have read and agree to the Contract, Privacy Policy, Pricing and any Addendums in full.
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="material-icons-outlined align-middle me-1">check</i> Re-sign &amp; Continue
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @php
            $totalContracts = $mainContracts->count() + $addendums->count() + $privacyPolicies->count();
            $signedContracts = $mainContracts->where('is_signed', true)->count() + $addendums->where('is_signed', true)->count() + $privacyPolicies->where('is_signed', true)->count();
            $pendingContracts = $mainContracts->where('is_signed', false)->where('requires_signature', true)->count() + $addendums->where('is_signed', false)->where('requires_signature', true)->count() + $privacyPolicies->where('is_signed', false)->where('requires_signature', true)->count();
        @endphp

        <!-- Contract Stats -->
        @if($totalContracts > 0)
        <div class="contract-stats">
            <div class="stat-card total">
                <div class="stat-number">{{ $totalContracts }}</div>
                <div class="stat-label">Total Contracts</div>
            </div>
            <div class="stat-card signed">
                <div class="stat-number">{{ $signedContracts }}</div>
                <div class="stat-label">Signed</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number">{{ $pendingContracts }}</div>
                <div class="stat-label">Pending Signature</div>
            </div>
        </div>
        @endif

        <!-- Main Client Contracts -->
        <div class="modern-card">
            <div class="card-header-custom">
                <h5 class="mb-0">
                    <i class="material-icons-outlined align-middle me-2">description</i>
                    Main Client Contracts
                </h5>
            </div>
            <div class="card-body p-0">
                @if ($mainContracts->count() > 0)
                    @foreach ($mainContracts as $contract)
                        <div class="contract-item-modern">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <h5 class="mb-1" style="color: #293b50;">
                                        <i class="material-icons-outlined align-middle me-2">article</i>
                                        {{ $contract->title }}
                                        @if($contract->hasFile())
                                            <span class="badge bg-info ms-2" style="font-size: 0.65rem;">
                                                <i class="material-icons-outlined align-middle" style="font-size: 12px;">attach_file</i>
                                                {{ strtoupper($contract->file_type) }}
                                            </span>
                                        @endif
                                    </h5>
                                    <small class="text-muted">
                                        Version {{ $contract->version }} | 
                                        Updated: {{ $contract->updated_at->format('d M Y') }}
                                        @if($contract->hasFile())
                                            | File: {{ $contract->getFileSizeFormatted() }}
                                        @endif
                                    </small>
                                </div>
                                <div class="col-md-4 text-center">
                                    @if ($contract->is_signed)
                                        <span class="signature-badge badge-signed">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">check_circle</i>
                                            Signed on {{ $contract->signature->signed_at->format('d M Y') }}
                                        </span>
                                    @elseif($contract->requires_signature)
                                        <span class="signature-badge badge-pending">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">pending</i>
                                            Signature Required
                                        </span>
                                    @else
                                        <span class="signature-badge badge-no-signature">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">info</i>
                                            For Reference Only
                                        </span>
                                    @endif
                                </div>
                                <div class="col-md-3 text-end">
                                    <a href="{{ route('customer.contracts.show', $contract->id) }}" 
                                       class="btn btn-view-contract me-2">
                                        <i class="material-icons-outlined align-middle me-1">visibility</i>
                                        View
                                    </a>
                                    @if (!$contract->is_signed && $contract->requires_signature)
                                        <a href="{{ route('customer.contracts.show', $contract->id) }}" 
                                           class="btn btn-sign-contract">
                                            <i class="material-icons-outlined align-middle me-1">edit</i>
                                            Sign
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="material-icons-outlined">folder_open</i>
                        <h5 style="color: #293b50;">No Contracts Available</h5>
                        <p>There are no main contracts available at the moment.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Addendums to Client Contract -->
        <div class="modern-card">
            <div class="card-header-custom card-header-orange">
                <h5 class="mb-0">
                    <i class="material-icons-outlined align-middle me-2">library_add</i>
                    Addendums to Client Contract
                </h5>
            </div>
            <div class="card-body p-0">
                @if ($addendums->count() > 0)
                    @foreach ($addendums as $contract)
                        <div class="contract-item-modern">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <h5 class="mb-1" style="color: #293b50;">
                                        <i class="material-icons-outlined align-middle me-2">note_add</i>
                                        {{ $contract->title }}
                                        @if($contract->hasFile())
                                            <span class="badge bg-info ms-2" style="font-size: 0.65rem;">
                                                <i class="material-icons-outlined align-middle" style="font-size: 12px;">attach_file</i>
                                                {{ strtoupper($contract->file_type) }}
                                            </span>
                                        @endif
                                    </h5>
                                    <small class="text-muted">
                                        Version {{ $contract->version }} | 
                                        Updated: {{ $contract->updated_at->format('d M Y') }}
                                        @if($contract->hasFile())
                                            | File: {{ $contract->getFileSizeFormatted() }}
                                        @endif
                                    </small>
                                </div>
                                <div class="col-md-4 text-center">
                                    @if ($contract->is_signed)
                                        <span class="signature-badge badge-signed">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">check_circle</i>
                                            Signed on {{ $contract->signature->signed_at->format('d M Y') }}
                                        </span>
                                    @elseif($contract->requires_signature)
                                        <span class="signature-badge badge-pending">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">pending</i>
                                            Signature Required
                                        </span>
                                    @else
                                        <span class="signature-badge badge-no-signature">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">info</i>
                                            For Reference Only
                                        </span>
                                    @endif
                                </div>
                                <div class="col-md-3 text-end">
                                    <a href="{{ route('customer.contracts.show', $contract->id) }}" 
                                       class="btn btn-view-contract me-2">
                                        <i class="material-icons-outlined align-middle me-1">visibility</i>
                                        View
                                    </a>
                                    @if (!$contract->is_signed && $contract->requires_signature)
                                        <a href="{{ route('customer.contracts.show', $contract->id) }}" 
                                           class="btn btn-sign-contract">
                                            <i class="material-icons-outlined align-middle me-1">edit</i>
                                            Sign
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="material-icons-outlined">info</i>
                        <h5 style="color: #293b50;">No Addendums Available</h5>
                        <p>You have no addendums to the Client Contract.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Privacy Policies -->
        <div class="modern-card">
            <div class="card-header-custom" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                <h5 class="mb-0">
                    <i class="material-icons-outlined align-middle me-2">policy</i>
                    Privacy Policies
                </h5>
            </div>
            <div class="card-body p-0">
                @if ($privacyPolicies->count() > 0)
                    @foreach ($privacyPolicies as $contract)
                        <div class="contract-item-modern">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <h5 class="mb-1" style="color: #293b50;">
                                        <i class="material-icons-outlined align-middle me-2">policy</i>
                                        {{ $contract->title }}
                                        @if($contract->hasFile())
                                            <span class="badge bg-info ms-2" style="font-size: 0.65rem;">
                                                <i class="material-icons-outlined align-middle" style="font-size: 12px;">attach_file</i>
                                                {{ strtoupper($contract->file_type) }}
                                            </span>
                                        @endif
                                    </h5>
                                    <small class="text-muted">
                                        Version {{ $contract->version }} | 
                                        Updated: {{ $contract->updated_at->format('d M Y') }}
                                        @if($contract->hasFile())
                                            | File: {{ $contract->getFileSizeFormatted() }}
                                        @endif
                                    </small>
                                </div>
                                <div class="col-md-4 text-center">
                                    @if ($contract->is_signed)
                                        <span class="signature-badge badge-signed">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">check_circle</i>
                                            Signed on {{ $contract->signature->signed_at->format('d M Y') }}
                                        </span>
                                    @elseif($contract->requires_signature)
                                        <span class="signature-badge badge-pending">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">pending</i>
                                            Signature Required
                                        </span>
                                    @else
                                        <span class="signature-badge badge-no-signature">
                                            <i class="material-icons-outlined me-1" style="font-size: 18px;">info</i>
                                            For Reference Only
                                        </span>
                                    @endif
                                </div>
                                <div class="col-md-3 text-end">
                                    <a href="{{ route('customer.contracts.show', $contract->id) }}" 
                                       class="btn btn-view-contract me-2">
                                        <i class="material-icons-outlined align-middle me-1">visibility</i>
                                        View
                                    </a>
                                    @if (!$contract->is_signed && $contract->requires_signature)
                                        <a href="{{ route('customer.contracts.show', $contract->id) }}" 
                                           class="btn btn-sign-contract">
                                            <i class="material-icons-outlined align-middle me-1">edit</i>
                                            Sign
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="material-icons-outlined">policy</i>
                        <h5 style="color: #293b50;">No Privacy Policies Available</h5>
                        <p>There are no privacy policies to review at this time.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            setTimeout(function() {
                let flashMessage = document.getElementById('flash-message');
                if (flashMessage) {
                    flashMessage.style.opacity = '0';
                    flashMessage.style.transform = 'translateY(-20px)';
                    flashMessage.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        flashMessage.style.display = 'none';
                    }, 300);
                }
            }, 4000);

            // Smooth animations for cards
            const cards = document.querySelectorAll('.modern-card, .description-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Back button functionality
            const backButton = document.getElementById('backButton');
            if (backButton) {
                backButton.addEventListener('click', function() {
                    window.history.back();
                });
            }

            console.log('Contracts page loaded successfully!');
        });
    </script>
@endpush
