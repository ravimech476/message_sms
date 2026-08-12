@extends('layouts.app')
@section('title', 'Support & Technical Documentation - SMS Expert')

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
    .support-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
        padding: 2rem;
    }

    .support-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
        margin-bottom: 2rem;
    }

    .support-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .support-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .alert-success {
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: white;
    }

    .alert-danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
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
        padding: 3rem 2rem;
        margin-bottom: 3rem;
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
        font-size: 2.5rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }

    .hero-subtitle {
        color: #ea6118;
        font-weight: 600;
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
    }

    .hero-description {
        color: #64748b;
        font-size: 1.1rem;
        line-height: 1.6;
        max-width: 800px;
        margin: 0 auto;
    }

    .main-content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 3rem;
        margin-bottom: 3rem;
    }

    @media (max-width: 768px) {
        .main-content-grid {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .hero-title {
            font-size: 2rem;
        }
    }

    .content-section {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        height: fit-content;
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

    .content-subtitle {
        color: #ea6118;
        font-weight: 600;
        font-size: 1.1rem;
        margin: 2rem 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .content-text {
        color: #475569;
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }

    .api-services-list {
        list-style: none;
        padding: 0;
        margin: 1.5rem 0;
    }

    .api-services-list li {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .api-services-list li:hover {
        background: #ea6118;
        border-color: #ea6118;
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
    }

    .api-services-list li:hover a {
        color: white;
    }

    .api-services-list a {
        color: #293b50;
        text-decoration: none;
        font-weight: 500;
        flex: 1;
        transition: all 0.3s ease;
    }

    .service-icon {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: all 0.3s ease;
    }

    .api-services-list li:hover .service-icon {
        background: white;
        color: #ea6118;
        transform: scale(1.1);
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

    .contact-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #0891b2;
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        margin-top: 2rem;
    }

    .contact-card h5 {
        color: #0891b2;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .contact-card p {
        color: #64748b;
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }

    .contact-button {
        background: linear-gradient(135deg, #0891b2, #0e7490);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 1rem 2rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .contact-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(8, 145, 178, 0.4);
        color: white;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin: 3rem 0;
    }

    .feature-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .feature-icon {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin: 0 auto 1.5rem auto;
        transition: all 0.3s ease;
    }

    .feature-card:hover .feature-icon {
        transform: scale(1.1) rotate(5deg);
    }

    .feature-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    .feature-description {
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 0;
    }

    .quick-links {
        background: linear-gradient(135deg, #fef7ed, #fed7aa);
        border: 2px solid #f59e0b;
        border-radius: 15px;
        padding: 2rem;
        margin-top: 2rem;
    }

    .quick-links h5 {
        color: #92400e;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .quick-link-item {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid #fed7aa;
    }

    .quick-link-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    .quick-link-item a {
        color: #92400e;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }

    .quick-link-item:hover a {
        color: #ea6118;
    }
</style>
@endpush

@section('content')
<div class="support-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">brightness_7</i>
                Support & Documentation
            </div>&nbsp;
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Support</li>
                </ol>
            </nav>
        </div>
<button id="backButton" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined me-1">arrow_back</i> Back
        </button>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="alert alert-success" id="flash-message">
            <i class="material-icons-outlined">check_circle</i>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" id="flash-error-message">
            <i class="material-icons-outlined">error</i>
            {{ session('error') }}
        </div>
    @endif

    <!-- Hero Section -->
    <div class="hero-section">
        <h1 class="hero-title">
            {{-- <i class="material-icons-outlined">integration_instructions</i> --}}
            Technical Documentation
        </h1>
        <p class="hero-subtitle">SMS Expert API & Developer Resources</p>
        <p class="hero-description">
            Welcome to our comprehensive technical documentation center. This support area is designed to help clients, 
            programmers, and technical developers integrate with our HTTPs/POST + SMPP APIs for powerful SMS messaging solutions.
        </p>
    </div>

    <!-- Main Content Grid -->
    <div class="main-content-grid">
        <!-- Main Content -->
        <div class="content-section">
            <h2 class="content-title">
                <i class="material-icons-outlined">api</i>
                API Services & Documentation
            </h2>
            
            <p class="content-text">
                Our robust SMS platform provides comprehensive APIs for developers and businesses looking to integrate 
                SMS messaging capabilities into their applications. Explore our technical documentation and get started 
                with our powerful messaging services.
            </p>

            <h3 class="content-subtitle">
                <i class="material-icons-outlined">list</i>
                Available API Services
            </h3>

            <ul class="api-services-list">
                <li>
                    <div class="service-icon">
                        <i class="material-icons-outlined">account_balance_wallet</i>
                    </div>
                    <a href="{{ route('technical.wholesalewalletcheck') }}">
                        Reviewing SMS and keyword wallet balances
                    </a>
                </li>
                <li>
                    <div class="service-icon">
                        <i class="material-icons-outlined">send</i>
                    </div>
                    <a href="{{ route('technical.sendingsms') }}">
                        Sending outbound SMS via HTTP
                    </a>
                </li>
                <li>
                    <div class="service-icon">
                        <i class="material-icons-outlined">settings_ethernet</i>
                    </div>
                    <a href="javascript:;">
                        Sending outbound SMS via SMPP (please call for details)
                    </a>
                </li>
                <li>
                    <div class="service-icon">
                        <i class="material-icons-outlined">receipt</i>
                    </div>
                    <a href="{{ route('technical.receivingdlrs') }}">
                        Receiving delivery receipts
                    </a>
                </li>
                <li>
                    <div class="service-icon">
                        <i class="material-icons-outlined">inbox</i>
                    </div>
                    <a href="{{ route('technical.receivingsms') }}">
                        Receiving inbound SMS
                    </a>
                </li>
                <li>
                    <div class="service-icon">
                        <i class="material-icons-outlined">vpn_key</i>
                    </div>
                    <a href="{{ route('technical.keywordwhois') }}">
                        Managing keywords
                    </a>
                </li>
            </ul>

            <h3 class="content-subtitle">
                <i class="material-icons-outlined">dashboard</i>
                Dashboard and Default Settings
            </h3>
            <p class="content-text">
                A number of settings for keywords and delivery receipts can be configured manually within the 
                <a href="{{ route('dashboard') }}" style="color: #ea6118; font-weight: 600;">Dashboard</a>. 
                Please familiarize yourself with the options and contact us if you have any questions.
            </p>

            <h3 class="content-subtitle">
                <i class="material-icons-outlined">support_agent</i>
                Account Management and Support
            </h3>
            <p class="content-text">
                For technical assistance, email 
                <a href="mailto:care@smsexpert.co.uk" style="color: #ea6118; font-weight: 600;">care@smsexpert.co.uk</a> 
                anytime. Our technical support team is ready to help you integrate and optimize your SMS messaging solutions.
            </p>
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

    <!-- Features Grid -->
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <i class="material-icons-outlined">api</i>
            </div>
            <h4 class="feature-title">RESTful APIs</h4>
            <p class="feature-description">
                Easy-to-use HTTP/HTTPS APIs for seamless integration with your applications and systems.
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <i class="material-icons-outlined">speed</i>
            </div>
            <h4 class="feature-title">High Performance</h4>
            <p class="feature-description">
                Built for scale with high-throughput capabilities and reliable message delivery worldwide.
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <i class="material-icons-outlined">security</i>
            </div>
            <h4 class="feature-title">Secure & Reliable</h4>
            <p class="feature-description">
                Enterprise-grade security with encrypted connections and comprehensive delivery reporting.
            </p>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="quick-links">
        <h5>
            <i class="material-icons-outlined">link</i>
            Quick Access Links
        </h5>
        <div class="quick-links-grid">
            <div class="quick-link-item">
                <a href="{{ route('dashboard') }}">
                    <i class="material-icons-outlined">dashboard</i>
                    Dashboard
                </a>
            </div>
            <div class="quick-link-item">
                <a href="{{ route('sendsms') }}">
                    <i class="material-icons-outlined">send</i>
                    Send SMS
                </a>
            </div>
            <div class="quick-link-item">
                <a href="{{ route('keywords') }}">
                    <i class="material-icons-outlined">vpn_key</i>
                    Keywords
                </a>
            </div>
            <div class="quick-link-item">
                <a href="{{ route('profile') }}">
                    <i class="material-icons-outlined">settings</i>
                    Settings
                </a>
            </div>
        </div>
    </div>

    <!-- Contact Support Card -->
    <div class="contact-card">
        <h5>
            <i class="material-icons-outlined">support_agent</i>
            Need Technical Assistance?
        </h5>
        <p>
            Our technical support team is here to help you integrate and optimize your SMS messaging solutions. 
            Get expert assistance with API integration, troubleshooting, and best practices.
        </p>
        <a href="mailto:care@smsexpert.co.uk" class="contact-button" target="_blank">
            <i class="material-icons-outlined">email</i>
            Contact Support
        </a>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash messages
    setTimeout(function() {
        const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
        flashMessages.forEach(msg => {
            if (msg) {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-20px)';
                setTimeout(() => msg.style.display = 'none', 300);
            }
        });
    }, 4000);

    // Smooth animations
    const elements = document.querySelectorAll('.hero-section, .content-section, .sidebar, .feature-card');
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Feature cards hover effects
    const featureCards = document.querySelectorAll('.feature-card');
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // API services list hover effects
    const serviceItems = document.querySelectorAll('.api-services-list li');
    serviceItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px) scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0) scale(1)';
        });
    });

    // Sidebar links smooth transitions
    const sidebarLinks = document.querySelectorAll('.sidebar-list a');
    sidebarLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });

    console.log('Support page loaded successfully!');
});
</script>
@endpush