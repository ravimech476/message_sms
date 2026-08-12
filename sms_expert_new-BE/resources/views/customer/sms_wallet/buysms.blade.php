@extends('layouts.app')
@section('title')
    {{ __('Buy SMS') }}
@endsection

@push('style')
    <style>
        .wallet-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .wallet-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .wallet-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .wallet-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .wallet-header {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            margin: -1px -1px 0 -1px;
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
            background: #f8fafc;
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

        .info-text {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .form-text {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .form-control,
        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
            outline: none;
        }

        .form-check {
            margin-bottom: 0.75rem;
        }

        .form-check-input {
            margin-top: 0.125rem;
        }

        .form-check-input:checked {
            background-color: #ea6118;
            border-color: #ea6118;
        }

        .form-check-label {
            color: #475569;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        }

        .btn-outline-primary {
            color: #ea6118;
            border: 2px solid #ea6118;
            background: transparent;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: #ea6118;
            color: white;
            transform: translateY(-1px);
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
            font-size: 1.1rem;
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
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .quick-purchase-card {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .quick-purchase-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .purchase-link {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .purchase-link:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        }

        .radio-group {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .input-group {
            position: relative;
        }

        .input-group .form-control {
            padding-left: 2.5rem;
        }

        .input-group::before {
            content: '£';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-weight: 600;
            z-index: 10;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #ea6118, #d1520e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-right: 1rem;
        }

        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .payment-info-box {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .amount-section {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.05), rgba(41, 59, 80, 0.05));
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(234, 97, 24, 0.2);
        }

        .user-welcome {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li i {
            color: #ea6118;
            font-size: 18px;
        }

        .terms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 1rem;
        }

        .terms-column ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .terms-column li {
            padding: 0.5rem 0;
            color: #64748b;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .terms-column li::before {
            content: '•';
            color: #ea6118;
            font-weight: bold;
            margin-top: 0.1rem;
        }

        .highlight-box {
            background: linear-gradient(135deg, #e0f2fe, #b3e5fc);
            border: 1px solid #0288d1;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            color: #01579b;
        }
    </style>
@endpush

@section('content')
    <div class="wallet-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Buy SMS</div> &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Buy SMS</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <div id="flash-message" class="alert alert-success">
                <i class="material-icons-outlined me-2">check_circle</i>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div id="flash-error-message" class="alert alert-danger">
                <i class="material-icons-outlined me-2">error</i>
                {{ session('error') }}
            </div>
        @endif

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- SMS Information Section -->
            <div class="col-lg-8">
                <!-- SMS Delivery Info -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <div class="notification-icon">
                                <i class="material-icons-outlined">send</i>
                            </div>
                            SMS Delivery Platform
                        </h5>
                    </div>
                    <div class="section-content">
                        <p class="info-text mb-3">
                            With SMS Expert you can send SMS conveniently to individual mobiles or groups of them directly
                            from our easy to use online dashboard or from our API's whenever you wish.
                        </p>

                        <ul class="feature-list">
                            <li>
                                <i class="material-icons-outlined">flash_on</i>
                                <span>Instant delivery to individual mobiles or groups</span>
                            </li>
                            <li>
                                <i class="material-icons-outlined">integration_instructions</i>
                                <span>Easy-to-use online dashboard and powerful APIs</span>
                            </li>
                            <li>
                                <i class="material-icons-outlined">security</i>
                                <span>Secure and reliable SMS delivery platform</span>
                            </li>
                            <li>
                                <i class="material-icons-outlined">support</i>
                                <span>24/7 customer support and technical assistance</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- How to Buy SMS -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <div class="notification-icon" style="background: linear-gradient(135deg, #16a34a, #15803d);">
                                <i class="material-icons-outlined">shopping_cart</i>
                            </div>
                            How to Buy SMS Credits
                        </h5>
                    </div>
                    <div class="section-content">
                        <p class="info-text">
                            Simply choose the monetary amount you wish to spend on new SMS credits in the purchase form and
                            click the orange "Buy SMS" button. We'll then display and email you an invoice containing
                            payment options.
                        </p>

                        <div class="highlight-box">
                            <strong>
                                <i class="material-icons-outlined me-1">info</i>
                                Step-by-Step Process:
                            </strong>
                            <br>
                            1. Enter the amount you wish to purchase (minimum £ 100)<br>
                            2. Click "Buy SMS" to generate your invoice<br>
                            3. Receive your invoice via email with payment instructions<br>
                            4. Complete payment and your credits will be added to your wallet
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchase Form Section -->
            <div class="col-lg-4">
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <div class="notification-icon" style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
                                <i class="material-icons-outlined">payment</i>
                            </div>
                            Purchase SMS Credits
                        </h5>
                    </div>
                    <div class="section-content">
                        <div class="user-welcome">
                            <i class="material-icons-outlined">person</i>
                            You are logged in as: {{ ucfirst($user_contactname) ?? 'User' }}
                        </div>

                        <form action="{{ route('buysms.invoice') }}" method="POST" id="buySmsForm">
                            @csrf

                            <div class="amount-section">
                                <div class="form-group">
                                    <label class="form-label">
                                        {{-- <i class="material-icons-outlined me-1">attach_money</i> --}}
                                        Purchase Amount
                                    </label>
                                    <p class="form-text">
                                        Please enter the amount (in pounds) that you wish to purchase.
                                    </p>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="whatvolume" id="whatvolume"
                                            value="bespoke" checked>
                                        <label class="form-check-label" for="whatvolume">
                                            Custom Amount
                                        </label>
                                    </div>

                                    <div class="input-group">
                                        <input type="number" name="whatvolumeother" class="form-control" value="500"
                                            min="100" step="1" required>
                                    </div>
                                    <small class="text-muted">+ VAT will be added</small>
                                </div>
                            </div>
                            @if ($max_amount->maxcardpurchase == 0)
                                <div class="payment-info-box">
                                    <i class="material-icons-outlined me-2">account_balance</i>
                                    <strong>Payment Method:</strong><br>
                                    Your account is currently configured to only let you pay by bank transfer.
                                    If you wish to pay by card or Paypal then contact us before creating the invoice.
                                </div>
                            @else
                                @php
                                    $maxCardPurchase = $max_amount->maxcardpurchase;
                                    $vat = 0.2; // 20% VAT
                                    $beforeVat = $maxCardPurchase / (1 + $vat); // e.g. 1000 / 1.2 = 833.3333
                                @endphp

                                <div class="payment-info-box">
                                    <i class="material-icons-outlined me-2">credit_card</i>
                                    <strong>Payment Method:</strong><br>
                                    Your account is configured to let you pay up to
                                    <strong>£ {{ number_format($beforeVat, 2) }} + VAT</strong> by card or Paypal.<br>
                                    For larger amounts, please pay by bank transfer, or contact us if you wish to buy more
                                    than
                                    <strong>£ {{ number_format($beforeVat, 2) }} + VAT</strong> by card/Paypal.
                                </div>
                            @endif
                            {{-- <div class="payment-info-box">
                                <i class="material-icons-outlined me-2">account_balance</i>
                                <strong>Payment Method:</strong><br>
                                Your account is currently configured to only let you pay by bank transfer. If you wish to pay by card or Paypal then contact us before creating the invoice.
                            </div> --}}

                            <button type="submit" class="btn btn-primary w-100 mb-3"
                                onclick="return confirm('We will now generate, display & email an SMS Expert invoice so that you can make a secure purchase.\n\nAre you sure?');">
                                <i class="material-icons-outlined me-2">receipt</i>
                                Buy SMS
                            </button>

                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="material-icons-outlined me-1" style="font-size: 16px;">lock</i>
                                    Secure SSL encrypted transaction
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms and Conditions -->
        <div class="section-card">
            <div class="section-header">
                <h5 class="section-title">
                    <div class="notification-icon" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                        <i class="material-icons-outlined">description</i>
                    </div>
                    Additional Notes for Pre-purchasing Blocks of Bulk SMS
                </h5>
            </div>
            <div class="section-content">
                <div class="terms-grid">
                    <div class="terms-column">
                        <h6 class="form-label">
                            <i class="material-icons-outlined me-1">receipt_long</i>
                            Payment & Pricing
                        </h6>
                        <ul>
                            <li>Add VAT to all prices</li>
                            <li>Cleared bank transfer payments are required unless alternative arrangements have been agreed
                            </li>
                            <li>We can only credit wallets once we see cleared funds</li>
                            <li>Payment in full is due within 3 working days (unless agreed prior to invoice)</li>
                        </ul>
                    </div>

                    <div class="terms-column">
                        <h6 class="form-label">
                            <i class="material-icons-outlined me-1">policy</i>
                            Service Terms
                        </h6>
                        <ul>
                            <li>In many cases we process your payment and topup your online wallet immediately, however
                                please allow upto 24 hours for this</li>
                            <li>Pre-purchased SMS is non-refundable</li>
                            <li>Rates shown are for SMS delivery to UK mobiles only</li>
                            <li>SMS on non-UK mobiles or landlines will vary, please enquire</li>
                            <li>SMS Expert reserves right to cancel invoice or revise terms/pricing if payment is late</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            setTimeout(function() {
                let flashMessage = document.getElementById('flash-message');
                if (flashMessage) {
                    flashMessage.style.opacity = '0';
                    flashMessage.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        flashMessage.style.display = 'none';
                    }, 300);
                }
            }, 4000);

            setTimeout(function() {
                let flashMessage = document.getElementById('flash-error-message');
                if (flashMessage) {
                    flashMessage.style.opacity = '0';
                    flashMessage.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        flashMessage.style.display = 'none';
                    }, 300);
                }
            }, 5000);

            // Form validation
            const form = document.getElementById('buySmsForm');
            const amountInput = document.querySelector('input[name="whatvolumeother"]');

            if (amountInput) {
                amountInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    const minAmount = 100;

                    if (value < minAmount) {
                        this.style.borderColor = '#dc2626';
                    } else {
                        this.style.borderColor = '#ea6118';
                    }
                });
            }

            // Smooth animations for cards
            const cards = document.querySelectorAll('.section-card, .wallet-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            console.log('Buy SMS page loaded successfully!');
        });
    </script>
@endpush
