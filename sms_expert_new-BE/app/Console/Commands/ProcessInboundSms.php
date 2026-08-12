<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class ProcessInboundSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:process-inbound 
                            {--queue=sms.inbound : Queue name to process}
                            {--prefetch=1 : Number of messages to prefetch}
                            {--timeout=0 : Timeout in seconds (0 for infinite)}
                            {--test : Run in test mode without processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process inbound SMS messages from RabbitMQ queue';

    private $rabbitMQ;
    private $startTime;
    private $processedCount = 0;
    private $failedCount = 0;
    private $successCount = 0;
    private $shouldStop = false;

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
        
        $this->info("Starting Inbound SMS Processor");
        $this->info("Queue: {$queue}");
        $this->info("Prefetch: {$prefetch}");
        $this->info("Timeout: " . ($timeout > 0 ? "{$timeout} seconds" : "infinite"));
        
        if ($testMode) {
            $this->warn("Running in TEST MODE - messages will not be processed");
        }
        
        try {
            // Initialize RabbitMQ
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
                $this->info("1. Check if RabbitMQ is running");
                $this->info("2. Setup queues: php artisan rabbitmq:setup");
                $this->info("3. Check credentials in .env file");
                return 1;
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
            $this->info("Starting to consume inbound SMS messages from queue...");
            
            // Create a wrapper callback that checks for stop signal
            $callback = function($data) {
                if ($this->shouldStop) {
                    return false; // This will stop consumption
                }
                return $this->processInboundSms($data);
            };
            
            try {
                $this->rabbitMQ->consumeFromQueue($queue, $callback, $prefetch);
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
            $this->error("Error in Inbound SMS Processor: " . $e->getMessage());
            Log::error("Inbound SMS Processor Error", [
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
     * Process a single inbound SMS message
     */
    public function processInboundSms($data)
    {
        $this->processedCount++;
        
        // Check if we should stop
        if ($this->shouldStop) {
            return false;
        }
        
        $startTime = microtime(true);
        
        try {
            // Extract message details
            $messageId = $data['message_id'] ?? null;
            $senderNumber = $data['sender_number'] ?? $data['source_addr'] ?? null;
            $receiverNumber = $data['receiver_number'] ?? $data['destination_addr'] ?? null;
            $message = $data['message'] ?? $data['short_message'] ?? null;
            $dataCoding = $data['data_coding'] ?? 0;
            $esmClass = $data['esm_class'] ?? 0;
            $receivedAt = $data['received_at'] ?? Carbon::now();
            
            // Validate required fields
            if (empty($senderNumber) || empty($message)) {
                throw new Exception("Missing required fields: sender_number or message");
            }
            
            $this->info("Processing Inbound SMS from: {$senderNumber}, To: {$receiverNumber}");
            
            // Test mode - don't actually save
            if ($this->option('test')) {
                $this->info("TEST MODE: Would process inbound SMS");
                $this->info("  From: {$senderNumber}");
                $this->info("  To: {$receiverNumber}");
                $this->info("  Message: {$message}");
                $this->successCount++;
                return true;
            }
            
            // Determine message type (MO or DLR)
            $messageType = $this->determineMessageType($esmClass, $data);
            
            // Check for duplicate
            if ($this->isDuplicate($messageId, $senderNumber, $message)) {
                $this->info("Duplicate message detected, skipping: {$messageId}");
                $this->successCount++;
                return true;
            }
            
            // Decode message based on data_coding
            $decodedMessage = $this->decodeMessage($message, $dataCoding);
            
            // Determine encoding name
            $encoding = $this->getEncodingName($dataCoding);
            
            // Extract network and country info
            $networkInfo = $this->extractNetworkInfo($data);
            
            // Save to database
            $inboundId = DB::table('sms_inbound')->insertGetId([
                'message_id' => $messageId,
                'sender_number' => $senderNumber,
                'receiver_number' => $receiverNumber,
                'message' => $decodedMessage,
                'encoding' => $encoding,
                'data_coding' => $dataCoding,
                'smsc_message_id' => $data['smsc_message_id'] ?? null,
                'received_at' => $receivedAt,
                'network_code' => $networkInfo['network_code'] ?? null,
                'country_code' => $networkInfo['country_code'] ?? null,
                'status' => 'received',
                'message_type' => $messageType,
                'raw_pdu' => json_encode($data),
                'metadata' => json_encode([
                    'esm_class' => $esmClass,
                    'priority_flag' => $data['priority_flag'] ?? 0,
                    'protocol_id' => $data['protocol_id'] ?? 0,
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            // Process the message (keywords, webhooks, etc.)
            if ($messageType === 'mo') {
                $this->processKeywordsAndActions($inboundId, $senderNumber, $receiverNumber, $decodedMessage);
            }
            
            // Mark as processed
            DB::table('sms_inbound')
                ->where('id', $inboundId)
                ->update([
                    'status' => 'processed',
                    'processed_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            
            // Update statistics
            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to ms
            $this->updateStatistics($receiverNumber, $processingTime);
            
            $this->successCount++;
            $this->info("✓ Inbound SMS processed successfully: ID {$inboundId}");
            
            // Log the successful processing
            Log::info("Inbound SMS processed", [
                'id' => $inboundId,
                'from' => $senderNumber,
                'to' => $receiverNumber,
                'message_type' => $messageType,
                'processing_time_ms' => round($processingTime, 2)
            ]);
            
            // Return true to acknowledge message and remove from queue
            return true;
            
        } catch (Exception $e) {
            $this->failedCount++;
            
            $this->error("✗ Failed to process inbound SMS: " . $e->getMessage());
            
            Log::error("Inbound SMS Processing Failed", [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Try to save as failed
            try {
                DB::table('sms_inbound')->insert([
                    'message_id' => $data['message_id'] ?? null,
                    'sender_number' => $data['sender_number'] ?? $data['source_addr'] ?? 'unknown',
                    'receiver_number' => $data['receiver_number'] ?? $data['destination_addr'] ?? 'unknown',
                    'message' => $data['message'] ?? $data['short_message'] ?? '',
                    'status' => 'failed',
                    'message_type' => 'mo',
                    'error_message' => $e->getMessage(),
                    'raw_pdu' => json_encode($data),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            } catch (Exception $saveError) {
                Log::error("Failed to save failed inbound SMS: " . $saveError->getMessage());
            }
            
            // Check if it's a permanent failure
            if ($this->isPermanentFailure($e)) {
                return true; // Acknowledge to remove from queue
            }
            
            // Return false to trigger retry logic
            return false;
        }
    }

    /**
     * Determine if message is MO (Mobile Originated) or DR (Delivery Receipt)
     */
    private function determineMessageType($esmClass, $data)
    {
        // ESM class bit 2 set indicates it's a delivery receipt
        if (($esmClass & 0x04) === 0x04) {
            return 'dr';
        }
        
        // Check message content for DLR patterns
        $message = $data['message'] ?? $data['short_message'] ?? '';
        if (preg_match('/id:|stat:|err:|text:/i', $message)) {
            return 'dr';
        }
        
        return 'mo';
    }

    /**
     * Check for duplicate messages
     */
    private function isDuplicate($messageId, $senderNumber, $message)
    {
        if (empty($messageId)) {
            return false;
        }
        
        try {
            // Check by message ID
            $exists = DB::table('sms_inbound')
                ->where('message_id', $messageId)
                ->exists();
            
            if ($exists) {
                return true;
            }
            
            // Also check for duplicate by content within last 5 minutes
            $recentDuplicate = DB::table('sms_inbound')
                ->where('sender_number', $senderNumber)
                ->where('message', $message)
                ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                ->exists();
            
            return $recentDuplicate;
            
        } catch (Exception $e) {
            Log::warning("Failed to check for duplicate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decode message based on data coding
     */
    private function decodeMessage($message, $dataCoding)
    {
        try {
            switch ($dataCoding) {
                case 0x00: // SMSC Default (GSM 7-bit)
                    return $message; // Usually already decoded by SMPP library
                    
                case 0x03: // Latin 1 (ISO-8859-1)
                    return utf8_encode($message);
                    
                case 0x08: // UCS-2 (UTF-16)
                    // If message is hex string, convert it
                    if (ctype_xdigit($message)) {
                        $decoded = '';
                        for ($i = 0; $i < strlen($message); $i += 4) {
                            $decoded .= mb_convert_encoding(
                                hex2bin(substr($message, $i, 4)),
                                'UTF-8',
                                'UTF-16BE'
                            );
                        }
                        return $decoded;
                    }
                    return $message;
                    
                default:
                    return $message;
            }
        } catch (Exception $e) {
            Log::warning("Failed to decode message: " . $e->getMessage());
            return $message;
        }
    }

    /**
     * Get encoding name from data coding
     */
    private function getEncodingName($dataCoding)
    {
        $encodings = [
            0x00 => 'GSM-7',
            0x01 => 'ASCII',
            0x03 => 'Latin-1',
            0x04 => 'Binary',
            0x08 => 'UCS-2',
        ];
        
        return $encodings[$dataCoding] ?? 'default';
    }

    /**
     * Extract network and country information
     */
    private function extractNetworkInfo($data)
    {
        $info = [
            'network_code' => null,
            'country_code' => null
        ];
        
        // Try to extract from sender number (international format)
        $senderNumber = $data['sender_number'] ?? $data['source_addr'] ?? '';
        
        if (preg_match('/^\+?(\d{1,3})/', $senderNumber, $matches)) {
            $info['country_code'] = $matches[1];
        }
        
        // Network code might be provided in optional parameters
        if (isset($data['network_code'])) {
            $info['network_code'] = $data['network_code'];
        }
        
        return $info;
    }

    /**
     * Process keywords and trigger actions
     */
    private function processKeywordsAndActions($inboundId, $senderNumber, $receiverNumber, $message)
    {
        try {
            // Get active keywords for this virtual number
            $keywords = DB::table('sms_inbound_keywords')
                ->where('virtual_number', $receiverNumber)
                ->where('is_active', true)
                ->orderBy('priority', 'asc')
                ->get();
            
            if ($keywords->isEmpty()) {
                return;
            }
            
            $messageUpper = strtoupper(trim($message));
            
            foreach ($keywords as $keyword) {
                $keywordUpper = strtoupper($keyword->keyword);
                $matched = false;
                
                switch ($keyword->match_type) {
                    case 'exact':
                        $matched = ($messageUpper === $keywordUpper);
                        break;
                    case 'starts_with':
                        $matched = str_starts_with($messageUpper, $keywordUpper);
                        break;
                    case 'contains':
                        $matched = str_contains($messageUpper, $keywordUpper);
                        break;
                }
                
                if ($matched) {
                    $this->executeKeywordAction($keyword, $inboundId, $senderNumber, $receiverNumber, $message);
                    break; // Stop at first match
                }
            }
            
        } catch (Exception $e) {
            Log::error("Failed to process keywords: " . $e->getMessage());
        }
    }

    /**
     * Execute keyword action
     */
    private function executeKeywordAction($keyword, $inboundId, $senderNumber, $receiverNumber, $message)
    {
        try {
            $actionData = json_decode($keyword->action_data, true);
            
            switch ($keyword->action) {
                case 'auto_reply':
                    $this->sendAutoReply($senderNumber, $receiverNumber, $actionData['reply_text'] ?? '');
                    break;
                    
                case 'webhook':
                    $this->triggerWebhook($actionData['webhook_url'] ?? '', [
                        'inbound_id' => $inboundId,
                        'from' => $senderNumber,
                        'to' => $receiverNumber,
                        'message' => $message,
                        'keyword' => $keyword->keyword
                    ]);
                    break;
                    
                case 'forward':
                    $this->forwardMessage($actionData['forward_to'] ?? '', $message, $senderNumber);
                    break;
            }
            
            Log::info("Keyword action executed", [
                'keyword' => $keyword->keyword,
                'action' => $keyword->action,
                'inbound_id' => $inboundId
            ]);
            
        } catch (Exception $e) {
            Log::error("Failed to execute keyword action: " . $e->getMessage());
        }
    }

    /**
     * Send auto-reply
     */
    private function sendAutoReply($to, $from, $replyText)
    {
        try {
            // Queue the reply message
            $this->rabbitMQ->publishToQueue('sms.outbound', [
                'queue_id' => 'AR-' . uniqid(),
                'mobile_number' => $to,
                'message' => $replyText,
                'from' => $from,
                'priority' => 8,
                'metadata' => [
                    'type' => 'auto_reply',
                    'initiator' => 'InboundProcessor'
                ]
            ], 8);
            
            $this->info("Auto-reply queued to: {$to}");
            
        } catch (Exception $e) {
            Log::error("Failed to send auto-reply: " . $e->getMessage());
        }
    }

    /**
     * Trigger webhook
     */
    private function triggerWebhook($url, $data)
    {
        // Queue webhook for async processing to avoid blocking
        // You can create a separate webhook queue or use a job
        Log::info("Webhook triggered", ['url' => $url, 'data' => $data]);
        
        // Simple HTTP POST (for immediate webhooks)
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            Log::error("Webhook failed: " . $e->getMessage());
        }
    }

    /**
     * Forward message
     */
    private function forwardMessage($forwardTo, $message, $originalSender)
    {
        try {
            $forwardText = "Forwarded from {$originalSender}: {$message}";
            
            // Queue the forwarded message
            $this->rabbitMQ->publishToQueue('sms.outbound', [
                'queue_id' => 'FW-' . uniqid(),
                'mobile_number' => $forwardTo,
                'message' => $forwardText,
                'priority' => 7,
                'metadata' => [
                    'type' => 'forwarded',
                    'original_sender' => $originalSender,
                    'initiator' => 'InboundProcessor'
                ]
            ], 7);
            
            $this->info("Message forwarded to: {$forwardTo}");
            
        } catch (Exception $e) {
            Log::error("Failed to forward message: " . $e->getMessage());
        }
    }

    /**
     * Update statistics
     */
    private function updateStatistics($virtualNumber, $processingTime)
    {
        try {
            $now = Carbon::now();
            $date = $now->toDateString();
            $hour = $now->hour;
            
            DB::table('sms_inbound_statistics')
                ->updateOrInsert(
                    [
                        'date' => $date,
                        'hour' => $hour,
                        'virtual_number' => $virtualNumber
                    ],
                    [
                        'messages_received' => DB::raw('messages_received + 1'),
                        'messages_processed' => DB::raw('messages_processed + 1'),
                        'avg_processing_time' => DB::raw("(avg_processing_time * messages_processed + {$processingTime}) / (messages_processed + 1)"),
                        'updated_at' => $now
                    ]
                );
        } catch (Exception $e) {
            Log::warning("Failed to update statistics: " . $e->getMessage());
        }
    }

    /**
     * Check if error is permanent (should not retry)
     */
    private function isPermanentFailure(Exception $e)
    {
        $permanentErrors = [
            'Invalid message format',
            'Database constraint violation',
            'Duplicate message'
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
        
        $this->info("\n=== Inbound SMS Processor Statistics ===");
        $this->info("Runtime: {$runtime} seconds");
        $this->info("Processed: {$this->processedCount} messages");
        $this->info("Successful: {$this->successCount} messages");
        $this->info("Failed: {$this->failedCount} messages");
        
        if ($this->processedCount > 0) {
            $successRate = round(($this->successCount / $this->processedCount) * 100, 2);
            $this->info("Success Rate: {$successRate}%");
            
            if ($runtime > 0) {
                $throughput = round($this->processedCount / $runtime, 2);
                $this->info("Throughput: {$throughput} messages/second");
            }
        }
        
        // Disconnect RabbitMQ
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
