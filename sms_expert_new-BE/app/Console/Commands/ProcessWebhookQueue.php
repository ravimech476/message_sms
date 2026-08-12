<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\DeliveryStatusService;
use App\Services\InboundSmsProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

/**
 * Process Webhook Queue
 *
 * Consumes DLR and Inbound SMS messages from RabbitMQ webhook queues
 * and processes them in the background.
 *
 * Usage:
 *   php artisan queue:webhook                    # Process both DLR and Inbound
 *   php artisan queue:webhook --type=dlr         # Process only DLR
 *   php artisan queue:webhook --type=inbound     # Process only Inbound SMS
 *   php artisan queue:webhook --once             # Process one message and exit
 *   php artisan queue:webhook --stats            # Show queue statistics
 */
class ProcessWebhookQueue extends Command
{
    protected $signature = 'queue:webhook
                            {--type=all : Type of messages to process (all, dlr, inbound)}
                            {--once : Process only one message and exit}
                            {--stats : Show queue statistics only}
                            {--prefetch=1 : Number of messages to prefetch}';

    protected $description = 'Process webhook messages (DLR and Inbound SMS) from RabbitMQ queues';

    protected $rabbitMQService;
    protected $deliveryStatusService;
    protected $inboundProcessor;
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

        $type = $this->option('type');
        $this->info('=== Webhook Queue Processor ===');
        $this->info("Processing: {$type}");
        $this->newLine();

        try {
            $this->rabbitMQService = new RabbitMQService();
            $this->deliveryStatusService = app(DeliveryStatusService::class);
            $this->inboundProcessor = app(InboundSmsProcessor::class);
        } catch (Exception $e) {
            $this->error('Failed to initialize services: ' . $e->getMessage());
            return 1;
        }

        $prefetch = (int) $this->option('prefetch');

        // Show initial queue stats
        $this->showQueueStats();
        $this->newLine();

