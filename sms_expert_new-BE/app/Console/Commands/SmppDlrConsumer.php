<?php

namespace App\Console\Commands;

use App\Services\Queue\RabbitMQService;
use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Exception;

class SmppDlrConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:consume-dlr {--queue=sms.dlr} {--prefetch=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume DLR (Delivery Receipt) messages from RabbitMQ';

    private $smsQueueService;
    private $rabbitMQ;
    private $shouldStop = false;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->option('queue');
        $prefetch = (int) $this->option('prefetch');
        
        $this->info("Starting SMPP DLR Consumer...");
        $this->info("Queue: {$queue}");
        $this->info("Prefetch Count: {$prefetch}");
        $this->info("Press Ctrl+C to stop");
        
        try {
            $this->smsQueueService = new SmsQueueService();
            $this->rabbitMQ = new RabbitMQService();
            
            // Start consuming
            $this->consumeDlrMessages($queue, $prefetch);
            
        } catch (Exception $e) {
            $this->error("DLR Consumer error: " . $e->getMessage());
            SmppLogger::vonage()->error("SMPP DLR Consumer error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    /**
     * Consume DLR messages from queue
     */
    private function consumeDlrMessages($queue, $prefetch)
    {
        $this->info("Waiting for DLR messages...");
        
        $messageCount = 0;
        $startTime = time();
        
        try {
            $this->rabbitMQ->consumeFromQueue(
                $queue,
                function ($data) use (&$messageCount, $startTime) {
                    if ($this->shouldStop) {
                        $this->info("\nShutting down gracefully...");
                        return false;
                    }
                    
                    $messageCount++;
                    
                    // Display DLR info
                    $this->info(sprintf(
                        "[%s] Processing DLR #%d - Message ID: %s, Status: %s",
                        date('Y-m-d H:i:s'),
                        $messageCount,
                        $data['message_id'] ?? 'unknown',
                        $data['status'] ?? 'unknown'
                    ));
                    
                    // Process the DLR
                    $result = $this->smsQueueService->processDlr($data);
                    
                    if ($result) {
                        $this->info("✓ DLR processed successfully");
                    } else {
                        $this->warn("⚠ DLR processing failed");
                    }
                    
                    // Show statistics every 100 messages
                    if ($messageCount % 100 == 0) {
                        $this->showStatistics($messageCount, $startTime);
                    }
                    
                    return $result;
                },
                $prefetch
            );
        } catch (Exception $e) {
            $this->error("Error in DLR consumer loop: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Show consumer statistics
     */
    private function showStatistics($messageCount, $startTime)
    {
        $runtime = time() - $startTime;
        $messagesPerSecond = $runtime > 0 ? round($messageCount / $runtime, 2) : 0;
        
        $this->info("=== DLR Statistics ===");
        $this->info("DLRs Processed: {$messageCount}");
        $this->info("Runtime: " . gmdate("H:i:s", $runtime));
        $this->info("Rate: {$messagesPerSecond} dlr/sec");
        $this->info("======================");
    }

    /**
     * Handle system signals
     */
public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->info("\nReceived signal: {$signal}");
        $this->shouldStop = true;
    }
}
