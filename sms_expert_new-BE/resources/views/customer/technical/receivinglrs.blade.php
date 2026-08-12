@extends('layouts.app')
@section('title', 'Receiving Delivery Receipts - SMS Expert Documentation')

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

    .code-block code {
        color: #e2e8f0;
        background: transparent;
        padding: 0;
    }

    .xml-block {
        background: #1e293b;
        color: #e2e8f0;
        border-radius: 10px;
        padding: 1.5rem;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        line-height: 1.6;
        overflow-x: auto;
        margin: 1rem 0;
        border: 1px solid #334155;
        white-space: pre-wrap;
    }

    .xml-tag {
        color: #34d399;
    }

    .xml-content {
        color: #fbbf24;
    }

    .xml-comment {
        color: #94a3b8;
        font-style: italic;
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
        margin-bottom: 0.75rem;
        line-height: 1.6;
    }

    .info-card p:last-child {
        margin-bottom: 0;
    }

    .status-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin: 2rem 0;
    }

    .status-card {
        background: white;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
    }

    .status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .status-card.intermediate {
        border-color: #f59e0b;
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
    }

    .status-card.success {
        border-color: #16a34a;
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    }

    .status-card.failure {
        border-color: #dc2626;
        background: linear-gradient(135deg, #fef2f2, #fecaca);
    }

    .status-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 1rem auto;
        transition: all 0.3s ease;
    }

    .status-card.intermediate .status-icon {
        background: #f59e0b;
        color: white;
    }

    .status-card.success .status-icon {
        background: #16a34a;
        color: white;
    }

    .status-card.failure .status-icon {
        background: #dc2626;
        color: white;
    }

    .status-card:hover .status-icon {
        transform: scale(1.1);
    }

    .status-title {
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .status-card.intermediate .status-title {
        color: #92400e;
    }

    .status-card.success .status-title {
        color: #15803d;
    }

    .status-card.failure .status-title {
        color: #b91c1c;
    }

    .status-description {
        color: #64748b;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .sample-code-section {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        margin-top: 2rem;
    }

    .sample-code-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .language-tab {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 10px 10px 0 0;
        font-weight: 600;
        margin-bottom: 0;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .code-container {
        background: #1e293b;
        border-radius: 0 10px 10px 10px;
        padding: 2rem;
        overflow-x: auto;
    }

    .code-container pre {
        color: #e2e8f0;
        margin: 0;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .php-keyword {
        color: #a78bfa;
        font-weight: bold;
    }

    .php-variable {
        color: #34d399;
    }

    .php-string {
        color: #fbbf24;
    }

    .php-comment {
        color: #94a3b8;
        font-style: italic;
    }

    .php-function {
        color: #60a5fa;
    }

    .dlr-flow {
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
        border: 2px solid #f59e0b;
        border-radius: 15px;
        padding: 2rem;
        margin: 2rem 0;
        text-align: center;
    }

    .dlr-flow h5 {
        color: #92400e;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .flow-steps {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .flow-step {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        flex: 1;
        min-width: 150px;
        border: 1px solid #fed7aa;
    }

    .flow-step-number {
        background: #f59e0b;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin: 0 auto 0.5rem auto;
    }

    .flow-arrow {
        color: #f59e0b;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
    }

    @media (max-width: 768px) {
        .flow-steps {
            flex-direction: column;
        }
        
        .flow-arrow {
            transform: rotate(90deg);
        }
    }
</style>
@endpush

@section('content')
<div class="technical-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">receipt</i>
                Receiving Delivery Receipts
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
                    <li class="breadcrumb-item active">Delivery Receipts</li>
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
            <i class="material-icons-outlined">receipt</i>
            Delivery Receipt API
        </h1>
        <p class="hero-description">
            Receive real-time SMS delivery receipts (DLR) from SMS Expert through webhooks. 
            Track message delivery status and handle delivery confirmations automatically.
        </p>
    </div>

    <!-- DLR Flow -->
    <div class="dlr-flow">
        <h5>
            <i class="material-icons-outlined">timeline</i>
            How Delivery Receipts Work
        </h5>
        <div class="flow-steps">
            <div class="flow-step">
                <div class="flow-step-number">1</div>
                <strong>Send SMS</strong><br>
                <small>Via API with DLR enabled</small>
            </div>
            <div class="flow-arrow">→</div>
            <div class="flow-step">
                <div class="flow-step-number">2</div>
                <strong>Message Delivered</strong><br>
                <small>To recipient's device</small>
            </div>
            <div class="flow-arrow">→</div>
            <div class="flow-step">
                <div class="flow-step-number">3</div>
                <strong>Receipt Generated</strong><br>
                <small>Delivery status confirmed</small>
            </div>
            <div class="flow-arrow">→</div>
            <div class="flow-step">
                <div class="flow-step-number">4</div>
                <strong>Webhook Called</strong><br>
                <small>Your endpoint receives DLR</small>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <h2 class="content-title">
                <i class="material-icons-outlined">webhook</i>
                Delivery Receipt Overview
            </h2>
            
            <p class="content-text">
                Receive SMS delivery receipts (DLR) from SMS Expert through HTTPS/POST webhooks. When delivery confirmation is received, SMS Expert will POST delivery details to your configured endpoint.
            </p>

            <div class="info-card">
                <h6>
                    <i class="material-icons-outlined">settings</i>
                    Configuration Options
                </h6>
                <p>
                    <strong>Dashboard Setup:</strong> Configure your default delivery receipt URL in the SMS Expert dashboard.
                </p>
                <p>
                    <strong>Per-Message Override:</strong> Specify a custom delivery receipt URL when sending individual SMS messages.
                </p>
                <p>
                    <strong>User Parameters:</strong> Include custom user-defined parameters that will be passed back with the delivery receipt.
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
                                HTTPS/POST to your configured endpoint URL
                                <div class="code-block">
                                    <code>http://www.mydomain.com/handler.php</code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">data_object</i>
                                POST Parameters
                            </th>
                            <td>
                                <strong>$xml:</strong> XML payload containing delivery receipt data
                                <div class="xml-block"><span class="xml-tag">&lt;?xml version="1.0" encoding="ISO-8859-1"?&gt;</span>
<span class="xml-tag">&lt;itagg_delivery_receipt&gt;</span>
    <span class="xml-tag">&lt;version&gt;</span><span class="xml-content">1.1</span><span class="xml-tag">&lt;/version&gt;</span>
    <span class="xml-tag">&lt;msisdn&gt;</span><span class="xml-content">447123456789</span><span class="xml-tag">&lt;/msisdn&gt;</span>
    <span class="xml-tag">&lt;submission_ref&gt;</span><span class="xml-content">123456</span><span class="xml-tag">&lt;/submission_ref&gt;</span>
    <span class="xml-tag">&lt;status&gt;</span><span class="xml-content">Delivered</span><span class="xml-tag">&lt;/status&gt;</span>
    <span class="xml-tag">&lt;reason&gt;</span><span class="xml-content">4</span><span class="xml-tag">&lt;/reason&gt;</span>
    <span class="xml-tag">&lt;retry&gt;</span><span class="xml-content">0</span><span class="xml-tag">&lt;/retry&gt;</span>
<span class="xml-tag">&lt;/itagg_delivery_receipt&gt;</span></div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">link</i>
                                GET Parameters
                            </th>
                            <td>
                                <strong>$userid:</strong> User-defined parameter (if specified when sending SMS)
                                <div class="code-block">
                                    <code>?userid=your_custom_identifier</code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">check_circle</i>
                                Response Required
                            </th>
                            <td>
                                Your endpoint must return <strong>"OK"</strong> to acknowledge receipt. Any other response will trigger retry attempts.
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">link</i>
                                Reference Matching
                            </th>
                            <td>
                                The <code>&lt;submission_ref&gt;</code> value matches the submission reference returned when sending SMS via the Send SMS API.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3 style="color: #293b50; font-weight: 700; margin: 2rem 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="material-icons-outlined">info</i>
                Delivery Status Codes
            </h3>

            <div class="status-cards">
                <div class="status-card intermediate">
                    <div class="status-icon">
                        <i class="material-icons-outlined">schedule</i>
                    </div>
                    <h6 class="status-title">Intermediate Status</h6>
                    <div class="status-description">
                        <strong>1:</strong> Buffered (Phone related)<br>
                        <strong>2:</strong> Buffered (Deliverer related)<br>
                        <strong>3:</strong> Acknowledged by SMSC
                    </div>
                </div>

                <div class="status-card success">
                    <div class="status-icon">
                        <i class="material-icons-outlined">check_circle</i>
                    </div>
                    <h6 class="status-title">Success Status</h6>
                    <div class="status-description">
                        <strong>4:</strong> Delivered to mobile device
                    </div>
                </div>

                <div class="status-card failure">
                    <div class="status-icon">
                        <i class="material-icons-outlined">error</i>
                    </div>
                    <h6 class="status-title">Failure Status</h6>
                    <div class="status-description">
                        <strong>5:</strong> Failed (no further info)<br>
                        <strong>6:</strong> Final status unknown
                    </div>
                </div>
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

    <!-- Sample Code Section -->
    <div class="sample-code-section">
        <h2 class="sample-code-title">
            <i class="material-icons-outlined">code</i>
            Sample PHP Implementation
        </h2>
        
        <div class="language-tab">
            <i class="material-icons-outlined">code</i>
            PHP Delivery Receipt Handler
        </div>
        <div class="code-container">
            <pre><code><span class="php-comment">// PHP class for parsing XML delivery receipts</span>
<span class="php-keyword">&lt;?php</span>
<span class="php-keyword">class</span> <span class="php-function">XMLAssocArray</span> {
    <span class="php-keyword">var</span> <span class="php-variable">$arrays</span>, <span class="php-variable">$keys</span>, <span class="php-variable">$node_flag</span>, <span class="php-variable">$depth</span>, <span class="php-variable">$xml_parser</span>;

    <span class="php-keyword">function</span> <span class="php-function">xml2array</span>(<span class="php-variable">$xml</span>) {
        <span class="php-variable">$this</span>-><span class="php-variable">depth</span> = <span class="php-string">-1</span>;
        <span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span> = <span class="php-function">xml_parser_create</span>();
        <span class="php-function">xml_set_object</span>(<span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span>, <span class="php-variable">$this</span>);
        <span class="php-function">xml_parser_set_option</span>(<span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span>, <span class="php-string">XML_OPTION_CASE_FOLDING</span>, <span class="php-string">0</span>);
        <span class="php-function">xml_set_element_handler</span>(<span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span>, <span class="php-string">"startElement"</span>, <span class="php-string">"endElement"</span>);
        <span class="php-function">xml_set_character_data_handler</span>(<span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span>, <span class="php-string">"characterData"</span>);
        <span class="php-function">xml_parse</span>(<span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span>, <span class="php-variable">$xml</span>, <span class="php-keyword">true</span>);
        <span class="php-function">xml_parser_free</span>(<span class="php-variable">$this</span>-><span class="php-variable">xml_parser</span>);
        <span class="php-keyword">return</span> <span class="php-variable">$this</span>-><span class="php-variable">arrays</span>[<span class="php-string">1</span>];
    }

    <span class="php-comment">// Element handlers</span>
    <span class="php-keyword">function</span> <span class="php-function">startElement</span>(<span class="php-variable">$parser</span>, <span class="php-variable">$name</span>, <span class="php-variable">$attrs</span>) {
        <span class="php-variable">$this</span>-><span class="php-variable">keys</span>[] = <span class="php-variable">$name</span>;
        <span class="php-variable">$this</span>-><span class="php-variable">node_flag</span> = <span class="php-string">1</span>;
        <span class="php-variable">$this</span>-><span class="php-variable">depth</span>++;
    }

    <span class="php-keyword">function</span> <span class="php-function">characterData</span>(<span class="php-variable">$parser</span>, <span class="php-variable">$data</span>) {
        <span class="php-variable">$key</span> = <span class="php-function">end</span>(<span class="php-variable">$this</span>-><span class="php-variable">keys</span>);
        <span class="php-variable">$this</span>-><span class="php-variable">arrays</span>[<span class="php-variable">$this</span>-><span class="php-variable">depth</span>][<span class="php-variable">$key</span>] = <span class="php-variable">$data</span>;
        <span class="php-variable">$this</span>-><span class="php-variable">node_flag</span> = <span class="php-string">0</span>;
    }

    <span class="php-keyword">function</span> <span class="php-function">endElement</span>(<span class="php-variable">$parser</span>, <span class="php-variable">$name</span>) {
        <span class="php-variable">$key</span> = <span class="php-function">array_pop</span>(<span class="php-variable">$this</span>-><span class="php-variable">keys</span>);
        <span class="php-keyword">if</span>(<span class="php-variable">$this</span>-><span class="php-variable">node_flag</span> == <span class="php-string">1</span>) {
            <span class="php-variable">$this</span>-><span class="php-variable">arrays</span>[<span class="php-variable">$this</span>-><span class="php-variable">depth</span>][<span class="php-variable">$key</span>] = <span class="php-variable">$this</span>-><span class="php-variable">arrays</span>[<span class="php-variable">$this</span>-><span class="php-variable">depth</span> + <span class="php-string">1</span>];
            <span class="php-function">unset</span>(<span class="php-variable">$this</span>-><span class="php-variable">arrays</span>[<span class="php-variable">$this</span>-><span class="php-variable">depth</span> + <span class="php-string">1</span>]);
        }
        <span class="php-variable">$this</span>-><span class="php-variable">node_flag</span> = <span class="php-string">1</span>;
        <span class="php-variable">$this</span>-><span class="php-variable">depth</span>--;
    }
}

<span class="php-comment">// Main delivery receipt handler</span>
<span class="php-variable">$userid</span> = <span class="php-variable">$_GET</span>[<span class="php-string">"userid"</span>];
<span class="php-variable">$xml</span> = <span class="php-variable">$_POST</span>[<span class="php-string">"xml"</span>];

<span class="php-variable">$parser</span> = <span class="php-keyword">new</span> <span class="php-function">XMLAssocArray</span>();
<span class="php-variable">$arr</span> = <span class="php-variable">$parser</span>-><span class="php-function">xml2array</span>(<span class="php-variable">$xml</span>);

<span class="php-comment">// Extract delivery receipt data</span>
<span class="php-variable">$subref</span> = <span class="php-variable">$arr</span>[<span class="php-string">"submission_ref"</span>];
<span class="php-variable">$msisdn</span> = <span class="php-variable">$arr</span>[<span class="php-string">"msisdn"</span>];
<span class="php-variable">$status</span> = <span class="php-variable">$arr</span>[<span class="php-string">"status"</span>];
<span class="php-variable">$reason</span> = <span class="php-variable">$arr</span>[<span class="php-string">"reason"</span>];
<span class="php-variable">$retry</span> = <span class="php-variable">$arr</span>[<span class="php-string">"retry"</span>];

<span class="php-comment">// Process the delivery receipt (example: send email notification)</span>
<span class="php-function">mail</span>(
    <span class="php-string">"yourself@mydomain.com"</span>,
    <span class="php-string">"DLR for SMS to $msisdn: $status"</span>,
    <span class="php-string">"Mobile number: $msisdn\r\n
    Submission reference: $subref\r\n
    Delivered reason: $reason\r\n
    Status: $status\r\n
    Retry: $retry\r\n
    userid: $userid\r\n"</span>,
    <span class="php-string">"From: yourself@mydomain.com\r\nReply-To: yourself@mydomain.com\r\n"</span>
);

<span class="php-comment">// Always return "OK" to acknowledge receipt</span>
<span class="php-function">print</span>(<span class="php-string">"OK"</span>);
<span class="php-keyword">?&gt;</span></code></pre>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth animations
    const elements = document.querySelectorAll('.hero-section, .main-content, .sidebar, .sample-code-section, .status-card');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Status card hover effects
    const statusCards = document.querySelectorAll('.status-card');
    statusCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    console.log('Delivery receipts documentation page loaded successfully!');
});
</script>
@endpush