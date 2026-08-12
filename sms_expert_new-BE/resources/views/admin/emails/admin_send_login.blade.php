@extends('emails.layouts.master')

@section('title', $subjectLine)
@section('header_title', 'Your Login Details')

@section('content')
    <p style="margin-bottom: 20px;">Hello {{ urldecode($user->contactname ?? 'Customer') }},</p>

    <p>Thank you for contacting SMS Expert.</p>

    <p>When you start using SMS Expert you will need the following login details and the site can be accessed by following this link:</p>

    <div style="text-align: center; margin: 25px 0;">
        <a href="{{ config('domains.customer')[0] ?? 'https://sms.expert' }}" class="btn btn-primary">
            Go to SMS Expert Dashboard
        </a>
    </div>

    <div class="info-card">
        <div class="info-card-header">Your Login Credentials</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Username:</strong></td>
                <td style="padding: 10px 0; text-align: right; font-family: 'Courier New', monospace;" class="highlight">{{ $user->uname }}</td>
            </tr>
        </table>
    </div>

    <p>Please log in using your existing password. If you do not remember your password, please click on the following link to reset it:</p>

    <div style="text-align: center; margin: 25px 0;">
        <a href="{{ url(route('reset.password', ['userId' => $user->id])) }}" class="btn btn-secondary">
            Reset Your Password
        </a>
    </div>

    <div class="alert alert-warning">
        <strong>Important:</strong> If you have not requested your logins, please contact the care team at SMS Expert.
    </div>

    <p>If you require any further assistance, please call one of the SMS Expert team:</p>

    <p style="text-align: center;">
        <a href="tel:01509606305" style="font-size: 22px; font-weight: bold; color: #ea6118; text-decoration: none;">
            01509 606 305
        </a>
    </p>

    <div class="divider"></div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">SMS Expert</strong><br>
        Tel: 01509 606 305
    </p>

    <p class="text-muted" style="font-size: 12px; margin-top: 20px;">
        Please be advised our opening hours are Monday to Friday - 9am till 5pm.
    </p>
@endsection
