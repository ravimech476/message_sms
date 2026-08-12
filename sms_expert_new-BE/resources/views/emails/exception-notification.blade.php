@extends('emails.layouts.master')

@section('title', 'SMS Expert - Application Exception Alert')
@section('header_title', 'Application Error Alert')

@section('content')
    <!-- Environment Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; text-transform: uppercase; background: {{ ($exceptionData['environment'] ?? 'production') === 'production' ? '#ef4444' : '#f59e0b' }}; color: white;">
            {{ strtoupper($exceptionData['environment'] ?? 'UNKNOWN') }} ENVIRONMENT
        </span>
    </div>

    <!-- Error Summary Alert -->
    <div class="alert alert-danger" style="text-align: center;">
        <strong style="font-size: 16px;">{{ class_basename($exceptionData['exception_class'] ?? 'Exception') }}</strong>
    </div>

    <!-- Quick Error Summary -->
    <div class="info-card" style="background-color: #fef2f2; border: 2px solid #ef4444;">
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 30%;"><strong>Error Type:</strong></td>
                <td style="padding: 12px 0; color: #ef4444; font-weight: 600;">{{ $exceptionData['exception_class'] ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Error Message:</strong></td>
                <td style="padding: 12px 0; color: #991b1b; font-weight: 600;">{{ $exceptionData['exception_message'] ?? 'No message' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>File:</strong></td>
                <td style="padding: 12px 0; font-family: 'Courier New', monospace; font-size: 13px; word-break: break-all;">{{ $exceptionData['file'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Line Number:</strong></td>
                <td style="padding: 12px 0;"><span style="display: inline-block; background: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 16px;">{{ $exceptionData['line'] ?? 'N/A' }}</span></td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Error Code:</strong></td>
                <td style="padding: 12px 0;">{{ $exceptionData['exception_code'] ?: '0' }}</td>
            </tr>
        </table>
    </div>

    <!-- Timestamp and App Info -->
    <div class="info-card">
        <div class="info-card-header">When & Where</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Timestamp:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['timestamp'] ?? now() }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Application:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['app_name'] ?? 'SMS Expert' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>App URL:</strong></td>
                <td style="padding: 10px 0; word-break: break-all;">{{ $exceptionData['app_url'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Request Information -->
    <div class="info-card">
        <div class="info-card-header">Request Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Request URL:</strong></td>
                <td style="padding: 10px 0; word-break: break-all;">{{ $exceptionData['url'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>HTTP Method:</strong></td>
                <td style="padding: 10px 0;">
                    <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; background: #0ea5e9; color: white;">{{ $exceptionData['method'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Client IP Address:</strong></td>
                <td style="padding: 10px 0;">
                    <span style="display: inline-block; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-weight: 600;" class="highlight">{{ $exceptionData['ip'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>User Agent:</strong></td>
                <td style="padding: 10px 0; word-break: break-all; font-size: 13px;">{{ $exceptionData['user_agent'] ?? 'N/A' }}</td>
            </tr>
            @if(!empty($exceptionData['referer']))
            <tr>
                <td style="padding: 10px 0;"><strong>Referer:</strong></td>
                <td style="padding: 10px 0; word-break: break-all;">{{ $exceptionData['referer'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- User Information (if logged in) -->
    @if(!empty($exceptionData['user_id']))
    <div class="info-card">
        <div class="info-card-header">Logged In User</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>User ID:</strong></td>
                <td style="padding: 10px 0;" class="highlight">{{ $exceptionData['user_id'] }}</td>
            </tr>
            @if(!empty($exceptionData['user_email']))
            <tr>
                <td style="padding: 10px 0;"><strong>Email:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['user_email'] }}</td>
            </tr>
            @endif
            @if(!empty($exceptionData['user_name']))
            <tr>
                <td style="padding: 10px 0;"><strong>Username:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['user_name'] }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    <!-- Request Headers -->
    @if(!empty($exceptionData['headers']))
    <div class="info-card">
        <div class="info-card-header">Request Headers</div>
        <table style="width: 100%; font-size: 13px;">
            @foreach($exceptionData['headers'] as $headerName => $headerValue)
            <tr>
                <td style="padding: 6px 0; width: 35%; color: #6b7280;"><strong>{{ $headerName }}:</strong></td>
                <td style="padding: 6px 0; word-break: break-all;">{{ is_array($headerValue) ? implode(', ', $headerValue) : $headerValue }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <!-- Request Input -->
    @if(!empty($exceptionData['input']) && is_array($exceptionData['input']) && count($exceptionData['input']) > 0)
    <div class="info-card">
        <div class="info-card-header">Request Input Data</div>
        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; margin: 0;">{{ json_encode($exceptionData['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
    @endif

    <!-- Stack Trace -->
    @if(!empty($exceptionData['trace']) && is_array($exceptionData['trace']))
    <div class="info-card">
        <div class="info-card-header">Stack Trace (Recent {{ count($exceptionData['trace']) }} Calls)</div>
        @foreach($exceptionData['trace'] as $index => $traceItem)
        <div style="background: {{ $index === 0 ? '#fef2f2' : '#f8fafc' }}; border-left: 3px solid {{ $index === 0 ? '#ef4444' : '#6b7280' }}; padding: 12px; margin: 8px 0; border-radius: 4px; font-size: 13px;">
            <div style="margin-bottom: 5px;"><strong style="color: #293B50;">#{{ $index }}</strong></div>
            <div style="color: #0066cc; font-weight: 500; word-break: break-all;">{{ $traceItem['file'] ?? 'unknown' }}</div>
            <div style="margin-top: 4px;">
                Line: <span style="color: #10b981; font-weight: 600; background: #ecfdf5; padding: 2px 8px; border-radius: 3px;">{{ $traceItem['line'] ?? 'N/A' }}</span>
            </div>
            @if(!empty($traceItem['function']))
            <div style="margin-top: 4px; color: #7c3aed; font-style: italic; font-family: 'Courier New', monospace;">{{ $traceItem['function'] }}</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Server Information -->
    @if(!empty($exceptionData['server']))
    <div class="info-card">
        <div class="info-card-header">Server Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>PHP Version:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['server']['php_version'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Laravel Version:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['server']['laravel_version'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Server Software:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['server']['server_software'] ?? 'N/A' }}</td>
            </tr>
            @if(!empty($exceptionData['server']['memory_usage']))
            <tr>
                <td style="padding: 10px 0;"><strong>Memory Usage:</strong></td>
                <td style="padding: 10px 0;">{{ $exceptionData['server']['memory_usage'] }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    <div class="divider"></div>

    <!-- Action Required -->
    <div class="alert alert-warning">
        <strong>Action Required:</strong> Please investigate this error immediately if it's affecting production users.
    </div>

    <p class="text-muted" style="font-size: 12px; text-align: center; margin-top: 20px;">
        This is an automated error notification from {{ $exceptionData['app_name'] ?? 'SMS Expert' }}<br>
        Generated at {{ $exceptionData['timestamp'] ?? now() }}<br>
        Please do not reply to this email.
    </p>
@endsection
