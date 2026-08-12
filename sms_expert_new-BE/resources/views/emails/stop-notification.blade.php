@extends('emails.layouts.master')

@section('title', 'SMS Expert - STOP Notification')
@section('header_title', 'STOP Request - Action Required')

@section('content')
    <!-- Alert Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #ef4444; color: white;">
            ACTION REQUIRED
        </span>
    </div>

    <!-- Warning Alert -->
    <div class="alert alert-danger" style="text-align: center;">
        <strong>{{ $stopData['command_type'] ?? 'STOP' }} Request Received</strong><br>
        <span style="font-size: 13px;">A mobile number has requested to opt-out of your messages</span>
    </div>

    <!-- Request Details -->
    <div class="info-card" style="background-color: #fef2f2; border: 2px solid #ef4444;">
        <div class="info-card-header" style="background: #ef4444;">Opt-Out Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 35%;"><strong>Mobile Number:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-weight: 600;">{{ $stopData['source'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Destination:</strong></td>
                <td style="padding: 12px 0;">{{ $stopData['destination'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Command Type:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #ef4444; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">{{ $stopData['command_type'] ?? 'STOP' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Time:</strong></td>
                <td style="padding: 12px 0;">{{ $stopData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>
    </div>

    <!-- Action Required -->
    <div class="alert alert-warning">
        <strong>Required Action:</strong>
        <p style="margin: 10px 0 0 0;">
            Please remove this mobile number from your database(s) immediately to comply with opt-out regulations.
        </p>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated opt-out notification from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        If you need further information, please contact support
    </p>
@endsection
