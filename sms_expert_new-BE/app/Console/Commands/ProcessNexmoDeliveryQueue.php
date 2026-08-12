<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\Queue\NexmoDeliveryQueueService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ProcessNexmoDeliveryQueue extends Command
{
    protected $signature = 'nexmo:process-delivery-queue 
                            {--continuous : Run continuously}
                            {--timeout=0 : Timeout in seconds (0 = no timeout)}
                            {--batch-size=100 : Number of messages to process per batch}
                            {--interval=5 : Seconds to wait between batches when queue is empty}';

    protected $description = 'Process Nexmo delivery reports from RabbitMQ queue';

    private $rabbitMQ;
    private $queueService;
    private $processedCount = 0;
    private $failedCount = 0;
    private $shouldStop = false;
    private $startTime;
    private $batchSize;
    private $lastStatsTime;
    private $processingActive = false;
    private $queueName;

    public function handle()
    {
        $continuous = $this->option('continuous');
        $timeout = $this->option('timeout');
        $this->batchSize = $this->option('batch-size');
        $interval = $this->option('interval');

        $this->startTime = Carbon::now();
        $this->lastStatsTime = Carbon::now();
        $this->queueName = env('RABBITMQ_NEXMO_DELIVERY_QUEUE', 'nexmo.delivery.reports');

        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║     NEXMO DELIVERY QUEUE PROCESSOR STARTED             ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("Mode: " . ($continuous ? "Continuous" : "Single Run"));
        $this->info("Batch Size: {$this->batchSize}");
        $this->info("Queue: {$this->queueName}");
        $this->info("Started at: " . $this->startTime->format('Y-m-d H:i:s'));
        $this->info("");

        try {
            // Initialize RabbitMQ
            $this->info("▶ Initializing RabbitMQ connection...");
            try {
                $this->rabbitMQ = new RabbitMQService();
                $this->queueService = new NexmoDeliveryQueueService($this->rabbitMQ);
                
                $stats = $this->rabbitMQ->getQueueStats($this->queueName);
                if (isset($stats['messages'])) {
                    $this->info("✓ RabbitMQ connected - Queue has {$stats['messages']} messages waiting");
                }
            } catch (Exception $e) {
                $this->error("✗ RabbitMQ connection failed: " . $e->getMessage());
                return 1;
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
                $this->runContinuous($interval);
            } else {
                $this->runOnce();
            }
        } catch (Exception $e) {
            $this->error("Nexmo Delivery Queue Processor Error: " . $e->getMessage());
            Log::error("Nexmo Delivery Queue Processor Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->cleanup();
        }

        return 0;
    }

    private function runContinuous($interval)
    {
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║     CONTINUOUS MODE - Press Ctrl+C to stop             ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("");

        $cycleCount = 0;

        while (!$this->shouldStop) {
            $cycleCount++;
            $cycleStart = Carbon::now();

            $this->info("┌─ Cycle #{$cycleCount} ─ " . $cycleStart->format('H:i:s') . " ─────────────────────────");

            $processedThisCycle = $this->processQueue();

            $cycleDuration = Carbon::now()->diffInSeconds($cycleStart);
            $this->info("└─ Cycle complete: {$processedThisCycle} records in {$cycleDuration}s ────────────────");

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

    private function runOnce()
    {
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║     SINGLE RUN MODE                                    ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
        $this->info("");

        $totalProcessed = 0;

        while (!$this->shouldStop) {
            $processed = $this->processQueue();
            $totalProcessed += $processed;

            if ($processed == 0) {
                break;
            }

            if ($totalProcessed % ($this->batchSize * 5) == 0) {
                $this->showStatistics();
            }
        }

        $this->info("✓ Completed processing {$totalProcessed} delivery reports");
        $this->info("");
        $this->showStatistics();
    }

    private function processQueue()
    {
        $this->line("│ ▶ Processing Nexmo delivery queue...");

        try {
            $stats = $this->rabbitMQ->getQueueStats($this->queueName);
            $messageCount = $stats['messages'] ?? 0;

            if ($messageCount == 0) {
                $this->line("│   Queue is empty");
                return 0;
            }

            $this->info("│   Found {$messageCount} record(s) in queue");

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
                $this->queueName,
                function ($data) use (&$processed, &$failed, &$processedInBatch, $progressBar, $batchSize) {
                    try {
                        // Process the delivery report
                        $result = $this->queueService->processDeliveryReport($data);

                        if ($result) {
                            $processed++;
                            Log::debug("Nexmo delivery report processed", [
                                'message_id' => $data['message_id'] ?? 'unknown'
                            ]);
                        } else {
                            $failed++;
                            Log::warning("Nexmo delivery report processing returned false", [
                                'message_id' => $data['message_id'] ?? 'unknown'
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
                        Log::error("Error processing Nexmo delivery report", [
                            'error' => $e->getMessage(),
                            'message_id' => $data['message_id'] ?? 'unknown'
                        ]);
                        $failed++;
                        $progressBar->advance();

                        // Return false to NACK and requeue for retry
                        return false;
                    }
                },
                $this->batchSize
            );

            $this->processingActive = false;

            $progressBar->finish();
            $this->info("");

            if ($processed > 0) {
                $this->info("│ ✓ Processed {$processed} record(s)" . ($failed > 0 ? " ({$failed} failed)" : ""));
                $this->processedCount += $processed;
                $this->failedCount += $failed;
            }

            return $processed;
        } catch (Exception $e) {
            $this->error("│ ✗ Error processing queue: " . $e->getMessage());
            Log::error("Nexmo delivery queue processing error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    private function showStatistics()
    {
        $runtime = Carbon::now()->diffInSeconds($this->startTime);
        $runtimeFormatted = gmdate('H:i:s', $runtime);

        $this->info("");
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║         NEXMO DELIVERY QUEUE STATISTICS                ║");
        $this->info("╠════════════════════════════════════════════════════════╣");
        $this->info("║ Runtime:           {$runtimeFormatted}                                ║");
        $this->info("║ Records Processed: " . str_pad($this->processedCount, 6, ' ', STR_PAD_LEFT) . "                               ║");

        if ($this->failedCount > 0) {
            $this->info("║ Records Failed:    " . str_pad($this->failedCount, 6, ' ', STR_PAD_LEFT) . "                               ║");
        }

        if ($runtime > 0) {
            $rate = round($this->processedCount / $runtime * 60, 2);
            $this->info("║ Processing Rate:   " . str_pad($rate, 6, ' ', STR_PAD_LEFT) . " records/min                    ║");
        }

        try {
            $stats = $this->rabbitMQ->getQueueStats($this->queueName);
            $remaining = $stats['messages'] ?? 0;
            $this->info("║ Queue Remaining:   " . str_pad($remaining, 6, ' ', STR_PAD_LEFT) . "                               ║");
        } catch (Exception $e) {
            // Ignore
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
        $this->info("  Total Processed: {$this->processedCount} records");
        $this->info("  Total Failed: {$this->failedCount} records");
        $this->info("  Duration: " . gmdate('H:i:s', $totalRuntime));

        $this->info("");
        $this->info("╔════════════════════════════════════════════════════════╗");
        $this->info("║     NEXMO DELIVERY QUEUE PROCESSOR STOPPED             ║");
        $this->info("╚════════════════════════════════════════════════════════╝");
    }
}
