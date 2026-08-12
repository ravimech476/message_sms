@extends('emails.layouts.master')

@section('title', 'Migrated Customers Daily Report')
@section('header_title', 'Migrated Customers — Daily Report')

@section('content')
    <p style="margin-bottom: 20px;">Hi Admin,</p>

    <p>Here is the customer migration summary as of <strong>{{ $stats['date'] }}</strong>.</p>

    <div class="divider"></div>

    <div class="info-card">
        <div class="info-card-header">Migration Summary</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 8px 0;"><strong>Migrated yesterday</strong></td>
                <td style="padding: 8px 0; text-align: right; color:#198754; font-weight:600;">{{ number_format($stats['yesterday_migrated']) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Total migrated</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ number_format($stats['total_migrated']) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Pending migration</strong></td>
                <td style="padding: 8px 0; text-align: right; color:#ea6118; font-weight:600;">{{ number_format($stats['pending_migrate']) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Total customers</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ number_format($stats['total_customers']) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Migrated</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ $stats['migrated_percent'] }}%</td>
            </tr>
        </table>
    </div>

    <p style="margin-top: 25px;">
        Best regards,<br>
        <strong style="color: #ea6118;">SMS Expert System</strong>
    </p>
@endsection
