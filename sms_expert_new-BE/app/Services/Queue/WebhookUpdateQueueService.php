<?php

namespace App\Services\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

/**
 * Publishes per-customer Nexmo virtual-number webhook-update tasks onto
 * RabbitMQ. The consumer command (rabbitmq:consume-webhook-update) does the
 * actual Nexmo API call for each number that belongs to the customer.
 *
 * Used by AdminUserController::bulkMigrate so the bulk-migrate response
 * returns immediately and the (potentially slow) Nexmo API calls run on
 * the background worker fleet.
 */
class WebhookUpdateQueueService
{
    private $connection;
    private $channel;
    private $queueName = 'nexmo.webhook.update';

    public function __construct()
    {
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $host = env('RABBITMQ_HOST', '127.0.0.1');
            $port = env('RABBITMQ_PORT', 5672);
            $user = env('RABBITMQ_USER', 'guest');
            $password = env('RABBITMQ_PASSWORD', 'guest');
            $vhost = env('RABBITMQ_VHOST', '/');

            $this->connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost
            );

            $this->channel = $this->connection->channel();

            $this->channel->queue_declare(
                $this->queueName,
                false,  // passive
                true,   // durable — survives RabbitMQ restart
                false,  // exclusive
                false   // auto_delete
            );
        } catch (\Exception $e) {
            Log::error('Webhook Update Queue Connection Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enqueue a webhook-URL update for every Nexmo virtual number owned
     * by the given customer. The consumer resolves the customer's
     * numbers and hits Nexmo once per number.
     */
    public function queueCustomerUpdate(string $userBigid, string $webhookUrl): array
    {
        try {
            $messageBody = json_encode([
                'type'        => 'update_mohttp',
                'user_bigid'  => $userBigid,
                'webhook_url' => $webhookUrl,
                'queued_at'   => now()->toIso8601String(),
            ]);

            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type'  => 'application/json',
            ]);

            $this->channel->basic_publish($message, '', $this->queueName);

            Log::info('Nexmo webhook update queued', [
                'user_bigid'  => $userBigid,
                'webhook_url' => $webhookUrl,
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Failed to queue Nexmo webhook update', [
                'user_bigid' => $userBigid,
                'error'      => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function __destruct()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            // ignore — destructor must not throw
        }
    }
}
