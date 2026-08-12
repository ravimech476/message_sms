@extends('emails.layouts.master')

@section('title', 'SMS Expert - Route Auto-Corrected')
@section('header_title', 'Route Auto-Correction Notice')

@section('content')
    <!-- Info Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #f59e0b; color: white;">
            AUTO-CORRECTED
        </span>
    </div>

    <!-- Summary Alert -->
    <div class="alert alert-warning" style="text-align: center;">
        <strong>API Route Parameter Corrected</strong><br>
        <span style="font-size: 13px;">The sms.mes API was called with an incorrect route parameter</span>
    </div>

    <!-- Correction Details -->
    <div class="info-card" style="background-color: #fef3c7; border: 2px solid #f59e0b;">
        <div class="info-card-header" style="background: #f59e0b;">Correction Details</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 35%;"><strong>Original Route:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #ef4444; color: white; padding: 4px 12px; border-radius: 4px; font-family: 'Courier New', monospace; text-decoration: line-through;">{{ $routeData['from_route'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Corrected To:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #10b981; color: white; padding: 4px 12px; border-radius: 4px; font-family: 'Courier New', monospace;">{{ $routeData['to_route'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Customer:</strong></td>
                <td style="padding: 12px 0;">{{ $routeData['customer'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Time:</strong></td>
                <td style="padding: 12px 0;">{{ $routeData['timestamp'] ?? now()->format('Y-m-d H:i:s') }}</td>
            </tr>
        </table>
    </div>

    <!-- Info -->
    <div class="alert alert-info">
        <strong>Note:</strong> The message was processed successfully after the route correction. You may want to update your API integration to use the correct route parameter.
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated notification from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        API Route Monitoring System
    </p>
@endsection
