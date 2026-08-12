<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\InvoiceCopyMail;
use App\Services\Queue\EmailQueueService;
use Exception;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            // $invoice = OrderItem::where('userref', $bigid)->whereIn('status', ['order', 'invoice'])->orderBy('id', 'desc')->get();
            $invoice = OrderItem::where('userref', $bigid)->where('status','invoice')->orderBy('id', 'desc')->get();
            $creditNotes = DB::table('credit_notes')
                ->where('users_bigid', $bigid)
                ->orderBy('id', 'ASC')
                ->get();

            $invoiceOrder = OrderItem::where('userref', $bigid)
                ->where('status', 'invoice')
                ->orderBy('id', 'desc')
                ->get();

            $invoiceProforma = OrderItem::where('userref', $bigid)
                ->where('status', 'order')
                ->orderBy('id', 'desc')
                ->get();



            return view('customer.invoice.index', compact('invoice', 'creditNotes', 'invoiceOrder', 'invoiceProforma'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function show($id)
    {
        $ref = session('user_info')['bigid'] ?? null;
        $invoice = Invoice::with('orderItems')->find($id);
        $user = User::where('bigid', $ref)->first();

        // OLD SYSTEM parity: summary, PayPal "Buy Now" button + exact card/PayPal gating.
        $pay = self::paymentDetails($id);
        $summary           = $pay['summary'];
        $cardPaypalAllowed = $pay['cardPaypalAllowed'];
        $paypalRef         = $pay['paypalRef'];
        $buyNowUrl         = $pay['buyNowUrl'];

        return view('customer.invoice.show', compact('invoice', 'user', 'cardPaypalAllowed', 'paypalRef', 'summary', 'buyNowUrl'));
    }

    /**
     * OLD SYSTEM parity (generateInvoice.inc) — resolve the per-invoice summary,
     * the PayPal "Buy Now" hosted-button URL, and whether the Card/PayPal option
     * is allowed at all. Shared by the invoice page AND the invoice emails so they
     * always agree. Keyed off the invoice id, so it works whether the caller has a
     * model or a serialized array.
     *
     * Gating (exactly as generateInvoice.inc:104-140):
     *   - SMS-credits product (20551) + a negotiated BACS-only rate
     *     (smsg_userroute.priority < 10) → Card/PayPal hidden.
     *   - invoice amount (ex-VAT) > useroption.maxcardpurchase → Card/PayPal hidden.
     * The Buy Now URL appends &amount only for the amount-passing hosted buttons.
     */
    public static function paymentDetails($invoiceId): array
    {
        $default = [
            'summary'           => 'Pre-purchase of SMS Expert Credits',
            'paypalRef'         => null,
            'cardPaypalAllowed' => false,
            'buyNowUrl'         => null,
            'amountNoVat'       => 0.0,
        ];

        $invoice = Invoice::with('orderItems')->find($invoiceId);
        if (!$invoice) {
            return $default;
        }

        $userref    = $invoice->userref;
        $productref = optional($invoice->orderItems)->productref;
        $summary    = Product::summaryFor($productref);
        $paypalRef  = $invoice->paypalref ?: null;
        $amountNoVat = (float) ($invoice->easilyamountnovat ?? 0);

        $cardPaypalAllowed = true;

        // SMS-credits product (20551): hide Card/PayPal if on a BACS-only negotiated rate.
        if ((string) $productref === '20551') {
            $route = DB::table('smsg_userroute')
                ->where('userref', $userref)
                ->where('countrydialcode', '44')
                ->where('routenum', 7)
                ->where('numbits', 7)
                ->where('origtype', 'alpha')
                ->orderBy('priority', 'asc')
                ->first();
            if ($route && $route->priority < 10) {
                $cardPaypalAllowed = false;
            }
        }

        // Amount above the customer's max card/PayPal limit → hide Card/PayPal.
        // A limit of 0 (or no useroption row) means "no limit set" — NOT "limit of
        // zero" — so card/PayPal stays allowed (matches the new system's original
        // intent; without this, the ~100 customers with maxcardpurchase=0 could
        // never pay by card). The limit only bites once it's a positive value.
        $maxCardPurchase = (float) (DB::table('useroption')->where('userref', $userref)->value('maxcardpurchase') ?? 0);
        if ($maxCardPurchase > 0 && $amountNoVat > $maxCardPurchase) {
            $cardPaypalAllowed = false;
        }

        // Amount-passing hosted buttons (OLD SYSTEM generateInvoice.inc:91).
        $amountPassing = ['LGWNK6R85WUQQ', 'E2U29H7WEB8TL', 'F5PT3PE5GBMW6', 'YE75YFZ82KM3U', '2E2NADWXJPH48', 'GPESKS2L9HTPA'];

        $buyNowUrl = null;
        if ($cardPaypalAllowed && $paypalRef) {
            $amountParam = in_array($paypalRef, $amountPassing, true)
                ? '&amount=' . number_format($amountNoVat, 2, '.', '')
                : '';
            $buyNowUrl = 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick'
                . '&hosted_button_id=' . $paypalRef
                . '&invoice=' . $invoice->id
                . '&custom=INV' . $invoice->id
                . $amountParam;
        }

        return [
            'summary'           => $summary,
            'paypalRef'         => $paypalRef,
            'cardPaypalAllowed' => $cardPaypalAllowed,
            'buyNowUrl'         => $buyNowUrl,
            'amountNoVat'       => $amountNoVat,
        ];
    }

    /**
     * Download invoice for customer
     */
    public function download($id)
    {
        try {
            $ref = session('user_info')['bigid'] ?? null;

            if (!$ref) {
                return redirect()->back()->with('error', 'You need to log in to download invoices.');
            }

            // Get the invoice with related data
            $invoice = Invoice::with('orderItems')->findOrFail($id);

            // Verify that this invoice belongs to the logged-in user
            if ($invoice->userref != $ref) {
                return redirect()->back()->with('error', 'You are not authorized to download this invoice.');
            }

            // Get user details
            $user = User::where('bigid', $ref)->first();

            if (!$user) {
                return redirect()->back()->with('error', 'User not found.');
            }

            // Generate invoice HTML
            $html = self::generateInvoiceHtml($invoice, $user);

            // Render a real PDF with DomPDF and stream it inline (clean print + save).
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isRemoteEnabled'      => true,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont'          => 'sans-serif',
                ]);

            return $pdf->stream('invoice_' . $id . '_' . date('Ymd') . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Failed to download invoice: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download invoice.');
        }
    }

    /**
     * Generate HTML for customer invoice
     */
    public static function generateInvoiceHtml($invoice, $user)
    {
        $invoiceDate = Carbon::createFromTimestamp($invoice->invoicedate, 'Europe/London')->format('d/m/Y');
        $paidDate = $invoice->paiddate > 0 ? Carbon::createFromTimestamp($invoice->paiddate, 'Europe/London')->format('d/m/Y H:i') : 'Unpaid';
        $paymentMethod = '';

        // Line-item description = product.descriptionlong for this invoice's productref
        // (OLD SYSTEM parity). Resolved from the invoice id so it works whether $invoice
        // is a model or a serialized array (queued email payload).
        $invoiceIdForSummary = is_array($invoice) ? ($invoice['id'] ?? null) : ($invoice->id ?? null);
        $summary = Product::summaryFor(
            DB::table('orderitem')->where('invoiceref', $invoiceIdForSummary)->value('productref')
        );

        // Determine payment method — present the raw datacashreply token nicely
        // ('bacs' → BACS (OLD parity), 'paypal' → PayPal, 'free' → Free, else card gateway = Card).
        if ($invoice->datacashreply) {
            $dcr = strtolower(trim($invoice->datacashreply));
            if (strpos($dcr, 'bacs') === 0)      { $paymentMethod = 'BACS'; }
            elseif ($dcr === 'paypal')           { $paymentMethod = 'PayPal'; }
            elseif ($dcr === 'free')             { $paymentMethod = 'Free'; }
            else                                 { $paymentMethod = 'Card'; }
        } elseif ($invoice->paiddate > 0) {
            // Check notes for payment method
            $paymentNote = DB::table('users_notes')
                ->where('users_bigid', $invoice->userref)
                ->where('notes', 'LIKE', '%Invoice #' . $invoice->id . ' paid via%')
                ->orderBy('insertdate', 'desc')
                ->first();

            if ($paymentNote && $paymentNote->notes) {
                if (strpos($paymentNote->notes, 'PayPal') !== false) {
                    $paymentMethod = 'PayPal';
                } elseif (strpos($paymentNote->notes, 'BACS') !== false) {
                    $paymentMethod = 'BACS';
                }
            }
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 12mm 0; }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.35;
        }
        .no-print {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        .print-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .download-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button:hover { background: #218838; }
        .download-button:hover { background: #0056b3; }
        .invoice-container {
            width: 700px;
            max-width: 700px;
            margin: 0 auto;
            padding: 0;
            background: white;
        }
        .header {
            margin-bottom: 16px;
            border-bottom: 3px solid #293b50;
            padding-bottom: 10px;
        }
        .company-header {
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #293b50;
            margin-bottom: 5px;
        }
        .company-details {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }
        .invoice-title-section {
            display: table;
            width: 100%;
            margin-top: 10px;
        }
        .invoice-title {
            display: table-cell;
            vertical-align: middle;
            font-size: 36px;
            font-weight: 300;
            color: #293b50;
            letter-spacing: 2px;
        }
        .invoice-number-badge {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }
        .invoice-number {
            font-size: 20px;
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }
        .status-paid {
            background: #28a745;
            color: white;
        }
        .status-unpaid {
            background: #dc3545;
            color: white;
        }
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 18px;
        }
        .bill-to {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .invoice-details {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }
        .section-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .info-line {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }
        .info-line strong {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th {
            background: #f8f8f8;
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }
        td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            font-size: 14px;
            color: #666;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .item-description {
            font-weight: 600;
            color: #333;
        }
        .item-subtitle {
            font-size: 12px;
            color: #999;
            font-weight: normal;
        }
        .totals {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            display: block;
            text-align: right;
            margin-bottom: 8px;
        }
        .total-label {
            display: inline-block;
            width: 150px;
            text-align: right;
            padding-right: 20px;
            color: #666;
            font-size: 14px;
        }
        .total-value {
            display: inline-block;
            width: 120px;
            text-align: right;
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }
        .grand-total {
            border-top: 2px solid #293b50;
            padding-top: 10px;
            margin-top: 10px;
        }
        .grand-total .total-label {
            font-weight: bold;
            color: #293b50;
            font-size: 16px;
        }
        .grand-total .total-value {
            color: #293b50;
            font-weight: bold;
            font-size: 18px;
        }
        .payment-info {
            background: #f0f9ff;
            border: 1px solid #0891b2;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .payment-info h3 {
            color: #0891b2;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }
        .payment-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .payment-info strong {
            color: #333;
        }
        .footer {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 12px;
            line-height: 1.8;
        }
        .footer strong {
            color: #666;
            font-size: 14px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            .invoice-container { 
                padding: 20px; 
                max-width: 100%;
            }
            body { 
                print-color-adjust: exact; 
                -webkit-print-color-adjust: exact; 
            }
            @page {
                margin: 0.5in;
                size: A4;
            }
            .payment-info {
                page-break-inside: avoid;
            }
            table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-header">
                <img src="' . (is_file(public_path('assets/images/auth/smsexpertlogowhiteback.jpg')) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents(public_path('assets/images/auth/smsexpertlogowhiteback.jpg'))) : asset('assets/images/auth/smsexpertlogowhiteback.jpg')) . '" alt="SMS Expert" width="260" style="width: 260px; height: auto; display: block; margin-top: 22px; margin-bottom: 8px;">
                <div class="company-details">
                    Oak Business Centre, 79-93 Ratcliffe Road, Sileby, Leicestershire, LE12 7PU United Kingdom<br>
                    Email: care@smsexpert.co.uk | Web: www.smsexpert.co.uk
                </div>
            </div>
            <div class="invoice-title-section">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number-badge">
                    <div class="invoice-number">#' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) . '</div>
                    <div class="status-badge ' . ($invoice->paiddate > 0 ? 'status-paid' : 'status-unpaid') . '">
                        ' . ($invoice->paiddate > 0 ? 'PAID' : 'UNPAID') . '
                    </div>
                </div>
            </div>
        </div>
        
        <div class="invoice-info">
            <div class="bill-to">
                <div class="section-title">Bill To</div>
                <div class="info-line"><strong>' . htmlspecialchars(urldecode($user->busname ?? $user->contactname ?? 'Customer')) . '</strong></div>
                <div class="info-line">' . htmlspecialchars(urldecode($user->contactname ?? '')) . '</div>
                ' . ($user->address1 ? '<div class="info-line">' . htmlspecialchars($user->address1) . '</div>' : '') . '
                ' . ($user->address2 ? '<div class="info-line">' . htmlspecialchars($user->address2) . '</div>' : '') . '
                ' . ($user->town ? '<div class="info-line">' . htmlspecialchars($user->town) . ', ' . htmlspecialchars($user->pcode ?? '') . '</div>' : '') . '
                ' . ($user->country ? '<div class="info-line">' . htmlspecialchars($user->country) . '</div>' : '') . '
                <div class="info-line" style="margin-top: 10px;">
                    <strong>Email:</strong> ' . htmlspecialchars($user->contactemail ?? '') . '<br>
                    <strong>Phone:</strong> ' . htmlspecialchars($user->phone ?? '') . '
                </div>
            </div>
            <div class="invoice-details">
                <div class="section-title">Invoice Details</div>
                <div class="info-line"><strong>Invoice Date:</strong> ' . $invoiceDate . '</div>
                ' . ($invoice->paiddate > 0 ? '<div class="info-line"><strong>Payment Date:</strong> ' . $paidDate . '</div>' : '') . '
                ' . ($paymentMethod ? '<div class="info-line"><strong>Payment Method:</strong> ' . $paymentMethod . '</div>' : '') . '
                <div class="info-line"><strong>Customer ID:</strong> ' . htmlspecialchars($user->bigid ?? '') . '</div>
                <div class="info-line"><strong>VAT Number:</strong> GB 332 4975 92</div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                   
                    <th style="width: 15%;" class="text-right">Unit Price</th>
                    <th style="width: 10%;" class="text-center">VAT Rate</th>
                    <th style="width: 15%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>';

        // foreach ($invoice->orderItems as $item) {
        $html .= '
                <tr>
                    <td>
                        <div class="item-description">' . htmlspecialchars($summary) . '</div>
                    </td>
                    <td class="text-center">' . number_format($invoice->orderItems->nonvatprice) . '</td>
                
                    <td class="text-center">' . ($invoice->orderItems->vatrate ?? 20) . '%</td>
                    <td class="text-right">£' . number_format($invoice->easilyamount, 2) . '</td>
                </tr>';
        // }

        $vatAmount = $invoice->easilyamount - $invoice->easilyamountnovat;

        $html .= '
            </tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">£' . number_format($invoice->easilyamountnovat, 2) . '</div>
            </div>
            <div class="total-row">
                <div class="total-label">VAT (20%):</div>
                <div class="total-value">£' . number_format($vatAmount, 2) . '</div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">Total Amount:</div>
                <div class="total-value">£' . number_format($invoice->easilyamount, 2) . '</div>
            </div>
        </div>';

        if ($invoice->paiddate > 0) {
            $html .= '
        <div class="payment-info">
            <h3>Payment Information</h3>
            <p>This invoice has been paid in full.</p>
            <p><strong>Payment Date:</strong> ' . $paidDate . '</p>
            <p><strong>Payment Method:</strong> ' . ($paymentMethod ?: 'Electronic Transfer') . '</p>
            <p><strong>Transaction Reference:</strong> INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) . '-' . date('Ymd', $invoice->paiddate) . '</p>
        </div>';
        }

        $html .= '
        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <p>For any queries regarding this invoice, please contact care@smsexpert.co.uk</p>
            <p style="margin-top: 20px;">
                SMS Expert Limited | UK Reg. 12106151 | VAT Reg. GB332497592<br>
                Terms: Payment due within 30 days | Late payments subject to interest charges
            </p>
            <p style="margin-top: 10px;">&copy; ' . date('Y') . ' SMS Expert. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    public function sendInvoice(Request $request)
    {
        $ref = session('user_info')['bigid'] ?? null;
        $userref =  $ref;


        #### Confirmation Mail ###
        $user = User::where('bigid', $userref)->first();
        if (!empty($user->contactemail)) {
            $toAddress = $user->contactemail;
        } else {
            $toAddress = 'nomail@example.com';
        }

        //$ccAddress = 'support@devsmsexpert.com';
        $ccAddress = '';

        $invoiceId = $request->invoice_id;

        $user = User::where('bigid', $ref)->first();

        $invoice = Invoice::with('orderItems')->find($invoiceId);
        try {
            // Invoice Copy Mail via RabbitMQ queue
            $emailQueueService = new EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\InvoiceCopyMail',
                $toAddress,
                [
                    'recipient' => $toAddress,
                    'cc_address' => $ccAddress,
                    'invoice_id' => $invoiceId,
                    'user' => $user ? $user->toArray() : null,
                    'invoice' => $invoice ? array_merge($invoice->toArray(), [
                        'order_items' => $invoice->orderItems ? $invoice->orderItems->toArray() : null
                    ]) : null,
                ],
                $ccAddress ? [$ccAddress] : []
            );
            // Redirect to invoice page
            return redirect()->route('invoice.show', $invoiceId)->with('success', 'We have just emailed a copy of this invoice to you');
        } catch (Exception $e) {
            Log::error('Email sending failed: ' . $e->getMessage());
            return redirect()->route('invoice.show', $invoiceId)->with('error', 'Failed to send email');
        }
    }
}
