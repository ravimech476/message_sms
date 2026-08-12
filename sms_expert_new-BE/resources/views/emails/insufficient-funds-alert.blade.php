@extends('emails.layouts.master')

@section('title', 'SMS Expert - Insufficient Wallet Funds')
@section('header_title', 'Insufficient Wallet Funds')

@section('content')
    <div class="alert alert-danger" style="text-align: center;">
        <strong style="font-size: 18px;">ATTENTION</strong><br>
        Your SMS send attempt has failed due to insufficient wallet credit.
    </div>

    <p style="margin-bottom: 20px;">Dear {{ $data['contact_name'] }},</p>

    <p>This is an automated alert to inform you that your SMS Expert account has run out of wallet credit while attempting to send a non-premium rate SMS message.</p>

    <div class="info-card">
        <div class="info-card-header">Account Status</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Account Username:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $data['username'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Status:</strong></td>
                <td style="padding: 10px 0; text-align: right;"><span style="color: #ef4444; font-weight: 600;">Insufficient Funds</span></td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Action Required:</strong></td>
                <td style="padding: 10px 0; text-align: right;" class="highlight">Top up your SMS wallet immediately</td>
            </tr>
        </table>
    </div>

    <p><strong>To ensure your SMS services continue without interruption, please top up your wallet immediately:</strong></p>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $data['login_url'] }}" class="btn btn-success" style="background: #10b981;">
            Top Up Wallet Now
        </a>
    </div>

    <div class="info-card">
        <div class="info-card-header">How to Top Up Your Wallet</div>
        <ol style="margin: 0; padding-left: 20px;">
            <li style="padding: 8px 0;">Click the button above or go to {{ $data['login_url'] }}</li>
            <li style="padding: 8px 0;">Log in to your control panel</li>
            <li style="padding: 8px 0;">Click on the "SMS Wallet" menu link</li>
            <li style="padding: 8px 0;">Select your desired credit amount</li>
            <li style="padding: 8px 0;">Complete the purchase</li>
        </ol>
    </div>

    <div class="alert alert-danger">
        <strong>Important:</strong> Any SMS sending attempts will continue to fail until you add sufficient credit to your wallet.
    </div>

    <p>If you need assistance or have any questions, please don't hesitate to contact our support team.</p>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Support Team</strong>
    </p>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated notification from SMS Expert.<br>
        You are receiving this email because your account is configured to receive wallet alerts.<br>
        To manage your notification preferences, please log in to your control panel.
    </p>
@endsection
