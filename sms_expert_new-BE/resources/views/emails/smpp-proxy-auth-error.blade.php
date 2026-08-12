@extends('emails.layouts.master')

@section('title', 'SMS Expert - SMPP Proxy Authentication Error')
@section('header_title', 'SMPP Authentication Error')

@section('content')
    <!-- Error Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #ef4444; color: white;">
            AUTHENTICATION FAILED
        </span>
    </div>

    <!-- Error Summary -->
    <div class="alert alert-danger" style="text-align: center;">
        <strong>SMPP Proxy Authentication Error</strong><br>
        <span style="font-size: 13px;">An authentication attempt has failed</span>
    </div>

    <!-- Error Details -->
    <div class="info-card" style="background-color: #fef2f2; border: 2px solid #ef4444;">
        <div class="info-card-header" style="background: #ef4444;">Error Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 35%;"><strong>Error Message:</strong></td>
                <td style="padding: 12px 0; color: #991b1b; font-weight: 600;">{{ $errorData['error_message'] ?? 'Authentication failed' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>IP Address:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #f1f5f9; padding: 6px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-weight: 600;">{{ $errorData['ip_address'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Username:</strong></td>
                <td style="padding: 12px 0; font-family: 'Courier New', monospace;">{{ $errorData['username'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Password:</strong></td>
                <td style="padding: 12px 0; font-family: 'Courier New', monospace;">{{ $errorData['password_masked'] ?? '********' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>SMPP User:</strong></td>
                <td style="padding: 12px 0;">{{ $errorData['smpp_username'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Time:</strong></td>
                <td style="padding: 12px 0;">{{ $errorData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>
    </div>

    <div class="alert alert-warning">
        <strong>Security Note:</strong>
        <p style="margin: 10px 0 0 0;">Multiple failed authentication attempts from the same IP address may indicate a brute force attack. Consider reviewing your SMPP security settings.</p>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated security alert from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        SMPP Proxy Authentication Monitor
    </p>
@endsection
