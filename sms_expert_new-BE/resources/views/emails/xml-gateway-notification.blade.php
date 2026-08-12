@extends('emails.layouts.master')

@section('title', 'SMS Expert - XML Gateway Notification')
@section('header_title', 'XML-SMS Gateway Notification')

@section('content')
    <!-- Status Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        @php
            $type = $notificationData['type'] ?? 'success';
            $isError = $type === 'error';
            $badgeColor = $isError ? '#ef4444' : '#10b981';
            $badgeText = $isError ? 'ERROR' : 'SUCCESS';
        @endphp
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: {{ $badgeColor }}; color: white;">
            {{ $badgeText }}
        </span>
    </div>

    <!-- Summary -->
    @if($isError)
    <div class="alert alert-danger" style="text-align: center;">
        <strong>XML Gateway Warning/Error</strong><br>
        <span style="font-size: 13px;">An issue occurred during XML-SMS processing</span>
    </div>
    @else
    <div class="alert alert-success" style="text-align: center;">
        <strong>XML Gateway Confirmation</strong><br>
        <span style="font-size: 13px;">Your SMS submission was processed successfully</span>
    </div>
    @endif

    <!-- Details -->
    <div class="info-card" style="background-color: {{ $isError ? '#fef2f2' : '#f0fdf4' }}; border: 2px solid {{ $badgeColor }};">
        <div class="info-card-header" style="background: {{ $badgeColor }};">{{ $isError ? 'Error Details' : 'Submission Details' }}</div>
        <table style="width: 100%;">
            @if(!$isError && !empty($notificationData['messages_sent']))
            <tr>
                <td style="padding: 12px 0; width: 40%;"><strong>Messages Sent:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #10b981; color: white; padding: 6px 16px; border-radius: 4px; font-weight: 700; font-size: 18px;">{{ $notificationData['messages_sent'] }}</span>
                </td>
            </tr>
            @endif
            @if(!empty($notificationData['message']))
            <tr>
                <td style="padding: 12px 0; width: 40%;"><strong>{{ $isError ? 'Error Message' : 'Status' }}:</strong></td>
                <td style="padding: 12px 0; {{ $isError ? 'color: #991b1b;' : '' }}">{{ $notificationData['message'] }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding: 12px 0;"><strong>Time:</strong></td>
                <td style="padding: 12px 0;">{{ $notificationData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
            @if(!empty($notificationData['from_email']))
            <tr>
                <td style="padding: 12px 0;"><strong>Source Email:</strong></td>
                <td style="padding: 12px 0;">{{ $notificationData['from_email'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if($isError)
    <div class="alert alert-warning">
        <strong>Troubleshooting Tips:</strong>
        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
            <li>Check your XML format is correct</li>
            <li>Verify your account credentials</li>
            <li>Ensure you have sufficient balance</li>
            <li>Contact support if the issue persists</li>
        </ul>
    </div>
    @endif

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated notification from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        XML-SMS Gateway Service
    </p>
@endsection
