<?php

namespace App\Services\Queue;

use App\Services\Queue\RabbitMQService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * Webhook Queue Service
 *
 * Handles queuing of incoming webhooks (DLR and Inbound SMS) to RabbitMQ
 * for background processing.
 */
class WebhookQueueService
{
    protected RabbitMQService $rabbitMQService;

    // Queue names
    const QUEUE_DLR = 'webhook.dlr';
    const QUEUE_INBOUND = 'webhook.inbound';

    public function __construct()
    {
        $this->rabbitMQService = app(RabbitMQService::class);
    }

    /**
     * Queue a DLR (Delivery Receipt) for background processing
     */
    public function queueDlr(array $data, string $provider, string $requestId): bool
    {
        $queueData = [
            'queue_id' => 'dlr_' . $requestId,
            'type' => 'dlr',
            'provider' => $provider,
            'request_id' => $requestId,
            'data' => $data,
            'received_at' => Carbon::now()->toIso8601String(),
            'queued_at' => Carbon::now()->toIso8601String(),
        ];

        $queueName = env('RABBITMQ_WEBHOOK_DLR_QUEUE', self::QUEUE_DLR);

        Log::info("[WebhookQueue] Queuing DLR", [
            'queue_id' => $queueData['queue_id'],
            'provider' => $provider,
            'queue' => $queueName
        ]);

        return $this->rabbitMQService->publishToQueue($queueName, $queueData, 7); // High priority for DLR
    }

    /**
     * Queue an Inbound SMS for background processing
     */
    public function queueInboundSms(array $data, string $provider, string $requestId): bool
    {
        $queueData = [
            'queue_id' => 'inbound_' . $requestId,
            'type' => 'inbound',
            'provider' => $provider,
            'request_id' => $requestId,
            'data' => $data,
            'received_at' => Carbon::now()->toIso8601String(),
            'queued_at' => Carbon::now()->toIso8601String(),
        ];

        $queueName = env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', self::QUEUE_INBOUND);

        Log::info("[WebhookQueue] Queuing Inbound SMS", [
            'queue_id' => $queueData['queue_id'],
            'provider' => $provider,
            'queue' => $queueName
        ]);

        return $this->rabbitMQService->publishToQueue($queueName, $queueData, 5); // Normal priority
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        return [
            'dlr' => $this->rabbitMQService->getQueueStats(env('RABBITMQ_WEBHOOK_DLR_QUEUE', self::QUEUE_DLR)),
            'inbound' => $this->rabbitMQService->getQueueStats(env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', self::QUEUE_INBOUND)),
        ];
    }
}
