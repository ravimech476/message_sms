<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use App\Models\User;


/**
 * Campaign Queue Service
 * Handles campaign file processing via RabbitMQ
 */
class CampaignQueueService
{
    private $rabbitMQ;
    private $smsQueueService;

    const QUEUE_CAMPAIGN = 'campaign.process';

    public function __construct()
    {
        $this->rabbitMQ = new RabbitMQService();
        $this->smsQueueService = new SmsQueueService();
    }

    /**
     * Get campaign files directory path
     * Creates the directory if it doesn't exist
     */
    public static function getCampaignDirectory(): string
    {
        $campaignDir = storage_path('app/campaigns');
        if (!is_dir($campaignDir)) {
            mkdir($campaignDir, 0755, true);
        }
        return $campaignDir;
    }

    /**
     * Get full path for a campaign file
     */
    public static function getCampaignFilePath(string $filename): string
    {
        return self::getCampaignDirectory() . '/' . $filename;
    }

    /**
     * Clean up old campaign files (older than specified days)
     */
    public function cleanupOldCampaignFiles(int $daysOld = 7): array
    {
        $campaignDir = self::getCampaignDirectory();
        $deleted = 0;
        $failed = 0;
        $cutoffTime = time() - ($daysOld * 86400);

        $files = glob($campaignDir . '/*.csv');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (@unlink($file)) {
                    $deleted++;
                    Log::info("Deleted old campaign file", ['file' => basename($file)]);
                } else {
                    $failed++;
                    Log::warning("Failed to delete old campaign file", ['file' => basename($file)]);
                }
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
            'directory' => $campaignDir
        ];
    }

    /**
     * Queue a campaign for processing
     * Returns immediately after queuing - file will be processed async
     */
    public function queueCampaign($campaignData)
    {
        try {
            $queueId = 'campaign_' . uniqid() . '_' . time();

            $data = [
                'queue_id' => $queueId,
                'campaign_id' => $campaignData['campaign_id'],
                'campaign_row_id' => $campaignData['campaign_row_id'],
                'user_ref' => $campaignData['user_ref'],
                'filename' => $campaignData['filename'],
                'filepath' => $campaignData['filepath'],
                'num_lines' => $campaignData['num_lines'] ?? 0,
                'num_lines_done' => $campaignData['num_lines_done'] ?? 0,
                'queued_at' => Carbon::now()->toDateTimeString(),
                'metadata' => $campaignData['metadata'] ?? []
            ];

            // Publish to RabbitMQ campaign queue
            $result = $this->rabbitMQ->publishToQueue(
                self::QUEUE_CAMPAIGN,
                $data,
                5 // Default priority
            );

            if ($result) {
                Log::info("Campaign queued for processing", [
                    'queue_id' => $queueId,
                    'campaign_id' => $campaignData['campaign_id'],
                    'user_ref' => $campaignData['user_ref']
                ]);

                return [
                    'success' => true,
                    'queue_id' => $queueId,
                    'message' => 'Campaign queued for processing'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to queue campaign'
            ];
        } catch (Exception $e) {
            Log::error("Failed to queue campaign: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process campaign from queue (Consumer)
     * This is called by the RabbitMQ consumer
     */
    public function processCampaign($data)
    {
        $campaignId = $data['campaign_id'] ?? null;
        $userRef = $data['user_ref'] ?? null;
        $filepath = $data['filepath'] ?? null;
        $campaignRowId = $data['campaign_row_id'] ?? null;
        $numLinesDonePreviously = $data['num_lines_done'] ?? 0;

        // OLD SYSTEM (smssend.inc:1057-1066): user's per-route chargetype (pps/ppd). Fetch ONCE
        // per campaign (constant for all rows) instead of per-row, then reuse in each insert.
        $campaignChargeType = DB::table('users')->where('bigid', $userRef)->value('chargetype1') ?: 'pps';

        Log::info("Starting campaign processing", [
            'campaign_id' => $campaignId,
            'user_ref' => $userRef,
            'filepath' => $filepath
        ]);

        try {
            // Validate campaign exists and is not deleted
            $campaign = DB::table('smsg_campaigns')
                ->where('campaignid', $campaignId)
                ->where('userref', $userRef)
                ->first();

            if (!$campaign) {
                Log::error("Campaign not found", ['campaign_id' => $campaignId]);
                return ['status' => 'failed', 'message' => 'Campaign not found'];
            }

            if ($campaign->status === 'deleted') {
                Log::info("Campaign was deleted, skipping", ['campaign_id' => $campaignId]);
                return ['status' => 'deleted', 'message' => 'Campaign deleted'];
            }

            // DUPLICATE PREVENTION: Check if campaign is already done or currently firing
            if ($campaign->status === 'done') {
                Log::warning("Campaign already completed, skipping duplicate processing", [
                    'campaign_id' => $campaignId,
                    'status' => $campaign->status
                ]);
                return ['status' => 'done', 'message' => 'Campaign already completed'];
            }

            // Check if already firing - might be a duplicate queue message
            if ($campaign->status === 'firing' && $numLinesDonePreviously == 0) {
                // Get the actual lines done from DB
                $actualLinesDone = $campaign->numlinesdone ?? 0;
                if ($actualLinesDone > 0) {
                    Log::warning("Campaign already being processed (lines done: {$actualLinesDone}), checking for duplicate", [
                        'campaign_id' => $campaignId,
                        'status' => $campaign->status,
                        'lines_done' => $actualLinesDone
                    ]);
                    // Use actual lines done to resume from correct position
                    $numLinesDonePreviously = $actualLinesDone;
                }
            }

            // Update campaign status to 'firing'
            DB::table('smsg_campaigns')
                ->where('id', $campaignRowId)
                ->update(['status' => 'firing']);

            // Check if file exists - try multiple locations for backward compatibility
            $fileFound = false;
            $actualFilepath = $filepath;

            // First, check the provided filepath
            if (file_exists($filepath)) {
                $fileFound = true;
                $actualFilepath = $filepath;
            } else {
                // Try storage/app/campaigns directory (new location)
                $filename = basename($filepath);
                $storagePath = storage_path('app/campaigns/' . $filename);

                if (file_exists($storagePath)) {
                    $fileFound = true;
                    $actualFilepath = $storagePath;
                    Log::info("File found in storage path", [
                        'original_path' => $filepath,
                        'actual_path' => $actualFilepath
                    ]);
                } else {
                    // Try /tmp as fallback for backward compatibility
                    $tmpPath = '/tmp/' . $filename;
                    if (file_exists($tmpPath)) {
                        $fileFound = true;
                        $actualFilepath = $tmpPath;
                        Log::info("File found in /tmp fallback", [
                            'original_path' => $filepath,
                            'actual_path' => $actualFilepath
                        ]);
                    }
                }
            }

            if (!$fileFound) {
                $errorMsg = 'File not found. Tried: ' . $filepath;
                if (isset($storagePath)) {
                    $errorMsg .= ', ' . $storagePath;
                }
                $this->updateCampaignStatus($campaignRowId, 'failed', $errorMsg);
                return ['status' => 'failed', 'message' => $errorMsg];
            }

            // Use the actual filepath for processing
            $filepath = $actualFilepath;

            // Get user's short domain for short URL support
            $userShortDomain = DB::table('users')
                ->where('bigid', $userRef)
                ->value('shortdomain');
            $shortUrlAllowed = !empty($userShortDomain);

            // Process the CSV file
            $result = $this->processCSVFile($filepath, $userRef, $campaignId, $campaignRowId, $numLinesDonePreviously, $shortUrlAllowed);

            // Update campaign status based on result
            $this->updateCampaignStatus($campaignRowId, $result['status'], $result['message']);

            Log::info("Campaign processing completed", [
                'campaign_id' => $campaignId,
                'status' => $result['status'],
                'processed' => $result['processed'] ?? 0,
                'queued_now' => $result['queued_now'] ?? 0,
                'scheduled' => $result['scheduled'] ?? 0,
                'failed' => $result['failed'] ?? 0
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error("Campaign processing failed", [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($campaignRowId) {
                $this->updateCampaignStatus($campaignRowId, 'failed', 'Error: ' . $e->getMessage());
            }

            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

     // Load Testing Purpose
    // private function processCSVFile($filepath, $userRef, $campaignId, $campaignRowId, $numLinesDonePreviously, $shortUrlAllowed)
    // {
    //     $nowTime = Carbon::now('Europe/London')->format('YmdHis');
    //     $totalProcessed = 0;
    //     $queuedNow = 0;
    //     $scheduledFuture = 0;
    //     $failedCount = 0;
    //     $skippedBlacklist = 0;
    //     $skippedDuplicate = 0;
    //     $rowNum = 0;

    //     // Variables for tracking last values (for CSV rows that inherit from previous)
    //     $lastSenderId = '';
    //     $lastMessage = '';
    //     $lastRawMessage = '';
    //     $lastSendDateTime = '';
    //     $lastRoute = '7';
    //     $lastShortDomain = '';
    //     $lastLongUrl = '';
    //     $lastRawLongUrl = '';

    //     // Open file
    //     $handle = fopen($filepath, 'r');
    //     if (!$handle) {
    //         return ['status' => 'failed', 'message' => 'Cannot open file'];
    //     }

    //     // Enable auto-detection of line endings
    //     ini_set('auto_detect_line_endings', true);

    //     while (($row = fgetcsv($handle)) !== false) {
    //         $rowNum++;

    //         // Skip already processed rows (for resume functionality)
    //         if ($numLinesDonePreviously > 0 && $rowNum <= $numLinesDonePreviously) {
    //             continue;
    //         }

    //         // Skip comment lines, empty lines, header rows
    //         $firstCol = $row[0] ?? '';
    //         $firstCol = str_replace(' ', '', $firstCol);

    //         if (empty(trim($firstCol))) {
    //             continue;
    //         }
    //         if (substr($firstCol, 0, 1) === '#') {
    //             continue;
    //         }
    //         if (strtolower(substr($firstCol, 0, 3)) === 'end') {
    //             break;
    //         }
    //         if (stripos($firstCol, 'mobile') !== false && $rowNum <= 2) {
    //             continue; // Skip header row
    //         }

    //         // Check for scientific notation error (Excel issue)
    //         if (strpos(strtolower($firstCol), 'e') !== false || strpos($firstCol, '.') !== false) {
    //             Log::warning("Scientific notation in phone number, skipping", [
    //                 'campaign_id' => $campaignId,
    //                 'row' => $rowNum,
    //                 'value' => $firstCol
    //             ]);
    //             $failedCount++;
    //             continue;
    //         }

    //         // Parse mobile number
    //         $mobileNumber = preg_replace('/[^0-9]/', '', $firstCol);

    //         // Format UK numbers
    //         if (substr($mobileNumber, 0, 2) === '07') {
    //             $mobileNumber = '44' . substr($mobileNumber, 1);
    //         } elseif (substr($mobileNumber, 0, 4) === '+447') {
    //             $mobileNumber = substr($mobileNumber, 1);
    //         } elseif (substr($mobileNumber, 0, 4) === '0044') {
    //             $mobileNumber = substr($mobileNumber, 2);
    //         } elseif (substr($mobileNumber, 0, 4) === '4407') {
    //             $mobileNumber = '447' . substr($mobileNumber, 4);
    //         } elseif (substr($mobileNumber, 0, 5) === '+4407') {
    //             $mobileNumber = '447' . substr($mobileNumber, 5);
    //         } elseif (substr($mobileNumber, 0, 1) === '7') {
    //             $mobileNumber = '44' . $mobileNumber;
    //         }

    //         // Validate length
    //         if (strlen($mobileNumber) < 8 || strlen($mobileNumber) > 15) {
    //             Log::warning("Invalid phone number length", [
    //                 'campaign_id' => $campaignId,
    //                 'row' => $rowNum,
    //                 'number' => $mobileNumber
    //             ]);
    //             $failedCount++;
    //             continue;
    //         }

    //         // Check blacklist
    //         $isBlacklisted = DB::table('itagg_outbound_blacklist')
    //             ->where('users_bigid', $userRef)
    //             ->where('msisdn', $mobileNumber)
    //             ->exists();

    //         if ($isBlacklisted) {
    //             Log::info("Number blacklisted, skipping", [
    //                 'campaign_id' => $campaignId,
    //                 'number' => $mobileNumber
    //             ]);
    //             $skippedBlacklist++;

    //             // Still update numlinesdone
    //             DB::table('smsg_campaigns')
    //                 ->where('id', $campaignRowId)
    //                 ->where('status', '!=', 'deleted')
    //                 ->increment('numlinesdone');
    //             continue;
    //         }

    //         // Parse send time (column 1) - format: YYYYMMDDHHiiss
    //         $sendDateTime = '';
    //         if (isset($row[1]) && !empty(trim($row[1]))) {
    //             $sendDateTime = trim($row[1]);
    //             // Validate format
    //             if (strlen($sendDateTime) !== 14) {
    //                 $sendDateTime = ''; // Invalid format, send now
    //             } elseif ($sendDateTime < date("YmdHis", strtotime("-1 days"))) {
    //                 $sendDateTime = ''; // Date in past, send now
    //             }
    //         }
    //         if (empty($sendDateTime)) {
    //             $sendDateTime = $lastSendDateTime;
    //         }
    //         $lastSendDateTime = $sendDateTime;

    //         // Parse sender ID (column 2)
    //         $senderId = '';
    //         if (isset($row[2]) && !empty(trim($row[2]))) {
    //             $senderId = trim($row[2]);
    //             // Format sender ID if it's a phone number
    //             if (substr($senderId, 0, 2) === '07') {
    //                 $senderId = '44' . substr($senderId, 1);
    //             } elseif (substr($senderId, 0, 4) === '+447') {
    //                 $senderId = substr($senderId, 1);
    //             } elseif (substr($senderId, 0, 4) === '0044') {
    //                 $senderId = substr($senderId, 2);
    //             } elseif (substr($senderId, 0, 1) === '7' && strlen($senderId) > 5) {
    //                 $senderId = '44' . $senderId;
    //             }
    //         } else {
    //             $senderId = $lastSenderId;
    //         }
    //         $lastSenderId = $senderId;

    //         // Parse message (column 3)
    //         $message = '';
    //         if (isset($row[3]) && !empty(trim($row[3]))) {
    //             $message = trim($row[3]);
    //             $lastRawMessage = $message;
    //         } else {
    //             $message = $lastRawMessage;
    //         }

    //         // Parse custom fields (columns 4, 5, 6)
    //         $field1 = isset($row[4]) ? trim($row[4]) : '';
    //         $field2 = isset($row[5]) ? trim($row[5]) : '';
    //         $field3 = isset($row[6]) ? trim($row[6]) : '';

    //         // Replace placeholders in message
    //         $message = str_replace('#FIELD1#', $field1, $message);
    //         $message = str_replace('#FIELD2#', $field2, $message);
    //         $message = str_replace('#FIELD3#', $field3, $message);

    //         // Parse route (column 7)
    //         $route = '7';
    //         if (isset($row[7]) && !empty(trim($row[7]))) {
    //             $route = strtolower(trim($row[7]));
    //         } else {
    //             $route = $lastRoute;
    //         }
    //         $lastRoute = $route;

    //         // Parse short URL fields (columns 8-12)
    //         $shortDomain = '';
    //         if (isset($row[8]) && !empty(trim($row[8]))) {
    //             $shortDomain = trim($row[8]);
    //         } else {
    //             $shortDomain = $lastShortDomain;
    //         }
    //         $lastShortDomain = $shortDomain;

    //         $longUrl = '';
    //         if (isset($row[9]) && !empty(trim($row[9]))) {
    //             $longUrl = trim($row[9]);
    //             $lastRawLongUrl = $longUrl;
    //         } else {
    //             $longUrl = $lastRawLongUrl;
    //         }

    //         // URL field placeholders
    //         $urlField1 = isset($row[10]) ? trim($row[10]) : '';
    //         $urlField2 = isset($row[11]) ? trim($row[11]) : '';
    //         $urlField3 = isset($row[12]) ? trim($row[12]) : '';

    //         $longUrl = str_replace('#URLFIELD1#', $urlField1, $longUrl);
    //         $longUrl = str_replace('#URLFIELD2#', $urlField2, $longUrl);
    //         $longUrl = str_replace('#URLFIELD3#', $urlField3, $longUrl);

    //         // Add http:// if missing
    //         if (!empty($longUrl) && substr(strtolower($longUrl), 0, 7) !== 'http://' && substr(strtolower($longUrl), 0, 8) !== 'https://') {
    //             $longUrl = 'http://' . $longUrl;
    //         }

    //         // Handle short URL generation
    //         if (!empty($shortDomain) && $shortUrlAllowed) {
    //             $shortId = $this->createShortId();

    //             // Ensure unique shortId
    //             while (DB::table('smsg_shorturl_forwarding')->where('shortid', $shortId)->exists()) {
    //                 $shortId = $this->createShortId();
    //             }

    //             $shortUrl = rtrim($shortDomain, '/') . '/' . $shortId . '/';
    //             $message = str_replace('#SHORTURL#', $shortUrl, $message);

    //             // Insert short URL record
    //             DB::table('smsg_shorturl_forwarding')->insert([
    //                 'status' => 'live',
    //                 'expirydate' => '20300101000000',
    //                 'userref' => $userRef,
    //                 'campaignref' => $campaignId,
    //                 'mobnum' => $mobileNumber,
    //                 'senderid' => urlencode($senderId),
    //                 'datecreated' => date('YmdHis'),
    //                 'shortdomain' => urlencode($shortDomain),
    //                 'shortid' => $shortId,
    //                 'longurl' => urlencode($longUrl),
    //                 'firstclickdate' => '',
    //                 'firstclickip' => '',
    //                 'numclicks' => 0,
    //                 'campaignrownum' => $rowNum
    //             ]);
    //         } else {
    //             $message = str_replace('#SHORTURL#', '', $message);
    //         }

    //         // Skip if sender ID or message is empty
    //         if (empty($senderId) || empty($message)) {
    //             Log::warning("Missing sender ID or message", [
    //                 'campaign_id' => $campaignId,
    //                 'row' => $rowNum
    //             ]);
    //             $failedCount++;
    //             continue;
    //         }

    //         // DUPLICATE PREVENTION: Check if this mobile number already has an SMS for this campaign
    //         $existingLog = DB::table('smsg_log')
    //             ->where('campaignref', $campaignId)
    //             ->where('mobnum', $mobileNumber)
    //             ->whereIn('sentstatus', ['pending', 'ok', 'tomorrowonward'])
    //             ->first();

    //         if ($existingLog) {
    //             Log::warning("Duplicate SMS detected for this campaign+mobile, skipping", [
    //                 'campaign_id' => $campaignId,
    //                 'mobile' => $mobileNumber,
    //                 'existing_status' => $existingLog->sentstatus,
    //                 'existing_bigid' => $existingLog->bigid,
    //                 'row' => $rowNum
    //             ]);

    //             // Still update numlinesdone to keep progress accurate
    //             DB::table('smsg_campaigns')
    //                 ->where('id', $campaignRowId)
    //                 ->where('status', '!=', 'deleted')
    //                 ->increment('numlinesdone');

    //             $skippedDuplicate++;
    //             continue;
    //         }

    //         // Calculate SMS parts
    //         $messageLength = mb_strlen($message, 'UTF-8');
    //         $numParts = $messageLength <= 160 ? 1 : (int) ceil($messageLength / 153);

    //         // Extract country code
    //         $countryCode = $this->extractCountryCode($mobileNumber);

    //         // Generate unique bigid
    //         $bigId = md5(uniqid(rand(), true));

    //         // Determine provider based on sender ID (from smsshortcodes.whichoperator)
    //         $provider = $this->determineProviderFromSender($senderId);
    //         $supplierName = $provider === 'sinch' ? 'Sinch SMPP' : 'Vonage SMPP';

    //         // Determine if scheduled or immediate
    //         $isScheduled = !empty($sendDateTime) && $sendDateTime > $nowTime;
    //         $sentStatus = $isScheduled ? 'tomorrowonward' : 'pending';
    //         $sentStatusText = $isScheduled ? 'Scheduled for delivery at ' . $sendDateTime : 'Queued for SMPP delivery';

    //         $current_date = Carbon::now('Europe/London')->format('YmdHis');

    //         // Get user ID
    //         $getUserId = User::where('bigid', $userRef)->first();

    //         // Insert into smsg_log
    //         $dosendtime = $isScheduled ? $sendDateTime : $current_date;

    //         // Calculate dosendtimeint as Unix timestamp (like old system)
    //         $dosendtimeint = mktime(
    //             (int) substr($dosendtime, 8, 2),
    //             (int) substr($dosendtime, 10, 2),
    //             (int) substr($dosendtime, 12, 2),
    //             (int) substr($dosendtime, 4, 2),
    //             (int) substr($dosendtime, 6, 2),
    //             (int) substr($dosendtime, 0, 4)
    //         );

    //         // Calculate daemon priority (like old system)
    //         $userDaemonPriority = $getUserId->daemonpriority ?? 100;
    //         $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) ? 200 : $userDaemonPriority; // Campaigns are bulk, use 200
    //         $daemonId = $baseDaemonId + mt_rand(0, 39);

    //         $smsgLogId = DB::table('smsg_log')->insertGetId([
    //             'sms_type' => 'sms',
    //             'initiator' => 'CampaignManager',
    //             'bigid' => $bigId,
    //             'mobnum' => $mobileNumber,
    //             'numparts' => $numParts,
    //             'text' => $message,
    //             'originator' => $senderId,
    //             'numbits' => 7,
    //             'timesubmitted' => $current_date,
    //             'userref' => $userRef,
    //             'affiliateref' => '0',
    //             'dosendtime' => $dosendtime,
    //             'dosendtimeint' => $dosendtimeint,
    //             'dayofyear' => substr($dosendtime, 0, 8),
    //             'timesent' => '00000000000000',
    //             'sentstatus' => $sentStatus,
    //             'sentstatustmp' => $sentStatus,
    //             'sentstatustext' => $sentStatusText,
    //             'suppliermsgref' => '',
    //             'smsgdaemonid' => $daemonId,
    //             'sendpriority' => $baseDaemonId,
    //             'costprice' => 0.000000,
    //             'userprice' => 0.000000,
    //             'aggregator_dlrcode' => 0,
    //             'aggregator_dlrmsg' => $isScheduled ? 'Scheduled - ' . $provider : 'Queued - ' . $provider,
    //             'campaignref' => $campaignId,
    //             'binaryflags' => '',
    //             'profit' => 0.000000,
    //             'countrydialcode' => $countryCode,
    //             'suppliername' => $supplierName,
    //             'supplierrouteref' => $route,
    //             'requested_route' => 0,
    //             'requested_routetag' => '',
    //             'deliverystatus2' => $isScheduled ? 'scheduled' : 'pending',
    //             'migration_flag' => $getUserId->migration_flag ?? 'new',
    //         ]);

    //         // Update campaign progress
    //         DB::table('smsg_campaigns')
    //             ->where('id', $campaignRowId)
    //             ->where('status', '!=', 'deleted')
    //             ->increment('numlinesdone');

    //         // whitelist numbers from .env
    //         $allowedQueueNumbers = array_filter(array_map(
    //             'trim',
    //             explode(',', env('QUEUE_TEST_NUMBERS', ''))
    //         ));

    //         $isAllowed = in_array($mobileNumber, $allowedQueueNumbers, true);

    //         if (!$isScheduled && $isAllowed) {

    //             try {
    //                 $queueResult = $this->smsQueueService->queueSms([
    //                     'user_ref' => $userRef,
    //                     'mobile_number' => $mobileNumber,
    //                     'message' => $message,
    //                     'sender_id' => $senderId,
    //                     'priority' => 5,
    //                     'reference_id' => $bigId,
    //                     'provider' => $provider,
    //                     'metadata' => [
    //                         'smsg_log_id' => $smsgLogId,
    //                         'bigid' => $bigId,
    //                         'campaign_id' => $campaignId,
    //                         'source' => 'campaign_manager',
    //                         'provider' => $provider,
    //                         'scheduled' => false
    //                     ]
    //                 ]);

    //                 if (!empty($queueResult['success'])) {
    //                     $queuedNow++;

    //                     Log::debug("SMS queued (whitelist)", [
    //                         'campaign_id' => $campaignId,
    //                         'mobile' => $mobileNumber,
    //                         'queue_id' => $queueResult['queue_id'] ?? null
    //                     ]);
    //                 } else {
    //                     $failedCount++;

    //                     Log::error("Queue failed", [
    //                         'campaign_id' => $campaignId,
    //                         'mobile' => $mobileNumber,
    //                         'error' => $queueResult['error'] ?? 'Unknown'
    //                     ]);

    //                     DB::table('smsg_log')
    //                         ->where('id', $smsgLogId)
    //                         ->update([
    //                             'sentstatus' => 'fail',
    //                             'sentstatustext' => 'Queue failed: ' . ($queueResult['error'] ?? 'Unknown')
    //                         ]);
    //                 }
    //             } catch (\Exception $e) {
    //                 $failedCount++;

    //                 Log::error("Queue exception", [
    //                     'campaign_id' => $campaignId,
    //                     'mobile' => $mobileNumber,
    //                     'error' => $e->getMessage()
    //                 ]);
    //             }
    //         } elseif (!$isScheduled && !$isAllowed) {

    //             //  Not in whitelist → skip
    //             Log::warning("SMS skipped (not in whitelist)", [
    //                 'campaign_id' => $campaignId,
    //                 'mobile' => $mobileNumber
    //             ]);

    //             $failedCount++;
    //         } else {

    //             //  Scheduled messages (no whitelist restriction OR apply if needed)
    //             $scheduledFuture++;

    //             Log::debug("SMS scheduled", [
    //                 'campaign_id' => $campaignId,
    //                 'mobile' => $mobileNumber,
    //                 'send_time' => $sendDateTime
    //             ]);
    //         }

    //         $totalProcessed++;

    //         // Pause every 500 messages to prevent overload
    //         if ($totalProcessed % 500 === 0) {
    //             usleep(50000); // 50ms pause
    //             Log::info("Campaign progress", [
    //                 'campaign_id' => $campaignId,
    //                 'processed' => $totalProcessed
    //             ]);
    //         }

    //         // Check if campaign was deleted during processing
    //         $currentStatus = DB::table('smsg_campaigns')
    //             ->where('id', $campaignRowId)
    //             ->value('status');

    //         if ($currentStatus === 'deleted') {
    //             Log::info("Campaign deleted during processing", ['campaign_id' => $campaignId]);
    //             fclose($handle);
    //             return [
    //                 'status' => 'deleted',
    //                 'message' => "Campaign deleted. Processed {$totalProcessed} messages before deletion.",
    //                 'processed' => $totalProcessed,
    //                 'queued_now' => $queuedNow,
    //                 'scheduled' => $scheduledFuture,
    //                 'failed' => $failedCount,
    //                 'blacklisted' => $skippedBlacklist,
    //                 'duplicates_skipped' => $skippedDuplicate
    //             ];
    //         }
    //     }

    //     fclose($handle);

    //     $statusMessage = "{$totalProcessed} messages processed. {$queuedNow} queued now, {$scheduledFuture} scheduled for future.";
    //     if ($failedCount > 0) {
    //         $statusMessage .= " {$failedCount} failed.";
    //     }
    //     if ($skippedBlacklist > 0) {
    //         $statusMessage .= " {$skippedBlacklist} blacklisted.";
    //     }
    //     if ($skippedDuplicate > 0) {
    //         $statusMessage .= " {$skippedDuplicate} duplicates skipped.";
    //     }

    //     return [
    //         'status' => 'done',
    //         'message' => $statusMessage,
    //         'processed' => $totalProcessed,
    //         'queued_now' => $queuedNow,
    //         'scheduled' => $scheduledFuture,
    //         'failed' => $failedCount,
    //         'blacklisted' => $skippedBlacklist,
    //         'duplicates_skipped' => $skippedDuplicate
    //     ];
    // }


    /**
     * Process CSV file and queue/schedule SMS messages
     */
    private function processCSVFile($filepath, $userRef, $campaignId, $campaignRowId, $numLinesDonePreviously, $shortUrlAllowed)
    {
        $nowTime = Carbon::now('Europe/London')->format('YmdHis');
        $totalProcessed = 0;
        $queuedNow = 0;
        $scheduledFuture = 0;
        $failedCount = 0;
        $skippedBlacklist = 0;
        $skippedDuplicate = 0;
        $rowNum = 0;

        // Variables for tracking last values (for CSV rows that inherit from previous)
        $lastSenderId = '';
        $lastMessage = '';
        $lastRawMessage = '';
        $lastSendDateTime = '';
        $lastRoute = '7';
        $lastShortDomain = '';
        $lastLongUrl = '';
        $lastRawLongUrl = '';

        // Open file
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return ['status' => 'failed', 'message' => 'Cannot open file'];
        }

        // Enable auto-detection of line endings
        ini_set('auto_detect_line_endings', true);

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip already processed rows (for resume functionality)
            if ($numLinesDonePreviously > 0 && $rowNum <= $numLinesDonePreviously) {
                continue;
            }

            // Skip comment lines, empty lines, header rows
            $firstCol = $row[0] ?? '';
            $firstCol = str_replace(' ', '', $firstCol);

            if (empty(trim($firstCol))) {
                continue;
            }
            if (substr($firstCol, 0, 1) === '#') {
                continue;
            }
            if (strtolower(substr($firstCol, 0, 3)) === 'end') {
                break;
            }
            if (stripos($firstCol, 'mobile') !== false && $rowNum <= 2) {
                continue; // Skip header row
            }

            // Check for scientific notation error (Excel issue)
            if (strpos(strtolower($firstCol), 'e') !== false || strpos($firstCol, '.') !== false) {
                Log::warning("Scientific notation in phone number, skipping", [
                    'campaign_id' => $campaignId,
                    'row' => $rowNum,
                    'value' => $firstCol
                ]);
                $failedCount++;
                continue;
            }

            // Parse mobile number
            $mobileNumber = preg_replace('/[^0-9]/', '', $firstCol);

            // Format UK numbers
            if (substr($mobileNumber, 0, 2) === '07') {
                $mobileNumber = '44' . substr($mobileNumber, 1);
            } elseif (substr($mobileNumber, 0, 4) === '+447') {
                $mobileNumber = substr($mobileNumber, 1);
            } elseif (substr($mobileNumber, 0, 4) === '0044') {
                $mobileNumber = substr($mobileNumber, 2);
            } elseif (substr($mobileNumber, 0, 4) === '4407') {
                $mobileNumber = '447' . substr($mobileNumber, 4);
            } elseif (substr($mobileNumber, 0, 5) === '+4407') {
                $mobileNumber = '447' . substr($mobileNumber, 5);
            } elseif (substr($mobileNumber, 0, 1) === '7') {
                $mobileNumber = '44' . $mobileNumber;
            }

            // Validate length
            if (strlen($mobileNumber) < 8 || strlen($mobileNumber) > 15) {
                Log::warning("Invalid phone number length", [
                    'campaign_id' => $campaignId,
                    'row' => $rowNum,
                    'number' => $mobileNumber
                ]);
                $failedCount++;
                continue;
            }

            // Check blacklist
            $isBlacklisted = DB::table('itagg_outbound_blacklist')
                ->where('users_bigid', $userRef)
                ->where('msisdn', $mobileNumber)
                ->exists();

            if ($isBlacklisted) {
                Log::info("Number blacklisted, skipping", [
                    'campaign_id' => $campaignId,
                    'number' => $mobileNumber
                ]);
                $skippedBlacklist++;

                // Still update numlinesdone
                DB::table('smsg_campaigns')
                    ->where('id', $campaignRowId)
                    ->where('status', '!=', 'deleted')
                    ->increment('numlinesdone');
                continue;
            }

            // Parse send time (column 1) - format: YYYYMMDDHHiiss
            $sendDateTime = '';
            if (isset($row[1]) && !empty(trim($row[1]))) {
                $sendDateTime = trim($row[1]);
                // Validate format
                if (strlen($sendDateTime) !== 14) {
                    $sendDateTime = ''; // Invalid format, send now
                } elseif ($sendDateTime < date("YmdHis", strtotime("-1 days"))) {
                    $sendDateTime = ''; // Date in past, send now
                }
            }
            if (empty($sendDateTime)) {
                $sendDateTime = $lastSendDateTime;
            }
            $lastSendDateTime = $sendDateTime;

            // Parse sender ID (column 2)
            $senderId = '';
            if (isset($row[2]) && !empty(trim($row[2]))) {
                $senderId = trim($row[2]);
                // Format sender ID if it's a phone number
                if (substr($senderId, 0, 2) === '07') {
                    $senderId = '44' . substr($senderId, 1);
                } elseif (substr($senderId, 0, 4) === '+447') {
                    $senderId = substr($senderId, 1);
                } elseif (substr($senderId, 0, 4) === '0044') {
                    $senderId = substr($senderId, 2);
                } elseif (substr($senderId, 0, 1) === '7' && strlen($senderId) > 5) {
                    $senderId = '44' . $senderId;
                }
            } else {
                $senderId = $lastSenderId;
            }
            $lastSenderId = $senderId;

            // Parse message (column 3)
            $message = '';
            if (isset($row[3]) && !empty(trim($row[3]))) {
                $message = trim($row[3]);
                $lastRawMessage = $message;
            } else {
                $message = $lastRawMessage;
            }

            // Parse custom fields (columns 4, 5, 6)
            $field1 = isset($row[4]) ? trim($row[4]) : '';
            $field2 = isset($row[5]) ? trim($row[5]) : '';
            $field3 = isset($row[6]) ? trim($row[6]) : '';

            // Replace placeholders in message
            $message = str_replace('#FIELD1#', $field1, $message);
            $message = str_replace('#FIELD2#', $field2, $message);
            $message = str_replace('#FIELD3#', $field3, $message);

            // Parse route (column 7)
            $route = '7';
            if (isset($row[7]) && !empty(trim($row[7]))) {
                $route = strtolower(trim($row[7]));
            } else {
                $route = $lastRoute;
            }
            $lastRoute = $route;

            // Parse short URL fields (columns 8-12)
            $shortDomain = '';
            if (isset($row[8]) && !empty(trim($row[8]))) {
                $shortDomain = trim($row[8]);
            } else {
                $shortDomain = $lastShortDomain;
            }
            $lastShortDomain = $shortDomain;

            $longUrl = '';
            if (isset($row[9]) && !empty(trim($row[9]))) {
                $longUrl = trim($row[9]);
                $lastRawLongUrl = $longUrl;
            } else {
                $longUrl = $lastRawLongUrl;
            }

            // URL field placeholders
            $urlField1 = isset($row[10]) ? trim($row[10]) : '';
            $urlField2 = isset($row[11]) ? trim($row[11]) : '';
            $urlField3 = isset($row[12]) ? trim($row[12]) : '';

            $longUrl = str_replace('#URLFIELD1#', $urlField1, $longUrl);
            $longUrl = str_replace('#URLFIELD2#', $urlField2, $longUrl);
            $longUrl = str_replace('#URLFIELD3#', $urlField3, $longUrl);

            // Add http:// if missing
            if (!empty($longUrl) && substr(strtolower($longUrl), 0, 7) !== 'http://' && substr(strtolower($longUrl), 0, 8) !== 'https://') {
                $longUrl = 'http://' . $longUrl;
            }

            // Handle short URL generation
            if (!empty($shortDomain) && $shortUrlAllowed) {
                $shortId = $this->createShortId();

                // Ensure unique shortId
                while (DB::table('smsg_shorturl_forwarding')->where('shortid', $shortId)->exists()) {
                    $shortId = $this->createShortId();
                }

                $shortUrl = rtrim($shortDomain, '/') . '/' . $shortId . '/';
                $message = str_replace('#SHORTURL#', $shortUrl, $message);

                // Insert short URL record
                DB::table('smsg_shorturl_forwarding')->insert([
                    'status' => 'live',
                    'expirydate' => '20300101000000',
                    'userref' => $userRef,
                    'campaignref' => $campaignId,
                    'mobnum' => $mobileNumber,
                    'senderid' => urlencode($senderId),
                    'datecreated' => Carbon::now('Europe/London')->format('YmdHis'),
                    'shortdomain' => urlencode($shortDomain),
                    'shortid' => $shortId,
                    'longurl' => urlencode($longUrl),
                    'firstclickdate' => '',
                    'firstclickip' => '',
                    'numclicks' => 0,
                    'campaignrownum' => $rowNum
                ]);
            } else {
                $message = str_replace('#SHORTURL#', '', $message);
            }

            // Skip if sender ID or message is empty
            if (empty($senderId) || empty($message)) {
                Log::warning("Missing sender ID or message", [
                    'campaign_id' => $campaignId,
                    'row' => $rowNum
                ]);
                $failedCount++;
                continue;
            }

            // DUPLICATE PREVENTION: Check if this mobile number already has an SMS for this campaign
            $existingLog = DB::table('smsg_log')
                ->where('campaignref', $campaignId)
                ->where('mobnum', $mobileNumber)
                ->whereIn('sentstatus', ['pending', 'ok', 'tomorrowonward'])
                ->first();

            if ($existingLog) {
                Log::warning("Duplicate SMS detected for this campaign+mobile, skipping", [
                    'campaign_id' => $campaignId,
                    'mobile' => $mobileNumber,
                    'existing_status' => $existingLog->sentstatus,
                    'existing_bigid' => $existingLog->bigid,
                    'row' => $rowNum
                ]);

                // Still update numlinesdone to keep progress accurate
                DB::table('smsg_campaigns')
                    ->where('id', $campaignRowId)
                    ->where('status', '!=', 'deleted')
                    ->increment('numlinesdone');

                $skippedDuplicate++;
                continue;
            }

            // Calculate SMS parts
            $messageLength = mb_strlen($message, 'UTF-8');
            $numParts = $messageLength <= 160 ? 1 : (int) ceil($messageLength / 153);

            // Extract country code
            $countryCode = $this->extractCountryCode($mobileNumber);

            // Generate unique bigid
            $bigId = md5(uniqid(rand(), true));

            // Determine provider based on sender ID (from smsshortcodes.whichoperator)
            $provider = $this->determineProviderFromSender($senderId);
            $supplierName = $provider === 'sinch' ? 'Sinch SMPP' : 'Vonage SMPP';

            // Determine if scheduled or immediate
            $isScheduled = !empty($sendDateTime) && $sendDateTime > $nowTime;
            $sentStatus = $isScheduled ? 'tomorrowonward' : 'pending';
            $sentStatusText = $isScheduled ? 'Scheduled for delivery at ' . $sendDateTime : 'Queued for SMPP delivery';

            $current_date = Carbon::now('Europe/London')->format('YmdHis');

            // Get user ID
            $getUserId = User::where('bigid', $userRef)->first();

            // Calculate dosendtime and dosendtimeint (like old system)
            $dosendtime = $isScheduled ? $sendDateTime : $current_date;
            $dosendtimeint = mktime(
                (int) substr($dosendtime, 8, 2),
                (int) substr($dosendtime, 10, 2),
                (int) substr($dosendtime, 12, 2),
                (int) substr($dosendtime, 4, 2),
                (int) substr($dosendtime, 6, 2),
                (int) substr($dosendtime, 0, 4)
            );

            // Calculate daemon priority (like old system)
            $userDaemonPriority = $getUserId ? ($getUserId->daemonpriority ?? 100) : 100;
            $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) ? 200 : $userDaemonPriority; // Campaigns are bulk, use 200
            $daemonId = $baseDaemonId + mt_rand(0, 39);

            // Insert into smsg_log
            $smsgLogId = DB::table('smsg_log')->insertGetId([
                'sms_type' => 'sms',
                'initiator' => 'ControlPanel',
                'bigid' => $bigId,
                'mobnum' => $mobileNumber,
                'numparts' => $numParts,
                'text' => $message,
                'originator' => $senderId,
                'numbits' => 7,
                'timesubmitted' => $current_date,
                'userref' => $userRef,
                'affiliateref' => '0',
                'dosendtime' => $dosendtime,
                'dosendtimeint' => $dosendtimeint,
                'dayofyear' => substr($dosendtime, 0, 8),
                'timesent' => '00000000000000',
                'sentstatus' => $sentStatus,
                'sentstatustmp' => $sentStatus,
                'sentstatustext' => $sentStatusText,
                'suppliermsgref' => '',
                'smsgdaemonid' => $daemonId,
                'sendpriority' => $baseDaemonId,
                'costprice' => 0.000000,
                'userprice' => 0.000000,
                'aggregator_dlrcode' => 0,
                'aggregator_dlrmsg' => $isScheduled ? 'Scheduled - ' . $provider : 'Queued - ' . $provider,
                'campaignref' => $campaignId,
                'binaryflags' => '',
                'profit' => 0.000000,
                'countrydialcode' => $countryCode,
                // OLD SYSTEM (smssend.inc:930/1140): ofcomnetid from the Ofcom range lookup on the
                // mobile number. Ofcom caches per 4-digit prefix so a big campaign parses each once.
                'ofcomnetid' => app(\App\Services\TableCache::class)->ofcomNetId($mobileNumber),
                'suppliername' => $supplierName,
                'supplierrouteref' => $route,
                'requested_route' => 0,
                // OLD SYSTEM (smssend.inc:1150): the raw route token, verbatim (was empty).
                'requested_routetag' => $route,
                // OLD SYSTEM (smssend.inc:1148): user's per-route chargetype (pps/ppd), primary slot.
                'chargetype' => $campaignChargeType,
                // OLD parity: scheduled = sentstatus 'tomorrowonward', deliverystatus2 EMPTY (DLR column).
                'deliverystatus2' => $isScheduled ? '' : 'pending',
                // New-system send -> always 'new' so the DLR/delivery pipeline processes it
                // (it filters migration_flag = 'new'); do NOT copy the user's cutover flag,
                // which would tag an 'old' user's send 'old' and orphan it from DLR updates.
                'migration_flag' => 'new',
            ]);

            // Update campaign progress
            DB::table('smsg_campaigns')
                ->where('id', $campaignRowId)
                ->where('status', '!=', 'deleted')
                ->increment('numlinesdone');

            // If NOT scheduled, queue to SMPP immediately
            if (!$isScheduled) {
                try {
                    // SMS_ASYNC_PUBLISH=true -> publish each row to sms.outbound so the 5
                    // parallel ProcessSmsQueue workers send it (fast, uses the persistent
                    // transmitter binds). This replaces the slow per-row SYNCHRONOUS SMPP send
                    // (queueSms sends directly, one connect+submit at a time in this single
                    // campaign consumer). Same async path as the dashboard/API. When the flag
                    // is off, falls back to the legacy direct send.
                    $asyncPublish = filter_var(env('SMS_ASYNC_PUBLISH', false), FILTER_VALIDATE_BOOLEAN);
                    $smsPayload = [
                        'user_ref' => $userRef,
                        'mobile_number' => $mobileNumber,
                        'message' => $message,
                        'sender_id' => $senderId,
                        'priority' => 5,
                        'reference_id' => $bigId,
                        'provider' => $provider,
                        'metadata' => [
                            'smsg_log_id' => $smsgLogId,
                            'bigid' => $bigId,
                            'campaign_id' => $campaignId,
                            'source' => 'campaign_manager',
                            'provider' => $provider,
                            'scheduled' => false
                        ]
                    ];
                    $queueResult = $asyncPublish
                        ? $this->smsQueueService->enqueueSms($smsPayload)
                        : $this->smsQueueService->queueSms($smsPayload);

                    if ($queueResult['success']) {
                        $queuedNow++;
                        Log::debug("SMS queued for immediate delivery", [
                            'campaign_id' => $campaignId,
                            'mobile' => $mobileNumber,
                            'queue_id' => $queueResult['queue_id'] ?? null
                        ]);
                    } else {
                        $failedCount++;
                        Log::error("Failed to queue SMS", [
                            'campaign_id' => $campaignId,
                            'mobile' => $mobileNumber,
                            'error' => $queueResult['error'] ?? 'Unknown'
                        ]);

                        // Refund guard: the wallet is only charged when Nexmo ACCEPTS
                        // the message (SMPPService::storeMessageIdMapping sets
                        // sentstatus='ok' AND increments smsg_server1_sent together).
                        // So if this row was already 'ok' (charged) and we are now
                        // failing it, refund the charged userprice to the wallet. If it
                        // was never 'ok' (the normal submission-failure case) nothing was
                        // charged, so we must NOT refund — that would give free credit.
                        $chargedRow = DB::table('smsg_log')
                            ->where('id', $smsgLogId)
                            ->select('sentstatus', 'userprice', 'userref')
                            ->first();
                        if ($chargedRow && $chargedRow->sentstatus === 'ok' && (float) $chargedRow->userprice > 0) {
                            DB::table('users')
                                ->where('bigid', $chargedRow->userref)
                                ->decrement('smsg_server1_sent', (float) $chargedRow->userprice);
                            Log::info("Refunded wallet for failed SMS (Nexmo not sent)", [
                                'smsg_log_id' => $smsgLogId,
                                'userref' => $chargedRow->userref,
                                'amount' => (float) $chargedRow->userprice,
                            ]);
                        }

                        // Update smsg_log to show failure
                        DB::table('smsg_log')
                            ->where('id', $smsgLogId)
                            ->update([
                                'sentstatus' => 'fail',
                                'sentstatustext' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown')
                            ]);
                    }
                } catch (Exception $e) {
                    $failedCount++;
                    Log::error("Exception queuing SMS", [
                        'campaign_id' => $campaignId,
                        'mobile' => $mobileNumber,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                // Scheduled - the existing cron/daemon will pick it up
                $scheduledFuture++;
                Log::debug("SMS scheduled for future delivery", [
                    'campaign_id' => $campaignId,
                    'mobile' => $mobileNumber,
                    'send_time' => $sendDateTime
                ]);
            }

            $totalProcessed++;

            // Pause every 500 messages to prevent overload
            if ($totalProcessed % 500 === 0) {
                usleep(50000); // 50ms pause
                Log::info("Campaign progress", [
                    'campaign_id' => $campaignId,
                    'processed' => $totalProcessed
                ]);
            }

            // Check if campaign was deleted during processing
            $currentStatus = DB::table('smsg_campaigns')
                ->where('id', $campaignRowId)
                ->value('status');

            if ($currentStatus === 'deleted') {
                Log::info("Campaign deleted during processing", ['campaign_id' => $campaignId]);
                fclose($handle);
                return [
                    'status' => 'deleted',
                    'message' => "Campaign deleted. Processed {$totalProcessed} messages before deletion.",
                    'processed' => $totalProcessed,
                    'queued_now' => $queuedNow,
                    'scheduled' => $scheduledFuture,
                    'failed' => $failedCount,
                    'blacklisted' => $skippedBlacklist,
                    'duplicates_skipped' => $skippedDuplicate
                ];
            }
        }

        fclose($handle);

        $statusMessage = "{$totalProcessed} messages processed. {$queuedNow} queued now, {$scheduledFuture} scheduled for future.";
        if ($failedCount > 0) {
            $statusMessage .= " {$failedCount} failed.";
        }
        if ($skippedBlacklist > 0) {
            $statusMessage .= " {$skippedBlacklist} blacklisted.";
        }
        if ($skippedDuplicate > 0) {
            $statusMessage .= " {$skippedDuplicate} duplicates skipped.";
        }

        return [
            'status' => 'done',
            'message' => $statusMessage,
            'processed' => $totalProcessed,
            'queued_now' => $queuedNow,
            'scheduled' => $scheduledFuture,
            'failed' => $failedCount,
            'blacklisted' => $skippedBlacklist,
            'duplicates_skipped' => $skippedDuplicate
        ];
    }

    /**
     * Update campaign status
     */
    private function updateCampaignStatus($campaignRowId, $status, $statusInfo)
    {
        DB::table('smsg_campaigns')
            ->where('id', $campaignRowId)
            ->update([
                'status' => $status,
                'statusinfo' => $statusInfo,
                'canpausedelete' => $status === 'done' ? 'y' : 'n'
            ]);
    }

    /**
     * Create random short ID
     */
    private function createShortId()
    {
        $src = "abc012ABCdef345DEFghijklm67GHIJKLMnopqrs89NOPQRSTtuvwxyzUVWXYZ";
        $shortId = '';
        for ($i = 0; $i < 8; $i++) {
            $shortId .= $src[rand(0, strlen($src) - 1)];
        }
        return $shortId;
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        $countries = DB::table('country')
            ->select('dialcode')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();

        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country->dialcode;
            }
        }

        return '44'; // Default to UK
    }

    /**
     * Determine SMPP provider based on sender ID (from number)
     * Checks smsshortcodes.whichoperator field to determine if Sinch or Nexmo
     *
     * @param string $senderId The sender ID (from number)
     * @return string 'sinch' or 'nexmo'
     */
    private function determineProviderFromSender(string $senderId): string
    {
        // If alphanumeric sender ID, default to nexmo
        if (!preg_match('/^[0-9]+$/', $senderId)) {
            return 'nexmo';
        }

        // Format UK mobile numbers
        if (substr($senderId, 0, 2) === '07') {
            $senderId = '447' . substr($senderId, 2);
        }

        // Check smsshortcodes table for this number
        $shortcode = DB::table('smsshortcodes')
            ->where('number', $senderId)
            ->first();

        if ($shortcode && !empty($shortcode->whichoperator)) {
            $operator = strtolower($shortcode->whichoperator);

            // Check for Sinch/mBlox indicators
            if (str_contains($operator, 'sinch') || str_contains($operator, 'mblox')) {
                Log::info("Campaign: Provider determined from sender", [
                    'sender_id' => $senderId,
                    'operator' => $shortcode->whichoperator,
                    'provider' => 'sinch'
                ]);
                return 'sinch';
            }
        }

        // Default to nexmo
        return 'nexmo';
    }

    /**
     * Start consuming campaign queue
     */
    public function startConsumer()
    {
        Log::info("Starting campaign queue consumer");

        $this->rabbitMQ->consumeFromQueue(
            self::QUEUE_CAMPAIGN,
            function ($data) {
                return $this->processCampaign($data);
            },
            1 // Process one campaign at a time
        );
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats()
    {
        return $this->rabbitMQ->getQueueStats(self::QUEUE_CAMPAIGN);
    }
}
