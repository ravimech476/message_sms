@extends('emails.layouts.master')

@section('title', 'SMS Expert Daily Low Wallets Report')
@section('header_title', 'Daily Low Wallets Report')

@section('content')
    <p class="text-muted" style="text-align: center; margin-bottom: 20px;">{{ date('l, F j, Y') }}</p>

    <div class="info-card">
        <div class="info-card-header">Summary</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Total users notified:</strong></td>
                <td style="padding: 10px 0; text-align: right;" class="highlight">{{ $data['count'] ?? 0 }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Report generated at:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ date('H:i:s') }}</td>
            </tr>
        </table>
    </div>

    <h3 style="color: #293B50; margin: 25px 0 15px 0;">Users with Low Wallet Balance</h3>

    {!! $data['report_html'] ?? '<p class="text-muted">No users to report.</p>' !!}

    <div class="alert alert-info">
        <strong>Action Items:</strong>
        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
            <li>Review accounts with negative balances immediately</li>
            <li>Contact high-volume users proactively if balance is critically low</li>
            <li>Monitor users who frequently hit low balance thresholds</li>
        </ul>
    </div>

    <div class="divider"></div>

    <p class="text-muted" style="font-size: 12px; text-align: center;">
        This is an automated daily report from SMS Expert Wallet Reminder System<br>
        Generated on {{ date('Y-m-d H:i:s') }}
    </p>
@endsection
