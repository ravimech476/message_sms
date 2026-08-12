@extends('emails.layouts.master')

@section('title', 'SMS Expert - Database Tidy Report')
@section('header_title', 'Database Tidy Report')

@section('content')
    <!-- Report Date Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #293B50; color: white;">
            {{ $reportData['date'] ?? now()->format('Y-m-d H:i') }}
        </span>
    </div>

    <!-- Summary -->
    <div class="alert alert-info" style="text-align: center;">
        <strong>Database Maintenance Completed</strong><br>
        <span style="font-size: 13px;">Automated cleanup tasks have been executed successfully</span>
    </div>

    <!-- Report Content -->
    <div class="info-card">
        <div class="info-card-header">Cleanup Report</div>
        <div style="background: #f8fafc; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; word-wrap: break-word;">{{ $reportData['content'] ?? 'No report content available' }}</div>
    </div>

    <!-- Statistics -->
    @if(!empty($reportData['stats']))
    <div class="info-card">
        <div class="info-card-header">Statistics</div>
        <table style="width: 100%;">
            @foreach($reportData['stats'] as $stat => $value)
            <tr>
                <td style="padding: 10px 0; width: 50%;"><strong>{{ $stat }}:</strong></td>
                <td style="padding: 10px 0;">{{ $value }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated database maintenance report from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Generated at {{ now()->format('Y-m-d H:i:s T') }}
    </p>
@endsection
