@extends('campaign.layouts.app')

@section('title', 'Campaign Manager - SMS Expert')

@push('style')
<style>
    .dashboard-container {
        background: #f8fafc;
        margin: -2rem;
        padding: 2rem;
    }

    .welcome-header {
        background: linear-gradient(135deg, #ea6118 0%, #293b50 100%);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(234, 97, 24, 0.3);
        padding: 1rem;
        margin-bottom: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .welcome-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .welcome-header h1 {
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-header p {
        opacity: 0.9;
        margin-bottom: 0;
    }

    .action-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        text-decoration: none;
        display: block;
        height: 100%;
    }

    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(234, 97, 24, 0.2);
        border-color: #ea6118;
    }

    .action-card .card-body {
        text-align: center;
        padding: 2rem;
    }

    .action-icon {
        width: 56px;
        height: 47px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 32px;
    }

    .action-card h5 {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .action-card p {
        color: #64748b;
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .info-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .info-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
        border-radius: 15px 15px 0 0;
    }

    .info-card .card-header h6 {
        color: #293b50;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-card .card-body {
        padding: 1.5rem;
    }

    .quick-link-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #293b50;
        margin-bottom: 0.25rem;
    }

    .quick-link-item:hover {
        background: #f8fafc;
        transform: translateX(5px);
        color: #ea6118;
    }

    .quick-link-item:last-child {
        margin-bottom: 0;
    }

    .quick-link-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        color: white;
    }

    .gradient-primary { background: linear-gradient(135deg, #293b50, #1f2c3d); }
    .gradient-success { background: linear-gradient(135deg, #16a34a, #15803d); }
    .gradient-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .gradient-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .gradient-info { background: linear-gradient(135deg, #0891b2, #0e7490); }
    .gradient-purple { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
    .gradient-orange { background: linear-gradient(135deg, #ea6118, #d1520e); }

    .btn-primary-custom {
        background: linear-gradient(135deg, #ea6118, #293b50);
        border: none;
        color: white;
        padding: 0.6rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(234, 97, 24, 0.4);
        color: white;
    }

    .section-title {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .account-info-item {
        padding: 0.5rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .account-info-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .account-info-item small {
        color: #64748b;
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.8rem;
    }

    .account-info-item code {
        background: #f1f5f9;
        padding: 0.25rem 0.5rem;
        border-radius: 5px;
        color: #293b50;
        font-size: 0.85rem;
    }

    .highlight-box {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border-left: 4px solid #ea6118;
        padding: 1rem 1.25rem;
        border-radius: 0 10px 10px 0;
        margin-bottom: 1rem;
    }

    .highlight-box h6 {
        font-size: 0.95rem;
    }

    .highlight-box p {
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
    }

    .steps-list {
        padding-left: 0;
        list-style: none;
        counter-reset: step-counter;
        margin-bottom: 0;
    }

    .steps-list li {
        position: relative;
        padding-left: 2.5rem;
        margin-bottom: 0.75rem;
        counter-increment: step-counter;
        font-size: 0.9rem;
        line-height: 1.5;
    }

    .steps-list li:last-child {
        margin-bottom: 0;
    }

    .steps-list li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        width: 24px;
        height: 24px;
        background: linear-gradient(135deg, #ea6118, #293b50);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .steps-list li strong {
        color: #293b50;
    }

    .how-to-title {
        color: #293b50;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Right column cards compact styling */
    .compact-card .card-body {
        padding: 1rem;
    }

    .compact-card .card-header {
        padding: 0.75rem 1rem;
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    <!-- Welcome Header -->
    <div class="welcome-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="display-6">Welcome to Campaign Manager 🚀</h1>
                <p class="lead">Hello, {{ ucfirst(urldecode($user['contactname'] ?? $user['username'] ?? 'User')) }}! Manage your SMS campaigns efficiently.</p>
            </div>
            <div class="d-none d-md-block">
                <i class="material-icons-outlined" style="font-size: 80px; opacity: 0.3;">campaign</i>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h5 class="section-title">
        <i class="material-icons-outlined">flash_on</i>
        Quick Actions
    </h5>
    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <a href="{{ route('campaign.quick') }}" class="action-card">
                <div class="card-body">
                    <div class="action-icon gradient-orange text-white">
                        <i class="material-icons-outlined">send</i>
                    </div>
                    <h5>Quick Campaign</h5>
                    <p>Submit new SMS campaign (quick) - Send SMS to a list of mobile numbers instantly</p>
                </div>
            </a>
        </div>
        <div class="col-lg-4 col-md-6">
            <a href="{{ route('campaign.upload') }}" class="action-card">
                <div class="card-body">
                    <div class="action-icon gradient-success text-white">
                        <i class="material-icons-outlined">upload_file</i>
                    </div>
                    <h5>Bulk Campaign</h5>
                    <p>Submit new SMS campaign (file upload) - Upload CSV for bulk SMS campaigns</p>
                </div>
            </a>
        </div>
        <div class="col-lg-4 col-md-6">
            <a href="{{ route('campaign.previous.list') }}" class="action-card">
                <div class="card-body">
                    <div class="action-icon gradient-info text-white">
                        <i class="material-icons-outlined">history</i>
                    </div>
                    <h5>Campaigns History</h5>
                    <p>View previous SMS campaigns - Track and manage your past campaigns</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Information Section -->
    <div class="row g-4">
        <!-- Getting Started - Left Column -->
        <div class="col-lg-8">
            <div class="info-card">
                <div class="card-header">
                    <h6>
                        <i class="material-icons-outlined">info</i>
                        Getting Started
                    </h6>
                </div>
                <div class="card-body">
                    {{-- <div class="highlight-box">
                        <h6 class="text-primary mb-2">For all sales and support queries please contact your account manager.</h6>
                        <p>
                            By continuing to use the SMS Expert services you hereby agree to the latest contract, 
                            privacy policy, pricing and any addendums and to abide by all applicable laws and regulations.
                        </p>
                        <a href="#" class="btn-primary-custom btn btn-sm">
                            <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">description</i>
                            View your SMS Expert contract
                        </a>
                    </div> --}}

                    <h6 class="how-to-title">
                        <i class="material-icons-outlined" style="font-size: 20px;">help_outline</i>
                        How to send an SMS Campaign
                    </h6>
                    <ol class="steps-list">
                        <li>
                            <strong>Quick Campaign:</strong> Use this for simple campaigns where all recipients receive the same message. 
                            Just enter the recipient numbers, sender ID, and message text.
                        </li>
                        <li>
                            <strong>Bulk Campaign:</strong> Use this for larger campaigns or campaigns with personalised messages. 
                            Upload a CSV file containing recipient numbers, sender IDs, and message text.
                        </li>
                        <li>
                            <strong>Campaigns History:</strong> Track the status of your campaigns, download delivery reports, 
                            and manage ongoing campaigns.
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Right Column - Quick Links & Account Info -->
        <div class="col-lg-4">
            <!-- Quick Links -->
            <div class="info-card compact-card mb-3">
                <div class="card-header">
                    <h6>
                        <i class="material-icons-outlined">link</i>
                        Quick Links
                    </h6>
                </div>
                <div class="card-body p-2">
                    <a href="{{ route('campaign.dashboard.link', ['username' => $user['username']]) }}" class="quick-link-item" target="_blank">
                        <div class="quick-link-icon gradient-primary">
                            <i class="material-icons-outlined" style="font-size: 18px;">dashboard</i>
                        </div>
                        <span>Main Dashboard</span>
                    </a>
                    <a href="{{ route('campaign.blacklist') }}" class="quick-link-item">
                        <div class="quick-link-icon gradient-danger">
                            <i class="material-icons-outlined" style="font-size: 18px;">block</i>
                        </div>
                        <span>View STOP Blacklist</span>
                    </a>
                    <a href="{{ route('campaign.accounts') }}" class="quick-link-item">
                        <div class="quick-link-icon gradient-success">
                            <i class="material-icons-outlined" style="font-size: 18px;">people</i>
                        </div>
                        <span>View Accounts</span>
                    </a>
                    <a href="{{ route('campaign.download.sample.csv') }}" class="quick-link-item">
                        <div class="quick-link-icon gradient-info">
                            <i class="material-icons-outlined" style="font-size: 18px;">download</i>
                        </div>
                        <span>Download Sample CSV</span>
                    </a>
                </div>
            </div>

            <!-- Account Info -->
            <div class="info-card compact-card">
                <div class="card-header">
                    <h6>
                        <i class="material-icons-outlined">person</i>
                        Account Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="account-info-item">
                        <small>Username</small>
                        <code>{{ $user['username'] ?? 'N/A' }}</code>
                    </div>
                    <div class="account-info-item">
                        <small>User ID</small>
                        <code>{{ $user['bigid'] ?? 'N/A' }}</code>
                    </div>
                    <div class="account-info-item">
                        <small>Login Type</small>
                        <span class="badge" style="background: linear-gradient(135deg, #ea6118, #293b50);">
                            {{ ucfirst($user['login_type'] ?? 'Campaign Manager') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