        if ($this->option('once')) {
            $this->info('Processing single message...');
            return $this->processOnce($type);
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
            if ($type === 'dlr' || $type === 'all') {
                $this->startConsumer('dlr', $prefetch);
            }

            if ($type === 'inbound' || $type === 'all') {
                $this->startConsumer('inbound', $prefetch);
            }
        } catch (Exception $e) {
            $this->error('Queue consumption error: ' . $e->getMessage());
            Log::error('Webhook queue consumption error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Start consumer for specific queue type
     */
    protected function startConsumer(string $type, int $prefetch): void
    {
        $queueName = $type === 'dlr'
            ? env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr')
            : env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound');

        $this->info("Consuming from: {$queueName}");

        $this->rabbitMQService->consumeFromQueue(
            $queueName,
            function ($data) use ($type) {
                return $this->processMessage($data, $type);
            },
            $prefetch
        );
    }

    /**
     * Process a single message from the queue
     */
    protected function processMessage(array $data, string $type): bool
    {
        $queueId = $data['queue_id'] ?? 'unknown';
        $provider = $data['provider'] ?? 'unknown';
        $startTime = microtime(true);

        $this->line("[" . now()->format('H:i:s') . "] Processing {$type}: {$queueId} ({$provider})");

        try {
            // Extract the actual webhook data
            $webhookData = $data['data'] ?? $data;
            $webhookProvider = $data['provider'] ?? $webhookData['_provider'] ?? 'nexmo';

            if ($type === 'dlr') {
                $result = $this->processDlr($webhookData, $webhookProvider);
            } else {
                $result = $this->processInbound($webhookData, $webhookProvider);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result) {
                $this->processedCount++;
                $this->info("  ✓ Success ({$duration}ms)");
                return true;
            } else {
                $this->failedCount++;
                $this->warn("  ✗ Failed ({$duration}ms)");
                return false;
            }
        } catch (Exception $e) {
            $this->failedCount++;
            $this->error("  ✗ Error: " . $e->getMessage());
            Log::error('Webhook processing error', [
                'queue_id' => $queueId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process DLR (Delivery Receipt)
     */
    protected function processDlr(array $data, string $provider): bool
    {
        if ($provider === 'sinch') {
            return $this->processSinchDlr($data);
        }
        return $this->processNexmoDlr($data);
    }

    /**
     * Process Nexmo DLR
     */
    protected function processNexmoDlr(array $data): bool
    {
        $messageId = $data['messageId'] ?? $data['message-id'] ?? null;
        $status = $data['status'] ?? 'unknown';
        $to = $data['to'] ?? $data['msisdn'] ?? '';
        $errCode = $data['err-code'] ?? '0';
        $timestamp = $data['message-timestamp'] ?? null;

        if (empty($messageId)) {
            Log::warning("[WebhookQueue] Nexmo DLR missing messageId");
            return false;
        }

        // Map status
        $mappedStatus = $this->deliveryStatusService->mapVonageStatus($status);

        // Prepare DLR data
        $dlrData = [
            'message_id' => $messageId,
            'mobile_number' => $to,
            'status' => $mappedStatus['status'],
            'error_code' => $errCode ?: $mappedStatus['error_code'],
            'done_date' => $timestamp ? Carbon::parse($timestamp)->format('YmdHis') : Carbon::now()->format('YmdHis'),
            'provider' => 'nexmo',
            'aggregator_code' => $errCode,
            'aggregator_msg' => $status,
            'retry' => '0',
            'raw_data' => $data,
        ];

        // Process using DeliveryStatusService
        $result = $this->deliveryStatusService->processDeliveryReceipt($dlrData);

        Log::info("[WebhookQueue] Nexmo DLR processed", [
            'message_id' => $messageId,
            'status' => $status,
            'result' => $result
        ]);

        return $result;
    }

    /**
     * Process Sinch DLR
     */
    protected function processSinchDlr(array $data): bool
    {
        $messageId = $data['id'] ?? $data['batch_id'] ?? null;
        $status = $data['status'] ?? 'UNKNOWN';
        $recipient = ltrim($data['recipient'] ?? '', '+');
        $errorCode = $data['code'] ?? 0;
        $timestamp = $data['at'] ?? null;

        if (empty($messageId)) {
            Log::warning("[WebhookQueue] Sinch DLR missing message ID");
            return false;
        }

        // Map status
        $mappedStatus = $this->deliveryStatusService->mapSinchStatus($status, $errorCode);

        // Prepare DLR data
        $dlrData = [
            'message_id' => $messageId,
            'mobile_number' => $recipient,
            'status' => $mappedStatus['status'],
            'error_code' => $errorCode,
            'done_date' => $timestamp ? Carbon::parse($timestamp)->format('YmdHis') : Carbon::now()->format('YmdHis'),
            'provider' => 'sinch',
            'aggregator_code' => $errorCode,
            'aggregator_msg' => $status,
            'retry' => '0',
            'raw_data' => $data,
        ];

        // Process using DeliveryStatusService
        $result = $this->deliveryStatusService->processDeliveryReceipt($dlrData);

        Log::info("[WebhookQueue] Sinch DLR processed", [
            'message_id' => $messageId,
            'status' => $status,
            'result' => $result
        ]);

        return $result;
    }

    /**
     * Process Inbound SMS
     */
    protected function processInbound(array $data, string $provider): bool
    {
        if ($provider === 'sinch') {
            return $this->processSinchInbound($data);
        }
        return $this->processNexmoInbound($data);
    }

    /**
     * Process Nexmo Inbound SMS
     */
    protected function processNexmoInbound(array $data): bool
    {
        $from = $data['msisdn'] ?? '';
        $to = $data['to'] ?? '';
        $text = $data['text'] ?? $data['message'] ?? '';
        $messageId = $data['messageId'] ?? $data['message-id'] ?? uniqid('nexmo_mo_');
        $keyword = $data['keyword'] ?? null;
        $timestamp = $data['message-timestamp'] ?? null;

        Log::info("[WebhookQueue] Processing Nexmo Inbound SMS", [
            'from' => $from,
            'to' => $to,
            'text' => substr($text, 0, 50)
        ]);

        // Prepare inbound data
        $inboundData = [
            'message_id' => $messageId,
            'source' => $from,
            'dest' => $to,
            'message' => $text,
            'keyword' => $keyword,
            'provider' => 'nexmo',
            'received_at' => $timestamp ? Carbon::parse($timestamp)->toDateTimeString() : Carbon::now()->toDateTimeString(),
            'raw_data' => $data,
        ];

        return $this->storeInboundSms($inboundData);
    }

    /**
     * Process Sinch Inbound SMS
     */
    protected function processSinchInbound(array $data): bool
    {
        $from = ltrim($data['from'] ?? '', '+');
        $to = ltrim($data['to'] ?? '', '+');
        $text = $data['body'] ?? $data['message'] ?? '';
        $messageId = $data['id'] ?? uniqid('sinch_mo_');
        $timestamp = $data['received_at'] ?? null;

        Log::info("[WebhookQueue] Processing Sinch Inbound SMS", [
            'from' => $from,
            'to' => $to,
            'text' => substr($text, 0, 50)
        ]);

        // Prepare inbound data
        $inboundData = [
            'message_id' => $messageId,
            'source' => $from,
            'dest' => $to,
            'message' => $text,
            'provider' => 'sinch',
            'received_at' => $timestamp ? Carbon::parse($timestamp)->toDateTimeString() : Carbon::now()->toDateTimeString(),
            'raw_data' => $data,
        ];

        return $this->storeInboundSms($inboundData);
    }

    /**
     * Store inbound SMS in database
     */
    protected function storeInboundSms(array $data): bool
    {
        try {
            // Try to use InboundSmsProcessor if available
            if ($this->inboundProcessor) {
                return $this->inboundProcessor->process($data);
            }

            // Fallback: Store in smsg_inbound table
            $now = Carbon::now();
            $cleanFrom = preg_replace('/[^0-9]/', '', $data['source'] ?? '');
            $cleanTo = preg_replace('/[^0-9]/', '', $data['dest'] ?? '');

            // Find the shortcode/virtual number owner
            $shortcode = DB::table('smsshortcodes')
                ->where('number', $cleanTo)
                ->orWhere('number', ltrim($cleanTo, '44'))
                ->first();

            $userRef = $shortcode->bigid ?? null;

            // Insert into smsg_inbound
            DB::table('smsg_inbound')->insert([
                'bigid' => md5(uniqid('', true)),
                'userref' => $userRef,
                'mobnum' => $cleanFrom,
                'shortcode' => $cleanTo,
                'text' => $data['message'] ?? '',
                'operator_msgid' => $data['message_id'] ?? '',
                'timereceived' => $now->format('YmdHis'),
                'provider' => $data['provider'] ?? 'unknown',
                'created_at' => $now,
            ]);

            Log::info("[WebhookQueue] Inbound SMS stored", [
                'from' => $cleanFrom,
                'to' => $cleanTo,
                'userref' => $userRef
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("[WebhookQueue] Failed to store inbound SMS", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Process single message and exit
     */
    protected function processOnce(string $type): int
    {
        try {
            $dlrQueue = env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr');
            $inboundQueue = env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound');

            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                env('RABBITMQ_HOST', 'localhost'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                env('RABBITMQ_VHOST', '/')
            );

            $channel = $connection->channel();
            $processed = false;

            // Try DLR queue first if applicable
            if ($type === 'dlr' || $type === 'all') {
                $message = $channel->basic_get($dlrQueue);
                if ($message) {
                    $data = json_decode($message->body, true);
                    $result = $this->processMessage($data, 'dlr');
                    if ($result) {
                        $channel->basic_ack($message->getDeliveryTag());
                    } else {
                        $channel->basic_nack($message->getDeliveryTag(), false, true);
                    }
                    $processed = true;
                }
            }

            // Try inbound queue if no DLR was processed
            if (!$processed && ($type === 'inbound' || $type === 'all')) {
                $message = $channel->basic_get($inboundQueue);
                if ($message) {
                    $data = json_decode($message->body, true);
                    $result = $this->processMessage($data, 'inbound');
                    if ($result) {
                        $channel->basic_ack($message->getDeliveryTag());
                    } else {
                        $channel->basic_nack($message->getDeliveryTag(), false, true);
                    }
                    $processed = true;
                }
            }

            if (!$processed) {
                $this->warn('No messages available in queue');
            }

            $this->showSummary();

            $channel->close();
            $connection->close();

            return $processed && $this->failedCount === 0 ? 0 : 1;
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
        $this->info('=== Webhook Queue Statistics ===');
        $this->newLine();

        try {
            $this->rabbitMQService = new RabbitMQService();
        } catch (Exception $e) {
            $this->error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            return 1;
        }

        $queues = [
            env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr') => 'Webhook DLR',
            env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound') => 'Webhook Inbound',
            env('RABBITMQ_DLR_QUEUE', 'sms.dlr') => 'Legacy DLR',
            env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound') => 'Legacy Inbound',
            'sms.dead' => 'Dead Letter',
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
    protected function showQueueStats(): void
    {
        $dlrQueue = env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr');
        $inboundQueue = env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound');

        $dlrStats = $this->rabbitMQService->getQueueStats($dlrQueue);
        $inboundStats = $this->rabbitMQService->getQueueStats($inboundQueue);

        $this->info("DLR Queue ({$dlrQueue}):");
        $this->line("  Messages: " . ($dlrStats['messages'] ?? 0));
        $this->line("  Consumers: " . ($dlrStats['consumers'] ?? 0));

        $this->info("Inbound Queue ({$inboundQueue}):");
        $this->line("  Messages: " . ($inboundStats['messages'] ?? 0));
        $this->line("  Consumers: " . ($inboundStats['consumers'] ?? 0));
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
