@extends('emails.layouts.master')

@section('title', 'SMS Expert - Heartbeat Alert')
@section('header_title', 'SMS Heartbeat Alert')

@section('content')
    <!-- Alert Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        @php
            $severity = $alertData['severity'] ?? 'warning';
            $badgeColor = match($severity) {
                'critical' => '#ef4444',
                'error' => '#ef4444',
                'warning' => '#f59e0b',
                default => '#0ea5e9',
            };
        @endphp
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: {{ $badgeColor }}; color: white;">
            {{ strtoupper($severity) }} ALERT
        </span>
    </div>

    <!-- Alert Summary -->
    @if($severity === 'critical' || $severity === 'error')
    <div class="alert alert-danger" style="text-align: center;">
        <strong>{{ $alertData['title'] ?? 'SMS Heartbeat Alert' }}</strong><br>
        <span style="font-size: 13px;">A critical issue has been detected with SMS delivery</span>
    </div>
    @else
    <div class="alert alert-warning" style="text-align: center;">
        <strong>{{ $alertData['title'] ?? 'SMS Heartbeat Alert' }}</strong><br>
        <span style="font-size: 13px;">A potential issue has been detected</span>
    </div>
    @endif

    <!-- Alert Content -->
    <div class="info-card" style="background-color: {{ $severity === 'critical' ? '#fef2f2' : '#fef3c7' }}; border: 2px solid {{ $badgeColor }};">
        <div class="info-card-header" style="background: {{ $badgeColor }};">Alert Details</div>
        <div style="padding: 15px; font-size: 14px;">
            {!! $alertData['content'] ?? 'No details available' !!}
        </div>
    </div>

    <!-- Statistics -->
    @if(!empty($alertData['stats']))
    <div class="info-card">
        <div class="info-card-header">Current Statistics</div>
        <table style="width: 100%;">
            @foreach($alertData['stats'] as $stat => $value)
            <tr>
                <td style="padding: 10px 0; width: 50%;"><strong>{{ $stat }}:</strong></td>
                <td style="padding: 10px 0;">{{ $value }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <!-- Last Check Info -->
    <div class="info-card">
        <div class="info-card-header">Check Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Alert Time:</strong></td>
                <td style="padding: 10px 0;">{{ $alertData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
            @if(!empty($alertData['last_success']))
            <tr>
                <td style="padding: 10px 0;"><strong>Last Success:</strong></td>
                <td style="padding: 10px 0;">{{ $alertData['last_success'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="alert alert-info">
        <strong>Recommended Action:</strong>
        <p style="margin: 10px 0 0 0;">Please check the SMS delivery system and supplier connections. If issues persist, contact technical support.</p>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated alert from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        SMS Heartbeat Monitoring System
    </p>
@endsection
