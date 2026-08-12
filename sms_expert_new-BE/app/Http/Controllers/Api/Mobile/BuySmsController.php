<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\UserOption;
use App\Models\Invoice;
use App\Mail\OrderConfirmationMail;
use App\Mail\InvoiceMail;
use App\Services\Queue\EmailQueueService;
use Carbon\Carbon;

class BuySmsController extends Controller
{
    /**
     * Get Buy SMS page data (max card purchase info)
     * GET /api/mobile/buy-sms
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

            // Get user data
            $userData = User::where('bigid', $bigid)->first();
            
            if (!$userData) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get max card purchase amount
            $userOption = UserOption::where('userref', $bigid)->first();
            $maxCardPurchase = $userOption ? $userOption->maxcardpurchase : 0;

            // Calculate before VAT amount
            $vatRate = 0.2; // 20% VAT
            $beforeVat = $maxCardPurchase > 0 ? round($maxCardPurchase / (1 + $vatRate), 2) : 0;

            // Calculate wallet balance
            $smsg_wallet = $userData->smsg_wallet ?? 0;
            $smsg_server1_sent = $userData->smsg_server1_sent ?? 0;
            $smsg_server2_sent = $userData->smsg_server2_sent ?? 0;
            $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;

            // Determine payment method availability
            $canPayByCard = $maxCardPurchase > 0;
            $paymentMessage = $canPayByCard 
                ? "You can pay up to £{$beforeVat} + VAT by card or PayPal. For larger amounts, please pay by bank transfer."
                : "Your account is configured to only pay by bank transfer. Contact us to enable card/PayPal payments.";

            return response()->json([
                'status' => true,
                'message' => 'Buy SMS data retrieved successfully',
                'data' => [
                    'user' => [
                        'name' => $userData->contactname,
                        'email' => $userData->contactemail,
                    ],
                    'wallet_balance' => round($remaining_wallet, 2),
                    'minimum_purchase_amount' => 100,
                    'vat_rate' => 20,
                    'payment' => [
                        'can_pay_by_card' => $canPayByCard,
                        'max_card_purchase' => $maxCardPurchase,
                        'max_card_purchase_before_vat' => $beforeVat,
                        'payment_message' => $paymentMessage,
                    ],
                    'features' => [
                        'Instant delivery to individual mobiles or groups',
                        'Easy-to-use online dashboard and powerful APIs',
                        'Secure and reliable SMS delivery platform',
                        '24/7 customer support and technical assistance',
                    ],
                    'terms' => [
                        'Add VAT to all prices',
                        'Payment in full is due within 3 working days',
                        'Pre-purchased SMS is non-refundable',
                        'Rates shown are for SMS delivery to UK mobiles only',
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching buy SMS data: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch buy SMS data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create invoice for SMS purchase
     * POST /api/mobile/buy-sms
     */
    public function store(Request $request)
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

            // Validate request
            $request->validate([
                'amount' => 'required|numeric|min:100',
            ]);

            $amount = $request->amount;
            $minAmount = 100;

            if ($amount < $minAmount) {
                return response()->json([
                    'status' => false,
                    'message' => "Minimum purchase amount is £{$minAmount}. Please contact care@smsexpert.co.uk for smaller amounts."
                ], 422);
            }

            // Calculate amount with VAT
            $amountWithVat = round($amount * 1.2, 2);

            // Get user data
            $userData = User::where('bigid', $bigid)->first();
            
            if (!$userData) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Create invoice
            $signip = $request->ip();
            $todayunixtime = time();
            $paypalref = 'E2U29H7WEB8TL';

            $invoiceId = $this->createInvoice($bigid, $signip, $todayunixtime, $amountWithVat, $amount, $paypalref);

