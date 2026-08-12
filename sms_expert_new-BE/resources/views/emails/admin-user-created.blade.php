@extends('emails.layouts.master')

@section('title', 'SMS Expert Admin Account Created')
@section('header_title', 'Welcome to SMS Expert Admin')

@section('content')
    @php
        $contactName = urldecode(is_object($adminUser) ? ($adminUser->contactname ?? 'User') : ($adminUser['contactname'] ?? 'User'));
        $contactEmail = is_object($adminUser) ? $adminUser->contactemail : ($adminUser['contactemail'] ?? '');
        $username = is_object($adminUser) ? $adminUser->uname : ($adminUser['uname'] ?? '');
    @endphp

    <p style="margin-bottom: 20px;">Hello {{ $contactName }},</p>

    <div class="alert alert-success">
        <strong>Your admin account has been successfully created!</strong><br>
        You can now access the SMS Expert admin panel using the credentials below.
    </div>

    <div class="info-card">
        <div class="info-card-header">Your Login Credentials</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Username:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-family: 'Courier New', monospace;" class="highlight">{{ $username }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Email:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $contactEmail }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Password:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-family: 'Courier New', monospace;" class="highlight">{{ $password }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Role:</strong></td>
                <td style="padding: 10px 0; text-align: right;">
                    <span style="display: inline-block; background: #ea6118; color: white; padding: 5px 15px; border-radius: 20px; font-size: 14px; font-weight: 600;">{{ $roleName }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $loginUrl }}" class="btn btn-primary">
            Login to Admin Panel
        </a>
    </div>

    <p class="text-muted" style="text-align: center; font-size: 14px;">
        Or copy this URL: <a href="{{ $loginUrl }}" style="color: #ea6118;">{{ $loginUrl }}</a>
    </p>

    <div class="alert alert-warning">
        <strong>Security Recommendations:</strong>
        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
            <li>Please change your password after your first login</li>
            <li>Do not share your login credentials with anyone</li>
            <li>Always log out when you're done using the admin panel</li>
            <li>Report any suspicious activity to your administrator</li>
        </ul>
    </div>

    <p>If you have any questions or need assistance, please contact your system administrator.</p>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>

    <p class="text-muted" style="font-size: 12px; margin-top: 20px;">
        This is an automated message. Please do not reply to this email.
    </p>
@endsection
