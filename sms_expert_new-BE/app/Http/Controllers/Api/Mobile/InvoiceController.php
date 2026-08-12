<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Get all invoices for the user
     * GET /api/mobile/invoices
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $bigid = $user->bigid;

            // Get pagination parameters
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            // Get invoices (status = 'invoice' for paid, 'order' for pro forma)
            $invoices = OrderItem::where('userref', $bigid)
                ->whereIn('status', ['invoice', 'order'])
                ->orderBy('id', 'desc')
                ->get();

            // Get credit notes
            $creditNotes = DB::table('credit_notes')
                ->where('users_bigid', $bigid)
                ->orderBy('id', 'ASC')
                ->get();

            // Calculate totals
            $totalInvoices = $invoices->count();
            $totalAmount = $invoices->sum('fullprice');
            $totalCreditNotes = $creditNotes->count();

            // Format invoices
            $formattedInvoices = $invoices->map(function ($invoice) {
                $createdDate = is_numeric($invoice->createddate) 
                    ? Carbon::createFromTimestamp($invoice->createddate) 
                    : Carbon::parse($invoice->createddate);
                
                return [
                    'id' => $invoice->id,
                    'invoice_ref' => $invoice->invoiceref,
                    'display_ref' => str_pad($invoice->invoiceref, 6, '0', STR_PAD_LEFT),
                    'date' => $createdDate->format('Y-m-d'),
                    'date_formatted' => $createdDate->format('j M Y'),
                    'amount' => round($invoice->fullprice, 2),
                    'amount_no_vat' => round($invoice->nonvatprice, 2),
                    'vat_rate' => $invoice->vatrate ?? 20,
                    'status' => $invoice->status,
                    'status_label' => $invoice->status === 'invoice' ? 'Paid' : 'Pro Forma',
                    'is_paid' => $invoice->status === 'invoice',
                    'summary' => 'Pre-purchase of SMS Expert Credits',
                    'currency' => 'GBP',
                    'currency_symbol' => '£',
                ];
            });

            // Format credit notes
            $formattedCreditNotes = $creditNotes->map(function ($note) {
                $dateInserted = Carbon::parse($note->date_inserted);
                
                return [
                    'id' => $note->id,
                    'amount' => round($note->price_inc_vat ?? 0, 2),
                    'invoice_id' => $note->invoice_id,
                    'date' => $dateInserted->format('Y-m-d'),
                    'date_formatted' => $dateInserted->format('jS F Y'),
                    'reason' => $note->reason ?? 'No reason provided',
                    'currency' => 'GBP',
                    'currency_symbol' => '£',
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Invoices retrieved successfully',
                'data' => [
                    'summary' => [
                        'total_invoices' => $totalInvoices,
                        'total_amount' => round($totalAmount, 2),
                        'total_credit_notes' => $totalCreditNotes,
                        'currency' => 'GBP',
                        'currency_symbol' => '£',
                    ],
                    'invoices' => $formattedInvoices,
                    'credit_notes' => $formattedCreditNotes,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching invoices: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single invoice details
     * GET /api/mobile/invoices/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $bigid = $user->bigid;

            // Get invoice with order items
            $invoice = Invoice::with('orderItems')->find($id);

            if (!$invoice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            // Verify invoice belongs to user
            if ($invoice->userref != $bigid) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized to view this invoice'
                ], 403);
            }

            // Get user details
            $userData = User::where('bigid', $bigid)->first();

            // Format dates
            $invoiceDate = Carbon::createFromTimestamp($invoice->invoicedate, 'Europe/London');
            $paidDate = $invoice->paiddate > 0 
                ? Carbon::createFromTimestamp($invoice->paiddate, 'Europe/London') 
                : null;

            // Determine payment method — present the raw datacashreply token nicely
            // ('bacs' → BACS (OLD parity), 'paypal' → PayPal, 'free' → Free, else card gateway = Card).
            $paymentMethod = null;
            if ($invoice->datacashreply) {
                $dcr = strtolower(trim($invoice->datacashreply));
                if (strpos($dcr, 'bacs') === 0)      { $paymentMethod = 'BACS'; }
                elseif ($dcr === 'paypal')           { $paymentMethod = 'PayPal'; }
                elseif ($dcr === 'free')             { $paymentMethod = 'Free'; }
                else                                 { $paymentMethod = 'Card'; }
            } elseif ($invoice->paiddate > 0) {
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

            // Calculate VAT
            $vatAmount = $invoice->easilyamount - $invoice->easilyamountnovat;

            return response()->json([
                'status' => true,
                'message' => 'Invoice details retrieved successfully',
                'data' => [
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoice_ref' => str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
                        'date' => $invoiceDate->format('Y-m-d'),
                        'date_formatted' => $invoiceDate->format('d/m/Y'),
                        'is_paid' => $invoice->paiddate > 0,
                        'paid_date' => $paidDate ? $paidDate->format('Y-m-d H:i:s') : null,
                        'paid_date_formatted' => $paidDate ? $paidDate->format('d/m/Y H:i') : null,
                        'payment_method' => $paymentMethod,
                        'subtotal' => round($invoice->easilyamountnovat, 2),
                        'vat_rate' => 20,
                        'vat_amount' => round($vatAmount, 2),
                        'total' => round($invoice->easilyamount, 2),
                        'currency' => 'GBP',
                        'currency_symbol' => '£',
                    ],
                    'customer' => [
                        'id' => $userData->bigid,
                        'name' => $userData->contactname,
                        'company' => $userData->busname,
                        'email' => $userData->contactemail,
                        'phone' => $userData->phone,
                        'address' => [
                            'line1' => $userData->address1,
                            'line2' => $userData->address2,
                            'town' => $userData->town,
                            'postcode' => $userData->pcode,
                            'country' => $userData->country,
                        ],
                    ],
                    'items' => [
                        [
                            'description' => 'SMS Credits',
                            'subtitle' => 'Bulk SMS Package',
                            'quantity' => $invoice->orderItems ? $invoice->orderItems->quantity : $invoice->easilyamountnovat,
                            'unit_price' => round($invoice->easilyamountnovat, 2),
                            'vat_rate' => 20,
                            'total' => round($invoice->easilyamount, 2),
                        ]
                    ],
                    'company_details' => [
                        'name' => 'SMS Expert',
                        'address' => 'Oak Business Centre, 79-93 Ratcliffe Road, Sileby, Leicestershire, LE12 7PU United Kingdom',
                        'email' => 'care@smsexpert.co.uk',
                        'website' => 'www.smsexpert.co.uk',
                        'vat_number' => 'GB 332 4975 92',
                        'company_number' => '12106151',
                    ],
                    'payment_instructions' => $invoice->paiddate == 0 ? [
                        'bank_name' => 'Please contact care@smsexpert.co.uk for payment details',
                        'reference' => 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT),
                    ] : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching invoice details: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch invoice details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoices by type (paid or pro forma)
     * GET /api/mobile/invoices/type/{type}
     */
    public function byType(Request $request, $type)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $bigid = $user->bigid;

            // Determine status based on type
            $status = $type === 'paid' ? 'invoice' : 'order';

            // Get invoices by type
            $invoices = OrderItem::where('userref', $bigid)
                ->where('status', $status)
                ->orderBy('id', 'desc')
                ->get();

            // Format invoices
            $formattedInvoices = $invoices->map(function ($invoice) {
                $createdDate = is_numeric($invoice->createddate) 
                    ? Carbon::createFromTimestamp($invoice->createddate) 
                    : Carbon::parse($invoice->createddate);
                
                return [
                    'id' => $invoice->id,
                    'invoice_ref' => $invoice->invoiceref,
                    'display_ref' => str_pad($invoice->invoiceref, 6, '0', STR_PAD_LEFT),
                    'date' => $createdDate->format('Y-m-d'),
                    'date_formatted' => $createdDate->format('j M Y'),
                    'amount' => round($invoice->fullprice, 2),
                    'amount_no_vat' => round($invoice->nonvatprice, 2),
                    'status' => $invoice->status,
                    'status_label' => $invoice->status === 'invoice' ? 'Paid' : 'Pro Forma',
                    'is_paid' => $invoice->status === 'invoice',
                    'summary' => 'Pre-purchase of SMS Expert Credits',
                    'currency' => 'GBP',
                    'currency_symbol' => '£',
                ];
            });

            return response()->json([
                'status' => true,
                'message' => ucfirst($type) . ' invoices retrieved successfully',
                'data' => [
                    'type' => $type,
                    'count' => $invoices->count(),
                    'total_amount' => round($invoices->sum('fullprice'), 2),
                    'invoices' => $formattedInvoices,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching invoices by type: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
