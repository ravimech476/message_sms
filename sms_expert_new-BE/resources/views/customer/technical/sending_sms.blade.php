@extends('layouts.app')
@section('title', 'Sending SMS via HTTP - SMS Expert Documentation')

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

    .api-table .table thead th {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        color: #293b50;
        font-weight: 700;
        padding: 1.5rem 1rem;
        border: none;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
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
        padding: 1.5rem;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        line-height: 1.5;
        overflow-x: auto;
        margin: 1rem 0;
        border: 1px solid #334155;
    }

    .code-block code {
        color: #e2e8f0;
        background: transparent;
        padding: 0;
    }

    .highlight {
        color: #fbbf24;
        font-weight: 600;
    }

    .parameter {
        color: #34d399;
        font-weight: 600;
    }

    .string {
        color: #a78bfa;
    }

    .comment {
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
        font-size: 0.9rem;
        line-height: 1.6;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .quick-info {
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
        border: 2px solid #f59e0b;
        border-radius: 10px;
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .quick-info h6 {
        color: #92400e;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-info ul {
        color: #64748b;
        margin: 0;
        padding-left: 1.5rem;
    }

    .quick-info li {
        margin-bottom: 0.5rem;
        line-height: 1.5;
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
</style>
@endpush

@section('content')
<div class="technical-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">send</i>
                Sending SMS via HTTP
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
                    <li class="breadcrumb-item active">Sending SMS</li>
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
            <i class="material-icons-outlined" style="font-size: 32px !important;">http</i>
            Sending SMS via HTTP API
        </h1>
        <p class="hero-description">
            Send SMS text messages to mobile phones using our simple HTTP/HTTPS API. 
            Support for single messages, bulk sending, scheduling, and delivery receipts.
        </p>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="main-content">
            <h2 class="content-title">
                <i class="material-icons-outlined">integration_instructions</i>
                API Overview
            </h2>
            
            <p class="content-text">
                Send an SMS text message to a mobile phone using our HTTP API. Messages should conform to the GSM ASCII character set for standard SMS delivery.
            </p>

            <div class="info-card">
                <h6>
                    <i class="material-icons-outlined">info</i>
                    Important Guidelines
                </h6>
                <p>
                    <strong>Bulk Sending:</strong> We recommend sending to no more than 500 comma-separated numbers in a single API call.<br>
                    <strong>Number Format:</strong> UK mobiles should begin with 447 or 07. Non-UK mobiles should include the country code without a "+" prefix.<br>
                    <strong>Encoding:</strong> By default, we assume messages are not UTF8 encoded. Contact us to enable UTF8 support for your account.
                </p>
            </div>

            <!-- API Documentation Table -->
            <div class="api-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">link</i>
                                HTTPS/Post Call
                            </th>
                            <td>
                                <div class="code-block">
                                    <code>{{ config('app.url') }}api/smsg/sms.mes</code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">settings</i>
                                Required Parameters
                            </th>
                            <td>
                                <div class="code-block">
                                    <code><span class="parameter">$usr</span>, <span class="parameter">$pwd</span> <span class="comment">[as supplied during account signup]</span><br><br>
<span class="parameter">$from</span> <span class="comment">[alpha/numeric up to 11 chars, shortcode, or mobile number]</span><br><br>
<span class="parameter">$to</span> <span class="comment">[mobile number or list of comma-separated numbers]</span><br><br>
<span class="parameter">$type</span> <span class="string">{"text"}</span><br><br>
<span class="parameter">$route</span> <span class="string">{"p", "g", "l", "d", "e"}</span> <span class="comment">[please ask us for the correct code to use]</span><br><br>
<span class="parameter">$txt</span> <span class="comment">[message to send to phone. Must be URL-encoded]</span></code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">message</i>
                                Long SMS Support
                            </th>
                            <td>
                                No changes are required to send SMS longer than 160 characters and up to 1,377 characters in length.<br><br>
                                <strong>Billing:</strong> SMS longer than 160 characters is billed "per 153 characters."
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">schedule</i>
                                Scheduling (Option 1)
                            </th>
                            <td>
                                <div class="code-block">
                                    <code><span class="parameter">$send</span> <span class="comment">{yyyymmddhhmmss}</span></code>
                                </div>
                                Use this optional parameter to schedule SMS sending for a specific future date and time.<br><br>
                                <strong>Format:</strong> Year, Month, Date, Hours, Minutes, Seconds (with leading zeros)<br>
                                <strong>Example:</strong> 16:07:08 on 9th March 2015 = <code>20150309160708</code>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">timer</i>
                                Scheduling (Option 2)
                            </th>
                            <td>
                                <div class="code-block">
                                    <code><span class="parameter">$sendrelative</span> <span class="comment">[number of seconds into the future to send SMS]</span></code>
                                </div>
                                Specify how many seconds to wait "from now" before sending the SMS.<br><br>
                                <strong>Note:</strong> Do not use both scheduling options together.
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">receipt</i>
                                Delivery Receipts
                            </th>
                            <td>
                                <div class="code-block">
                                    <code><span class="parameter">$dreceipt_url</span> <span class="comment">[set this to a valid URL]</span><br><br>
<span class="parameter">$userid</span> <span class="comment">[optional user defined parameter]</span></code>
                                </div>
                                Override the default delivery receipt URL configured in your dashboard.<br><br>
                                <strong>Example:</strong> <code>http://www.mydomain.com/handler.php?userid=myid</code><br><br>
                                <a href="{{ route('technical.receivingdlrs') }}" style="color: #ea6118; font-weight: 600;">Learn more about delivery receipts →</a>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">api</i>
                                API Response
                            </th>
                            <td>
                                This function will return error/status response.<br><br>
                                <strong>Example response:</strong>
                                <div class="code-block">
                                    <code><span class="string">"success": "SMS log created successfully"</span></code>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <i class="material-icons-outlined">link</i>
                                Example URL
                            </th>
                            <td>
                                <div class="code-block">
                                    <code>{{ config('app.url') }}api/smsg/sms.mes?<span class="parameter">usr</span>=XXX&<span class="parameter">pwd</span>=YYY&<span class="parameter">from</span>=test&<span class="parameter">to</span>=917094514970&<span class="parameter">type</span>=text&<span class="parameter">route</span>=d&<span class="parameter">txt</span>=Hello</code>
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

            <div class="quick-info">
                <h6>
                    <i class="material-icons-outlined">lightbulb</i>
                    Quick Tips
                </h6>
                <ul>
                    <li>Always URL-encode your message text to handle special characters properly</li>
                    <li>Test with a single number before sending to multiple recipients</li>
                    <li>Use delivery receipts to track message delivery status</li>
                    <li>Contact support if you need custom routing or special features</li>
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

    <!-- Sample Code Section -->
    <div class="sample-code-section">
        <h2 class="sample-code-title">
            <i class="material-icons-outlined">code</i>
            Sample Code Implementation
        </h2>
        
        <div class="language-tab">
            <i class="material-icons-outlined">code</i>
            PHP Example
        </div>
        <div class="code-container">
            <pre><code><span class="comment">&lt;?php</span>

<span class="comment">// Initialize cURL</span>
<span class="parameter">$curl</span> = <span class="highlight">curl_init</span>();

<span class="comment">// Configure cURL options</span>
<span class="highlight">curl_setopt_array</span>(<span class="parameter">$curl</span>, <span class="highlight">array</span>(
  <span class="parameter">CURLOPT_URL</span> => <span class="string">'{{ config('app.url') }}api/smsg/sms.mes?usr=master&pwd=master&from=test&to=917094514970&type=text&route=d&txt=api%20test'</span>,
  <span class="parameter">CURLOPT_RETURNTRANSFER</span> => <span class="highlight">true</span>,
  <span class="parameter">CURLOPT_ENCODING</span> => <span class="string">''</span>,
  <span class="parameter">CURLOPT_MAXREDIRS</span> => <span class="highlight">10</span>,
  <span class="parameter">CURLOPT_TIMEOUT</span> => <span class="highlight">0</span>,
  <span class="parameter">CURLOPT_FOLLOWLOCATION</span> => <span class="highlight">true</span>,
  <span class="parameter">CURLOPT_HTTP_VERSION</span> => <span class="parameter">CURL_HTTP_VERSION_1_1</span>,
  <span class="parameter">CURLOPT_CUSTOMREQUEST</span> => <span class="string">'GET'</span>,
  <span class="parameter">CURLOPT_HTTPHEADER</span> => <span class="highlight">array</span>(
    <span class="string">'Cookie: XSRF-TOKEN=eyJpdiI6InRrZDVTc1o2'</span>
  ),
));

<span class="comment">// Execute the request</span>
<span class="parameter">$response</span> = <span class="highlight">curl_exec</span>(<span class="parameter">$curl</span>);

<span class="comment">// Close cURL</span>
<span class="highlight">curl_close</span>(<span class="parameter">$curl</span>);

<span class="comment">// Output the response</span>
<span class="highlight">echo</span> <span class="parameter">$response</span>;

<span class="comment">?&gt;</span></code></pre>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth animations
    const elements = document.querySelectorAll('.hero-section, .main-content, .sidebar, .sample-code-section');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    console.log('Sending SMS documentation page loaded successfully!');
});
</script>
@endpush