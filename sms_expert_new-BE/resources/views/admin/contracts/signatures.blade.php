@extends('admin.layouts.app')
@section('title', 'CRM')

@push('style')
<style>
    .breadcrumb-item+.breadcrumb-item::before {
        content: " / " !important;
        color: #6c757d !important;
    }
    
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        text-align: center;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
        font-size: 40px;
        color: #ea6118;
        margin-bottom: 10px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #293b50;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #6c757d;
        font-size: 14px;
        font-weight: 500;
    }
    
    .contract-info-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .contract-title {
        font-size: 20px;
        font-weight: 600;
        color: #293b50;
        margin-bottom: 5px;
    }
    
    .contract-version {
        color: #6c757d;
    }
    
    .signatures-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .card-header-custom {
        background: #293b50;
        color: white;
        padding: 15px 20px;
        border-radius: 10px 10px 0 0;
        font-weight: 500;
        font-size: 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .signature-item {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.2s;
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }
    
    .signature-item:last-child {
        border-bottom: none;
    }
    
    .signature-item:hover {
        background: #f8f9fa;
    }
    
    .signature-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 700;
        flex-shrink: 0;
    }
    
    .signature-details {
        flex: 1;
    }
    
    .signature-name {
        font-size: 18px;
        font-weight: 600;
        color: #293b50;
        margin-bottom: 5px;
    }
    
    .signature-badge {
        display: inline-block;
        background: #d4edda;
        color: #155724;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        margin-bottom: 10px;
    }
    
    .signature-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    
    .info-detail {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #6c757d;
        font-size: 14px;
    }
    
    .info-detail i {
        color: #ea6118;
        font-size: 18px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 80px;
        opacity: 0.3;
        margin-bottom: 15px;
    }
    
    .btn-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .btn-print {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-print:hover {
        background: linear-gradient(135deg, #d1520e, #b8450c);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
    }
    
    @media print {
        .btn-actions, .page-breadcrumb, .back-button-container {
            display: none !important;
        }
        
        .signatures-card {
            box-shadow: none;
        }
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Contract Signatures</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.contracts.index') }}">Contracts</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Signatures</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <a href="{{ route('admin.contracts.index') }}" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </a>
            </div>
        </div>
        <!--end breadcrumb-->

        <!-- Contract Info -->
        <div class="contract-info-card">
            <div class="contract-title">{{ $contract->title }}</div>
            <div class="contract-version">Version {{ $contract->version }} | Created: {{ $contract->created_at->format('d M Y') }}</div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <i class="material-icons-outlined stat-icon">how_to_reg</i>
                <div class="stat-value">{{ $signatures->count() }}</div>
                <div class="stat-label">Total Signatures</div>
            </div>
            <div class="stat-card">
                <i class="material-icons-outlined stat-icon">calendar_today</i>
                <div class="stat-value">{{ $contract->created_at->format('d M Y') }}</div>
                <div class="stat-label">Contract Created</div>
            </div>
            <div class="stat-card">
                <i class="material-icons-outlined stat-icon">schedule</i>
                <div class="stat-value">
                    {{ $signatures->isNotEmpty() ? $signatures->first()->signed_at->format('d M Y') : 'N/A' }}
                </div>
                <div class="stat-label">First Signature</div>
            </div>
        </div>

        <!-- Signatures List -->
        <div class="signatures-card">
            <div class="card-header-custom">
                <div>
                    <i class="material-icons-outlined align-middle me-2">list</i>
                    Signature List
                </div>
                <div class="btn-actions">
                    {{-- <button onclick="window.print()" class="btn btn-print">
                        <i class="material-icons-outlined align-middle" style="font-size: 18px;">print</i>
                        Print
                    </button> --}}
                    <a href="{{ route('admin.contracts.edit', $contract->id) }}" class="btn btn-info btn-sm">
                        <i class="material-icons-outlined align-middle" style="font-size: 16px;">edit</i>
                        Edit Contract
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                @if($signatures->count() > 0)
                    @foreach($signatures as $signature)
                        <div class="signature-item">
                            <div class="signature-avatar">
                                {{ strtoupper(substr($signature->signee_name, 0, 2)) }}
                            </div>
                            <div class="signature-details">
                                <div class="signature-name">{{ $signature->signee_name }}</div>
                                <div class="signature-badge">
                                    <i class="material-icons-outlined align-middle" style="font-size: 14px;">check_circle</i>
                                    Signed
                                </div>
                                <div class="signature-info-grid">
                                    <div class="info-detail">
                                        <i class="material-icons-outlined">work</i>
                                        <span>{{ $signature->signee_position ?: 'N/A' }}</span>
                                    </div>
                                    <div class="info-detail">
                                        <i class="material-icons-outlined">email</i>
                                        <span>{{ $signature->signee_email }}</span>
                                    </div>
                                    <div class="info-detail">
                                        <i class="material-icons-outlined">schedule</i>
                                        <span>{{ $signature->signed_at->format('d M Y, H:i:s') }}</span>
                                    </div>
                                    <div class="info-detail">
                                        <i class="material-icons-outlined">location_on</i>
                                        <span>{{ $signature->ip_address ?: 'N/A' }}</span>
                                    </div>
                                    @if($signature->user_agent)
                                        <div class="info-detail" style="grid-column: 1 / -1;">
                                            <i class="material-icons-outlined">devices</i>
                                            <span style="word-break: break-word;">{{ $signature->user_agent }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class="material-icons-outlined">pending_actions</i>
                        <h4>No Signatures Yet</h4>
                        <p>This contract hasn't been signed by any customers yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>

@include('admin.layouts.footer')
@endsection
