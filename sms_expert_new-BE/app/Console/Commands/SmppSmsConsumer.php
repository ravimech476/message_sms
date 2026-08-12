<?php

namespace App\Console\Commands;

use App\Services\Queue\RabbitMQService;
use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Illuminate\Support\Facades\DB;
use Exception;

class SmppSmsConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:consume-sms {--queue=sms.outbound} {--prefetch=5} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume SMS messages from RabbitMQ and send via SMPP';

    private $smsQueueService;
    private $rabbitMQ;
    private $shouldStop = false;
    private $debugMode = false;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'onPcntlSignal']);
            pcntl_signal(SIGINT, [$this, 'onPcntlSignal']);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->option('queue');
        $prefetch = (int) $this->option('prefetch');
        $this->debugMode = $this->option('debug');

        $this->info("Starting SMPP SMS Consumer...");
        $this->info("Queue: {$queue}");
        $this->info("Prefetch Count: {$prefetch}");
        if ($this->debugMode) {
            $this->info("Debug Mode: ENABLED");
        }
        $this->info("Press Ctrl+C to stop");
        $this->info("");

        try {
            // Test SMPP connection first
            $this->testSmppConnection();

            $this->smsQueueService = new SmsQueueService();
            $this->rabbitMQ = new RabbitMQService();

            // Start consuming
            $this->consumeMessages($queue, $prefetch);
        } catch (Exception $e) {
            $this->error("Consumer error: " . $e->getMessage());
            SmppLogger::vonage()->error("SMPP Consumer error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Test SMPP connection before starting consumer
     */
    private function testSmppConnection()
    {
        $this->info(" SMPP connection...");

        try {
            $smppService = new \App\Services\SMPP\SMPPService();
            $connected = $smppService->connect();

            if ($connected) {
                $this->info("✓ SMPP connection successful");
                $stats = $smppService->getStatistics();
                $this->info("  Host: " . $stats['host']);
                $this->info("  Port: " . $stats['port']);
                $this->info("  Bound: " . ($stats['bound'] ? 'Yes' : 'No'));
                $smppService->disconnect();
            } else {
                throw new Exception("Failed to connect to SMPP server");
            }
        } catch (Exception $e) {
            $this->error("✗ SMPP connection failed: " . $e->getMessage());
            $this->error("Please check:");
            $this->error("  1. SMPP credentials in .env file");
            $this->error("  2. Network connectivity to SMPP host");
            $this->error("  3. Firewall settings");
            throw $e;
        }

        $this->info("");
    }

    /**
     * Consume messages from queue
     */
    private function consumeMessages($queue, $prefetch)
    {
        $this->info("Waiting for messages...");

        $messageCount = 0;
        $startTime = time();
        $successCount = 0;
        $failedCount = 0;
        $requeuedCount = 0;

        try {
            $this->rabbitMQ->consumeFromQueue(
                $queue,
                function ($data) use (&$messageCount, &$successCount, &$failedCount, &$requeuedCount, $startTime) {
                    if ($this->shouldStop) {
                        $this->info("\nShutting down gracefully...");
                        return false;
                    }

                    $messageCount++;

                    // Display progress
                    $this->info(sprintf(
                        "[%s] Processing message #%d - Queue ID: %s",
                        date('Y-m-d H:i:s'),
                        $messageCount,
                        $data['queue_id'] ?? 'unknown'
                    ));

                    if ($this->debugMode) {
                        $this->info("  Mobile: " . ($data['mobile_number'] ?? 'unknown'));
                        $this->info("  Message: " . substr($data['message'] ?? '', 0, 50) . "...");
                    }

                    try {
                        // Process the SMS
                        $result = $this->smsQueueService->processSms($data);

                        if ($result === true) {
                            $successCount++;
                            $this->info("✓ Message processed successfully");

                            // Check database for sent status
                            if ($this->debugMode && !empty($data['queue_id'])) {
                                $queueRecord = DB::table('sms_queue')
                                    ->where('queue_id', $data['queue_id'])
                                    ->first();

                                if ($queueRecord) {
                                    $this->info("  Status: " . $queueRecord->status);
                                    $this->info("  Message ID: " . $queueRecord->message_id);
                                }
                            }
                        } else {
                            // $requeuedCount++;
                            $this->warn("⚠ Message requeued for retry");

                            // Show error details if available
                            if ($this->debugMode && !empty($data['queue_id'])) {
                                $queueRecord = DB::table('sms_queue')
                                    ->where('queue_id', $data['queue_id'])
                                    ->first();

                                if ($queueRecord && $queueRecord->error_message) {
                                    $this->error("  Error: " . $queueRecord->error_message);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $failedCount++;
                        $this->error("✗ Error processing message: " . $e->getMessage());

                        if ($this->debugMode) {
                            $this->error("  Trace: " . $e->getTraceAsString());
                        }

                        // Log the error
                        SmppLogger::vonage()->error("Failed to process SMS", [
                            'queue_id' => $data['queue_id'] ?? null,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        // Update database with error
                        if (!empty($data['queue_id'])) {
                            DB::table('sms_queue')
                                ->where('queue_id', $data['queue_id'])
                                ->update([
                                    'status' => 'retry',
                                    'error_message' => $e->getMessage(),
                                    'updated_at' => now()
                                ]);
                        }

                        return false; // Requeue the message
                    }

                    // Show statistics every 10 messages
                    if ($messageCount % 10 == 0) {
                        $this->showStatistics($messageCount, $successCount, $failedCount, $requeuedCount, $startTime);
                    }

                    return $result;
                },
                $prefetch
            );
        } catch (Exception $e) {
            $this->error("Error in consumer loop: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Show consumer statistics
     */
    private function showStatistics($messageCount, $successCount, $failedCount, $requeuedCount, $startTime)
    {
        $runtime = time() - $startTime;
        $messagesPerSecond = $runtime > 0 ? round($messageCount / $runtime, 2) : 0;
        $successRate = $messageCount > 0 ? round(($successCount / $messageCount) * 100, 2) : 0;

        $this->info("");
        $this->info("=== Statistics ===");
        $this->info("Messages Processed: {$messageCount}");
        $this->info("  Success: {$successCount}");
        $this->info("  Failed: {$failedCount}");
        $this->info("  Requeued: {$requeuedCount}");
        $this->info("Success Rate: {$successRate}%");
        $this->info("Runtime: " . gmdate("H:i:s", $runtime));
        $this->info("Rate: {$messagesPerSecond} msg/sec");

        // Check SMPP connection status
        try {
            $connections = DB::table('smpp_connections')
                ->where('status', 'connected')
                ->get();

            if ($connections->count() > 0) {
                $this->info("");
                $this->info("SMPP Connections:");
                foreach ($connections as $conn) {
                    $this->info("  {$conn->host}:{$conn->port} - Sent: {$conn->messages_sent}, Failed: {$conn->messages_failed}");
                }
            } else {
                $this->warn("No active SMPP connections!");
            }
        } catch (Exception $e) {
            // Ignore stats errors
        }

        $this->info("==================");
        $this->info("");
    }

    /**
     * Handle pcntl signals (SIGTERM / SIGINT) for graceful shutdown.
     *
     * Renamed from handleSignal() to avoid colliding with Symfony Command's
     * native handleSignal(int, int|false): int|false — PHP 8.1+ fatals on
     * the signature mismatch. We don't use Symfony's signal subscriber here;
     * pcntl_signal() takes any callable, so any name works.
     */
    public function onPcntlSignal(int $signal): void
    {
        // A signal can fire mid-query (e.g. SIGTERM during a DLR lookup), at which point
        // the console OutputInterface may be null/closed — calling $this->info() then throws
        // "Call to a member function writeln() on null" and CRASHES the worker instead of
        // stopping it gracefully. A signal handler must do the minimum: just flag the stop.
        $this->shouldStop = true;
    }
}
