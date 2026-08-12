@extends('emails.layouts.master')

@section('title', 'SMS Expert Order Confirmation')
@section('header_title', 'Order Confirmation')

@section('content')
    <p style="margin-bottom: 20px;">Hi {{ $userContactName ?? 'Customer' }},</p>

    <div class="alert alert-success">
        <strong>Thank you for your order!</strong><br>
        Your order for {{ $orderSummary ?? 'SMS Expert credits' }} has been received successfully.
    </div>

    <p>We have just sent you a separate email containing a copy of the invoice for your records.</p>

    <!-- Order Info Card -->
    <div class="info-card">
        <div class="info-card-header">Order Information</div>
        <table style="width: 100%;">
            <tr>
                <td style="padding: 10px 0;"><strong>Invoice Number:</strong></td>
                <td style="padding: 10px 0; text-align: right;" class="highlight">{{ $invoiceno ?? '' }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0;"><strong>Order Date:</strong></td>
                <td style="padding: 10px 0; text-align: right;">{{ date('D j M Y') }}</td>
            </tr>
        </table>
    </div>

    @php
        // Platform-dynamic, absolute base URL: prefer the scheme'd CUSTOMER_DOMAIN,
        // fall back to CUSTOMER_DOMAINS / APP_URL. Ensure a scheme + no trailing slash.
        $invoiceBase = trim((string) (config('domains.customer_domains')[0] ?? (config('domains.customer')[0] ?? config('app.url'))));
        if ($invoiceBase !== '' && !preg_match('#^https?://#i', $invoiceBase)) { $invoiceBase = 'https://' . $invoiceBase; }
        $invoiceBase = rtrim($invoiceBase, '/');
    @endphp

    <!-- Download Button -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $invoiceBase . route('admin.invoice.download', $invoiceno, false) }}"
           class="btn btn-primary">
            Download Invoice
        </a>
    </div>

    <p class="text-muted" style="font-size: 13px;">
        You can also view, print, or re-email a copy of the invoice at any time by signing in to your account on the
        <a href="{{ $invoiceBase ?: 'https://sms.expert' }}" style="color: #ea6118;">SMS Expert Dashboard</a>
        and going to the <em>Invoices</em> page.
    </p>

    <div class="divider"></div>

    <!-- How to Pay Section -->
    <h3 style="color: #293B50; margin-bottom: 15px;">How to Pay</h3>
    <p>Please pay this invoice by bank transfer. You will find our bank details on the invoice.</p>

    <!-- Processing Section -->
    <h3 style="color: #293B50; margin: 25px 0 15px 0;">Processing Your Payment</h3>
    @if (($isCredits ?? true))
        <p>We look forward to your payment and will top up your SMS wallet as soon as the payment is received and verified. During office hours this can be within minutes, but please bear with us in case we are busy. We will email you again as soon as this is done.</p>
    @else
        <p>We look forward to your payment and will process your order as soon as the payment is received and verified. During office hours this can be within minutes, but please bear with us in case we are busy. We will email you again as soon as this is done.</p>
    @endif

    <!-- Help Section -->
    <h3 style="color: #293B50; margin: 25px 0 15px 0;">Always Here to Help</h3>
    <p>Please call or email us for any assistance. Again, many thanks for choosing SMS Expert for your SMS services.</p>

    <div class="divider"></div>

    <p style="text-align: center; color: #ea6118; font-weight: 600; margin-top: 20px;">
        Achieve amazing things for your business with SMS
    </p>
@endsection
