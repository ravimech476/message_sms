<?php

namespace App\Services\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class CampaignFileMigrationQueueService
{
    private $connection;
    private $channel;
    private $queueName = 'campaign.file.migration';

    public function __construct()
    {
        $this->connect();
    }

    /**
     * Connect to RabbitMQ
     */
    private function connect()
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

            // Declare the queue
            $this->channel->queue_declare(
                $this->queueName,
                false,  // passive
                true,   // durable
                false,  // exclusive
                false   // auto_delete
            );

        } catch (\Exception $e) {
            Log::error('Campaign File Migration Queue Connection Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Queue a batch migration job
     */
    public function queueBatchMigration(array $data): array
    {
        try {
            $messageBody = json_encode([
                'type' => 'batch_migration',
                'batch_id' => $data['batch_id'],
                'direction' => $data['direction'],
                'user_bigids' => $data['user_bigids'],
                'queued_at' => now()->toIso8601String(),
            ]);

            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            $this->channel->basic_publish($message, '', $this->queueName);

            Log::info('Campaign file migration batch queued', [
                'batch_id' => $data['batch_id'],
                'direction' => $data['direction'],
                'user_count' => count($data['user_bigids']),
            ]);

            return [
                'success' => true,
                'batch_id' => $data['batch_id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to queue campaign file migration batch', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Queue a single file migration job
     */
    public function queueFileMigration(array $data): array
    {
        try {
            $messageBody = json_encode([
                'type' => 'file_migration',
                'migration_id' => $data['migration_id'],
                'batch_id' => $data['batch_id'],
                'user_bigid' => $data['user_bigid'],
                'direction' => $data['direction'],
                'filename' => $data['filename'],
                'source_path' => $data['source_path'],
                'destination_path' => $data['destination_path'],
                'queued_at' => now()->toIso8601String(),
            ]);

            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
            ]);

            $this->channel->basic_publish($message, '', $this->queueName);

            return [
                'success' => true,
                'migration_id' => $data['migration_id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to queue campaign file migration', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue depth
     */
    public function getQueueDepth(): int
    {
        try {
            list($queue, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $this->queueName,
                true,   // passive - just check
                false,
                false,
                false
            );
            return $messageCount;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Close connection
     */
    public function close()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            // Ignore close errors
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
