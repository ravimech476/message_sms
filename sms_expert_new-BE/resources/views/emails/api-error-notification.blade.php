@extends('emails.layouts.master')

@section('title', 'SMS Expert - Mobile API Error')
@section('header_title', 'API Error Alert')

@section('content')
    <!-- Severity Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        @php
            $severity = $errorData['severity'] ?? 'high';
            $severityColor = match($severity) {
                'critical' => '#ef4444',
                'high' => '#f59e0b',
                'medium' => '#eab308',
                default => '#0ea5e9',
            };
        @endphp
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; text-transform: uppercase; background: {{ $severityColor }}; color: white;">
            {{ strtoupper($severity) }} SEVERITY
        </span>
    </div>

    <!-- Error Summary -->
    <div class="alert alert-danger" style="text-align: center;">
        <strong style="font-size: 16px;">API Error Detected</strong><br>
        <span style="font-size: 14px;">{{ $errorData['type'] ?? 'Unknown Error Type' }}</span>
    </div>

    <!-- Quick Summary -->
    <div class="info-card" style="background-color: #fef2f2; border: 2px solid #ef4444;">
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 30%;"><strong>Endpoint:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: #0ea5e9; color: white;">{{ $errorData['request']['method'] ?? 'N/A' }}</span>
                    <span style="font-family: 'Courier New', monospace;">{{ $errorData['request']['path'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Status Code:</strong></td>
                <td style="padding: 12px 0;">
                    @php
                        $statusCode = $errorData['response']['status_code'] ?? 500;
                        $statusBg = $statusCode >= 500 ? '#ef4444' : '#f59e0b';
                    @endphp
                    <span style="display: inline-block; padding: 6px 16px; border-radius: 4px; font-weight: 700; font-size: 18px; background: {{ $statusBg }}; color: white;">{{ $statusCode }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Timestamp:</strong></td>
                <td style="padding: 12px 0;">{{ $errorData['timestamp'] ?? now() }}</td>
            </tr>
        </table>
    </div>

    <!-- Error Message -->
    <div class="info-card">
        <div class="info-card-header" style="background: #ef4444;">Error Message</div>
        <div style="background: #fef2f2; padding: 15px; border-radius: 4px; border: 1px solid #fecaca; color: #991b1b; font-family: 'Courier New', monospace; font-size: 14px; word-break: break-word;">
            {{ $errorData['exception']['message'] ?? $errorData['response']['message'] ?? 'No error message available' }}
        </div>
    </div>

    <!-- Request Details -->
    <div class="info-card">
        <div class="info-card-header">Request Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Full URL:</strong></td>
                <td style="padding: 10px 0; word-break: break-all;">{{ $errorData['request']['url'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Client IP:</strong></td>
                <td style="padding: 10px 0;">
                    <span style="display: inline-block; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-weight: 600;" class="highlight">{{ $errorData['request']['ip'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>User Agent:</strong></td>
                <td style="padding: 10px 0; word-break: break-all; font-size: 13px;">{{ $errorData['request']['user_agent'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>User ID:</strong></td>
                <td style="padding: 10px 0;">{{ $errorData['request']['user_id'] ?? 'Guest' }}</td>
            </tr>
            @if(!empty($errorData['request']['user_bigid']))
            <tr>
                <td style="padding: 10px 0;"><strong>User BigID:</strong></td>
                <td style="padding: 10px 0;">{{ $errorData['request']['user_bigid'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Exception Details -->
    @if(isset($errorData['exception']))
    <div class="info-card" style="background-color: #fef2f2;">
        <div class="info-card-header" style="background: #ef4444;">Exception Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Exception Class:</strong></td>
                <td style="padding: 10px 0; color: #ef4444; font-weight: 600;">{{ $errorData['exception']['class'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>File:</strong></td>
                <td style="padding: 10px 0; font-family: 'Courier New', monospace; font-size: 13px; word-break: break-all;">{{ $errorData['exception']['file'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Line Number:</strong></td>
                <td style="padding: 10px 0;">
                    <span style="display: inline-block; background: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 16px;">{{ $errorData['exception']['line'] ?? 'N/A' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Stack Trace -->
    @if(isset($errorData['exception']['trace']) && count($errorData['exception']['trace']) > 0)
    <div class="info-card">
        <div class="info-card-header">Stack Trace (Top 10)</div>
        @foreach($errorData['exception']['trace'] as $index => $frame)
        <div style="background: {{ $index === 0 ? '#fef2f2' : '#f8fafc' }}; border-left: 3px solid {{ $index === 0 ? '#ef4444' : '#6b7280' }}; padding: 10px; margin: 6px 0; border-radius: 4px; font-size: 12px;">
            <strong>#{{ $index }}</strong>
            <span style="color: #0066cc;">{{ $frame['file'] ?? 'unknown' }}</span>:<span style="color: #10b981; font-weight: 600;">{{ $frame['line'] ?? 0 }}</span>
            @if(!empty($frame['function']))
            <br><span style="color: #7c3aed; font-style: italic;">{{ $frame['function'] }}</span>
            @endif
        </div>
        @endforeach
    </div>
    @endif
    @endif

    <!-- Request Body -->
    @if(isset($errorData['request']['body']) && !empty($errorData['request']['body']))
    <div class="info-card">
        <div class="info-card-header">Request Body</div>
        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; margin: 0;">{{ json_encode($errorData['request']['body'], JSON_PRETTY_PRINT) }}</pre>
    </div>
    @endif

    <!-- Environment Info -->
    @if(isset($errorData['environment']))
    <div class="info-card">
        <div class="info-card-header">Environment</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 8px 0; width: 35%;"><strong>Environment:</strong></td>
                <td style="padding: 8px 0;">
                    <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; background: {{ ($errorData['environment']['app_env'] ?? 'production') === 'production' ? '#ef4444' : '#f59e0b' }}; color: white;">
                        {{ strtoupper($errorData['environment']['app_env'] ?? 'N/A') }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>PHP Version:</strong></td>
                <td style="padding: 8px 0;">{{ $errorData['environment']['php_version'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Laravel Version:</strong></td>
                <td style="padding: 8px 0;">{{ $errorData['environment']['laravel_version'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated API error notification from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Mobile API Error Monitoring System<br>
        To configure notifications, update <code>config/api_monitor.php</code>
    </p>
@endsection
