@extends('layouts.app')
@section('title', 'Wallet Balances - SMS Expert Documentation')

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

    .technical-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
        margin-bottom: 2rem;
    }

    .technical-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
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

    .wallet-info-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin: 2rem 0;
    }

    @media (max-width: 768px) {
        .wallet-info-cards {
            grid-template-columns: 1fr;
        }
    }

    .wallet-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #0891b2;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s ease;
    }

    .wallet-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(8, 145, 178, 0.2);
    }

    .wallet-card.sms-wallet {
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
        border-color: #ea6118;
    }

    .wallet-card.sms-wallet:hover {
        box-shadow: 0 10px 30px rgba(234, 97, 24, 0.2);
    }

    .wallet-icon {
        background: #0891b2;
        color: white;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 1rem auto;
        transition: all 0.3s ease;
    }

    .wallet-card.sms-wallet .wallet-icon {
        background: #ea6118;
    }

    .wallet-card:hover .wallet-icon {
        transform: scale(1.1) rotate(10deg);
    }

    .wallet-title {
        color: #0891b2;
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
    }

    .wallet-card.sms-wallet .wallet-title {
        color: #ea6118;
    }

    .wallet-description {
        color: #64748b;
        font-size: 0.9rem;
        line-height: 1.4;
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
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        border: 2px solid #dc2626;
        border-radius: 10px;
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .info-card h6 {
        color: #dc2626;
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

    .quick-tips {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border: 2px solid #16a34a;
        border-radius: 10px;
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .quick-tips h6 {
        color: #16a34a;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-tips ul {
        color: #64748b;
        margin: 0;
        padding-left: 1.5rem;
    }

    .quick-tips li {
        margin-bottom: 0.5rem;
        line-height: 1.5;
    }
</style>
@endpush

@section('content')
<div class="technical-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">account_balance_wallet</i>
                Wallet Balances
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
                    <li class="breadcrumb-item active">Wallet Balances</li>
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
            <i class="material-icons-outlined">account_balance_wallet</i>
            Wallet Balance API
        </h1>
        <p class="hero-description">
            Check your current wallet funds and balances using our simple API. 
            Monitor both SMS credits and keyword allowances in real-time.
        </p>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <h2 class="content-title">
                <i class="material-icons-outlined">info</i>
                Wallet System Overview
            </h2>
            
            <p class="content-text">
                Return details of your current wallet funds. SMS Expert provides two separate wallets to manage different aspects of your account.
            </p>

            <!-- Wallet Info Cards -->
            <div class="wallet-info-cards">
                <div class="wallet-card sms-wallet">
                    <div class="wallet-icon">
                        <i class="material-icons-outlined">sms</i>
                    </div>
                    <h4 class="wallet-title">SMS Wallet</h4>
                    <p class="wallet-description">
                        Monetary amount in UK Sterling showing total purchased funds and remaining balance after message costs.
                    </p>
                </div>
                
                <div class="wallet-card">
                    <div class="wallet-icon">
                        <i class="material-icons-outlined">vpn_key</i>
                    </div>
                    <h4 class="wallet-title">Keywords Wallet</h4>
                    <p class="wallet-description">
                        Shows the number of keywords you can still register and manage on your account.
                    </p>
                </div>
            </div>

            <div class="info-card">
                <h6>
                    <i class="material-icons-outlined">info</i>
                    How Balance Calculation Works
                </h6>
                <p>
                    <strong>SMS Funds:</strong> Your current balance is calculated by deducting the amount spent on sent messages from the total amount you have purchased.
                </p>
                <p>
                    <strong>Keywords:</strong> The keyword wallet displays the remaining number of keywords available for registration on your account.
                </p>
            </div>

            <!-- API Documentation Table -->
            <div class="api-table">
                <table class="table">
                    <tbody>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">link</i>
                                HTTPS/Post Call
                            </th>
                            <td>
                                <div class="code-block">
                                    <code>{{ config('app.url') }}api/plat_wallet</code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">settings</i>
                                Parameters
                            </th>
                            <td>
                                <div class="code-block">
                                    <code>$usr, $pwd <em>[as supplied during account signup]</em></code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">api</i>
                                API Responses
                            </th>
                            <td>
                                See <a href="{{ route('technical.wholesaleapiresponsecodes') }}" style="color: #ea6118; font-weight: 600;">keyword tool response codes page</a> for detailed response information.
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">link</i>
                                Example URL
                            </th>
                            <td>
                                <div class="code-block">
                                    <code>{{ config('app.url') }}api/plat_wallet?usr=XXX&pwd=YYY</code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">web</i>
                                Browser Testing
                            </th>
                            <td>
                                Paste the example URL into your browser and modify the parameters to test the API quickly.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="quick-tips">
                <h6>
                    <i class="material-icons-outlined">lightbulb</i>
                    Integration Tips
                </h6>
                <ul>
                    <li>Check wallet balances before sending large SMS campaigns</li>
                    <li>Set up automated balance monitoring in your applications</li>
                    <li>Use this API to display real-time balance information to users</li>
                    <li>Monitor keyword allowances before registering new keywords</li>
                    <li>Implement low-balance alerts to avoid service interruptions</li>
                </ul>
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
    const elements = document.querySelectorAll('.hero-section, .main-content, .sidebar, .wallet-card');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Wallet card hover effects
    const walletCards = document.querySelectorAll('.wallet-card');
    walletCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    console.log('Wallet balances documentation page loaded successfully!');
});
</script>
@endpush