<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\SMPP\SMPPService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class ProcessDlrQueue extends Command
{
    protected $signature = 'smpp:dlr-receiver {--continuous} {--timeout=0} {--batch-size=100} {--interval=5} {--process-queue-only} {--process-smpp-only}';
    protected $description = 'Process DLR (Delivery Receipt) messages from SMPP and RabbitMQ queue';

    private $smpp;
    private $rabbitMQ;
    private $processedCount = 0;
    private $failedCount = 0;
    private $deletedCount = 0; // Track deleted DLRs from queue
    private $shouldStop = false;
    private $startTime;
    private $batchSize;
    private $lastStatsTime;
    private $processingActive = false;

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
     * IMPORTANT: Messages are acknowledged (deleted from queue) after successful processing
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
            
            // Consume messages from queue
            $this->rabbitMQ->consumeFromQueue(
                'sms.dlr',
                function($dlrData) use (&$processed, &$failed, &$processedInBatch, $progressBar, $batchSize) {
                    try {
                        // Process the DLR
                        $result = $this->processDlr($dlrData);
                        
                        if ($result) {
                            $processed++;
                            $this->deletedCount++; // Count as deleted from queue
                        } else {
                            $failed++;
                        }
                        
                        $processedInBatch++;
                        $progressBar->advance();
                        
                        // Stop after batch size
                        if ($processedInBatch >= $batchSize) {
                            return 'stop';
                        }
                        
                        return $result;
                        
                    } catch (Exception $e) {
                        Log::error("Error in DLR callback: " . $e->getMessage());
                        $failed++;
                        $progressBar->advance();
                        return false; // Will be acknowledged anyway to prevent blocking
                    }
                },
                $this->batchSize
            );
            
            $this->processingActive = false;
            
            $progressBar->finish();
            $this->info("");
            
            if ($processed > 0) {
                $this->info("│ ✓ Processed {$processed} DLR(s), deleted from queue" . ($failed > 0 ? " ({$failed} failed but removed)" : ""));
                $this->processedCount += $processed;
                $this->failedCount += $failed;
            }
            
            return $processed;
            
        } catch (Exception $e) {
            $this->error("│ ✗ Error processing queue: " . $e->getMessage());
            Log::error("Queue processing error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Process a single DLR
     * Returns true on success (message will be acknowledged and deleted from queue)
     * Returns false on failure (message will still be acknowledged to prevent blocking)
     */
    public function processDlr($dlrData)
    {
        try {
            if (!is_array($dlrData)) {
                Log::warning("Invalid DLR data format", ['data' => $dlrData]);
                return false; // Will be acknowledged anyway
            }
            
            Log::info("Processing DLR", [
                'message_id' => $dlrData['message_id'] ?? 'unknown',
                'status' => $dlrData['status'] ?? 'unknown',
                'mobile' => $dlrData['mobile_number'] ?? 'unknown'
            ]);
            
            // Validate required fields
            if (!isset($dlrData['message_id']) || empty($dlrData['message_id'])) {
                Log::warning("DLR missing message_id", $dlrData);
                return false; // Will be acknowledged anyway to clear from queue
            }
            
            // Begin transaction
            DB::beginTransaction();
            
            try {
                // Update smsg_log with DLR status
                $updated = $this->updateSmsgLogWithDlr($dlrData);
                
                // Store in DLR table for tracking
                $this->storeDlrRecord($dlrData);
                
                // Update queue record if exists
                if (isset($dlrData['queue_id'])) {
                    $this->updateQueueRecord($dlrData);
                }
                
                DB::commit();
                
                Log::info("DLR processed successfully and will be deleted from queue", [
                    'message_id' => $dlrData['message_id']
                ]);
                
                return true; // Success - message will be ACKed and deleted from queue
                
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            Log::error("Failed to process DLR", [
                'error' => $e->getMessage(),
                'data' => $dlrData
            ]);
            return false; // Failed - but will still be ACKed to prevent queue blocking
        }
    }

    private function updateSmsgLogWithDlr($dlrData)
    {
        try {
            $deliveryStatus = $this->mapDlrStatus($dlrData['status'] ?? '');
            $deliveryTime = Carbon::now()->format('YmdHis');
            
            $updateData = [
                'deliverystatus2' => $deliveryStatus,
                'deliverytime2' => $deliveryTime,
                'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                'aggregator_dlrmsg' => $dlrData['status_text'] ?? ''
            ];
            
            // Set sent status based on delivery status
            if (in_array($deliveryStatus, ['Delivered', 'Accepted'])) {
                $updateData['sentstatus'] = 'success';
                $updateData['sentstatustext'] = $deliveryStatus;
            } else if (in_array($deliveryStatus, ['Expired', 'Deleted', 'Non Delivered', 'Rejected', 'Failed'])) {
                $updateData['sentstatus'] = 'fail';
                $updateData['sentstatustext'] = $deliveryStatus;
            }
            
            // Try to find by message_id
            $affected = DB::table('smsg_log')
                ->where(function($query) use ($dlrData) {
                    $query->where('suppliermsgref', $dlrData['message_id'])
                          ->orWhere('deliveryreceipt1', $dlrData['message_id'])
                          ->orWhere('bigid', $dlrData['message_id']);
                })
                ->update($updateData);
            
            if ($affected > 0) {
                Log::info("Updated smsg_log for DLR (will delete from queue)", [
                    'message_id' => $dlrData['message_id'],
                    'status' => $deliveryStatus,
                    'rows_affected' => $affected
                ]);
                return true;
            } else {
                Log::warning("No smsg_log record found for DLR (will still delete from queue)", [
                    'message_id' => $dlrData['message_id']
                ]);
                return false;
            }
                
        } catch (Exception $e) {
            Log::error("Failed to update smsg_log with DLR", [
                'error' => $e->getMessage(),
                'message_id' => $dlrData['message_id'] ?? 'unknown'
            ]);
            throw $e;
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
                    'queue_id' => $dlrData['queue_id'] ?? null,
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

    private function mapDlrStatus($status)
    {
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Expired',
            'DELETED' => 'Deleted',
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'Accepted',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Rejected'
        ];
        
        return $statusMap[$status] ?? 'Unknown';
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
