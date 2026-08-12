<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SMPPService;
use App\Services\Queue\RabbitMQService;
use App\Services\DeliveryStatusService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;

class SmppInboundReceiver extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:inbound-receiver 
                            {--timeout=0 : Timeout in seconds (0 for infinite)}
                            {--reconnect-delay=5 : Delay in seconds before reconnecting}
                            {--max-reconnect-attempts=0 : Maximum reconnection attempts (0 for unlimited)}
                            {--enquire-interval=30 : Enquire link interval in seconds}
                            {--enquire-max-failures=3 : Max enquire link failures before reconnect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously receive inbound SMS (MO) messages from SMPP and push to RabbitMQ';

    private $smppService;
    private $rabbitMQ;
    private $isRunning = true;
    private $lastActivity;
    private $lastEnquireLink;
    private $reconnectDelay;
    private $timeout;
    private $enquireInterval;
    private $processedCount = 0;
    private $reconnectAttempts = 0;
    private $maxReconnectAttempts;
    private $consecutiveErrors = 0;
    private $maxConsecutiveErrors = 10;
    private $enquireLinkFailures = 0;
    private $maxEnquireLinkFailures;
    private $inboundCount = 0;
    private $dlrCount = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->timeout = (int)$this->option('timeout');
        $this->reconnectDelay = (int)$this->option('reconnect-delay');
        $this->maxReconnectAttempts = (int)$this->option('max-reconnect-attempts');
        $this->enquireInterval = (int)$this->option('enquire-interval');
        $this->maxEnquireLinkFailures = (int)$this->option('enquire-max-failures');
        
        $this->info("Starting SMPP Inbound Receiver...");
        $this->info("Timeout: " . ($this->timeout > 0 ? "{$this->timeout} seconds" : "Infinite (Continuous)"));
        $this->info("Reconnect delay: {$this->reconnectDelay} seconds");
        $this->info("Enquire link interval: {$this->enquireInterval} seconds");
        $this->info("Enquire link max failures: {$this->maxEnquireLinkFailures}");
        $this->info("Max reconnect attempts: " . ($this->maxReconnectAttempts > 0 ? $this->maxReconnectAttempts : "Unlimited"));
        $this->info(str_repeat("=", 60));
        
        // Initialize RabbitMQ (optional - will fall back to direct database storage if unavailable)
        try {
            $this->rabbitMQ = new RabbitMQService();
            $this->info("RabbitMQ connected successfully");
        } catch (Exception $e) {
            $this->warn("RabbitMQ connection failed: " . $e->getMessage());
            $this->warn("Will store messages directly to database instead of queuing");
            $this->rabbitMQ = null;
        }
        
        // Set up signal handlers for graceful shutdown
        $this->setupSignalHandlers();
        
        $startTime = time();
        
        // Main loop - runs continuously
        while ($this->isRunning) {
            try {
                // Check global timeout (if set)
                if ($this->timeout > 0 && (time() - $startTime) > $this->timeout) {
                    $this->info("Global timeout reached. Shutting down...");
                    break;
                }
                
                // Check if we've exceeded max consecutive errors
                if ($this->consecutiveErrors >= $this->maxConsecutiveErrors) {
                    $this->error("Max consecutive errors ({$this->maxConsecutiveErrors}) reached. Shutting down...");
                    break;
                }
                
                // Ensure connection is established
                if (!$this->ensureConnected()) {
                    sleep($this->reconnectDelay);
                    continue;
                }
                
                // Reset consecutive errors on successful connection
                $this->consecutiveErrors = 0;
                
                // Process incoming PDUs (DELIVER_SM)
                $this->processIncomingPdus();
                
                // Maintain connection with enquire_link
                $this->maintainConnection();
                
                // Small delay to prevent CPU spinning
                usleep(100000); // 100ms
                
                // Process signals if available
                $this->dispatchSignals();
                
            } catch (Exception $e) {
                $this->consecutiveErrors++;
                $this->handleError($e);
            }
        }
        
        // Clean shutdown
        $this->disconnect();
        $this->info(str_repeat("=", 60));
        $this->info("Inbound Receiver stopped gracefully.");
        $this->info("Total messages processed: {$this->processedCount}");
        $this->info("Inbound SMS (MO): {$this->inboundCount}");
        $this->info("Delivery Receipts: {$this->dlrCount}");
        $this->info("Total reconnection attempts: {$this->reconnectAttempts}");
        
        return 0;
    }
    
    /**
     * Setup signal handlers for graceful shutdown
     */
    private function setupSignalHandlers()
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGHUP, [$this, 'shutdown']);
            $this->info("Signal handlers registered (SIGTERM, SIGINT, SIGHUP)");
        } else {
            $this->warn("PCNTL extension not loaded. Signal handling disabled.");
        }
    }
    
    /**
     * Dispatch pending signals
     */
    private function dispatchSignals()
    {
        if (extension_loaded('pcntl')) {
            pcntl_signal_dispatch();
        }
    }
    
    /**
     * Ensure SMPP connection is established
     */
    private function ensureConnected()
    {
        if ($this->smppService && $this->isConnected()) {
            return true;
        }
        
        // Check max reconnect attempts
        if ($this->maxReconnectAttempts > 0 && $this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->error("Maximum reconnection attempts reached ({$this->maxReconnectAttempts})");
            $this->isRunning = false;
            return false;
        }
        
        return $this->connect();
    }
    
    /**
     * Connect to SMPP server
     */
    private function connect()
    {
        try {
            $this->reconnectAttempts++;
            $this->info("Connecting to SMPP server (Attempt #{$this->reconnectAttempts})...");
            
            // Clean up existing connection if any
            if ($this->smppService) {
                try {
                    $this->smppService->disconnect();
                } catch (Exception $e) {
                    // Ignore disconnect errors
                }
                $this->smppService = null;
            }
            
            $this->smppService = new SMPPService(
                env('SMPP_HOST', 'smpp1.nexmo.com'),
                env('SMPP_PORT', 8000)
            );
            
            $this->smppService->connect();
            $this->lastActivity = Carbon::now();
            $this->lastEnquireLink = Carbon::now();
            $this->enquireLinkFailures = 0;
            
            $this->info("✓ Connected and bound successfully!");
            $this->info("Listening for inbound SMS messages...");
            
            return true;
            
        } catch (Exception $e) {
            $this->error("✗ Failed to connect: " . $e->getMessage());
            SmppLogger::vonage()->error("SMPP Inbound Connection Error", [
                'attempt' => $this->reconnectAttempts,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->smppService = null;
            return false;
        }
    }
    
    /**
     * Check if SMPP is connected
     */
    private function isConnected()
    {
        if (!$this->smppService) {
            return false;
        }
        
        try {
            $stats = $this->smppService->getStatistics();
            return isset($stats['connected']) && isset($stats['bound']) 
                   && $stats['connected'] && $stats['bound'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Process incoming PDUs (DELIVER_SM)
     */
    private function processIncomingPdus()
    {
        if (!$this->smppService) {
            return;
        }
        
        try {
            // Read incoming PDUs from SMPP connection
            $pdus = $this->smppService->readIncomingMessages();
            
            if (empty($pdus)) {
                return;
            }
            
            foreach ($pdus as $pdu) {
                $this->processedCount++;
                $this->lastActivity = Carbon::now();
                
                // Log raw PDU for debugging
                SmppLogger::vonage()->debug("Raw PDU received", [
                    'pdu' => $pdu,
                    'esm_class' => isset($pdu['esm_class']) ? sprintf('0x%02X', $pdu['esm_class']) : 'N/A',
                    'short_message' => $pdu['short_message'] ?? 'N/A'
                ]);
                
                // Determine message type
                if ($this->isDeliveryReceipt($pdu)) {
                    $this->dlrCount++;
                    $this->pushToQueue('sms.dlr', $pdu);
                    $this->info("✓ DLR received and queued | Total DLRs: {$this->dlrCount}");
                    SmppLogger::vonage()->info("DLR Processed", [
                        'from' => $pdu['source_addr'] ?? 'unknown',
                        'message' => substr($pdu['short_message'] ?? '', 0, 100)
                    ]);
                } else {
                    $this->inboundCount++;
                    $this->pushToQueue('sms.inbound', $pdu);
                    $this->info("✓ Inbound SMS received from: {$pdu['source_addr']} | Total MO: {$this->inboundCount}");
                    SmppLogger::vonage()->info("Inbound SMS Processed", [
                        'from' => $pdu['source_addr'] ?? 'unknown',
                        'to' => $pdu['destination_addr'] ?? 'unknown',
                        'message' => $pdu['short_message'] ?? 'N/A'
                    ]);
                }
                
                // Note: DELIVER_SM_RESP is already sent by readIncomingMessages()
            }
            
            // Reset enquire link failures on successful PDU processing
            if ($this->enquireLinkFailures > 0) {
                $this->enquireLinkFailures = 0;
            }
            
        } catch (Exception $e) {
            throw new Exception("Failed to process incoming PDUs: " . $e->getMessage());
        }
    }
    
    /**
     * Check if PDU is a delivery receipt
     */
    private function isDeliveryReceipt($pdu)
    {
        // Check ESM class (bit 2 set indicates DLR)
        if (isset($pdu['esm_class']) && ($pdu['esm_class'] & 0x04) === 0x04) {
            return true;
        }
        
        // Check message content for DLR format
        $message = $pdu['short_message'] ?? '';
        if (preg_match('/id:|stat:|err:|text:/i', $message)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Push message to RabbitMQ queue or process directly if RabbitMQ unavailable
     */
    private function pushToQueue($queueName, $pdu)
    {
        try {
            // Prepare message data
            $messageData = [
                'message_id' => $pdu['message_id'] ?? uniqid('MO-'),
                'source_addr' => $pdu['source_addr'] ?? null,
                'destination_addr' => $pdu['destination_addr'] ?? null,
                'sender_number' => $pdu['source_addr'] ?? null,
                'receiver_number' => $pdu['destination_addr'] ?? null,
                'short_message' => $pdu['short_message'] ?? null,
                'message' => $pdu['short_message'] ?? null,
                'data_coding' => $pdu['data_coding'] ?? 0,
                'esm_class' => $pdu['esm_class'] ?? 0,
                'protocol_id' => $pdu['protocol_id'] ?? 0,
                'priority_flag' => $pdu['priority_flag'] ?? 0,
                'registered_delivery' => $pdu['registered_delivery'] ?? 0,
                'service_type' => $pdu['service_type'] ?? null,
                'source_addr_ton' => $pdu['source_addr_ton'] ?? 0,
                'source_addr_npi' => $pdu['source_addr_npi'] ?? 0,
                'dest_addr_ton' => $pdu['dest_addr_ton'] ?? 0,
                'dest_addr_npi' => $pdu['dest_addr_npi'] ?? 0,
                'smsc_message_id' => $pdu['smsc_message_id'] ?? null,
                'received_at' => Carbon::now()->toDateTimeString(),
                'raw_pdu' => $pdu
            ];

            // If RabbitMQ is available, publish to queue
            if ($this->rabbitMQ) {
                $this->rabbitMQ->publishToQueue($queueName, $messageData, 5);

                SmppLogger::vonage()->info("Message pushed to queue", [
                    'queue' => $queueName,
                    'from' => $pdu['source_addr'] ?? 'unknown',
                    'to' => $pdu['destination_addr'] ?? 'unknown'
                ]);
            } else {
                // RabbitMQ unavailable - process directly
                $this->processDirectly($queueName, $messageData);
            }

        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to push to queue, attempting direct processing", [
                'queue' => $queueName,
                'error' => $e->getMessage()
            ]);

            // Fallback to direct processing if RabbitMQ fails
            try {
                $this->processDirectly($queueName, $messageData ?? $pdu);
            } catch (Exception $fallbackError) {
                SmppLogger::vonage()->error("Direct processing also failed", [
                    'error' => $fallbackError->getMessage()
                ]);
                throw $fallbackError;
            }
        }
    }

    /**
     * Process message directly without RabbitMQ (fallback)
     */
    private function processDirectly($queueName, $messageData)
    {
        if ($queueName === 'sms.dlr') {
            // Process DLR directly using DeliveryStatusService
            $deliveryStatusService = app(DeliveryStatusService::class);

            $dlrPayload = [
                'message_id' => $messageData['message_id'] ?? '',
                'mobile_number' => $messageData['source_addr'] ?? '',
                'status' => $this->extractDlrStatus($messageData['short_message'] ?? ''),
                'error_code' => $this->extractDlrErrorCode($messageData['short_message'] ?? ''),
                'done_date' => Carbon::now()->format('YmdHis'),
                'provider' => 'nexmo',
                'raw_data' => $messageData,
            ];

            $result = $deliveryStatusService->processDeliveryReceipt($dlrPayload);

            SmppLogger::vonage()->info("DLR processed directly (RabbitMQ unavailable)", [
                'message_id' => $dlrPayload['message_id'],
                'status' => $dlrPayload['status'],
                'result' => $result
            ]);
        } else {
            // Store inbound SMS directly to database
            $this->storeInboundSmsDirectly($messageData);
        }
    }

    /**
     * Store inbound SMS directly to itagg_incominglog table (OLD SYSTEM format)
     */
    private function storeInboundSmsDirectly($data)
    {
        $cleanFrom = preg_replace('/[^0-9]/', '', $data['source_addr'] ?? '');
        $cleanTo = preg_replace('/[^0-9]/', '', $data['destination_addr'] ?? '');
        $message = $data['short_message'] ?? '';
        $operatorMessageId = $data['message_id'] ?? '';

        // Find shortcode owner from smsshortcodes table
        $shortcode = DB::table('smsshortcodes')
            ->where('number', $cleanTo)
            ->orWhere('number', ltrim($cleanTo, '44'))
            ->first();

        $userBigid = $shortcode->bigid ?? '';

        // Parse keyword and subkeyword from message
        $keyword = '';
        $subkeyword = '';
        $messageParts = explode(' ', trim($message), 3);
        if (count($messageParts) >= 1) {
            $keyword = strtoupper($messageParts[0]);
        }
        if (count($messageParts) >= 2) {
            $subkeyword = strtoupper($messageParts[1]);
        }

        // Build data for itagg_incominglog (OLD SYSTEM format)
        $insertData = [
            'recieved' => Carbon::now(),
            'source' => $cleanFrom,
            'dest' => $cleanTo,
            'keyword' => $keyword,
            'subkeyword' => $subkeyword,
            'msg' => urlencode($message),
            'network' => '',
            'matched' => 0,
            'user_bigid' => $userBigid,
            'mobile_client_bigid' => '',
            'mobile_client_type' => '',
            'mobile_client_version' => '',
            'viewed_by_java_desktop' => 0,
            'msisdnAlias' => '',
            'mbloxDeliverer' => 'nexmo_smpp',
        ];

        // Add operator_message_id if available
        if (!empty($operatorMessageId)) {
            $insertData['operator_message_id'] = $operatorMessageId;
        }

        // Insert into itagg_incominglog
        $id = DB::table('itagg_incominglog')->insertGetId($insertData);

        SmppLogger::vonage()->info("Inbound SMS stored directly to itagg_incominglog (RabbitMQ unavailable)", [
            'id' => $id,
            'from' => $cleanFrom,
            'to' => $cleanTo,
            'user_bigid' => $userBigid,
            'keyword' => $keyword
        ]);
    }

    /**
     * Extract DLR status from message content
     */
    private function extractDlrStatus($message)
    {
        if (preg_match('/stat:([A-Z]+)/i', $message, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'UNKNOWN';
    }

    /**
     * Extract DLR error code from message content
     */
    private function extractDlrErrorCode($message)
    {
        if (preg_match('/err:(\d+)/i', $message, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
    
    /**
     * Maintain connection with enquire_link
     */
    private function maintainConnection()
    {
        if (!$this->smppService) {
            return;
        }
        
        $now = Carbon::now();
        
        // Send enquire_link if interval has passed
        if (!$this->lastEnquireLink || $this->lastEnquireLink->diffInSeconds($now) >= $this->enquireInterval) {
            try {
                if ($this->smppService->enquireLink()) {
                    $this->lastEnquireLink = $now;
                    $this->lastActivity = $now;
                    
                    // Reset failure counter on success
                    if ($this->enquireLinkFailures > 0) {
                        $this->info("→ Enquire link sent (recovered after {$this->enquireLinkFailures} failures)");
                        $this->enquireLinkFailures = 0;
                    } else {
                        $this->line("→ Enquire link sent");
                    }
                } else {
                    $this->handleEnquireLinkFailure();
                }
            } catch (Exception $e) {
                $this->handleEnquireLinkFailure($e);
            }
        }
    }
    
    /**
     * Handle enquire link failure
     */
    private function handleEnquireLinkFailure(Exception $e = null)
    {
        $this->enquireLinkFailures++;
        
        $errorMsg = $e ? $e->getMessage() : "No response";
        
        if ($this->enquireLinkFailures < $this->maxEnquireLinkFailures) {
            $this->warn("✗ Enquire link failed ({$this->enquireLinkFailures}/{$this->maxEnquireLinkFailures}): {$errorMsg}");
            $this->lastEnquireLink = Carbon::now();
            
            SmppLogger::vonage()->warning("SMPP Inbound Enquire Link Failed", [
                'failure_count' => $this->enquireLinkFailures,
                'max_failures' => $this->maxEnquireLinkFailures,
                'error' => $errorMsg
            ]);
        } else {
            $this->error("✗ Enquire link failed {$this->enquireLinkFailures} times - forcing reconnection");
            
            SmppLogger::vonage()->error("SMPP Inbound Enquire Link Max Failures", [
                'failure_count' => $this->enquireLinkFailures,
                'error' => $errorMsg
            ]);
            
            throw new Exception("Enquire link failed {$this->enquireLinkFailures} times");
        }
    }
    
    /**
     * Handle errors
     */
    private function handleError(Exception $e)
    {
        $this->error("Error: " . $e->getMessage());
        SmppLogger::vonage()->error("SMPP Inbound Receiver Error", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'consecutive_errors' => $this->consecutiveErrors,
            'processed_count' => $this->processedCount,
            'inbound_count' => $this->inboundCount,
            'dlr_count' => $this->dlrCount
        ]);
        
        $this->disconnect();
        
        if ($this->isRunning) {
            $delay = min($this->reconnectDelay * min($this->consecutiveErrors, 5), 60);
            $this->warn("Waiting {$delay} seconds before reconnecting...");
            sleep($delay);
        }
    }
    
    /**
     * Disconnect from SMPP server
     */
    private function disconnect()
    {
        try {
            if ($this->smppService) {
                $this->line("Disconnecting from SMPP server...");
                $this->smppService->disconnect();
                $this->smppService = null;
            }
        } catch (Exception $e) {
            $this->warn("Error during disconnect: " . $e->getMessage());
            $this->smppService = null;
        }
    }
    
    /**
     * Handle shutdown signal
     */
    public function shutdown($signal = null)
    {
        $signalName = $this->getSignalName($signal);
        $this->info("\n" . str_repeat("=", 60));
        $this->info("Shutdown signal received ({$signalName}). Stopping gracefully...");
        $this->info(str_repeat("=", 60));
        $this->isRunning = false;
    }
    
    /**
     * Get signal name
     */
    private function getSignalName($signal)
    {
        if (!$signal) {
            return 'MANUAL';
        }
        
        $signals = [
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGHUP => 'SIGHUP',
        ];
        
        return $signals[$signal] ?? "UNKNOWN({$signal})";
    }
}
