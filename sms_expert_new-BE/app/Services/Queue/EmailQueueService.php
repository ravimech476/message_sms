<?php

namespace App\Services\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class EmailQueueService
{
    protected $connection;
    protected $channel;
    protected $queueName = 'email.notifications';
    protected $exchangeName = 'email.exchange';
    
    public function __construct()
    {
        try {
            // Get RabbitMQ configuration from environment
            $host = env('RABBITMQ_HOST', '127.0.0.1');
            $port = env('RABBITMQ_PORT', 5672);
            $user = env('RABBITMQ_USER', 'guest');
            $password = env('RABBITMQ_PASSWORD', 'guest');
            $vhost = env('RABBITMQ_VHOST', '/');
            
            // Create connection
            $this->connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost
            );
            
            $this->channel = $this->connection->channel();
            
            // Try to declare the queue with basic settings first
            try {
                // Declare queue with basic settings (no x-message-ttl or x-max-priority)
                $this->channel->queue_declare(
                    $this->queueName,
                    false,  // passive
                    true,   // durable
                    false,  // exclusive
                    false,  // auto_delete
                    false   // nowait
                    // Remove arguments to avoid conflicts
                );
            } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
                if (strpos($e->getMessage(), 'PRECONDITION_FAILED') !== false) {
                    // Queue exists with different parameters, just use it as is
                    Log::info('Queue already exists, using existing configuration');
                    // Recreate channel after exception
                    $this->channel = $this->connection->channel();
                } else {
                    throw $e;
                }
            }
            
            // Declare exchange
            $this->channel->exchange_declare(
                $this->exchangeName,
                'direct',
                false,  // passive
                true,   // durable
                false   // auto_delete
            );
            
            // Bind queue to exchange
            $this->channel->queue_bind($this->queueName, $this->exchangeName, 'email');
            
        } catch (\Exception $e) {
            Log::error('Failed to initialize EmailQueueService: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Queue an email for sending
     *
     * @param string $mailableClass
     * @param string|array $recipient
     * @param array $data
     * @param array $ccRecipients
     * @param int $priority
     * @return bool
     */
    public function queueEmail(string $mailableClass, $recipient, array $data = [], array $ccRecipients = [], int $priority = 5): bool
    {
        try {
            $messageBody = json_encode([
                'mailable_class' => $mailableClass,
                'recipient' => $recipient,
                'data' => $data,
                'cc_recipients' => $ccRecipients,
                'queued_at' => now()->toIso8601String()
            ]);
            
            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => $priority,
                'timestamp' => time(),
                'content_type' => 'application/json'
            ]);
            
            $this->channel->basic_publish(
                $message,
                $this->exchangeName,
                'email'
            );
            
            Log::info('Email queued successfully', [
                'mailable' => $mailableClass,
                'recipient' => is_array($recipient) ? $recipient[0] : $recipient,
                'priority' => $priority
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to queue email: ' . $e->getMessage(), [
                'mailable' => $mailableClass,
                'recipient' => is_array($recipient) ? $recipient[0] : $recipient
            ]);
            return false;
        }
    }
    
    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getQueueStats(): array
    {
        try {
            list($queue, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $this->queueName,
                true,   // passive - just check queue exists
                false,
                false,
                false
            );
            
            return [
                'queue_name' => $queue,
                'messages' => $messageCount,
                'consumers' => $consumerCount
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get queue stats: ' . $e->getMessage());
            return [
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Close connections
     */
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
            Log::error('Error closing RabbitMQ connection: ' . $e->getMessage());
        }
    }
}
