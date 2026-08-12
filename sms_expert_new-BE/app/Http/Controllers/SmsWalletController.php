<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use App\Models\UserOption;
use App\Models\Invoice;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderConfirmationMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use App\Services\Queue\EmailQueueService;
use Exception;
use Carbon\Carbon;



class SmsWalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $user_contactname = Session::get('user_info')['contactname'];
            $bigid = Session::get('user_info')['bigid'];
            // $user = User::with('reminders')->where('bigid', $bigid)->first();
            $user = User::with('reminders', 'options')->where('bigid', $bigid)->first();
            if ($user) {
                $smsg_wallet = $user->smsg_wallet;
                $smsg_server1_sent = $user->smsg_server1_sent;
                $smsg_server2_sent = $user->smsg_server2_sent;

                $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;
            }
            return view('customer.sms_wallet.index', compact('user_contactname', 'bigid', 'user', 'remaining_wallet'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function buysms()
    {
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $user_contactname = Session::get('user_info')['contactname'];
            $max_amount = UserOption::where('userref', $userInfo['bigid'])->first();
        }
        return view('customer.sms_wallet.buysms', compact('user_contactname','max_amount'));
    }

    public function contract()
    {
        return view('customer.sms_wallet.contract');
    }


    public function buysmsInvoice(Request $request)
    {
        $whatvolumeother = $request->input('whatvolumeother');

        $minsmsamount = 100;

        if($whatvolumeother >= $minsmsamount){
            $amountwantedpoundswithvat = round($whatvolumeother * 1.2, 2);


            $ref = session('user_info')['bigid'] ?? null;
    
            $userref =  $ref;
            $signip = $_SERVER['REMOTE_ADDR'];
            $todayunixtime = time();
            // $todayunixtime = Carbon::now('Europe/London')->format('YmdHis');
            $amountinpoundswithvat =  $amountwantedpoundswithvat;
            $amountinpounds = $whatvolumeother ?? 0;
            $paypalref = 'E2U29H7WEB8TL';
    
            $invoiceId = $this->createInvoice($userref, $signip, $todayunixtime, $amountinpoundswithvat, $amountinpounds, $paypalref);
            session(['invoice_id' => $invoiceId]);
    
    
    
            #### Confirmation Mail ###
            $user = User::where('bigid', $userref)->first();
            if (!empty($user->contactemail)) {
                $toAddress = $user->contactemail;
            } else {
                $toAddress = 'nomail@example.com';
            }
    
            //$ccAddress = 'support@devsmsexpert.com';
            $ccAddress = '';
    
            $invoiceId = session('invoice_id');
    
            $user = User::where('bigid', $ref)->first();
    
            $invoice = Invoice::with('orderItems')->find($invoiceId);
    
            $data = [
                'invoice_id' =>  $invoiceId
            ];
    
            $user_contact_name = $user->contactname;
            try {
                // Check if RabbitMQ is enabled
                $useRabbitMQ = env('RABBITMQ_ENABLED', true);
                
                if ($useRabbitMQ) {
                    try {
                        // Use RabbitMQ for background email sending
                        $emailQueue = new EmailQueueService();
                        
                        // Queue Order Confirmation Email
                        $confirmationQueued = $emailQueue->queueEmail(
                            OrderConfirmationMail::class,
                            $toAddress,
                            [
                                'cc_address' => $ccAddress,
                                'user_contact_name' => $user_contact_name,
                                'invoice_id' => $invoiceId
                            ],
                            !empty($ccAddress) ? [$ccAddress] : [],
                            7 // Priority
                        );
                        
                        // Queue Invoice Email
                        $invoiceQueued = $emailQueue->queueEmail(
                            InvoiceMail::class,
                            $toAddress,
                            [
                                'cc_address' => $ccAddress,
                                'invoice_id' => $invoiceId,
                                'user' => $user->toArray(),
                                'invoice' => $invoice->toArray()
                            ],
                            !empty($ccAddress) ? [$ccAddress] : [],
                            7 // Priority
                        );
                        
                        if ($confirmationQueued && $invoiceQueued) {
                            Log::info('Emails queued successfully via RabbitMQ', [
                                'invoice_id' => $invoiceId,
                                'user_email' => $toAddress
                            ]);
                        } else {
                            throw new \Exception('Failed to queue one or more emails');
                        }
                    } catch (\Exception $queueException) {
                        // Fallback to direct mail sending if RabbitMQ fails
                        Log::warning('RabbitMQ unavailable, sending emails directly', [
                            'error' => $queueException->getMessage()
                        ]);
                        
                        // Send confirmation email directly
                        Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $user_contact_name, $invoiceId));
                        //Invoice Mail
                        Mail::send(new InvoiceMail($toAddress,$ccAddress,$invoiceId,$user, $invoice));
                    }
                } else {
                    // Send emails directly if RabbitMQ is disabled
                    Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $user_contact_name, $invoiceId));
                    //Invoice Mail
                    Mail::send(new InvoiceMail($toAddress,$ccAddress,$invoiceId,$user, $invoice));
                }
                ### Confirmation Mail End ###
            } catch (Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                return redirect()->route('buysms.invoice.view')->with('error', 'Failed to send email');
            }
    
    
            //Redirect to invoice page
            return redirect()->route('buysms.invoice.view')->with('success', 'Confirmation email has been sent');
        }
        else {
            return redirect()->route('buysms')->with('error', 'Your account is currently configured to only allow purchases of '.$minsmsamount.'+vat or more. We are happy to consider smaller amounts so please email care@smsexpert.co.uk to discuss your SMS requirements.');
        }

        
    }

    public function buysmsInvoiceView()
    {
        $invoiceId = session('invoice_id');
        $ref = session('user_info')['bigid'] ?? null;

        $invoice = Invoice::with('orderItems')->find($invoiceId);

        $user = User::where('bigid', $ref)->first();

        $max_amount = UserOption::where('userref', $ref)->first();

        return view('customer.sms_wallet.buysms_invoice', compact('invoice', 'user','max_amount'));
    }

    public function createInvoice($userref, $signip, $todayunixtime, $amountinpoundswithvat, $amountinpounds, $paypalref)
    {
        try {
            $invoiceId = DB::table('invoices')->insertGetId([
                'userref' => $userref,
                'agencyref' => 0,
                'raddr' => $signip,
                'rhost' => '',
                'invoicedate' => $todayunixtime,
                'easilyamount' => $amountinpoundswithvat,
                'easilyamountnovat' => $amountinpounds,
                'paypalref' => $paypalref,
            ]);

            $productref = 20551;
            $vatrate = 20;


            DB::table('orderitem')->insert([
                'invoiceref' => $invoiceId,
                'productref' => $productref,
                'createddate' => $todayunixtime,
                'status' => 'order',
                'fullprice' => $amountinpoundswithvat,
                'vatrate' => $vatrate,
                'nonvatprice' => $amountinpounds,
                'userref' => $userref,
                'quantity' => $amountinpounds,
                'groupitem' => 1,
                'invoice_fullprice' => $amountinpoundswithvat,
                'invoice_nonvatprice' => $amountinpounds,
                'subsequentvatprice' => 0,
            ]);

            DB::table('chequepromises')->insert([
                'userref' => $userref,
                'invoiceref' =>     $invoiceId,
                'amount' =>    $amountinpoundswithvat,
            ]);

            $todayymdhis = date("YmdHis");
            $datenow = Carbon::now('Europe/London')->format('YmdHis');
            $formattedDate = Carbon::now('Europe/London')->format('d/m/Y H:i');
            
            // Create a detailed note for the invoice
            $noteText = "Invoice #{$invoiceId} created on {$formattedDate}\n";
            $noteText .= "Amount: £{$amountinpounds} + VAT (£{$amountinpoundswithvat} total)\n";
            $noteText .= "Status: Awaiting payment\n";
            $noteText .= "Payment method: To be confirmed (PayPal/BACS)";

            DB::table('users_notes')->insert([
                'users_bigid' => $userref,
                'staffinitials' => 'system',
                'nextcontactdate' => date('Ymd', strtotime('+7 days')) . '1200', // Follow up in 7 days
                'notes' => $noteText,
                'insertdate' => now(),
                'timelength' => '10',
                'myinsertdate' => $todayymdhis,
                'settonousrprenfc' => '1',
            ]);

            return $invoiceId;
        } catch (\Exception $e) {
            // Log the error and return null
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
