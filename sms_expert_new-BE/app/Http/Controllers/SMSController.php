<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Services\SMSService;
use App\Services\SmsSendingService;
use App\Services\Queue\SmsQueueService; // Add SMPP Queue Service
use App\Services\Queue\RabbitMQService; // Add RabbitMQ Service
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use App\Services\SinchService;
use App\Services\SMPP\SinchSmppService;
use App\Models\SmsShortcode;
use App\Models\ItaggInstance;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\UserRouteService;
use App\Services\SmsValidationService;
use Illuminate\Support\Facades\Schema;



class SMSController extends Controller
{
    protected $smsService;
    protected $smsQueueService; // Unified SMPP Queue Service (handles both Nexmo and Sinch)
    protected $rabbitMQService; // RabbitMQ Service
    protected $bulkThroughputService;
    protected $walletValidationService;
    protected $smsSendingService;
    protected $userRouteService; // OLD SYSTEM pricing service
    protected $smsValidationService; // OLD SYSTEM validation service
    public $userBigId;
    public $GRACE_PERIOD = 5;

    public function __construct(SMSService $smsService, UserRouteService $userRouteService, SmsValidationService $smsValidationService)
    {
        $this->smsService = $smsService;
        $this->userRouteService = $userRouteService; // OLD SYSTEM pricing service
        $this->smsValidationService = $smsValidationService; // OLD SYSTEM validation service
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();
        $this->smsSendingService = new SmsSendingService();

        // Initialize SMPP Queue Service (handles both Nexmo and Sinch via provider field)
        try {
            $this->smsQueueService = new SmsQueueService();
            $this->rabbitMQService = new RabbitMQService();
        } catch (\Exception $e) {
            Log::error('Failed to initialize SMPP services: ' . $e->getMessage());
            // Fallback to regular SMS service if SMPP fails
            $this->smsQueueService = null;
            $this->rabbitMQService = null;
        }
    }

    /**
     * Get the operator for a sender ID based on smsshortcodes and itagg_instance
     *
     * @param string $senderId The sender ID (from number)
     * @param string $userRef The user's bigid reference
     * @return string The operator name ('nexmo', 'mBlox', etc.) or 'nexmo' as default
     */
    private function getOperatorForSender(string $senderId, string $userRef): string
    {
        try {
            // First, find the shortcode by number
            $shortcode = SmsShortcode::where('number', $senderId)->first();

            if (!$shortcode) {
                Log::info("No shortcode found for sender: {$senderId}, using default nexmo");
                return 'nexmo';
            }

            // Check if this shortcode is assigned to the user via itagg_instance
            $instance = ItaggInstance::where('smsshortcodes_id', $shortcode->id)
                ->where('users_bigid', $userRef)
                ->first();

            if (!$instance) {
                Log::info("No itagg_instance found for shortcode {$shortcode->id} and user {$userRef}, using default nexmo");
                return 'nexmo';
            }

            // Return the operator from shortcode
            $operator = $shortcode->whichoperator ?? 'nexmo';
            Log::info("Found operator for sender {$senderId}: {$operator}");

            return strtolower($operator);
        } catch (\Exception $e) {
            Log::error("Error getting operator for sender {$senderId}: " . $e->getMessage());
            return 'nexmo';
        }
    }

