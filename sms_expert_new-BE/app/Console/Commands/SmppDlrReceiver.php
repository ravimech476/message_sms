<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SMPPService;
use App\Services\SMPP\SMPPPoolManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;

class SmppDlrReceiver extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:dlr-receiver
                            {--bank= : Bank key from config/smpp_banks.php (e.g. a0, b0). Required when SMPP_BANKS_ENABLED=true. Omit for single-bind .env mode.}
                            {--send : Also act as SENDER (transceiver): drain the SMPP outbound queue and submit on this bound session, so the DLR returns here. Used with SMPP_PERSISTENT_SEND=true.}
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
    protected $description = 'Continuously receive and process DLRs from SMPP connection';

    private $smppService;
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

    /** Transceiver send mode (drain outbound queue + submit on this bound session) */
    private $sendEnabled = false;
    private $rabbit = null;
    private $outboundQueue;
    private $outboundBatch = 20;
    private $sentCount = 0;

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
        
        // Transceiver send mode: drain the outbound queue and submit on THIS bound
        // session so Vonage routes the DLR back here (reliable SMPP DLR). Enabled by
        // --send or globally via SMPP_PERSISTENT_SEND.
        $this->sendEnabled = (bool) ($this->option('send') || config('smpp.persistent_send'));
        $this->outboundQueue = config('smpp.outbound_queue', 'sms.outbound');
        $this->outboundBatch = max(1, (int) config('smpp.outbound_batch', 20));
        if ($this->sendEnabled) {
            try {
                $this->rabbit = new \App\Services\Queue\RabbitMQService();
                $this->info("Transceiver SEND mode ON — draining '{$this->outboundQueue}' (batch {$this->outboundBatch}).");
            } catch (\Throwable $e) {
                $this->warn("Could not init RabbitMQ for send mode: " . $e->getMessage() . " (receive-only this run)");
                $this->sendEnabled = false;
            }
        }

        $this->info("Starting SMPP DLR Receiver...");
        $this->info("Timeout: " . ($this->timeout > 0 ? "{$this->timeout} seconds" : "Infinite (Continuous)"));
        $this->info("Reconnect delay: {$this->reconnectDelay} seconds");
        $this->info("Enquire link interval: {$this->enquireInterval} seconds");
        $this->info("Enquire link max failures: {$this->maxEnquireLinkFailures}");
        $this->info("Max reconnect attempts: " . ($this->maxReconnectAttempts > 0 ? $this->maxReconnectAttempts : "Unlimited"));
        $this->info(str_repeat("=", 60));
        
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
                
                // Process incoming PDUs (DLRs / MO)
                $this->processPdus();

                // Transceiver: submit any queued outbound SMS on THIS bound session
                if ($this->sendEnabled) {
                    $this->drainOutboundQueue();
                }

                // Maintain connection with enquire_link (don't throw on failure)
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
        $this->info("DLR Receiver stopped gracefully.");
        $this->info("Total PDUs processed: {$this->processedCount}");
        $this->info("Total reconnection attempts: {$this->reconnectAttempts}");
        
        return 0;
    }
    
    /**
     * Setup signal handlers for graceful shutdown
     */
    private function setupSignalHandlers()
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true); // Enable async signals
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
     * Transceiver send: pull queued outbound SMS and submit on THIS bound session.
     * Because Vonage routes a message's DLR back to the submitting session, doing the
     * submit here (on the persistent transceiver) guarantees the DLR returns to this
     * same daemon and is processed by processPdus() — the whole point of persistent mode.
     *
     * Pulls up to outboundBatch messages per loop iteration (non-blocking basic_get).
     * ACK on successful submit; NACK+requeue on failure (the id-scoped duplicate guard
     * in SMPPService::sendSMS makes a requeued resend idempotent).
     */
    private function drainOutboundQueue(): void
    {
        if (!$this->rabbit || !$this->smppService) {
            return;
        }

        for ($i = 0; $i < $this->outboundBatch; $i++) {
            $msg = $this->rabbit->getNextMessage($this->outboundQueue);
            if (!$msg) {
                break; // queue empty
            }

            $payload = json_decode($msg->body, true);
            if (!is_array($payload) || empty($payload['to']) || !isset($payload['message'])) {
                // Poison / non-send message — drop it rather than loop forever.
                $this->rabbit->ackMessage($msg);
                continue;
            }

            try {
                $result = $this->smppService->sendSMS(
                    $payload['to'],
                    $payload['message'],
                    $payload['from'] ?? null,
                    (int) ($payload['priority'] ?? 5),
                    $payload['queue_id'] ?? null,
                    $payload['initiator'] ?? 'ControlPanel',
                    $payload['reference_id'] ?? null,
                    $payload['schedule_delivery_time'] ?? null,
                    $payload['smsg_log_id'] ?? null
                );

                if (!empty($result['success'])) {
                    $this->rabbit->ackMessage($msg);
                    $this->sentCount++;
                    SmppLogger::vonage()->info('Transceiver submitted queued SMS', [
                        'to'          => $payload['to'],
                        'smsg_log_id' => $payload['smsg_log_id'] ?? null,
                        'message_id'  => $result['message_id'] ?? null,
                        'bank'        => $this->option('bank'),
                    ]);
                } else {
                    // Submit failed — requeue and stop this round (likely a link issue).
                    $this->rabbit->nackMessage($msg, true);
                    SmppLogger::vonage()->warning('Transceiver submit failed — requeued', [
                        'to'    => $payload['to'],
                        'error' => $result['error'] ?? 'unknown',
                    ]);
                    break;
                }
            } catch (\Throwable $e) {
                $this->rabbit->nackMessage($msg, true);
                SmppLogger::vonage()->error('Transceiver submit exception — requeued: ' . $e->getMessage(), [
                    'to' => $payload['to'] ?? null,
                ]);
                break;
            }
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
            
            // When --bank=<key> is supplied AND config('smpp_banks.enabled') is
            // true, SMPPService pulls the bank's host/port/system_id/password/
            // system_type/seq_id_range from config/smpp_banks.php. Otherwise it
            // falls back to single-bind env vars (legacy behaviour).
            $bankKey = $this->option('bank') ?: null;
            $this->smppService = new SMPPService(
                env('SMPP_HOST', 'smpp1.nexmo.com'),
                env('SMPP_PORT', 8000),
                $bankKey
            );
            
            $this->smppService->connect();
            $this->lastActivity = Carbon::now();
            $this->lastEnquireLink = Carbon::now();
            $this->enquireLinkFailures = 0; // Reset enquire link failure counter
            
            $this->info("✓ Connected and bound successfully!");
            
            return true;
            
        } catch (Exception $e) {
            $this->error("✗ Failed to connect: " . $e->getMessage());
            SmppLogger::vonage()->error("SMPP Connection Error", [
                'attempt' => $this->reconnectAttempts,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Only alert after a sustained outage (5 consecutive reconnect
            // attempts) — transient socket blips on startup are noise.
            if ($this->reconnectAttempts >= 5) {
                \App\Services\SMPP\SmppErrorAlertService::notify(
                    'Vonage SMPP DLR receiver cannot connect',
                    "Vonage DLR receiver has failed to connect/bind for {$this->reconnectAttempts} consecutive attempts. Inbound DLR processing is degraded.",
                    [
                        'provider' => 'vonage',
                        'bank'     => method_exists($this, 'option') ? ($this->option('bank') ?: '(single-bind)') : '(unknown)',
                        'attempt'  => $this->reconnectAttempts,
                        'error'    => $e->getMessage(),
                    ]
                );
            }

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
     * Process incoming PDUs
     */
    private function processPdus()
    {
        if (!$this->smppService) {
            return;
        }
        
        try {
            $processed = $this->smppService->processIncomingPdus();
            
            if ($processed > 0) {
                $this->processedCount += $processed;
                $this->lastActivity = Carbon::now();
                
                // Reset enquire link failures on successful PDU processing
                if ($this->enquireLinkFailures > 0) {
                    $this->enquireLinkFailures = 0;
                }
                
                $this->info("✓ Processed {$processed} PDUs | Total: {$this->processedCount} | " . 
                           Carbon::now()->format('Y-m-d H:i:s'));
            }
        } catch (Exception $e) {
            throw new Exception("Failed to process PDUs: " . $e->getMessage());
        }
    }
    
    /**
     * Maintain connection with enquire_link
     * This method does NOT throw exceptions - it handles failures gracefully
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
                    // Enquire link failed but connection might still be alive
                    $this->handleEnquireLinkFailure();
                }
            } catch (Exception $e) {
                // Exception during enquire link - handle gracefully
                $this->handleEnquireLinkFailure($e);
            }
        }
    }
    
    /**
     * Handle enquire link failure without killing the connection immediately
     */
    private function handleEnquireLinkFailure(Exception $e = null)
    {
        $this->enquireLinkFailures++;
        
        $errorMsg = $e ? $e->getMessage() : "No response";
        
        if ($this->enquireLinkFailures < $this->maxEnquireLinkFailures) {
            // Just warn, don't disconnect yet
            $this->warn("✗ Enquire link failed ({$this->enquireLinkFailures}/{$this->maxEnquireLinkFailures}): {$errorMsg}");
            
            // Update last enquire link time to prevent spamming
            $this->lastEnquireLink = Carbon::now();
            
            SmppLogger::vonage()->warning("SMPP Enquire Link Failed", [
                'failure_count' => $this->enquireLinkFailures,
                'max_failures' => $this->maxEnquireLinkFailures,
                'error' => $errorMsg
            ]);
        } else {
            // Max failures reached - force reconnection
            $this->error("✗ Enquire link failed {$this->enquireLinkFailures} times - forcing reconnection");
            
            SmppLogger::vonage()->error("SMPP Enquire Link Max Failures", [
                'failure_count' => $this->enquireLinkFailures,
                'error' => $errorMsg
            ]);
            
            // Throw exception to trigger reconnection
            throw new Exception("Enquire link failed {$this->enquireLinkFailures} times");
        }
    }
    
    /**
     * Handle errors
     */
    private function handleError(Exception $e)
    {
        $this->error("Error: " . $e->getMessage());
        SmppLogger::vonage()->error("SMPP DLR Receiver Error", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'consecutive_errors' => $this->consecutiveErrors,
            'processed_count' => $this->processedCount,
            'enquire_failures' => $this->enquireLinkFailures
        ]);
        
        // Disconnect and cleanup
        $this->disconnect();
        
        if ($this->isRunning) {
            // Use exponential backoff but cap at 60 seconds
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