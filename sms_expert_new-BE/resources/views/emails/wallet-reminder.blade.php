@extends('emails.layouts.master')

@section('title', 'SMS Expert Wallet Reminder')
@section('header_title', 'Wallet Reminder')

@section('content')
    <p style="margin-bottom: 20px;">Hi {{ $data['contact_name'] }},</p>

    <div class="alert alert-warning" style="text-align: center;">
        <strong style="font-size: 18px;">Your SMS Expert wallet balance is low</strong><br>
        <span style="font-size: 24px; font-weight: bold; color: #ea6118;">£{{ $data['wallet_balance'] }}</span>
    </div>

    <p>Please top up in plenty of time to ensure your SMS services continue uninterrupted.</p>

    <!-- How to Top Up -->
    <div class="info-card">
        <div class="info-card-header">How to Top Up Your Wallet</div>
        <ul style="margin: 0; padding-left: 20px;">
            <li style="padding: 8px 0;">Contact us to request an invoice</li>
            <li style="padding: 8px 0;">Generate your own invoice by logging in to the SMS Expert Dashboard</li>
            <li style="padding: 8px 0;">Order online through your account</li>
        </ul>
    </div>

    <!-- CTA Button -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $data['login_url'] }}" class="btn btn-primary">
            Go to SMS Expert Dashboard
        </a>
    </div>

    <p>If your volume requirements have changed, please contact us to discuss your ongoing SMS plans.</p>

    <div class="divider"></div>

    <!-- Manage Notifications -->
    <div class="info-card" style="background-color: #f1f5f9;">
        <h4 style="margin: 0 0 10px 0; color: #293B50;">Manage Your Notifications</h4>
        <p style="margin: 0; font-size: 14px;">
            Login to the <a href="{{ $data['login_url'] }}" style="color: #ea6118;">SMS Expert Dashboard</a>
            to modify or unsubscribe from these SMS wallet reminder alerts whenever you wish.
        </p>
    </div>

    <p>Please reply to this email if you wish to discuss your SMS Expert account with us at any time.</p>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated wallet reminder for account: <strong>{{ $data['username'] }}</strong><br>
        You are receiving this email because your wallet balance is below your configured threshold.
    </p>
@endsection
