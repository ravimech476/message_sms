@extends('emails.layouts.master')

@section('title', 'SMS Expert - Funds Alert')
@section('header_title', 'Funds Alert')

@section('content')
    <!-- Alert Type Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        @php
            $alertType = $alertData['alert_type'] ?? 'FundsAlert';
            $isStatus = $alertType === 'FundsStatus';
            $badgeColor = $isStatus ? '#0ea5e9' : '#ef4444';
        @endphp
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: {{ $badgeColor }}; color: white;">
            {{ strtoupper($alertType) }}
        </span>
    </div>

    <!-- Alert Summary -->
    @if($isStatus)
    <div class="alert alert-info" style="text-align: center;">
        <strong>Funds Status Report</strong><br>
        <span style="font-size: 13px;">Current balance overview</span>
    </div>
    @else
    <div class="alert alert-danger" style="text-align: center;">
        <strong>Low Funds Warning</strong><br>
        <span style="font-size: 13px;">One or more balances are below threshold</span>
    </div>
    @endif

    <!-- Alerts List -->
    @if(!empty($alertData['alerts']))
    <div class="info-card" style="background-color: {{ $isStatus ? '#f0f9ff' : '#fef2f2' }}; border: 2px solid {{ $badgeColor }};">
        <div class="info-card-header" style="background: {{ $badgeColor }};">{{ $isStatus ? 'Status Details' : 'Alert Details' }}</div>
        @foreach($alertData['alerts'] as $alert)
        <div style="padding: 12px; border-bottom: 1px solid #e5e7eb; {{ $loop->last ? 'border-bottom: none;' : '' }}">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: {{ $isStatus ? '#0ea5e9' : '#ef4444' }}; margin-right: 10px;"></span>
            {{ $alert }}
        </div>
        @endforeach
    </div>
    @endif

    <!-- Wallet Overview -->
    @if(!empty($alertData['wallet_exposure']))
    <div class="info-card">
        <div class="info-card-header">Wallet Overview</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 50%;"><strong>Total Wallet Exposure:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #ea6118; color: white; padding: 6px 16px; border-radius: 4px; font-weight: 700; font-size: 18px;">&pound;{{ number_format($alertData['wallet_exposure'], 2) }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Check Time:</strong></td>
                <td style="padding: 12px 0;">{{ $alertData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>
    </div>
    @endif

    @if(!$isStatus)
    <div class="alert alert-warning">
        <strong>Recommended Action:</strong>
        <p style="margin: 10px 0 0 0;">Please check the supplier balances and top up if necessary to avoid service interruption.</p>
    </div>
    @endif

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated funds alert from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Financial Monitoring System
    </p>
@endsection
