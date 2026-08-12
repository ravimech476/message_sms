@extends('emails.layouts.master')

@section('title', 'SMS Expert Invoice')
@section('header_title', 'Invoice')

@section('content')
    <p style="margin-bottom: 20px;">Dear {{ urldecode($user->contactname ?? 'Customer') }},</p>

    <p>Thank you for your purchase. Please find your invoice details below.</p>

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
            Download Invoice PDF
        </a>
    </div>

    <!-- Invoice Details Card -->
    <div class="info-card">
        <div class="info-card-header">Invoice Details</div>

        <table style="width: 100%;">
            <tr>
                <td style="padding: 8px 0;"><strong>Invoice Number:</strong></td>
                <td style="padding: 8px 0; text-align: right;" class="highlight">{{ $invoiceno }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Invoice Date:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ date('D j M Y', $invoice->invoicedate ?? time()) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0;"><strong>Customer:</strong></td>
                <td style="padding: 8px 0; text-align: right;">{{ urldecode($user->busname ?? $user->contactname ?? '') }}</td>
            </tr>
        </table>
    </div>

    <!-- Order Summary -->
    <table class="data-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $summary ?? 'Pre-purchase of SMS Expert Credits' }}</td>
                <td style="text-align: right;">£{{ number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2) }}</td>
            </tr>
            @php
                $price = $invoice->orderItems->invoice_nonvatprice ?? 0;
                $vatAmount = ($price * 20) / 100;
            @endphp
            <tr>
                <td>VAT (20%)</td>
                <td style="text-align: right;">£{{ number_format($vatAmount, 2) }}</td>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <td><strong>Total</strong></td>
                <td style="text-align: right;"><strong class="highlight">£{{ number_format($invoice->orderItems->invoice_fullprice ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Payment Information -->
    <div class="info-card" style="background-color: #fef3c7; border-color: #f59e0b;">
        <h3 style="margin: 0 0 15px 0; color: #92400e; font-size: 16px;">Bank Transfer Payment Details</h3>

        <table style="width: 100%;">
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Pay to:</td>
                <td style="padding: 6px 0; text-align: right;"><strong>SMS Expert Ltd</strong></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Bank:</td>
                <td style="padding: 6px 0; text-align: right;"><strong>Tide Bank</strong></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Sort Code:</td>
                <td style="padding: 6px 0; text-align: right;"><strong>23-69-72</strong></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Account:</td>
                <td style="padding: 6px 0; text-align: right;"><strong>20177535</strong></td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Reference:</td>
                <td style="padding: 6px 0; text-align: right;"><strong class="highlight">{{ $invoiceno }}</strong></td>
            </tr>
        </table>
    </div>

    {{-- Pay Securely by Card or PayPal — OLD SYSTEM "Buy Now" (generateInvoice.inc).
         Only shown when the invoice qualifies (see InvoiceController::paymentDetails gating). --}}
    @if (!empty($buyNowUrl))
        <div class="info-card" style="text-align: center;">
            <h3 style="margin: 0 0 6px 0; font-size: 16px;">Pay Securely by Card or PayPal</h3>
            <p style="margin: 0 0 14px 0; color: #6b7280; font-size: 13px;">(SMS Expert Ltd)</p>
            <a href="{{ $buyNowUrl }}" target="_blank"
               style="display: inline-block; background-color: #ffc439; color: #003087; font-weight: bold;
                      text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 16px;">
                Buy Now &mdash; Pay with PayPal / Card
            </a>
            <p style="margin: 12px 0 0 0; color: #9ca3af; font-size: 12px;">
                PayPal &mdash; the safer, easier way to pay online.
            </p>
        </div>
    @endif

    <div class="alert alert-info">
        <strong>Important:</strong> Payment in full is due upon receipt. SMS Expert is unable to credit your account until cleared funds are received.
    </div>

    <p class="text-muted" style="margin-top: 30px; font-size: 13px;">
        If you have any questions about this invoice, please contact us at
        <a href="mailto:care@smsexpert.co.uk" style="color: #ea6118;">care@smsexpert.co.uk</a>
    </p>
@endsection
