@extends('emails.layouts.master')

@section('title', 'SMS Expert - Cron Job Failure Alert')
@section('header_title', 'Cron Job Failure Alert')

@section('content')
    <!-- Environment Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; text-transform: uppercase; background: {{ ($cronData['environment'] ?? 'production') === 'production' ? '#ef4444' : '#f59e0b' }}; color: white;">
            {{ strtoupper($cronData['environment'] ?? 'UNKNOWN') }} ENVIRONMENT
        </span>
    </div>

    <!-- Error Summary -->
    <div class="alert alert-danger" style="text-align: center;">
        <strong style="font-size: 16px;">Scheduled Task Failed</strong><br>
        <span style="font-size: 14px;">{{ $cronData['description'] ?? 'A scheduled command did not complete successfully.' }}</span>
    </div>

    <!-- Command Details -->
    <div class="info-card" style="background-color: #fef2f2; border: 2px solid #ef4444;">
        <div class="info-card-header" style="background: #ef4444;">Failed Command</div>
        <div style="background: #1e1e1e; color: #10b981; padding: 15px; margin: -20px; margin-top: 15px; border-radius: 0 0 8px 8px; font-family: 'Courier New', monospace; font-size: 14px; font-weight: 600;">
            php artisan {{ $cronData['command'] ?? 'unknown' }}
        </div>
    </div>

    <!-- Timing Information -->
    <div class="info-card">
        <div class="info-card-header">Execution Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Timestamp:</strong></td>
                <td style="padding: 10px 0;">{{ $cronData['timestamp'] ?? now() }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Application:</strong></td>
                <td style="padding: 10px 0;">{{ $cronData['app_name'] ?? 'SMS Expert' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>App URL:</strong></td>
                <td style="padding: 10px 0;">{{ $cronData['app_url'] ?? config('app.url') }}</td>
            </tr>
            @if(!empty($cronData['server_ip']))
            <tr>
                <td style="padding: 10px 0;"><strong>Server IP:</strong></td>
                <td style="padding: 10px 0;">
                    <span style="display: inline-block; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-weight: 600;" class="highlight">{{ $cronData['server_ip'] }}</span>
                </td>
            </tr>
            @endif
            @if(!empty($cronData['hostname']))
            <tr>
                <td style="padding: 10px 0;"><strong>Hostname:</strong></td>
                <td style="padding: 10px 0;">{{ $cronData['hostname'] }}</td>
            </tr>
            @endif
            @if(!empty($cronData['execution_time']))
            <tr>
                <td style="padding: 10px 0;"><strong>Execution Time:</strong></td>
                <td style="padding: 10px 0;">{{ $cronData['execution_time'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Error Details (if exception occurred) -->
    @if(!empty($cronData['exception']))
    <div class="info-card" style="background-color: #fef2f2;">
        <div class="info-card-header" style="background: #ef4444;">Exception Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0; width: 35%;"><strong>Error Type:</strong></td>
                <td style="padding: 10px 0; color: #ef4444; font-weight: 600;">{{ $cronData['exception']['class'] ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Error Message:</strong></td>
                <td style="padding: 10px 0; color: #991b1b;">{{ $cronData['exception']['message'] ?? 'No message' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>File:</strong></td>
                <td style="padding: 10px 0; font-family: 'Courier New', monospace; font-size: 13px; word-break: break-all;">{{ $cronData['exception']['file'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Line Number:</strong></td>
                <td style="padding: 10px 0;">
                    <span style="display: inline-block; background: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 16px;">{{ $cronData['exception']['line'] ?? 'N/A' }}</span>
                </td>
            </tr>
        </table>
    </div>
    @endif

    <!-- Error Output -->
    @if(!empty($cronData['error_output']))
    <div class="info-card">
        <div class="info-card-header">Error Output</div>
        <pre style="background: #1e1e1e; color: #ef4444; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; margin: 0; max-height: 300px; overflow-y: auto;">{{ $cronData['error_output'] }}</pre>
    </div>
    @endif

    <!-- Stack Trace -->
    @if(!empty($cronData['exception']['trace']))
    <div class="info-card">
        <div class="info-card-header">Stack Trace</div>
        @foreach(array_slice($cronData['exception']['trace'], 0, 8) as $index => $traceItem)
        <div style="background: {{ $index === 0 ? '#fef2f2' : '#f8fafc' }}; border-left: 3px solid {{ $index === 0 ? '#ef4444' : '#6b7280' }}; padding: 10px; margin: 6px 0; border-radius: 4px; font-size: 12px;">
            <strong>#{{ $index }}</strong>
            <span style="color: #0066cc;">{{ $traceItem['file'] ?? 'unknown' }}</span>:<span style="color: #10b981; font-weight: 600;">{{ $traceItem['line'] ?? 0 }}</span>
            @if(!empty($traceItem['function']))
            <br><span style="color: #7c3aed; font-style: italic;">{{ $traceItem['function'] }}</span>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Recommended Actions -->
    <div class="alert alert-info">
        <strong>Recommended Actions:</strong>
        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
            <li>Check the application logs: <code>storage/logs/laravel.log</code></li>
            <li>Verify the command can run manually: <code>php artisan {{ $cronData['command'] ?? 'command' }}</code></li>
            <li>Check if dependencies or external services are available</li>
            <li>Review recent code changes that might affect this command</li>
            <li>Check server resources (disk space, memory, CPU)</li>
            <li>Verify database connections are working</li>
        </ul>
    </div>

    <!-- Server Information -->
    @if(!empty($cronData['server']))
    <div class="info-card">
        <div class="info-card-header">Server Information</div>
        <table style="width: 100%;">
            @if(!empty($cronData['server']['php_version']))
            <tr>
                <td style="padding: 8px 0; width: 35%;"><strong>PHP Version:</strong></td>
                <td style="padding: 8px 0;">{{ $cronData['server']['php_version'] }}</td>
            </tr>
            @endif
            @if(!empty($cronData['server']['memory_usage']))
            <tr>
                <td style="padding: 8px 0;"><strong>Memory Usage:</strong></td>
                <td style="padding: 8px 0;">{{ $cronData['server']['memory_usage'] }}</td>
            </tr>
            @endif
            @if(!empty($cronData['server']['disk_free']))
            <tr>
                <td style="padding: 8px 0;"><strong>Disk Free:</strong></td>
                <td style="padding: 8px 0;">{{ $cronData['server']['disk_free'] }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated cron failure notification from {{ $cronData['app_name'] ?? 'SMS Expert' }}<br>
        Generated at {{ $cronData['timestamp'] ?? now() }}<br>
        Please do not reply to this email.
    </p>
@endsection
