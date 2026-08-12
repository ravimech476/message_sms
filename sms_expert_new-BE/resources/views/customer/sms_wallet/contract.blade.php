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

    .section-content {
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

    .back-button {
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .back-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
        color: white;
    }

    .icon-primary {
        color: #ea6118;
        font-size: 1.2rem;
    }

    .info-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #0891b2;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .info-card h5 {
        color: #0891b2;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-card p {
        color: #64748b;
        margin-bottom: 0;
        line-height: 1.6;
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
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.3s ease;
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

    .no-addendums {
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
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: 2rem 0;
    }

    .stat-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #ea6118;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: #64748b;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 500;
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
                   {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li>--}}
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

    <!-- Contract Overview -->
    <div class="contract-overview">
        <h5>
            <i class="material-icons-outlined">description</i>
            Your SMS Expert Agreement
        </h5>
        <p>
            Review your current contract terms, pricing, and policies. By continuing to use our services, 
            you agree to be bound by these terms and conditions.
        </p>
    </div>

    <!-- Contract Statistics -->
    <div class="contract-stats">
        <div class="stat-card">
            <div class="stat-number">1</div>
            <div class="stat-label">Master Agreement</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">0</div>
            <div class="stat-label">Addendums</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">1</div>
            <div class="stat-label">Privacy Policy</div>
        </div>
    </div>

    <!-- Main Client Contract -->
    <div class="contract-card">
        <div class="section-header">
            <h5 class="section-title">
                <i class="material-icons-outlined">assignment</i>
                Main Client Contract
            </h5>
        </div>
        <div class="section-content">
            <a href="javascript:;" class="document-link">
                <div class="document-icon">
                    <i class="material-icons-outlined">description</i>
                </div>
                <div class="document-info">
                    <div class="document-title">SMS Expert Master Agreement-1-20-20190923</div>
                    <div class="document-description">Master service agreement outlining terms and conditions</div>
                </div>
                <i class="material-icons-outlined">arrow_forward</i>
            </a>
            <div class="effective-date">
                <i class="material-icons-outlined">schedule</i>
                Effective from: September 23, 2019
            </div>
        </div>
    </div>

    <!-- Contract Addendums -->
    <div class="contract-card">
        <div class="section-header">
            <h5 class="section-title">
                <i class="material-icons-outlined">post_add</i>
                Contract Addendums
            </h5>
        </div>
        <div class="section-content">
            <div class="no-addendums">
                <i class="material-icons-outlined">info</i>
                <div>
                    <strong>No Addendums Available</strong><br>
                    <small>You currently have no addendums to your client contract. Any future modifications will appear here.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy -->
    <div class="contract-card">
        <div class="section-header">
            <h5 class="section-title">
                <i class="material-icons-outlined">privacy_tip</i>
                Privacy & Data Protection
            </h5>
        </div>
        <div class="section-content">
            <a href="javascript:;" class="document-link">
                <div class="document-icon">
                    <i class="material-icons-outlined">shield</i>
                </div>
                <div class="document-info">
                    <div class="document-title">Privacy & Cookie Policy</div>
                    <div class="document-description">How we collect, use, and protect your personal data</div>
                </div>
                <i class="material-icons-outlined">arrow_forward</i>
            </a>
        </div>
    </div>

    <!-- Acceptance Notice -->
    <div class="acceptance-notice">
        <h6>
            <i class="material-icons-outlined">verified</i>
            Terms Acceptance
        </h6>
        <p>
            By making any purchases or continuing to use any of our services or products, you are deemed to have 
            accepted the current Contract, Privacy Policy, Pricing, and any Addendums in full. These terms constitute 
            a legally binding agreement between you and SMS Expert.
        </p>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth animations
    const cards = document.querySelectorAll('.contract-card, .contract-overview, .acceptance-notice');
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

    // Document links hover effects
    const documentLinks = document.querySelectorAll('.document-link');
    documentLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px) scale(1.02)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0) scale(1)';
        });
    });

    console.log('Contract page loaded successfully!');
});
</script>
@endpush