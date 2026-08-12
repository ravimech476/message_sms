@extends('emails.layouts.master')

@section('title', 'SMS Expert Invoice')
@section('header_title', 'Invoice')

@section('content')
    <!-- Payment Information Banner -->
    <div class="info-card" style="background-color: #fef3c7; border-color: #f59e0b;">
        <h3 style="margin: 0 0 15px 0; color: #92400e; font-size: 16px; text-align: center;">Pay Securely by Bank Transfer</h3>
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
                <td style="padding: 6px 0; color: #6b7280;">Payment Ref:</td>
                <td style="padding: 6px 0; text-align: right;"><strong class="highlight">{{ $invoiceno ?? '' }}</strong></td>
            </tr>
        </table>
    </div>

    <h2 style="text-align: center; color: #293B50; margin: 25px 0 15px 0;">Invoice</h2>

    <div class="alert alert-warning" style="text-align: center;">
        Payment in full is due upon receipt.<br>
        SMS Expert is unable to credit the account until cleared funds are received.
    </div>

    <!-- Company and Customer Details -->
    <div style="display: flex; flex-wrap: wrap; margin: 20px 0;">
        <div style="flex: 1; min-width: 250px; padding-right: 15px;">
            <p><strong>SMS Expert Limited</strong></p>
            <p style="margin: 5px 0;">79-93 Ratcliffe Road</p>
            <p style="margin: 5px 0;">Sileby</p>
            <p style="margin: 5px 0;">Leicestershire</p>
            <p style="margin: 5px 0;">LE12 7PU</p>
            <p style="margin: 5px 0;">Email: <strong>care@smsexpert.co.uk</strong></p>
            <p style="margin: 5px 0;">Phone: <strong>01509 606305</strong></p>
            <p style="margin: 5px 0;">VAT Number: <strong>GB332497592</strong></p>
            <p style="margin: 5px 0;">Registered in England, No. <strong>12106151</strong></p>
            <p style="margin: 15px 0 0 0;"><strong>Invoice Number:</strong> <span class="highlight">{{ $invoiceno ?? '' }}</span></p>
        </div>
        <div style="flex: 1; min-width: 250px; text-align: right;">
            <p><strong>Invoiced To</strong></p>
            <p style="margin: 5px 0;">{{ urldecode($user->contactname ?? '') }}</p>
            <p style="margin: 5px 0;">{{ urldecode($user->busname ?? '') }}</p>
            <p style="margin: 5px 0;">{{ $user->address1 ?? '' }}</p>
            <p style="margin: 5px 0;">{{ $user->town ?? '' }}</p>
            <p style="margin: 5px 0;">{{ $user->country ?? '' }}</p>
            <p style="margin: 5px 0;">{{ $user->pcode ?? '' }}</p>
        </div>
    </div>

    <p style="text-align: right;"><strong>{{ date('D j M Y', $invoice->invoicedate ?? time()) }}</strong></p>

    <!-- Invoice Table -->
    @php
        $price = $invoice->orderItems->invoice_nonvatprice ?? 0;
        $vatPercentage = 20;
        $vatAmount = ($price * $vatPercentage) / 100;
    @endphp

    <table class="data-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $summary ?? 'Pre-purchase of SMS Expert Credits' }}</strong></td>
                <td style="text-align: right;">£{{ number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Total</td>
                <td style="text-align: right;">£{{ number_format($invoice->orderItems->invoice_nonvatprice ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>VAT (20.00%)</td>
                <td style="text-align: right;">£{{ number_format($vatAmount, 2) }}</td>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <td><strong>Sub Total</strong></td>
                <td style="text-align: right;"><strong>£{{ number_format($invoice->orderItems->invoice_fullprice ?? 0, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Vouchers Used</td>
                <td style="text-align: right;">£0.00</td>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <td><strong>Grand Total</strong></td>
                <td style="text-align: right;"><strong class="highlight">£{{ number_format($invoice->orderItems->invoice_fullprice ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    {{-- Pay Securely by Card or PayPal — OLD SYSTEM "Buy Now" (generateInvoice.inc).
         Only shown when the invoice qualifies (see InvoiceController::paymentDetails gating). --}}
    @if (!empty($buyNowUrl))
        <div style="text-align: center; margin-top: 24px; padding: 18px; border: 1px solid #e5e7eb; border-radius: 8px;">
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
@endsection
