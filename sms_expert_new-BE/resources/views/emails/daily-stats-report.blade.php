@extends('emails.layouts.master')

@section('title', 'SMS Expert - Daily Stats Report')
@section('header_title', 'Daily Statistics Report')

@section('content')
    <!-- Report Date Badge -->
    <div style="text-align: center; margin-bottom: 20px;">
        <span style="display: inline-block; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 600; background: #293B50; color: white;">
            {{ $reportData['report_date'] ?? 'Daily Report' }}
        </span>
    </div>

    @if(!empty($reportData['is_debug']))
    <div class="alert alert-warning" style="text-align: center;">
        <strong>DEBUG MODE</strong><br>
        <span style="font-size: 13px;">Testing of new features - do not rely on data</span>
    </div>
    @endif

    <!-- Traffic Statistics -->
    <div class="info-card">
        <div class="info-card-header">Traffic Statistics</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 40%;"><strong>Volume Sent:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="font-size: 18px; font-weight: 700; color: #293B50;">{{ number_format($reportData['traffic']['volume'] ?? 0) }}</span>
                    <span style="font-size: 12px; color: #6b7280;"> messages</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Client Cost:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="font-size: 16px; font-weight: 600; color: #10b981;">&pound;{{ number_format($reportData['traffic']['userprice'] ?? 0, 2) }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Our Cost:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="font-size: 16px; font-weight: 600; color: #ef4444;">&pound;{{ number_format($reportData['traffic']['costprice'] ?? 0, 2) }}</span>
                </td>
            </tr>
            <tr style="background-color: #f0fdf4;">
                <td style="padding: 12px 0;"><strong>Profit:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #10b981; color: white; padding: 6px 16px; border-radius: 4px; font-weight: 700; font-size: 18px;">&pound;{{ number_format($reportData['traffic']['profit'] ?? 0, 0) }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Avg SMS Profit:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="font-family: 'Courier New', monospace;">&pound;{{ sprintf("%.4f", $reportData['traffic']['profitpersms'] ?? 0) }}</span>
                    <span style="font-size: 12px; color: #6b7280;"> per message</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Purchases -->
    @if(!empty($reportData['purchases']))
    <div class="info-card">
        <div class="info-card-header">Purchases (Cleared & Processed, excl VAT)</div>
        @if(count($reportData['purchases']) > 0)
        <table style="width: 100%;">
            @foreach($reportData['purchases'] as $purchase)
            <tr>
                <td style="padding: 10px 0; width: 50%;">
                    <strong>{{ $purchase['product'] }}:</strong>
                </td>
                <td style="padding: 10px 0;">
                    <span style="font-weight: 600; color: #10b981;">&pound;{{ number_format($purchase['amount'], 2) }}</span>
                </td>
            </tr>
            @endforeach
        </table>
        @else
        <p style="color: #6b7280; text-align: center; padding: 15px 0;">No purchases recorded</p>
        @endif
    </div>
    @endif

    <!-- Wallet Statistics -->
    <div class="info-card" style="background-color: #fef3c7; border: 1px solid #f59e0b;">
        <div class="info-card-header" style="background: #f59e0b;">Wallet & Client Statistics</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 12px 0; width: 50%;"><strong>Active Clients:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #293B50; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600;">{{ number_format($reportData['wallet']['client_count'] ?? 0) }}</span>
                    <span style="font-size: 12px; color: #6b7280;"> (enabled with +ve wallets)</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0;"><strong>Total Wallet Exposure:</strong></td>
                <td style="padding: 12px 0;">
                    <span style="display: inline-block; background: #ea6118; color: white; padding: 6px 16px; border-radius: 4px; font-weight: 700; font-size: 18px;">&pound;{{ number_format($reportData['wallet']['total'] ?? 0, 0) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Downloadable Reports -->
    @if(!empty($reportData['reports']))
    <div class="info-card">
        <div class="info-card-header">Downloadable Reports</div>
        <table style="width: 100%;">
            @foreach($reportData['reports'] as $report)
            <tr>
                <td style="padding: 10px 0; width: 60%;">
                    <strong>{{ $report['name'] }}:</strong>
                </td>
                <td style="padding: 10px 0;">
                    <a href="{{ $report['url'] }}" style="display: inline-block; background: #ea6118; color: white; padding: 6px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 13px;">
                        Download
                    </a>
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="divider"></div>

    <!-- Footer Notes -->
    <div style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-top: 20px;">
        <p style="font-size: 11px; color: #6b7280; margin: 0 0 8px 0;">
            <sup>1</sup> Traffic includes main internal/test account messages sent at cost
        </p>
        <p style="font-size: 11px; color: #6b7280; margin: 0;">
            <sup>2</sup> Main internal/test accounts excluded, but other newer ones may have been included
        </p>
    </div>

    <p class="text-muted" style="font-size: 12px; text-align: center; margin-top: 20px;">
        This is an automated daily statistics report from <strong>{{ config('app.name', 'SMS Expert') }}</strong><br>
        Generated at {{ $reportData['generated_at'] ?? now()->format('Y-m-d H:i:s T') }}
    </p>
@endsection
