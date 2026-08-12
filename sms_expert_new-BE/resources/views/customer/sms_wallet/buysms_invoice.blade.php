@extends('layouts.app')
@section('title')
    {{ __('Invoice') }}
@endsection

@push('style')
    <style>
        /* SMS Wallet Theme for Invoice Page */
        .invoice-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .invoice-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }

        .invoice-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .invoice-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .invoice-header {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .invoice-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .invoice-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            margin: 0.5rem 0 0 0;
        }

        .breadcrumb-container {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .breadcrumb-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* .back-btn {
                display: flex;
                align-items: center;
                font-size: 0.85rem;
                padding: 6px 12px;
                border-radius: 6px;
                transition: all 0.2s ease;
                background: linear-gradient(135deg, #64748b, #475569);
                border: none;
                color: white;
                font-weight: 500;
            } */

        /* .back-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
                color: white;
            } */

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

        .highlight-text {
            color: #293b50;
            font-weight: 600;
        }

        .payment-section {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .payment-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .payment-details {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-label {
            color: #64748b;
            font-weight: 500;
        }

        .payment-value {
            color: #293b50;
            font-weight: 700;
        }

        .invoice-section {
            background: white;
            border-radius: 15px;
            border: 2px solid #e2e8f0;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .invoice-logo {
            text-align: center;
            margin: 2rem 0;
        }

        .invoice-logo img {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .invoice-details-header {
            text-align: center;
            margin: 2rem 0;
        }

        .invoice-number {
            color: #ea6118;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .invoice-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            margin: 1rem 0;
        }

        .company-details {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .company-name {
            color: #293b50;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .company-info {
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }

        .invoice-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 1rem;
            border-color: #e2e8f0;
            vertical-align: middle;
            color: #64748b;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .table .total-row {
            background: #f8fafc;
            font-weight: 600;
            color: #293b50;
        }

        .table .grand-total-row {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            font-weight: 700;
            color: #293b50;
            font-size: 1.1rem;
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

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .invoice-container {
                padding: 1rem;
            }

            .invoice-title {
                font-size: 1.5rem;
            }

            .section-content,
            .payment-section,
            .invoice-section {
                padding: 1.5rem;
            }

            .payment-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .table-responsive {
                font-size: 0.9rem;
            }

            /* .back-btn {
                    width: 100%;
                    text-align: center;
                    margin-top: 1rem;
                } */

            .breadcrumb-container {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Override main wrapper styles */
        .main-wrapper {
            background: #f8fafc !important;
            min-height: 100vh;
        }

        .main-content {
            background: transparent;
            padding: 0;
        }

         .breadcrumb-item a {
            color: #ea6118;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #64748b;
        }
    </style>
@endpush

@section('content')
    <div class="invoice-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Invoice Details</div>&nbsp;
                <div class="breadcrumb-item active">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                </div>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <div id="flash-message" class="alert alert-success alert-modern fade-in">
                <i class="material-icons-outlined me-2">check_circle</i>
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div id="flash-error-message" class="alert alert-danger alert-modern fade-in">
                <i class="material-icons-outlined me-2">error</i>
                {{ session('error') }}
            </div>
        @endif

        <!-- Invoice Header -->
        <div class="invoice-card fade-in">
            <div class="invoice-header">
                <div class="invoice-title">SMS Order Confirmation</div>
                <div class="invoice-subtitle">Invoice #{{ $invoice->id ?? 'N/A' }}</div>
            </div>
        </div>

        <!-- Order Confirmation Section -->
        <div class="section-card fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    <div class="notification-icon">
                        <i class="material-icons-outlined">check_circle</i>
                    </div>
                    Order Confirmation
                </h2>
            </div>
            <div class="section-content">
                <p class="info-text">
                    Thank you for ordering SMS Expert credits from SMS Expert. We have just sent you a separate email
                    containing a copy of the invoice for your records, number <span
                        class="highlight-text">{{ $invoice->id ?? 'N/A' }}</span>. The
                    invoice is also shown further down this page.
                </p>
                <p class="info-text">
                    You can view/print/re-email a copy of the invoice at any time by signing in to your account on
                    the <a href="{{ route('dashboard') }}" class="highlight-text text-decoration-none">SMS Expert
                        Dashboard</a>
                    and going to the <em>Invoices</em> page.
                </p>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div class="section-card fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    <div class="notification-icon">
                        <i class="material-icons-outlined">payment</i>
                    </div>
                    Payment Instructions
                </h2>
            </div>
            <div class="section-content">
                <div class="row g-4">
                    @if ($max_amount->maxcardpurchase == 0)
                        <div class="col-md-4">
                            <h6 class="highlight-text">How to Pay</h6>
                            <p class="info-text">
                                Please pay this invoice by bank transfer. You will find our bank details on the invoice.
                            </p>
                        </div>
                    @else
                        @php
                            $maxCardPurchase = $max_amount->maxcardpurchase;
                            $vat = 0.2; // 20% VAT
                            $beforeVat = $maxCardPurchase / (1 + $vat); // Convert inclusive (with VAT) to exclusive
                        @endphp

                        <div class="col-md-4">
                            <h6 class="highlight-text">How to Pay</h6>
                            <p class="info-text">
                                Your account is configured to only allow up to
                                <strong>£ {{ number_format($maxCardPurchase, 2) }} + VAT</strong> via Cards or Paypal.
                                Therefore, please pay this invoice by bank transfer.
                                You will find our bank details on the invoice.
                                Please contact us if you wish to discuss payment methods.
                            </p>
                        </div>
                    @endif

                    {{-- <div class="col-md-4">
                        <h6 class="highlight-text">How to Pay</h6>
                        <p class="info-text">Please pay this invoice by bank transfer. You will find our bank details on the invoice.</p>
                    </div> --}}
                    <div class="col-md-4">
                        <h6 class="highlight-text">Processing Your Payment</h6>
                        <p class="info-text">We will top up your SMS wallet as soon as the payment is received and verified.
                            During office hours this can be within minutes.</p>
                    </div>
                    <div class="col-md-4">
                        <h6 class="highlight-text">Always Here to Help</h6>
                        <p class="info-text">Please call or email us for any assistance. Thank you for choosing SMS Expert
                            for your SMS services.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Details Section -->
        <div class="payment-section fade-in">
            <div class="payment-title">
                <i class="material-icons-outlined me-2">account_balance</i>
                Pay Securely by Bank Transfer
            </div>
            <div class="payment-details">
                <div class="payment-row">
                    <span class="payment-label">Pay</span>
                    <span class="payment-value">SMS Expert Ltd</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Bank</span>
                    <span class="payment-value">Tide Bank</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Sort Code</span>
                    <span class="payment-value">23-69-72</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Account</span>
                    <span class="payment-value">20177535</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Payment Reference</span>
                    <span class="payment-value">{{ $invoice->id ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Official Invoice Section -->
        <div class="invoice-section fade-in">
            <!-- Logo -->
            <div class="invoice-logo">
                <img src="{{ asset('assets/images/auth/smsexpertlogowhiteback.jpg') }}" alt="SMS Expert Logo">
            </div>

            <!-- Invoice Header -->
            <div class="invoice-details-header">
                <h3 class="invoice-number">INVOICE</h3>
                <div class="invoice-warning">
                    <i class="material-icons-outlined me-2">warning</i>
                    Payment in full is due upon receipt. SMS Expert is unable to credit the account until cleared funds are
                    received.
                </div>
            </div>

            <!-- Company and Customer Details -->
            <div class="row">
                <div class="col-md-6">
                    <div class="company-details">
                        <div class="company-name">SMS Expert Limited</div>
                        <div class="company-info">
                            79-93 Ratcliffe Road<br>
                            Sileby<br>
                            Leicestershire<br>
                            LE12 7PU<br><br>
                            Email: <strong>care@smsexpert.co.uk</strong><br>
                            Phone: <strong>01509 606305</strong><br>
                            VAT Number: <strong>GB332497592</strong><br>
                            Registered in England, No. <strong>12106151</strong><br><br>
                            <span class="highlight-text">Invoice Number: {{ $invoice->id ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="company-details">
                        <div class="company-name">Invoiced To</div>
                        <div class="company-info">
                            {{ urldecode($user->contactname ?? '') }}<br>
                            {{ urldecode($user->busname ?? '') }}<br>
                            {{ $user->address1 ?? '' }}<br>
                            {{ $user->town ?? '' }}<br>
                            {{ $user->city ?? '' }}<br>
                            {{ $user->country ?? '' }}<br>
                            {{ $user->pcode ?? '' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mt-3">
                <strong>
                    {{ (!empty($invoice) && !empty($invoice->invoicedate)) ? \Carbon\Carbon::createFromTimestamp($invoice->invoicedate, 'Europe/London')->format('j M Y') : \Carbon\Carbon::now('Europe/London')->format('j M Y') }}
                </strong>
                {{-- <strong>{{ isset($invoice) && $invoice->invoicedate ? date('D j M Y', strtotime($invoice->invoicedate)) : date('D j M Y') }}</strong> --}}
            </div>

            <!-- Invoice Table -->
            <div class="invoice-table">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Pre-purchase of SMS Expert Credits</strong></td>
                            <td class="text-end">
                                £{{ isset($invoice) && isset($invoice->orderItems) ? number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2) : '0.00' }}
                            </td>
                        </tr>
                        <tr class="total-row">
                            <td>Total</td>
                            <td class="text-end">
                                £{{ isset($invoice) && isset($invoice->orderItems) ? number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2) : '0.00' }}
                            </td>
                        </tr>
                        <tr>
                            <td>VAT (20.00%)</td>
                            <td class="text-end">
                                @php
                                    $price =
                                        isset($invoice) && isset($invoice->orderItems)
                                            ? $invoice->orderItems->invoice_nonvatprice ?? 0
                                            : 0;
                                    $vatAmount = ($price * 20) / 100;
                                @endphp
                                £{{ number_format($vatAmount, 2) }}
                            </td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Sub Total</strong></td>
                            <td class="text-end">
                                <strong>£{{ isset($invoice) && isset($invoice->orderItems) ? number_format($invoice->orderItems->invoice_fullprice ?? 0, 2) : '0.00' }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Vouchers Used</td>
                            <td class="text-end">£0.00</td>
                        </tr>
                        <tr class="grand-total-row">
                            <td><strong>GRAND TOTAL</strong></td>
                            <td class="text-end">
                                <strong>£{{ isset($invoice) && isset($invoice->orderItems) ? number_format($invoice->orderItems->invoice_fullprice ?? 0, 2) : '0.00' }}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    {{-- @include('layouts.footer') --}}
    <!-- End Footer -->
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

            // Initialize animations
            const cards = document.querySelectorAll(
                '.invoice-card, .section-card, .payment-section, .invoice-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            console.log('Modern Invoice page loaded successfully!');
        });
    </script>
@endpush
