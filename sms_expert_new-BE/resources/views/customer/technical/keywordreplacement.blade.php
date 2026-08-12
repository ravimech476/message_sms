@extends('layouts.app')
@section('title', 'Keyword Replacement - SMS Expert Documentation')

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

        /* .code-block {
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
        } */

        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            border: 1px solid #334155;

            /* 🔑 key fixes */
            max-width: 100%;
            overflow-x: auto;
        }

        .code-url {
            display: block;
            white-space: normal;
            /* allow wrapping */
            word-break: break-all;
            /* break long URLs */
            overflow-wrap: anywhere;
            /* modern browsers */
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

        .replacement-flow {
            background: linear-gradient(135deg, #fef7ed, #fed7aa);
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
        }

        .replacement-flow h5 {
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
                    <i class="material-icons-outlined icon-primary">swap_horiz</i>
                    Keyword Replacement
                </div>
                &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('technical.support') }}">Support</a>
                        </li>
                        <li class="breadcrumb-item active">Keyword Replacement</li>
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
                <i class="material-icons-outlined">swap_horiz</i>
                Keyword Replacement API
            </h1>
            <p class="hero-description">
                Replace an existing keyword with a new one while preserving all configurations and settings.
                Seamlessly transition to new keywords without losing your setup.
            </p>
        </div>

        <!-- Replacement Flow -->
        <div class="replacement-flow">
            <h5>
                <i class="material-icons-outlined">timeline</i>
                How Keyword Replacement Works
            </h5>
            <div class="flow-steps">
                <div class="flow-step">
                    <div class="flow-step-number">1</div>
                    <strong>Check Availability</strong><br>
                    <small>New keyword must be available</small>
                </div>
                <div class="flow-arrow">→</div>
                <div class="flow-step">
                    <div class="flow-step-number">2</div>
                    <strong>Preserve Settings</strong><br>
                    <small>All configurations transferred</small>
                </div>
                <div class="flow-arrow">→</div>
                <div class="flow-step">
                    <div class="flow-step-number">3</div>
                    <strong>Replace Keyword</strong><br>
                    <small>Old keyword released, new one active</small>
                </div>
                <div class="flow-arrow">→</div>
                <div class="flow-step">
                    <div class="flow-step-number">4</div>
                    <strong>Immediate Active</strong><br>
                    <small>New keyword ready for use</small>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-content">
                <h2 class="content-title">
                    <i class="material-icons-outlined">transform</i>
                    Keyword Replacement Overview
                </h2>

                <p class="content-text">
                    Replace an existing keyword with a new one while maintaining all forwarding configurations and settings.
                    This is ideal when you need to change keyword text without losing your setup.
                </p>

                <div class="info-card">
                    <h6>
                        <i class="material-icons-outlined">info</i>
                        Replacement Benefits
                    </h6>
                    <p>
                        <strong>Configuration Preservation:</strong> All email and URL forwarding settings are automatically
                        transferred<br>
                        <strong>Seamless Transition:</strong> No service interruption during the replacement process<br>
                        <strong>Cost Effective:</strong> Avoid re-registration costs while updating keyword text<br>
                        <strong>Instant Activation:</strong> New keyword becomes immediately active after replacement
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
                                        <code>{{ config('app.url') }}api/plat_keyreplace</code>
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
                                        <code>$usr, $pwd [as supplied during account signup],
                                            $oldkeyword [current keyword to replace],
                                            $newkeyword [new keyword to use],
                                            $oldshortcode ["60300" default],
                                            $newshortcode ["60300" default],
                                        </code>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <i class="material-icons-outlined">api</i>
                                    API Responses
                                </th>
                                <td>
                                    See <a href="{{ route('technical.wholesaleapiresponsecodes') }}"
                                        style="color: #ea6118; font-weight: 600;">keyword tool response codes</a> for
                                    detailed response information and error handling.
                                </td>
                            </tr>
                            <tr>
                                <th>
                                    <i class="material-icons-outlined">link</i>
                                    Example URL
                                </th>
                                <td>
                                    <div class="code-block">
                                        <code class="code-url">
                                           {{ config('app.url') }}api/plat_keyreplace?usr=XXX&pwd=YYY&oldkeyword=hello&newkeyword=bye&oldshortcode=60300&newshortcode=60300
                                        </code>
                                    </div>
                                </td>
                                {{-- <td>
                                    <div class="code-block">
                                        <code>{{ config('app.url') }}api/plat_keyreplace?usr=XXX&pwd=YYY&oldkeyword=OLDTEXT&newkeyword=NEWTEXT&shortcode=60300</code>
                                    </div>
                                </td> --}}
                            </tr>
                            <tr>
                                <th>
                                    <i class="material-icons-outlined">web</i>
                                    Browser Testing
                                </th>
                                <td>
                                    Paste the example URL into your browser and modify the parameters to test keyword
                                    replacement quickly.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="info-card">
                    <h6>
                        <i class="material-icons-outlined">lightbulb</i>
                        Best Practices
                    </h6>
                    <p>
                        <strong>Availability Check:</strong> Verify new keyword availability before attempting
                        replacement<br>
                        <strong>Test Configuration:</strong> Confirm forwarding works correctly after replacement<br>
                        <strong>Document Changes:</strong> Keep records of keyword replacements for your campaigns<br>
                        <strong>Communication:</strong> Inform users about keyword changes if necessary
                    </p>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            @include('customer.technical.sidebar')
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth animations
            const elements = document.querySelectorAll('.hero-section, .main-content, .sidebar, .replacement-flow');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    element.style.transition = 'all 0.5s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });

            console.log('Keyword replacement documentation page loaded successfully!');
        });
    </script>
@endpush
