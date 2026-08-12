<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\SMPP\SMPPService;
use App\Services\SMPP\SinchSmppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;
use Carbon\Carbon;

/**
 * Process SMS messages from RabbitMQ queue
 * Handles both Nexmo and Sinch providers based on 'provider' field in message data
 */
class ProcessSmsQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:process-queue
                            {--queue=sms.outbound : Queue name to process}
                            {--provider=both : Which SMPP providers to bind: both|nexmo|sinch. Use nexmo to avoid binding Sinch (Sinch caps at 2 binds/system_id, and the sinch:dlr-receiver already holds one).}
                            {--prefetch=1 : Number of messages to prefetch}
                            {--timeout=0 : Timeout in seconds (0 for infinite)}
                            {--test : Run in test mode without processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process SMS messages from RabbitMQ queue (handles both Nexmo and Sinch)';

    private $rabbitMQ;
    private $smpp;          // Nexmo SMPP
    private $sinchSmpp;     // Sinch SMPP
    private $startTime;
    private $processedCount = 0;
    private $failedCount = 0;
    private $successCount = 0;
    private $shouldStop = false;
    private $dlrProcessedCount = 0;
    private $lastEnquireLink = 0;
    private $lastNexmoEnquireLink = 0;
    private $sinchSocket = null;  // Keep Sinch socket for DLR listening

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $queue = $this->option('queue');
        $prefetch = $this->option('prefetch');
        $timeout = $this->option('timeout');
        $testMode = $this->option('test');
        
        $this->startTime = Carbon::now();
        
        $this->info("Starting SMS Queue Processor");
        $this->info("Queue: {$queue}");
        $this->info("Prefetch: {$prefetch}");
        $this->info("Timeout: " . ($timeout > 0 ? "{$timeout} seconds" : "infinite"));
        
        if ($testMode) {
            $this->warn("Running in TEST MODE - messages will not be sent");
        }
        
        try {
            // First check if RabbitMQ is accessible
            $this->info("Checking RabbitMQ connection...");
            
            try {
                $this->rabbitMQ = new RabbitMQService();
                
                // Check if queue exists and get stats
                $stats = $this->rabbitMQ->getQueueStats($queue);
                
                if (isset($stats['error'])) {
                    $this->error("Queue error: " . $stats['error']);
                    
                    // Try to setup queues
                    $this->warn("Attempting to setup RabbitMQ queues...");
                    $this->call('rabbitmq:setup');
                    
                    // Reinitialize RabbitMQ service
                    $this->rabbitMQ = new RabbitMQService();
                    $stats = $this->rabbitMQ->getQueueStats($queue);
                }
                
                $this->info("Queue '{$queue}' has {$stats['messages']} messages waiting");
                
            } catch (Exception $e) {
                $this->error("RabbitMQ connection failed: " . $e->getMessage());
                $this->error("Please ensure RabbitMQ is running and configured correctly.");
                $this->info("\nTroubleshooting steps:");
                $this->info("1. Check if RabbitMQ is running: sudo systemctl status rabbitmq-server");
                $this->info("2. Setup queues: php artisan rabbitmq:setup");
                $this->info("3. Check credentials in .env file");
                return 1;
            }
            
            // Initialize SMPP services (both Nexmo and Sinch)
            if (!$testMode) {
                $this->info("Connecting to SMPP servers...");

                // Initialize Nexmo SMPP
                try {
                    $this->smpp = new SMPPService();
                    // SMPP_SEND_TRANSMITTER_ONLY=true -> bind the send worker as a
                    // TRANSMITTER (send-only). It then never receives deliver_sm, so
                    // submit_sm_resp is captured cleanly (no DLR-flood starvation) and
                    // throughput matches local. DLRs are owned by the separate
                    // smpp:dlr-receiver (bind_receiver) — REQUIRED to be running when this
                    // is on. Default false keeps the current transceiver behaviour.
                    if (filter_var(env('SMPP_SEND_TRANSMITTER_ONLY', false), FILTER_VALIDATE_BOOLEAN)) {
                        $this->smpp->setBindMode('transmitter');
                        $this->info("Nexmo SMPP bind mode: transmitter (send-only)");
                    }
                    $this->smpp->connect();
                    $this->info("Nexmo SMPP connected successfully");
                } catch (Exception $e) {
                    $this->warn("Nexmo SMPP connection failed: " . $e->getMessage());
                    $this->smpp = null;
                }

                // Initialize Sinch SMPP with persistent connection for DLR — unless this is
                // a Nexmo-only worker. Binding Sinch here collides with sinch:dlr-receiver
                // (Sinch caps at 2 binds/system_id) and floods ESME_RALYBND. Skip it when
                // --provider=nexmo so this worker only handles Vonage/Nexmo.
                $provider = strtolower($this->option('provider') ?: 'both');
                if (in_array($provider, ['both', 'sinch'], true)) {
                    try {
                        $this->sinchSmpp = new SinchSmppService();
                        $this->sinchSmpp->setPersistentMode(true);  // Keep connection alive for DLR

                        // Connect and bind immediately
                        if ($this->sinchSmpp->connect() && $this->sinchSmpp->bind()) {
                            $this->info("Sinch SMPP connected and bound (persistent mode for DLR)");
                            $this->lastEnquireLink = time();
                        } else {
                            $this->warn("Sinch SMPP failed to connect/bind");
                            $this->sinchSmpp = null;
                        }
                    } catch (Exception $e) {
                        $this->warn("Sinch SMPP initialization failed: " . $e->getMessage());
                        $this->sinchSmpp = null;
                    }
                } else {
                    $this->info("Provider=nexmo — skipping Sinch bind (no collision with sinch:dlr-receiver)");
                    $this->sinchSmpp = null;
                }

                if (!$this->smpp && !$this->sinchSmpp) {
                    $this->error("No SMPP services available - messages will be marked as failed");
                }
            } else {
                $this->info("Test mode - skipping SMPP connection");
                $this->smpp = null;
                $this->sinchSmpp = null;
            }
            
            // Register signal handlers for graceful shutdown
            if (extension_loaded('pcntl')) {
                pcntl_async_signals(true);
                pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
                pcntl_signal(SIGINT, [$this, 'handleShutdown']);
                
                // Set timeout alarm if specified
                if ($timeout > 0) {
                    pcntl_alarm($timeout);
                    pcntl_signal(SIGALRM, [$this, 'handleTimeout']);
                }
            }
            
            // Check if there are messages to process
            $stats = $this->rabbitMQ->getQueueStats($queue);
            if ($stats['messages'] == 0) {
                $this->info("No messages in queue. Waiting for messages...");
                $this->info("Press Ctrl+C to stop");
            }
            
            // Start consuming messages
            $this->info("Starting to consume messages from queue...");
            
            // Create a wrapper callback that checks for stop signal
            $callback = function($data) {
                if ($this->shouldStop) {
                    return false; // This will stop consumption
                }
                return $this->processSmsMessage($data);
            };

            // Create idle callback for DLR checking (called every 1 second when no messages)
            $idleCallback = function() {
                $this->checkForDlrs();
            };

            try {
                $this->rabbitMQ->consumeFromQueue($queue, $callback, $prefetch, $idleCallback);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Channel connection is closed') !== false) {
                    $this->error("RabbitMQ channel was closed. This usually means:");
                    $this->error("1. The queue doesn't exist");
                    $this->error("2. RabbitMQ server was restarted");
                    $this->error("3. Network connection was lost");
                    $this->info("\nTry running: php artisan rabbitmq:setup");
                } else {
                    $this->error("Queue consumption error: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            $this->error("Error in SMS Queue Processor: " . $e->getMessage());
            Log::error("SMS Queue Processor Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->cleanup();
        }
        
        return 0;
    }

    /**
     * Process a single SMS message
     */
    public function processSmsMessage($data)
    {
        $this->processedCount++;
        
        // Check if we should stop
        if ($this->shouldStop) {
            return false;
        }

        // Guard: a malformed/empty queue message (json_decode returned null, or a non-array
        // body) must NOT crash the worker — otherwise ONE bad message in the queue kills it
        // and nothing drains. Discard it (ack = remove) and keep going.
        if (!is_array($data)) {
            $this->failedCount++;
            Log::warning("SMS Queue: message body is not a valid array — discarding", [
                'type' => gettype($data),
                'raw'  => is_string($data) ? mb_substr($data, 0, 500) : $data,
            ]);
            return true; // ack -> remove the bad message so the queue keeps moving
        }

        try {
            // Extract message details
            $queueId = $data['queue_id'] ?? null;
            $mobile = $data['mobile_number'] ?? null;
            $message = $data['message'] ?? null;
            $from = $data['sender_id'] ?? null;
            $priority = $data['priority'] ?? 5;
            $metadata = $data['metadata'] ?? [];
            
            // Check if this is a misrouted campaign message (not SMS data)
            // Campaign messages have queue_id starting with 'campaign_' and contain filepath/filename
            if ($queueId && str_starts_with($queueId, 'campaign_') && isset($data['filepath'])) {
                Log::warning("SMS Queue: Detected misrouted campaign message - this should be in campaign.process queue", [
                    'queue_id' => $queueId,
                    'campaign_id' => $data['campaign_id'] ?? null,
                    'filepath' => $data['filepath'] ?? null
                ]);
                
                $this->warn("⚠ Skipping misrouted campaign message: {$queueId} (should be in campaign.process queue)");
                
                // Acknowledge and remove - don't retry as it's in wrong queue
                return true;
            }
            
            // Check for duplicate - prevent sending same message multiple times
            // if ($this->isMessageAlreadySent($data)) {
            //     Log::warning("SMS Queue: Duplicate message detected, skipping", [
            //         'queue_id' => $queueId,
            //         'mobile' => $mobile
            //     ]);
                
            //     $this->warn("⚠ Skipping duplicate message: {$queueId} (already sent)");
                
            //     // Acknowledge and remove duplicate from queue
            //     return true;
            // }
            
            // Validate required fields with detailed logging
            if (empty($mobile) || empty($message)) {
                $missingFields = [];
                if (empty($mobile)) $missingFields[] = 'mobile_number';
                if (empty($message)) $missingFields[] = 'message';
                
                Log::warning("SMS Queue: Missing required fields", [
                    'queue_id' => $queueId,
                    'missing_fields' => $missingFields,
                    'received_data' => array_keys((array) $data),
                    'mobile_value' => $mobile,
                    'message_length' => $message ? strlen($message) : 0,
                    'full_data' => $data
                ]);
                
                // Mark as failed and acknowledge to remove from queue
                if ($queueId) {
                    $this->markMessageAsFailed($queueId, 'Missing required fields: ' . implode(', ', $missingFields));
                }
                
                $this->failedCount++;
                $this->error("✗ Failed to process SMS: Missing required fields: " . implode(', ', $missingFields) . " (Queue ID: {$queueId})");
                
                // Return true to acknowledge and remove malformed message from queue
                return true;
            }
            
            // Determine provider from data (default to nexmo)
            $provider = $data['provider'] ?? ($metadata['provider'] ?? 'nexmo');
            $referenceId = $data['reference_id'] ?? ($metadata['bigid'] ?? null);
            // Exact smsg_log row id — REQUIRED so the message_id / deliveryreceipt1 update
            // targets THIS row. Without it the update falls back to bigid+mobnum, which is
            // NOT unique when the same number appears twice in one batch: both rows then get
            // the SAME message_id and only the first gets its DLR (the rest stay 'pending').
            $smsgLogId = $metadata['smsg_log_id'] ?? null;

            $this->info("Processing SMS: Queue ID: {$queueId}, To: {$mobile}, Provider: {$provider}");

            // Test mode - don't actually send
            if ($this->option('test')) {
                $this->info("TEST MODE: Would send SMS to {$mobile} via {$provider}");
                $this->successCount++;
                return true;
            }

            // Check if appropriate SMPP is available
            if ($provider === 'sinch') {
                if (!$this->sinchSmpp) {
                    throw new Exception("Sinch SMPP not available");
                }
            } else {
                if (!$this->smpp) {
                    throw new Exception("Nexmo SMPP not connected");
                }
            }

            // Mark as processing to prevent duplicate processing
            if ($queueId) {
                $this->markMessageAsProcessing($queueId);
            }

            // OLD SYSTEM parity (smsg_2send_body.inc:2399): mark the smsg_log row 'firing' while it
            // is actively being sent, instead of leaving it sitting 'pending'. It flips to 'ok' on
            // success (in the SMPP service) or to 'fail' below if the SMPP submit fails.
            $this->updateSmsgLogSendStatus($referenceId, $smsgLogId, 'firing', null, $mobile);

            // Send SMS via appropriate SMPP provider
            if ($provider === 'sinch') {
                $result = $this->sinchSmpp->sendSMS(
                    $mobile,
                    $message,
                    $from,
                    $priority,
                    $queueId,
                    $metadata['initiator'] ?? 'QueueProcessor',
                    $referenceId,
                    null, // scheduleDeliveryTime
                    $smsgLogId // exact smsg_log row -> unique message_id per row (dup-number safe)
                );
            } else {
                $result = $this->smpp->sendSMS(
                    $mobile,
                    $message,
                    $from,
                    $priority,
                    $queueId,
                    $metadata['initiator'] ?? 'QueueProcessor',
                    $referenceId,
                    null, // scheduleDeliveryTime
                    $smsgLogId // exact smsg_log row -> unique message_id per row (dup-number safe)
                );
            }

            if ($result['success']) {
                $this->successCount++;

                // Update database
                if ($queueId) {
                    $this->markMessageAsSent($queueId, $result);
                }

                $this->info("✓ SMS sent successfully via {$provider}: {$mobile}");

                // Check for DLRs after sending (especially for Sinch)
                $this->checkForDlrs();

                // Return true to acknowledge message and remove from queue
                return true;
            } else {
                // OLD SYSTEM parity (smsg_2send_body.inc:2526): the SMPP submit FAILED, so mark the
                // smsg_log row 'fail' with the error + timesent. Previously this was only logged, so
                // a failed send stayed stuck 'pending'/'firing' forever — invisible to reporting AND
                // to the nightly requeue. Writing 'fail' here is what makes failed sends findable.
                $this->updateSmsgLogSendStatus($referenceId, $smsgLogId, 'fail', $result['error'] ?? 'SMPP send failed', $mobile);
                throw new Exception("Failed to send SMS via {$provider}: " . ($result['error'] ?? 'Unknown error'));
            }
            
        } catch (Exception $e) {
            $this->failedCount++;
            
            $this->error("✗ Failed to process SMS: " . $e->getMessage());
            
            Log::error("SMS Processing Failed", [
                'queue_id' => $data['queue_id'] ?? null,
                'mobile' => $data['mobile_number'] ?? null,
                'error' => $e->getMessage(),
                'retry_count' => $data['retry_count'] ?? 0
            ]);
            
            // Check if it's a permanent failure
            if ($this->isPermanentFailure($e)) {
                // Mark as permanently failed
                if (isset($data['queue_id'])) {
                    $this->markMessageAsFailed($data['queue_id'], $e->getMessage());
                }
                return true; // Acknowledge to remove from queue
            }
            
            // Return false to trigger retry logic in RabbitMQService
            return false;
        }
    }

    /**
     * Check if message has already been sent
     * NOTE: sms_queue table removed - checking smsg_log only
     */
    private function isMessageAlreadySent($data)
    {
        if (empty($data['queue_id']) && empty($data['reference_id'])) {
            return false;
        }

        try {
            // Check smsg_log for duplicate prevention using bigid from metadata
            $bigId = $data['metadata']['bigid'] ?? $data['metadata']['smsg_log_id'] ?? $data['reference_id'] ?? null;

            if ($bigId) {
                $smsgLog = DB::table('smsg_log')
                    ->where('bigid', $bigId)
                    ->first();

                if ($smsgLog && $smsgLog->sentstatus === 'ok') {
                    Log::info("Duplicate check: Found in smsg_log with sentstatus ok", [
                        'queue_id' => $data['queue_id'] ?? null,
                        'bigid' => $bigId
                    ]);
                    return true;
                }
            }

            // For campaign messages, check if this specific mobile+campaign combo was already sent
            $campaignId = $data['metadata']['campaign_id'] ?? null;
            $mobileNumber = $data['mobile_number'] ?? null;

            if ($campaignId && $mobileNumber) {
                $existingSent = DB::table('smsg_log')
                    ->where('campaignref', $campaignId)
                    ->where('mobnum', $mobileNumber)
                    ->where('sentstatus', 'ok')
                    ->exists();

                if ($existingSent) {
                    Log::info("Duplicate check: Campaign SMS already sent to this number", [
                        'queue_id' => $data['queue_id'] ?? null,
                        'campaign_id' => $campaignId,
                        'mobile' => $mobileNumber
                    ]);
                    return true;
                }
            }

        } catch (Exception $e) {
            Log::warning("Failed to check message status: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Mark message as processing
     * NOTE: sms_queue table removed - using smsg_log instead
     */
    private function markMessageAsProcessing($queueId)
    {
        // No longer using sms_queue - smsg_log is updated directly by SMPP services
        Log::debug("Processing message", ['queue_id' => $queueId]);
    }

    /**
     * Mark message as sent
     * NOTE: sms_queue table removed - smsg_log is updated by SMPP services
     */
    private function markMessageAsSent($queueId, $result)
    {
        // smsg_log is updated directly by SMPP services with wallet deduction
        Log::info("Message sent successfully", [
            'queue_id' => $queueId,
            'message_id' => $result['message_id'] ?? null,
            'host' => $result['host'] ?? null
        ]);
    }

    /**
     * Mark message as failed
     * NOTE: sms_queue table removed - using smsg_log instead
     */
    private function markMessageAsFailed($queueId, $error)
    {
        // smsg_log is updated directly by SMPP services
        Log::error("Message failed", [
            'queue_id' => $queueId,
            'error' => $error
        ]);
    }

    /**
     * Update the smsg_log row's sentstatus during the send lifecycle — OLD SYSTEM parity.
     *
     * OLD's send daemon transitions each row 'no' -> 'firing' (processing) -> 'ok' | 'fail'
     * (smsg_2send_body.inc:2399 / 1924 / 2526). The NEW queue consumer previously wrote only the
     * 'ok' state (via the SMPP service); a send that FAILED at the SMPP layer was merely logged, so
     * the row stayed stuck 'pending' forever — invisible to reporting and impossible to requeue.
     * This writes the 'firing' and 'fail' states so the row always reflects the real outcome.
     *
     * Matches by the exact smsg_log id when available (unique — dup-number safe), else by bigid
     * (+ mobnum). NEVER overwrites a row that already sent successfully ('ok').
     */
    private function updateSmsgLogSendStatus($referenceId, $smsgLogId, string $status, ?string $errorText = null, ?string $mobile = null): void
    {
        try {
            $query = DB::table('smsg_log');

            if (!empty($smsgLogId)) {
                $query->where('id', $smsgLogId);
            } elseif (!empty($referenceId)) {
                $query->where('bigid', $referenceId);
                if (!empty($mobile)) {
                    $query->where('mobnum', ltrim((string) $mobile, '+'));
                }
            } else {
                return; // no reliable key to match on — skip
            }

            $update = ['sentstatus' => $status];
            if ($status === 'fail') {
                // Record the reason + send time, exactly like OLD's fail UPDATE.
                $update['sentstatustext'] = $errorText ? substr($errorText, 0, 250) : 'SMPP send failed';
                $update['timesent'] = Carbon::now('Europe/London')->format('YmdHis');
            }

            // Never move a row that already sent successfully.
            $query->where('sentstatus', '<>', 'ok')->update($update);
        } catch (\Throwable $e) {
            Log::warning('updateSmsgLogSendStatus failed', [
                'status'      => $status,
                'smsg_log_id' => $smsgLogId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check for incoming DLRs from Sinch SMPP
     * Also sends enquire_link to keep connection alive
     */
    private function checkForDlrs(): void
    {
        // Check Nexmo/Vonage SMPP for DLRs over the SAME bound session (transceiver mode).
        // Vonage delivers each message's deliver_sm (DLR) back to the session that submitted
        // it, so as long as this worker is bound as a TRANSCEIVER (SMPP_SEND_TRANSMITTER_ONLY
        // =false) we receive DLRs natively over SMPP — no HTTP poll, no webhook. handleDeliverSm
        // -> processDlr updates smsg_log.deliverystatus2 directly using the receipted_message_id
        // TLV (== deliveryreceipt1). Runs only when NOT transmitter-only (a transmitter bind
        // never gets deliver_sm, so DLRs there still need the HTTP path).
        if ($this->smpp && !filter_var(env('SMPP_SEND_TRANSMITTER_ONLY', false), FILTER_VALIDATE_BOOLEAN)) {
            try {
                // Drain any incoming deliver_sm (DLR / MO) PDUs and process them inline.
                $nexmoDlrs = $this->smpp->processIncomingPdus();
                if ($nexmoDlrs > 0) {
                    $this->dlrProcessedCount += $nexmoDlrs;
                    $this->info("  → Processed {$nexmoDlrs} DLR/MO PDU(s) from Nexmo (SMPP)");
                }

                // Keep the Vonage session alive so DLRs keep flowing. On failure, reconnect
                // (connect() also re-binds), so a dropped session self-heals.
                if (time() - $this->lastNexmoEnquireLink >= 30) {
                    if ($this->smpp->enquireLink()) {
                        $this->lastNexmoEnquireLink = time();
                    } else {
                        $this->warn("Nexmo SMPP enquire_link failed, attempting reconnect...");
                        try {
                            $this->smpp->connect();
                            $this->lastNexmoEnquireLink = time();
                            $this->info("Nexmo SMPP reconnected");
                        } catch (Exception $re) {
                            $this->warn("Nexmo SMPP reconnect failed: " . $re->getMessage());
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning("Nexmo DLR check failed: " . $e->getMessage());
                $this->warn("Nexmo DLR check error: " . $e->getMessage());
            }
        }

        // Check Sinch SMPP for DLRs
        if ($this->sinchSmpp) {
            try {
                // Ensure connection is still alive
                if (!$this->sinchSmpp->isStillConnected()) {
                    $this->warn("Sinch SMPP connection lost, reconnecting...");
                    if ($this->sinchSmpp->ensureConnected()) {
                        $this->info("Sinch SMPP reconnected successfully");
                        $this->lastEnquireLink = time();
                    } else {
                        $this->error("Sinch SMPP reconnection failed");
                        return;
                    }
                }

                // Check for DLRs (with 500ms timeout to catch incoming PDUs)
                $dlrCount = $this->sinchSmpp->checkForDlr(1);

                if ($dlrCount > 0) {
                    $this->dlrProcessedCount += $dlrCount;
                    $this->info("  → Processed {$dlrCount} DLR(s) from Sinch");
                }

                // Send enquire_link every 30 seconds to keep connection alive
                if (time() - $this->lastEnquireLink >= 30) {
                    $this->line("Sending Sinch SMPP enquire_link...");
                    if ($this->sinchSmpp->sendEnquireLink()) {
                        $this->lastEnquireLink = time();
                        $this->line("Sinch SMPP connection alive");
                    } else {
                        // Connection may have dropped, try to reconnect
                        $this->warn("Sinch SMPP enquire_link failed, attempting reconnect...");
                        if ($this->sinchSmpp->ensureConnected()) {
                            $this->info("Sinch SMPP reconnected");
                            $this->lastEnquireLink = time();
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning("DLR check failed: " . $e->getMessage());
                $this->warn("DLR check error: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if error is permanent (should not retry)
     */
    private function isPermanentFailure(Exception $e)
    {
        $permanentErrors = [
            'Invalid Source Address',
            'Invalid Destination Address',
            'Invalid phone number',
            'Blacklisted number',
            'Invalid message content',
            'Account suspended',
            'Insufficient credits',
            'Missing required fields',
            'mobile_number or message'
        ];
        
        foreach ($permanentErrors as $error) {
            if (stripos($e->getMessage(), $error) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle graceful shutdown
     */
    public function handleShutdown($signal)
    {
        $this->info("\nReceived shutdown signal. Cleaning up...");
        $this->shouldStop = true;
        
        // Give time for current message to finish
        sleep(2);
        
        $this->cleanup();
        exit(0);
    }

    /**
     * Handle timeout
     */
    public function handleTimeout($signal)
    {
        $this->info("\nTimeout reached. Shutting down...");
        $this->shouldStop = true;
        
        // Give time for current message to finish
        sleep(2);
        
        $this->cleanup();
        exit(0);
    }

    /**
     * Cleanup resources
     */
    private function cleanup()
    {
        if (!$this->startTime) {
            return;
        }
        
        $runtime = Carbon::now()->diffInSeconds($this->startTime);
        
        $this->info("\n=== Queue Processor Statistics ===");
        $this->info("Runtime: {$runtime} seconds");
        $this->info("Processed: {$this->processedCount} messages");
        $this->info("Successful: {$this->successCount} messages");
        $this->info("Failed: {$this->failedCount} messages");
        $this->info("DLRs Processed: {$this->dlrProcessedCount}");
        
        if ($this->processedCount > 0) {
            $successRate = round(($this->successCount / $this->processedCount) * 100, 2);
            $this->info("Success Rate: {$successRate}%");
            
            if ($runtime > 0) {
                $throughput = round($this->processedCount / $runtime, 2);
                $this->info("Throughput: {$throughput} messages/second");
            }
        }
        
        // Disconnect services
        if (isset($this->smpp) && $this->smpp) {
            try {
                $this->smpp->disconnect();
                $this->info("Nexmo SMPP disconnected");
            } catch (Exception $e) {
                Log::warning("Failed to disconnect Nexmo SMPP: " . $e->getMessage());
            }
        }

        if (isset($this->sinchSmpp) && $this->sinchSmpp) {
            try {
                // Disable persistent mode before disconnecting
                $this->sinchSmpp->setPersistentMode(false);
                $this->sinchSmpp->disconnect();
                $this->info("Sinch SMPP disconnected");
            } catch (Exception $e) {
                Log::warning("Failed to disconnect Sinch SMPP: " . $e->getMessage());
            }
        }

        if (isset($this->rabbitMQ)) {
            try {
                $this->rabbitMQ->disconnect();
                $this->info("RabbitMQ disconnected");
            } catch (Exception $e) {
                Log::warning("Failed to disconnect RabbitMQ: " . $e->getMessage());
            }
        }
    }
}
