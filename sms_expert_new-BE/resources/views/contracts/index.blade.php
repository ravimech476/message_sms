@extends('layouts.app')

@section('title', 'Contract & Terms - SMS Expert')

@push('style')
    <style>
        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .contract-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .contract-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }

        .contract-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .contract-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title .icon-main {
            color: #293b50;
        }

        .section-title .icon-addendum {
            color: #ea6118;
        }

        .section-title .icon-privacy {
            color: #28a745;
        }


        .section-content {
            padding: 1.5rem;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .contract-overview {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .contract-overview h5 {
            color: #293b50;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .contract-overview p {
            color: #64748b;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .contract-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total {
            border-left: 4px solid #ea6118;
        }

        .stat-card.signed {
            border-left: 4px solid #16a34a;
        }

        .stat-card.pending {
            border-left: 4px solid #f59e0b;
        }

        .stat-card.privacy {
            border-left: 4px solid #28a745;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: #293b50;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .document-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #293b50;
            margin-bottom: 1rem;
        }

        .document-link:last-child {
            margin-bottom: 0;
        }

        .document-link:hover {
            background: #ea6118;
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        }

        .document-icon {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
        }

        .document-icon.icon-green {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .document-icon.icon-blue {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
        }

        .document-link:hover .document-icon {
            background: white;
            color: #ea6118;
            transform: scale(1.1);
        }

        .document-info {
            flex: 1;
        }

        .document-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: inherit;
        }

        .document-description {
            font-size: 0.85rem;
            color: #64748b;
            margin: 0;
        }

        .document-link:hover .document-description {
            color: rgba(255, 255, 255, 0.8);
        }

        .document-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .document-meta .badge {
            font-size: 0.7rem;
        }

        .no-contracts {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: #fef7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            color: #92400e;
            font-weight: 500;
        }

        .signature-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
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

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .btn-view {
            background: linear-gradient(135deg, #293b50, #1f2c3d);
            color: white;
            border: none;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(41, 59, 80, 0.3);
            color: white;
        }

        .btn-sign {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            border: none;
        }

        .btn-sign:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .btn-download {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            border: none;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
            color: white;
        }

        .acceptance-notice {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px solid #dc2626;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }

        .acceptance-notice h6 {
            color: #dc2626;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .acceptance-notice p {
            color: #991b1b;
            margin: 0;
            line-height: 1.6;
        }

        .verified-badge {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 15px;
            padding: 1.5rem;
            border: 2px solid #16a34a;
            margin-top: 2rem;
        }

        .verified-icon {
            font-size: 48px !important;
            color: white;
        }

        .warning-notice {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .effective-date {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            font-weight: 500;
            font-size: 0.9rem;
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

        .alert-info {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
        }
    </style>
@endpush

@section('content')
    <div class="contract-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">gavel</i>
                    Contract & Terms
                </div>
                &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Contract</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Alert Messages -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="flash-message">
                <i class="material-icons-outlined align-middle me-2">check_circle</i>
                {{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="flash-message">
                <i class="material-icons-outlined align-middle me-2">error</i>
                {{ session('error') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        @endif

        <!-- Contract Notice (if not agreed and has reasons) -->
        @if (!$hasAgreed && $reasons)
            <div class="warning-notice">
                <div class="d-flex align-items-start">
                    <i class="material-icons-outlined text-warning me-3" style="font-size: 36px;">warning</i>
                    <div>
                        <h6 class="mb-2 fw-bold" style="color: #f59e0b;">Important Notice</h6>
                        <p class="mb-2" style="color: #64748b;">For the following reason(s) please read, accept and sign
                            the Contract before continuing...</p>
                        <div class="alert alert-light mb-0" style="background: white; color: #64748b;">
                            <i class="material-icons-outlined align-middle me-2" style="color: #f59e0b;">arrow_forward</i>
                            {{ $reasons }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Contract Overview -->
        <div class="contract-overview">
            <h5>
                <i class="material-icons-outlined">description</i>
                Your SMS Expert Agreement
            </h5>
            <p>
                Review your current contract terms and policies. By continuing to use our services,
                you agree to be bound by these terms and conditions.
            </p>
        </div>

        @php
            $totalContracts = $mainContracts->count() + $addendums->count() + $privacyPolicies->count();
            $signedContracts =
                $mainContracts->where('is_signed', true)->count() +
                $addendums->where('is_signed', true)->count() +
                $privacyPolicies->where('is_signed', true)->count();
            $pendingContracts =
                $mainContracts->where('is_signed', false)->where('requires_signature', true)->count() +
                $addendums->where('is_signed', false)->where('requires_signature', true)->count() +
                $privacyPolicies->where('is_signed', false)->where('requires_signature', true)->count();
        @endphp

        <!-- Contract Statistics -->
        <div class="contract-stats">
            <div class="stat-card total">
                <div class="stat-number">{{ $mainContracts->count() }}</div>
                <div class="stat-label">Master Agreement</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $addendums->count() }}</div>
                <div class="stat-label">Addendums</div>
            </div>
            <div class="stat-card privacy">
                <div class="stat-number">{{ $privacyPolicies->count() }}</div>
                <div class="stat-label">Privacy Policy</div>
            </div>
            <div class="stat-card signed">
                <div class="stat-number">{{ $signedContracts }}</div>
                <div class="stat-label">Signed</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number">{{ $pendingContracts }}</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <!-- Main Client Contract -->
        <div class="contract-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined icon-main">assignment</i>
                    Main Client Contracts
                </h5>
            </div>
            <div class="section-content">
                @if ($mainContracts->count() > 0)
                    @foreach ($mainContracts as $contract)
                        <div class="document-link" style="cursor: default;">
                            <div class="document-icon">
                                <i class="material-icons-outlined">description</i>
                            </div>
                            <div class="document-info">
                                <div class="document-title">{{ $contract->title }}</div>
                                <div class="document-description">
                                    Version {{ $contract->version }} | Updated:
                                    {{ $contract->updated_at->format('d M Y') }}
                                    @if ($contract->hasFile())
                                        | {{ strtoupper($contract->file_type) }} ({{ $contract->getFileSizeFormatted() }})
                                    @endif
                                </div>
                                <div class="document-meta">
                                    @if ($contract->is_signed)
                                        <span class="signature-badge badge-signed">
                                            <i class="material-icons-outlined me-1"
                                                style="font-size: 14px;">check_circle</i>
                                            Signed {{ $contract->signature->signed_at->format('d M Y') }}
                                        </span>
                                    @elseif($contract->requires_signature)
                                        <span class="signature-badge badge-pending">
                                            <i class="material-icons-outlined me-1" style="font-size: 14px;">pending</i>
                                            Signature Required
                                        </span>
                                    @else
                                        <span class="signature-badge badge-no-signature">
                                            <i class="material-icons-outlined me-1" style="font-size: 14px;">info</i>
                                            For Reference
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="{{ route('contracts.show', $contract->id) }}" class="btn btn-action btn-view">
                                    <i class="material-icons-outlined" style="font-size: 16px;">visibility</i>
                                    View
                                </a>
                                @if ($contract->hasFile())
                                    <a href="{{ route('contracts.download', $contract->id) }}"
                                        class="btn btn-action btn-download">
                                        <i class="material-icons-outlined" style="font-size: 16px;">download</i>
                                    </a>
                                @endif
                                @if (!$contract->is_signed && $contract->requires_signature)
                                    <a href="{{ route('contracts.show', $contract->id) }}" class="btn btn-action btn-sign">
                                        <i class="material-icons-outlined" style="font-size: 16px;">edit</i>
                                        Sign
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="no-contracts">
                        <i class="material-icons-outlined">info</i>
                        <div>
                            <strong>No Main Contracts Available</strong><br>
                            <small>There are no main contracts assigned to your account at this time.</small>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Contract Addendums -->
        <div class="contract-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined icon-addendum">post_add</i>
                    Contract Addendums
                </h5>
            </div>
            <div class="section-content">
                @if ($addendums->count() > 0)
                    @foreach ($addendums as $contract)
                        <div class="document-link" style="cursor: default;">
                            <div class="document-icon" style="background: linear-gradient(135deg, #ea6118, #d1520e);">
                                <i class="material-icons-outlined">note_add</i>
                            </div>
                            <div class="document-info">
                                <div class="document-title">{{ $contract->title }}</div>
                                <div class="document-description">
                                    Version {{ $contract->version }} | Updated:
                                    {{ $contract->updated_at->format('d M Y') }}
                                    @if ($contract->hasFile())
                                        | {{ strtoupper($contract->file_type) }} ({{ $contract->getFileSizeFormatted() }})
                                    @endif
                                </div>
                                <div class="document-meta">
                                    @if ($contract->is_signed)
                                        <span class="signature-badge badge-signed">
                                            <i class="material-icons-outlined me-1"
                                                style="font-size: 14px;">check_circle</i>
                                            Signed {{ $contract->signature->signed_at->format('d M Y') }}
                                        </span>
                                    @elseif($contract->requires_signature)
                                        <span class="signature-badge badge-pending">
                                            <i class="material-icons-outlined me-1" style="font-size: 14px;">pending</i>
                                            Signature Required
                                        </span>
                                    @else
                                        <span class="signature-badge badge-no-signature">
                                            <i class="material-icons-outlined me-1" style="font-size: 14px;">info</i>
                                            For Reference
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="{{ route('contracts.show', $contract->id) }}" class="btn btn-action btn-view">
                                    <i class="material-icons-outlined" style="font-size: 16px;">visibility</i>
                                    View
                                </a>
                                @if ($contract->hasFile())
                                    <a href="{{ route('contracts.download', $contract->id) }}"
                                        class="btn btn-action btn-download">
                                        <i class="material-icons-outlined" style="font-size: 16px;">download</i>
                                    </a>
                                @endif
                                @if (!$contract->is_signed && $contract->requires_signature)
                                    <a href="{{ route('contracts.show', $contract->id) }}"
                                        class="btn btn-action btn-sign">
                                        <i class="material-icons-outlined" style="font-size: 16px;">edit</i>
                                        Sign
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="no-contracts">
                        <i class="material-icons-outlined">info</i>
                        <div>
                            <strong>No Addendums Available</strong><br>
                            <small>You currently have no addendums to your client contract. Any future modifications will
                                appear here.</small>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Privacy Policy -->
        <div class="contract-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined icon-privacy">privacy_tip</i>
                    Privacy & Data Protection
                </h5>
            </div>
            <div class="section-content">
                @if ($privacyPolicies->count() > 0)
                    @foreach ($privacyPolicies as $contract)
                        <div class="document-link" style="cursor: default;">
                            <div class="document-icon icon-green">
                                <i class="material-icons-outlined">shield</i>
                            </div>
                            <div class="document-info">
                                <div class="document-title">{{ $contract->title }}</div>
                                <div class="document-description">
                                    Version {{ $contract->version }} | Updated:
                                    {{ $contract->updated_at->format('d M Y') }}
                                    @if ($contract->hasFile())
                                        | {{ strtoupper($contract->file_type) }} ({{ $contract->getFileSizeFormatted() }})
                                    @endif
                                </div>
                                <div class="document-meta">
                                    @if ($contract->is_signed)
                                        <span class="signature-badge badge-signed">
                                            <i class="material-icons-outlined me-1"
                                                style="font-size: 14px;">check_circle</i>
                                            Signed {{ $contract->signature->signed_at->format('d M Y') }}
                                        </span>
                                    @elseif($contract->requires_signature)
                                        <span class="signature-badge badge-pending">
                                            <i class="material-icons-outlined me-1" style="font-size: 14px;">pending</i>
                                            Signature Required
                                        </span>
                                    @else
                                        <span class="signature-badge badge-no-signature">
                                            <i class="material-icons-outlined me-1" style="font-size: 14px;">info</i>
                                            For Reference
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="{{ route('contracts.show', $contract->id) }}" class="btn btn-action btn-view">
                                    <i class="material-icons-outlined" style="font-size: 16px;">visibility</i>
                                    View
                                </a>
                                @if ($contract->hasFile())
                                    <a href="{{ route('contracts.download', $contract->id) }}"
                                        class="btn btn-action btn-download">
                                        <i class="material-icons-outlined" style="font-size: 16px;">download</i>
                                    </a>
                                @endif
                                @if (!$contract->is_signed && $contract->requires_signature)
                                    <a href="{{ route('contracts.show', $contract->id) }}"
                                        class="btn btn-action btn-sign">
                                        <i class="material-icons-outlined" style="font-size: 16px;">edit</i>
                                        Sign
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    {{-- Show default link if no privacy policies in database --}}
                    <a href="https://www.sms.expert/privacy-and-cookie-policy/" target="_blank" class="document-link">
                        <div class="document-icon icon-green">
                            <i class="material-icons-outlined">shield</i>
                        </div>
                        <div class="document-info">
                            <div class="document-title">Privacy & Cookie Policy</div>
                            <div class="document-description">How we collect, use, and protect your personal data</div>
                        </div>
                        <i class="material-icons-outlined">arrow_forward</i>
                    </a>
                @endif
            </div>
        </div>

        <!-- Pricing Section -->
        <div class="contract-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined" style="color: #16a34a;">payments</i>
                    Pricing
                </h5>
            </div>
            <div class="section-content">
                @php($p = \App\Http\Controllers\Admin\GlobalPricingController::getPricingValues())
                <p style="color: #64748b; margin-bottom: 1.5rem;">
                    Unless alternative rates have been agreed in writing then our pricing from <strong>{{ \Carbon\Carbon::parse($p['contract_effective_date'])->format('d/m/Y') }}</strong> is as follows...
                </p>

                <div class="pricing-item" style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border-left: 4px solid #ea6118;">
                    <i class="material-icons-outlined" style="color: #ea6118;">arrow_forward</i>
                    <div>
                        <strong>Virtual UK Mobile Numbers, or keywords on 60300:</strong>
                        <span style="color: #16a34a; font-weight: 600;">£{{ number_format((float)$p['contract_virtual_number_price_year'], 0) }} per year</span>
                    </div>
                </div>

                <div class="pricing-item" style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border-left: 4px solid #ea6118;">
                    <i class="material-icons-outlined" style="color: #ea6118;">arrow_forward</i>
                    <div>
                        <strong>SMS to overseas mobiles (all sent volumes):</strong>
                        <span style="color: #16a34a; font-weight: 600;">{{ rtrim(rtrim(number_format((float)$p['contract_overseas_rate_pence'], 2), '0'), '.') }} pence per text</span>
                    </div>
                </div>

                <div class="pricing-item" style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; padding: 1rem; background: #f8fafc; border-radius: 10px; border-left: 4px solid #ea6118;">
                    <i class="material-icons-outlined" style="color: #ea6118;">arrow_forward</i>
                    <div>
                        <strong>SMS to UK mobiles (based on monthly sent volumes)...</strong>
                        <div style="margin-top: 0.75rem; padding-left: 1rem;">
                            <div style="display: flex; justify-content: space-between; max-width: 400px; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                                <span>upto {{ number_format((int)$p['contract_uk_tier1_upto']) }}:</span>
                                <span style="color: #16a34a; font-weight: 600;">£{{ number_format((float)$p['contract_uk_tier1_rate'], 4) }} ({{ rtrim(rtrim(number_format((float)$p['contract_uk_tier1_rate']*100, 2), '0'), '.') }} pence per text)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; max-width: 400px; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0;">
                                <span>upto {{ number_format((int)$p['contract_uk_tier2_upto']) }}:</span>
                                <span style="color: #16a34a; font-weight: 600;">£{{ number_format((float)$p['contract_uk_tier2_rate'], 4) }} ({{ rtrim(rtrim(number_format((float)$p['contract_uk_tier2_rate']*100, 2), '0'), '.') }} pence per text)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; max-width: 400px; padding: 0.5rem 0;">
                                <span>over {{ number_format((int)$p['contract_uk_tier2_upto']) }}:</span>
                                <span style="color: #16a34a; font-weight: 600;">£{{ number_format((float)$p['contract_uk_tier3_rate'], 4) }} ({{ rtrim(rtrim(number_format((float)$p['contract_uk_tier3_rate']*100, 2), '0'), '.') }} pence per text)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acceptance Notice -->
        <div class="acceptance-notice">
            <h6>
                <i class="material-icons-outlined">verified</i>
                Terms Acceptance
            </h6>
            <p>
                If you have made any purchases or continue to use any of our services or products then you are deemed to have
                accepted the current Contract, Privacy Policy, Pricing and any Addendums in full.
            </p>
        </div>

        <!-- Agreement Status -->
        @if ($hasAgreed)
            <div class="verified-badge">
                <div class="d-flex align-items-center">
                    <i class="material-icons-outlined verified-icon me-3">verified</i>
                    <div>
                        <h5 class="text-white mb-1">Contract Agreed</h5>
                        @if ($dateAgreed)
                            <p class="text-white mb-0" style="opacity: 0.9;">You agreed to the contract on:
                                <strong>{{ $dateAgreed }}</strong></p>
                        @else
                            <p class="text-white mb-0" style="opacity: 0.9;">You have agreed to the contract.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
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
            const cards = document.querySelectorAll(
                '.contract-card, .contract-overview, .acceptance-notice, .verified-badge');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Stat cards animation
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';

                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                }, 500 + (index * 100));
            });

            // Back button functionality
            // const backButton = document.getElementById('backButton');
            // if (backButton) {
            //     backButton.addEventListener('click', function() {
            //         window.history.back();
            //     });
            // }

            console.log('Contract page loaded successfully!');
        });
    </script>
@endpush
