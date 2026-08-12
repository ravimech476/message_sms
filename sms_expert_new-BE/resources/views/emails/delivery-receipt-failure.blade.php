@extends('emails.layouts.master')

@section('title', 'SMS Expert - Delivery Receipt Forwarding Failed')
@section('header_title', 'Delivery Receipt Failure')

@section('content')
    <div class="alert alert-danger">
        <strong>Alert:</strong> A delivery receipt forwarding attempt has failed. Please review the details below.
    </div>

    <div class="info-card">
        <div class="info-card-header">Failure Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 40%;"><strong>Failed URL:</strong></td>
                <td style="padding: 10px 0; word-break: break-all;">{{ $data['url'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Error Code:</strong></td>
                <td style="padding: 10px 0;" class="highlight">{{ $data['errno'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Error Message:</strong></td>
                <td style="padding: 10px 0;">{{ $data['error'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Server Response:</strong></td>
                <td style="padding: 10px 0;">{{ $data['result'] ?: 'No response received' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Retries Remaining:</strong></td>
                <td style="padding: 10px 0;" class="highlight">{{ $data['retries_left'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Daemon Name:</strong></td>
                <td style="padding: 10px 0;">{{ $data['daemon_name'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Record ID:</strong></td>
                <td style="padding: 10px 0;">{{ $data['receipt_id'] }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Contact:</strong></td>
                <td style="padding: 10px 0;">{{ $data['contact_name'] }} ({{ $data['contact_email'] }})</td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <div class="info-card-header">XML Content</div>
        <pre style="background: #f8fafc; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; margin: 0;">{!! htmlspecialchars($data['xml']) !!}</pre>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated notification from SMS Expert.<br>
        If you continue to experience issues, please contact support.
    </p>
@endsection
