@extends('layouts.app')
@section('title', 'API Response Codes - SMS Expert Documentation')

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

    .response-codes {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin: 2rem 0;
    }

    .response-codes .table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .response-codes .table tbody tr {
        transition: all 0.3s ease;
    }

    .response-codes .table tbody tr:hover {
        background: #f8fafc;
        transform: translateX(2px);
    }

    .response-codes .table tbody td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .response-codes .table tbody tr:last-child td {
        border-bottom: none;
    }

    .code-item {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .status-code {
        background: #1e293b;
        color: #e2e8f0;
        padding: 0.5rem 0.75rem;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-weight: 700;
        min-width: 30px;
        text-align: center;
        font-size: 0.9rem;
    }

    .status-code.success {
        background: #16a34a;
        color: white;
    }

    .status-code.error {
        background: #dc2626;
        color: white;
    }

    .status-message {
        flex: 1;
        color: #475569;
        font-weight: 500;
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

    .format-example {
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
        border: 2px solid #f59e0b;
        border-radius: 10px;
        padding: 1.5rem;
        margin: 1.5rem 0;
        text-align: center;
    }

    .format-example h6 {
        color: #92400e;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .format-example .code-block {
        background: #1e293b;
        color: #e2e8f0;
        border: 1px solid #374151;
        margin: 1rem 0;
    }
</style>
@endpush

@section('content')
<div class="technical-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">code</i>
                API Response Codes
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
                    <li class="breadcrumb-item active">Response Codes</li>
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
            <i class="material-icons-outlined">list_alt</i>
            API Response Codes Reference
        </h1>
        <p class="hero-description">
            Complete reference for all API response codes returned by SMS Expert keyword management tools. 
            Understand success and error codes for better error handling in your applications.
        </p>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <h2 class="content-title">
                <i class="material-icons-outlined">info</i>
                Response Format Overview
            </h2>
            
            <p class="content-text">
                All keyword management tools return error/status response codes and messages as plain text. The response format is consistent across all API endpoints for easy parsing and handling.
            </p>

            <div class="format-example">
                <h6>
                    <i class="material-icons-outlined">format_list_numbered</i>
                    Response Format
                </h6>
                <p style="color: #64748b; margin-bottom: 1rem;">
                    Plain text with 2 rows: header row followed by error code and message separated by "|"
                </p>
                <div class="code-block">
                    <code>code|text
1|keyword unavailable</code>
                </div>
            </div>

            <!-- API Documentation Table -->
            <div class="api-table">
                <table class="table">
                    <tbody>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">format_align_left</i>
                                Format Structure
                            </th>
                            <td>
                                Plain text with 2 rows:<br><br>
                                <strong>Row 1:</strong> Header row<br>
                                <strong>Row 2:</strong> Return error code + return error message (separated by "|")
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">check_circle</i>
                                Success Response
                            </th>
                            <td>
                                The return error code will be <strong>"0" (zero)</strong> to indicate success
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">error</i>
                                Failure Response
                            </th>
                            <td>
                                The return error code will be <strong>non-zero</strong> to indicate failure. See the complete list of error codes below.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="info-card">
                <h6>
                    <i class="material-icons-outlined">integration_instructions</i>
                    Implementation Tips
                </h6>
                <p>
                    <strong>Error Handling:</strong> Always check if the first character of the response is "0" for success.<br>
                    <strong>Message Parsing:</strong> Split the response by "|" to separate code from message.<br>
                    <strong>Logging:</strong> Log both the error code and message for debugging purposes.
                </p>
            </div>

            <h3 style="color: #293b50; font-weight: 700; margin: 2rem 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                <i class="material-icons-outlined">list</i>
                Complete Response Code Reference
            </h3>

            <!-- Response Codes Table -->
            <div class="response-codes">
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code success">0</div>
                                    <div class="status-message">keyword sms email and url forwarding updates successful</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code success">0</div>
                                    <div class="status-message">keyword renewal successful</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code success">0</div>
                                    <div class="status-message">keyword replacement successful</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code success">0</div>
                                    <div class="status-message">keyword deletion successful</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code success">0</div>
                                    <div class="status-message">keyword creation successful</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code success">0</div>
                                    <div class="status-message">keyword available</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">1</div>
                                    <div class="status-message">keyword unavailable</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">2</div>
                                    <div class="status-message">keyword renewal failed</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">3</div>
                                    <div class="status-message">keyword replacement failed</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">4</div>
                                    <div class="status-message">keyword deletion failed</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">5</div>
                                    <div class="status-message">keyword creation failed</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">6</div>
                                    <div class="status-message">bad username or password</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">7</div>
                                    <div class="status-message">bad keyword parameter</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">8</div>
                                    <div class="status-message">insufficient keyword funds</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">9</div>
                                    <div class="status-message">bad characters found in input fields</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">10</div>
                                    <div class="status-message">ip address problem</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">11</div>
                                    <div class="status-message">temporary problem</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">12</div>
                                    <div class="status-message">ip address blocked</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">13</div>
                                    <div class="status-message">bad shortcode parameter</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">30</div>
                                    <div class="status-message">keyword sms email/url forwarding updates failed</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">31</div>
                                    <div class="status-message">bad keyword sms email/url forwarding parameters</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">32</div>
                                    <div class="status-message">no keyword sms email/url forwarding changes to make</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">33</div>
                                    <div class="status-message">bad keyword sms email/url forwarding flags</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">40</div>
                                    <div class="status-message">wallet retrieval failed</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="code-item">
                                    <div class="status-code error">50</div>
                                    <div class="status-message">keyword list retrieval failed</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
    const elements = document.querySelectorAll('.hero-section, .main-content, .sidebar, .response-codes');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Code item hover effects
    const codeItems = document.querySelectorAll('.code-item');
    codeItems.forEach(item => {
        item.parentElement.parentElement.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        item.parentElement.parentElement.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });

    console.log('API Response Codes documentation page loaded successfully!');
});
</script>
@endpush