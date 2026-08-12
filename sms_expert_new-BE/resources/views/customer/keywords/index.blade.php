@extends('layouts.app')
@section('title', 'Keywords - SMS Expert')

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

        .keywords-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .keywords-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .keywords-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .keywords-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
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

        .data-table {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: #293b50;
            font-weight: 700;
            padding: 1.5rem 1rem;
            border: none;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #475569;
        }

        .table tbody tr:hover {
            background: #f8fafc;
            transform: translateX(2px);
            transition: all 0.2s ease;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .keyword-badge {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dedicated-badge {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .virtual-number {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            color: #293b50;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .expiry-badge {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .expiry-soon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .expiry-expired {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .config-button {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .config-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .stats-summary {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #ea6118;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .no-data i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
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

        .keywords-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .keyword-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .keyword-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .keyword-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .keyword-card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .keyword-card-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
        }

        .keyword-card-content {
            margin-bottom: 1.5rem;
        }

        .keyword-card-footer {
            display: flex;
            justify-content: between;
            align-items: center;
            gap: 1rem;
        }

        /* Keyword Registration Section Styles */
        .registration-card {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .registration-card h5 {
            color: #b45309;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .registration-card p {
            color: #78350f;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .registration-card .text-muted {
            color: #92400e !important;
        }

        .virtual-number-card {
            background: linear-gradient(135deg, #ede9fe, #ddd6fe);
            border: 2px solid #8b5cf6;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .virtual-number-card h5 {
            color: #6d28d9;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .virtual-number-card p {
            color: #5b21b6;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .contract-card {
            background: linear-gradient(135deg, #fef2f2, #fecaca);
            border: 2px solid #ef4444;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .contract-card h5 {
            color: #dc2626;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .contract-card p {
            color: #991b1b;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .contract-card a {
            color: #dc2626;
            text-decoration: underline;
        }
    </style>
@endpush

@section('content')
    <div class="keywords-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">vpn_key</i>
                    Keywords
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Keywords</li>
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

        <!-- Keyword Registration Section -->
        @php
            $userInfo = Session::get('user_info');
            $userBigId = $userInfo['bigid'] ?? null;
            
            // Get user data for keyword wallet and platinum access
            $userData = null;
            $keywordsLeft = 0;
            $platinumAccess = 'n';
            $keywordsLeftDisplay = 0;
            
            if ($userBigId) {
                $userData = DB::table('users')
                    ->selectRaw('(platkeywordwallet / NULLIF(platkeywordcost, 0)) as keywordsleft, platinumaccess')
                    ->where('bigid', $userBigId)
                    ->first();
                
                if ($userData) {
                    $keywordsLeft = $userData->keywordsleft ?? 0;
                    $platinumAccess = $userData->platinumaccess ?? 'n';
                    $keywordsLeftDisplay = floor($keywordsLeft);
                }
            }
            
            // Determine shortcode based on platinum access
            $codesCanRegOn = '60300';
        @endphp

        <div class="registration-card">
            <h5>
                <i class="material-icons-outlined">add_circle</i>
                Register Keywords...
            </h5>
            @if ($keywordsLeft < 1.00)
                <p>
                    &rarr; You can't currently register any more keywords. Please contact us to discuss setting up additional keywords.
                </p>
            @elseif ($keywordsLeft == 1.00)
                <p>
                    &rarr; You can <a href="{{ route('keywords.register') }}">register</a> 1 more keyword on {{ $codesCanRegOn }}. Please contact us if you think you'll need more keywords.
                </p>
            @else
                <p>
                    &rarr; You can <a href="{{ route('keywords.register') }}">register</a> {{ $keywordsLeftDisplay }} more keywords on {{ $codesCanRegOn }}. Please contact us if you think you'll need even more keywords.
                </p>
            @endif
        </div>

        <!-- Virtual Number Section -->
        <div class="virtual-number-card">
            <h5>
                <i class="material-icons-outlined">phone_android</i>
                Register Dedicated Virtual Mobile Number...
            </h5>
            <p>
                &rarr; Please contact us to discuss setting up dedicated virtual numbers.
            </p>
        </div>

        <!-- Contract Reminder Section -->
        <div class="contract-card">
            <h5>
                <i class="material-icons-outlined">description</i>
                Contractual Reminder...
            </h5>
            <p>
                &rarr; By continuing to use the SMS Expert services you agree to the latest <a href="{{ route('contracts.index') }}">contract</a> and to abide by all applicable laws and regulations.
            </p>
        </div>

        <!-- Information Card -->
        <div class="info-card">
            <h5>
                <i class="material-icons-outlined">info</i>
                About Keywords & Virtual Numbers
            </h5>
            <p>
                Keywords are text commands that customers can send to your virtual numbers to trigger automated responses,
                subscriptions, or other services. Each keyword is associated with a virtual number and can be configured
                with various modules to handle different types of interactions.
            </p>
        </div>

        <!-- Main Content -->
        <div class="keywords-card">
            @if (isset($itaggs) && !empty($itaggs))
                <!-- Statistics Summary -->
                <div class="stats-summary">
                    <div class="stats-number">{{ count($itaggs) }}</div>
                    <div class="stats-label">Keywords</div>
                </div>

                <!-- Keywords Grid View -->
                <div class="keywords-grid">
                    @foreach ($itaggs as $itagg)
                        @php
                            $inputDate = $itagg['expiry'] ?? '2026-08-01';
                            $timestamp = strtotime($inputDate);
                            $formattedDate = date('d M Y', $timestamp);
                            $daysUntilExpiry = ceil((strtotime($inputDate) - time()) / (60 * 60 * 24));

                           if ($itagg['keyword'] == '*') {
                                // $keyword = 'Dedicated Number';
                                $keywordType = 'dedicated';
                            } else {
                                // $keyword = $itagg['keyword'];
                                $keywordType = 'keyword';
                            }

                            $keyword = $itagg['keyword'];

                            $expiryClass = 'expiry-badge';
                            if ($daysUntilExpiry < 0) {
                                $expiryClass .= ' expiry-expired';
                                $expiryStatus = 'Expired';
                            } elseif ($daysUntilExpiry < 30) {
                                $expiryClass .= ' expiry-soon';
                                $expiryStatus = $daysUntilExpiry . ' days left';
                            } else {
                                $expiryStatus = 'Active until ' . $formattedDate;
                            }
                        @endphp

                        <div class="keyword-card">
                            <div class="keyword-card-content">
                                <div class="mb-3">
                                    <strong class="d-block mb-2" style="color: #293b50;">
                                        <i class="material-icons-outlined">vpn_key</i>
                                        Keyword
                                    </strong>
                                    <div>
                                        <span class="keyword-badge">
                                            <i class="material-icons-outlined">vpn_key</i>
                                            {{ $keyword }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <strong class="d-block mb-2" style="color: #293b50;">
                                        <i class="material-icons-outlined">phone_in_talk</i>
                                        Virtual Number
                                    </strong>
                                    @if (!empty($itagg['number']))
                                        <div class="virtual-number">
                                            <i class="material-icons-outlined">phone</i>
                                            {{ $itagg['number'] }}
                                        </div>
                                    @else
                                        <div>
                                            <p>No Virtual Number Assigned</p>
                                        </div>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <strong class="d-block mb-2" style="color: #293b50;">
                                        <i class="material-icons-outlined">schedule</i>
                                        Status
                                    </strong>
                                    <span class="{{ $expiryClass }}">
                                        <i class="material-icons-outlined">
                                            @if ($daysUntilExpiry < 0)
                                                error
                                            @elseif($daysUntilExpiry < 30)
                                                warning
                                            @else
                                                check_circle
                                            @endif
                                        </i>
                                        {{ $expiryStatus }}
                                    </span>
                                </div>
                            </div>

                            <div class="keyword-card-footer">
                            <a href="{{ route('keywords.config', ['itaggId' => $itagg['id'], 'keyword' => $keyword]) }}" 
                               class="config-button">
                                <i class="material-icons-outlined">settings</i>
                                Configure
                            </a>
                        </div>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- No Data State -->
                <div class="section-card">
                    <div class="no-data">
                        <i class="material-icons-outlined">vpn_key_off</i>
                        <h4>No Keywords Found</h4>
                        <p>You don't have any active keywords or virtual numbers configured yet. Contact support to set up
                            your first keyword.</p>
                    </div>
                </div>
            @endif
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
            const cards = document.querySelectorAll('.keyword-card, .section-card, .keywords-card, .registration-card, .virtual-number-card, .contract-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add hover effects to keyword cards
            const keywordCards = document.querySelectorAll('.keyword-card');
            keywordCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            console.log('Keywords page loaded successfully!');
        });
    </script>
@endpush
