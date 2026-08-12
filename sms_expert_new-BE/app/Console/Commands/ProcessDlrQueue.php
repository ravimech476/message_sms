<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\SMPP\SMPPService;
use App\Services\UserRouteService;
use App\Services\DeliveryStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class ProcessDlrQueue extends Command
{
    protected $signature = 'smpp:dlr-receiverss {--continuous} {--timeout=0} {--batch-size=100} {--interval=5} {--process-queue-only} {--process-smpp-only}';
    protected $description = 'Process DLR (Delivery Receipt) messages from SMPP and RabbitMQ queue';

    private $smpp;
    private $rabbitMQ;
    private DeliveryStatusService $deliveryStatusService;
    private $processedCount = 0;
    private $failedCount = 0;
    private $deletedCount = 0;
    private $shouldStop = false;
    private $startTime;
    private $batchSize;
    private $lastStatsTime;
    private $processingActive = false;

    public function __construct(DeliveryStatusService $deliveryStatusService)
    {
        parent::__construct();
        $this->deliveryStatusService = $deliveryStatusService;
    }

    public function handle()
    {
        $continuous = $this->option('continuous');
        $timeout = $this->option('timeout');
        $this->batchSize = $this->option('batch-size');
        $interval = $this->option('interval');
        $queueOnly = $this->option('process-queue-only');
        $smppOnly = $this->option('process-smpp-only');

        $this->startTime = Carbon::now();
        $this->lastStatsTime = Carbon::now();

        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║          DLR RECEIVER STARTED                          ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("Mode: " . ($continuous ? "Continuous" : "Single Run"));
        $this->info("Batch Size: {$this->batchSize}");
        $this->info("Started at: " . $this->startTime->format('Y-m-d H:i:s'));
        $this->info("");

        try {
            // Initialize RabbitMQ
            if (!$smppOnly) {
                $this->info("▶ Initializing RabbitMQ connection...");
                try {
                    $this->rabbitMQ = new RabbitMQService();
                    $stats = $this->rabbitMQ->getQueueStats('sms.dlr');
                    if (isset($stats['messages'])) {
                        $this->info("✓ RabbitMQ connected - DLR Queue has {$stats['messages']} messages waiting");
                    }
                } catch (Exception $e) {
                    $this->error("✗ RabbitMQ connection failed: " . $e->getMessage());
                    if ($queueOnly) {
                        return 1;
                    }
                }
            }

            // Initialize SMPP
            if (!$queueOnly) {
                $this->info("▶ Connecting to SMPP server for DLR reception...");
                try {
                    $this->smpp = new SMPPService();
                    $this->smpp->connect();
                    $this->info("✓ SMPP connected successfully");
                } catch (Exception $e) {
                    $this->warn("⚠ SMPP connection failed: " . $e->getMessage());
                    if ($smppOnly) {
                        return 1;
                    }
                    $this->smpp = null;
                }
            }

            $this->info("");

            // Register signal handlers
            if (extension_loaded('pcntl')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
                pcntl_signal(SIGINT, [$this, 'handleShutdown']);

                if ($timeout > 0) {
                    pcntl_alarm($timeout);
                    pcntl_signal(SIGALRM, [$this, 'handleTimeout']);
                }
            }

            if ($continuous) {
                $this->runContinuous($interval, $queueOnly, $smppOnly);
            } else {
                $this->runOnce($queueOnly, $smppOnly);
            }
        } catch (Exception $e) {
            $this->error("DLR Receiver Error: " . $e->getMessage());
            Log::error("DLR Receiver Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->cleanup();
        }

        return 0;
    }

    private function runContinuous($interval, $queueOnly, $smppOnly)
    {
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║     CONTINUOUS MODE - Press Ctrl+C to stop            ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("");

        $cycleCount = 0;

        while (!$this->shouldStop) {
            $cycleCount++;
            $cycleStart = Carbon::now();

            $this->info("┌─ Cycle #{$cycleCount} ─ " . $cycleStart->format('H:i:s') . " ─────────────────────────");

            $processedThisCycle = 0;

            // Process incoming DLRs from SMPP
            if ($this->smpp && !$queueOnly) {
                $smppProcessed = $this->processIncomingDlrs();
                $processedThisCycle += $smppProcessed;
            }

            // Process queued DLRs from RabbitMQ
            if ($this->rabbitMQ && !$smppOnly) {
                $queueProcessed = $this->processQueuedDlrs();
                $processedThisCycle += $queueProcessed;
            }

            $cycleDuration = Carbon::now()->diffInSeconds($cycleStart);
            $this->info("└─ Cycle complete: {$processedThisCycle} DLRs in {$cycleDuration}s ────────────────");

            // Show statistics every 10 cycles
            if ($cycleCount % 10 == 0 || Carbon::now()->diffInSeconds($this->lastStatsTime) >= 60) {
                $this->showStatistics();
                $this->lastStatsTime = Carbon::now();
            }

            // Sleep
            if (!$this->shouldStop && $processedThisCycle == 0) {
                $sleepTime = $interval;
                $this->line("  ⏸ Waiting {$sleepTime}s for next check...");
                $this->info("");
                for ($i = 0; $i < $sleepTime; $i++) {
                    if ($this->shouldStop) break;
                    sleep(1);
                }
            } else if (!$this->shouldStop) {
                $this->line("  ⏸ Brief pause...");
                $this->info("");
                sleep(1);
            }
        }
    }

    private function runOnce($queueOnly, $smppOnly)
    {
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║     SINGLE RUN MODE                                    ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("");

        // Process incoming DLRs from SMPP
        if ($this->smpp && !$queueOnly) {
            $this->processIncomingDlrs();
        }

        // Process ALL queued DLRs
        if ($this->rabbitMQ && !$smppOnly) {
            $this->info("Processing all queued DLRs...");
            $totalProcessed = 0;

            while (!$this->shouldStop) {
                $processed = $this->processQueuedDlrs();
                $totalProcessed += $processed;

                if ($processed == 0) {
                    break;
                }

                if ($totalProcessed % ($this->batchSize * 5) == 0) {
                    $this->showStatistics();
                }
            }

            $this->info("✓ Completed processing {$totalProcessed} queued DLRs");
        }

        $this->info("");
        $this->showStatistics();
    }

    private function processIncomingDlrs()
    {
        if (!$this->smpp) {
            return 0;
        }

        $this->line("│ ▶ Checking SMPP for incoming DLRs...");

        try {
            $dlrCount = $this->smpp->processIncomingPdus();

            if ($dlrCount > 0) {
                $this->info("│ ✓ Received {$dlrCount} DLR(s) from SMPP");
                $this->processedCount += $dlrCount;
                return $dlrCount;
            } else {
                $this->line("│   No new DLRs from SMPP");
                return 0;
            }
        } catch (Exception $e) {
            $this->error("│ ✗ Error processing SMPP DLRs: " . $e->getMessage());

            // Try to reconnect
            try {
                $this->warn("│ ⟳ Attempting SMPP reconnection...");
                $this->smpp->disconnect();
                sleep(2);
                $this->smpp->connect();
                $this->info("│ ✓ SMPP reconnected successfully");
            } catch (Exception $reconnectError) {
                $this->error("│ ✗ Reconnection failed: " . $reconnectError->getMessage());
                $this->smpp = null;
            }

            return 0;
        }
    }

    /**
     * Process queued DLRs from RabbitMQ
     * Messages are automatically deleted (ACK'd) after callback returns
     */
    private function processQueuedDlrs()
    {
        if (!$this->rabbitMQ) {
            return 0;
        }

        $this->line("│ ▶ Processing DLR queue...");

        try {
            $stats = $this->rabbitMQ->getQueueStats('sms.dlr');
            $messageCount = $stats['messages'] ?? 0;

            if ($messageCount == 0) {
                $this->line("│   Queue is empty");
                return 0;
            }

            $this->info("│   Found {$messageCount} DLR(s) in queue");

            $processed = 0;
            $failed = 0;
            $batchSize = min($messageCount, $this->batchSize);

            $progressBar = $this->output->createProgressBar($batchSize);
            $progressBar->setFormat('│   Processing: %current%/%max% [%bar%] %percent:3s%% ');
            $progressBar->start();

            $this->processingActive = true;
            $processedInBatch = 0;

            // Consume messages from queue with auto-acknowledgment
            $this->rabbitMQ->consumeFromQueue(
                'sms.dlr',
                function ($dlrData) use (&$processed, &$failed, &$processedInBatch, $progressBar, $batchSize) {
                    try {
                        // Process the DLR
                        $result = $this->processDlr($dlrData);

                        if ($result) {
                            $processed++;
                            $this->deletedCount++;
                            Log::info("DLR processed and deleted from queue", [
                                'message_id' => $dlrData['message_id'] ?? 'unknown'
                            ]);
                        } else {
                            $failed++;
                            Log::warning("DLR processing failed but removed from queue", [
                                'message_id' => $dlrData['message_id'] ?? 'unknown'
                            ]);
                        }

                        $processedInBatch++;
                        $progressBar->advance();

                        // Stop after batch size
                        if ($processedInBatch >= $batchSize) {
                            return 'stop';
                        }

                        // Return true to ACK (delete) the message
                        return true;
                    } catch (Exception $e) {
                        Log::error("Error in DLR callback", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $failed++;
                        $progressBar->advance();

                        // Return true to ACK even on error (prevent queue blocking)
                        return true;
                    }
                },
                $this->batchSize
            );

            $this->processingActive = false;

            $progressBar->finish();
            $this->info("");

            if ($processed > 0) {
                $this->info("│ ✓ Processed {$processed} DLR(s) and deleted from queue" . ($failed > 0 ? " ({$failed} failed but removed)" : ""));
                $this->processedCount += $processed;
                $this->failedCount += $failed;
            }

            return $processed;
        } catch (Exception $e) {
            $this->error("│ ✗ Error processing queue: " . $e->getMessage());
            Log::error("Queue processing error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Process a single DLR using DeliveryStatusService (OLD SYSTEM compatible)
     */
    public function processDlr($dlrData)
    {
        try {
            if (!is_array($dlrData)) {
                Log::warning("Invalid DLR data format", ['data' => $dlrData]);
                return false;
            }

            Log::info("Processing DLR (OLD SYSTEM logic)", [
                'message_id' => $dlrData['message_id'] ?? 'unknown',
                'status' => $dlrData['status'] ?? 'unknown',
                'mobile' => $dlrData['mobile_number'] ?? 'unknown'
            ]);

            // Validate required fields
            if (!isset($dlrData['message_id']) || empty($dlrData['message_id'])) {
                Log::warning("DLR missing message_id", $dlrData);
                return false;
            }

            // Prepare DLR data for DeliveryStatusService
            // Map SMPP status to format expected by the service
            $status = $dlrData['status'] ?? 'UNKNOWN';
            $errorCode = $dlrData['error_code'] ?? 0;

            // Map SMPP status codes to reason codes for OLD SYSTEM processing
            $mappedErrorCode = $this->mapSmppStatusToErrorCode($status, $errorCode);

            $dlrPayload = [
                'message_id' => $dlrData['message_id'],
                'mobile_number' => $dlrData['mobile_number'] ?? $dlrData['source'] ?? '',
                'status' => $status,
                'error_code' => $mappedErrorCode,
                'done_date' => $dlrData['done_date'] ?? Carbon::now()->format('YmdHis'),
                'provider' => $dlrData['provider'] ?? 'nexmo',
                'aggregator_code' => $errorCode,
                'aggregator_msg' => $dlrData['status_text'] ?? $status,
                'retry' => $dlrData['retry'] ?? '0',
                'raw_data' => $dlrData,
            ];

            // Use DeliveryStatusService for OLD SYSTEM compatible processing
            $result = $this->deliveryStatusService->processDeliveryReceipt($dlrPayload);

            if ($result) {
                // Store in DLR table for tracking
                $this->storeDlrRecord($dlrData);

                // Update queue record if exists
                if (isset($dlrData['queue_id'])) {
                    $this->updateQueueRecord($dlrData);
                }

                Log::info("DLR processed successfully (OLD SYSTEM)", [
                    'message_id' => $dlrData['message_id'],
                    'will_be_deleted' => true
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error("Failed to process DLR", [
                'error' => $e->getMessage(),
                'data' => $dlrData,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Map SMPP status codes to OLD SYSTEM reason/error codes
     */
    private function mapSmppStatusToErrorCode(string $status, $currentErrorCode = 0): int
    {
        // If we already have an error code from the provider, use it
        if ($currentErrorCode > 0 && $currentErrorCode != 0) {
            return (int) $currentErrorCode;
        }

        // Map SMPP status to OLD SYSTEM reason codes
        $statusMap = [
            'DELIVRD' => 4,   // Delivered to mobile device
            'EXPIRED' => 8,   // Message expired
            'DELETED' => 20,  // Permanent Operator error
            'UNDELIV' => 5,   // Failed, no further info
            'ACCEPTD' => 3,   // Delivered to network (acked)
            'ACKED'   => 3,   // Delivered to network
            'UNKNOWN' => 6,   // Final status unknown
            'REJECTD' => 27,  // Barred by User
            'BUFFERED' => 2,  // Buffered at SMSC
        ];

        $upperStatus = strtoupper($status);
        return $statusMap[$upperStatus] ?? 6; // Default to 'unknown'
    }

    private function updateSmsgLogWithDlr($dlrData)
    {
        try {
            // Ensure data is always an array
            $data = is_array($dlrData) ? $dlrData : json_decode($dlrData, true);

            if (empty($data)) {
                Log::warning("updateSmsgLogWithDlr: Invalid or empty DLR data", [
                    'raw' => $dlrData
                ]);
                return;
            }

            // Log structured DLR data
            Log::info("updateSmsgLogWithDlr ", $data);

            $deliveryStatus = $this->mapDlrStatusForSmsgLog($data['status'] ?? '');
            $deliveryTime = isset($data['done_date'])
                ? Carbon::parse($data['done_date'])->format('YmdHi')
                : Carbon::now()->format('YmdHi');

            // Use bigid for direct update if available
            if (!empty($data['bigid'])) {
                // Direct update using bigid
                $this->updateSmsgLogByBigid($data['bigid'], $deliveryStatus, $deliveryTime, $data);
            } else if (!empty($data['message_id'])) {
                // Try to find the message by message_id first
                $existingRecord = null;

                // Check if we have queue_id
                if (!empty($data['queue_id'])) {
                    $existingRecord = DB::table('sms_queue')
                        ->where('queue_id', $data['queue_id'])
                        ->first();
                }

                // If not found by queue_id, try by message_id
                if (!$existingRecord) {
                    $existingRecord = DB::table('sms_queue')
                        ->where('message_id', $data['message_id'])
                        ->first();
                }

                // If still not found, try to find in smsg_log directly
                if (!$existingRecord) {
                    // Extract phone number from source/destination
                    // Note: In DLR, source is usually the recipient number
                    $phoneNumber = $data['source'] ?? '';
                    if (!$phoneNumber || $phoneNumber == 'MYBRANDNAME') {
                        $phoneNumber = $data['destination'] ?? '';
                    }
                    // Clean phone number
                    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

                    $messageId = $data['message_id'] ?? '';
                    $smsgLogRecord = null;

                    // First try to find by message_id in onesixty_suppliermsgref (decimal format for Vonage DLR)
                    if (!empty($messageId)) {
                        $smsgLogRecord = DB::table('smsg_log')
                            ->where('onesixty_suppliermsgref', $messageId)
                            ->where('migration_flag', 'new')
                            ->first();

                        if ($smsgLogRecord) {
                            Log::info("DLR: Found record by onesixty_suppliermsgref", ['message_id' => $messageId]);
                        }
                    }

                    // deliveryreceipt1 fallback REMOVED — it's un-indexed (full scan). The match
                    // above on the indexed onesixty_suppliermsgref (which holds the same hex id)
                    // is the fast, single-seek path.

                    // Vonage DLR sends message_id in decimal, try converting to hex
                    if (!$smsgLogRecord && !empty($messageId) && ctype_digit($messageId)) {
                        // For large decimal numbers, use GMP if available
                        if (strlen($messageId) > 15 && function_exists('gmp_strval')) {
                            $hexMessageId = gmp_strval(gmp_init($messageId, 10), 16);
                        } else {
                            $hexMessageId = dechex((int)$messageId);
                        }

                        Log::debug("DLR: Trying decimal to hex conversion", [
                            'decimal' => $messageId,
                            'hex' => $hexMessageId
                        ]);

                        $smsgLogRecord = DB::table('smsg_log')
                            ->where('deliveryreceipt1', $hexMessageId)
                            ->where('migration_flag', 'new')
                            ->first();

                        if ($smsgLogRecord) {
                            Log::info("DLR: Found record by hex conversion", ['hex' => $hexMessageId]);
                        }
                    }

                    // Try smpp_message_mapping table
                    if (!$smsgLogRecord && !empty($messageId)) {
                        $mapping = DB::table('smpp_message_mapping')
                            ->where('message_id', $messageId)
                            ->first();

                        // Also try with hex conversion if message_id is decimal
                        if (!$mapping && ctype_digit($messageId)) {
                            // For large decimal numbers, use GMP if available
                            if (strlen($messageId) > 15 && function_exists('gmp_strval')) {
                                $hexMessageId = gmp_strval(gmp_init($messageId, 10), 16);
                            } else {
                                $hexMessageId = dechex((int)$messageId);
                            }
                            $mapping = DB::table('smpp_message_mapping')
                                ->where('message_id', $hexMessageId)
                                ->first();
                        }

                        if ($mapping) {
                            $smsgLogRecord = DB::table('smsg_log')
                                ->where('bigid', $mapping->bigid)
                                ->when(!empty($mapping->mobile_number), function ($q) use ($mapping) {
                                    // a bigid can cover several numbers — target this DLR's recipient row
                                    $q->where('mobnum', $mapping->mobile_number);
                                })
                                ->where('migration_flag', 'new')
                                ->first();

                            if ($smsgLogRecord) {
                                Log::info("DLR: Found record via smpp_message_mapping", [
                                    'message_id' => $messageId,
                                    'bigid' => $mapping->bigid
                                ]);
                            }
                        }
                    }

                    // Fallback: Try by mobile number. Match on the trailing significant digits
                    // so the country-code prefix can't break it — the provider DLR reports the
                    // full international MSISDN (e.g. India "919003096885"), but stored mobnum may
                    // omit/alter the 91. Exact match worked for UK, not India.
                    if (!$smsgLogRecord && $phoneNumber) {
                        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
                        $suffix = strlen($cleanNumber) >= 9 ? substr($cleanNumber, -9) : $cleanNumber;
                        $smsgLogRecord = DB::table('smsg_log')
                            ->where(function ($q) use ($cleanNumber, $suffix) {
                                $q->where('mobnum', $cleanNumber)
                                  ->orWhere('mobnum', 'like', '%' . $suffix);
                            })
                            ->where('sentstatus', 'ok')
                            ->where('migration_flag', 'new')
                            ->where(function ($q) {
                                $q->whereNull('deliverystatus2')->orWhere('deliverystatus2', '');
                            })
                            ->orderBy('id', 'desc')
                            ->first();

                        if ($smsgLogRecord) {
                            Log::info("DLR: Found record by mobile number fallback (suffix match)", ['mobile' => $cleanNumber, 'matched_mobnum' => $smsgLogRecord->mobnum]);
                        }
                    }

                    Log::info("SMSG Log Record lookup result", [
                        'message_id' => $messageId,
                        'phone' => $phoneNumber,
                        'found' => $smsgLogRecord ? true : false
                    ]);

                    if ($smsgLogRecord) {
                        // Calculate pricing and all required fields for direct match
                        $updateData = $this->calculateDlrUpdateData($smsgLogRecord, $deliveryStatus, $deliveryTime, $data);

                        // Perform update with all required fields
                        DB::table('smsg_log')
                            ->where('id', $smsgLogRecord->id)
                            ->update($updateData);

                        // NOTE: Wallet deduction is done in storeMessageIdMapping() when SMS is sent
                        // Do NOT deduct again here during DLR processing to avoid double charging

                        // Handle DLR push callback
                        $this->processDlrPushCallback($smsgLogRecord, $deliveryStatus, $updateData);

                        Log::info("Updated smsg_log with DLR (direct match) - with all fields", [
                            'phone' => $phoneNumber,
                            'status' => $deliveryStatus,
                            'message_id' => $data['message_id'],
                            'updateData' => $updateData
                        ]);
                        return;
                    }

                    Log::warning("Could not find matching record for DLR", [
                        'message_id' => $data['message_id'],
                        'phone' => $phoneNumber
                    ]);
                    return;
                }
                if ($existingRecord) {
                    $metaData = json_decode($existingRecord->metadata, true);
                    $updateData = [
                        'deliverystatus1'    => 'acked',
                        'deliverystatus2'    => $deliveryStatus,
                        'deliverytime1'      => $deliveryTime,
                        'deliverytime2'      => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
                        'aggregator_dlrcode' => $data['error_code'] ?? 0,
                        'aggregator_dlrmsg'  => $data['status_text'] ?? $data['status'] ?? ''

                    ];
                    $existingRecordsmsgLogs = DB::table('smsg_log')
                        ->where('bigid',  $metaData['bigid'])
                        ->first();
                    // Copy delivery_receipt1 to delivery_receipt2 if exists
                    if ($existingRecordsmsgLogs && !empty($existingRecordsmsgLogs->deliveryreceipt1)) {
                        $updateData['deliveryreceipt2'] = $existingRecordsmsgLogs->deliveryreceipt1;
                    }

                    // OLD SYSTEM: Use smsg_userroute + smsg_route for pricing
                    // costprice comes from smsg_route table (based on route/provider: Nexmo, Sinch, Mblox)
                    // userprice comes from smsg_userroute table (user-specific rate)
                    $thecountrycodes = $this->extractCountryCode($existingRecordsmsgLogs->mobnum);
                    Log::info("OLD SYSTEM pricing - countrydialcode: " . $thecountrycodes);
                    Log::info("OLD SYSTEM pricing - Mobilenumber: " . $existingRecordsmsgLogs->mobnum);

                    $userBigId = $existingRecordsmsgLogs->userref;
                    $getUserId = DB::table('users')
                        ->where('bigid',  $userBigId)
                        ->first();

                    // Use OLD SYSTEM pricing logic via smsg_userroute table
                    $userRouteService = app(UserRouteService::class);

                    // Get route number from smsg_log record or default to 7002
                    $routenum = $existingRecordsmsgLogs->routenum ?? $existingRecordsmsgLogs->requested_route ?? 7002;

                    // Get pricing using old system logic (smsg_userroute + smsg_route tables)
                    // This gets: userprice from smsg_userroute, costprice from smsg_route (provider-specific)
                    $pricing = $userRouteService->getPricingForPhoneNumber(
                        $userBigId,
                        $existingRecordsmsgLogs->mobnum,
                        $routenum,
                        $existingRecordsmsgLogs->numbits ?? 7,  // numbits
                        'alpha'  // origtype
                    );

                    Log::info("OLD SYSTEM pricing result", [
                        'routenum' => $routenum,
                        'userprice' => $pricing['userprice'],
                        'costprice' => $pricing['costprice'],
                        'profit' => $pricing['profit'],
                        'suppliername' => $pricing['suppliername'] ?? 'unknown'
                    ]);

                    $updateData['userprice'] = $pricing['userprice'];
                    $updateData['costprice'] = $pricing['costprice'];  // From smsg_route (Nexmo/Sinch/Mblox)
                    $updateData['profit'] = $pricing['profit'];
                    $updateData['countrydialcode'] = $thecountrycodes;
                    // Perform update
                    $updated = DB::table('smsg_log')
                        ->where('bigid',  $metaData['bigid'])
                        ->update($updateData);

                    $bigId = $getUserId->bigid;
                    $smsgServer1Sent = $getUserId->smsg_server1_sent + $updateData['userprice'];
                    // Increment user's total_credits_sent
                    DB::table('users')
                        ->where('bigid', $bigId)
                        ->update(['smsg_server1_sent' => $smsgServer1Sent]);


                    $dreceiptUrlDetails = app(\App\Services\TableCache::class)->useroption($bigId); // cached per account (Phase 2)
                    Log::info("Updated smsg_log with DLR", [
                        'data' => $dreceiptUrlDetails
                    ]);
                    // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL
                    // is the PER-MESSAGE smsg_log.dreceipt_url, NOT useroption.dreceipt_push_url.
                    $dreceiptUrl = $existingRecordsmsgLogs->dreceipt_url ?? '';
                    if (
                        $dreceiptUrlDetails &&
                        strlen($dreceiptUrl) > 10 &&
                        $dreceiptUrlDetails->dreceipt_tries_num > 0 &&
                        intval($dreceiptUrlDetails->dreceipt_retries_wait_mins) >= 0
                    ) {
                        $time = Carbon::now()->format('YmdHis');

                        $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                            <itagg_delivery_receipt>
                                <version>1.1</version>
                                <msisdn>{$existingRecordsmsgLogs->mobnum}</msisdn>
                                <submission_ref>{$updateData['deliveryreceipt2']}</submission_ref>
                                <status>{$deliveryStatus}</status>
                                <reason>4</reason>
                                <gmt_timestamp>{$time}</gmt_timestamp>
                                <retry>0</retry>
                            </itagg_delivery_receipt>";

                        DB::table('delivery_receipt_push_log')->insert([
                            'thismsgreference' => $updateData['deliveryreceipt2'],
                            'msisdn'           => $existingRecordsmsgLogs->mobnum,
                            'smsg_log_bigid'   => $metaData['bigid'],
                            'users_bigid'      => $bigId,
                            'timestamp'        => $time,
                            'status'           => 'new',
                            'message_status'   => $deliveryStatus,
                            'reason'           => '4',
                            'url'              => $dreceiptUrl,
                            'inserted_time'    => Carbon::now()->format('YmdHis'),
                            'retries_left'     => $dreceiptUrlDetails->dreceipt_tries_num,
                            'wait_minutes'     => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                            'dosendtime'       => Carbon::now()->format('Y-m-d H:i:s'),
                            'xml'              => $itagg_receipt_xml,
                            'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                            'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
                        ]);

                        Log::info("Inserted into delivery_receipt_push_log for DLR push ", [
                            'message_id' => $updateData['deliveryreceipt2'],
                            'url'        => $dreceiptUrl
                        ]);
                    }


                    Log::info("Updated smsg_log with DLR", [
                        'message_id' => $metaData['smsg_log_id'],
                        'status'     => $deliveryStatus,
                        'updated'    => $updated
                    ]);
                } else {
                    Log::warning("sms_queue not found : ", ['data' => $data]);
                }
            } else {
                Log::warning("DLR data does not contain message_id", ['data' => $data]);
            }
        } catch (Exception $e) {
            Log::warning("Failed to update smsg_log with DLR: " . $e->getMessage(), [
                'raw' => $dlrData
            ]);
        }
    }

    private function storeDlrRecord($dlrData)
    {
        try {
            $exists = DB::table('sms_dlr')
                ->where('message_id', $dlrData['message_id'])
                ->exists();

            if ($exists) {
                DB::table('sms_dlr')
                    ->where('message_id', $dlrData['message_id'])
                    ->update([
                        'status' => $dlrData['status'] ?? 'UNKNOWN',
                        'status_text' => $dlrData['status_text'] ?? '',
                        'error_code' => $dlrData['error_code'] ?? null,
                        'done_date' => $dlrData['done_date'] ?? Carbon::now(),
                        'raw_dlr' => json_encode($dlrData),
                        'updated_at' => Carbon::now()
                    ]);
            } else {
                DB::table('sms_dlr')->insert([
                    'message_id' => $dlrData['message_id'],
                    'queue_id' => $dlrData['queue_id'] ?? '12365',
                    'mobile_number' => $dlrData['mobile_number'] ?? '',
                    'status' => $dlrData['status'] ?? 'UNKNOWN',
                    'status_text' => $dlrData['status_text'] ?? '',
                    'error_code' => $dlrData['error_code'] ?? null,
                    'submit_date' => $dlrData['submit_date'] ?? null,
                    'done_date' => $dlrData['done_date'] ?? Carbon::now(),
                    'raw_dlr' => json_encode($dlrData),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to store DLR record", [
                'error' => $e->getMessage(),
                'message_id' => $dlrData['message_id'] ?? 'unknown'
            ]);
            throw $e;
        }
    }

    private function updateQueueRecord($dlrData)
    {
        try {
            if (!isset($dlrData['queue_id'])) {
                return;
            }

            $deliveryStatus = $this->mapDlrStatus($dlrData['status'] ?? '');

            DB::table('sms_queue')
                ->where('queue_id', $dlrData['queue_id'])
                ->update([
                    'delivery_status' => $deliveryStatus,
                    'delivery_time' => Carbon::now(),
                    'error_code' => $dlrData['error_code'] ?? null,
                    'error_message' => $dlrData['status_text'] ?? '',
                    'updated_at' => Carbon::now()
                ]);
        } catch (Exception $e) {
            Log::warning("Failed to update queue record", [
                'error' => $e->getMessage(),
                'queue_id' => $dlrData['queue_id']
            ]);
        }
    }

    /**
     * Map DLR status (OLD SYSTEM compatible)
     */
    private function mapDlrStatus($status)
    {
        // Use consistent OLD SYSTEM status mapping
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Non Delivered',
            'DELETED' => 'Non Delivered',
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'acked',
            'ACKED'   => 'acked',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Non Delivered',
            'BUFFERED' => 'buffered smsc',
        ];

        return $statusMap[strtoupper($status)] ?? 'Unknown';
    }

    /**
     * Map DLR status for smsg_log (uses OLD SYSTEM logic via DeliveryStatusService)
     * This is kept for backward compatibility with legacy code paths
     */
    private function mapDlrStatusForSmsgLog($status)
    {
        // Map SMPP status to error code first
        $errorCode = $this->mapSmppStatusToErrorCode($status);

        // Use OLD SYSTEM status mapping
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Non Delivered',  // OLD SYSTEM: expired = Non Delivered
            'DELETED' => 'Non Delivered',  // OLD SYSTEM: deleted = Non Delivered
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'acked',          // OLD SYSTEM: accepted = acked
            'ACKED'   => 'acked',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Non Delivered',  // OLD SYSTEM: rejected = Non Delivered
            'BUFFERED' => 'buffered smsc', // OLD SYSTEM: buffered status
        ];

        return $statusMap[strtoupper($status)] ?? 'Unknown';
    }

    private function extractCountryCode($phoneNumber)
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Try to extract country code (1-4 digits at the start)
        // Common patterns: +91, +1, +44, +86, etc.
        if (strlen($cleaned) >= 10) {
            // Try 3 digit country code first (like 971 for UAE)
            $countryCode = substr($cleaned, 0, 3);
            if (in_array($countryCode, ['971', '966', '965', '968', '974', '973'])) {
                return $countryCode;
            }

            // Try 2 digit country code (like 91 for India, 44 for UK)
            $countryCode = substr($cleaned, 0, 2);
            if (in_array($countryCode, ['91', '44', '86', '81', '82', '61', '64', '65'])) {
                return $countryCode;
            }

            // Try 1 digit country code (like 1 for US/Canada)
            $countryCode = substr($cleaned, 0, 1);
            if ($countryCode == '1') {
                return $countryCode;
            }
        }

        // Default fallback
        return substr($cleaned, 0, 2);
    }

    private function updateSmsgLogByBigid($bigid, $deliveryStatus, $deliveryTime, $data)
    {
        try {
            // ONLY process NEW SYSTEM records - OLD SYSTEM daemon handles its own records
            $smsgLogRecord = DB::table('smsg_log')
                ->where('bigid', $bigid)
                ->where('migration_flag', 'new')  // Only NEW system records
                ->first();

            if (!$smsgLogRecord) {
                Log::warning("smsg_log record not found for bigid (or is OLD system record)", ['bigid' => $bigid]);
                return;
            }

            // Calculate pricing and all required fields
            $updateData = $this->calculateDlrUpdateData($smsgLogRecord, $deliveryStatus, $deliveryTime, $data);

            // Perform update
            DB::table('smsg_log')
                ->where('bigid', $bigid)
                ->where('migration_flag', 'new')  // Only NEW system records
                ->update($updateData);

            // NOTE: Wallet deduction is done in storeMessageIdMapping() when SMS is sent
            // Do NOT deduct again here during DLR processing to avoid double charging

            // Handle DLR push callback
            $this->processDlrPushCallback($smsgLogRecord, $deliveryStatus, $updateData);

            Log::info("Updated smsg_log by bigid with all fields", [
                'bigid' => $bigid,
                'status' => $deliveryStatus
            ]);
        } catch (Exception $e) {
            Log::error("Failed to update smsg_log by bigid", [
                'error' => $e->getMessage(),
                'bigid' => $bigid
            ]);
        }
    }

    private function calculateDlrUpdateData($smsgLogRecord, $deliveryStatus, $deliveryTime, $data)
    {
        $updateData = [
            'deliverystatus1'    => 'acked',
            'deliverystatus2'    => $deliveryStatus,
            'deliverytime1'      => $deliveryTime,
            'deliverytime2'      => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
            'aggregator_dlrcode' => $data['error_code'] ?? 0,
            'aggregator_dlrmsg'  => $data['status_text'] ?? $data['status'] ?? ''
        ];

        // Copy delivery_receipt1 to delivery_receipt2 if exists
        if (!empty($smsgLogRecord->deliveryreceipt1)) {
            $updateData['deliveryreceipt2'] = $smsgLogRecord->deliveryreceipt1;
        }

        // Extract country code and calculate pricing
        $countryCode = $this->extractCountryCode($smsgLogRecord->mobnum);
        $updateData['countrydialcode'] = $countryCode;

        // Get user details
        $user = DB::table('users')
            ->where('bigid', $smsgLogRecord->userref)
            ->first();

        // Check if userprice was already set correctly by storeMessageIdMapping
        // If already set and > 0, preserve it (it's already the TOTAL including all parts)
        $existingUserPrice = (float)($smsgLogRecord->userprice ?? 0);
        $existingCostPrice = (float)($smsgLogRecord->costprice ?? 0);
        $existingProfit = (float)($smsgLogRecord->profit ?? 0);

        if ($existingUserPrice > 0) {
            // Preserve existing pricing (already calculated as total in storeMessageIdMapping)
            $updateData['userprice'] = $existingUserPrice;
            $updateData['costprice'] = $existingCostPrice;
            $updateData['profit'] = $existingProfit;

            Log::debug("DLR: Preserving existing pricing (already set by storeMessageIdMapping)", [
                'userref' => $smsgLogRecord->userref,
                'mobnum' => $smsgLogRecord->mobnum,
                'userprice' => $existingUserPrice,
                'numparts' => $smsgLogRecord->numparts ?? 1
            ]);
        } elseif ($user) {
            // Calculate pricing for records that don't have it set
            $userRouteService = app(UserRouteService::class);

            // Get route number from smsg_log record or default to 7002
            $routenum = $smsgLogRecord->routenum ?? $smsgLogRecord->requested_route ?? 7002;

            // Get pricing using old system logic (smsg_userroute table)
            $pricing = $userRouteService->getPricingForPhoneNumber(
                $smsgLogRecord->userref,
                $smsgLogRecord->mobnum,
                $routenum,
                7,  // numbits (7-bit text)
                'alpha'  // origtype
            );

            // Multiply by numParts to get TOTAL
            $numParts = isset($smsgLogRecord->numparts) && $smsgLogRecord->numparts > 0
                ? (int)$smsgLogRecord->numparts
                : 1;

            $updateData['userprice'] = round($pricing['userprice'] * $numParts, 4);
            $updateData['costprice'] = round($pricing['costprice'] * $numParts, 4);
            $updateData['profit'] = round($pricing['profit'] * $numParts, 4);

            Log::debug("DLR: Calculating pricing (was not set)", [
                'userref' => $smsgLogRecord->userref,
                'mobnum' => $smsgLogRecord->mobnum,
                'routenum' => $routenum,
                'numparts' => $numParts,
                'countrydialcode' => $countryCode,
                'userprice' => $updateData['userprice'],
                'costprice' => $updateData['costprice'],
                'profit' => $updateData['profit']
            ]);
        }

        return $updateData;
    }

    private function processDlrPushCallback($smsgLogRecord, $deliveryStatus, $updateData)
    {
        try {
            // Get user options for DLR push (retry/daemon settings only) — cached per account (Phase 2).
            $userOptions = app(\App\Services\TableCache::class)->useroption($smsgLogRecord->userref);

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url (resolved at send from the request param or the
            // useroption default), NOT useroption.dreceipt_push_url. Retry/daemon come from useroption.
            $dreceiptUrl = $smsgLogRecord->dreceipt_url ?? '';

            // Check if DLR push is configured
            if (
                !$userOptions ||
                strlen($dreceiptUrl) <= 10 ||
                $userOptions->dreceipt_tries_num <= 0 ||
                intval($userOptions->dreceipt_retries_wait_mins) < 0
            ) {
                return;
            }

            $time = Carbon::now()->format('YmdHis');
            $deliveryReceipt = $updateData['deliveryreceipt2'] ?? $updateData['deliveryreceipt1'] ?? $smsgLogRecord->deliveryreceipt1 ?? '';

            // Create XML for delivery receipt
            $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                <itagg_delivery_receipt>
                    <version>1.1</version>
                    <msisdn>{$smsgLogRecord->mobnum}</msisdn>
                    <submission_ref>{$deliveryReceipt}</submission_ref>
                    <status>{$deliveryStatus}</status>
                    <reason>4</reason>
                    <gmt_timestamp>{$time}</gmt_timestamp>
                    <retry>0</retry>
                </itagg_delivery_receipt>";

            // Insert into delivery receipt push log
            DB::table('delivery_receipt_push_log')->insert([
                'thismsgreference' => $deliveryReceipt,
                'msisdn'           => $smsgLogRecord->mobnum,
                'smsg_log_bigid'   => $smsgLogRecord->bigid,
                'users_bigid'      => $smsgLogRecord->userref,
                'timestamp'        => $time,
                'status'           => 'new',
                'message_status'   => $deliveryStatus,
                'reason'           => '4',
                'url'              => $dreceiptUrl,
                'inserted_time'    => Carbon::now()->format('YmdHis'),
                'retries_left'     => $userOptions->dreceipt_tries_num,
                'wait_minutes'     => $userOptions->dreceipt_retries_wait_mins,
                'dosendtime'       => Carbon::now()->format('Y-m-d H:i:s'),
                'xml'              => $itagg_receipt_xml,
                'dlr_daemon_id'    => $userOptions->dlr_daemon_id ?? 'default',
                'apitype'          => $userOptions->apitype ?? 'w'
            ]);

            Log::info("Inserted into delivery_receipt_push_log", [
                'message_ref' => $deliveryReceipt,
                'url'         => $dreceiptUrl
            ]);
        } catch (Exception $e) {
            Log::error("Failed to process DLR push callback", [
                'error' => $e->getMessage(),
                'smsg_log_bigid' => $smsgLogRecord->bigid ?? 'unknown'
            ]);
        }
    }

    private function showStatistics()
    {
        $runtime = Carbon::now()->diffInSeconds($this->startTime);
        $runtimeFormatted = gmdate('H:i:s', $runtime);

        $this->info("");
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║              DLR STATISTICS                            ║");
        $this->info("╠════════════════════════════════════════════════════════╣");
        $this->info("║ Runtime:           {$runtimeFormatted}                                ║");
        $this->info("║ DLRs Processed:    " . str_pad($this->processedCount, 6, ' ', STR_PAD_LEFT) . "                               ║");
        $this->info("║ Deleted from Queue:" . str_pad($this->deletedCount, 6, ' ', STR_PAD_LEFT) . "                               ║");

        if ($this->failedCount > 0) {
            $this->info("║ DLRs Failed:       " . str_pad($this->failedCount, 6, ' ', STR_PAD_LEFT) . " (but removed from queue)       ║");
        }

        if ($runtime > 0) {
            $rate = round($this->processedCount / $runtime * 60, 2);
            $this->info("║ Processing Rate:   " . str_pad($rate, 6, ' ', STR_PAD_LEFT) . " DLRs/min                       ║");
        }

        if ($this->rabbitMQ) {
            try {
                $stats = $this->rabbitMQ->getQueueStats('sms.dlr');
                $remaining = $stats['messages'] ?? 0;
                $this->info("║ Queue Remaining:   " . str_pad($remaining, 6, ' ', STR_PAD_LEFT) . "                               ║");
            } catch (Exception $e) {
                // Ignore
            }
        }

        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("");
    }

    public function handleShutdown($signal)
    {
        $this->info("");
        $this->warn("╔════════════════════════════════════════════════════════╗");
        $this->warn("║  SHUTDOWN SIGNAL - Graceful shutdown...                ║");
        $this->warn("╚════════════════════════════════════════════════════════╝");
        $this->shouldStop = true;

        if ($this->processingActive) {
            $this->info("Waiting for current batch to complete...");
            sleep(2);
        }
    }

    public function handleTimeout($signal)
    {
        $this->info("");
        $this->warn("╔════════════════════════════════════════════════════════╗");
        $this->warn("║  TIMEOUT REACHED - Shutting down...                    ║");
        $this->warn("╚════════════════════════════════════════════════════════╝");
        $this->shouldStop = true;
    }

    private function cleanup()
    {
        $this->info("");
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║              CLEANUP & SHUTDOWN                        ║");
        $this->info("╚════════════════════════════════════════════════════════╝");

        $this->showStatistics();

        if ($this->smpp) {
            try {
                $this->smpp->disconnect();
                $this->info("✓ SMPP disconnected");
            } catch (Exception $e) {
                Log::warning("Failed to disconnect SMPP: " . $e->getMessage());
            }
        }

        if ($this->rabbitMQ) {
            try {
                $this->rabbitMQ->disconnect();
                $this->info("✓ RabbitMQ disconnected");
            } catch (Exception $e) {
                Log::warning("Failed to disconnect RabbitMQ: " . $e->getMessage());
            }
        }

        $endTime = Carbon::now();
        $totalRuntime = $endTime->diffInSeconds($this->startTime);

        $this->info("");
        $this->info("Session Summary:");
        $this->info("  Total Processed: {$this->processedCount} DLRs");
        $this->info("  Deleted from Queue: {$this->deletedCount} DLRs");
        $this->info("  Duration: " . gmdate('H:i:s', $totalRuntime));

        $this->info("");
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║          DLR RECEIVER STOPPED                          ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
    }
}