    /**
     * Send SMS via Sinch SMPP directly (without sms_queue table)
     *
     * @param string $to Destination phone number
     * @param string $message SMS message content
     * @param string $from Sender ID
     * @param int $smsgLogId The smsg_log record ID for updating status
     * @param string|null $bigid The bigid reference for wallet deduction
     * @param string|null $scheduleDeliveryTime Scheduled delivery time (SMPP format)
     * @return array Result with success status and details
     */
    private function sendViaSinchSmpp(string $to, string $message, string $from, int $smsgLogId, ?string $bigid = null, ?string $scheduleDeliveryTime = null): array
    {
        try {
            $sinchSmpp = new SinchSmppService();
            $result = $sinchSmpp->sendSMS(
                $to,
                $message,
                $from,
                5, // priority
                null, // queueId not needed
                'ControlPanel',
                $bigid, // referenceId for wallet deduction
                $scheduleDeliveryTime
            );

            if ($result['success']) {
                // Update smsg_log with success
                DB::table('smsg_log')
                    ->where('id', $smsgLogId)
                    ->update([
                        'sentstatus' => 'sent',
                        'sentstatustext' => 'Sent via Sinch SMPP',
                        'suppliername' => 'Sinch SMPP',
                        'suppliermsgref' => $result['message_id'] ?? '',
                        'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                    ]);

                Log::info('SMS sent via Sinch SMPP', [
                    'to' => $to,
                    'from' => $from,
                    'message_id' => $result['message_id'] ?? ''
                ]);

                return [
                    'success' => true,
                    'message_id' => $result['message_id'] ?? '',
                    'provider' => 'sinch_smpp'
                ];
            } else {
                // Update smsg_log with failure
                DB::table('smsg_log')
                    ->where('id', $smsgLogId)
                    ->update([
                        'sentstatus' => 'fail',
                        'sentstatustext' => 'Sinch SMPP failed: ' . ($result['error'] ?? 'Unknown error'),
                        'suppliername' => 'Sinch SMPP',
                    ]);

                Log::error('Sinch SMPP send failed', [
                    'to' => $to,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Sinch SMPP exception', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            // Update smsg_log with exception
            DB::table('smsg_log')
                ->where('id', $smsgLogId)
                ->update([
                    'sentstatus' => 'fail',
                    'sentstatustext' => 'Sinch SMPP exception: ' . $e->getMessage(),
                    'suppliername' => 'Sinch SMPP',
                ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via Nexmo SMPP directly (without sms_queue table)
     *
     * @param string $to Destination phone number
     * @param string $message SMS message content
     * @param string $from Sender ID
     * @param int $smsgLogId The smsg_log record ID for updating status
     * @param string|null $bigid The bigid reference for wallet deduction
     * @param string|null $scheduleDeliveryTime Scheduled delivery time (SMPP format)
     * @return array Result with success status and details
     */
    private function sendViaNexmoSmpp(string $to, string $message, string $from, int $smsgLogId, ?string $bigid = null, ?string $scheduleDeliveryTime = null): array
    {
        try {
            $smppPool = new \App\Services\SMPP\SMPPPoolManager();
            $result = $smppPool->sendSMS(
                $to,
                $message,
                $from,
                5, // priority
                null, // queueId not needed
                'ControlPanel',
                $bigid, // referenceId for wallet deduction
                $scheduleDeliveryTime,
                $smsgLogId // thread the exact row id (needed for persistent-send + per-row DLR)
            );

            if ($result['success']) {
                // PERSISTENT MODE: the submit was only QUEUED. Leave sentstatus as
                // 'pending' — the transceiver daemon does the real submit and sets
                // sentstatus='ok' + suppliermsgref + deliveryreceipt1 (via
                // storeMessageIdMapping). Marking 'ok' here would trip the duplicate
                // guard and the message would never actually be submitted.
                if (!empty($result['queued'])) {
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update(['sentstatustext' => 'Queued for SMPP transceiver']);

                    Log::info('SMS queued for persistent SMPP transceiver', [
                        'to' => $to, 'from' => $from, 'smsg_log_id' => $smsgLogId,
                    ]);

                    return [
                        'success' => true,
                        'message_id' => '',
                        'provider' => 'nexmo_smpp',
                        'queued' => true,
                    ];
                }

                // Direct mode: the message was submitted synchronously — mark sent.
                DB::table('smsg_log')
                    ->where('id', $smsgLogId)
                    ->update([
                        'sentstatus' => 'ok',
                        // OLD SYSTEM parity: successful sends leave sentstatustext EMPTY (see
                        // SMPPService/ProcessScheduledSms). Only failures/blacklist populate it.
                        'sentstatustext' => '',
                        'suppliername' => 'Vonage SMPP',
                        'suppliermsgref' => $result['message_id'] ?? '',
                        'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                    ]);

                Log::info('SMS sent via Nexmo SMPP', [
                    'to' => $to,
                    'from' => $from,
                    'message_id' => $result['message_id'] ?? ''
                ]);

                return [
                    'success' => true,
                    'message_id' => $result['message_id'] ?? '',
                    'provider' => 'nexmo_smpp'
                ];
            } else {
                // Update smsg_log with failure
                DB::table('smsg_log')
                    ->where('id', $smsgLogId)
                    ->update([
                        'sentstatus' => 'fail',
                        'sentstatustext' => 'Nexmo SMPP failed: ' . ($result['error'] ?? 'Unknown error'),
                        'suppliername' => 'Vonage SMPP',
                    ]);

                Log::error('Nexmo SMPP send failed', [
                    'to' => $to,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Nexmo SMPP exception', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            // Update smsg_log with exception
            DB::table('smsg_log')
                ->where('id', $smsgLogId)
                ->update([
                    'sentstatus' => 'fail',
                    'sentstatustext' => 'Nexmo SMPP exception: ' . $e->getMessage(),
                    'suppliername' => 'Vonage SMPP',
                ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userInfo = Session::get('user_info');

        if (isset($userInfo['bigid'])) {
            $bigid = Session::get('user_info')['bigid'];
            $user = User::with('options')->where('bigid', $bigid)->first();
            $get_users = User::where('bigid', $bigid)->first();
            $whatsapp = 'no';
            if ($get_users) {
                $smsg_wallet = $get_users->smsg_wallet;
                $smsg_server1_sent = $get_users->smsg_server1_sent;
                $smsg_server2_sent = $get_users->smsg_server2_sent;
                $whatsapp = $get_users->whatsapp_enabled;

                $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;
            }
            // Set timezone to Europe/London
            $now = Carbon::now('Europe/London');

            // Get current date, hour, and rounded minute
            $current_date = $now->format('d/m/Y');
            $current_hour = $now->format('H');
            $current_minute = $now->format('i');
            $rounded_minute = floor($current_minute / 5) * 5;

            return view('customer.send_sms.index', compact('bigid', 'user', 'remaining_wallet', 'current_date', 'current_hour', 'rounded_minute', 'whatsapp'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    /**
     * Send Message using SMPP Queue System
     */

    private function extractCountryCode($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Served from the no-TTL country cache (TableCache) instead of loading all 229 country
        // rows on every call. countryForNumber does the longest-prefix match (max dialcode = 4).
        $cache = app(\App\Services\TableCache::class);
        $country = $cache->countryForNumber($phoneNumber);

        // Fallback: default to UK if nothing matches (from the same cached map).
        return $country ?: $cache->countries()->get('44');
    }
    private function calculateSmsParts($message): int
    {
        $length = mb_strlen($message, 'UTF-8'); // handles Unicode properly

        // For single SMS, max is 160 characters
        // For multi-part SMS, each part is 153 characters (7 chars used for headers)
        if ($length <= 160) {
            return 1;
        }

        // Multi-part SMS: 153 characters per part
        return (int) ceil($length / 153);
    }

    /**
     * Calculate SMS cost based on recipients and message length
     *
     * OLD SYSTEM LOGIC:
     * - Uses smsg_userroute table for user-specific pricing
     * - Falls back to default user rates if no user-specific rate found
     * - Route selection: 7002/7029 for UK Direct, 8002/8029 for International
     */
    public function calculateCost(Request $request)
    {
        try {
            $validated = $request->validate([
                'txtTo' => 'required',
                'messageContent' => 'required',
                'senderId' => 'nullable|string'
            ]);

            $userInfo = Session::get('user_info');
            if (!isset($userInfo['bigid'])) {
                return response()->json(['success' => false, 'error' => 'User not authenticated'], 401);
            }

            $user = User::where('bigid', $userInfo['bigid'])->first();
            if (!$user) {
                return response()->json(['success' => false, 'error' => 'User not found'], 404);
            }

            $userBigId = $userInfo['bigid'];
            $numbers = array_map('trim', explode(',', $validated['txtTo']));
            $message = $validated['messageContent'];
            $senderId = $validated['senderId'] ?? '';

            // Determine operator based on sender ID
            $operator = $this->getOperatorForSender($senderId, $userBigId);
            $isSinch = (stripos($operator, 'mblox') !== false || stripos($operator, 'sinch') !== false);
            $providerName = $isSinch ? 'Sinch' : 'Nexmo (Vonage)';

            $smsParts = $this->calculateSmsParts($message);
            $messageLength = mb_strlen($message, 'UTF-8');

            // Determine route based on provider
            // 7002 = Nexmo Direct UK, 8002 = Nexmo Global
            // Sinch routes: 3002, etc.
            $ukRoutenum = $isSinch ? 3002 : 7002;
            $intlRoutenum = $isSinch ? 8002 : 8002;

            // Determine operator for cost lookup
            $costOperator = $isSinch ? 'sinch' : 'vonage';

            $totalCost = 0;
            $countryStats = [];
            $invalidNumbers = [];

            foreach ($numbers as $number) {
                $clean = preg_replace('/[^0-9]/', '', $number);
                if (substr($clean, 0, 2) === '07') {
                    $clean = '44' . substr($clean, 1);
                }

                $countryInfo = $this->extractCountryCode($clean);
                if (!$countryInfo) {
                    $invalidNumbers[] = $number;
                    continue;
                }

                $dial = $countryInfo->dialcode;

                // Select route based on country (UK vs International)
                $routenum = ($dial === '44') ? $ukRoutenum : $intlRoutenum;

                // Get pricing from smsg_userroute + country cost table
                $pricing = $this->userRouteService->getPricingForPhoneNumber(
                    $userBigId,
                    $clean,
                    $routenum,
                    7,      // numbits (7-bit for standard text)
                    'alpha', // origtype
                    $costOperator  // Pass operator for country cost lookup
                );

                $ratePerSMS = $pricing['userprice'];
                $costForNumber = round($ratePerSMS * $smsParts, 4);
                $totalCost = round($totalCost + $costForNumber, 4);

                if (!isset($countryStats[$dial])) {
                    $countryStats[$dial] = [
                        'country' => $countryInfo->iso_code ?? 'Unknown',
                        'dialcode' => $dial,
                        'count' => 0,
                        'rate_per_sms' => $ratePerSMS,
                        'total_cost' => 0
                    ];
                }

                $countryStats[$dial]['count']++;
                $countryStats[$dial]['total_cost'] =
                    round($countryStats[$dial]['total_cost'] + $costForNumber, 4);
            }

            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

            // OLD SYSTEM: Include validation warnings in response
            $validationWarnings = [];

            // Validate Sender ID
            if (!empty($senderId)) {
                $senderValidation = $this->smsValidationService->validateSenderId($senderId);
                if (!$senderValidation['valid']) {
                    $validationWarnings[] = $senderValidation['error'];
                }
            }

            // Validate message length
            $lengthValidation = $this->smsValidationService->validateMessageLength($message);
            if (!$lengthValidation['valid']) {
                $validationWarnings[] = $lengthValidation['error'];
            }

            // Validate GSM characters
            $gsmValidation = $this->smsValidationService->validateGsmCharacters($message);
            if (!$gsmValidation['valid']) {
                $validationWarnings[] = $gsmValidation['error'];
            }

            // Check wallet balance
            if ($walletBalance < $totalCost) {
                $shortage = $totalCost - $walletBalance;
                $validationWarnings[] = "Wallet funds too low - you cannot afford to send these messages. Please top up your SMS wallet by at least £" . number_format($shortage, 2) . ".";
            }

            return response()->json([
                'success' => true,
                'provider' => $providerName,
                'sender_id' => $senderId,
                'message_info' => [
                    'length' => $messageLength,
                    'sms_parts' => $smsParts,
                    'max_length' => 1377,
                    'chars_per_part' => $smsParts > 1 ? 153 : 160
                ],
                'recipients' => [
                    'total' => count($numbers) - count($invalidNumbers),
                    'invalid' => count($invalidNumbers),
                    'invalid_numbers' => $invalidNumbers
                ],
                'cost_breakdown' => array_values($countryStats),
                'total_cost' => [
                    'amount' => $totalCost,
                    'formatted' => number_format($totalCost, 4, '.', '')
                ],
                'wallet' => [
                    'balance' => $walletBalance,
                    'formatted_balance' => number_format($walletBalance, 4, '.', ''),
                    'sufficient_funds' => $walletBalance >= $totalCost
                ],
                'validation' => [
                    'valid' => empty($validationWarnings),
                    'warnings' => $validationWarnings
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function calculateCost(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'txtTo' => 'required',
    //             'messageContent' => 'required'
    //         ]);

    //         $userInfo = Session::get('user_info');
    //         if (!isset($userInfo['bigid'])) {
    //             return response()->json(['success' => false, 'error' => 'User not authenticated'], 401);
    //         }

    //         $user = User::where('bigid', $userInfo['bigid'])->first();
    //         if (!$user) {
    //             return response()->json(['success' => false, 'error' => 'User not found'], 404);
    //         }

    //         $userMargin = DB::table('user_margin')
    //             ->where('user_id', $user->id)
    //             ->where('is_active', 1)
    //             ->first();

    //         $marginPercentage = $userMargin ? (float)$userMargin->margin_percentage : 0;

    //         $numbers = array_map('trim', explode(',', $validated['txtTo']));
    //         $message = $validated['messageContent'];

    //         $smsParts = $this->calculateSmsParts($message);
    //         $messageLength = mb_strlen($message, 'UTF-8');

    //         $totalCost = 0;
    //         $countryStats = [];
    //         $invalidNumbers = [];

    //         foreach ($numbers as $number) {

    //             $clean = preg_replace('/[^0-9]/', '', $number);
    //             if (substr($clean, 0, 2) === '07') {
    //                 $clean = '44' . substr($clean, 1);
    //             }

    //             $countryInfo = $this->extractCountryCode($clean);
    //             if (!$countryInfo) {
    //                 $invalidNumbers[] = $number;
    //                 continue;
    //             }

    //             $countryRate = DB::table('country')->where('id', $countryInfo->id)->first();

    //             // ===== PRICING LOGIC (FINAL) =====
    //             $baseCostGBP = 0.0400;

    //             if ($countryRate) {
    //                 if (!empty($countryRate->cost_price_gbp) && $countryRate->cost_price_gbp > 0) {
    //                     $baseCostGBP = (float)$countryRate->cost_price_gbp;
    //                 } elseif (!empty($countryRate->cost_per_sms) && $countryRate->cost_per_sms > 0) {
    //                     $baseCostGBP = (float)$countryRate->cost_per_sms;
    //                 }
    //             }

    //             // ✅ ROUND BASE FIRST
    //             $baseCostGBP = round($baseCostGBP, 2);

    //             if ($marginPercentage > 0) {
    //                 $marginAmount = round($baseCostGBP * ($marginPercentage / 100), 2);
    //                 $ratePerSMS = round($baseCostGBP + $marginAmount, 2);
    //             } else {
    //                 $ratePerSMS = round($baseCostGBP * 1.25, 2);
    //             }

    //             $costForNumber = round($ratePerSMS * $smsParts, 2);
    //             $totalCost = round($totalCost + $costForNumber, 2);
    //             // =================================

    //             $dial = $countryInfo->dialcode;

    //             if (!isset($countryStats[$dial])) {
    //                 $countryStats[$dial] = [
    //                     'country' => $countryInfo->iso_code ?? 'Unknown',
    //                     'dialcode' => $dial,
    //                     'count' => 0,
    //                     'rate_per_sms' => $ratePerSMS,
    //                     'total_cost' => 0
    //                 ];
    //             }

    //             $countryStats[$dial]['count']++;
    //             $countryStats[$dial]['total_cost'] =
    //                 round($countryStats[$dial]['total_cost'] + $costForNumber, 2);
    //         }

    //         $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

    //         return response()->json([
    //             'success' => true,
    //             'message_info' => [
    //                 'length' => $messageLength,
    //                 'sms_parts' => $smsParts
    //             ],
    //             'recipients' => [
    //                 'total' => count($numbers) - count($invalidNumbers),
    //                 'invalid' => count($invalidNumbers),
    //                 'invalid_numbers' => $invalidNumbers
    //             ],
    //             'cost_breakdown' => array_values($countryStats),
    //             'total_cost' => [
    //                 'amount' => $totalCost,
    //                 'formatted' => number_format($totalCost, 2)
    //             ],
    //             'wallet' => [
    //                 'balance' => $walletBalance,
    //                 'formatted_balance' => number_format($walletBalance, 2),
    //                 'sufficient_funds' => $walletBalance >= $totalCost
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    //prevoius code
    // public function calculateCost(Request $request)
    // {
    //     try {
    //         // Validate the input
    //         $validated = $request->validate([
    //             'txtTo' => 'required',
    //             'messageContent' => 'required'
    //         ]);

    //         // Get user info
    //         $userInfo = Session::get('user_info');
    //         if (!isset($userInfo['bigid'])) {
    //             return response()->json([
    //                 'success' => false,
    //                 'error' => 'User not authenticated'
    //             ], 401);
    //         }

    //         $userBigId = $userInfo['bigid'];
    //         $getUserId = User::where('bigid', $userBigId)->first();

    //         if (!$getUserId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'error' => 'User not found'
    //             ], 404);
    //         }

    //         // Get user's margin percentage from user_margin table
    //         $userMargin = DB::table('user_margin')
    //             ->where('user_id', $getUserId->id)
    //             ->where('is_active', 1)
    //             ->first();

    //         $marginPercentage = $userMargin ? (float)$userMargin->margin_percentage : 0;

    //         // Parse phone numbers
    //         $phoneNumbers = explode(',', $validated['txtTo']);
    //         $phoneNumbers = array_map('trim', $phoneNumbers);

    //         // Calculate message parts
    //         $message = $validated['messageContent'];
    //         $messageLength = mb_strlen($message, 'UTF-8');
    //         $smsParts = $this->calculateSmsParts($message);

    //         // Group numbers by country and calculate costs
    //         $costDetails = [];
    //         $totalCost = 0;
    //         $invalidNumbers = [];
    //         $countryStats = [];

    //         foreach ($phoneNumbers as $number) {
    //             // Clean the number
    //             $cleanNumber = preg_replace('/[^0-9+]/', '', $number);

    //             // Format UK numbers
    //             if (substr($cleanNumber, 0, 2) == '07') {
    //                 $cleanNumber = '44' . substr($cleanNumber, 1);
    //             }

    //             // Extract country code
    //             $countryInfo = $this->extractCountryCode($cleanNumber);

    //             if (!$countryInfo) {
    //                 $invalidNumbers[] = $number;
    //                 continue;
    //             }

    //             // Get country cost_price_gbp from country table
    //             $countryRate = DB::table('country')
    //                 ->where('id', $countryInfo->id)
    //                 ->first();

    //             // Get base cost from country.cost_price_gbp
    //             $baseCostGBP = 0.0400; // Default fallback
    //             if ($countryRate) {
    //                 // Priority: cost_price_gbp > cost_per_sms > default
    //                 if (isset($countryRate->cost_price_gbp) && $countryRate->cost_price_gbp > 0) {
    //                     $baseCostGBP = (float)$countryRate->cost_price_gbp;
    //                 } elseif (isset($countryRate->cost_per_sms) && $countryRate->cost_per_sms > 0) {
    //                     $baseCostGBP = (float)$countryRate->cost_per_sms;
    //                 }
    //             }

    //             // Calculate user rate: cost_price_gbp + (cost_price_gbp × margin_percentage / 100)
    //             if ($marginPercentage > 0) {
    //                 $marginAmount = $baseCostGBP * ($marginPercentage / 100);
    //                 $ratePerSMS = round($baseCostGBP + $marginAmount, 4);
    //             } else {
    //                 // Fallback to user's common_sms_rate or default markup (25%)
    //                 if ($getUserId->common_sms_rate && $getUserId->common_sms_rate > 0) {
    //                     $ratePerSMS = (float)$getUserId->common_sms_rate;
    //                 } else {
    //                     $ratePerSMS = round($baseCostGBP * 1.25, 4); // 25% default markup
    //                 }
    //             }

    //             // Calculate cost for this number
    //             $costForNumber = $ratePerSMS * $smsParts;
    //             $totalCost += $costForNumber;

    //             // Track country statistics
    //             $countryCode = $countryInfo->dialcode;
    //             if (!isset($countryStats[$countryCode])) {
    //                 $countryStats[$countryCode] = [
    //                     'country' => $countryInfo->iso_code ?? 'Unknown',
    //                     'dialcode' => $countryCode,
    //                     'count' => 0,
    //                     'rate_per_sms' => (float)$ratePerSMS,
    //                     'cost_per_number' => (float)$costForNumber,
    //                     'total_cost' => 0,
    //                     'base_cost_gbp' => (float)$baseCostGBP,
    //                     'margin_percentage' => $marginPercentage
    //                 ];
    //             }
    //             $countryStats[$countryCode]['count']++;
    //             $countryStats[$countryCode]['total_cost'] = (float)($countryStats[$countryCode]['total_cost'] + $costForNumber);
    //         }

    //         // Get wallet balance
    //         $walletBalance = $getUserId->smsg_wallet - $getUserId->smsg_server1_sent - $getUserId->smsg_server2_sent;

    //         // Check if user has sufficient balance
    //         $hasSufficientFunds = $walletBalance >= $totalCost;

    //         // Log the calculation for debugging
    //         Log::info('SMS Cost Calculation', [
    //             'user_id' => $getUserId->id,
    //             'user_bigid' => $userBigId,
    //             'margin_percentage' => $marginPercentage,
    //             'total_cost' => $totalCost,
    //             'wallet_balance' => $walletBalance,
    //             'country_stats' => $countryStats
    //         ]);

    //         // Format response - ensure all numeric values are properly typed
    //         return response()->json([
    //             'success' => true,
    //             'message_info' => [
    //                 'length' => $messageLength,
    //                 'sms_parts' => $smsParts,
    //                 'part_info' => $smsParts > 1 ? '(multi-messages are 153 characters each)' : '(single message up to 160 characters)'
    //             ],
    //             'recipients' => [
    //                 'total' => count($phoneNumbers) - count($invalidNumbers),
    //                 'invalid' => count($invalidNumbers),
    //                 'invalid_numbers' => $invalidNumbers
    //             ],
    //             'cost_breakdown' => array_values($countryStats),
    //             'total_cost' => [
    //                 'amount' => (float)$totalCost,
    //                 'formatted' => '£ ' . number_format($totalCost, 2)
    //             ],
    //             'wallet' => [
    //                 'balance' => (float)$walletBalance,
    //                 'formatted_balance' => '£ ' . number_format($walletBalance, 2),
    //                 'sufficient_funds' => $hasSufficientFunds,
    //                 'shortage' => !$hasSufficientFunds ? (float)($totalCost - $walletBalance) : 0,
    //                 'formatted_shortage' => !$hasSufficientFunds ? '£' . number_format($totalCost - $walletBalance, 2) : null
    //             ],
    //             'pricing_info' => [
    //                 'margin_percentage' => $marginPercentage,
    //                 'pricing_method' => $marginPercentage > 0 ? 'margin_based' : 'common_rate'
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error calculating SMS cost', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Failed to calculate cost: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    // load testing purpose numbes
    // public function sendMessage(Request $request)
    // {

    //     // echo now();exit;
    //     // Determine if this is scheduled send or immediate send
    //     $isScheduled = $request->has('send_type') && $request->input('send_type') === 'send_later';

    //     // Validate the input
    //     $validated = $request->validate([
    //         'txtTo' => [
    //             'required',
    //             function ($attribute, $value, $fail) {
    //                 $numbers = explode(',', $value);
    //                 // $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9})$/';
    //                 $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9}|01932\d{6})$/';
    //                 $indiaPattern = '/^\+?91\d{10}$/';

    //                 $invalidNumbers = [];

    //                 foreach ($numbers as $number) {
    //                     $number = trim($number);

    //                     if (!preg_match($ukPattern, $number) && !preg_match($indiaPattern, $number)) {
    //                         $invalidNumbers[] = $number;
    //                     }
    //                 }

    //                 if (!empty($invalidNumbers)) {
    //                     $fail("The following numbers are invalid: " . implode(', ', $invalidNumbers));
    //                 }
    //             },
    //         ],
    //         'messageContent' => 'required',
    //         'send_date' => $isScheduled ? 'required|date' : 'nullable|date',
    //         'send_hh' => $isScheduled ? 'required' : 'nullable',
    //         'send_mm' => $isScheduled ? 'required' : 'nullable',
    //     ]);

    //     // Get user info for bulk throughput validation
    //     $userInfo = Session::get('user_info');

    //     $userref = isset($userInfo['bigid']) ? $userInfo['bigid'] : 'system';


    //     // Get user ID from users table for blacklist check
    //     $getUserId = User::where('bigid', $userref)->first();
    //     if (!$getUserId) {
    //         return back()->with('error', 'User not found.');
    //     }

    //     // Parse and format phone numbers first
    //     $to = explode(',', $validated['txtTo']);
    //     $to = array_map(function ($number) {
    //         $number = preg_replace('/\D/', '', $number); // digits only
    //         if (substr($number, 0, 1) === '0') {
    //             return '44' . substr($number, 1);
    //         }
    //         return $number;
    //     }, $to);

    //     // Check for blacklisted numbers in itagg_outbound_blacklist table
    //     $blacklistedNumbers = [];
    //     $allowedNumbers = [];

    //     foreach ($to as $phoneNumber) {
    //         $isBlacklisted = DB::table('itagg_outbound_blacklist')
    //             ->where(function ($query) use ($userref, $phoneNumber) {
    //                 $query->where('users_bigid', $userref)
    //                     ->where('msisdn', $phoneNumber);
    //             })
    //             ->exists();

    //         if ($isBlacklisted) {
    //             $blacklistedNumbers[] = $phoneNumber;
    //         } else {
    //             $allowedNumbers[] = $phoneNumber;
    //         }
    //     }

    //     // If ALL numbers are blacklisted, return error and stop
    //     if (count($allowedNumbers) === 0) {
    //         $errorMessage = 'Numbers have been blocked and need to be unblocked before sending: ' . implode(', ', $blacklistedNumbers);
    //         return back()->with('error', $errorMessage);
    //     }

    //     // Update $to array to only include allowed numbers
    //     $to = $allowedNumbers;

    //     // Store blacklisted info for later display
    //     $hasBlockedNumbers = count($blacklistedNumbers) > 0;

    //     // Check bulk throughput limit before processing
    //     $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($userref);

    //     if (!$throughputCheck['allowed']) {
    //         Log::warning('SMS sending blocked due to throughput limit', [
    //             'user' => $userref,
    //             'reason' => $throughputCheck['reason'] ?? 'limit_exceeded',
    //             'current_tally' => $throughputCheck['current_tally'] ?? 0,
    //             'limit' => $throughputCheck['limit'] ?? 0
    //         ]);

    //         return back()->with('error', 'You have reached your daily SMS send limit of ' . ($throughputCheck['limit'] ?? 0) . ' messages. Please contact support to increase your limit.');
    //     }

    //     // Count the recipient numbers (already parsed above)
    //     $messageCount = count($to);

    //     // Check wallet balance before processing (pass phone numbers for rate calculation)
    //     $walletCheck = $this->walletValidationService->validateWalletBalance($userref, $messageCount, $to);

    //     if (!$walletCheck['has_funds']) {
    //         Log::warning('SMS sending blocked due to insufficient funds', [
    //             'user' => $userref,
    //             'reason' => $walletCheck['reason'] ?? 'insufficient_funds',
    //             'current_balance' => $walletCheck['current_balance'] ?? 0,
    //             'required_amount' => $walletCheck['required_amount'] ?? 0
    //         ]);

    //         $errorMessage = 'Insufficient wallet funds. ';
    //         $errorMessage .= 'Current balance: £' . number_format($walletCheck['current_balance'] ?? 0, 2) . '. ';
    //         $errorMessage .= 'Required: £' . number_format($walletCheck['required_amount'] ?? 0, 2) . '. ';
    //         $errorMessage .= 'Please top up your SMS wallet to continue sending messages.';

    //         return back()->with('error', $errorMessage);
    //     }

    //     $message = $validated['messageContent'];

    //     // Generate unique bigid
    //     $bigid = md5(uniqid(rand(), true));

    //     $from = $request->txtSenderSpecific ?? $request->txtSenderDefault ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME');
    //     $smsType = $request->messageTypes ?? 'sms';
    //     $numbits = 7;
    //     $datenow = Carbon::now('Europe/London')->format('YmdHis');
    //     $flag_check = $getUserId->migration_flag ?? 'old';
    //     // userref already set above from bulk throughput check
    //     $affiliateref = '0';
    //     $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

    //     // Determine send time and status based on send type
    //     if ($isScheduled && $request->has('send_date')) {
    //         // Scheduled send
    //         $sendAt = Carbon::createFromFormat(
    //             'Y-m-d H:i',
    //             $request->send_date . ' ' . $request->send_hh . ':' . $request->send_mm,
    //             'Europe/London'
    //         );

    //         // Validate scheduled time is in future
    //         if ($sendAt->isPast()) {
    //             return back()->with('error', 'Scheduled time must be in the future.');
    //         }

    //         $send = $sendAt->format('YmdHis');
    //         $scheduleDeliveryTime = $sendAt->toDateTimeString();
    //         $sentstatus = 'tomorrowonward';  // Standard status for scheduled messages
    //         $sentstatustext = 'Scheduled for delivery';
    //     } else {
    //         // Immediate send
    //         $send = $datenow;
    //         $scheduleDeliveryTime = Carbon::now('Europe/London')->toDateTimeString();
    //         $sentstatus = 'pending';
    //         $sentstatustext = $useSmppQueue ? 'Queued for SMPP delivery' : 'Pending';
    //     }
    //     // $thecountrycodes = ['44'];


    //     // Track results
    //     $successCount = 0;
    //     $failedCount = 0;
    //     $queuedMessages = [];

    //     // Check if message type is WhatsApp
    //     if ($smsType === 'whatsapp') {
    //         // Handle WhatsApp messaging via Nexmo API
    //         return $this->sendWhatsAppMessage($to, $message, $from, $bigid, $userref, $datenow);
    //     }

    //     // whitelist numbers
    //     $allowedQueueNumbers = array_map(
    //         'trim',
    //         explode(',', env('QUEUE_TEST_NUMBERS', ''))
    //     );


    //     // Check if SMPP Queue Service is available for regular SMS

    //     // Insert data into the smsg_log table and queue for SMPP
    //     foreach ($to as $i => $thenum) {
    //         try {
    //             $countryInfo = $this->extractCountryCode($thenum);
    //             $thecountrycodes = $countryInfo ? $countryInfo->dialcode : '44';

    //             $current_date = Carbon::now('Europe/London')->format('YmdHis');

    //             $dosendtime = $isScheduled ? $send : $current_date;

    //             // Calculate dosendtimeint as Unix timestamp (like old system)
    //             $dosendtimeint = mktime(
    //                 (int) substr($dosendtime, 8, 2),
    //                 (int) substr($dosendtime, 10, 2),
    //                 (int) substr($dosendtime, 12, 2),
    //                 (int) substr($dosendtime, 4, 2),
    //                 (int) substr($dosendtime, 6, 2),
    //                 (int) substr($dosendtime, 0, 4)
    //             );

    //             // Calculate daemon priority (like old system)
    //             $userDaemonPriority = $user->daemonpriority ?? 100;
    //             $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($to) > 500 ? 200 : $userDaemonPriority;
    //             $daemonId = $baseDaemonId + mt_rand(0, 39);

    //             // Insert into smsg_log for record keeping
    //             $smsgLogId = DB::table('smsg_log')->insertGetId([
    //                 'sms_type' => $smsType,
    //                 'initiator' => 'ControlPanel',
    //                 'bigid' => $bigid,
    //                 'mobnum' => $thenum,
    //                 'numparts' => $this->calculateSmsParts($message),
    //                 'text' => $message,
    //                 'originator' => $from,
    //                 'numbits' => $numbits,
    //                 'timesubmitted' => $current_date,
    //                 'userref' => $userref,
    //                 'affiliateref' => $affiliateref,
    //                 'dosendtime' => $dosendtime,
    //                 'dosendtimeint' => $dosendtimeint,
    //                 'dayofyear' => substr($dosendtime, 0, 8),
    //                 'timesent' => '00000000000000',
    //                 'sentstatus' => $sentstatus,
    //                 'sentstatustmp' => $sentstatus,
    //                 'sentstatustext' => $sentstatustext,
    //                 'suppliermsgref' => '',
    //                 'smsgdaemonid' => $daemonId,
    //                 'sendpriority' => $baseDaemonId,
    //                 'costprice' => 0.000000,
    //                 'userprice' => 0.000000,
    //                 'aggregator_dlrcode' => 0,
    //                 'aggregator_dlrmsg' => $isScheduled ? 'Scheduled' : ($useSmppQueue ? 'Queued' : 'Non Delivered'),
    //                 'campaignref' => '',
    //                 'binaryflags' => '',
    //                 'profit' => 0.000000,
    //                 'countrydialcode' => $thecountrycodes ?? '',
    //                 'suppliername' => $useSmppQueue ? 'Vonage SMPP' : '',
    //                 'supplierrouteref' => '',
    //                 'requested_route' => 0,
    //                 'requested_routetag' => '',
    //                 'deliverystatus2' => $isScheduled ? 'tomorrowonward' : 'pending',
    //                 'migration_flag' => $flag_check,
    //             ]);

    //             $shouldQueue = in_array($thenum, $allowedQueueNumbers, true);

    //             if ($shouldQueue) {

    //                 Log::info('SMS routing decision (Nexmo only)', [
    //                     'from' => $from,
    //                     'mobile' => $thenum
    //                 ]);

    //                 // Always send via Nexmo SMPP
    //                 $sendResult = $this->sendViaNexmoSmpp(
    //                     $thenum,
    //                     $message,
    //                     $from,
    //                     $smsgLogId,
    //                     $bigid,
    //                     null
    //                 );

    //                 if ($sendResult['success']) {
    //                     $successCount++;

    //                     Log::info('SMS sent successfully via Nexmo SMPP', [
    //                         'message_id' => $sendResult['message_id'] ?? '',
    //                         'mobile' => $thenum,
    //                         'bigid' => $bigid
    //                     ]);
    //                 } else {
    //                     $failedCount++;

    //                     Log::error('Failed to send SMS via Nexmo SMPP', [
    //                         'mobile' => $thenum,
    //                         'error' => $sendResult['error'] ?? 'Unknown error'
    //                     ]);
    //                 }
    //             } else {

    //                 // Not in whitelist or scheduled → skip
    //                 Log::warning('SMS skipped (not allowed or scheduled)', [
    //                     'mobile' => $thenum
    //                 ]);

    //                 $failedCount++;
    //             }
    //         } catch (\Exception $e) {
    //             $failedCount++;
    //             Log::error('Error processing SMS', [
    //                 'mobile' => $thenum,
    //                 'error' => $e->getMessage()
    //             ]);
    //         }
    //     }

    //     // Return response based on results
    //     if ($successCount > 0) {
    //         if ($isScheduled) {
    //             $message = "SMS scheduled successfully! {$successCount} message(s) will be sent at " . (isset($sendAt) ? $sendAt->format('Y-m-d H:i') : 'scheduled time');
    //         } else {
    //             $message = "SMS sent successfully! {$successCount} message(s) sent";
    //         }

    //         // Add info about blocked numbers if any
    //         if ($hasBlockedNumbers) {
    //             $message .= ". Note: The following number(s) were blocked by iTAGG and not sent: " . implode(', ', $blacklistedNumbers);
    //         }

    //         return back()->with('success', $message);
    //     } else {
    //         return back()->with('error', 'Failed to process SMS messages. Please try again.');
    //     }
    // }

    /**
     * OLD SYSTEM parity (smsg_2send.php:1005): log a per-user-blacklisted (STOP opt-out) recipient
     * as a smsg_log row marked sentstatus='fail' / 'Blacklisted number.' — inserted at submit,
     * never queued/sent, priced at 0 (never charged). OLD inserts the row at submit and the worker
     * marks it fail; the new API path does the same; this brings the dashboard in line so the
     * blocked attempt is visible in Sent SMS instead of being silently dropped.
     */
    private function insertBlacklistedFailRow($thenum, $bl, string $message, string $from, string $userref, string $smsType, string $affiliateref, string $dashChargeType): void
    {
        $bigid = md5(uniqid(rand(), true));
        $countryInfo = $this->extractCountryCode($thenum);
        $countryCode = $countryInfo ? $countryInfo->dialcode : '44';
        $now = Carbon::now('Europe/London')->format('YmdHis');
        $dosendtimeint = mktime(
            (int) substr($now, 8, 2), (int) substr($now, 10, 2), (int) substr($now, 12, 2),
            (int) substr($now, 4, 2), (int) substr($now, 6, 2), (int) substr($now, 0, 4)
        );

        $upstreamError = 'Message actively blocked by iTAGG system: This number is blacklisted for '
            . 'this user via text message [' . ($bl->stop_or_stopall ?? 'STOP') . '] sent in at timestamp '
            . ($bl->date_blocked ?? '');

        DB::table('smsg_log')->insert([
            'sms_type' => $smsType,
            'initiator' => 'ControlPanel',
            'bigid' => $bigid,
            'mobnum' => $thenum,
            'numparts' => $this->calculateSmsParts($message),
            'text' => $message,
            'originator' => $from,
            'numbits' => 7,
            'timesubmitted' => $now,
            'userref' => $userref,
            'affiliateref' => $affiliateref,
            'dosendtime' => $now,
            'dosendtimeint' => $dosendtimeint,
            'dayofyear' => substr($now, 0, 8),
            'timesent' => $now, // OLD sets timesent when marking the row fail
            'sentstatus' => 'fail',
            'sentstatustmp' => 'fail',
            'sentstatustext' => 'Blacklisted number.',
            'upstream_errormessage' => $upstreamError,
            'suppliermsgref' => '',
            'costprice' => 0.000000,   // never priced / charged (OLD parity)
            'userprice' => 0.000000,
            'profit' => 0.000000,
            'aggregator_dlrcode' => 0,
            'aggregator_dlrmsg' => 'Blacklisted',
            'campaignref' => '',
            'binaryflags' => '',
            'countrydialcode' => $countryCode,
            'ofcomnetid' => app(\App\Services\TableCache::class)->ofcomNetId($thenum),
            'suppliername' => '',
            'supplierrouteref' => '',
            'requested_route' => 0,
            'requested_routetag' => '',
            'chargetype' => $dashChargeType,
            // OLD SYSTEM parity: a blacklisted row is never sent, so deliverystatus2 stays EMPTY
            // (OLD's insert leaves it at the '' default and the blacklist UPDATE never sets it).
            // NOT 'pending' — that would falsely mark it awaiting delivery / re-processable.
            'deliverystatus2' => '',
            'migration_flag' => 'new',
        ]);
    }

    //Working code
    public function sendMessage(Request $request)
    {

        // echo now();exit;
        // Determine if this is scheduled send or immediate send
        $isScheduled = $request->has('send_type') && $request->input('send_type') === 'send_later';

        // Validate the input
        $validated = $request->validate([
            'txtTo' => [
                'required',
                function ($attribute, $value, $fail) {
                    $numbers = explode(',', $value);
                    // $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9})$/';
                    $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9}|01932\d{6})$/';
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

        // OLD SYSTEM (smssend.inc:1057-1066): user's per-route chargetype (pps/ppd). Fetch ONCE
        // (constant for all recipients in this send) and reuse in each smsg_log insert below.
        $dashChargeType = DB::table('users')->where('bigid', $userref)->value('chargetype1') ?: 'pps';


        // Get user ID from users table for blacklist check
        $getUserId = User::where('bigid', $userref)->first();
        if (!$getUserId) {
            return back()->withInput()->with('error', 'User not found.');
        }

        // Parse and format phone numbers first
        $to = explode(',', $validated['txtTo']);
        $to = array_map(function ($number) {
            $number = preg_replace('/\D/', '', $number); // digits only
            if (substr($number, 0, 1) === '0') {
                return '44' . substr($number, 1);
            }
            return $number;
        }, $to);

        // Message + sender + GSM validation FIRST — a bad character rejects the WHOLE send
        // (matches OLD's client validation) before anything is logged.
        $message = $validated['messageContent'];
        $from = $request->txtSenderSpecific ?? $request->txtSenderDefault ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME');

        // OLD SYSTEM GSM validation (cp2_sendsms.inc): reject non-GSM characters (^ { } | ~ €,
        // smart quotes, etc.) server-side too — in case the client JS check was bypassed.
        $invalidCharPos = \App\Helpers\GsmCharacterConverter::firstInvalidCharPosition($message);
        if ($invalidCharPos !== -1) {
            $badChar = mb_substr($message, $invalidCharPos - 1, 1);
            return back()->withInput()->with('error',
                "The message contains an invalid character: \"{$badChar}\" at position {$invalidCharPos}. "
                . 'This is usually a Microsoft Word character or an Apple apostrophe, and will cause the message '
                . 'to fail if sent to the network. Please delete the character and re-type it.');
        }

        // Defaults used when logging a blacklisted recipient (same as the main insert below).
        $smsTypeForLog = $request->messageTypes ?? 'sms';
        $affiliaterefForLog = '0';

        // Check for blacklisted numbers in itagg_outbound_blacklist table. OLD SYSTEM parity
        // (smsg_2send.php:1005): a per-user-blacklisted (STOP opt-out) recipient is STILL logged —
        // a smsg_log row is inserted and marked sentstatus='fail' / 'Blacklisted number.' (never
        // charged, never sent). Only allowed numbers go on to be sent.
        $blacklistedNumbers = [];
        $allowedNumbers = [];
        foreach ($to as $phoneNumber) {
            $bl = DB::table('itagg_outbound_blacklist')
                ->where('users_bigid', $userref)
                ->where('msisdn', $phoneNumber)
                ->first();

            if ($bl) {
                $blacklistedNumbers[] = $phoneNumber;
                $this->insertBlacklistedFailRow($phoneNumber, $bl, $message, $from, $userref, $smsTypeForLog, $affiliaterefForLog, $dashChargeType);
            } else {
                $allowedNumbers[] = $phoneNumber;
            }
        }

        // If ALL numbers are blacklisted, they are already logged as failed — nothing left to send.
        if (count($allowedNumbers) === 0) {
            return back()->withInput()->with('error',
                'All recipient numbers are blocked (opted out via STOP) and were logged as failed. '
                . 'Blocked: ' . implode(', ', $blacklistedNumbers));
        }

        // Only allowed numbers proceed to the actual send.
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

            // OLD SYSTEM style error message
            if ($throughputCheck['reason'] === 'throughput_not_configured' || ($throughputCheck['limit'] ?? 0) == 0) {
                $errorMessage = 'There was an error while submitting: throughput exceeded. Your Daily Bulk SMS Limit is not configured. Please contact support.';
            } else {
                $errorMessage = 'There was an error while submitting: throughput exceeded. You have reached your daily SMS send limit of ' . ($throughputCheck['limit'] ?? 0) . ' messages. Please contact support to increase your limit.';
            }

            return back()->withInput()->with('error', $errorMessage);
        }

        // Count the recipient numbers (already parsed above)
        $messageCount = count($to);

        // OLD SYSTEM: Calculate total cost using smsg_userroute pricing BEFORE wallet validation
        $numbits = 7;
        $numParts = $this->calculateSmsParts($message);
        $operator = $this->getOperatorForSender($from, $userref);
        $isSinch = (stripos($operator, 'mblox') !== false || stripos($operator, 'sinch') !== false);
        $ukRoutenum = $isSinch ? 3002 : 7002;
        $intlRoutenum = $isSinch ? 8002 : 8002;

        // Determine operator for cost lookup
        $costOperator = $isSinch ? 'sinch' : 'vonage';

        $totalCostOldSystem = 0;
        foreach ($to as $thenum) {
            $countryInfo = $this->extractCountryCode($thenum);
            $thecountrycode = $countryInfo ? $countryInfo->dialcode : '44';
            $routenum = ($thecountrycode === '44') ? $ukRoutenum : $intlRoutenum;

            $pricing = $this->userRouteService->getPricingForPhoneNumber(
                $userref,
                $thenum,
                $routenum,
                $numbits,
                'alpha',
                $costOperator  // Pass operator for country cost lookup
            );
            $totalCostOldSystem += round($pricing['userprice'] * $numParts, 6);
        }

        // Get wallet balance
        $walletBalance = $getUserId->smsg_wallet - $getUserId->smsg_server1_sent - $getUserId->smsg_server2_sent;

        // OLD SYSTEM: Check wallet balance using OLD SYSTEM pricing
        if ($walletBalance < $totalCostOldSystem) {
            Log::warning('SMS sending blocked due to insufficient funds (OLD SYSTEM)', [
                'user' => $userref,
                'reason' => 'insufficient_funds',
                'current_balance' => $walletBalance,
                'required_amount' => $totalCostOldSystem
            ]);

            // OLD SYSTEM style error message: "Please top up your SMS wallet by at least £X"
            $shortage = $totalCostOldSystem - $walletBalance;
            // Convert to pence for display if less than £1
            if ($shortage < 1) {
                $shortageDisplay = number_format($shortage * 100, 1) . 'p';
            } else {
                $shortageDisplay = '£' . number_format($shortage, 2);
            }
            $errorMessage = 'Wallet funds too low - you cannot afford to send these messages. ';
            $errorMessage .= 'Please top up your SMS wallet by at least ' . $shortageDisplay . '.';

            return back()->withInput()->with('error', $errorMessage);
        }

        // OLD SYSTEM: Validate Sender ID (like cp2_sendsms.inc validateSenderId)
        $senderValidation = $this->smsValidationService->validateSenderId($from);
        if (!$senderValidation['valid']) {
            return back()->withInput()->with('error', $senderValidation['error']);
        }

        // OLD SYSTEM: Validate message length (max 1377 chars = 9 x 153)
        $lengthValidation = $this->smsValidationService->validateMessageLength($message);
        if (!$lengthValidation['valid']) {
            return back()->withInput()->with('error', $lengthValidation['error']);
        }

        // OLD SYSTEM: Validate GSM characters (like cp2_sendsms.inc charPositionOrMinusOne)
        $gsmValidation = $this->smsValidationService->validateGsmCharacters($message);
        if (!$gsmValidation['valid']) {
            return back()->withInput()->with('error', $gsmValidation['error']);
        }

        // NOTE: $bigid is generated PER RECIPIENT inside the foreach loop
        // below — NOT once per request. Using one bigid for all recipients
        // caused SMPPService::sendSMS's duplicate-prevention check (which
        // queries smsg_log by bigid) to short-circuit every recipient after
        // the first, returning success: true with message_id: 0 and no
        // actual submit_sm. See log line "Message already sent, preventing
        // duplicate".
        $smsType = $request->messageTypes ?? 'sms';
        // $numbits already set above in wallet validation
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        // New-system sends are always tagged 'new' so the new DLR/delivery pipeline
        // processes them (DeliveryStatusService skips migration_flag != 'new').
        $flag_check = 'new';
        // userref already set above from bulk throughput check
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
                return back()->withInput()->with('error', 'Scheduled time must be in the future.');
            }

            $send = $sendAt->format('YmdHis');
            $scheduleDeliveryTime = $sendAt->toDateTimeString();
            $sentstatus = 'tomorrowonward';  // Standard status for scheduled messages
            $sentstatustext = 'Scheduled for delivery';
        } else {
            // Immediate send
            $send = $datenow;
            $scheduleDeliveryTime = Carbon::now('Europe/London')->toDateTimeString();
            $sentstatus = 'pending';
            $sentstatustext = $useSmppQueue ? 'Queued for SMPP delivery' : 'Pending';
        }
        // $thecountrycodes = ['44'];


        // Track results
        $successCount = 0;
        $failedCount = 0;
        $queuedMessages = [];

        // Check if message type is WhatsApp
        if ($smsType === 'whatsapp') {
            // Handle WhatsApp messaging via Nexmo API
            return $this->sendWhatsAppMessage($to, $message, $from, $bigid, $userref, $datenow);
        }

        // Check if SMPP Queue Service is available for regular SMS

        // Calculate daemon priority (like old system)
        $userDaemonPriority = $getUserId->daemonpriority ?? 100;
        $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($to) > 500 ? 200 : $userDaemonPriority;

        // Resolve the DLR callback URL ONCE for the whole send — it depends only on the account
        // (useroption default), not the recipient. Hoisted out of the per-recipient loop below so
        // a multi-recipient send does a single useroption lookup instead of one per number.
        $dashDreceiptUrl = $this->smsSendingService->getDreceiptUrl('', $userref);

        // Insert data into the smsg_log table and queue for SMPP
        foreach ($to as $i => $thenum) {
            try {
                // One bigid per recipient = one row in smsg_log. Matches
                // OLD SYSTEM convention and bypasses SMPPService's dedup.
                $bigid = md5(uniqid(rand(), true));

                $countryInfo = $this->extractCountryCode($thenum);
                $thecountrycodes = $countryInfo ? $countryInfo->dialcode : '44';

                $current_date = Carbon::now('Europe/London')->format('YmdHis');

                $dosendtime = $isScheduled ? $send : $current_date;

                // Calculate dosendtimeint as Unix timestamp (like old system)
                $dosendtimeint = mktime(
                    (int) substr($dosendtime, 8, 2),
                    (int) substr($dosendtime, 10, 2),
                    (int) substr($dosendtime, 12, 2),
                    (int) substr($dosendtime, 4, 2),
                    (int) substr($dosendtime, 6, 2),
                    (int) substr($dosendtime, 0, 4)
                );

                // Calculate daemon ID with random offset (like old system - 40 parallel daemons)
                $daemonId = $baseDaemonId + mt_rand(0, 39);

                // Calculate pricing using smsg_userroute table and country cost table
                $numParts = $this->calculateSmsParts($message);
                $operator = $this->getOperatorForSender($from, $userref);
                $isSinch = (stripos($operator, 'mblox') !== false || stripos($operator, 'sinch') !== false);

                // Determine operator for cost lookup (vonage or sinch)
                $costOperator = $isSinch ? 'sinch' : 'vonage';

                // Select route based on country and provider
                $ukRoutenum = $isSinch ? 3002 : 7002;
                $intlRoutenum = $isSinch ? 8002 : 8002;
                $routenum = ($thecountrycodes === '44') ? $ukRoutenum : $intlRoutenum;

                // Get pricing from smsg_userroute + country table (use $userref which is user's bigid)
                $pricing = $this->userRouteService->getPricingForPhoneNumber(
                    $userref,
                    $thenum,
                    $routenum,
                    $numbits,
                    'alpha',
                    $costOperator  // Pass operator for country cost lookup
                );

                $userprice = round($pricing['userprice'] * $numParts, 6);
                $costprice = round($pricing['costprice'] * $numParts, 6);
                $profit = round($userprice - $costprice, 6);
                $suppliername = $pricing['suppliername'];

                // Insert into smsg_log for record keeping
                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => $smsType,
                    'initiator' => 'ControlPanel',
                    'bigid' => $bigid,
                    'mobnum' => $thenum,
                    'numparts' => $numParts,
                    'text' => $message,
                    'originator' => $from,
                    'numbits' => $numbits,
                    'timesubmitted' => $current_date,
                    'userref' => $userref,
                    'affiliateref' => $affiliateref,
                    'dosendtime' => $dosendtime,
                    'dosendtimeint' => $dosendtimeint,
                    'dayofyear' => substr($dosendtime, 0, 8),
                    'timesent' => '00000000000000',
                    'sentstatus' => $sentstatus,
                    'sentstatustmp' => $sentstatus,
                    'sentstatustext' => $sentstatustext,
                    'suppliermsgref' => '',
                    'smsgdaemonid' => $daemonId,
                    'sendpriority' => $baseDaemonId,
                    'costprice' => $costprice,
                    'userprice' => $userprice,
                    'aggregator_dlrcode' => 0,
                    'aggregator_dlrmsg' => $isScheduled ? 'Scheduled' : ($useSmppQueue ? 'Queued' : 'Non Delivered'),
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => $profit,
                    'countrydialcode' => $thecountrycodes ?? '',
                    // OLD SYSTEM (smssend.inc:930/1140): ofcomnetid from the Ofcom range lookup on
                    // the mobile number (cached per prefix).
                    'ofcomnetid' => app(\App\Services\TableCache::class)->ofcomNetId($thenum),
                    'suppliername' => $suppliername,
                    'supplierrouteref' => '',
                    'requested_route' => $routenum,
                    // OLD SYSTEM (smssend.inc:1150): requested route token. The dashboard derives
                    // the route from the sender/country (no letter), so store the resolved route.
                    'requested_routetag' => $routenum,
                    // OLD SYSTEM (smssend.inc:1148): user's per-route chargetype (pps/ppd).
                    'chargetype' => $dashChargeType,
                    // OLD SYSTEM parity: scheduled rows carry sentstatus='tomorrowonward'; deliverystatus2
                    // is the DLR column and must stay EMPTY until the message actually sends.
                    'deliverystatus2' => $isScheduled ? '' : 'pending',
                    'migration_flag' => $flag_check,
                    // OLD SYSTEM (smssend.inc:951-963/1184): DLR callback URL. The dashboard has no
                    // per-message field, so fall back to the account default (useroption.dreceipt_push_url)
                    // exactly like the API path — otherwise control-panel sends never fire callbacks.
                    // Resolved once above the loop (account-level), not per recipient.
                    'dreceipt_url' => $dashDreceiptUrl,
                ]);

                // Send SMS directly via SMPP (no sms_queue table used)
                // ONLY send immediately if NOT scheduled
                if (!$isScheduled && $useSmppQueue) {
                    // Determine which operator to use based on sender ID
                    $operator = $this->getOperatorForSender($from, $userref);
                    $provider = (str_contains($operator, 'mblox') || str_contains($operator, 'sinch')) ? 'sinch' : 'nexmo';

                    // SMS_ASYNC_PUBLISH=true -> publish to RabbitMQ (sms.outbound) and return
                    // immediately. The ProcessSmsQueue worker owns the persistent SMPP bind and
                    // does the actual send, so the dashboard request is NOT blocked on a live
                    // SMPP connect/submit (which is what made it slow). This mirrors the API /
                    // SmsSendingService async path. When false, falls back to the legacy
                    // synchronous direct send below.
                    $asyncPublish = filter_var(env('SMS_ASYNC_PUBLISH', false), FILTER_VALIDATE_BOOLEAN);

                    if ($asyncPublish && $this->smsQueueService !== null) {
                        $queueResult = $this->smsQueueService->enqueueSms([
                            'user_ref'      => $userref,
                            'mobile_number' => $thenum,
                            'message'       => $message,
                            'sender_id'     => $from,
                            'priority'      => 5,
                            'reference_id'  => $bigid,
                            'provider'      => $provider,
                            'metadata'      => [
                                'smsg_log_id' => $smsgLogId,
                                'bigid'       => $bigid,
                                'source'      => 'dashboard',
                                'provider'    => $provider,
                            ],
                        ]);

                        if (!empty($queueResult['success'])) {
                            $successCount++;
                            Log::info('SMS queued to sms.outbound (dashboard async)', [
                                'queue_id' => $queueResult['queue_id'] ?? '',
                                'mobile'   => $thenum,
                                'bigid'    => $bigid,
                                'provider' => $provider,
                            ]);
                        } else {
                            $failedCount++;
                            DB::table('smsg_log')->where('id', $smsgLogId)->update([
                                'sentstatus'     => 'fail',
                                'sentstatustext' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown'),
                            ]);
                            Log::error('Failed to queue SMS to sms.outbound (dashboard)', [
                                'mobile' => $thenum,
                                'error'  => $queueResult['error'] ?? 'Unknown error',
                            ]);
                        }
                    } else {
                        Log::info('SMS routing decision (direct SMPP)', [
                            'from' => $from,
                            'operator' => $operator,
                            'mobile' => $thenum
                        ]);

                        // Route based on operator - send directly via SMPP (synchronous fallback)
                        // Check if operator contains 'mblox' or 'sinch' (case insensitive)
                        // Examples: mBlox/Vodafone, mBlox/O2, mblox, sinch
                        if (str_contains($operator, 'mblox') || str_contains($operator, 'sinch')) {
                            // Send directly via Sinch SMPP (no sms_queue table)
                            $sendResult = $this->sendViaSinchSmpp(
                                $thenum,
                                $message,
                                $from,
                                $smsgLogId,
                                $bigid,
                                null // no schedule for immediate
                            );

                            if ($sendResult['success']) {
                                $successCount++;
                                Log::info('SMS sent successfully via Sinch SMPP (direct)', [
                                    'message_id' => $sendResult['message_id'] ?? '',
                                    'mobile' => $thenum,
                                    'bigid' => $bigid,
                                    'operator' => $operator
                                ]);
                            } else {
                                $failedCount++;
                                Log::error('Failed to send SMS via Sinch SMPP', [
                                    'mobile' => $thenum,
                                    'error' => $sendResult['error'] ?? 'Unknown error'
                                ]);
                            }
                        } else {
                            // Default: Send directly via Nexmo SMPP (no sms_queue table)
                            $sendResult = $this->sendViaNexmoSmpp(
                                $thenum,
                                $message,
                                $from,
                                $smsgLogId,
                                $bigid,
                                null // no schedule for immediate
                            );

                            if ($sendResult['success']) {
                                $successCount++;
                                Log::info('SMS sent successfully via Nexmo SMPP (direct)', [
                                    'message_id' => $sendResult['message_id'] ?? '',
                                    'mobile' => $thenum,
                                    'bigid' => $bigid
                                ]);
                            } else {
                                $failedCount++;
                                Log::error('Failed to send SMS via Nexmo SMPP', [
                                    'mobile' => $thenum,
                                    'error' => $sendResult['error'] ?? 'Unknown error'
                                ]);
                            }
                        }
                    }
                } else if ($isScheduled) {
                    // Handle scheduled SMS - store in smsg_log with deliverystatus2='tomorrowonward'
                    // The scheduler cron will pick these up and send at the scheduled time
                    // No sms_queue table needed - smsg_log tracks the schedule
                    $operator = $this->getOperatorForSender($from, $userref);
                    $provider = (str_contains($operator, 'mblox') || str_contains($operator, 'sinch')) ? 'sinch' : 'nexmo';

                    // Update smsg_log with provider info for scheduled pickup
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'suppliername' => ($provider === 'sinch') ? 'Sinch SMPP' : 'Vonage SMPP',
                            'aggregator_dlrmsg' => 'Scheduled - ' . $operator,
                        ]);

                    $successCount++;
                    Log::info('SMS scheduled for later delivery (smsg_log based)', [
                        'mobile' => $thenum,
                        'bigid' => $bigid,
                        'scheduled_at' => $scheduleDeliveryTime,
                        'operator' => $operator,
                        'provider' => $provider
                    ]);
                } else {
                    // SMPP not available
                    Log::info('SMS logged (SMPP not available)', ['mobile' => $thenum]);
                    $successCount++;
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

    public function sendSinch()
    {
        $sinch = new SinchService();

        $response = $sinch->sendSMS('917094514970', 'Hello from Sinch Laravel Integration!');

        return response()->json($response);
    }

    /**
     * Schedule SMS for later sending via SMPP
     */
    public function scheduleSendMessage(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'txtTo' => [
                'required',
                function ($attribute, $value, $fail) {
                    $numbers = explode(',', $value);
                    // $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9})$/';
                    $ukPattern = '/^(?:\+44\d{10}|44\d{10}|07\d{9}|01932\d{6})$/';
                    $indiaPattern = '/^91\d{10}$/';

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
            'send_date' => 'required|date',
            'send_hh' => 'required',
            'send_mm' => 'required',
        ]);

        $to = explode(',', $validated['txtTo']);

        $to = array_map(function ($number) {
            $number = preg_replace('/\D/', '', $number);
            if (substr($number, 0, 1) === '0') {
                return '44' . substr($number, 1);
            }
            return $number;
        }, $to);

        $message = $validated['messageContent'];

        // OLD SYSTEM GSM validation (cp2_sendsms.inc): reject non-GSM characters server-side.
        $invalidCharPos = \App\Helpers\GsmCharacterConverter::firstInvalidCharPosition($message);
        if ($invalidCharPos !== -1) {
            $badChar = mb_substr($message, $invalidCharPos - 1, 1);
            return back()->withInput()->with('error',
                "The message contains an invalid character: \"{$badChar}\" at position {$invalidCharPos}. "
                . 'This is usually a Microsoft Word character or an Apple apostrophe, and will cause the message '
                . 'to fail if sent to the network. Please delete the character and re-type it.');
        }

        // NOTE: $bigid is generated PER RECIPIENT inside the foreach loop
        // below — see comment in sendMessage() for why a single per-request
        // bigid silently breaks multi-recipient sends.

        $from = env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME');
        $numbits = 7;
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $userInfo = Session::get('user_info');
        $userref = isset($userInfo['bigid']) ? $userInfo['bigid'] : 'system';

        // Combine date and time into a single DateTime string
        $sendAt = \Carbon\Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->send_date . ' ' . $request->send_hh . ':' . $request->send_mm
        );

        $affiliateref = '0';
        $send = $sendAt->format('YmdHis');
        $thecountrycodes = ['91'];

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($send, 8, 2),
            (int) substr($send, 10, 2),
            (int) substr($send, 12, 2),
            (int) substr($send, 4, 2),
            (int) substr($send, 6, 2),
            (int) substr($send, 0, 4)
        );

        // Calculate daemon priority (like old system)
        $userDaemonPriority = $user->daemonpriority ?? 100;
        $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($to) > 500 ? 200 : $userDaemonPriority;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        // Check if SMPP Queue Service is available
        $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

        $successCount = 0;
        $failedCount = 0;

        // Resolve the DLR callback URL ONCE for the whole send — account-level (useroption default),
        // not per recipient. Hoisted out of the loop so a multi-recipient schedule does a single
        // useroption lookup instead of one per number.
        $schedDreceiptUrl = $this->smsSendingService->getDreceiptUrl('', $userref);

        // Insert data into the smsg_log table and queue for scheduled SMPP sending
        foreach ($to as $i => $thenum) {
            try {
                // One bigid per recipient = one smsg_log row, matches OLD SYSTEM.
                $bigid = md5(uniqid(rand(), true));

                // Calculate pricing using smsg_userroute table and country cost table
                $countryInfo = $this->extractCountryCode($thenum);
                $countryCode = $countryInfo ? $countryInfo->dialcode : '44';
                $numParts = $this->calculateSmsParts($message);
                $operator = $this->getOperatorForSender($from, $userref);
                $isSinch = (stripos($operator, 'mblox') !== false || stripos($operator, 'sinch') !== false);

                // Determine operator for cost lookup (vonage or sinch)
                $costOperator = $isSinch ? 'sinch' : 'vonage';

                $ukRoutenum = $isSinch ? 3002 : 7002;
                $intlRoutenum = $isSinch ? 8002 : 8002;
                $routenum = ($countryCode === '44') ? $ukRoutenum : $intlRoutenum;

                $pricing = $this->userRouteService->getPricingForPhoneNumber(
                    $userref,
                    $thenum,
                    $routenum,
                    $numbits,
                    'alpha',
                    $costOperator  // Pass operator for country cost lookup
                );

                $userprice = round($pricing['userprice'] * $numParts, 6);
                $costprice = round($pricing['costprice'] * $numParts, 6);
                $profit = round($userprice - $costprice, 6);
                $suppliername = $pricing['suppliername'];

                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'bigid' => $bigid,
                    'mobnum' => $thenum,
                    'numparts' => $numParts,
                    'text' => $message,
                    'originator' => $from,
                    'numbits' => $numbits,
                    'timesubmitted' => $datenow,
                    'userref' => $userref,
                    'affiliateref' => $affiliateref,
                    'dosendtime' => $send,
                    'dosendtimeint' => $dosendtimeint,
                    'dayofyear' => substr($send, 0, 8),
                    'timesent' => '00000000000000',
                    'sentstatus' => 'tomorrowonward',
                    'sentstatustmp' => 'tomorrowonward',
                    'sentstatustext' => $useSmppQueue ? 'Scheduled for SMPP delivery' : 'Scheduled',
                    'suppliermsgref' => '',
                    'smsgdaemonid' => $daemonId,
                    'sendpriority' => $baseDaemonId,
                    'costprice' => $costprice,
                    'userprice' => $userprice,
                    'aggregator_dlrcode' => 0,
                    'aggregator_dlrmsg' => 'Scheduled',
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => $profit,
                    'countrydialcode' => $countryCode,
                    'suppliername' => $suppliername,
                    'supplierrouteref' => '',
                    'requested_route' => $routenum,
                    'requested_routetag' => '',
                    // OLD parity: scheduled = sentstatus 'tomorrowonward', deliverystatus2 EMPTY (DLR column).
                    'deliverystatus2' => '',
                    'migration_flag' => 'new',
                    // OLD SYSTEM (smssend.inc:951-963/1184): DLR callback URL from the account default
                    // (useroption.dreceipt_push_url) so scheduled control-panel sends fire callbacks too.
                    // Resolved once above the loop (account-level), not per recipient.
                    'dreceipt_url' => $schedDreceiptUrl,
                ]);

                // Scheduled SMS is tracked via smsg_log with deliverystatus2='tomorrowonward'
                // The scheduler cron (ProcessScheduledSms) will pick these up and send via SMPP
                // No sms_queue table needed
                $operator = $this->getOperatorForSender($from, $userref);
                $provider = (str_contains($operator, 'mblox') || str_contains($operator, 'sinch')) ? 'sinch' : 'nexmo';

                // Update smsg_log with provider info for scheduled pickup
                DB::table('smsg_log')
                    ->where('id', $smsgLogId)
                    ->update([
                        'suppliername' => ($provider === 'sinch') ? 'Sinch SMPP' : 'Vonage SMPP',
                        'aggregator_dlrmsg' => 'Scheduled - ' . $operator,
                    ]);

                $successCount++;
                Log::info('SMS scheduled for later delivery (smsg_log based)', [
                    'mobile' => $thenum,
                    'bigid' => $bigid,
                    'scheduled_at' => $sendAt->toDateTimeString(),
                    'operator' => $operator,
                    'provider' => $provider
                ]);
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Error scheduling SMS', [
                    'mobile' => $thenum,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($successCount > 0) {
            $message = $useSmppQueue
                ? "SMS scheduled successfully! {$successCount} message(s) will be sent at {$sendAt->format('Y-m-d H:i')} via SMPP."
                : "SMS message scheduled successfully";

            return back()->with('success', $message);
        } else {
            return back()->with('error', 'Failed to schedule SMS messages.');
        }
    }

    /**
     * OLD SYSTEM API Response Helper
     * Returns response in OLD SYSTEM format: "error code|error text|submission reference\n{code}|{message}|{ref}"
     */
    private function oldSystemResponse(int $errorCode, string $errorText, string $submissionRef = '0')
    {
        $response = "error code|error text|submission reference\n";
        $response .= "{$errorCode}|{$errorText}|{$submissionRef}\n";
        return response($response, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * OLD SYSTEM: Globally blocked numbers that must never receive SMS
     */
    private function isGloballyBlockedNumber(string $number): bool
    {
        $blockedNumbers = [
            '447710101010', // test number
            '447725903111', // blocked per OLD SYSTEM
        ];
        return in_array($number, $blockedNumbers);
    }

    /**
     * SMS API Controller - OLD SYSTEM Compatible
     * Returns responses in OLD SYSTEM format: "error code|error text|submission reference"
     *
     * API Parameters (same as OLD SYSTEM sms.mes):
     * - usr: username
     * - pwd: password
     * - from: sender ID
     * - to: recipient number(s), comma separated
     * - txt: message text
     * - type: text/binary/unicode (default: text)
     * - route: route number (optional, auto-selected)
     * - send: scheduled send time YYYYMMDDHHmmss (optional)
     * - sendrelative: offset in seconds from now (optional)
     */
    public function sendSmsApi(Request $request)
    {
        // Get the parameters from the URL (same as OLD SYSTEM)
        $usr = $request->query('usr', '');
        $pwd = $request->query('pwd', '');
        $to = $request->query('to', '');
        $txt = $request->query('txt', '');
        $from = $request->query('from', '');
        $type = $request->query('type', 'text');
        $route = $request->query('route', '');
        $send = $request->query('send', '');
        $sendrelative = $request->query('sendrelative');
        $sitype = $request->query('sitype', '');
        $userdef = $request->query('userdef', '');

        // Handle sendrelative (offset in seconds)
        if ($sendrelative) {
            $send = Carbon::now('Europe/London')->addSeconds((int)$sendrelative)->format('YmdHis');
        }

        // OLD SYSTEM: Check user details - error code 1
        if (trim($usr) == '' || trim($pwd) == '') {
            return $this->oldSystemResponse(1, 'bad user details (missing credentials)');
        }

        // Check if the user exists and password matches
        $user = DB::table('users')
            ->select('smsg_wallet', 'smsg_server1_sent', 'smsg_server2_sent', 'bigid', 'agencyref',
                     'bulk_throughput', 'bulk_throughput_lastwarned', 'contactemail', 'contactname',
                     'busname', 'bulksms_daily_tally', 'bulksms_tally_last_reset', '1s_preferredroute',
                     'user_type', 'daemonpriority', 'pword', 'uname')
            ->where('uname', trim($usr))
            ->first();

        if (!$user) {
            return $this->oldSystemResponse(1, 'bad user details (1)');
        }

        // Validate password
        if (trim($pwd) !== $user->pword) {
            return $this->oldSystemResponse(1, 'bad user details (1)');
        }

        $userref = $user->bigid;
        $affiliateref = $user->agencyref ?? '0';

        // OLD SYSTEM: Check "to" field - error code 2
        if (!isset($to) || trim($to) == '') {
            return $this->oldSystemResponse(2, 'missing or invalid fields (to)');
        }

        // OLD SYSTEM: Check "from" field - error code 2
        if (!isset($from) || trim($from) == '') {
            return $this->oldSystemResponse(2, 'missing or invalid fields (from)');
        }

        // OLD SYSTEM: Validate Sender ID format
        $senderValidation = $this->smsValidationService->validateSenderId($from);
        if (!$senderValidation['valid']) {
            return $this->oldSystemResponse(2, 'missing or invalid fields (from - invalid format)');
        }

        // OLD SYSTEM: Check "type" field - error code 2
        if (!isset($type) || trim($type) == '') {
            return $this->oldSystemResponse(2, 'missing or invalid fields (type is blank)');
        }

        // Determine numbits based on type
        if ($type == 'text' || $type == 'longmessage') {
            $numbits = 7;
        } elseif ($type == 'binary') {
            $numbits = 8;
        } elseif ($type == 'unicode') {
            $numbits = 16;
        } else {
            return $this->oldSystemResponse(2, 'missing or invalid fields (type is invalid)');
        }

        // OLD SYSTEM: Check "txt" field - error code 2
        if (!isset($txt) || trim($txt) == '') {
            return $this->oldSystemResponse(2, 'missing or invalid fields (1000)');
        }

        // OLD SYSTEM: Message length validation - error code 2
        if ($sitype != 'longmessage') {
            if ($numbits == 7 && strlen($txt) > 1377) {
                return $this->oldSystemResponse(2, 'missing or invalid fields (1001)');
            }
            if ($numbits == 8 && strlen($txt) > 459) {
                return $this->oldSystemResponse(2, 'missing or invalid fields (1002)');
            }
            if ($numbits == 16 && strlen($txt) > 70) {
                return $this->oldSystemResponse(2, 'missing or invalid fields (1003)');
            }
        }

        // OLD SYSTEM: Reset daily tally if needed and check throughput - error code 302
        $lastMidnight = Carbon::today('Europe/London')->format('Y-m-d 00:00:00');
        if (!$user->bulksms_tally_last_reset || $user->bulksms_tally_last_reset < $lastMidnight) {
            DB::table('users')
                ->where('bigid', $userref)
                ->update([
                    'bulksms_tally_last_reset' => Carbon::now('Europe/London'),
                    'bulksms_daily_tally' => 0
                ]);
            $bulksms_daily_tally = 0;
        } else {
            $bulksms_daily_tally = $user->bulksms_daily_tally ?? 0;
        }

        // Increment tally
        DB::table('users')
            ->where('bigid', $userref)
            ->increment('bulksms_daily_tally');
        $bulksms_daily_tally++;

        // Check throughput limit
        $bulk_throughput = $user->bulk_throughput ?? 0;
        if ($bulksms_daily_tally > $bulk_throughput) {
            Log::warning('API SMS blocked - throughput exceeded', [
                'user' => $usr,
                'bigid' => $userref,
                'tally' => $bulksms_daily_tally,
                'limit' => $bulk_throughput
            ]);
            return $this->oldSystemResponse(302, 'throughput exceeded');
        }

        // Tidy numbers and process
        $to = trim(str_replace(' ', '', $to));
        $to = str_replace([':', ';'], ',', $to);
        $to = rtrim($to, ',');
        $thenums = explode(',', $to);

        // OLD SYSTEM: Calculate wallet balance and check - error code 102
        $current_wallet = sprintf("%.2f", $user->smsg_wallet - $user->smsg_server1_sent);
        $min_price = count($thenums) * 0.009; // 0.9p minimum per message

        if ($current_wallet < $min_price || $current_wallet < 0.08) {
            Log::warning('API SMS blocked - insufficient credit', [
                'user' => $usr,
                'bigid' => $userref,
                'wallet' => $current_wallet,
                'min_required' => $min_price,
                'num_messages' => count($thenums)
            ]);
            return $this->oldSystemResponse(102, 'submission failed due to insufficient credit');
        }

        // Process each number
        $thecountrycodes = [];
        for ($i = 0; $i < count($thenums); $i++) {
            // Convert UK format
            if (substr($thenums[$i], 0, 1) == '0') {
                $thenums[$i] = '44' . substr($thenums[$i], 1);
            }

            // Clean up weird formats
            if (substr($thenums[$i], 0, 6) == '440447') {
                $thenums[$i] = substr($thenums[$i], 3);
            }
            if (substr($thenums[$i], 0, 5) == '44447') {
                $thenums[$i] = substr($thenums[$i], 2);
            }

            // OLD SYSTEM: Validate number length and format - error code 2
            if (strlen($thenums[$i]) < 8 || strlen($thenums[$i]) > 15 || preg_match('/[^0-9]/i', $thenums[$i])) {
                return $this->oldSystemResponse(2, 'missing or invalid fields (to)');
            }

            // OLD SYSTEM: Check globally blocked numbers - error code 600
            if ($this->isGloballyBlockedNumber($thenums[$i])) {
                return $this->oldSystemResponse(600, 'globally blocked number (' . $thenums[$i] . ')');
            }

            // OLD SYSTEM: Validate country code - error code 2/101
            $countryInfo = $this->extractCountryCode($thenums[$i]);
            if (!$countryInfo) {
                return $this->oldSystemResponse(2, 'missing or invalid fields: unknown country');
            }
            $thecountrycodes[$i] = $countryInfo->dialcode;
        }

        // Generate unique bigid (like OLD SYSTEM smssendcreateref)
        $bigid = strtolower(substr(md5(uniqid(rand(), true)), 0, 32));

        // Set send time
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        if (!isset($send) || trim($send) == '' || $send == 0) {
            $send = $datenow;
        }

        // Calculate dosendtimeint as Unix timestamp
        $dosendtimeint = mktime(
            (int) substr($send, 8, 2),
            (int) substr($send, 10, 2),
            (int) substr($send, 12, 2),
            (int) substr($send, 4, 2),
            (int) substr($send, 6, 2),
            (int) substr($send, 0, 4)
        );

        // Calculate daemon priority
        $userDaemonPriority = $user->daemonpriority ?? 100;
        $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($thenums) > 500 ? 200 : $userDaemonPriority;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        // Check if SMPP Queue Service is available
        $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

        // Determine operator and route
        $operator = $this->getOperatorForSender($from, $userref);
        $isSinch = (stripos($operator, 'mblox') !== false || stripos($operator, 'sinch') !== false);

        // Determine operator for cost lookup (vonage or sinch)
        $costOperator = $isSinch ? 'sinch' : 'vonage';

        $ukRoutenum = $isSinch ? 3002 : 7002;
        $intlRoutenum = 8002;

        $successCount = 0;
        $failedCount = 0;

        // Process and insert each number
        foreach ($thenums as $i => $thenum) {
            $countryCode = $thecountrycodes[$i] ?? '44';
            $routenum = ($countryCode === '44') ? $ukRoutenum : $intlRoutenum;

            // Calculate pricing using smsg_userroute + country cost table
            $numParts = $this->calculateSmsParts($txt);
            $pricing = $this->userRouteService->getPricingForPhoneNumber(
                $userref,
                $thenum,
                $routenum,
                $numbits,
                'alpha',
                $costOperator  // Pass operator for country cost lookup
            );

            $userprice = round($pricing['userprice'] * $numParts, 6);
            $costprice = round($pricing['costprice'] * $numParts, 6);
            $profit = round($userprice - $costprice, 6);
            $suppliername = $pricing['suppliername'];

            // Insert into smsg_log
            $smsgLogId = DB::table('smsg_log')->insertGetId([
                'sms_type' => 'sms',
                'initiator' => 'API',
                'bigid' => $bigid,
                'mobnum' => $thenum,
                'numparts' => $numParts,
                'text' => $txt,
                'originator' => $from,
                'numbits' => $numbits,
                'timesubmitted' => $datenow,
                'userref' => $userref,
                'affiliateref' => $affiliateref,
                'dosendtime' => $send,
                'dosendtimeint' => $dosendtimeint,
                'dayofyear' => substr($send, 0, 8),
                'timesent' => '00000000000000',
                'sentstatus' => 'pending',
                'sentstatustmp' => 'pending',
                'sentstatustext' => $useSmppQueue ? 'Queued for SMPP delivery' : 'Pending',
                'suppliermsgref' => '',
                'smsgdaemonid' => $daemonId,
                'sendpriority' => $baseDaemonId,
                'costprice' => $costprice,
                'userprice' => $userprice,
                'aggregator_dlrcode' => 0,
                'aggregator_dlrmsg' => $useSmppQueue ? 'Queued' : 'Non Delivered',
                'campaignref' => '',
                'binaryflags' => '',
                'profit' => $profit,
                'countrydialcode' => $countryCode,
                'suppliername' => $suppliername,
                'supplierrouteref' => '',
                'requested_route' => $routenum,
                'requested_routetag' => '',
                'deliverystatus2' => 'pending',
                'migration_flag' => 'new',
                'sitype' => $sitype,
                'userdefined' => $userdef,
            ]);

            if ($useSmppQueue) {
                // Send directly via SMPP
                if ($isSinch) {
                    $sendResult = $this->sendViaSinchSmpp($thenum, $txt, $from, $smsgLogId, $bigid, null);
                } else {
                    $sendResult = $this->sendViaNexmoSmpp($thenum, $txt, $from, $smsgLogId, $bigid, null);
                }

                if ($sendResult['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            } else {
                $successCount++;
            }
        }

        // OLD SYSTEM success response
        return $this->oldSystemResponse(0, 'sms submitted', $bigid);
    }

    /**
     * Send WhatsApp Message via API using Nexmo
     */
    private function sendWhatsAppMessageApi($recipients, $message, $from, $bigid, $userref, $datenow)
    {
        $successCount = 0;
        $failedCount = 0;
        $messageIds = [];

        // Get Nexmo credentials from environment
        $nexmoApiKey = env('NEXMO_API_KEY');
        $nexmoApiSecret = env('NEXMO_API_SECRET');
        $nexmoWhatsAppNumber = env('NEXMO_WHATSAPP_NUMBER');

        if (!$nexmoApiKey || !$nexmoApiSecret || !$nexmoWhatsAppNumber) {
            return response()->json([
                'success' => false,
                'error' => 'WhatsApp service is not configured'
            ], 503);
        }

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
                $thecountrycodes = $countryInfo ? $countryInfo->dialcode : '44';

                // Format phone number for WhatsApp
                $formattedNumber = $thenum;
                if (substr($formattedNumber, 0, 1) !== '+') {
                    $formattedNumber = '+' . $formattedNumber;
                }

                // Insert into smsg_log
                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => 'whatsapp',
                    'bigid' => $bigid,
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
                    'sentstatustext' => 'Sending via WhatsApp API',
                    'suppliermsgref' => '',
                    'smsgdaemonid' => $daemonId,
                    'sendpriority' => $baseDaemonId,
                    'costprice' => 0.000000,
                    'userprice' => 0.000000,
                    'aggregator_dlrcode' => 0,
                    'aggregator_dlrmsg' => 'Pending',
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => 0.000000,
                    'countrydialcode' => $thecountrycodes ?? '',
                    'suppliername' => 'Nexmo WhatsApp',
                    'supplierrouteref' => '',
                    'requested_route' => 0,
                    'requested_routetag' => '',
                    'deliverystatus2' => 'pending',
                    'migration_flag' => 'new',
                ]);

                // Send WhatsApp message via Nexmo API
                $response = Http::withBasicAuth($nexmoApiKey, $nexmoApiSecret)
                    ->post('https://api.nexmo.com/v1/messages', [
                        'from' => [
                            'type' => 'whatsapp',
                            'number' => $nexmoWhatsAppNumber
                        ],
                        'to' => [
                            'type' => 'whatsapp',
                            'number' => $formattedNumber
                        ],
                        'message' => [
                            'content' => [
                                'type' => 'text',
                                'text' => $message
                            ]
                        ]
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $messageId = $responseData['message_uuid'] ?? '';

                    $successCount++;
                    $messageIds[] = $messageId;

                    // Update smsg_log with success
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'sentstatus' => 'ok',
                            'sentstatustext' => 'WhatsApp message sent via API',
                            'suppliermsgref' => $messageId,
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                            'deliverystatus2' => 'Sent'
                        ]);

                    Log::info('WhatsApp message sent via API', [
                        'message_id' => $messageId,
                        'mobile' => $thenum
                    ]);
                } else {
                    $failedCount++;
                    $errorMessage = $response->json()['error_text'] ?? 'Unknown error';

                    Log::error('Failed to send WhatsApp message via API', [
                        'mobile' => $thenum,
                        'error' => $errorMessage
                    ]);

                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'sentstatus' => 'fail',
                            'sentstatustext' => 'WhatsApp API failed: ' . $errorMessage,
                            'deliverystatus2' => 'Failed'
                        ]);
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Exception in WhatsApp API', [
                    'mobile' => $thenum,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => $successCount > 0,
            'message' => $successCount > 0 ? 'WhatsApp messages sent via API' : 'Failed to send WhatsApp messages',
            'sent' => $successCount,
            'failed' => $failedCount,
            'message_ids' => $messageIds,
            'bigid' => $bigid,
            'service' => 'nexmo_whatsapp'
        ]);
    }

    /**
     * Get SMPP Queue Statistics
     */
    public function getQueueStats(Request $request)
    {
        if ($this->smsQueueService === null) {
            return response()->json([
                'success' => false,
                'error' => 'SMPP Queue Service not available'
            ], 503);
        }

        try {
            $stats = $this->smsQueueService->getStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get queue stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check SMPP Service Status
     */
    public function checkSmppStatus(Request $request)
    {
        $status = [
            'smpp_enabled' => env('SMPP_ENABLED', true),
            'queue_service_available' => $this->smsQueueService !== null,
            'rabbitmq_available' => $this->rabbitMQService !== null,
        ];

        if ($this->smsQueueService !== null) {
            try {
                $stats = $this->smsQueueService->getStatistics();
                $status['queue_stats'] = $stats['queue_stats'] ?? [];
                $status['smpp_connections'] = $stats['smpp_pool_stats'] ?? [];
            } catch (\Exception $e) {
                $status['error'] = $e->getMessage();
            }
        }

        return response()->json($status);
    }

    /**
     * Get Throughput Status for current user
     */
    public function getThroughputStatus(Request $request)
    {
        $userInfo = Session::get('user_info');
        $userref = isset($userInfo['bigid']) ? $userInfo['bigid'] : null;

        if (!$userref) {
            return response()->json([
                'error' => 'User not authenticated'
            ], 401);
        }

        $status = $this->bulkThroughputService->getThroughputStatus($userref);

        return response()->json($status);
    }

    /**
     * Get Wallet Status for current user
     */
    public function getWalletStatus(Request $request)
    {
        $userInfo = Session::get('user_info');
        $userref = isset($userInfo['bigid']) ? $userInfo['bigid'] : null;

        if (!$userref) {
            return response()->json([
                'error' => 'User not authenticated'
            ], 401);
        }

        $status = $this->walletValidationService->getWalletStatus($userref);

        return response()->json($status);
    }

    // Keep all the existing methods below unchanged.

    public function sentSms(Request $request)
    {
        if (!$request->all()) {

            $resultArray = [
                'records' => '',
                'totalPages' => '',
                'currentPage' => '',
                'perPage' => '',
                'perPageOptions' => '',
                'totalRecords' => '',
                'chkitagg' => 'checked',
                'chkcontrolpanel' => 'checked',
                'chkapi' => 'checked',
                'chkemail' => 'checked',
                'chkmobyclip' => 'checked',
                'selRoutes' => 'all',
                'selDelivery' => 'all',
                'logMatch' => '',
                'mobile' => '',
                'start_date' => date('Y-m-d'),
                'start_hh' => '00',
                'start_mm' => '',
                'end_date' => date('Y-m-d'),
                'end_hh' => '23',
                'end_mm' => '55',
                'export' => ''
            ];
            return view('customer.sent_sms.index', $resultArray);
        }

        // Build dynamic conditions
        $conditions = [];

        if ($request->selRoutes == "standard") {
            $conditions[] = " AND requested_route < 10000";
        } elseif ($request->selRoutes == "premium") {
            $conditions[] = " AND requested_route >= 10000";
        }

        if ($request->selDelivery == "delivered") {
            // OLD SYSTEM uses 'Delivered', also check lowercase for backward compatibility
            $conditions[] = " AND ((deliverystatus2 IN ('Delivered', 'delivered') AND requested_route<10000) OR (sentstatus='ok' AND requested_route>=10000))";
        } elseif ($request->selDelivery == "failed") {
            // OLD SYSTEM uses 'Non Delivered', also check lowercase for backward compatibility
            $conditions[] = " AND (sentstatus IN ('fail') OR deliverystatus2 IN ('Non Delivered', 'non delivered', 'failed'))";
        } elseif ($request->selDelivery == "pending") {
            $conditions[] = " AND ((sentstatus NOT IN ('fail') AND (deliverystatus2='' OR deliverystatus2 LIKE '%buffered%' OR deliverystatus2 IN ('acked', 'pending')) AND requested_route<10000))";
        } elseif ($request->selDelivery == "lost_notification") {
            // OLD SYSTEM uses 'Lost Notification'
            $conditions[] = " AND deliverystatus2 IN ('Lost Notification', 'lost notification')";
        }

        if ($request->start_date && $request->end_date) {
            $start_date = str_replace("-", "", $request->start_date) . $request->start_hh . $request->start_mm . '00';
            $end_date = str_replace("-", "", $request->end_date) . $request->end_hh . $request->end_mm . '00';
            $conditions[] = " AND dosendtime BETWEEN '$start_date' AND '$end_date'";
        }

        if ($request->logMatch) {
            $conditions[] = " AND ( userdefined like 'log%" .  urlencode($request->logMatch) . "%' )";
        }
        if ($request->mobile) {
            $conditions[] = " AND mobnum = '" . $request->mobile . "'";
        }

        $perPageOptions = [20, 50, 100, 250, 500];
        $perPage = in_array($request->get('perPage', 20), $perPageOptions) ? $request->get('perPage', 20) : 20;
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $unionQuery = $this->getConsolidatedRawQuery($conditions);
        $paginatedQuery = "$unionQuery ORDER BY dosendtime DESC LIMIT $perPage OFFSET $offset";
        $records = DB::select($paginatedQuery);
        $totalCountQuery = "SELECT COUNT(*) as total FROM ({$unionQuery}) as combined_logs";
        $totalRecords = DB::select($totalCountQuery)[0]->total;
        $totalPages = ceil($totalRecords / $perPage);
        $convertResultdata = json_decode(json_encode($records), true);
        $resultArray = [
            'data' => $convertResultdata,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRecords' => $totalRecords,
            'chkitagg' => (isset($request->chkitagg) && !empty($request->chkitagg)) ? 'checked' : '',
            'chkcontrolpanel' => (isset($request->chkcontrolpanel) && !empty($request->chkcontrolpanel)) ? 'checked' : '',
            'chkapi' => (isset($request->chkapi) && !empty($request->chkapi)) ? 'checked' : '',
            'chkemail' => (isset($request->chkemail) && !empty($request->chkemail)) ? 'checked' : '',
            'chkmobyclip' => (isset($request->chkmobyclip) && !empty($request->chkmobyclip)) ? 'checked' : '',
            'selRoutes' => (isset($request->selRoutes) && !empty($request->selRoutes)) ? $request->selRoutes : '',
            'selDelivery' => (isset($request->selDelivery) && !empty($request->selDelivery)) ? $request->selDelivery : '',
            'logMatch' => (isset($request->logMatch) && !empty($request->logMatch)) ? $request->logMatch : '',
            'mobile' => (isset($request->mobile) && !empty($request->mobile)) ? $request->mobile : '',
            'start_date' => (isset($request->start_date) && !empty($request->start_date)) ? $request->start_date : '',
            'start_hh' => (isset($request->start_hh) && !empty($request->start_hh)) ? $request->start_hh : '',
            'start_mm' => (isset($request->start_mm) && !empty($request->start_mm)) ? $request->start_mm : '',
            'end_date' => (isset($request->end_date) && !empty($request->end_date)) ? $request->end_date : '',
            'end_hh' => (isset($request->end_hh) && !empty($request->end_hh)) ? $request->end_hh : '',
            'end_mm' => (isset($request->end_mm) && !empty($request->end_mm)) ? $request->end_mm : '',
            'export' => $this->exportToCsv($unionQuery),
            // 'export' => $this->exportToCsv(json_decode(json_encode(DB::select($unionQuery . ' ORDER BY dosendtime DESC')), true)),
        ];
        return view('customer.sent_sms.index', $resultArray);
    }

    public function exportToCsv($unionQuery)
    {
        $keysToKeep = [
            'mobnum',
            'text',
            'originator',
            'timesent',
            'sentstatus',
            'sentstatustext',
            'deliverystatus1',
            'deliverystatus2',
            'requested_route',
            'userprice'
        ];

        $folderPath = public_path('send_sms');
        $fileName   = $this->userBigId . '.csv';
        $filePath   = $folderPath . '/' . $fileName;

        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $file = fopen($filePath, 'w');

        $counter = 1;
        $headerWritten = false;

        // IMPORTANT: cursor() prevents memory crash

        $rows = DB::cursor($unionQuery . ' ORDER BY dosendtime DESC');

        foreach ($rows as $rowObj) {

            $row = json_decode(json_encode($rowObj), true);

            $row = array_intersect_key($row, array_flip($keysToKeep));

            if (!$headerWritten) {

                $header = $row;

                unset(
                    $header['sentstatustext'],
                    $header['deliverystatus1'],
                    $header['sentstatus'],
                    $header['requested_route'],
                    $header['timesent']
                );

                if (isset($header['deliverystatus2'])) {
                    $header['status'] = $header['deliverystatus2'];
                    unset($header['deliverystatus2']);
                }

                fputcsv($file, array_merge(['S.No'], array_keys($header)));

                $headerWritten = true;
            }

            if (isset($row['text'])) {
                // Stored URL-encoded + Windows-1252 (£ = %A3) for OLD parity — decode for UTF-8 display.
                $row['text'] = decodeSmsTextForDisplay($row['text']);
            }

            unset(
                $row['sentstatustext'],
                $row['deliverystatus1'],
                $row['sentstatus'],
                $row['requested_route'],
                $row['timesent']
            );

            if (!empty($row['deliverystatus2'])) {

                $row['status'] = match (strtolower($row['deliverystatus2'])) {
                    'pending' => 'Pending',
                    'delivered' => 'Delivered',
                    'Delivered' => 'Delivered',
                    'failed' => 'Failed',
                    'rejected' => 'Rejected',
                    'tomorrowonward' => 'Scheduled',
                    default => ucfirst($row['deliverystatus2']),
                };

                unset($row['deliverystatus2']);
            }

            fputcsv($file, array_merge([$counter], $row));

            $counter++;
        }

        fclose($file);

        return asset('send_sms/' . $fileName);
    }

    // public function exportToCsv($data)
    // {
    //     ### SMS Sent Screen ###      
    //     $keysToKeep = [
    //         'mobnum',
    //         'text',
    //         'originator',
    //         'timesent',
    //         'sentstatus',
    //         'sentstatustext',
    //         'deliverystatus1',
    //         'deliverystatus2',
    //         'requested_route',
    //         'userprice'
    //     ];

    //     $filteredData = array_map(function ($row) use ($keysToKeep) {
    //         return array_intersect_key($row, array_flip($keysToKeep));
    //     }, $data);

    //     if (empty($filteredData)) {
    //         return "";
    //     }

    //     $folderPath = public_path('send_sms');
    //     $fileName   = $this->userBigId . '.csv';
    //     $filePath   = $folderPath . '/' . $fileName;

    //     // Create the folder if it doesn't exist
    //     if (!file_exists($folderPath)) {
    //         mkdir($folderPath, 0755, true);
    //     }

    //     // Create and write to the CSV file
    //     $file = fopen($filePath, 'w');

    //     // Prepare header with S.No included
    //     $jsonEncode = json_decode(json_encode($filteredData[0]), true);
    //     unset($jsonEncode['sentstatustext'], $jsonEncode['deliverystatus1'], $jsonEncode['sentstatus'], $jsonEncode['requested_route'], $jsonEncode['timesent']);
    //     // Rename deliverystatus2 key for CSV header
    //     if (isset($jsonEncode['deliverystatus2'])) {
    //         $jsonEncode['status'] = $jsonEncode['deliverystatus2'];
    //         unset($jsonEncode['deliverystatus2']);
    //     }
    //     $headers = array_merge(['S.No'], array_keys($jsonEncode));
    //     fputcsv($file, $headers);

    //     // Status mapping (same as Blade)
    //     $statusMapping = [
    //         'pending'   => 'Pending',
    //         'ok'        => 'Completed',
    //         'fail'      => 'Failed',
    //         'tomorrowonward'  => 'Scheduled',
    //     ];

    //     $counter = 1;
    //     foreach ($filteredData as $row) {
    //         if (array_key_exists('timesent', $row)) {
    //             $thetimestr = $row['timesent'];
    //             $row['timesent'] = $thetimestr > 0
    //                 ? date('jS M Y H:i', mktime(
    //                     substr($thetimestr, 8, 2),
    //                     substr($thetimestr, 10, 2),
    //                     substr($thetimestr, 12, 2),
    //                     substr($thetimestr, 4, 2),
    //                     substr($thetimestr, 6, 2),
    //                     substr($thetimestr, 0, 4)
    //                 ))
    //                 : 'In progress';
    //         }

    //         if (array_key_exists('sentstatus', $row)) {
    //             $delivery_status = getDeliveryStatus($row, $statusIndicator_byref);
    //             $statusKey = $row['sentstatus'] ?? 'unknown';
    //             $row['sentstatus'] = $statusMapping[$statusKey] ?? ucfirst($statusKey);
    //         }

    //         if (array_key_exists('text', $row)) {
    //             $row['text'] = urldecode($row['text']);
    //         }

    //         unset($row['sentstatustext'], $row['deliverystatus1'], $row['sentstatus'], $row['requested_route'], $row['timesent']);

    //         // Rename deliverystatus2 to Status
    //         // if (isset($row['deliverystatus2'])) {
    //         //     $row['status'] = $row['deliverystatus2'];
    //         //     unset($row['deliverystatus2']);
    //         // }
    //         if (!empty($row['deliverystatus2'])) {
    //             $row['status'] = match (strtolower($row['deliverystatus2'])) {
    //                 'pending' => 'Pending',
    //                 'delivered' => 'Delivered',
    //                 'Delivered' => 'Delivered',
    //                 'failed' => 'Failed',
    //                 'rejected' => 'Rejected',
    //                 'tomorrowonward' => 'Scheduled',
    //                 default => ucfirst($row['deliverystatus2']),
    //             };
    //             unset($row['deliverystatus2']);
    //         }

    //         // Prepend S.No
    //         $finalRow = array_merge([$counter], $row);

    //         fputcsv($file, $finalRow);
    //         $counter++;
    //     }

    //     fclose($file);

    //     return asset('send_sms/' . $fileName);
    // }

    public function receviedSMSexportToCsv($data)
    {

        ### SMS Sent Screen ###      
        $keysToKeep = ['source', 'msg', 'recieved', 'dest'];
        $filteredData = array_map(function ($row) use ($keysToKeep) {
            return array_intersect_key($row, array_flip($keysToKeep));
        }, $data);

        if (empty($filteredData)) {
            return "";
        }
        $folderPath = public_path('received_sms');
        $fileName = $this->userBigId . '.csv';
        $filePath = $folderPath . '/' . $fileName;

        // Create the folder if it doesn't exist
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        // Create and write to the CSV file
        $file = fopen($filePath, 'w');
        $jsonEncode = $filteredData[0];

        fputcsv($file, array_keys($jsonEncode));
        foreach ($filteredData  as $row) {

            fputcsv($file, $row);
        }
        fclose($file);


        return asset('received_sms/' . $fileName);
    }

    public function getConsolidatedRawQuery(array $conditions)
    {
        $tables = DB::select("
        SELECT table_name
        FROM INFORMATION_SCHEMA.TABLES
        WHERE table_name LIKE 'smsg_log%'
        AND TABLE_SCHEMA = DATABASE()
    ");
        $userInfo = Session::get('user_info');
        $bigId = '';
        if (isset($userInfo['bigid'])) {
            $bigId = $userInfo['bigid'];
        }
        $this->userBigId = $bigId;

        // Explicitly select only the columns that exist in all tables and are needed
        $columns = "id, userref, mobnum, text, originator, timesent, dosendtime,
                    sentstatus, sentstatustext, deliverystatus1, deliverystatus2,
                    deliverytime2, requested_route, userprice, initiator, userdefined";

        $queries = collect($tables)->map(function ($table) use ($conditions, $columns) {
            $tablesName = $table->table_name ?? $table->TABLE_NAME;
            $query = "SELECT {$columns}, '{$tablesName}' AS table_name FROM {$tablesName} WHERE userref='" . $this->userBigId . "'";
            foreach ($conditions as $condition) {
                $query .= " $condition";
            }
            return $query;
        });

        $data = implode(' UNION ALL ', $queries->toArray());

        return $data;
    }

    /**
     * "Date Finalised" (deliverytime2) is stored to MINUTE precision only — it's a 12-digit
     * YYYYMMDDHHMM, so the seconds are always :00. When the DLR settles in the SAME minute as
     * the message was sent, the finalised time renders (e.g.) 19:27:00 while "Send at Time" is
     * 19:27:34 — i.e. it appears to finish BEFORE it was sent.
     *
     * To read sensibly, when the finalised time is at/earlier-than the send time (the
     * minute-truncation artefact) we show it as Send-at-Time + 2 seconds. Genuinely later
     * deliveries (a different/later minute) are returned unchanged. Both inputs are the already
     * display-formatted strings ('%W, %D %M %Y %T' -> 'l, jS F Y H:i:s').
     */
    private function adjustFinalisedDisplay($finalised, $sendAt)
    {
        if (empty($finalised) || empty($sendAt)) {
            return $finalised;
        }

        $fmt = 'l, jS F Y H:i:s';
        try {
            $fin  = \Carbon\Carbon::createFromFormat($fmt, trim($finalised));
            $send = \Carbon\Carbon::createFromFormat($fmt, trim($sendAt));
            if ($fin && $send && $fin->lessThanOrEqualTo($send)) {
                return $send->copy()->addSeconds(2)->format($fmt);
            }
        } catch (\Throwable $e) {
            // Unparseable (e.g. placeholder text) — leave the value as-is.
        }

        return $finalised;
    }

    public function sentSmsDetails(Request $request)
    {
        $tableName = $request->tableName;
        $id = $request->id;

        $stateViewTbl = $tableName;  // The actual table name
        $stateViewIdx = $id;  // The index or ID for the query

        // The first SQL query to get the delivery date
        $sqldate = "SELECT DATE_FORMAT(CONCAT(deliverytime2, '00'), '%Y%m%d') AS deliverytime2 FROM $stateViewTbl WHERE id = $stateViewIdx";
        $delivered = DB::select($sqldate);

        if ($delivered) {

            $delivered = $delivered[0]->deliverytime2; // Assuming this is the column you are getting

            // OLD SYSTEM parity: deliverytime2 is stored in GMT/UTC. Convert to UK local
            // for display — add 1 HOUR during BST, no offset in GMT (winter). isSummerTime()
            // keys off the delivery date so the correct offset is applied per-DST.
            if (isSummerTime($delivered)) {  // BST: UTC + 1 hour
                $sql = "SELECT DATE_FORMAT(timesubmitted,'%W, %D %M %Y %T') as timesubmitted,
                        text, mobnum, originator, initiator, requested_route, userprice, SItype,numparts,
                        CASE WHEN (deliverytime2 IS NULL OR deliverytime2 = '') THEN NULL
                             ELSE DATE_FORMAT(dosendtime + INTERVAL 1 SECOND, '%W, %D %M %Y %T') END as deliverytime,
                        upstream_errormessage, userdefined, countrydialcode, DATE_FORMAT(dosendtime, '%W, %D %M %Y %T') as dosendtime,sentstatus,sentstatustext,deliverystatus1,deliverystatus2,DATE_FORMAT(timesent, '%W, %D %M %Y %T') as timesent
                FROM $stateViewTbl
                WHERE id = $stateViewIdx";
            } else {
                // GMT (winter): UTC = GMT, show as-is
                $sql = "SELECT DATE_FORMAT(timesubmitted,'%W, %D %M %Y %T') as timesubmitted,
                        text, mobnum, originator, initiator, requested_route, userprice, SItype,numparts,
                        CASE WHEN (deliverytime2 IS NULL OR deliverytime2 = '') THEN NULL
                             ELSE DATE_FORMAT(dosendtime + INTERVAL 1 SECOND, '%W, %D %M %Y %T') END as deliverytime,
                        upstream_errormessage, userdefined, countrydialcode, DATE_FORMAT(dosendtime, '%W, %D %M %Y %T') as dosendtime,sentstatus,sentstatustext,deliverystatus1,deliverystatus2,DATE_FORMAT(timesent, '%W, %D %M %Y %T') as timesent
                FROM $stateViewTbl
                WHERE id = $stateViewIdx";
            }

            // Execute the final query
            $result = DB::select($sql);

            if ($result) {
                $row = $result[0];
                $timesubmitted = $row->timesubmitted;
                // Stored URL-encoded + Windows-1252 (£ = %A3) for OLD parity — decode for UTF-8 display.
                $text = decodeSmsTextForDisplay($row->text);
                $to = $row->mobnum;
                $senderid = decodeSmsTextForDisplay($row->originator);
                $initiator = $row->initiator;
                $requested_route = $row->requested_route;

                // userprice is already the total (already multiplied by numparts when stored)
                $userprice = (float)($row->userprice ?? 0);
                //   $costPrice = $row-> * 100;
                $sitype = $row->SItype;
                $deliverytime2 = $row->deliverytime;
                $upstreamerror = sanitiseStringForUserDisplay($row->upstream_errormessage);
                $userdefined = urldecode($row->userdefined);
                $countrydialcode = $row->countrydialcode;
                $dosendtime = $row->dosendtime;
                $msisdnAlias = '';
                $timesent = $row->timesent ?? '';
                $sentstatustext = $row->sentstatustext ?? '';


                if (preg_match("/MSISDNALIAS=(.{32,32})/", urldecode($userdefined), $arrMsisdnAlias)) {

                    $msisdnAlias = $arrMsisdnAlias[1];
                }
                $delivery_status =  getDeliveryStatus(json_decode(json_encode($row), true), $statusIndicator_byref);
                // Date Finalised: OLD SYSTEM parity (cp2_sentlog.inc). The delivery receipt has no
                // seconds (deliverytime2 = 12-digit, :00), so OLD adds a flat +1 MINUTE — applied in
                // the SQL above (CONCAT(deliverytime2,'00') + INTERVAL 1 MINUTE, or '1:1' HOUR_MINUTE
                // in BST). No PHP post-adjustment.
                $timeDelivered = ($deliverytime2 == "") ? "No information available" : $deliverytime2;
                $result = array();
                $result['Date_Submitted'] = $timesubmitted;
                $result['Send_at_time'] = $dosendtime;
                // Date Finalised = DLR settled time. OLD SYSTEM ($timeDelivered)
                // shows when handset confirmed delivery, not when we sent.
                $result['Date_Finalised'] = $timeDelivered;
                $result['Sent_at_Time'] = $timesent;  // Actual send time (when SMS was sent to provider)
                $result['Sender'] = $senderid;
                $result['Message'] = $text;
                $result['Send_at_time'] = $dosendtime;
                $result['Sent_To'] = $to;
                $result['Country_Dialcode'] = $countrydialcode;
                $result['Sent_By'] = sanitiseStringForUserDisplay($initiator);
                $result['Message_Status'] = $sentstatustext;
                $result['Delivery_Time'] = $timesent;  // Use timesent instead of deliverytime2 for consistency
                // Reason line shown under Delivery Status — matches OLD SYSTEM
                // "<br>Reason: '$upstreamerror'" when upstream_errormessage is set.
                $result['Upstream_Reason'] = $upstreamerror;
                // User defined log info — OLD SYSTEM cp2_sentlog.inc:920.
                $result['User_Defined'] = $userdefined;

                $tableName = $stateViewTbl; // Ensure $STATE_view_tbl is sanitized to avoid SQL injection.
                $row = DB::table($tableName)->where('id', $stateViewIdx)->first();

                // Process the delivery status
                $delivery_status = $row ? getDeliveryStatus(json_decode(json_encode($row), true), $status_byref) : null;
                // OLD SYSTEM parity: 'Unknown' (reason 6) / 'acked' / 'buffered*' are NON-final and
                // OLD shows them as "In Transit" — not the raw value. Final states pass through as-is.
                $delivery_status_new = deliveryStatus2DisplayLabel($row->deliverystatus2);
                $result['Delivery_Status'] = $delivery_status_new;
                if ($requested_route < 10000) {
                    $result['Recipient_Cost'] = 'n/a';
                    $result['Cost_to_You'] = '£ ' . format_four_decimal($userprice);
                    $bigId = '';
                    $userInfo = Session::get('user_info');
                    if (isset($userInfo['bigid'])) {
                        $bigId = $userInfo['bigid'];
                    }
                    $standards = get_userroutes($bigId);
                    $i = 0;
                    $found = false;
                    $catch = 0;

                    foreach ($standards as $thisStdRoute) { // first, test the normal routes - up to 8.
                        //					print("<br>{$thisStdRoute[0]} == $requested_route");
                        if ($thisStdRoute[0] == $requested_route) {

                            $requested_route = $thisStdRoute[0];
                            $found = true;
                            $route_desc = $requested_route . " (" . $thisStdRoute[5] . ")";
                            //$htmlRouteInfo  = "<TR class=\"cpTR\"><td class=\"cpTD\"><i>Sent on route </i></td>";
                            //$htmlRouteInfo .= "<TD class=\"cpTD\">$route_desc</td>";
                            break;
                        }
                    }
                    if (!$found) { // must be iTAGG response - more careful thought needed here - is this necessarily the case?

                        $result['Sent as']  = 'iTAGG response message, charged to wallet.';
                    }
                } else {

                    $cost = DB::table('smsshortcodes')
                        ->where('number', $senderid)
                        ->value('cost');
                    $result['Recipient_Cost'] = '£ ' . format_four_decimal($userprice);
                    // $result['Cost_to_You'] = 'n/a';
                }
                $result['Recipient_Cost'] = '£ ' . format_four_decimal($userprice);
                return response()->json($result);
            }
        }
    }

    /**
     * Show SMS details on a dedicated page
     *
     * @param Request $request
     * @param string $tableName
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function showSmsDetails(Request $request, string $tableName, int $id)
    {
        try {
            $stateViewTbl = $tableName;
            $stateViewIdx = $id;

            // Get filter params from query string for back navigation
            $filterParams = $request->except(['tableName', 'id', '_token']);

            // Validate table name to prevent SQL injection
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $stateViewTbl)) {
                return view('customer.sent_sms.show', ['error' => 'Invalid table name', 'filterParams' => $filterParams]);
            }

            // Check if table exists
            if (!Schema::hasTable($stateViewTbl)) {
                return view('customer.sent_sms.show', ['error' => 'Message not found', 'filterParams' => $filterParams]);
            }

            // The first SQL query to get the delivery date
            $sqldate = "SELECT DATE_FORMAT(CONCAT(deliverytime2, '00'), '%Y%m%d') AS deliverytime2 FROM $stateViewTbl WHERE id = ?";
            $delivered = DB::select($sqldate, [$stateViewIdx]);

            if (!$delivered) {
                return view('customer.sent_sms.show', ['error' => 'Message not found', 'filterParams' => $filterParams]);
            }

            $delivered = $delivered[0]->deliverytime2;

            // OLD SYSTEM parity: deliverytime2 is stored in GMT/UTC. Convert to UK local for
            // display — add 1 HOUR during BST, no offset in GMT (winter).
            if (isSummerTime($delivered)) {  // BST: UTC + 1 hour
                $sql = "SELECT DATE_FORMAT(timesubmitted,'%W, %D %M %Y %T') as timesubmitted,
                        text, mobnum, originator, initiator, requested_route, userprice, SItype, numparts,
                        CASE WHEN (deliverytime2 IS NULL OR deliverytime2 = '') THEN NULL
                             ELSE DATE_FORMAT(dosendtime + INTERVAL 1 SECOND, '%W, %D %M %Y %T') END as deliverytime,
                        upstream_errormessage, userdefined, countrydialcode, DATE_FORMAT(dosendtime, '%W, %D %M %Y %T') as dosendtime,
                        sentstatus, sentstatustext, deliverystatus1, deliverystatus2,
                        DATE_FORMAT(timesent, '%W, %D %M %Y %T') as timesent
                FROM $stateViewTbl
                WHERE id = ?";
            } else {
                // GMT (winter): UTC = GMT, show as-is
                $sql = "SELECT DATE_FORMAT(timesubmitted,'%W, %D %M %Y %T') as timesubmitted,
                        text, mobnum, originator, initiator, requested_route, userprice, SItype, numparts,
                        CASE WHEN (deliverytime2 IS NULL OR deliverytime2 = '') THEN NULL
                             ELSE DATE_FORMAT(dosendtime + INTERVAL 1 SECOND, '%W, %D %M %Y %T') END as deliverytime,
                        upstream_errormessage, userdefined, countrydialcode, DATE_FORMAT(dosendtime, '%W, %D %M %Y %T') as dosendtime,
                        sentstatus, sentstatustext, deliverystatus1, deliverystatus2,
                        DATE_FORMAT(timesent, '%W, %D %M %Y %T') as timesent
                FROM $stateViewTbl
                WHERE id = ?";
            }

            $result = DB::select($sql, [$stateViewIdx]);

            if (!$result) {
                return view('customer.sent_sms.show', ['error' => 'Message details not found', 'filterParams' => $filterParams]);
            }

            $row = $result[0];
            $timesubmitted = $row->timesubmitted;
            // Stored URL-encoded + Windows-1252 (£ = %A3) for OLD parity — decode for UTF-8 display.
            $text = decodeSmsTextForDisplay($row->text);
            $to = $row->mobnum;
            $senderid = decodeSmsTextForDisplay($row->originator);
            $initiator = $row->initiator;
            $requested_route = $row->requested_route;
            // userprice is already the total (already multiplied by numparts when stored)
            $userprice = (float)($row->userprice ?? 0);
            $deliverytime2 = $row->deliverytime;
            $dosendtime = $row->dosendtime;
            // Date Finalised: OLD SYSTEM parity (cp2_sentlog.inc). The delivery receipt has no
            // seconds (deliverytime2 = 12-digit, :00), so OLD adds a flat +1 MINUTE — applied in
            // the SQL above (CONCAT(deliverytime2,'00') + INTERVAL 1 MINUTE, or '1:1' HOUR_MINUTE
            // in BST). No PHP post-adjustment.
            $sentstatustext = $row->sentstatustext ?? '';
            // timesent is the actual time when SMS was sent to provider (stored in Europe/London timezone)
            $timesent = $row->timesent ?? '';

            // Get delivery status
            $rowData = DB::table($tableName)->where('id', $stateViewIdx)->first();
            // OLD SYSTEM parity: non-final states ('Unknown'=reason 6, 'acked', 'buffered*') show as
            // "In Transit"; empty falls back to "Pending"; final states pass through unchanged.
            $delivery_status_new = deliveryStatus2DisplayLabel($rowData->deliverystatus2 ?? '') ?: 'Pending';

            $smsDetails = [
                'Date_Submitted' => $timesubmitted,
                'Send_at_time' => $dosendtime,
                'Sent_at_Time' => $timesent ?: 'N/A',  // Actual send time (when SMS was sent to provider)
                'Date_Finalised' => $deliverytime2 ?: 'No information available',  // OLD SYSTEM "Date Finalised" — DLR settled time (cp2_sentlog.inc empty-value text)
                'Delivery_Time' => $deliverytime2 ?: 'N/A',  // Delivery receipt time (when handset received)
                'Sender' => $senderid,
                'Message' => $text,
                'Sent_To' => $to,
                'Sent_By' => sanitiseStringForUserDisplay($initiator),
                'Message_Status' => $sentstatustext ?: 'Message sent successfully',
                'Delivery_Status' => $delivery_status_new,
                // OLD SYSTEM shows "Reason: '<upstream_errormessage>'" below Delivery Status
                // when upstream_errormessage is non-empty (cp2_sentlog.inc:908).
                'Upstream_Reason' => sanitiseStringForUserDisplay($row->upstream_errormessage ?? ''),
                // OLD SYSTEM "User defined log info" row (cp2_sentlog.inc:920).
                'User_Defined' => urldecode($row->userdefined ?? ''),
                'Recipient_Cost' => ($requested_route < 10000) ? 'n/a' : '£ ' . format_four_decimal($userprice),
                'Cost_to_You' => '£ ' . format_four_decimal($userprice),
            ];

            // Debug info — shown in a SEPARATE "Debug Information" card, only when ?debug=true.
            // `meta` always surfaces which physical table the row came from (live smsg_log vs an
            // smsg_log_YYMM archive), the row id, and its migration flag (new/old). Additionally,
            // ?show_debug_columns=id,mobnum,text lists those exact smsg_log columns + their values.
            $debug = null;
            if ($request->boolean('debug')) {
                $flag = $rowData->migration_flag ?? null;
                $meta = [
                    'Source Table'     => $tableName,
                    'Record ID'        => $id,
                    'Migration Flag'   => ($flag === null || $flag === '') ? '(empty)' : $flag,
                    'Migration Status' => ($flag === 'new') ? 'NEW system' : 'OLD system',
                ];

                // Requested raw columns: ?show_debug_columns=id,name,mobnum
                $columns = [];
                $requested = trim((string) $request->get('show_debug_columns', ''));
                if ($requested !== '') {
                    $validCols = Schema::getColumnListing($stateViewTbl);
                    $rowArr = (array) $rowData;
                    foreach (explode(',', $requested) as $col) {
                        $col = trim($col);
                        if ($col === '') {
                            continue;
                        }
                        if (in_array($col, $validCols, true)) {
                            $val = $rowArr[$col] ?? null;
                            $columns[$col] = ($val === null) ? '(null)' : (string) $val;
                        } else {
                            $columns[$col] = '(no such column)';
                        }
                    }
                }

                $debug = ['meta' => $meta, 'columns' => $columns];
            }

            return view('customer.sent_sms.show', [
                'smsDetails' => $smsDetails,
                'tableName' => $tableName,
                'id' => $id,
                'filterParams' => $filterParams,
                'debug' => $debug,
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading SMS details page: ' . $e->getMessage());
            $filterParams = $request->except(['tableName', 'id', '_token']);
            return view('customer.sent_sms.show', ['error' => 'Error loading message details', 'filterParams' => $filterParams]);
        }
    }

    public function deliveryReceipt(Request $request)
    {

        if ($request->isMethod('get')) {
            $userInfo = Session::get('user_info');
            $userOptionDetails = DB::table('useroption')->where('userref', $userInfo['bigid'])->first();
            $resultArray = [
                'missidn' => '447777111111',
                'submission_reference' => '12345678901234567890123456789012',
                'retries' => $userOptionDetails->dreceipt_retries_wait_mins ?? '',
                'attempts' => $userOptionDetails->dreceipt_tries_num ?? '',
                'url' => $userOptionDetails->dreceipt_push_url ?? '',
            ];
            return view('customer.delivery_receipt.index', $resultArray);
        } elseif ($request->isMethod('post') && $request->form_name === 'delivery_receipts') {
            $validatedData = $request->validate([
                'url' => 'required|url'
            ]);

            $userInfo = Session::get('user_info');

            DB::table('useroption')
                ->where('userref', $userInfo['bigid'])
                ->update(['dreceipt_push_url' => $request->url]);

            // useroption changed → rebuild this account's cache so sends/DLRs use the new URL (Phase 2).
            app(\App\Services\TableCache::class)->rebuildUseroption($userInfo['bigid']);

            return back()->with('success', 'Delivery receipt url updated successfully.');
        } elseif ($request->isMethod('post') && $request->form_name === 'test_delivery_receipts') {

            $userInfo = Session::get('user_info');
            $ret = "";
            $objInfo = DB::table('useroption')->where('userref', $userInfo['bigid'])->first();


            $url    = $objInfo->dreceipt_push_url;
            $params = '<?xml version="1.0" encoding="ISO-8859-1"?>
        <itagg_delivery_receipt>
            <version>1.1</version>
            <msisdn>' . $request->missidn . '</msisdn>
            <submission_ref>' . $request->submission_reference . '</submission_ref>
            <status>Delivered</status>
            <reason>4</reason>
            <gmt_timestamp>' . date("YmdHis") . '</gmt_timestamp>
            <retry>0</retry>
        </itagg_delivery_receipt>';


            $startTime = time();
            $ch        = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // timeout after 30 seconds
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'xml=' . $params);
            $returned = curl_exec($ch);
            $errno    = curl_errno($ch);
            $error    = curl_error($ch);
            if ($errno != CURLE_OK) {
                $result = "Error: $errno - $error";
            } else {
                $result = "Response from your server: 200 ";
            }
            curl_close($ch);
            $endTime = time();

            $ret = "<hr><p>" . $result . "</p><p>Time taken: " . ($endTime - $startTime) . " seconds.</p>";
            return back()->with('success', $ret);
        }
    }

    public function receivedsms(Request $request)
    {

        $userInfo = Session::get('user_info');
        $date = date('Y-m-d');

        // Perform the query
        $itaggs = DB::table('itagg_instance')
            ->join('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
            ->select('itagg_instance.id as id', 'keyword', 'number')
            ->where('users_bigid', $userInfo['bigid'])
            ->where('status', 1)
            ->where('expiry', '>=', $date)
            ->get()
            ->map(function ($row) {
                return (array) $row; // Convert object to array for consistency
            })
            ->toArray();

        // Add additional rows to the $itaggs array
        $itaggs[] = ['id' => -1, 'keyword' => 'STOPs', 'number' => '60300/80809'];
        $itaggs[] = ['id' => -2, 'keyword' => 'STOPs', 'number' => '447786201088'];
        if (!$request->all()) {

            $resultArray = [
                'itaggs' => $itaggs,
                'selectedtagg' => '',
                'records' => '',
                'totalPages' => '',
                'currentPage' => '',
                'perPage' => '',
                'perPageOptions' => '',
                'totalRecords' => '',
                'chkitagg' => 'checked',
                'chkcontrolpanel' => 'checked',
                'chkapi' => 'checked',
                'chkemail' => 'checked',
                'chkmobyclip' => 'checked',
                'selRoutes' => 'all',
                'selDelivery' => 'all',
                'logMatch' => '',
                'mobile' => '',
                'start_date' => date('Y-m-d'),
                'start_hh' => '00',
                'start_mm' => '',
                'end_date' => date('Y-m-d'),
                'end_hh' => '23',
                'end_mm' => '55',
                'export' => ''
            ];
            return view('customer.receivedsms.index', $resultArray);
        }

        $userInfo = Session::get('user_info');
        $incoming_clause = DB::table('itagg_incominglog')->where('user_bigid', $userInfo['bigid']);

        // Build the query based on $STATE_itagg
        if ($request->selectedtagg == 'All Incoming') {
            // No additional conditions for "All Incoming"
        } elseif ($request->selectedtagg == '-1') {
            $incoming_clause->whereRaw("LOWER(msg) LIKE '%stop%'")
                ->whereIn('dest', ['60300', '80809']);
        } elseif ($request->selectedtagg == '-2') {
            $incoming_clause->whereRaw("LOWER(msg) LIKE '%stop%'")
                ->where('dest', '447786201088');
        } else {
            // Specific iTAGG instance logic
            $itaggInstance = DB::table('itagg_instance')
                ->join('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->where('itagg_instance.id', $request->selectedtagg)
                ->first();

            if ($itaggInstance) {
                if ($itaggInstance->keyword !== '*') {
                    $incoming_clause->where('keyword', 'LIKE', $itaggInstance->keyword);
                }

                $incoming_clause->where('dest', 'LIKE', $itaggInstance->number);
            }
        }

        // Apply the user's Start/End Date & Time filter to ALL cases. `recieved` is a MySQL
        // DATETIME ('Y-m-d H:i:s'), so build the bounds in the same format from start_date +
        // start_hh:start_mm (seconds 00) and end_date + end_hh:end_mm (seconds 59). This was
        // previously NOT applied — the date pickers had no effect on the results/export.
        $startDate = trim((string) $request->input('start_date'));
        $endDate   = trim((string) $request->input('end_date'));
        $startHh   = str_pad((string) $request->input('start_hh', '00'), 2, '0', STR_PAD_LEFT);
        $startMm   = str_pad((string) $request->input('start_mm', '00'), 2, '0', STR_PAD_LEFT);
        $endHh     = str_pad((string) $request->input('end_hh', '23'), 2, '0', STR_PAD_LEFT);
        $endMm     = str_pad((string) $request->input('end_mm', '59'), 2, '0', STR_PAD_LEFT);

        $startStamp = $startDate !== '' ? date('Y-m-d', strtotime($startDate)) . " {$startHh}:{$startMm}:00" : null;
        $endStamp   = $endDate   !== '' ? date('Y-m-d', strtotime($endDate)) . " {$endHh}:{$endMm}:59" : null;

        if ($startStamp !== null && $endStamp !== null) {
            $incoming_clause->whereBetween('recieved', [$startStamp, $endStamp]);
        } elseif ($startStamp !== null) {
            $incoming_clause->where('recieved', '>=', $startStamp);
        } elseif ($endStamp !== null) {
            $incoming_clause->where('recieved', '<=', $endStamp);
        }

        // Pagination
        $perPageOptions = [20, 50, 100, 250, 500];
        $perPage = in_array($request->get('perPage', 20), $perPageOptions) ? $request->get('perPage', 20) : 20;
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;

        // Capture a clean (WHERE-only) copy BEFORE adding select/offset/limit, so the
        // total count and the export aren't corrupted by pagination.
        $baseQuery = clone $incoming_clause;

        // Total count from the UNPAGINATED query. Calling ->count() after
        // ->offset()/->limit() runs "SELECT count(*) ... LIMIT x OFFSET y"; on page 2+
        // the OFFSET skips the single count row, so it returned 0 (Total: 0 /
        // "Page 2 of 0"). Counting a clean clone avoids that.
        $totalRecords = (clone $baseQuery)->count();
        $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $perPage) : 0;

        // Final query with pagination
        // Order by original recieved column (YmdHis format) for correct chronological sorting
        // Use table prefix to avoid ambiguity with the formatted alias
        $results = $incoming_clause->select('id', 'source', 'msg', DB::raw("DATE_FORMAT(itagg_incominglog.recieved, '%W, %D %M %Y %r') as recieved"), 'network', 'dest', 'msisdnAlias')
            ->orderByRaw('itagg_incominglog.recieved DESC')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Convert results to array
        $convertResultdata = json_decode(json_encode($results), true);

        $resultArray = [
            'itaggs' => $itaggs,
            'selectedtagg' => $request->selectedtagg,
            'data' => $convertResultdata,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRecords' => $totalRecords,
            'start_date' => (isset($request->start_date) && !empty($request->start_date)) ? $request->start_date : '',
            'start_hh' => (isset($request->start_hh) && !empty($request->start_hh)) ? $request->start_hh : '',
            'start_mm' => (isset($request->start_mm) && !empty($request->start_mm)) ? $request->start_mm : '',
            'end_date' => (isset($request->end_date) && !empty($request->end_date)) ? $request->end_date : '',
            'end_hh' => (isset($request->end_hh) && !empty($request->end_hh)) ? $request->end_hh : '',
            'end_mm' => (isset($request->end_mm) && !empty($request->end_mm)) ? $request->end_mm : '',
            'export' => $this->receviedSMSexportToCsv(json_decode(json_encode(
                (clone $baseQuery)
                    ->select('id', 'source', 'msg', DB::raw("DATE_FORMAT(itagg_incominglog.recieved, '%W, %D %M %Y %r') as recieved"), 'network', 'dest', 'msisdnAlias')
                    ->orderByRaw('itagg_incominglog.recieved DESC')
                    ->get()
            ), true)),

        ];
        return view('customer.receivedsms.index', $resultArray);
    }

    public function keywords()
    {
        $userInfo = Session::get('user_info');

        $itaggs = DB::table('itagg_instance')
            ->select(
                'keyword',
                'smsshortcodes.number',
                'itagg_type.description',
                'response_smsshortcodes_id',
                'forwarding_url',
                'forwarding_email',
                'advertise',
                'itagg_instance.id',
                'response_sender_id',
                'response_content',
                'itagg_type.id as itagg_type_id',
                'expiry',
                'must_respond',
                'itagg_instance.module_restrict as itagg_restrict',
                'smsshortcodes.module_restrict as smsshortcodes_restrict'
            )
            ->join('itagg_type', 'itagg_instance.itagg_type_id', '=', 'itagg_type.id')
            ->leftJoin('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('users_bigid', $userInfo['bigid'])
            ->where('itagg_instance.status', 1)
            ->orderBy('keyword')
            ->get()
            ->toArray();

        $resultArray = [
            'itaggs' => json_decode(json_encode($itaggs), true),
        ];

        return view('customer.keywords.index', $resultArray);
    }

    /**
     * Send WhatsApp Message using Nexmo WhatsApp Business API
     */
    private function sendWhatsAppMessage($recipients, $message, $from, $bigid, $userref, $datenow)
    {
        $successCount = 0;
        $failedCount = 0;
        $whatsappResults = [];

        // Get Nexmo credentials from environment
        $nexmoApiKey = env('NEXMO_API_KEY');
        $nexmoApiSecret = env('NEXMO_API_SECRET');
        $nexmoWhatsAppNumber = $from; // Your WhatsApp Business number

        if (!$nexmoApiKey || !$nexmoApiSecret || !$nexmoWhatsAppNumber) {
            Log::error('Nexmo WhatsApp credentials not configured');
            return back()->with('error', 'WhatsApp service is not configured. Please contact administrator.');
        }

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
                $thecountrycodes = $countryInfo ? $countryInfo->dialcode : '44';

                // Format phone number for WhatsApp (must include country code)
                $formattedNumber = $thenum;
                if (substr($formattedNumber, 0, 1) !== '+') {
                    $formattedNumber = '+' . $formattedNumber;
                }
                $userData = User::where('bigid', $userref)->first();
                // Insert into smsg_log for record keeping
                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => 'whatsapp',
                    'bigid' => $bigid,
                    'mobnum' => $thenum,
                    'numparts' => 1, // WhatsApp messages are counted as single units
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
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => 0.000000,
                    'countrydialcode' => $thecountrycodes ?? '',
                    'suppliername' => 'Nexmo WhatsApp',
                    'supplierrouteref' => '',
                    'requested_route' => 0,
                    'requested_routetag' => '',
                    'deliverystatus2' => 'pending',
                    'migration_flag' => 'new',
                ]);

                // Send WhatsApp message via Nexmo API
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
                    $whatsappResults[] = [
                        'message_id' => $messageId,
                        'mobile' => $thenum,
                        'status' => 'sent'
                    ];

                    // Update smsg_log with success
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'userprice' => $userData ? $userData->whatsapp_price : 0.000000,
                            'sentstatus' => 'ok',
                            'sentstatustext' => 'WhatsApp message sent',
                            'suppliermsgref' => $messageId,
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                            'deliverystatus2' => 'Delivered'
                        ]);

                    Log::info('WhatsApp message sent successfully', [
                        'message_id' => $messageId,
                        'mobile' => $thenum,
                        'bigid' => $bigid
                    ]);
                } else {
                    $failedCount++;
                    $errorMessage = $response->json()['error_text'] ?? 'Unknown error';

                    Log::error('Failed to send WhatsApp message', [
                        'mobile' => $thenum,
                        'error' => $errorMessage,
                        'response' => $response->body()
                    ]);

                    // Update smsg_log with failure
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            // 'userprice' => $userData ? $userData->whatsapp_price : 0.000000,
                            'sentstatus' => 'ok',
                            'sentstatustext' => 'WhatsApp send failed: ' . $errorMessage,
                            'deliverystatus2' => 'Failed',
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                        ]);
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Exception while sending WhatsApp message', [
                    'mobile' => $thenum,
                    'error' => $e->getMessage()
                ]);

                if (isset($smsgLogId)) {
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'userprice' => $userData ? $userData->whatsapp_price : 0.000000,
                            'sentstatus' => 'fail',
                            'sentstatustext' => 'Exception: ' . $e->getMessage(),
                            'deliverystatus2' => 'Failed',
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                        ]);
                }
            }
        }

        // Return response based on results
        if ($successCount > 0) {
            $message = "WhatsApp messages sent successfully! {$successCount} message(s) delivered.";
            if ($failedCount > 0) {
                $message .= " {$failedCount} message(s) failed.";
            }
            return back()->with('success', $message);
        } else {
            return back()->with('error', 'Failed to send WhatsApp messages. Please check the logs.');
        }
    }

    /**
     * Send WhatsApp Template Message (for business-initiated conversations)
     */
    public function sendWhatsAppTemplate(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required',
            'template_name' => 'required',
            'template_namespace' => 'required',
            'template_parameters' => 'array'
        ]);

        $nexmoApiKey = env('NEXMO_API_KEY');
        $nexmoApiSecret = env('NEXMO_API_SECRET');
        $nexmoWhatsAppNumber = env('NEXMO_WHATSAPP_NUMBER');

        $to = $validated['to'];
        if (substr($to, 0, 1) !== '+') {
            $to = '+' . $to;
        }

        $parameters = [];
        if (isset($validated['template_parameters'])) {
            foreach ($validated['template_parameters'] as $param) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => $param
                ];
            }
        }

        try {
            $response = Http::withBasicAuth($nexmoApiKey, $nexmoApiSecret)
                ->post('https://api.nexmo.com/v1/messages', [
                    'from' => [
                        'type' => 'whatsapp',
                        'number' => $nexmoWhatsAppNumber
                    ],
                    'to' => [
                        'type' => 'whatsapp',
                        'number' => $to
                    ],
                    'message' => [
                        'content' => [
                            'type' => 'template',
                            'template' => [
                                'name' => $validated['template_name'],
                                'namespace' => $validated['template_namespace'],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => $parameters
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp template message sent successfully',
                    'message_id' => $responseData['message_uuid'] ?? ''
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $response->json()['error_text'] ?? 'Failed to send template message'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp template', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }

    public function config($itagg_id, $keyword)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'] ?? null;

        // Fetch iTAGG data with module restrictions (similar to getiTAGGByID function)
        $itaggData = DB::table('itagg_instance')
            ->select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'smsshortcodes.number as shortcode',
                'itagg_type.description',
                'itagg_instance.response_smsshortcodes_id',
                'itagg_instance.forwarding_url',
                'itagg_instance.forwarding_email',
                'itagg_instance.response_sender_id as senderid',
                'itagg_instance.response_content as content',
                'itagg_type.id as type_id',
                'itagg_instance.expiry',
                'smsshortcodes.id as smsshortcodes_id',
                'smsshortcodes.module_restrict as smsshortcodes_module_restrict',
                'itagg_instance.module_restrict as itagg_module_restrict',
                'smsshortcodes.show_cp_subkeyword_management as cp_sk_management',
                'smsshortcodes.cost as tariff'
            )
            ->join('itagg_type', 'itagg_instance.itagg_type_id', '=', 'itagg_type.id')
            ->join('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('itagg_instance.id', $itagg_id)
            ->first();

        if (!$itaggData) {
            return back()->with('error', 'iTAGG instance not found.');
        }

        // Determine if this is a subkeyword or the main keyword
        $isSubkeyword = false;
        $subkeyword = '';

        // URL keyword might be the main keyword or a subkeyword
        // If URL keyword doesn't match the main keyword, it's a subkeyword
        if ($keyword !== $itaggData->keyword && urldecode($keyword) !== $itaggData->keyword) {
            $isSubkeyword = true;
            $subkeyword = $keyword;
        }

        // Check if currently configuring the * keyword (main keyword is * and NOT configuring a subkeyword)
        // This is the ONLY case where we show only Email Forwarder
        $isStarKeyword = ($itaggData->keyword === '*' && !$isSubkeyword);

        // Calculate combined module restriction (bitwise AND of shortcode and itagg restrictions)
        $codeRestrict = intval($itaggData->smsshortcodes_module_restrict);
        $itaggRestrict = intval($itaggData->itagg_module_restrict);
        $moduleRestrict = $codeRestrict & $itaggRestrict;

        // Module bit definitions (from the legacy code)
        $moduleBits = [
            'smsResponder' => 1,
            'Forwarder' => 2,           // Email Forwarder / Developer package
            'SMSForwarder' => 4,
            'BusinessCard' => 8,
            'Subscription' => 16,
            'WAPPushResponder' => 32,
            'LocationAndMapping' => 64,
            'WapPageResponse' => 128,
            'Voting' => 256,
            'MapResponse' => 512,
            'ApplicationDownload' => 1024,
            'EmailForwarder' => 2048,
            'LocationPLUS' => 4096,
            'MMSEmailForwarder' => 8192,
            'MMSForwarder' => 16384,
            'MMSForwarderPlus' => 32768,
        ];

        // Determine which modules are enabled
        $enabledModules = [];

        // Show only Email Forwarder for main keyword * (not subkeyword)
        // All other cases (subkeywords, other main keywords) show all modules
        if ($isStarKeyword) {
            // Only enable Email Forwarder for main keyword *
            foreach ($moduleBits as $name => $bit) {
                $enabledModules[$name] = ($name === 'Forwarder' || $name === 'EmailForwarder');
            }
        } else {
            // For ALL other cases: subkeywords (including Dedicated Number), main keywords (non-*)
            // Check module_restrict bits first
            foreach ($moduleBits as $name => $bit) {
                $enabledModules[$name] = ($moduleRestrict & $bit) === $bit;
            }

            // If no modules are enabled from module_restrict, enable all common modules
            $hasAnyModule = false;
            foreach ($enabledModules as $enabled) {
                if ($enabled) {
                    $hasAnyModule = true;
                    break;
                }
            }

            if (!$hasAnyModule) {
                // Enable all standard modules if none are set in module_restrict
                $enabledModules['smsResponder'] = true;
                $enabledModules['Forwarder'] = true;
                $enabledModules['EmailForwarder'] = true;
                $enabledModules['SMSForwarder'] = true;
                $enabledModules['BusinessCard'] = true;
                $enabledModules['Subscription'] = true;
                $enabledModules['WAPPushResponder'] = true;
                $enabledModules['Voting'] = true;
            }
        }

        // Fetch available modules from database
        $modules = DB::table('itagg_module')
            ->select('name', 'priority', 'is_public', 'type', 'id')
            ->where('type', '<>', 'cdp')
            ->where('type', '<>', 'mms')
            ->where('is_public', 1)
            ->orderBy('type', 'asc')
            ->orderBy('priority', 'asc')
            ->get();

        // Show subkeyword management based on cp_sk_management flag
        $showSubkeywordManagement = ($itaggData->cp_sk_management == 1);
        // echo"<pre>";print_R($enabledModules);exit;
        return view('customer.keywords.config', [
            'modules' => $modules,
            'itaggData' => $itaggData,
            'moduleRestrict' => $moduleRestrict,
            'enabledModules' => $enabledModules,
            'showSubkeywordManagement' => $showSubkeywordManagement,
            'maxSubkeywords' => 0, // No limit
            'isStarKeyword' => $isStarKeyword,
            'keyword' => $keyword, // Pass URL keyword (could be subkeyword)
            'itaggId' => $itagg_id,
            'isSubkeyword' => $isSubkeyword,
            'subkeyword' => $subkeyword,
        ]);
    }

    /**
     * Subkeyword Configuration Page
     * This handles URLs like /keyword/{itaggId}/{subkeyword}/config
     */
    public function subkeywordConfig($itagg_id, $subkeyword)
    {
        // Reuse the main config method with the subkeyword
        return $this->config($itagg_id, $subkeyword);
    }

    /**
     * Display SMS Responder page
     */
    public function smsResponder($itagg_id)
    {
        $userInfo = Session::get('user_info');

        // if (isset($userInfo['bigid'])) {
        $bigid = Session::get('user_info')['bigid'];
        // Get the itagg instance details
        $itaggData = DB::table('itagg_instance')
            ->select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'itagg_instance.response_sender_id',
                'itagg_instance.response_content',
                'itagg_instance.response_smsshortcodes_id',
                'itagg_instance.allowed_mobile_update_numbers',
                'itagg_instance.allow_mobile_update_across_subkeys',
                'smsshortcodes.number as shortcode_number'
            )
            ->leftJoin('smsshortcodes', 'itagg_instance.response_smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.id', $itagg_id)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->orderBy('smsshortcodes.id', 'desc')
            ->first();

        if (!$itaggData) {
            return back()->with('error', 'iTAGG instance not found.');
        }

        // Get available SMS short codes for the response route dropdown
        // Using inner join with itagg_instance to get only relevant shortcodes for this user
        $smsShortcodes = DB::table('smsshortcodes')
            ->select(
                'smsshortcodes.id',
                'smsshortcodes.number'
            )
            ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
            ->orderBy('smsshortcodes.number')
            ->get();

        return view('customer.keywords.sms_responder', [
            'itaggData' => $itaggData,
            'smsShortcodes' => $smsShortcodes,
            'itagg_id' => $itagg_id
        ]);
    }

    /**
     * Save SMS Responder settings
     * Handles BOTH main keyword (itagg_instance) AND subkeyword (itagg_subkeyword) updates
     */
    public function savesmsResponder(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'itagg_id' => 'required|integer',
            'senderid' => 'required|string|max:11',
            'responsetext' => 'required|string',
            'responseroute' => 'required|integer',
            'allowedUpdateNumbers' => 'nullable|string',
            'allowSubkeys' => 'required|in:0,1',
            'subkeyword' => 'nullable|string',
            'advertise' => 'nullable|in:0,1'
        ]);

        $itagg_id = $validated['itagg_id'];
        $senderid = $validated['senderid'];
        $responsetext = $validated['responsetext'];
        $responseroute = $validated['responseroute'];
        $allowedUpdateNumbers = $validated['allowedUpdateNumbers'] ?? '';
        $allowSubkeys = $validated['allowSubkeys'];
        $subkeyword = $request->input('subkeyword', '');
        $advertise = $request->input('advertise', '0');

        try {
            if ($subkeyword == '') {
                // Update is required to the main iTAGG itself
                $updated = DB::table('itagg_instance')
                    ->where('id', $itagg_id)
                    ->update([
                        'response_sender_id' => $senderid,
                        'response_content' => urlencode($responsetext),
                        'response_smsshortcodes_id' => $responseroute,
                        'allowed_mobile_update_numbers' => $allowedUpdateNumbers,
                        'allow_mobile_update_across_subkeys' => $allowSubkeys
                    ]);
            } else {
                // Update is required to an iTAGG's subkeyword
                $updated = DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $itagg_id)
                    ->where('keyword', $subkeyword)
                    ->update([
                        'response_sender_id' => $senderid,
                        'response_content' => urlencode($responsetext),
                        'response_smsshortcodes_id' => $responseroute
                    ]);
            }

            // The update to the Advertising state value is held at the iTAGG level - update this separately
            DB::table('itagg_instance')
                ->where('id', $itagg_id)
                ->update(['advertise' => $advertise]);

            Log::info('SMS Responder updated successfully', [
                'itagg_id' => $itagg_id,
                'sender_id' => $senderid,
                'response_route' => $responseroute,
                'subkeyword' => $subkeyword
            ]);

            return back()->with('success', 'SMS Responder settings updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating SMS Responder', [
                'error' => $e->getMessage(),
                'itagg_id' => $itagg_id,
                'subkeyword' => $subkeyword
            ]);

            return back()->with('error', 'Failed to update SMS Responder settings: ' . $e->getMessage());
        }
    }

    /**
     * Display Email Forwarder  page
     */

    public function emailForwarder($itagg_id)
    {

        $userInfo = Session::get('user_info');

        // if (isset($userInfo['bigid'])) {
        $bigid = Session::get('user_info')['bigid'];
        // Get the itagg instance details
        $itaggData = DB::table('itagg_instance')
            ->select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'itagg_instance.response_sender_id',
                'itagg_instance.response_content',
                'itagg_instance.response_smsshortcodes_id',
                'itagg_instance.allowed_mobile_update_numbers',
                'itagg_instance.allow_mobile_update_across_subkeys',
                'itagg_instance.forwarding_email',
                'itagg_instance.forwarding_url',
                'smsshortcodes.number as shortcode_number'
            )
            ->leftJoin('smsshortcodes', 'itagg_instance.response_smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.id', $itagg_id)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->orderBy('smsshortcodes.id', 'desc')
            ->first();

        if (!$itaggData) {
            return back()->with('error', 'iTAGG instance not found.');
        }

        // Get available SMS short codes for the response route dropdown
        // Using inner join with itagg_instance to get only relevant shortcodes for this user
        $smsShortcodes = DB::table('smsshortcodes')
            ->select(
                'smsshortcodes.id',
                'smsshortcodes.number'
            )
            ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
            ->orderBy('smsshortcodes.number')
            ->get();

        return view('customer.keywords.email_forwarder', [
            'itaggData' => $itaggData,
            'smsShortcodes' => $smsShortcodes,
            'itagg_id' => $itagg_id
        ]);
    }

    /**
     * Save Email Forwarder settings
     * Handles BOTH main keyword (itagg_instance) AND subkeyword (itagg_subkeyword) updates
     */
    public function saveEmailForwarder(Request $request)
    {
        // Validate fields
        $request->validate([
            'itagg_id' => 'required|integer',
            'email_address' => 'nullable|string',
            'url_address' => 'nullable|string|max:2000',
            'subkeyword' => 'nullable|string'
        ]);

        $itagg_id       = $request->itagg_id;
        $email_address  = $request->email_address ?? '';
        $url_address    = $request->url_address ?? '';
        $subkeyword     = $request->input('subkeyword', '');

        try {
            if ($subkeyword == '') {
                // Update is required to the main iTAGG itself
                $updated = DB::table('itagg_instance')
                    ->where('id', $itagg_id)
                    ->update([
                        'forwarding_email' => $email_address,
                        'forwarding_url' => $url_address,
                    ]);
            } else {
                // Update is required to an iTAGG's subkeyword
                $updated = DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $itagg_id)
                    ->where('keyword', $subkeyword)
                    ->update([
                        'forwarding_email' => $email_address,
                        'forwarding_url' => $url_address,
                    ]);
            }

            Log::info('Email Forwarder updated successfully', [
                'itagg_id' => $itagg_id,
                'email_address' => $email_address,
                'url_address' => $url_address,
                'subkeyword' => $subkeyword
            ]);

            return back()->with('success', 'Email Forwarder updated successfully.');
        } catch (\Exception $e) {
            Log::error('Error updating Email Forwarder', [
                'error' => $e->getMessage(),
                'itagg_id' => $itagg_id,
                'subkeyword' => $subkeyword
            ]);

            return back()->with('error', 'Failed to update Email Forwarder: ' . $e->getMessage());
        }
    }

    /**
     * Display SMS Forwarder  page
     */

    public function SMSForwarder($itagg_id)
    {

        $userInfo = Session::get('user_info');

        // if (isset($userInfo['bigid'])) {
        $bigid = Session::get('user_info')['bigid'];
        // Get the itagg instance details
        $itaggData = DB::table('itagg_instance')
            ->select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'itagg_instance.response_sender_id',
                'itagg_instance.response_content',
                'itagg_instance.response_smsshortcodes_id',
                'itagg_instance.allowed_mobile_update_numbers',
                'itagg_instance.allow_mobile_update_across_subkeys',
                'itagg_instance.forwarding_email',
                'itagg_instance.forwarding_url',
                'smsshortcodes.number as shortcode_number'
            )
            ->leftJoin('smsshortcodes', 'itagg_instance.response_smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.id', $itagg_id)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->orderBy('smsshortcodes.id', 'desc')
            ->first();

        if (!$itaggData) {
            return back()->with('error', 'iTAGG instance not found.');
        }

        // Get available SMS short codes for the response route dropdown
        // Using inner join with itagg_instance to get only relevant shortcodes for this user
        $smsShortcodes = DB::table('smsshortcodes')
            ->select(
                'smsshortcodes.id',
                'smsshortcodes.number'
            )
            ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
            ->orderBy('smsshortcodes.number')
            ->get();

        $sms_mobile = DB::table('itagg_module_SMSForwarder')
            ->where('keyword', $itaggData->keyword)
            ->where('subkeyword', '')
            ->where('smsshortcode_number', $itaggData->shortcode_number)
            ->first();

        if (isset($userInfo['bigid'])) {
            $user = User::with('reminders')->where('bigid', $bigid)->first();
            if ($user) {
                $smsg_wallet = $user->smsg_wallet;
                $smsg_server1_sent = $user->smsg_server1_sent;
                $smsg_server2_sent = $user->smsg_server2_sent;
                $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;
            }
        }


        return view('customer.keywords.sms_forwarder', [
            'itaggData' => $itaggData,
            'smsShortcodes' => $smsShortcodes,
            'itagg_id' => $itagg_id,
            'sms_mobile' => $sms_mobile,
            'wallet_bal' => $remaining_wallet
        ]);
    }

    /**
     * Save SMS Forwarder settings
     */
    public function saveSMSForwarder(Request $request)
    {
        // Basic validation
        $request->validate([
            'itagg'         => 'required|string',
            'subkeyword'    => 'nullable|string',
            'smsshortcode'  => 'required|integer',
            'fwd_mobile'    => 'required|string',
        ]);

        // echo "<pre>";print_r($request->all());exit;

        $keyword     = $request->itagg;
        $subkeyword  = $request->input('subkeyword', '');
        $shortcode   = $request->smsshortcode;
        $fwd_mobile  = trim($request->fwd_mobile);

        // Split on commas, strip ALL internal whitespace from each number (old system did
        // str_replace(array(" ","\n","\r"),"",...) before forwarding), drop empties.
        $numbers = array_values(array_filter(array_map(
            fn($n) => preg_replace('/\s+/', '', $n),
            explode(',', $fwd_mobile)
        )));

        // Old getValidState() accepted any international list: \+?[0-9]{10,20}.
        $intlPattern = '/^\+?[0-9]{10,20}$/';
        foreach ($numbers as $mobile) {
            if (!preg_match($intlPattern, $mobile)) {
                return back()->withErrors([
                    'fwd_mobile' => "The following number is invalid: $mobile"
                ])->withInput();
            }
        }

        // Persist the cleaned, comma-joined list (no stray spaces).
        $fwd_mobile = implode(',', $numbers);

        try {
            // Does the row exist? (Legacy SELECT equivalent)
            $existing = DB::table('itagg_module_SMSForwarder')
                ->where('keyword', $keyword)
                ->where('subkeyword', $subkeyword)
                ->where('smsshortcode_number', $shortcode)
                ->first();

            if ($existing) {
                // Update (Legacy UPDATE equivalent)
                DB::table('itagg_module_SMSForwarder')
                    ->where('keyword', $keyword)
                    ->where('subkeyword', $subkeyword)
                    ->where('smsshortcode_number', $shortcode)
                    ->update([
                        'fwd_mobile' => $fwd_mobile,
                    ]);

                return back()->with('success', 'SMS Forwarder updated successfully.');
            } else {
                // Insert (Legacy INSERT equivalent)
                DB::table('itagg_module_SMSForwarder')->insert([
                    'keyword'              => $keyword,
                    'subkeyword'           => $subkeyword,
                    'fwd_mobile'           => $fwd_mobile,
                    'smsshortcode_number'  => $shortcode
                ]);

                return back()->with('success', 'SMS Forwarder created successfully.');
            }
        } catch (\Exception $e) {

            Log::error('SMS Forwarder Error', [
                'error' => $e->getMessage(),
                'keyword' => $keyword,
                'subkeyword' => $subkeyword,
                'shortcode' => $shortcode
            ]);

            return back()->with('error', 'Failed to update SMS Forwarder: ' . $e->getMessage());
        }
    }

    /**
     * Display Business Card page
     */
    public function BusinessCard($itaggId, $keyword)
    {
        return view('customer.keywords.business_card', compact('itaggId', 'keyword'));
    }

    /**
     * Save  Business Card settings
     */

    public function saveBusinessCard(Request $request)
    {

        return back()->with('success', 'Business Card updated successfully.');
    }

    /**
     * Display Subscription  page
     */

    public function Subscription($itagg_id)
    {

        $userInfo = Session::get('user_info');

        // if (isset($userInfo['bigid'])) {
        $bigid = Session::get('user_info')['bigid'];
        // Get the itagg instance details
        $itaggData = DB::table('itagg_instance')
            ->select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'itagg_instance.response_sender_id',
                'itagg_instance.response_content',
                'itagg_instance.response_smsshortcodes_id',
                'itagg_instance.allowed_mobile_update_numbers',
                'itagg_instance.allow_mobile_update_across_subkeys',
                'itagg_instance.forwarding_email',
                'itagg_instance.forwarding_url',
                'itagg_instance.users_bigid',
                'smsshortcodes.number as shortcode_number'
            )
            ->leftJoin('smsshortcodes', 'itagg_instance.response_smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.id', $itagg_id)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->orderBy('smsshortcodes.id', 'desc')
            ->first();

        if (!$itaggData) {
            return back()->with('error', 'iTAGG instance not found.');
        }

        // Get available SMS short codes for the response route dropdown
        // Using inner join with itagg_instance to get only relevant shortcodes for this user
        $smsShortcodes = DB::table('smsshortcodes')
            ->select(
                'smsshortcodes.id',
                'smsshortcodes.number'
            )
            ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
            ->orderBy('smsshortcodes.number')
            ->get();

        $subscription = DB::table('itagg_module_Subscription')
            ->where('user_bigid', $bigid)
            ->where('itagg_id', $itagg_id)
            ->first();


        return view('customer.keywords.subscription', [
            'itaggData' => $itaggData,
            'smsShortcodes' => $smsShortcodes,
            'itagg_id' => $itagg_id,
            'subscription' =>  $subscription
        ]);
    }


    /**
     * Save Subscription Card settings
     */

    public function saveSubscription(Request $request)
    {
        $request->validate([
            'itagg_id'                => 'required|integer',
            'subscriberesponsetext'   => 'required|string',
            'unsubscriberesponsetext' => 'required|string',
            'failureresponsetext'     => 'required|string',
        ]);

        $user_bigid = $request->big_id;
        $shortcode  = $request->smsshortcode;
        $itagg_id   = $request->itagg_id;
        $maxSubscribers = $request->txtMaxSubscribers ?? 0;
        $sendNumbers = $request->allowedSendNumbers ?? '';

        // Encode messages
        $subscribe_response    = urlencode($request->subscriberesponsetext);
        $unsubscribe_response  = urlencode($request->unsubscriberesponsetext);
        $fail_response         = urlencode($request->failureresponsetext);

        try {
            // 🔍 Check if subscription already exists
            $existing = DB::table('itagg_module_Subscription')
                ->where('user_bigid', $user_bigid)
                ->where('itagg_id', $itagg_id)
                ->where('smsshortcode_number', $shortcode)
                ->first();

            if ($existing) {
                // 🔄 Update existing row
                $updated = DB::table('itagg_module_Subscription')
                    ->where('user_bigid', $user_bigid)
                    ->where('itagg_id', $itagg_id)
                    ->where('smsshortcode_number', $shortcode)
                    ->update([
                        'subscribe_response'   => $subscribe_response,
                        'unsubscribe_response' => $unsubscribe_response,
                        'fail_response'        => $fail_response,
                        'max_subscribers'      => $maxSubscribers,
                        'send_mobiles'         => $sendNumbers
                    ]);

                if ($updated) {
                    return back()->with('success', 'Subscription updated successfully.');
                } else {
                    return back()->with('error', 'No changes were made or iTAGG instance not found.');
                }
            } else {
                // group_name is the load-bearing link to members (cp_users_groups.name).
                // Old CP builds a unique urlencoded name; mirror that and create the group.
                $subkeyword = $request->input('subkeyword', '');
                $groupName  = urlencode('itagg' . ($subkeyword !== '' ? " $subkeyword" : '')
                    . " ($shortcode) (" . date('YmdHis') . ")");

                // 🆕 Insert new subscription
                DB::table('itagg_module_Subscription')->insert([
                    'user_bigid'            => $user_bigid,
                    'itagg_id'              => $itagg_id,
                    'subkeyword'            => $subkeyword !== '' ? $subkeyword : null,
                    'group_name'            => $groupName,
                    'subscribe_response'    => $subscribe_response,
                    'unsubscribe_response'  => $unsubscribe_response,
                    'fail_response'         => $fail_response,
                    'smsshortcode_number'   => $shortcode,
                    'max_subscribers'      =>  $maxSubscribers,
                    'send_mobiles'         =>  $sendNumbers
                ]);

                // Create the members group row (old Subscription.class:501-506).
                DB::table('cp_users_groups')->insert([
                    'name'       => $groupName,
                    'user_bigid' => $user_bigid,
                ]);

                return back()->with('success', 'Subscription created successfully.');
            }
        } catch (\Exception $e) {
            Log::error('Subscription Error:', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Subscription failed: ' . $e->getMessage());
        }
    }


    /**
     * Display WAPPushResponder page
     */

    public function WAPPushResponder($itagg_id)
    {

        $userInfo = Session::get('user_info');

        // if (isset($userInfo['bigid'])) {
        $bigid = Session::get('user_info')['bigid'];
        // Get the itagg instance details
        $itaggData = DB::table('itagg_instance')
            ->select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'itagg_instance.response_sender_id',
                'itagg_instance.response_content',
                'itagg_instance.response_smsshortcodes_id',
                'itagg_instance.allowed_mobile_update_numbers',
                'itagg_instance.allow_mobile_update_across_subkeys',
                'itagg_instance.forwarding_email',
                'itagg_instance.forwarding_url',
                'itagg_instance.users_bigid',
                'itagg_instance.smsshortcodes_id',
                'smsshortcodes.number as shortcode_number'
            )
            ->leftJoin('smsshortcodes', 'itagg_instance.response_smsshortcodes_id', '=', 'smsshortcodes.id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.id', $itagg_id)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->orderBy('smsshortcodes.id', 'desc')
            ->first();

        if (!$itaggData) {
            return back()->with('error', 'iTAGG instance not found.');
        }

        // Get available SMS short codes for the response route dropdown
        // Using inner join with itagg_instance to get only relevant shortcodes for this user
        $smsShortcodes = DB::table('smsshortcodes')
            ->select(
                'smsshortcodes.id',
                'smsshortcodes.number'
            )
            ->join('itagg_instance', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
            ->where('itagg_instance.users_bigid', $bigid)
            ->where('itagg_instance.status', 1)
            ->where('itagg_instance.expiry', '>=', date('Y-m-d'))
            ->groupBy('smsshortcodes.id', 'smsshortcodes.number')
            ->orderBy('smsshortcodes.number')
            ->get();

        $Wappushresponder = DB::table('itagg_module_WAPPushResponder')
            ->where('response_smsshortcodes_id', $itaggData->smsshortcodes_id)
            ->first();


        return view('customer.keywords.wappushresponder', [
            'itaggData' => $itaggData,
            'smsShortcodes' => $smsShortcodes,
            'itagg_id' => $itagg_id,
            'Wappushresponder' =>  $Wappushresponder
        ]);
    }

    /**
     * Save WAPPushResponder settings
     */

    public function saveWAPPushResponder(Request $request)
    {
        // Validate inputs
        $request->validate([
            'title'               => 'required|string',
            'url'                 => 'required|string',
            'itagg'               => 'required|string',
            'subkeyword'          => 'nullable|string',
            'smsshortcode'        => 'required|integer',
        ]);

        $title          = $request->title;
        $url            = $request->url;
        $keyword        = $request->itagg;
        $subkeyword     = $request->subkeyword ?? '';
        $shortcode      = $request->smsshortcode;
        $shortcode_id   = $request->smsshortcodes_id;


        try {
            // 👉 Check if row exists (same as old SELECT query)
            $existing = DB::table('itagg_module_WAPPushResponder')
                ->where('keyword', $keyword)
                ->where('subkeyword', $subkeyword)
                ->where('smsshortcode_number', $shortcode)
                ->first();



            // 👉 If record exists → UPDATE
            if ($existing) {
                $updated = DB::table('itagg_module_WAPPushResponder')
                    ->where('keyword', $keyword)
                    ->where('subkeyword', $subkeyword)
                    ->where('smsshortcode_number', $shortcode)
                    ->update([
                        'title'                    => $title,
                        'url'                      => $url,
                    ]);

                if ($updated) {
                    return back()->with('success', 'WAP Push Responder updated successfully.');
                } else {
                    return back()->with('error', 'No changes were made or iTAGG instance not found.');
                }
            } else {
                // 👉 If no record → INSERT (same as old INSERT)
                DB::table('itagg_module_WAPPushResponder')->insert([
                    'title'                     => $title,
                    'url'                       => $url,
                    'keyword'                   => $keyword,
                    'subkeyword'                => $subkeyword,
                    'smsshortcode_number'       => $shortcode,
                    'response_smsshortcodes_id' => $shortcode_id

                ]);

                return back()->with('success', 'WAP Push Responder created successfully.');
            }
        } catch (\Exception $e) {
            Log::error('WAP Push Responder Error', [
                'error'      => $e->getMessage(),
                'keyword'    => $keyword,
                'subkeyword' => $subkeyword,
                'shortcode'  => $shortcode
            ]);

            return back()->with('error', 'Failed to update WAP Push Responder: ' . $e->getMessage());
        }
    }

    public function Voting($itaggId, $keyword)
    {
        return view('customer.keywords.voting', compact('itaggId', 'keyword'));
    }

    public function saveVoting(Request $request)
    {

        return back()->with('success', 'Voting updated successfully.');
    }

    /**
     * Get all subkeywords for an iTAGG instance
     */
    public function getSubkeywords($itagg_id)
    {
        try {
            $subkeywords = DB::table('itagg_subkeyword')
                ->select(
                    'itagg_subkeyword.keyword',
                    'itagg_subkeyword.response_smsshortcodes_id',
                    'itagg_subkeyword.forwarding_email',
                    'itagg_subkeyword.response_sender_id',
                    'itagg_subkeyword.response_content'
                )
                ->join('itagg_instance', 'itagg_subkeyword.itagg_instance_id', '=', 'itagg_instance.id')
                ->where('itagg_instance.id', $itagg_id)
                ->get();

            return response()->json([
                'success' => true,
                'subkeywords' => $subkeywords
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching subkeywords', [
                'itagg_id' => $itagg_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subkeywords: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new subkeyword
     */
    public function addSubkeyword(Request $request)
    {
        $request->validate([
            'itagg_id' => 'required|integer',
            'subkeyword' => 'required|string|max:50|regex:/^[A-Za-z0-9]+$/'
        ]);

        $itagg_id = $request->itagg_id;
        $subkeyword = strtoupper(trim($request->subkeyword));

        try {
            // Check if iTAGG instance exists
            $itaggInstance = DB::table('itagg_instance')->where('id', $itagg_id)->first();

            if (!$itaggInstance) {
                return response()->json([
                    'success' => false,
                    'message' => 'iTAGG instance not found'
                ], 404);
            }

            // Check if subkeyword already exists
            $exists = DB::table('itagg_subkeyword')
                ->where('itagg_instance_id', $itagg_id)
                ->where('keyword', $subkeyword)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subkeyword already exists for this iTAGG instance'
                ], 400);
            }

            // Insert the new subkeyword
            DB::table('itagg_subkeyword')->insert([
                'itagg_instance_id' => $itagg_id,
                'keyword' => $subkeyword
            ]);

            Log::info('Subkeyword added successfully', [
                'itagg_id' => $itagg_id,
                'subkeyword' => $subkeyword
            ]);

            return response()->json([
                'success' => true,
                'message' => "Subkeyword '{$subkeyword}' added successfully"
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding subkeyword', [
                'itagg_id' => $itagg_id,
                'subkeyword' => $subkeyword,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add subkeyword: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a subkeyword
     */
    public function deleteSubkeyword(Request $request)
    {
        $request->validate([
            'itagg_id' => 'required|integer',
            'subkeyword' => 'required|string'
        ]);

        $itagg_id = $request->itagg_id;
        $subkeyword = strtoupper(trim($request->subkeyword));

        try {
            // Delete the subkeyword
            $deleted = DB::table('itagg_subkeyword')
                ->where('itagg_instance_id', $itagg_id)
                ->where('keyword', $subkeyword)
                ->delete();

            if ($deleted) {
                Log::info('Subkeyword deleted successfully', [
                    'itagg_id' => $itagg_id,
                    'subkeyword' => $subkeyword
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Subkeyword '{$subkeyword}' deleted successfully"
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Subkeyword not found'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error deleting subkeyword', [
                'itagg_id' => $itagg_id,
                'subkeyword' => $subkeyword,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subkeyword: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle module on/off for a keyword/subkeyword
     * Uses bitfield in modules_enabled column (like old PHP code)
     */
    public function toggleModule(Request $request)
    {
        $request->validate([
            'itagg_id' => 'required|integer',
            'module' => 'required|string',
            'action' => 'required|in:switchon,switchoff',
            'keyword' => 'required|string',
            'subkeyword' => 'nullable|string'
        ]);

        $itaggId = $request->itagg_id;
        $module = $request->module;
        $action = $request->action;
        $keyword = $request->keyword;
        $subkeyword = $request->subkeyword ?? '';

        try {
            // Module ID mapping (bitfield values)
            $moduleIds = [
                'smsResponder' => 1,
                'Forwarder' => 2,
                'SMSForwarder' => 4,
                'BusinessCard' => 8,
                'Subscription' => 16,
                'WAPPushResponder' => 32,
                'Voting' => 256,
                'EmailForwarder' => 2048,
            ];

            if (!isset($moduleIds[$module])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unknown module: ' . $module
                ], 400);
            }

            $moduleId = $moduleIds[$module];

            // Get current modules_enabled value
            if ($subkeyword === '') {
                // Main keyword - use itagg_instance table
                $record = DB::table('itagg_instance')
                    ->where('id', $itaggId)
                    ->first();
                $tableName = 'itagg_instance';
                $whereClause = ['id' => $itaggId];
            } else {
                // Subkeyword - use itagg_subkeyword table
                $record = DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $itaggId)
                    ->where('keyword', $subkeyword)
                    ->first();
                $tableName = 'itagg_subkeyword';
                $whereClause = ['itagg_instance_id' => $itaggId, 'keyword' => $subkeyword];
            }

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            $currentBitfield = $record->modules_enabled ?? 0;
            $isCurrentlyEnabled = ($currentBitfield & $moduleId) == $moduleId;

            if ($action === 'switchon') {
                // Enable module - add the bit if not already set
                if (!$isCurrentlyEnabled) {
                    $newBitfield = $currentBitfield + $moduleId;
                } else {
                    $newBitfield = $currentBitfield; // Already enabled
                }

                DB::table($tableName)
                    ->where($whereClause)
                    ->update(['modules_enabled' => $newBitfield]);

                Log::info('Module switched ON', [
                    'itagg_id' => $itaggId,
                    'module' => $module,
                    'module_id' => $moduleId,
                    'subkeyword' => $subkeyword,
                    'old_bitfield' => $currentBitfield,
                    'new_bitfield' => $newBitfield
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $module . ' module enabled successfully',
                    'status' => 'on'
                ]);
            } else {
                // Disable module - remove the bit if currently set
                if ($isCurrentlyEnabled) {
                    $newBitfield = $currentBitfield - $moduleId;
                } else {
                    $newBitfield = $currentBitfield; // Already disabled
                }

                DB::table($tableName)
                    ->where($whereClause)
                    ->update(['modules_enabled' => $newBitfield]);

                Log::info('Module switched OFF', [
                    'itagg_id' => $itaggId,
                    'module' => $module,
                    'module_id' => $moduleId,
                    'subkeyword' => $subkeyword,
                    'old_bitfield' => $currentBitfield,
                    'new_bitfield' => $newBitfield
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $module . ' module disabled successfully',
                    'status' => 'off'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error toggling module', [
                'itagg_id' => $itaggId,
                'module' => $module,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle module: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get module configuration status for a keyword/subkeyword
     * Uses bitfield in modules_enabled column (like old PHP code)
     */
    public function getModuleStatus(Request $request)
    {
        $itaggId = $request->query('itagg_id');
        $keyword = $request->query('keyword');
        $subkeyword = $request->query('subkeyword', '');

        try {
            // Module ID mapping (bitfield values)
            $moduleIds = [
                'smsResponder' => 1,
                'Forwarder' => 2,
                'SMSForwarder' => 4,
                'BusinessCard' => 8,
                'Subscription' => 16,
                'WAPPushResponder' => 32,
                'Voting' => 256,
                'EmailForwarder' => 2048,
            ];

            // Get current modules_enabled value
            if ($subkeyword === '') {
                // Main keyword - use itagg_instance table
                $record = DB::table('itagg_instance')
                    ->where('id', $itaggId)
                    ->first();
            } else {
                // Subkeyword - use itagg_subkeyword table
                $record = DB::table('itagg_subkeyword')
                    ->where('itagg_instance_id', $itaggId)
                    ->where('keyword', $subkeyword)
                    ->first();
            }

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Record not found'
                ], 404);
            }

            $currentBitfield = $record->modules_enabled ?? 0;
            $moduleStatus = [];

            // Check each module using bitwise AND
            foreach ($moduleIds as $moduleName => $moduleId) {
                $moduleStatus[$moduleName] = ($currentBitfield & $moduleId) == $moduleId;
            }

            return response()->json([
                'success' => true,
                'moduleStatus' => $moduleStatus,
                'bitfield' => $currentBitfield
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting module status', [
                'itagg_id' => $itaggId,
                'keyword' => $keyword,
                'subkeyword' => $subkeyword,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get module status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display keyword registration page
     */
    public function registerKeywordPage()
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'] ?? null;

        $isLoggedIn = !empty($bigid);
        $canRegister = false;
        $keywordsLeft = 0;
        $hasPlatinumAccess = false;
        $codeList = '60300';
        $whoisCode1 = '60300';
        $userData = null;

        if ($isLoggedIn) {
            // Get user data
            $userData = DB::table('users')
                ->selectRaw('uname, pword, user_type, platinumaccess, (platkeywordwallet / NULLIF(platkeywordcost, 0)) as platkeywordsleft, contactemail')
                ->where('bigid', $bigid)
                ->first();

            if ($userData) {
                $keywordsLeft = $userData->platkeywordsleft ?? 0;
                $hasPlatinumAccess = ($userData->platinumaccess === 'y');

                if ($hasPlatinumAccess && $keywordsLeft >= 1) {
                    $canRegister = true;
                }
            }
        }

        return view('customer.keywords.register', [
            'isLoggedIn' => $isLoggedIn,
            'canRegister' => $canRegister,
            'keywordsLeft' => $keywordsLeft,
            'hasPlatinumAccess' => $hasPlatinumAccess,
            'codeList' => $codeList,
            'whoisCode1' => $whoisCode1,
            'userData' => $userData,
            'keywordCreated' => false,
        ]);
    }

    /**
     * Check keyword availability (AJAX)
     */
    public function checkKeywordAvailability(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:20|regex:/^[A-Za-z0-9]+$/'
        ]);

        $keyword = strtolower(trim($request->keyword));
        $shortcode = '60300';

        try {
            // Check if keyword already exists in itagg_instance for this shortcode
            $shortcodeId = DB::table('smsshortcodes')
                ->where('number', $shortcode)
                ->value('id');

            if (!$shortcodeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shortcode not found'
                ]);
            }

            $exists = DB::table('itagg_instance')
                ->where('keyword', $keyword)
                ->where('smsshortcodes_id', $shortcodeId)
                ->where('status', 1)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => true,
                    'available' => false,
                    'shortcode' => $shortcode,
                    'message' => 'This keyword is already registered.'
                ]);
            }

            // Keyword is available
            return response()->json([
                'success' => true,
                'available' => true,
                'shortcode' => $shortcode,
                'keyword' => strtoupper($keyword)
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking keyword availability', [
                'keyword' => $keyword,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking keyword availability'
            ], 500);
        }
    }

    /**
     * Register a new keyword for existing user
     */
    public function registerKeyword(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|max:20|regex:/^[A-Za-z0-9]+$/',
            'shortcode' => 'required|string'
        ]);

        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'] ?? null;

        if (!$bigid) {
            return redirect()->route('keywords.register')
                ->with('error', 'You must be logged in to register a keyword.');
        }

        $keyword = strtolower(trim($request->keyword));
        $shortcode = $request->shortcode;

        try {
            // Get user data
            $userData = DB::table('users')
                ->selectRaw('uname, pword, user_type, platinumaccess, (platkeywordwallet / NULLIF(platkeywordcost, 0)) as platkeywordsleft, contactemail, contactname, busname')
                ->where('bigid', $bigid)
                ->first();

            if (!$userData) {
                return redirect()->route('keywords.register')
                    ->with('error', 'User not found.');
            }

            // Check if user has keywords left
            if ($userData->platkeywordsleft < 1) {
                return redirect()->route('keywords.register')
                    ->with('error', 'You don\'t have any remaining keyword credits. Please contact us to purchase more.');
            }

            // Check platinum access
            if ($userData->platinumaccess !== 'y') {
                return redirect()->route('keywords.register')
                    ->with('error', 'Keyword registration requires Platinum access.');
            }

            // Get shortcode ID
            $shortcodeData = DB::table('smsshortcodes')
                ->where('number', $shortcode)
                ->first();

            if (!$shortcodeData) {
                return redirect()->route('keywords.register')
                    ->with('error', 'Invalid shortcode.');
            }

            // Check if keyword already exists
            $exists = DB::table('itagg_instance')
                ->where('keyword', $keyword)
                ->where('smsshortcodes_id', $shortcodeData->id)
                ->where('status', 1)
                ->exists();

            if ($exists) {
                return redirect()->route('keywords.register')
                    ->with('error', 'This keyword is already registered. Please choose a different one.');
            }

            // Create the keyword
            $result = $this->createKeywordForExistingUser(
                $keyword,
                $userData->contactemail,
                $bigid,
                $userData->uname,
                $shortcode,
                $userData->user_type
            );

            if ($result['success']) {
                return redirect()->route('keywords.register')
                    ->with('success', 'Keyword "' . strtoupper($keyword) . '" has been registered successfully!')
                    ->with('keywordCreated', true)
                    ->with('createdKeyword', strtoupper($keyword))
                    ->with('shortcode', $shortcode)
                    ->with('expiryDate', $result['expiryDate']);
            } else {
                return redirect()->route('keywords.register')
                    ->with('error', 'Failed to register keyword. Error code: ' . $result['errorCode']);
            }
        } catch (\Exception $e) {
            Log::error('Error registering keyword', [
                'keyword' => $keyword,
                'user' => $bigid,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('keywords.register')
                ->with('error', 'An error occurred while registering the keyword. Please try again.');
        }
    }

    /**
     * Create keyword for existing user (helper method)
     */
    public function createKeywordForExistingUser($keyword, $email, $userBigId, $username, $shortcode, $userType)
    {
        $creationStatus = true;
        $errorCode = 0;

        // Date calculations
        $keywordStartDate = date('Y-m-d');
        $keywordEndDate = date('Y-m-d', strtotime('+1 year'));
        $nextContactAboutRenewal = date('Ymd', strtotime('+7 days'));
        $todayYmdHis = date('YmdHis');
        $nextContactDateFollow = date('Ymd2100', strtotime('+1 day'));

        // Default settings
        $subkeywords = 100;
        $giveUpgradeFeatures = true;

        // Shortcode configuration for 60300
        if ($shortcode === '60300') {
            $shortcodeId = 16;
            $moduleRestrict = 557375;
            $dedVirtCodeId = 156;
            $dedVirtModuleRestrict = 319;
        } elseif ($shortcode === '80809') {
            $shortcodeId = 45;
            $moduleRestrict = 319;
            $dedVirtCodeId = 160;
            $dedVirtModuleRestrict = 319;
        } else {
            return ['success' => false, 'errorCode' => 1101, 'expiryDate' => null];
        }

        // Auto-response message
        $responseContent = urlencode('This is a demo auto-response for your keyword ' . strtoupper($keyword) . '. You can set your own response as required on our website control panel. regards, iTagg.');

        DB::beginTransaction();

        try {
            // Insert into itagg_instance
            $inserted = DB::table('itagg_instance')->insert([
                'users_bigid' => $userBigId,
                'keyword' => $keyword,
                'purchased' => $keywordStartDate,
                'expiry' => $keywordEndDate,
                'nextcontactaboutrenewal' => $nextContactAboutRenewal,
                'keylevel' => '2011+',
                'forwarding_email' => $email,
                'response_sender_id' => 'iTagg',
                'response_content' => $responseContent,
                'smsshortcodes_id' => $shortcodeId,
                'itagg_type_id' => 3,
                'max_subkeywords' => $subkeywords,
                'itagg_purchasetype_id' => 1,
                'active' => 1,
                'status' => 1,
                'modules_enabled' => 3,
                'module_restrict' => $moduleRestrict
            ]);

            if (!$inserted) {
                throw new \Exception('Failed to insert keyword');
            }

            // Generate affiliate invite code
            $newAffiliateInviteCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));

            // Check for unique code
            $codeExists = true;
            $attempts = 0;
            while ($codeExists && $attempts < 10) {
                $existingCode = DB::table('affiliateinvite')->where('icode', $newAffiliateInviteCode)->exists();
                if (!$existingCode) {
                    $codeExists = false;
                } else {
                    $newAffiliateInviteCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
                    $attempts++;
                }
            }

            // Insert affiliate invite code
            DB::table('affiliateinvite')->insert([
                'assigned_userref' => $userBigId,
                'icode' => $newAffiliateInviteCode,
                'subdomain' => $newAffiliateInviteCode,
                'codenote' => 'code for existing client added when getting another freekey'
            ]);

            // Insert follow-up note
            DB::table('users_notes')->insert([
                'users_bigid' => $userBigId,
                'nextcontactdate' => $nextContactDateFollow,
                'notes' => 'follow',
                'myinsertdate' => $todayYmdHis
            ]);

            // Insert tracking note
            DB::table('users_notes')->insert([
                'users_bigid' => $userBigId,
                'nextcontactdate' => '202601012100',
                'notes' => 'existing client took another freekey: do check',
                'myinsertdate' => $todayYmdHis
            ]);

            // Deduct from keyword wallet
            DB::table('users')
                ->where('bigid', $userBigId)
                ->decrement('platkeywordwallet', 1);

            // If user has upgrade features, create additional instance for dedicated virtual
            if ($giveUpgradeFeatures) {
                DB::table('itagg_instance')->insert([
                    'users_bigid' => $userBigId,
                    'keyword' => $keyword,
                    'purchased' => $keywordStartDate,
                    'expiry' => $keywordEndDate,
                    'nextcontactaboutrenewal' => $nextContactAboutRenewal,
                    'keylevel' => '2011+',
                    'forwarding_email' => $email,
                    'response_sender_id' => 'iTagg',
                    'response_content' => $responseContent,
                    'smsshortcodes_id' => $dedVirtCodeId,
                    'itagg_type_id' => 3,
                    'max_subkeywords' => $subkeywords,
                    'itagg_purchasetype_id' => 1,
                    'active' => 1,
                    'status' => 1,
                    'modules_enabled' => 3,
                    'module_restrict' => $dedVirtModuleRestrict
                ]);
            }

            // Update user type if legacy
            DB::table('users')
                ->where('bigid', $userBigId)
                ->where('user_type', 'legacy')
                ->update(['user_type' => 'freekey']);

            DB::commit();

            // Log the successful registration
            Log::info('Keyword registered successfully', [
                'keyword' => $keyword,
                'user' => $userBigId,
                'shortcode' => $shortcode,
                'expiry' => $keywordEndDate
            ]);

            return [
                'success' => true,
                'errorCode' => 0,
                'expiryDate' => date('d M Y', strtotime($keywordEndDate))
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create keyword', [
                'keyword' => $keyword,
                'user' => $userBigId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'errorCode' => 103,
                'expiryDate' => null
            ];
        }
    }


    //Campaign Redirect

    public function CustomerTokenAndRedirect($username)
    {
        $user = User::where('uname', $username)->first();

        if (!$user) {
            return redirect()->back()->withErrors(['error' => 'User not found']);
        }

        // Always generate a new token for security
        $user->remember_token = Str::random(60);
        $user->save();

        // $customerDomains = config('custom.customer_domains');
        // $adminDomains    = config('custom.admin_domains');
        // $scheme = request()->getScheme();
        // // Pick first domain from config (or more advanced: pick based on user company)
        // if ($user->login_type === 'admin' && !empty($adminDomains)) {
        //     $autologinUrl =  $scheme . '://' . $adminDomains[0] . '/autologin?token=' . $user->remember_token;
        // } elseif (!empty($customerDomains)) {
        //     $autologinUrl =  $scheme . '://' . $customerDomains[0] . '/autologin?token=' . $user->remember_token;
        // } else {
        //     return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        // }


        $campaignDomains = config('domains.campaign_domains');
        $adminDomains    = config('domains.admin_domains');
        $scheme = request()->getScheme();

        // Pick first domain from config
        if ($user->login_type === 'admin' && !empty($adminDomains)) {
            $autologinUrl = $scheme . '://' . $adminDomains[0] . '/autologincustomer?token=' . $user->remember_token;
        } elseif (!empty($campaignDomains)) {
            $autologinUrl = $scheme . '://' . $campaignDomains[0] . '/autologincustomer?token=' . $user->remember_token;
        } else {
            return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        }


        return redirect()->away($autologinUrl);
    }

    /**
     * Step 2: Auto login on campaign domain
     */
    public function loginWithTokenCampaignCustomer(Request $request)
    {
        $host = $request->getHost();

        $campaignDomains = config('domains.campaign');
        $adminDomains    = config('domains.admin');

        // 🔹 Set different session cookies dynamically
        if (in_array($host, $adminDomains)) {
            Config::set('session.cookie', 'admin_session');
        } elseif (in_array($host, $campaignDomains)) {
            Config::set('session.cookie', 'campaign_session');
        }

        $token = $request->query('token');
        if (!$token) {
            return redirect('/')->withErrors(['error' => 'Token missing']);
        }

        $user = User::where('remember_token', $token)->first();
        if (!$user) {
            return redirect('/')->withErrors(['error' => 'Invalid or expired token']);
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account disabled.');
        }

        // Store user info in session
        Session::put('user_info', [
            'contactname' => $user->contactname,
            'bigid'       => $user->bigid,
            'username'    => $user->uname,
            'login_type'  => 'campaign',
        ]);

        Auth::login($user);

        // Check lockout
        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account locked.');
        }

        return redirect('/campaign/dashboard');
    }
}
