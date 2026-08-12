<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\InboundSmsProcessor;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Process Inbound SMS Queue
 * 
 * This command consumes messages from the RabbitMQ inbound SMS queue
 * and processes them one by one using the InboundSmsProcessor service.
 * 
 * Usage:
 *   php artisan queue:inbound-sms              # Process messages continuously
 *   php artisan queue:inbound-sms --once       # Process one message and exit
 *   php artisan queue:inbound-sms --stats      # Show queue statistics
 */
class ProcessInboundSmsQueue extends Command
{
    protected $signature = 'queue:inbound-sms
                            {--once : Process only one message and exit}
                            {--stats : Show queue statistics only}
                            {--prefetch=1 : Number of messages to prefetch}';

    protected $description = 'Process inbound SMS messages from RabbitMQ queue';

    protected $rabbitMQService;
    protected $processor;
    protected $processedCount = 0;
    protected $failedCount = 0;
    protected $startTime;

    public function handle()
    {
        $this->startTime = microtime(true);

        // Show statistics only
        if ($this->option('stats')) {
            return $this->showStats();
        }

        $this->info('=== Inbound SMS Queue Processor ===');
        $this->newLine();

        try {
            $this->rabbitMQService = new RabbitMQService();
            $this->processor = new InboundSmsProcessor();
        } catch (Exception $e) {
            $this->error('Failed to initialize services: ' . $e->getMessage());
            return 1;
        }

        $queueName = env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound');
        $prefetch = (int) $this->option('prefetch');

        $this->info("Queue: $queueName");
        $this->info("Prefetch: $prefetch");
        $this->newLine();

        // Show initial queue stats
        $this->showQueueStats($queueName);
        $this->newLine();

        if ($this->option('once')) {
            $this->info('Processing single message...');
            return $this->processOnce($queueName);
        }

        $this->info('Starting continuous processing...');
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        // Set up signal handler for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->shutdown();
            });
            pcntl_signal(SIGTERM, function () {
                $this->shutdown();
            });
        }

        try {
            $this->rabbitMQService->consumeFromQueue(
                $queueName,
                function ($data) {
                    return $this->processMessage($data);
                },
                $prefetch
            );
        } catch (Exception $e) {
            $this->error('Queue consumption error: ' . $e->getMessage());
            Log::error('Inbound SMS queue consumption error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Process a single message from the queue
     */
    protected function processMessage(array $data): bool
    {
        $queueId = $data['queue_id'] ?? 'unknown';
        $startTime = microtime(true);

        $this->line("[" . now()->format('H:i:s') . "] Processing: $queueId");

        try {
            // Extract the actual inbound data from the queue message
            $inboundData = $data['data'] ?? $data;
            $inboundData['request_id'] = $queueId;

            // Process the message
            $result = $this->processor->process($inboundData);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result) {
                $this->processedCount++;
                $this->info("  ✓ Success ({$duration}ms) - Source: " . ($inboundData['source'] ?? 'unknown'));
                return true;
            } else {
                $this->failedCount++;
                $this->warn("  ✗ Failed ({$duration}ms)");
                return false;
            }
        } catch (Exception $e) {
            $this->failedCount++;
            $this->error("  ✗ Error: " . $e->getMessage());
            Log::error('Inbound SMS processing error', [
                'queue_id' => $queueId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process single message and exit
     */
    protected function processOnce(string $queueName): int
    {
        try {
            // Get queue stats to check if there are messages
            $stats = $this->rabbitMQService->getQueueStats($queueName);
            
            if (isset($stats['error']) || $stats['messages'] == 0) {
                $this->warn('No messages in queue');
                return 0;
            }

            // Consume one message using basic_get instead of basic_consume
            $this->info('Fetching message from queue...');
            
            // Use a custom approach for single message
            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                env('RABBITMQ_HOST', 'localhost'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                env('RABBITMQ_VHOST', '/')
            );
            
            $channel = $connection->channel();
            $message = $channel->basic_get($queueName);
            
            if ($message) {
                $data = json_decode($message->body, true);
                $result = $this->processMessage($data);
                
                if ($result) {
                    $channel->basic_ack($message->getDeliveryTag());
                } else {
                    // Nack and requeue
                    $channel->basic_nack($message->getDeliveryTag(), false, true);
                }
                
                $this->showSummary();
            } else {
                $this->warn('No messages available');
            }
            
            $channel->close();
            $connection->close();
            
            return $result ? 0 : 1;
            
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show queue statistics
     */
    protected function showStats(): int
    {
        $this->info('=== Queue Statistics ===');
        $this->newLine();

        try {
            $this->rabbitMQService = new RabbitMQService();
        } catch (Exception $e) {
            $this->error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            return 1;
        }

        $queues = [
            env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound') => 'Inbound SMS',
            env('RABBITMQ_SMS_QUEUE', 'sms.outbound') => 'Outbound SMS',
            'sms.dead' => 'Dead Letter',
            env('RABBITMQ_DLR_QUEUE', 'sms.dlr') => 'Delivery Reports',
            env('RABBITMQ_FAILED_QUEUE', 'sms.failed') => 'Failed/Retry',
        ];

        $rows = [];
        foreach ($queues as $queue => $name) {
            $stats = $this->rabbitMQService->getQueueStats($queue);
            $rows[] = [
                $name,
                $queue,
                $stats['messages'] ?? 0,
                $stats['consumers'] ?? 0,
                isset($stats['error']) ? '❌ ' . $stats['error'] : '✓'
            ];
        }

        $this->table(
            ['Name', 'Queue', 'Messages', 'Consumers', 'Status'],
            $rows
        );

        return 0;
    }

    /**
     * Show queue stats inline
     */
    protected function showQueueStats(string $queueName): void
    {
        $stats = $this->rabbitMQService->getQueueStats($queueName);
        
        if (isset($stats['error'])) {
            $this->warn("Queue stats unavailable: " . $stats['error']);
        } else {
            $this->info("Messages pending: " . $stats['messages']);
            $this->info("Active consumers: " . $stats['consumers']);
        }
    }

    /**
     * Show processing summary
     */
    protected function showSummary(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);
        
        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Processed: {$this->processedCount}");
        $this->line("Failed: {$this->failedCount}");
        $this->line("Duration: {$duration}s");
    }

    /**
     * Graceful shutdown
     */
    protected function shutdown(): void
    {
        $this->newLine();
        $this->warn('Shutting down...');
        $this->showSummary();
        
        if ($this->rabbitMQService) {
            $this->rabbitMQService->disconnect();
        }
        
        exit(0);
    }
}
