@extends('layouts.app')
@section('title', 'SMS Wallet - SMS Expert')

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

        .wallet-balance {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .balance-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ea6118;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        /* .back-btn:hover {
                background-color: #f5f5f5;
            } */
    </style>
@endpush

@section('content')
    <div class="wallet-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">SMS Wallet</div> &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                            <i class="material-icons-outlined">home</i>
                        </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">SMS Wallet</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
            
        </div>

        @if (session('success'))
            <div class="alert alert-success" id="flash-message">
                <i class="material-icons-outlined">check_circle</i>
                {{ session('success') }}
            </div>
        @endif

        <!-- Wallet Balance Card -->
        <div class="wallet-card mb-4">
            <div class="wallet-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2" style="color: white; font-weight: 600;">
                            {{-- <i class="material-icons-outlined">account_balance_wallet</i> --}}
                            Welcome, {{ ucfirst($user_contactname) }}!
                        </h2>
                        <div class="balance-label">Current SMS Wallet Balance</div>
                        <div class="wallet-balance">£ {{ sprintf('%.2f', $remaining_wallet ?? 0) }}</div>
                        <p class="mb-0" style="color: rgba(255, 255, 255, 0.9); margin-top: 0.5rem;">
                            Available for pre-purchased SMS text messages
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="quick-purchase-actions">
                            <a href="{{ route('buysms') }}" class="purchase-link">
                                <i class="material-icons-outlined">shopping_cart</i>
                                Buy More SMS
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Purchase Info -->
        <div class="quick-purchase-card">
            <div class="quick-purchase-title">
                <i class="material-icons-outlined" style="font-size: 35px !important;">info</i>
                How to Pre-Purchase More SMS
            </div>
            <p class="info-text mb-3">
                To pre-purchase more SMS messages, you can <a href="{{ route('buysms') }}" class="text-decoration-none"
                    style="color: #ea6118; font-weight: 600;">buy online</a> or contact our support team for assistance.
            </p>
        </div>

        <!-- Settings Form -->
        <form action="{{ route('update.settings') }}" method="POST">
            @csrf

            @foreach ($user->reminders as $reminder)
                <!-- Daily Notification Settings -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <div class="notification-icon">
                                <i class="material-icons-outlined">notifications</i>
                            </div>
                            Daily Email Notifications
                        </h5>
                    </div>
                    <div class="section-content">
                        <!-- Email Reminder Toggle -->
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined" style="font-size: 30px !important;">email</i>
                                Email Reminder Preferences
                            </label>
                            <p class="form-text">
                                Do you wish to be reminded by email when you are running low on pre-purchased SMS?
                            </p>
                            <div class="radio-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="reminderon" id="reminderonYes"
                                        value="y" {{ $reminder->reminderon == 'y' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="reminderonYes">
                                        <i class="material-icons-outlined">check</i> Yes, notify me
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="reminderon" id="reminderonNo"
                                        value="n" {{ $reminder->reminderon == 'n' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="reminderonNo">
                                        <i class="material-icons-outlined">close</i> No, don't notify me
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Minimum Amount -->
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                {{-- <i class="material-icons-outlined">attach_money</i> --}}
                                <i class="material-icons-outlined money"></i><span
                                    style="vertical-align: -webkit-baseline-middle;padding-left: 10px;">Minimum Balance
                                    Threshold</span> </label>
                            <p class="form-text">
                                What monetary amount (in £ sterling) do you want set as a minimum to trigger your low
                                SMS
                                reminder?
                            </p>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" id="numonremind"
                                            name="numonremind" class="form-control"
                                            value="{{ $reminder->numonremind ?? '' }}" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reminder Period -->
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined" style="font-size: 30px !important;">schedule</i>
                                Follow-up Reminder Frequency
                            </label>
                            <p class="form-text">
                                How many days do you wish between follow-up reminders being sent to you?
                            </p>
                            <div class="row">
                                <div class="col-md-4">
                                    <select class="form-select" name="reminderperiod">
                                        @for ($i = 1; $i <= 14; $i++)
                                            <option value="{{ $i }}"
                                                {{ $reminder->reminderperiod == $i ? 'selected' : '' }}>
                                                {{ $i }} day{{ $i > 1 ? 's' : '' }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @foreach ($user->options as $option)
                <!-- Immediate Notification Settings -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">
                            <div class="notification-icon" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                                <i class="material-icons-outlined">priority_high</i>
                            </div>
                            Immediate Notifications
                        </h5>
                    </div>
                    <div class="section-content">
                        <!-- Immediate Email Toggle -->
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined" style="font-size: 30px !important;">warning</i>
                                Insufficient Funds Alert
                            </label>
                            <p class="form-text">
                                Do you wish to be immediately contacted via Email in the event of an SMS send failure due to
                                insufficient funds?
                            </p>
                            <div class="radio-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="immediateEmailReminderon"
                                        id="immediateEmailReminderon_yes" value="y"
                                        {{ $option->immediateEmailReminderon == 'y' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="immediateEmailReminderon_yes">
                                        <i class="material-icons-outlined">check</i> Yes, alert me immediately
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="immediateEmailReminderon"
                                        id="immediateEmailReminderonl_no" value="n"
                                        {{ $option->immediateEmailReminderon == 'n' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="immediateEmailReminderonl_no">
                                        <i class="material-icons-outlined">close</i> No, don't alert me
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Email Address -->
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined" style="font-size: 30px !important;">email</i>
                                Notification Email Address
                            </label>
                            <p class="form-text">
                                Email address where immediate notifications will be sent
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="email" id="immediateOutOfFundsNotificationEmail"
                                        name="immediateOutOfFundsNotificationEmail" class="form-control"
                                        value="{{ $option->immediateOutOfFundsNotificationEmail ?? '' }}"
                                        placeholder="your.email@example.com">
                                </div>
                            </div>
                        </div>

                        <!-- Important Notice -->
                        <div class="alert"
                            style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none;">
                            <i class="material-icons-outlined" style="font-size: 30px !important;">info</i>
                            <strong>Important:</strong> Immediate notifications will be sent a maximum of once per hour in
                            the event of an ongoing failure due to insufficient funds.
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Action Buttons -->
            <div class="section-card">
                <div class="section-content">
                    <div class="d-flex gap-3 justify-content-start">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined">save</i>
                            Save Settings
                        </button>
                        <button type="reset" class="btn btn-outline-primary">
                            <i class="material-icons-outlined">refresh</i>
                            Reset Form
                        </button>
                    </div>
                </div>
            </div>
        </form>
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

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const emailInput = document.getElementById('immediateOutOfFundsNotificationEmail');
                    const immediateEmailYes = document.getElementById('immediateEmailReminderon_yes');

                    if (immediateEmailYes && immediateEmailYes.checked && emailInput && !emailInput.value) {
                        e.preventDefault();
                        alert('Please enter an email address for immediate notifications.');
                        emailInput.focus();
                        return false;
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

            console.log('SMS Wallet page loaded successfully!');

            document.querySelectorAll('.material-icons-outlined.money').forEach(function(el) {
                el.innerHTML = '£';
                el.style.setProperty('font-size', '30px', 'important');
            });
        });
    </script>
@endpush
