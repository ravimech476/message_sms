@extends('emails.layouts.master')

@section('title', 'SMS Expert - Pooled Virtual Numbers Alert')
@section('header_title', 'Pooled Virtual Numbers Alert')

@section('content')
    <!-- Alert Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #f59e0b; color: white;">
            MONITORING ALERT
        </span>
    </div>

    <!-- Alert Summary -->
    <div class="alert alert-warning" style="text-align: center;">
        <strong>{{ $alertData['title'] ?? 'Pooled Virtual Numbers Alert' }}</strong><br>
        <span style="font-size: 13px;">An issue has been detected with pooled virtual numbers</span>
    </div>

    <!-- Alert Content -->
    <div class="info-card" style="background-color: #fef3c7; border: 2px solid #f59e0b;">
        <div class="info-card-header" style="background: #f59e0b;">Alert Details</div>
        <div style="padding: 15px; font-size: 14px;">
            {!! $alertData['content'] ?? 'No details available' !!}
        </div>
    </div>

    <!-- Statistics if available -->
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

    <div class="alert alert-info">
        <strong>Note:</strong> This alert was triggered by the pooled virtual numbers monitoring system. Please investigate if action is required.
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated alert from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Pooled Virtual Numbers Monitor<br>
        Generated at {{ now()->format('Y-m-d H:i:s T') }}
    </p>
@endsection
