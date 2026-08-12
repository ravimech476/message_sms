@extends('emails.layouts.master')

@section('title', 'SMS Expert - URL Forward Alert')
@section('header_title', 'URL Forward Daemon Alert')

@section('content')
    <!-- Alert Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        @php
            $alertType = $alertData['type'] ?? 'warning';
            $badgeColor = $alertType === 'error' ? '#ef4444' : '#f59e0b';
        @endphp
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: {{ $badgeColor }}; color: white;">
            {{ strtoupper($alertType) }}
        </span>
    </div>

    <!-- Alert Summary -->
    @if($alertType === 'error')
    <div class="alert alert-danger" style="text-align: center;">
        <strong>{{ $alertData['title'] ?? 'URL Forward Error' }}</strong><br>
        <span style="font-size: 13px;">An error has occurred in the URL forwarding system</span>
    </div>
    @else
    <div class="alert alert-warning" style="text-align: center;">
        <strong>{{ $alertData['title'] ?? 'URL Forward Alert' }}</strong><br>
        <span style="font-size: 13px;">An issue requires attention in the URL forwarding system</span>
    </div>
    @endif

    <!-- Alert Content -->
    <div class="info-card" style="background-color: {{ $alertType === 'error' ? '#fef2f2' : '#fef3c7' }}; border: 2px solid {{ $badgeColor }};">
        <div class="info-card-header" style="background: {{ $badgeColor }};">Alert Details</div>
        <div style="padding: 15px; font-size: 14px;">
            {!! $alertData['content'] ?? 'No details available' !!}
        </div>
    </div>

    <!-- URL Details if available -->
    @if(!empty($alertData['url_details']))
    <div class="info-card">
        <div class="info-card-header">URL Information</div>
        <table style="width: 100%;">
            @if(!empty($alertData['url_details']['url']))
            <tr>
                <td style="padding: 10px 0; width: 25%;"><strong>URL:</strong></td>
                <td style="padding: 10px 0; word-break: break-all; font-family: 'Courier New', monospace; font-size: 12px;">{{ $alertData['url_details']['url'] }}</td>
            </tr>
            @endif
            @if(!empty($alertData['url_details']['response_code']))
            <tr>
                <td style="padding: 10px 0;"><strong>Response Code:</strong></td>
                <td style="padding: 10px 0;">
                    @php
                        $code = $alertData['url_details']['response_code'];
                        $codeColor = $code >= 200 && $code < 300 ? '#10b981' : ($code >= 400 ? '#ef4444' : '#f59e0b');
                    @endphp
                    <span style="display: inline-block; background: {{ $codeColor }}; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">{{ $code }}</span>
                </td>
            </tr>
            @endif
            @if(!empty($alertData['url_details']['error']))
            <tr>
                <td style="padding: 10px 0;"><strong>Error:</strong></td>
                <td style="padding: 10px 0; color: #ef4444;">{{ $alertData['url_details']['error'] }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    <!-- Timestamp -->
    <div class="info-card">
        <div class="info-card-header">Alert Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Alert Time:</strong></td>
                <td style="padding: 10px 0;">{{ $alertData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated alert from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        URL Forward Daemon Monitor
    </p>
@endsection
