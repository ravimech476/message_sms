@extends('emails.layouts.master')

@section('title', 'SMS Expert - Daily Send Limit Reached')
@section('header_title', 'Daily Send Limit Reached')

@section('content')
    <div class="alert alert-warning" style="text-align: center;">
        <strong style="font-size: 18px;">Important Notice</strong><br>
        You have reached your daily SMS send limit.
    </div>

    <p><strong>Account: {{ $data['username'] }}, {{ $data['business_name'] }}</strong></p>

    <p style="margin-bottom: 20px;">Hi {{ $data['contact_name'] }},</p>

    <p>You have reached your send limit of <strong class="highlight">{{ $data['bulk_throughput'] }}</strong> SMS text messages per day. Any further SMS sent today will fail (at no cost).</p>

    <div class="info-card">
        <div class="info-card-header">Account Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Username:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $data['username'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Business:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ $data['business_name'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Daily Limit:</strong></td>
                <td style="padding: 10px 0; text-align: right;" class="highlight">{{ $data['bulk_throughput'] }} messages</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Messages Sent Today:</strong></td>
                <td style="padding: 10px 0; text-align: right;" class="highlight">{{ $data['messages_sent_today'] }}</td>
            </tr>
        </table>
    </div>

    <p>This is a safety and security limit and we're happy to set it higher, as long as you have sufficient pre-paid funds.</p>

    <p>Please contact us to discuss your account and make any necessary changes.</p>

    <div class="divider"></div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">The SMS Expert Team</strong>
    </p>

    <p class="text-muted" style="font-size: 12px; margin-top: 20px;">
        This is an automated notification from SMS Expert.<br>
        If you need to increase your daily limit, please contact our support team.
    </p>
@endsection
