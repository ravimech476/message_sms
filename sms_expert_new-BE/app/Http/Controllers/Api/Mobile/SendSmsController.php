<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Carbon\Carbon;
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use App\Services\Queue\SmsQueueService;
use App\Services\Queue\RabbitMQService;
use App\Services\UserRouteService;
use Illuminate\Support\Facades\Http;

class SendSmsController extends Controller
{
    protected $bulkThroughputService;
    protected $walletValidationService;
    protected $smsQueueService;
    protected $rabbitMQService;
    protected $userRouteService; // OLD SYSTEM pricing service

    public function __construct(UserRouteService $userRouteService)
    {
        $this->userRouteService = $userRouteService;
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();

        // Initialize SMPP Queue Services
        try {
            $this->smsQueueService = new SmsQueueService();
            $this->rabbitMQService = new RabbitMQService();
        } catch (\Exception $e) {
            Log::error('Failed to initialize SMPP services: ' . $e->getMessage());
            $this->smsQueueService = null;
            $this->rabbitMQService = null;
        }
    }

    /**
     * Get Send SMS page data
     * GET /api/mobile/send-sms
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Get user data with options
            $userData = User::with('options')->where('bigid', $bigid)->first();

            if (!$userData) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Calculate wallet balance
            $walletBalance = $userData->smsg_wallet - $userData->smsg_server1_sent - $userData->smsg_server2_sent;

            // Get WhatsApp enabled status
            $whatsappEnabled = $userData->whatsapp_enabled === 'yes';

            // Get default sender ID
            $defaultSenderId = $userData->options->first()->defaultsenderid ?? 'MYBRANDNAME';
            $customSenderId = $userData->sms_sender_id ?? '';

            // Get current time in London timezone
            $now = Carbon::now('Europe/London');

            // Get user's SMS shortcodes for response routes
            $smsShortcodes = DB::table('smsshortcodes')
                ->select('smsshortcodes.id', 'smsshortcodes.number')
                ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
                ->where('itagg_instance.users_bigid', $bigid)
                ->where('itagg_instance.status', 1)
                ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
                ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
                ->orderBy('smsshortcodes.number')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Send SMS data retrieved successfully',
                'data' => [
                    'user' => [
                        'bigid' => $bigid,
                        'name' => $userData->contactname,
                        'email' => $userData->contactemail,
                    ],
                    'wallet' => [
                        'balance' => round($walletBalance, 2),
                        'currency' => '£',
                        'formatted' => '£ ' . number_format($walletBalance, 2),
                    ],
                    'sender_id' => [
                        'default' => $defaultSenderId,
                        'custom' => $customSenderId,
                    ],
                    'whatsapp_enabled' => $whatsappEnabled,
                    'sms_shortcodes' => $smsShortcodes,
                    'current_time' => [
                        'date' => $now->format('Y-m-d'),
                        'hour' => $now->format('H'),
                        'minute' => (string)(floor($now->format('i') / 5) * 5),
                    ],
                    'message_limits' => [
                        'max_characters' => 1206,
                        'single_sms_limit' => 160,
                        'concatenated_sms_limit' => 153,
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching send SMS data', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch send SMS data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contacts (favourites or groups)
     * GET /api/mobile/send-sms/contacts
     */
    public function getContacts(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $listType = $request->query('type', 'favourites');

            $contacts = [];

            if ($listType === 'favourites') {
                // Get favourites
                $contacts = DB::table('mobile_favourite')
                    ->select('msisdn', 'name', 'user_bigid')
                    ->where('user_bigid', $bigid)
                    ->orderBy('name')
                    ->get()
                    ->map(function ($contact) {
                        return [
                            'id' => $contact->msisdn,
                            'name' => $contact->name ?: 'Contact (' . $contact->msisdn . ')',
                            'number' => $contact->msisdn,
                        ];
                    });
            } else {
                // Get groups with their members from the legacy CP address-book join
                // (itagg_module_Subscription_data never existed). Members:
                // cp_users_groups -> cp_group_addressbook -> cp_users_addressbook -> itagg_mobiledetail
                $groups = DB::table('cp_users_groups as g')
                    ->join('cp_group_addressbook as ga', 'g.id', '=', 'ga.group_id')
                    ->join('cp_users_addressbook as ab', 'ga.addressbook_id', '=', 'ab.id')
                    ->join('itagg_mobiledetail as md', 'ab.itagg_mobiledetail_id', '=', 'md.id')
                    ->where('g.user_bigid', $bigid)
                    ->select('g.id', 'g.name as groupname', 'md.msisdn')
                    ->orderBy('g.name')
                    ->get()
                    ->map(function ($group) {
                        return [
                            'id' => $group->id,
                            'name' => urldecode($group->groupname ?: '') ?: 'Group',
                            'number' => $group->msisdn,
                        ];
                    });
                $contacts = $groups;
            }

            return response()->json([
                'success' => true,
                'message' => 'Contacts retrieved successfully',
                'data' => [
                    'type' => $listType,
                    'contacts' => $contacts,
                    'total' => count($contacts),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching contacts', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contacts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate SMS cost
     * POST /api/mobile/send-sms/calculate
     *
     * OLD SYSTEM LOGIC:
     * - Uses smsg_userroute table for user-specific pricing
     * - Falls back to default user rates if no user-specific rate found
     */
    public function calculateCost(Request $request)
    {
        try {
            $validated = $request->validate([
                'recipients' => 'required|string',
                'message' => 'required|string'
            ]);

            $user = $request->user();
            $bigid = $user->bigid;

            $getUserId = User::where('bigid', $bigid)->first();

            if (!$getUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Parse phone numbers
            $phoneNumbers = array_map('trim', explode(',', $validated['recipients']));
            $phoneNumbers = array_filter($phoneNumbers);

            // Calculate message parts
            $message = $validated['message'];
            $messageLength = mb_strlen($message, 'UTF-8');
            $smsParts = $this->calculateSmsParts($message);

            // OLD SYSTEM: Default routes
            $ukRoutenum = 7002;  // UK Direct
            $intlRoutenum = 8002; // International

            // Group numbers by country and calculate costs
            $totalCost = 0;
            $invalidNumbers = [];
            $countryStats = [];
            $validNumbers = [];

            foreach ($phoneNumbers as $number) {
                // Clean the number
                $cleanNumber = preg_replace('/[^0-9+]/', '', $number);

                // Format UK numbers
                if (substr($cleanNumber, 0, 2) == '07') {
                    $cleanNumber = '44' . substr($cleanNumber, 1);
                }

                // Validate number format
                if (!$this->isValidPhoneNumber($cleanNumber)) {
                    $invalidNumbers[] = $number;
                    continue;
                }

                $validNumbers[] = $cleanNumber;

                // Extract country code
                $countryInfo = $this->extractCountryCode($cleanNumber);

                if (!$countryInfo) {
                    $invalidNumbers[] = $number;
                    continue;
                }

                $countryCode = $countryInfo->dialcode;

                // Select route based on country (UK vs International)
                $routenum = ($countryCode === '44') ? $ukRoutenum : $intlRoutenum;

                // Get pricing from smsg_userroute + country cost table
                // Default to vonage for mobile API cost calculation (most common)
                $pricing = $this->userRouteService->getPricingForPhoneNumber(
                    $bigid,
                    $cleanNumber,
                    $routenum,
                    7,      // numbits (7-bit for standard text)
                    'alpha', // origtype
                    'vonage' // default operator for cost lookup
                );

                $ratePerSMS = $pricing['userprice'];

                // Calculate cost for this number
                $costForNumber = round($ratePerSMS * $smsParts, 4);
                $totalCost = round($totalCost + $costForNumber, 4);

                // Track country statistics
                if (!isset($countryStats[$countryCode])) {
                    $countryStats[$countryCode] = [
                        'country' => $countryInfo->iso_code ?? 'Unknown',
                        'dialcode' => $countryCode,
                        'count' => 0,
                        'rate_per_sms' => (float)$ratePerSMS,
                        'total_cost' => 0,
                    ];
                }
                $countryStats[$countryCode]['count']++;
                $countryStats[$countryCode]['total_cost'] = round($countryStats[$countryCode]['total_cost'] + $costForNumber, 4);
            }

            // Get wallet balance
            $walletBalance = $getUserId->smsg_wallet - $getUserId->smsg_server1_sent - $getUserId->smsg_server2_sent;
            $hasSufficientFunds = $walletBalance >= $totalCost;

            return response()->json([
                'success' => true,
                'message' => 'Cost calculated successfully',
                'data' => [
                    'message_info' => [
                        'length' => $messageLength,
                        'sms_parts' => $smsParts,
                        'part_info' => $smsParts > 1 ? 'Multi-part message (153 chars each)' : 'Single message (up to 160 chars)',
                    ],
                    'recipients' => [
                        'total' => count($validNumbers),
                        'invalid' => count($invalidNumbers),
                        'invalid_numbers' => $invalidNumbers,
                    ],
                    'cost_breakdown' => array_values($countryStats),
                    'total_cost' => [
                        'amount' => round($totalCost, 4),
                        'formatted' => '£ ' . number_format($totalCost, 4),
                    ],
                    'wallet' => [
                        'balance' => round($walletBalance, 2),
                        'formatted' => '£ ' . number_format($walletBalance, 2),
                        'sufficient_funds' => $hasSufficientFunds,
                        'shortage' => !$hasSufficientFunds ? round($totalCost - $walletBalance, 2) : 0,
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating SMS cost', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate cost: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send SMS
     * POST /api/mobile/send-sms
     */
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'recipients' => 'required|string',
                'message' => 'required|string',
                'sender_id' => 'required|string|max:15',
                'message_type' => 'nullable|string|in:sms,whatsapp',
            ]);

            $user = $request->user();
            $bigid = $user->bigid;
            $messageType = $validated['message_type'] ?? 'sms';

            $getUserId = User::where('bigid', $bigid)->first();
            if (!$getUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Parse and format phone numbers
            $phoneNumbers = array_map('trim', explode(',', $validated['recipients']));
            $phoneNumbers = array_filter($phoneNumbers);

            $formattedNumbers = [];
            $invalidNumbers = [];

            foreach ($phoneNumbers as $number) {
                $cleanNumber = preg_replace('/\D/', '', $number);
                if (substr($cleanNumber, 0, 1) === '0') {
                    $cleanNumber = '44' . substr($cleanNumber, 1);
                }

                if ($this->isValidPhoneNumber($cleanNumber)) {
                    // Check blacklist
                    $isBlacklisted = DB::table('itagg_outbound_blacklist')
                        ->where('users_bigid', $bigid)
                        ->where('msisdn', $cleanNumber)
                        ->exists();

                    if (!$isBlacklisted) {
                        $formattedNumbers[] = $cleanNumber;
                    }
                } else {
                    $invalidNumbers[] = $number;
                }
            }

            if (empty($formattedNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid phone numbers provided',
                    'data' => ['invalid_numbers' => $invalidNumbers]
                ], 400);
            }

            // Check throughput limit
            $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($bigid);
            if (!$throughputCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily SMS limit reached. Limit: ' . ($throughputCheck['limit'] ?? 0)
                ], 429);
            }

            // Check wallet balance
            $walletCheck = $this->walletValidationService->validateWalletBalance($bigid, count($formattedNumbers), $formattedNumbers);
            if (!$walletCheck['has_funds']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet funds',
                    'data' => [
                        'current_balance' => $walletCheck['current_balance'] ?? 0,
                        'required_amount' => $walletCheck['required_amount'] ?? 0,
                    ]
                ], 402);
            }

            // Generate unique bigid for this batch
            $batchId = md5(uniqid(rand(), true));
            $message = $validated['message'];
            $senderId = $validated['sender_id'];
            $datenow = Carbon::now('Europe/London')->format('YmdHis');

            // Handle WhatsApp messages
            if ($messageType === 'whatsapp') {
                return $this->sendWhatsAppMessage($formattedNumbers, $message, $senderId, $batchId, $bigid, $datenow);
            }

            // Send SMS via SMPP
            $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);
            $successCount = 0;
            $failedCount = 0;

            // Calculate dosendtimeint as Unix timestamp (like old system)
            $dosendtimeint = mktime(
                (int) substr($datenow, 8, 2),
                (int) substr($datenow, 10, 2),
                (int) substr($datenow, 12, 2),
                (int) substr($datenow, 4, 2),
                (int) substr($datenow, 6, 2),
                (int) substr($datenow, 0, 4)
            );

            // Calculate daemon priority (like old system)
            $userDaemonPriority = $user->daemonpriority ?? 100;
            $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($formattedNumbers) > 500 ? 200 : $userDaemonPriority;
            $daemonId = $baseDaemonId + mt_rand(0, 39);

            foreach ($formattedNumbers as $thenum) {
                try {
                    $countryInfo = $this->extractCountryCode($thenum);
                    $countryCode = $countryInfo ? $countryInfo->dialcode : '44';

                    // Insert into smsg_log
                    $smsgLogId = DB::table('smsg_log')->insertGetId([
                        'sms_type' => 'sms',
                        'initiator' => 'MobileApp',
                        'bigid' => $batchId,
                        'mobnum' => $thenum,
                        'numparts' => $this->calculateSmsParts($message),
                        'text' => $message,
                        'originator' => $senderId,
                        'numbits' => 7,
                        'timesubmitted' => $datenow,
                        'userref' => $bigid,
                        'affiliateref' => '0',
                        'dosendtime' => $datenow,
                        'dosendtimeint' => $dosendtimeint,
                        'dayofyear' => substr($datenow, 0, 8),
                        'timesent' => '00000000000000',
                        'sentstatus' => 'pending',
                        'sentstatustmp' => 'pending',
                        'sentstatustext' => $useSmppQueue ? 'Queued for SMPP delivery' : 'Pending',
                        'suppliermsgref' => '',
                        'smsgdaemonid' => $daemonId,
                        'sendpriority' => $baseDaemonId,
                        'costprice' => 0.000000,
                        'userprice' => 0.000000,
                        'aggregator_dlrcode' => 0,
                        'aggregator_dlrmsg' => $useSmppQueue ? 'Queued' : 'Non Delivered',
                        'campaignref' => '',
                        'binaryflags' => '',
                        'profit' => 0.000000,
                        'countrydialcode' => $countryCode,
                        'suppliername' => $useSmppQueue ? 'Vonage SMPP' : '',
                        'supplierrouteref' => '',
                        'requested_route' => 0,
                        'requested_routetag' => '',
                        'deliverystatus2' => 'pending',
                        'migration_flag' => 'new',
                    ]);

                    if ($useSmppQueue) {
                        $queueResult = $this->smsQueueService->queueSms([
                            'user_ref' => $bigid,
                            'mobile_number' => $thenum,
                            'message' => $message,
                            'sender_id' => $senderId,
                            'priority' => 5,
                            'reference_id' => $batchId,
                            'metadata' => [
                                'smsg_log_id' => $smsgLogId,
                                'bigid' => $batchId,
                                'source' => 'mobile_app',
                            ]
                        ]);

                        if ($queueResult['success']) {
                            $successCount++;
                        } else {
                            $failedCount++;
                            DB::table('smsg_log')
                                ->where('id', $smsgLogId)
                                ->update([
                                    'sentstatus' => 'fail',
                                    'sentstatustext' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown error')
                                ]);
                        }
                    } else {
                        $successCount++;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Error sending SMS', [
                        'mobile' => $thenum,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => $successCount > 0,
                'message' => $successCount > 0 
                    ? "SMS sent successfully! {$successCount} message(s) sent." 
                    : 'Failed to send SMS messages',
                'data' => [
                    'batch_id' => $batchId,
                    'sent' => $successCount,
                    'failed' => $failedCount,
                    'invalid_numbers' => $invalidNumbers,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending SMS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule SMS for later
     * POST /api/mobile/send-sms/schedule
     */
    public function schedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'recipients' => 'required|string',
                'message' => 'required|string',
                'sender_id' => 'required|string|max:15',
                'send_date' => 'required|date',
                'send_hour' => 'required|string',
                'send_minute' => 'required|string',
                'message_type' => 'nullable|string|in:sms,whatsapp',
            ]);

            $user = $request->user();
            $bigid = $user->bigid;

            $getUserId = User::where('bigid', $bigid)->first();
            if (!$getUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Create scheduled datetime
            $sendAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['send_date'] . ' ' . $validated['send_hour'] . ':' . $validated['send_minute'],
                'Europe/London'
            );

            if ($sendAt->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheduled time must be in the future'
                ], 400);
            }

            // Parse and format phone numbers
            $phoneNumbers = array_map('trim', explode(',', $validated['recipients']));
            $phoneNumbers = array_filter($phoneNumbers);

            $formattedNumbers = [];
            $invalidNumbers = [];

            foreach ($phoneNumbers as $number) {
                $cleanNumber = preg_replace('/\D/', '', $number);
                if (substr($cleanNumber, 0, 1) === '0') {
                    $cleanNumber = '44' . substr($cleanNumber, 1);
                }

                if ($this->isValidPhoneNumber($cleanNumber)) {
                    $formattedNumbers[] = $cleanNumber;
                } else {
                    $invalidNumbers[] = $number;
                }
            }

            if (empty($formattedNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid phone numbers provided',
                    'data' => ['invalid_numbers' => $invalidNumbers]
                ], 400);
            }

            // Generate unique batch ID
            $batchId = md5(uniqid(rand(), true));
            $message = $validated['message'];
            $senderId = $validated['sender_id'];
            $datenow = Carbon::now('Europe/London')->format('YmdHis');
            $scheduleTime = $sendAt->format('YmdHis');

            // Calculate dosendtimeint as Unix timestamp (like old system)
            $dosendtimeint = mktime(
                (int) substr($scheduleTime, 8, 2),
                (int) substr($scheduleTime, 10, 2),
                (int) substr($scheduleTime, 12, 2),
                (int) substr($scheduleTime, 4, 2),
                (int) substr($scheduleTime, 6, 2),
                (int) substr($scheduleTime, 0, 4)
            );

            // Calculate daemon priority (like old system)
            $userDaemonPriority = $user->daemonpriority ?? 100;
            $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($formattedNumbers) > 500 ? 200 : $userDaemonPriority;
            $daemonId = $baseDaemonId + mt_rand(0, 39);

            $successCount = 0;
            $failedCount = 0;

            foreach ($formattedNumbers as $thenum) {
                try {
                    $countryInfo = $this->extractCountryCode($thenum);
                    $countryCode = $countryInfo ? $countryInfo->dialcode : '44';

                    DB::table('smsg_log')->insert([
                        'sms_type' => $validated['message_type'] ?? 'sms',
                        'initiator' => 'MobileApp',
                        'bigid' => $batchId,
                        'mobnum' => $thenum,
                        'numparts' => $this->calculateSmsParts($message),
                        'text' => $message,
                        'originator' => $senderId,
                        'numbits' => 7,
                        'timesubmitted' => $datenow,
                        'userref' => $bigid,
                        'affiliateref' => '0',
                        'dosendtime' => $scheduleTime,
                        'dosendtimeint' => $dosendtimeint,
                        'dayofyear' => substr($scheduleTime, 0, 8),
                        'timesent' => '00000000000000',
                        'sentstatus' => 'tomorrowonward',
                        'sentstatustmp' => 'tomorrowonward',
                        'sentstatustext' => 'Scheduled for delivery',
                        'suppliermsgref' => '',
                        'smsgdaemonid' => $daemonId,
                        'sendpriority' => $baseDaemonId,
                        'costprice' => 0.000000,
                        'userprice' => 0.000000,
                        'aggregator_dlrcode' => 0,
                        'aggregator_dlrmsg' => 'Scheduled',
                        'campaignref' => '',
                        'binaryflags' => '',
                        'profit' => 0.000000,
                        'countrydialcode' => $countryCode,
                        'suppliername' => 'Vonage SMPP',
                        'supplierrouteref' => '',
                        'requested_route' => 0,
                        'requested_routetag' => '',
                        // OLD parity: scheduled = sentstatus 'tomorrowonward', deliverystatus2 EMPTY (DLR column).
                        'deliverystatus2' => '',
                        'migration_flag' => 'new',
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error('Error scheduling SMS', [
                        'mobile' => $thenum,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => $successCount > 0,
                'message' => $successCount > 0 
                    ? "SMS scheduled successfully! {$successCount} message(s) will be sent at " . $sendAt->format('Y-m-d H:i')
                    : 'Failed to schedule SMS messages',
                'data' => [
                    'batch_id' => $batchId,
                    'scheduled' => $successCount,
                    'failed' => $failedCount,
                    'scheduled_at' => $sendAt->format('Y-m-d H:i'),
                    'invalid_numbers' => $invalidNumbers,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error scheduling SMS', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send WhatsApp message
     */
    private function sendWhatsAppMessage($recipients, $message, $from, $batchId, $userref, $datenow)
    {
        $nexmoApiKey = env('NEXMO_API_KEY');
        $nexmoApiSecret = env('NEXMO_API_SECRET');
        $nexmoWhatsAppNumber = env('NEXMO_WHATSAPP_NUMBER', $from);

        if (!$nexmoApiKey || !$nexmoApiSecret || !$nexmoWhatsAppNumber) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp service is not configured'
            ], 503);
        }

        $successCount = 0;
        $failedCount = 0;
        $messageIds = [];

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2),
            (int) substr($datenow, 10, 2),
            (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2),
            (int) substr($datenow, 6, 2),
            (int) substr($datenow, 0, 4)
        );

        // Calculate daemon priority (default for WhatsApp)
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        foreach ($recipients as $thenum) {
            try {
                $countryInfo = $this->extractCountryCode($thenum);
                $countryCode = $countryInfo ? $countryInfo->dialcode : '44';

                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => 'whatsapp',
                    'initiator' => 'MobileApp',
                    'bigid' => $batchId,
                    'mobnum' => $thenum,
                    'numparts' => 1,
                    'text' => $message,
                    'originator' => $nexmoWhatsAppNumber,
                    'numbits' => 7,
                    'timesubmitted' => $datenow,
                    'userref' => $userref,
                    'affiliateref' => '0',
                    'dosendtime' => $datenow,
                    'dosendtimeint' => $dosendtimeint,
                    'dayofyear' => substr($datenow, 0, 8),
                    'timesent' => '00000000000000',
                    'sentstatus' => 'pending',
                    'sentstatustmp' => 'pending',
                    'sentstatustext' => 'Sending via WhatsApp',
                    'suppliermsgref' => '',
                    'smsgdaemonid' => $daemonId,
                    'sendpriority' => $baseDaemonId,
                    'costprice' => 0.000000,
                    'userprice' => 0.000000,
                    'aggregator_dlrcode' => 0,
                    'aggregator_dlrmsg' => 'Pending',
                    'countrydialcode' => $countryCode,
                    'suppliername' => 'Nexmo WhatsApp',
                    'supplierrouteref' => '',
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => 0.000000,
                    'requested_route' => 0,
                    'requested_routetag' => '',
                    'deliverystatus2' => 'pending',
                    'migration_flag' => 'new',
                ]);

                $response = Http::withBasicAuth($nexmoApiKey, $nexmoApiSecret)
                    ->post('https://api.nexmo.com/v1/messages', [
                        'from' => $nexmoWhatsAppNumber,
                        'to' => $thenum,
                        'channel' => 'whatsapp',
                        'message_type' => 'text',
                        'text' => $message
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $messageId = $responseData['message_uuid'] ?? '';
                    $successCount++;
                    $messageIds[] = $messageId;

                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'sentstatus' => 'ok',
                            'sentstatustext' => 'WhatsApp message sent',
                            'suppliermsgref' => $messageId,
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                            'deliverystatus2' => 'Delivered'
                        ]);
                } else {
                    $failedCount++;
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'sentstatus' => 'fail',
                            'sentstatustext' => 'WhatsApp send failed',
                            'deliverystatus2' => 'Failed'
                        ]);
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('WhatsApp send error', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => $successCount > 0,
            'message' => $successCount > 0 
                ? "WhatsApp messages sent! {$successCount} delivered."
                : 'Failed to send WhatsApp messages',
            'data' => [
                'batch_id' => $batchId,
                'sent' => $successCount,
                'failed' => $failedCount,
                'message_ids' => $messageIds,
            ]
        ]);
    }

    /**
     * Helper: Extract country code from phone number
     */
    private function extractCountryCode($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        $countries = DB::table('country')
            ->select('dialcode', 'iso_code', 'id')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();

        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country;
            }
        }

        return DB::table('country')->where('dialcode', '44')->first();
    }

    /**
     * Helper: Calculate SMS parts
     */
    private function calculateSmsParts($message)
    {
        $length = mb_strlen($message, 'UTF-8');
        if ($length <= 160) {
            return 1;
        }
        return (int) ceil($length / 153);
    }

    /**
     * Helper: Validate phone number
     */
    private function isValidPhoneNumber($number)
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // UK pattern
        if (preg_match('/^44\d{10}$/', $number)) {
            return true;
        }
        
        // India pattern
        if (preg_match('/^91\d{10}$/', $number)) {
            return true;
        }
        
        // General international (10-15 digits)
        if (preg_match('/^\d{10,15}$/', $number)) {
            return true;
        }
        
        return false;
    }
}
