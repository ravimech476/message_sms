<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\NotificationQueueService;
use App\Services\Queue\RabbitMQService;
use Illuminate\Support\Facades\Log;

class NotificationQueueConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:consume 
                            {--single : Process only one notification and exit}
                            {--timeout=0 : Timeout in seconds (0 = no timeout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume and process notification emails from RabbitMQ queue';

    /**
     * @var NotificationQueueService
     */
    protected $notificationQueueService;

    /**
     * @var RabbitMQService
     */
    protected $rabbitMQService;

    /**
     * Queue name
     */
    protected $queueName = 'notifications.send';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Notification Queue Consumer...');
        $this->info('Press Ctrl+C to stop.');
        
        Log::info('Notification Queue Consumer started', [
            'single_mode' => $this->option('single'),
            'timeout' => $this->option('timeout')
        ]);

        try {
            $this->notificationQueueService = new NotificationQueueService();
            $this->rabbitMQService = app(RabbitMQService::class);
            
            if ($this->option('single')) {
                $this->info('Running in single-message mode...');
                $this->processSingleMessage();
            } else {
                $this->info('Running in continuous mode...');
                $this->startContinuousConsumer();
            }
            
        } catch (\Exception $e) {
            $this->error('Consumer error: ' . $e->getMessage());
            Log::error('Notification Queue Consumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Process a single message and exit
     */
    protected function processSingleMessage()
    {
        $this->info('Waiting for a notification to process...');
        
        $stats = $this->notificationQueueService->getQueueStats();
        
        if (($stats['messages'] ?? 0) > 0) {
            $this->info("Found {$stats['messages']} notification(s) in queue");
            $this->consumeMessages(1);
        } else {
            $this->info('No notifications in queue');
        }
    }

    /**
     * Start continuous consumer
     */
    protected function startContinuousConsumer()
    {
        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }

        $timeout = (int) $this->option('timeout');
        $startTime = time();

        while (true) {
            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                $this->info('Timeout reached. Shutting down...');
                break;
            }

            // Process signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                // Get queue stats
                $stats = $this->notificationQueueService->getQueueStats();
                $messageCount = $stats['messages'] ?? 0;

                if ($messageCount > 0) {
                    $this->info("Processing {$messageCount} notification(s) in queue...");
                    $this->consumeMessages($messageCount);
                } else {
                    // No messages, wait before checking again
                    $this->line('No messages in queue. Waiting...');
                    sleep(5);
                }
            } catch (\Exception $e) {
                $this->error('Error processing notification: ' . $e->getMessage());
                Log::error('Notification consumer loop error', [
                    'error' => $e->getMessage()
                ]);
                
                // Wait before retrying
                sleep(10);
            }

            // Touch heartbeat file
            $this->touchHeartbeat();
        }
    }

    /**
     * Consume messages from queue
     */
    protected function consumeMessages($limit = 100)
    {
        try {
            $this->rabbitMQService->consumeFromQueue(
                $this->queueName,
                function ($data) {
                    return $this->processMessage($data);
                },
                1 // prefetch count
            );
        } catch (\Exception $e) {
            // Log error but don't crash the consumer
            Log::error('Error consuming from notification queue', [
                'error' => $e->getMessage()
            ]);
            $this->error('Consume error: ' . $e->getMessage());
        }
    }

    /**
     * Process a single message
     */
    protected function processMessage(array $data)
    {
        try {
            $type = $data['type'] ?? 'notification_email';
            $notificationId = $data['notification_id'] ?? null;
            $userEmail = $data['user_email'] ?? 'N/A';

            $this->info("Processing {$type} for notification #{$notificationId} to {$userEmail}");

            $result = $this->notificationQueueService->processNotification($data);

            if ($result) {
                $this->info("Successfully processed notification #{$notificationId}");
            } else {
                $this->warn("Failed to process notification #{$notificationId}");
            }

            return $result;

        } catch (\Exception $e) {
            $this->error('Message processing error: ' . $e->getMessage());
            Log::error('Notification message processing error', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown($signal)
    {
        $this->info("\nReceived shutdown signal ({$signal}). Gracefully shutting down...");
        Log::info('Notification Queue Consumer shutdown', ['signal' => $signal]);
        exit(0);
    }

    /**
     * Touch heartbeat file for monitoring
     */
    protected function touchHeartbeat()
    {
        $heartbeatFile = storage_path('logs/notification_consumer_heartbeat');
        touch($heartbeatFile);
    }
}
