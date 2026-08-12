@extends('emails.layouts.master')

@section('title', 'SMS Expert Password Reset')
@section('header_title', 'Password Reset')

@section('content')
    <p style="margin-bottom: 20px;">Hello {{ $userName ?? 'Customer' }},</p>

    <p>Thank you for contacting <strong>SMS Expert</strong>. We received a request to reset your account password.</p>

    <!-- Reset Button -->
    <div style="text-align: center; margin: 35px 0;">
        <a href="{{ route('reset.password', ['userId' => $userId]) }}" class="btn btn-primary">
            Reset Your Password
        </a>
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> If you did not request to reset your password, please contact the SMS Expert care team urgently.
    </div>

    <div class="divider"></div>

    <div class="info-card">
        <h4 style="margin: 0 0 15px 0; color: #293B50;">Need Help?</h4>
        <p style="margin: 0;">If you require any further assistance, please call one of the SMS Expert team:</p>
        <p style="margin: 15px 0 0 0; text-align: center;">
            <a href="tel:01509606305" style="font-size: 22px; font-weight: bold; color: #ea6118; text-decoration: none;">
                01509 606 305
            </a>
        </p>
    </div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>

    <p class="text-muted" style="font-size: 12px; margin-top: 20px;">
        Please note: Our opening hours are Monday to Friday, 9am to 5pm.
    </p>
@endsection
