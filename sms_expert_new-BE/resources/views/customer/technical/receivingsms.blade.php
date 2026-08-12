@extends('layouts.app')
@section('title', 'Receiving SMS - SMS Expert Documentation')

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
    .technical-container {
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

    .hero-section {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border: 2px solid #ea6118;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(234, 97, 24, 0.1) 0%, transparent 70%);
        pointer-events: none;
    }

    .hero-title {
        color: #293b50;
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .hero-description {
        color: #64748b;
        font-size: 1.1rem;
        line-height: 1.6;
        margin: 0;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }

    .main-content {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 2rem;
    }

    .content-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .content-text {
        color: #475569;
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .api-table {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin: 2rem 0;
    }

    .api-table .table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .api-table .table tbody td,
    .api-table .table tbody th {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
        color: #475569;
        line-height: 1.6;
    }

    .api-table .table tbody th {
        background: #fef7ed;
        color: #ea6118;
        font-weight: 700;
        width: 25%;
    }

    .api-table .table tbody tr:hover {
        background: #f8fafc;
    }

    .api-table .table tbody tr:last-child td,
    .api-table .table tbody tr:last-child th {
        border-bottom: none;
    }

    .code-block {
        background: #1e293b;
        color: #e2e8f0;
        border-radius: 10px;
        padding: 1rem;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        line-height: 1.5;
        overflow-x: auto;
        margin: 0.5rem 0;
        border: 1px solid #334155;
    }

    .sidebar {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 0;
        height: fit-content;
        overflow: hidden;
    }

    .sidebar-section {
        padding: 1.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .sidebar-section:last-child {
        border-bottom: none;
    }

    .sidebar-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sidebar-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-list li {
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sidebar-list li:last-child {
        margin-bottom: 0;
    }

    .sidebar-list a {
        color: #64748b;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        flex: 1;
        padding: 0.5rem 0;
    }

    .sidebar-list a:hover {
        color: #ea6118;
        padding-left: 0.5rem;
    }

    .sidebar-arrow {
        color: #ea6118;
        font-weight: bold;
        min-width: 20px;
    }

    .back-to-support {
        background: linear-gradient(135deg, #ea6118, #293b50);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        margin-bottom: 2rem;
    }

    .back-to-support:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        color: white;
    }

    .info-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #0891b2;
        border-radius: 10px;
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .info-card h6 {
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
</style>
@endpush

@section('content')
<div class="technical-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">inbox</i>
                Receiving SMS
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
                    <li class="breadcrumb-item">
                        <a href="{{ route('technical.support') }}">Support</a>
                    </li>
                    <li class="breadcrumb-item active">Receiving SMS</li>
                </ol>
            </nav>
        </div>
<button id="backButton" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined me-1">arrow_back</i> Back
        </button>
    </div>

    <!-- Back to Support -->
     {{-- <a href="{{ route('technical.support') }}" class="back-to-support">
        <i class="material-icons-outlined">arrow_back</i>
        Back to Support Home
    </a> --}}

    <!-- Hero Section -->
    <div class="hero-section">
        <h1 class="hero-title">
            <i class="material-icons-outlined">inbox</i>
            Receiving Inbound SMS
        </h1>
        <p class="hero-description">
            Configure your system to receive inbound SMS messages from SMS Expert. 
            Handle incoming messages with webhooks and real-time notifications.
        </p>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <h2 class="content-title">
                <i class="material-icons-outlined">settings_input_antenna</i>
                Receiving SMS Overview
            </h2>
            
            <p class="content-text">
                Receive inbound SMS messages from SMS Expert through our webhook system. Configure your endpoint to handle incoming messages in real-time.
            </p>

            <div class="info-card">
                <h6>
                    <i class="material-icons-outlined">info</i>
                    How It Works
                </h6>
                <p>
                    When someone sends an SMS to your registered keywords or virtual numbers, SMS Expert will forward the message to your configured webhook URL in real-time. This allows you to process incoming messages and respond accordingly.
                </p>
            </div>

            <!-- API Documentation Table -->
            <div class="api-table">
                <table class="table">
                    <tbody>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">webhook</i>
                                Webhook Method
                            </th>
                            <td>
                                HTTP POST request to your configured endpoint URL
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">data_object</i>
                                Parameters Sent
                            </th>
                            <td>
                                <div class="code-block">
                                    <code>from: Sender's mobile number
to: Your keyword or virtual number  
message: The SMS message content
timestamp: When the message was received
reference: Unique message identifier</code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">settings</i>
                                Configuration
                            </th>
                            <td>
                                Configure your webhook URL in the SMS Expert dashboard under keyword settings or contact support for assistance.
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">security</i>
                                Security
                            </th>
                            <td>
                                All webhook requests include authentication tokens and can be validated against your account credentials for security.
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">code</i>
                                Response Required
                            </th>
                            <td>
                                Your endpoint should return HTTP 200 status to acknowledge receipt. Non-200 responses will trigger retry attempts.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="info-card">
                <h6>
                    <i class="material-icons-outlined">integration_instructions</i>
                    Integration Notes
                </h6>
                <p>
                    <strong>Real-time Processing:</strong> Messages are delivered within seconds of receipt.<br>
                    <strong>Retry Logic:</strong> Failed deliveries are retried up to 5 times with exponential backoff.<br>
                    <strong>Logging:</strong> All webhook attempts are logged in your dashboard for troubleshooting.
                </p>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="material-icons-outlined">home</i>
                    Support Home
                </h3>
                <ul class="sidebar-list">
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.support') }}">Introduction</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('dashboard') }}">Main Dashboard</a>
                    </li>
                     <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('customer.link.redirect', ['username' => $user->uname]) }}" target="_blank">Campaign Manager</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="https://www.sms.expert/" target="_blank">SMS Expert Home Page</a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="material-icons-outlined">http</i>
                    Sending SMS (HTTP)
                </h3>
                <ul class="sidebar-list">
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.sendingsms') }}">Send Outbound SMS</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.receivingdlrs') }}">Receive Delivery Receipts</a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="material-icons-outlined">settings_ethernet</i>
                    Sending SMS (SMPP)
                </h3>
                <ul class="sidebar-list">
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="javascript:;">Please Call For Details</a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="material-icons-outlined">inbox</i>
                    Receiving SMS
                </h3>
                <ul class="sidebar-list">
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.receivingsms') }}">Receive Inbound SMS</a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="material-icons-outlined">account_balance_wallet</i>
                    Wallet Balances
                </h3>
                <ul class="sidebar-list">
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.wholesalewalletcheck') }}">SMS + Keyword Balances</a>
                    </li>
                </ul>
            </div>

             <div class="sidebar-section">
                <h3 class="sidebar-title">
                    <i class="material-icons-outlined">vpn_key</i>
                    Keyword Tools
                </h3>
                <ul class="sidebar-list">
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.keywordwhois') }}">Keyword Availability</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.keywordregistration') }}">Register Keyword</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.keywordsetforwardings') }}">Set Keyword Forwarding</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.listkeywords') }}">List Keywords</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.keywordrenewal') }}">Renew Keyword</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.keyworddeletion') }}">Delete Keyword</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.keywordreplacement') }}">Replace Keyword</a>
                    </li>
                    <li>
                        <span class="sidebar-arrow">→</span>
                        <a href="{{ route('technical.wholesaleapiresponsecodes') }}">Tool Response Codes</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth animations
    const elements = document.querySelectorAll('.hero-section, .main-content, .sidebar');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    console.log('Receiving SMS documentation page loaded successfully!');
});
</script>
@endpush