@extends('emails.layouts.master')

@section('title', 'SMS Expert - Inbound SMS Notification')
@section('header_title', 'Inbound SMS Received')

@section('content')
    <!-- Keyword Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #10b981; color: white;">
            Keyword: {{ $smsData['keyword'] ?? 'N/A' }}
        </span>
    </div>

    <!-- Summary Alert -->
    <div class="alert alert-success" style="text-align: center;">
        <strong>New SMS Message Received</strong><br>
        <span style="font-size: 13px;">Someone has texted your SMS keyword</span>
    </div>

    <!-- Message Details -->
    <div class="info-card" style="background-color: #f0fdf4; border: 2px solid #10b981;">
        <div class="info-card-header" style="background: #10b981;">Message Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 35%;"><strong>From (Mobile):</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-weight: 600;">{{ $smsData['source'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Network:</strong></td>
                <td style="padding: 12px 0;">{{ $smsData['network_name'] ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Date & Time:</strong></td>
                <td style="padding: 12px 0;">{{ $smsData['date'] ?? now()->format('l, jS F Y \a\t H:i') }}</td>
            </tr>
            @if(!empty($smsData['subkeyword']))
            <tr>
                <td style="padding: 12px 0;"><strong>Subkeyword:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #ea6118; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">{{ $smsData['subkeyword'] }}</span>
                </td>
            </tr>
            @endif
            @if(!empty($smsData['rest']))
            <tr>
                <td style="padding: 12px 0;"><strong>Following Text:</strong></td>
                <td style="padding: 12px 0; font-style: italic;">{{ $smsData['rest'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Full Message -->
    <div class="info-card">
        <div class="info-card-header">Complete Message</div>
        <div style="background: #1e1e1e; color: #10b981; padding: 20px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 14px; white-space: pre-wrap; word-wrap: break-word;">{{ $smsData['message'] ?? 'No message content' }}</div>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This notification was sent by <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Your SMS keyword service is active and working
    </p>
@endsection
