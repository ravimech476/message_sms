@extends('emails.layouts.master')

@section('title', 'SMS Expert - SMPP Checks Report')
@section('header_title', 'SMPP Regular Checks Report')

@section('content')
    <!-- Status Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        @php
            $hasErrors = !empty($reportData['errors']);
            $badgeColor = $hasErrors ? '#ef4444' : '#10b981';
            $status = $hasErrors ? 'ISSUES DETECTED' : 'ALL CHECKS PASSED';
        @endphp
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: {{ $badgeColor }}; color: white;">
            {{ $status }}
        </span>
    </div>

    <!-- Summary -->
    @if($hasErrors)
    <div class="alert alert-danger" style="text-align: center;">
        <strong>SMPP Issues Detected</strong><br>
        <span style="font-size: 13px;">Some SMPP checks have failed - please review</span>
    </div>
    @else
    <div class="alert alert-success" style="text-align: center;">
        <strong>All SMPP Checks Passed</strong><br>
        <span style="font-size: 13px;">System is operating normally</span>
    </div>
    @endif

    <!-- Report Content -->
    <div class="info-card">
        <div class="info-card-header">Check Results</div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; word-wrap: break-word;">{{ $reportData['content'] ?? 'No report content available' }}</div>
    </div>

    <!-- Errors Section -->
    @if($hasErrors)
    <div class="info-card" style="background-color: #fef2f2; border: 2px solid #ef4444;">
        <div class="info-card-header" style="background: #ef4444;">Errors Found</div>
        @foreach($reportData['errors'] as $error)
        <div style="padding: 12px; border-bottom: 1px solid #fecaca; {{ $loop->last ? 'border-bottom: none;' : '' }}">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; margin-right: 10px;"></span>
            {{ $error }}
        </div>
        @endforeach
    </div>
    @endif

    <!-- Timestamp -->
    <div class="info-card">
        <div class="info-card-header">Check Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Check Time:</strong></td>
                <td style="padding: 10px 0;">{{ $reportData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Environment:</strong></td>
                <td style="padding: 10px 0;">{{ $reportData['environment'] ?? config('app.env') }}</td>
            </tr>
        </table>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated SMPP check report from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        SMPP Monitoring System
    </p>
@endsection
