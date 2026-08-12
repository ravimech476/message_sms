<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\User;
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
use App\Models\OrderItem;
use App\Models\UserOption;


class OutstandingInvoiceController extends Controller
{
    public function loginAndRedirect($id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->back()->with('error', 'User not found');
        }

        // Set session
        Session::put('user_info', [
            'bigid' => $user->bigid,
            'contactname' => $user->contactname,
            'email' => $user->email,
        ]);

        return redirect()->route('admin.outstanding.invoices');
    }

    public function outstandingInvoices()
    {
        $userInfo = Session::get('user_info');
        $user_contactname = $userInfo['contactname'] ?? null;
        $max_amount = UserOption::where('userref', $userInfo['bigid'])->first();

        return view('admin.customer.buysms', compact('user_contactname','max_amount'));
    }


    public function VirtualloginAndRedirect($id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->back()->with('error', 'User not found');
        }

        // Set session
        Session::put('user_info', [
            'bigid' => $user->bigid,
            'contactname' => $user->contactname,
            'email' => $user->email,
        ]);

        return redirect()->route('admin.virtual.invoices');
    }

    /**
     * "Reg: New Keyword" button on the admin customer page. Native replacement for the OLD
     * SYSTEM link (platforme.net ...show_info_about=existingusernewkeyword): act as the
     * customer (same session-impersonation the SMS / Other Stuff buttons use) and land on
     * the native keyword-registration page instead of redirecting to the old system.
     */
    public function keywordLoginAndRedirect($id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->back()->with('error', 'User not found');
        }

        Session::put('user_info', [
            'bigid' => $user->bigid,
            'contactname' => $user->contactname,
            'email' => $user->email,
        ]);

        return redirect()->route('admin.keyword.invoices');
    }

    /**
     * Native admin "Register New Keyword" screen (same admin layout as the SMS / Upgrade / NFC /
     * Other Stuff buy pages). Mirrors the OLD SYSTEM existingusernewkeyword page: shows the
     * registration form when the customer has remaining keywords + Platinum access, otherwise the
     * "you don't appear to have any remaining un-registered keywords" message. Remaining =
     * platkeywordwallet / platkeywordcost.
     */
    public function keywordInvoices()
    {
        $userInfo = Session::get('user_info');
        $userref = $userInfo['bigid'] ?? null;
        $user_contactname = $userInfo['contactname'] ?? null;

        $isLoggedIn = !empty($userref);
        $canRegister = false;
        $keywordsLeft = 0;
        $hasPlatinumAccess = false;

        if ($isLoggedIn) {
            $u = DB::table('users')
                ->selectRaw('platinumaccess, (platkeywordwallet / NULLIF(platkeywordcost, 0)) as platkeywordsleft')
                ->where('bigid', $userref)
                ->first();
            if ($u) {
                $keywordsLeft = (float) ($u->platkeywordsleft ?? 0);
                $hasPlatinumAccess = ($u->platinumaccess === 'y');
                $canRegister = $hasPlatinumAccess && $keywordsLeft >= 1;
            }
        }

        return view('admin.customer.keyword_register', compact(
            'user_contactname', 'isLoggedIn', 'canRegister', 'keywordsLeft', 'hasPlatinumAccess'
        ));
    }

    /**
     * Register the keyword from the admin screen. Same validation as the customer flow
     * (remaining > 0, Platinum, availability) and reuses SMSController::createKeywordForExistingUser,
     * which inserts the keyword AND decrements platkeywordwallet (so remaining drops by 1).
     */
    public function storeKeywordRegistration(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:20|regex:/^[A-Za-z0-9]+$/',
        ]);

        $userref = session('user_info')['bigid'] ?? null;
        if (!$userref) {
            return redirect()->route('admin.users')->with('error', 'Session expired — please reopen the keyword page.');
        }

        $user = DB::table('users')
            ->selectRaw('uname, user_type, platinumaccess, (platkeywordwallet / NULLIF(platkeywordcost, 0)) as platkeywordsleft, contactemail')
            ->where('bigid', $userref)
            ->first();

        if (!$user) {
            return redirect()->route('admin.keyword.invoices')->with('error', 'User not found.');
        }
        if ((float) $user->platkeywordsleft < 1) {
            return redirect()->route('admin.keyword.invoices')->with('error', 'No remaining un-registered keywords.');
        }
        if ($user->platinumaccess !== 'y') {
            return redirect()->route('admin.keyword.invoices')->with('error', 'Keyword registration on 60300 requires Platinum access.');
        }

        $keyword = strtolower(trim($request->keyword));
        $shortcode = '60300';

        $shortcodeData = DB::table('smsshortcodes')->where('number', $shortcode)->first();
        if (!$shortcodeData) {
            return redirect()->route('admin.keyword.invoices')->with('error', 'Shortcode not configured.');
        }

        $exists = DB::table('itagg_instance')
            ->where('keyword', $keyword)
            ->where('smsshortcodes_id', $shortcodeData->id)
            ->where('status', 1)
            ->exists();
        if ($exists) {
            return redirect()->route('admin.keyword.invoices')->with('error', 'That keyword is already registered. Please choose a different one.');
        }

        $result = app(\App\Http\Controllers\SMSController::class)->createKeywordForExistingUser(
            $keyword,
            $user->contactemail,
            $userref,
            $user->uname,
            $shortcode,
            $user->user_type
        );

        if (!empty($result['success'])) {
            return redirect()->route('admin.keyword.invoices')
                ->with('success', 'Keyword "' . strtoupper($keyword) . '" registered successfully on ' . $shortcode . '.');
        }

        return redirect()->route('admin.keyword.invoices')
            ->with('error', 'Failed to register keyword. Error code: ' . ($result['errorCode'] ?? 'unknown'));
    }


    public function vitualoutstandingInvoices()
    {
        $userInfo = Session::get('user_info');
        $user_contactname = $userInfo['contactname'] ?? null;
        $max_amount = UserOption::where('userref', $userInfo['bigid'])->first();

        return view('admin.customer.virtual_number_buy', compact('user_contactname', 'max_amount'));
    }

    public function outstandingbuyserviceInvoice(Request $request)
    {
        $whatvolumeother = $request->input('whatvolumeother');

        $minsmsamount = 1;

        if ($whatvolumeother >= $minsmsamount) {
            $amountwantedpoundswithvat = round($whatvolumeother * 1.2, 2);


            $ref = session('user_info')['bigid'] ?? null;

            $userref =  $ref;
            $signip = $_SERVER['REMOTE_ADDR'];
            $todayunixtime = time();
            // $todayunixtime = Carbon::now('Europe/London')->format('YmdHis');
            $amountinpoundswithvat =  $amountwantedpoundswithvat;
            $amountinpounds = $whatvolumeother ?? 0;
            // PayPal hosted button LGWNK6R85WUQQ is the OLD SYSTEM "otherstuff" service
            // button — PayPal displays it as "SMS Expert Services". (E2U29H7WEB8TL is the
            // bulk-SMS/credits button, shown as "Pre-purchase of SMS Expert Credits".)
            $paypalref = 'LGWNK6R85WUQQ';

            // Product 51930 = "SMS Expert Services" (OLD SYSTEM summary parity).
            $invoiceId = $this->createInvoice($userref, $signip, $todayunixtime, $amountinpoundswithvat, $amountinpounds, $paypalref, 51930);
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
                        Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
                    }
                } else {
                    // Send emails directly if RabbitMQ is disabled
                    Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $user_contact_name, $invoiceId));
                    //Invoice Mail
                    Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
                }
                ### Confirmation Mail End ###
            } catch (Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                return redirect()->route('outstanding.service.invoice.view')->with('error', 'Failed to send email');
            }


            //Redirect to invoice page
            return redirect()->route('outstanding.service.invoice.view')->with('success', 'Confirmation email has been sent');
        } else {
            return redirect()->route('admin.virtual.invoices')->with('error', 'Your account is currently configured to only allow purchases of ' . $minsmsamount . '+vat or more. We are happy to consider smaller amounts so please email care@smsexpert.co.uk to discuss your SMS requirements.');
        }
    }

    /**
     * "Upgrade" button on the admin customer page. Native replacement for the OLD SYSTEM link
     * (platforme.net ...show_info_about=buyupgrade): act as the customer (same session
     * impersonation as the SMS / Other Stuff buttons) and land on the native upgrade page.
     */
    public function UpgradeloginAndRedirect($id)
    {
        $user = User::find($id);
        if (!$user) {
            return redirect()->back()->with('error', 'User not found');
        }
        Session::put('user_info', [
            'bigid' => $user->bigid,
            'contactname' => $user->contactname,
            'email' => $user->email,
        ]);
        return redirect()->route('admin.upgrade.invoices');
    }

    /**
     * Native "Buy Upgrade" page. Shows Silver / Gold / Platinum packages at the customer's
     * prices (users.silverprice/goldprice/platinumprice, defaulting to the OLD SYSTEM
     * 99 / 200 / 600 — infopage_include_detail2.inc:6839-6849).
     */
    public function upgradeInvoices()
    {
        $userInfo = Session::get('user_info');
        $userref = $userInfo['bigid'] ?? null;
        $user = $userref ? User::where('bigid', $userref)->first() : null;
        $user_contactname = $userInfo['contactname'] ?? null;

        $packages = [
            'silver'   => ($user && (float) ($user->silverprice ?? 0) > 0)   ? (float) $user->silverprice   : 99,
            'gold'     => ($user && (float) ($user->goldprice ?? 0) > 0)     ? (float) $user->goldprice     : 200,
            'platinum' => ($user && (float) ($user->platinumprice ?? 0) > 0) ? (float) $user->platinumprice : 600,
        ];
        $currentType = $user->user_type ?? '';

        return view('admin.customer.upgrade_buy', compact('user_contactname', 'packages', 'currentType'));
    }

    /**
     * Create + email an invoice for the chosen upgrade package. Mirrors the OLD SYSTEM
     * buyupgrade flow (Silver/Gold/Platinum, each with its own PayPal product ref —
     * infopage_include_detail2.inc:6752-6754) and reuses the same createInvoice + email path
     * as the other buy pages.
     */
    public function outstandingbuyupgradeInvoice(Request $request)
    {
        $package = strtolower(trim($request->input('package', '')));
        $userref = session('user_info')['bigid'] ?? null;

        if (!$userref) {
            return redirect()->route('admin.users')->with('error', 'Session expired — please reopen the upgrade page.');
        }

        $user = User::where('bigid', $userref)->first();

        // OLD SYSTEM prices (defaults) + PayPal product refs.
        // 'productref' drives the invoice summary (OLD SYSTEM product.descriptionlong):
        // 51940/51950/51960 = Silver/Gold/Platinum Upgrade (12 months).
        $catalog = [
            'silver'   => ['amount' => ((float) ($user->silverprice ?? 0)   > 0 ? (float) $user->silverprice   : 99),  'ref' => 'YE75YFZ82KM3U', 'productref' => 51940],
            'gold'     => ['amount' => ((float) ($user->goldprice ?? 0)     > 0 ? (float) $user->goldprice     : 200), 'ref' => '2E2NADWXJPH48', 'productref' => 51950],
            'platinum' => ['amount' => ((float) ($user->platinumprice ?? 0) > 0 ? (float) $user->platinumprice : 600), 'ref' => 'GPESKS2L9HTPA', 'productref' => 51960],
        ];

        if (!isset($catalog[$package])) {
            return redirect()->route('admin.upgrade.invoices')->with('error', 'Please choose a valid upgrade package.');
        }

        $amountExVat   = (float) $catalog[$package]['amount'];
        $paypalref     = $catalog[$package]['ref'];
        $amountWithVat = round($amountExVat * 1.2, 2);
        $signip        = $_SERVER['REMOTE_ADDR'] ?? '';

        $invoiceId = $this->createInvoice($userref, $signip, time(), $amountWithVat, $amountExVat, $paypalref, $catalog[$package]['productref']);
        session(['invoice_id' => $invoiceId]);

        $this->sendPurchaseEmails($userref, $invoiceId);

        return redirect()->route('outstanding.service.invoice.view')
            ->with('success', ucfirst($package) . ' upgrade invoice (£' . number_format($amountExVat, 2) . ' + VAT) created and emailed.');
    }

    /**
     * "NFC Starter Pack" button on the admin customer page. Native replacement for the OLD
     * SYSTEM link (platforme.net ...show_info_about=buysmsin&productpages=nfc): act as the
     * customer and land on the native NFC purchase page.
     */
    public function NfcloginAndRedirect($id)
    {
        $user = User::find($id);
        if (!$user) {
            return redirect()->back()->with('error', 'User not found');
        }
        Session::put('user_info', [
            'bigid' => $user->bigid,
            'contactname' => $user->contactname,
            'email' => $user->email,
        ]);
        return redirect()->route('admin.nfc.invoices');
    }

    /**
     * Native "Buy NFC Starter Pack" page. OLD SYSTEM product: "NFC Starter Pack (15 taggs +
     * 1 Year Management/Reporting)" priced at users.smsinprice (the old "any price" PayPal
     * button F5PT3PE5GBMW6) — so the amount is pre-filled from smsinprice and editable.
     */
    public function nfcInvoices()
    {
        $userInfo = Session::get('user_info');
        $userref = $userInfo['bigid'] ?? null;
        $user = $userref ? User::where('bigid', $userref)->first() : null;
        $user_contactname = $userInfo['contactname'] ?? null;

        $nfcPrice = ($user && (float) ($user->smsinprice ?? 0) > 0) ? (float) $user->smsinprice : 0;

        return view('admin.customer.nfc_buy', compact('user_contactname', 'nfcPrice'));
    }

    /**
     * Create + email an invoice for the NFC Starter Pack. Mirrors the OLD SYSTEM buysmsin/nfc
     * flow (product ref F5PT3PE5GBMW6) and reuses the same createInvoice + email path.
     */
    public function outstandingbuynfcInvoice(Request $request)
    {
        $amountExVat = (float) $request->input('whatvolumeother', 0);
        $userref = session('user_info')['bigid'] ?? null;

        if (!$userref) {
            return redirect()->route('admin.users')->with('error', 'Session expired — please reopen the NFC page.');
        }

        if ($amountExVat < 1) {
            return redirect()->route('admin.nfc.invoices')->with('error', 'Please enter the NFC Starter Pack price (£1 or more).');
        }

        $paypalref     = 'F5PT3PE5GBMW6'; // OLD SYSTEM "nfcstarter" product
        $amountWithVat = round($amountExVat * 1.2, 2);
        $signip        = $_SERVER['REMOTE_ADDR'] ?? '';

        // Product 51800 = "NFC Starter Pack (15 Taggs + 1 Year Management/Reporting)".
        $invoiceId = $this->createInvoice($userref, $signip, time(), $amountWithVat, $amountExVat, $paypalref, 51800);
        session(['invoice_id' => $invoiceId]);

        $this->sendPurchaseEmails($userref, $invoiceId);

        return redirect()->route('outstanding.service.invoice.view')
            ->with('success', 'NFC Starter Pack invoice (£' . number_format($amountExVat, 2) . ' + VAT) created and emailed.');
    }

    /**
     * Shared: queue/send the Order Confirmation + Invoice emails for a created invoice.
     * Extracted so the upgrade flow reuses the exact same mail path as the buy-service flow.
     */
    private function sendPurchaseEmails($userref, $invoiceId): void
    {
        try {
            $user = User::where('bigid', $userref)->first();
            if (!$user) {
                return;
            }

            $toAddress = !empty($user->contactemail) ? $user->contactemail : 'nomail@example.com';
            $ccAddress = '';
            $invoice = Invoice::with('orderItems')->find($invoiceId);
            $userContactName = $user->contactname;

            if (env('RABBITMQ_ENABLED', true)) {
                try {
                    $emailQueue = new EmailQueueService();
                    $emailQueue->queueEmail(OrderConfirmationMail::class, $toAddress, [
                        'cc_address' => $ccAddress, 'user_contact_name' => $userContactName, 'invoice_id' => $invoiceId,
                    ], [], 7);
                    $emailQueue->queueEmail(InvoiceMail::class, $toAddress, [
                        'cc_address' => $ccAddress, 'invoice_id' => $invoiceId, 'user' => $user->toArray(), 'invoice' => $invoice->toArray(),
                    ], [], 7);
                } catch (\Exception $e) {
                    Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $userContactName, $invoiceId));
                    Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
                }
            } else {
                Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $userContactName, $invoiceId));
                Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
            }
        } catch (\Exception $e) {
            Log::error('Upgrade/purchase email sending failed: ' . $e->getMessage());
        }
    }

    public function outstandingSeviceInvoiceView()
    {
        $invoiceId = session('invoice_id');
        $ref = session('user_info')['bigid'] ?? null;

        $invoice = Invoice::with('orderItems')->find($invoiceId);
        $user = User::where('bigid', $ref)->first();
        $max_amount = UserOption::where('userref', $ref)->first();

        return view('admin.customer.buyservice_invoice', compact('invoice', 'user', 'max_amount'));
    }


    public function outstandingbuysmsInvoice(Request $request)
    {
        $whatvolumeother = $request->input('whatvolumeother');

        $minsmsamount = 1;

        if ($whatvolumeother >= $minsmsamount) {
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
                        Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
                    }
                } else {
                    // Send emails directly if RabbitMQ is disabled
                    Mail::send(new OrderConfirmationMail($toAddress, $ccAddress, $user_contact_name, $invoiceId));
                    //Invoice Mail
                    Mail::send(new InvoiceMail($toAddress, $ccAddress, $invoiceId, $user, $invoice));
                }
                ### Confirmation Mail End ###
            } catch (Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                return redirect()->route('outstanding.invoice.view')->with('error', 'Failed to send email');
            }


            //Redirect to invoice page
            return redirect()->route('outstanding.invoice.view')->with('success', 'Confirmation email has been sent');
        } else {
            return redirect()->route('admin.outstanding.invoices')->with('error', 'Your account is currently configured to only allow purchases of ' . $minsmsamount . '+vat or more. We are happy to consider smaller amounts so please email care@smsexpert.co.uk to discuss your SMS requirements.');
        }
    }

    public function outstandingInvoiceView()
    {
        $invoiceId = session('invoice_id');
        $ref = session('user_info')['bigid'] ?? null;

        $invoice = Invoice::with('orderItems')->find($invoiceId);
        $user = User::where('bigid', $ref)->first();
        $max_amount = UserOption::where('userref', $ref)->first();

        return view('admin.customer.buysms_invoice', compact('invoice', 'user', 'max_amount'));
    }

    /**
     * Create an invoice + order item. $productref controls the invoice "summary"
     * shown on the page/email (it's product.descriptionlong, OLD SYSTEM parity):
     *   20551 = Pre-purchase of SMS Expert Credits (default, buy-SMS)
     *   51930 = SMS Expert Services (buy other services)
     *   51800 = NFC Starter Pack
     *   51940/51950/51960 = Silver/Gold/Platinum Upgrade
     */
    public function createInvoice($userref, $signip, $todayunixtime, $amountinpoundswithvat, $amountinpounds, $paypalref, $productref = 20551)
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

            $productref = (int) $productref;
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

            DB::table('users_notes')->insert([
                'users_bigid' => $userref,
                'nextcontactdate' => '202601012100',
                'notes' =>    'follow',
                'insertdate' => $datenow,
                'myinsertdate' =>    $todayymdhis,
                'settonousrprenfc' => '1',
            ]);

            return $invoiceId;
        } catch (\Exception $e) {
            // Log the error and return null
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return null;
        }
    }



    public function cancelInvoice($invoiceId)
    {
        // Get the invoice
        $invoice = Invoice::findOrFail($invoiceId);

        // Update related orderItems
        OrderItem::where('invoiceref', $invoice->id)
            ->update(['status' => 'orderdeleted']);

        return redirect()->back()->with('success', 'Invoice items cancelled successfully.');
    }

    /**
     * Process PayPal payment for invoice
     */
    public function processPayPalPayment($invoiceId)
    {
        try {
            DB::beginTransaction();

            // Get the invoice
            $invoice = Invoice::findOrFail($invoiceId);

            // Get order items for this invoice
            $orderItems = OrderItem::where('invoiceref', $invoiceId)->first();

            if (!$orderItems) {
                DB::rollBack();
                return redirect()->back()->with('error', 'No order items found for this invoice.');
            }

            // 1. Update orderitem status to 'invoice'
            OrderItem::where('invoiceref', $invoiceId)
                ->update(['status' => 'invoice']);

            // 2. Update invoice with payment details
            $currentTimestamp = time();
            Invoice::where('id', $invoiceId)
                ->update([
                    'datacashref' => 'PayPal',
                    'datacashamount' => $orderItems->fullprice,
                    'datacashreply' => 'paypal',
                    'datacashstate' => 'ok',
                    'paiddate' => $currentTimestamp
                ]);

            // 3. Update user's wallet balance — ONLY for SMS-credits invoices.
            // OLD SYSTEM parity (generateInvoice.inc: producttype 'sms' = productref 20551):
            // "Pre-purchase of SMS Expert Credits" tops up the wallet; "SMS Expert Services",
            // NFC and upgrades do NOT credit the wallet.
            $user = User::where('bigid', $invoice->userref)->first();
            $isSmsCredits = ((int) ($orderItems->productref ?? 0) === 20551);
            $newWalletBalance = $user->smsg_wallet ?? 0;

            if ($user && $isSmsCredits) {
                // Formula: wallet = wallet + nonvatprice
                $newWalletBalance = $user->smsg_wallet + $orderItems->nonvatprice;

                User::where('id', $user->id)
                    ->update(['smsg_wallet' => $newWalletBalance]);

                // Log the transaction
                Log::info('PayPal payment processed', [
                    'invoice_id' => $invoiceId,
                    'user_bigid' => $invoice->userref,
                    'amount' => $orderItems->fullprice,
                    'wallet_credit' => $orderItems->nonvatprice,
                    'new_wallet_balance' => $newWalletBalance
                ]);
            } else {
                Log::info('PayPal payment processed (no wallet credit — non-SMS-credits invoice)', [
                    'invoice_id' => $invoiceId,
                    'user_bigid' => $invoice->userref,
                    'productref' => $orderItems->productref ?? null,
                    'amount' => $orderItems->fullprice,
                ]);
            }

            DB::commit();

            // Add a note about the payment with invoice download link
            $formattedDate = Carbon::now('Europe/London')->format('d/m/Y H:i');
            $noteText = "Invoice #{$invoiceId} paid via PayPal on {$formattedDate}\n";
            $noteText .= "Amount paid: £" . number_format($orderItems->fullprice, 2) . "\n";
            if ($isSmsCredits) {
                $noteText .= "Wallet credited: £" . number_format($orderItems->nonvatprice, 2) . "\n";
                $noteText .= "New wallet balance: £" . number_format($newWalletBalance, 2) . "\n";
            } else {
                $noteText .= "No wallet credit (service/upgrade invoice — not SMS credits)\n";
            }
            // Admins are User records (no 'name' column on the legacy users table) — their name is in
            // contactname (uname as fallback). auth()->user()->name was always null -> "Admin".
            $noteText .= "Processed by: " . (auth()->user()->contactname ?: (auth()->user()->uname ?: 'Admin')) . "\n\n";
            $noteText .= "Download Invoice: " . url('/admin/invoice/' . $invoiceId . '/download');

            DB::table('users_notes')->insert([
                'users_bigid' => $invoice->userref,
                'staffinitials' => auth()->user()->uname ?? 'admin', // users table has no 'name' col; uname identifies the staff member
                'nextcontactdate' => date('Ymd', strtotime('+30 days')) . '1200',
                'notes' => $noteText,
                'insertdate' => now(),
                'timelength' => '10',
                'myinsertdate' => date('YmdHis'),
                'settonousrprenfc' => '1',
            ]);

            return redirect()->back()->with('success', 'PayPal payment processed successfully. Invoice #' . $invoiceId . ' has been marked as paid.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process PayPal payment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process PayPal payment: ' . $e->getMessage());
        }
    }

    /**
     * Process BACS payment for invoice
     */
    public function processBACSPayment($invoiceId)
    {
        try {
            DB::beginTransaction();

            // Get the invoice
            $invoice = Invoice::findOrFail($invoiceId);

            // Get order items for this invoice
            $orderItems = OrderItem::where('invoiceref', $invoiceId)->first();

            if (!$orderItems) {
                DB::rollBack();
                return redirect()->back()->with('error', 'No order items found for this invoice.');
            }

            // 1. Update orderitem status to 'invoice'
            OrderItem::where('invoiceref', $invoiceId)
                ->update(['status' => 'invoice']);

            // 2. Update invoice with payment details
            $currentTimestamp = time();
            Invoice::where('id', $invoiceId)
                ->update([
                    'datacashref' => 'Cheque',
                    'datacashamount' => $orderItems->fullprice,
                    // OLD SYSTEM parity: BACS payments store datacashreply = 'bacs' — that exact value is
                    // what the Monthly Sales report classifies on (steve/mynews/toolslib.php:837 →
                    // MonthlySalesQuery). Previously this wrote 'BACS Transfer', which the classifier did
                    // not recognise, so BACS payments were mis-reported as PayPal.
                    'datacashreply' => 'bacs',
                    'datacashstate' => 'ok',
                    'paiddate' => $currentTimestamp
                ]);

            // 3. Update user's wallet balance — ONLY for SMS-credits invoices.
            // OLD SYSTEM parity (generateInvoice.inc: producttype 'sms' = productref 20551):
            // "Pre-purchase of SMS Expert Credits" tops up the wallet; "SMS Expert Services",
            // NFC and upgrades do NOT credit the wallet.
            $user = User::where('bigid', $invoice->userref)->first();
            $isSmsCredits = ((int) ($orderItems->productref ?? 0) === 20551);
            $newWalletBalance = $user->smsg_wallet ?? 0;

            if ($user && $isSmsCredits) {
                // Formula: wallet = wallet + nonvatprice
                $newWalletBalance = $user->smsg_wallet + $orderItems->nonvatprice;

                User::where('id', $user->id)
                    ->update(['smsg_wallet' => $newWalletBalance]);

                // Log the transaction
                Log::info('BACS payment processed', [
                    'invoice_id' => $invoiceId,
                    'user_bigid' => $invoice->userref,
                    'amount' => $orderItems->fullprice,
                    'wallet_credit' => $orderItems->nonvatprice,
                    'new_wallet_balance' => $newWalletBalance
                ]);
            } else {
                Log::info('BACS payment processed (no wallet credit — non-SMS-credits invoice)', [
                    'invoice_id' => $invoiceId,
                    'user_bigid' => $invoice->userref,
                    'productref' => $orderItems->productref ?? null,
                    'amount' => $orderItems->fullprice,
                ]);
            }

            DB::commit();

            // Add a note about the payment with invoice download link
            $formattedDate = Carbon::now('Europe/London')->format('d/m/Y H:i');
            $noteText = "Invoice #{$invoiceId} paid via BACS on {$formattedDate}\n";
            $noteText .= "Amount paid: £" . number_format($orderItems->fullprice, 2) . "\n";
            if ($isSmsCredits) {
                $noteText .= "Wallet credited: £" . number_format($orderItems->nonvatprice, 2) . "\n";
                $noteText .= "New wallet balance: £" . number_format($newWalletBalance, 2) . "\n";
            } else {
                $noteText .= "No wallet credit (service/upgrade invoice — not SMS credits)\n";
            }
            // Admins are User records (no 'name' column on the legacy users table) — their name is in
            // contactname (uname as fallback). auth()->user()->name was always null -> "Admin".
            $noteText .= "Processed by: " . (auth()->user()->contactname ?: (auth()->user()->uname ?: 'Admin')) . "\n\n";
            $noteText .= "Download Invoice: " . url('/admin/invoice/' . $invoiceId . '/download');

            DB::table('users_notes')->insert([
                'users_bigid' => $invoice->userref,
                'staffinitials' => auth()->user()->uname ?? 'admin', // users table has no 'name' col; uname identifies the staff member
                'nextcontactdate' => date('Ymd', strtotime('+30 days')) . '1200',
                'notes' => $noteText,
                'insertdate' => now(),
                'timelength' => '10',
                'myinsertdate' => date('YmdHis'),
                'settonousrprenfc' => '1',
            ]);

            return redirect()->back()->with('success', 'BACS payment processed successfully. Invoice #' . $invoiceId . ' has been marked as paid.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process BACS payment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process BACS payment: ' . $e->getMessage());
        }
    }

    /**
     * Download invoice as PDF
     */
    public function downloadInvoice($invoiceId)
    {
        try {
            // Get the invoice with related data
            $invoice = Invoice::with('orderItems')->findOrFail($invoiceId);

            // Get user details
            $user = User::where('bigid', $invoice->userref)->first();

            if (!$user) {
                return redirect()->back()->with('error', 'User not found for this invoice.');
            }

            // Generate the invoice HTML, then render a real PDF with DomPDF.
            $html = $this->generateInvoicePDF($invoice, $user);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isRemoteEnabled'      => true,  // allow the logo image (loaded from app URL)
                    'isHtml5ParserEnabled' => true,
                    'defaultFont'          => 'sans-serif',
                ]);

            // Stream inline so it opens in the browser's PDF viewer (clean print + save).
            return $pdf->stream('invoice_' . $invoiceId . '_' . date('Ymd') . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Failed to download invoice: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download invoice.');
        }
    }

    /**
     * Generate PDF content for invoice
     */
    private function generateInvoicePDF($invoice, $user)
    {
        // echo"<pre>";print_r($invoice->orderItems->vatrate); exit;
        $invoiceDate = Carbon::createFromTimestamp($invoice->invoicedate, 'Europe/London')->format('d/m/Y');
        $paidDate = $invoice->paiddate > 0 ? Carbon::createFromTimestamp($invoice->paiddate, 'Europe/London')->format('d/m/Y H:i') : 'Unpaid';
        $paymentMethod = '';

        // Line-item description = product.descriptionlong for this invoice's productref
        // (OLD SYSTEM parity), resolved from the invoice id (model or array safe).
        $invoiceIdForSummary = is_array($invoice) ? ($invoice['id'] ?? null) : ($invoice->id ?? null);
        $summary = \App\Models\Product::summaryFor(
            DB::table('orderitem')->where('invoiceref', $invoiceIdForSummary)->value('productref')
        );

        // Determine payment method from datacashreply or other fields.
        // datacashreply stores the raw token — 'bacs' (OLD-system parity), 'paypal', 'free', or the
        // DataCash card-gateway reply. Present it nicely so a BACS invoice shows "BACS", not "bacs".
        if ($invoice->datacashreply) {
            $dcr = strtolower(trim($invoice->datacashreply));
            if (strpos($dcr, 'bacs') === 0) {
                $paymentMethod = 'BACS';
            } elseif ($dcr === 'paypal') {
                $paymentMethod = 'PayPal';
            } elseif ($dcr === 'free') {
                $paymentMethod = 'Free';
            } else {
                $paymentMethod = 'Card'; // DataCash gateway reply = card payment
            }
        } elseif ($invoice->paiddate > 0) {
            // Check notes to determine payment method
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
    <title>Invoice #' . $invoice->id . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        /* Print layout: give the page proper margins so nothing is cut off at the
           left/right edges, and hide the on-screen Print/Download buttons. */
        @page {
            size: A4;
            margin: 15mm;
        }
        @media print {
            body { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .invoice-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
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
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: white;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 40px;
            border-bottom: 3px solid #293b50;
            padding-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            text-align: right;
        }
        .company-name {
            font-size: 32px;
            font-weight: bold;
            color: #293b50;
            margin-bottom: 5px;
        }
        .invoice-title {
            font-size: 42px;
            font-weight: 300;
            color: #293b50;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        .invoice-number {
            font-size: 18px;
            color: #666;
        }
        .invoice-info {
            margin-bottom: 40px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .info-col {
            display: table-cell;
            vertical-align: top;
            width: 50%;
            padding-right: 20px;
        }
        .info-col:last-child {
            padding-right: 0;
            text-align: right;
        }
        .info-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }
        .info-value {
            font-size: 14px;
            margin-bottom: 3px;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background: #293b50;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: normal;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals {
            margin-top: 30px;
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
            font-weight: bold;
            color: #666;
        }
        .total-value {
            display: inline-block;
            width: 140px;
            text-align: right;
        }
        .grand-total {
            border-top: 2px solid #293b50;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 20px;
        }
        .grand-total .total-label {
            color: #293b50;
        }
        .grand-total .total-value {
            color: #293b50;
            font-weight: bold;
        }
        .footer {
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        .payment-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        .payment-info h3 {
            color: #293b50;
            margin-bottom: 15px;
            font-size: 16px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            .invoice-container { 
                padding: 0; 
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
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="header-left">
                <img src="' . (is_file(public_path('assets/images/auth/smsexpertlogowhiteback.jpg')) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents(public_path('assets/images/auth/smsexpertlogowhiteback.jpg'))) : asset('assets/images/auth/smsexpertlogowhiteback.jpg')) . '" alt="SMS Expert" width="240" style="width: 240px; height: auto; margin-bottom: 12px;">
                <div style="color: #666; font-size: 14px;">
                    Oak Business Centre,
79-93 Ratcliffe Road, Sileby,Leicestershire, LE12 7PU
United Kingdom<br>
                    Email: care@smsexpert.co.uk<br>
                    Web: www.smsexpert.co.uk
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">#' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) . '</div>
                <div class="status-badge ' . ($invoice->paiddate > 0 ? 'status-paid' : 'status-unpaid') . '">
                    ' . ($invoice->paiddate > 0 ? 'PAID' : 'UNPAID') . '
                </div>
            </div>
        </div>
        
        <div class="invoice-info">
            <div class="info-row">
                <div class="info-col">
                    <div class="info-label">Bill To</div>
                    <div class="info-value"><strong>' . htmlspecialchars(urldecode($user->busname ?? 'N/A')) . '</strong></div>
                    <div class="info-value">' . htmlspecialchars(urldecode($user->contactname ?? '')) . '</div>
                    <div class="info-value">' . htmlspecialchars($user->address1 ?? '') . '</div>
                    ' . ($user->address2 ? '<div class="info-value">' . htmlspecialchars($user->address2) . '</div>' : '') . '
                    <div class="info-value">' . htmlspecialchars($user->town ?? '') . ', ' . htmlspecialchars($user->pcode ?? '') . '</div>
                    <div class="info-value">' . htmlspecialchars($user->country ?? 'United Kingdom') . '</div>
                    <div class="info-value" style="margin-top: 10px;">
                        <strong>Email:</strong> ' . htmlspecialchars($user->contactemail ?? '') . '<br>
                        <strong>Phone:</strong> ' . htmlspecialchars($user->phone ?? '') . '
                    </div>
                </div>
                <div class="info-col">
                    <div class="info-label">Invoice Details</div>
                    <div class="info-value"><strong>Invoice Date:</strong> ' . $invoiceDate . '</div>
                    ' . ($invoice->paiddate > 0 ? '<div class="info-value"><strong>Payment Date:</strong> ' . $paidDate . '</div>' : '') . '
                    ' . ($paymentMethod ? '<div class="info-value"><strong>Payment Method:</strong> ' . $paymentMethod . '</div>' : '') . '
                    <div class="info-value"><strong>Customer ID:</strong> ' . htmlspecialchars($user->bigid ?? '') . '</div>
                    <div class="info-value"><strong>VAT Number:</strong> GB 123 4567 89</div>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Description</th>
                  
                    <th style="width: 15%;" class="text-right">Unit Price</th>
                    <th style="width: 15%;" class="text-center">VAT Rate</th>
                    <th style="width: 15%;" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>';
        // echo"<pre>";print_r($invoice->orderItems); exit;
        // foreach ($invoice->orderItems as $item) {
        $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($summary) . '</strong></td>
                    <td class="text-center">' . number_format($invoice->orderItems->nonvatprice) . '</td>
                   
                    <td class="text-center">' . $invoice->orderItems->vatrate . '%</td>
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
                SMS Expert Limited, | UK Reg. 12106151 | VAT Reg. GB332497592<br>
                Terms: Payment due within 30 days | Late payments subject to interest charges
            </p>
            <p style="margin-top: 10px;">&copy; ' . date('Y') . ' SMS Expert. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
