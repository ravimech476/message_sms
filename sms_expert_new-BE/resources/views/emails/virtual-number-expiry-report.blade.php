@extends('emails.layouts.master')

@section('title', 'SMS Expert - Virtual Number Expiry Report')
@section('header_title', 'Virtual Number Expiry Report')

@section('content')
    <!-- Report Date Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #293B50; color: white;">
            {{ $reportData['report_date'] ?? now()->format('jS M Y') }}
        </span>
    </div>

    @if(!empty($reportData['is_debug']))
    <div class="alert alert-warning" style="text-align: center;">
        <strong>DEBUG MODE</strong><br>
        <span style="font-size: 13px;">Testing of new features - do not rely on data</span>
    </div>
    @endif

    <!-- Summary -->
    <div class="alert alert-info" style="text-align: center;">
        <strong>Virtual Number Expiry Summary</strong><br>
        <span style="font-size: 13px;">Numbers expiring within the monitoring period</span>
    </div>

    <!-- Expiry Statistics -->
    @if(!empty($reportData['stats']))
    <div class="info-card" style="background-color: #fef3c7; border: 2px solid #f59e0b;">
        <div class="info-card-header" style="background: #f59e0b;">Expiry Statistics</div>
        <table style="width: 100%;">
            @foreach($reportData['stats'] as $period => $count)
            <tr>
                <td style="padding: 12px 0; width: 60%;"><strong>{{ $period }}:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: {{ $count > 0 ? '#ef4444' : '#10b981' }}; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">{{ $count }} numbers</span>
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <!-- Report Content -->
    <div class="info-card">
        <div class="info-card-header">Detailed Report</div>
        <div style="font-size: 14px;">
            {!! $reportData['content'] ?? 'No expiring numbers found' !!}
        </div>
    </div>

    <!-- Numbers List if available -->
    @if(!empty($reportData['numbers']))
    <div class="info-card">
        <div class="info-card-header">Expiring Numbers</div>
        <table style="width: 100%; font-size: 13px;">
            <tr style="background: #f1f5f9;">
                <th style="padding: 10px; text-align: left;">Number</th>
                <th style="padding: 10px; text-align: left;">Customer</th>
                <th style="padding: 10px; text-align: left;">Expiry Date</th>
            </tr>
            @foreach($reportData['numbers'] as $number)
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px; font-family: 'Courier New', monospace;">{{ $number['number'] ?? 'N/A' }}</td>
                <td style="padding: 10px;">{{ $number['customer'] ?? 'N/A' }}</td>
                <td style="padding: 10px;">
                    @php
                        $daysLeft = $number['days_left'] ?? 0;
                        $urgencyColor = $daysLeft <= 7 ? '#ef4444' : ($daysLeft <= 30 ? '#f59e0b' : '#10b981');
                    @endphp
                    <span style="color: {{ $urgencyColor }}; font-weight: 600;">{{ $number['expiry_date'] ?? 'N/A' }}</span>
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="alert alert-warning">
        <strong>Action Required:</strong>
        <p style="margin: 10px 0 0 0;">Please review the expiring numbers and contact customers if renewal is required.</p>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated report from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Virtual Number Expiry Monitor<br>
        Generated at {{ $reportData['generated_at'] ?? now()->format('Y-m-d H:i:s T') }}
    </p>
@endsection
