<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Services\SMSService;
use App\Services\Queue\SmsQueueService;
use App\Services\Queue\RabbitMQService;
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

class SMSController extends Controller
{
    protected $smsService;
    protected $smsQueueService;
    protected $rabbitMQService;
    protected $bulkThroughputService;
    protected $walletValidationService;
    public $userBigId;
    public $GRACE_PERIOD = 5;

    public function __construct(SMSService $smsService)
    {
        $this->smsService = $smsService;
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();

        try {
            $this->smsQueueService = new SmsQueueService();
            $this->rabbitMQService = new RabbitMQService();
        } catch (\Exception $e) {
            Log::error('Failed to initialize SMPP services: ' . $e->getMessage());
            $this->smsQueueService = null;
            $this->rabbitMQService = null;
        }
    }

    // ... (keep all existing methods: index, extractCountryCode, calculateSmsParts, calculateCost)

    public function sendMessage(Request $request)
    {
        // Determine if this is scheduled send or immediate send
        $isScheduled = $request->has('send_type') && $request->input('send_type') === 'send_later';
        
        // Validate the input
        $validated = $request->validate([
            'txtTo' => [
                'required',
                function ($attribute, $value, $fail) {
                    $numbers = explode(',', $value);
                    $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9})$/';
                    $indiaPattern = '/^\+?91\d{10}$/';

                    $invalidNumbers = [];

                    foreach ($numbers as $number) {
                        $number = trim($number);

                        if (!preg_match($ukPattern, $number) && !preg_match($indiaPattern, $number)) {
                            $invalidNumbers[] = $number;
                        }
                    }

                    if (!empty($invalidNumbers)) {
                        $fail("The following numbers are invalid: " . implode(', ', $invalidNumbers));
                    }
                },
            ],
            'messageContent' => 'required',
            'send_date' => $isScheduled ? 'required|date' : 'nullable|date',
            'send_hh' => $isScheduled ? 'required' : 'nullable',
            'send_mm' => $isScheduled ? 'required' : 'nullable',
        ]);

        // Get user info for bulk throughput validation
        $userInfo = Session::get('user_info');
        $userref = isset($userInfo['bigid']) ? $userInfo['bigid'] : 'system';

        // Get user ID from users table for blacklist check
        $getUserId = User::where('bigid', $userref)->first();
        if (!$getUserId) {
            return back()->with('error', 'User not found.');
        }
       
        // Parse and format phone numbers first
        $to = explode(',', $validated['txtTo']);
        $to = array_map(function ($number) {
            $number = preg_replace('/\D/', '', $number);
            if (substr($number, 0, 1) === '0') {
                return '44' . substr($number, 1);
            }
            return $number;
        }, $to);

        // Check for blacklisted numbers in itagg_outbound_blacklist table
        $blacklistedNumbers = [];
        $allowedNumbers = [];
        
        foreach ($to as $phoneNumber) {
            $isBlacklisted = DB::table('itagg_outbound_blacklist')
                ->where(function ($query) use ($userref, $phoneNumber) {
                    $query->where('users_bigid', $userref)
                        ->where('msisdn', $phoneNumber);
                })
                ->exists();

            if ($isBlacklisted) {
                $blacklistedNumbers[] = $phoneNumber;
            } else {
                $allowedNumbers[] = $phoneNumber;
            }
        }

        // If ALL numbers are blacklisted, return error and stop
        if (count($allowedNumbers) === 0) {
            $errorMessage = 'All number(s) have been blocked by iTAGG and need to be unblocked before sending: ' . implode(', ', $blacklistedNumbers);
            return back()->with('error', $errorMessage);
        }

        // Update $to array to only include allowed numbers
        $to = $allowedNumbers;
        
        // Store blacklisted info for later display
        $hasBlockedNumbers = count($blacklistedNumbers) > 0;

        // Check bulk throughput limit before processing
        $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($userref);

        if (!$throughputCheck['allowed']) {
            Log::warning('SMS sending blocked due to throughput limit', [
                'user' => $userref,
                'reason' => $throughputCheck['reason'] ?? 'limit_exceeded',
                'current_tally' => $throughputCheck['current_tally'] ?? 0,
                'limit' => $throughputCheck['limit'] ?? 0
            ]);

            return back()->with('error', 'You have reached your daily SMS send limit of ' . ($throughputCheck['limit'] ?? 0) . ' messages. Please contact support to increase your limit.');
        }

        // Count the recipient numbers
        $messageCount = count($to);

        // Check wallet balance before processing
        $walletCheck = $this->walletValidationService->validateWalletBalance($userref, $messageCount, $to);

        if (!$walletCheck['has_funds']) {
            Log::warning('SMS sending blocked due to insufficient funds', [
                'user' => $userref,
                'reason' => $walletCheck['reason'] ?? 'insufficient_funds',
                'current_balance' => $walletCheck['current_balance'] ?? 0,
                'required_amount' => $walletCheck['required_amount'] ?? 0
            ]);

            $errorMessage = 'Insufficient wallet funds. ';
            $errorMessage .= 'Current balance: £' . number_format($walletCheck['current_balance'] ?? 0, 2) . '. ';
            $errorMessage .= 'Required: £' . number_format($walletCheck['required_amount'] ?? 0, 2) . '. ';
            $errorMessage .= 'Please top up your SMS wallet to continue sending messages.';

            return back()->with('error', $errorMessage);
        }

        $message = $validated['messageContent'];

        // Generate unique bigid
        $bigid = md5(uniqid(rand(), true));

        $from = $request->txtSenderSpecific ?? $request->txtSenderDefault ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME');
        $smsType = $request->messageTypes ?? 'sms';
        $numbits = 7;
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $affiliateref = '0';
        $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

        // Determine send time and status based on send type
        if ($isScheduled && $request->has('send_date')) {
            // Scheduled send
            $sendAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $request->send_date . ' ' . $request->send_hh . ':' . $request->send_mm,
                'Europe/London'
            );
            
            // Validate scheduled time is in future
            if ($sendAt->isPast()) {
                return back()->with('error', 'Scheduled time must be in the future.');
            }
            
            $send = $sendAt->format('YmdHis');
            $scheduleDeliveryTime = $sendAt->toDateTimeString();
            $sentstatus = 'tomorrowonward';  // Keep existing status for scheduled
            $sentstatustext = 'Scheduled for delivery';
        } else {
            // Immediate send
            $send = $datenow;
            $scheduleDeliveryTime = Carbon::now('Europe/London')->toDateTimeString();
            $sentstatus = 'pending';
            $sentstatustext = $useSmppQueue ? 'Queued for SMPP delivery' : 'Pending';
        }

        // Track results
        $successCount = 0;
        $failedCount = 0;
        $queuedMessages = [];

        // Check if message type is WhatsApp
        if ($smsType === 'whatsapp') {
            return $this->sendWhatsAppMessage($to, $message, $from, $bigid, $userref, $datenow);
        }

        // Insert data into the smsg_log table
        foreach ($to as $i => $thenum) {
            try {
                $countryInfo = $this->extractCountryCode($thenum);
                $thecountrycodes = $countryInfo ? $countryInfo->dialcode : '44';

                // Insert into smsg_log for record keeping
                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => $smsType,
                    'initiator' => 'ControlPanel',
                    'bigid' => $bigid,
                    'mobnum' => $thenum,
                    'numparts' => $this->calculateSmsParts($message),
                    'text' => $message,
                    'originator' => $from,
                    'numbits' => $numbits,
                    'timesubmitted' => $datenow,
                    'userref' => $userref,
                    'affiliateref' => $affiliateref,
                    'dosendtime' => $send,
                    'timesent' => '00000000000000',
                    'sentstatus' => $sentstatus,
                    'sentstatustext' => $sentstatustext,
                    'suppliermsgref' => 0,
                    'costprice' => 0.000000,
                    'userprice' => 0.000000,
                    'aggregator_dlrcode' => 0,
                    'aggregator_dlrmsg' => $isScheduled ? 'Scheduled' : ($useSmppQueue ? 'Queued' : 'Non Delivered'),
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => 0.000000,
                    'countrydialcode' => $thecountrycodes ?? '',
                    'suppliername' => $useSmppQueue ? 'Vonage SMPP' : '',
                    'supplierrouteref' => '',
                    'deliverystatus2' => $isScheduled ? 'tomorrowonward' : 'pending',
                ]);

                // ONLY queue to RabbitMQ if NOT scheduled
                if (!$isScheduled && $useSmppQueue) {
                    // Queue SMS via SMPP Queue Service for IMMEDIATE send
                    $queueParams = [
                        'user_ref' => $userref,
                        'mobile_number' => $thenum,
                        'message' => $message,
                        'sender_id' => $from,
                        'priority' => 5,
                        'reference_id' => $bigid,
                        'metadata' => [
                            'smsg_log_id' => $smsgLogId,
                            'bigid' => $bigid,
                            'source' => 'web_form',
                            'scheduled' => false
                        ]
                    ];
                    
                    $queueResult = $this->smsQueueService->queueSms($queueParams);

                    if ($queueResult['success']) {
                        $successCount++;
                        $queuedMessages[] = [
                            'queue_id' => $queueResult['queue_id'],
                            'mobile' => $thenum
                        ];

                        Log::info('SMS queued successfully via SMPP', [
                            'queue_id' => $queueResult['queue_id'],
                            'mobile' => $thenum,
                            'bigid' => $bigid
                        ]);
                    } else {
                        $failedCount++;
                        Log::error('Failed to queue SMS via SMPP', [
                            'mobile' => $thenum,
                            'error' => $queueResult['error'] ?? 'Unknown error'
                        ]);

                        // Update smsg_log to show failed
                        DB::table('smsg_log')
                            ->where('id', $smsgLogId)
                            ->update([
                                'sentstatus' => 'fail',
                                'sentstatustext' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown error')
                            ]);
                    }
                } else {
                    // If scheduled OR SMPP not available, just count as success (logged in DB)
                    $successCount++;
                    
                    if ($isScheduled) {
                        Log::info('SMS scheduled for later delivery', [
                            'mobile' => $thenum,
                            'bigid' => $bigid,
                            'scheduled_at' => $scheduleDeliveryTime
                        ]);
                    } else {
                        Log::info('SMS logged (SMPP not available)', ['mobile' => $thenum]);
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Error processing SMS', [
                    'mobile' => $thenum,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Return response based on results
        if ($successCount > 0) {
            if ($isScheduled) {
                $message = "SMS scheduled successfully! {$successCount} message(s) will be sent at " . (isset($sendAt) ? $sendAt->format('Y-m-d H:i') : 'scheduled time');
            } else {
                $message = "SMS sent successfully! {$successCount} message(s) sent";
            }
            
            // Add info about blocked numbers if any
            if ($hasBlockedNumbers) {
                $message .= ". Note: The following number(s) were blocked by iTAGG and not sent: " . implode(', ', $blacklistedNumbers);
            }

            return back()->with('success', $message);
        } else {
            return back()->with('error', 'Failed to process SMS messages. Please try again.');
        }
    }

    // ... (keep all other existing methods unchanged)
}