            if (!$invoiceId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to create invoice'
                ], 500);
            }

            // Get the created invoice
            $invoice = Invoice::with('orderItems')->find($invoiceId);

            // Send confirmation emails
            $this->sendInvoiceEmails($userData, $invoice, $invoiceId);

            return response()->json([
                'status' => true,
                'message' => 'Invoice created successfully. Confirmation email has been sent.',
                'data' => [
                    'invoice_id' => $invoiceId,
                    'invoice_ref' => str_pad($invoiceId, 6, '0', STR_PAD_LEFT),
                    'amount_without_vat' => $amount,
                    'vat_amount' => round($amountWithVat - $amount, 2),
                    'total_amount' => $amountWithVat,
                    'currency' => 'GBP',
                    'currency_symbol' => '£',
                    'status' => 'pending',
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating invoice: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to create invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create invoice in database
     */
    private function createInvoice($userref, $signip, $todayunixtime, $amountWithVat, $amountWithoutVat, $paypalref)
    {
        try {
            $invoiceId = DB::table('invoices')->insertGetId([
                'userref' => $userref,
                'agencyref' => 0,
                'raddr' => $signip,
                'rhost' => '',
                'invoicedate' => $todayunixtime,
                'easilyamount' => $amountWithVat,
                'easilyamountnovat' => $amountWithoutVat,
                'paypalref' => $paypalref,
            ]);

            $productref = 20551;
            $vatrate = 20;

            DB::table('orderitem')->insert([
                'invoiceref' => $invoiceId,
                'productref' => $productref,
                'createddate' => $todayunixtime,
                'status' => 'order',
                'fullprice' => $amountWithVat,
                'vatrate' => $vatrate,
                'nonvatprice' => $amountWithoutVat,
                'userref' => $userref,
                'quantity' => $amountWithoutVat,
                'groupitem' => 1,
                'invoice_fullprice' => $amountWithVat,
                'invoice_nonvatprice' => $amountWithoutVat,
                'subsequentvatprice' => 0,
            ]);

            DB::table('chequepromises')->insert([
                'userref' => $userref,
                'invoiceref' => $invoiceId,
                'amount' => $amountWithVat,
            ]);

            // Create note
            $todayymdhis = date("YmdHis");
            $formattedDate = Carbon::now('Europe/London')->format('d/m/Y H:i');
            
            $noteText = "Invoice #{$invoiceId} created on {$formattedDate} (via Mobile App)\n";
            $noteText .= "Amount: £{$amountWithoutVat} + VAT (£{$amountWithVat} total)\n";
            $noteText .= "Status: Awaiting payment\n";
            $noteText .= "Payment method: To be confirmed (PayPal/BACS)";

            DB::table('users_notes')->insert([
                'users_bigid' => $userref,
                'staffinitials' => 'mobile',
                'nextcontactdate' => date('Ymd', strtotime('+7 days')) . '1200',
                'notes' => $noteText,
                'insertdate' => now(),
                'timelength' => '10',
                'myinsertdate' => $todayymdhis,
                'settonousrprenfc' => '1',
            ]);

            return $invoiceId;
        } catch (\Exception $e) {
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send invoice confirmation emails
     */
    private function sendInvoiceEmails($user, $invoice, $invoiceId)
    {
        try {
            $toAddress = !empty($user->contactemail) ? $user->contactemail : 'nomail@example.com';
            $ccAddress = '';
            $userContactName = urldecode($user->contactname ?? '');

            // Check if RabbitMQ is enabled
            $useRabbitMQ = env('RABBITMQ_ENABLED', true);
            
            if ($useRabbitMQ) {
                try {
                    $emailQueue = new EmailQueueService();
                    
                    // Queue Order Confirmation Email
                    $emailQueue->queueEmail(
                        OrderConfirmationMail::class,
                        $toAddress,
                        [
                            'cc_address' => $ccAddress,
                            'user_contact_name' => $userContactName,
                            'invoice_id' => $invoiceId
                        ],
                        !empty($ccAddress) ? [$ccAddress] : [],
                        7
                    );
                    
                    // Queue Invoice Email
                    $emailQueue->queueEmail(
                        InvoiceMail::class,
                        $toAddress,
                        [
                            'cc_address' => $ccAddress,
                            'invoice_id' => $invoiceId,
                            'user' => $user->toArray(),
                            'invoice' => $invoice->toArray()
                        ],
                        !empty($ccAddress) ? [$ccAddress] : [],
                        7
                    );
                    
                    Log::info('Invoice emails queued via RabbitMQ', ['invoice_id' => $invoiceId]);
                } catch (\Exception $queueException) {
                    Log::warning('RabbitMQ unavailable, sending emails directly', ['error' => $queueException->getMessage()]);
                    
                    Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $userContactName, $invoiceId));
                    Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
                }
            } else {
                Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $userContactName, $invoiceId));
                Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send invoice emails: ' . $e->getMessage());
        }
    }
}
