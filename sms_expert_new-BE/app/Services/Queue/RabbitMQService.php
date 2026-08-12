<?php

namespace App\Services\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class RabbitMQService
{
    private $connection;
    private $channel;
    private $connected = false;
    private $maxRetries = 3; // Maximum retry attempts
    private static $skipConnection = false; // Flag to skip connection during migrations

    public function __construct()
    {
        // Skip connection during migrations
        if (self::$skipConnection || $this->isRunningMigration()) {
            return;
        }
        
        $this->maxRetries = env('RABBITMQ_MAX_RETRIES', 3);
        
        try {
            $this->connect();
        } catch (Exception $e) {
            // Log but don't throw during construction
            Log::warning('RabbitMQ connection failed during construction: ' . $e->getMessage());
            $this->connected = false;
        }
    }

    /**
     * Publish delayed message to queue using RabbitMQ delayed message plugin or TTL
     * @param string $queue Queue name
     * @param array $data Message data
     * @param int $delayMs Delay in milliseconds
     * @param int $priority Message priority
     */
    public function publishDelayedMessage($queue, $data, $delayMs, $priority = 5)
    {
        // Skip if we're in migration mode
        if (self::$skipConnection || $this->isRunningMigration()) {
            return false;
        }

        $this->ensureConnection();
        
        if (!$this->connected) {
            Log::warning('Cannot publish delayed message - RabbitMQ not connected');
            return false;
        }

        try {
            // Create a temporary delayed queue for this message
            $delayedQueueName = $queue . '.delayed.' . uniqid();
            
            // Declare delayed queue with TTL that expires to the target queue
            $this->channel->queue_declare(
                $delayedQueueName,
                false, // passive
                false, // durable (temporary queue)
                false, // exclusive
                true,  // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-message-ttl' => (int)$delayMs, // TTL in milliseconds
                    'x-expires' => (int)$delayMs + 60000, // Queue expires 1 minute after message
                    'x-dead-letter-exchange' => '', // Default exchange
                    'x-dead-letter-routing-key' => $queue // Route to target queue when TTL expires
                ])
            );
            
            // Prepare message
            $messageBody = json_encode($data);
            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => min(max($priority, 1), 10),
                'timestamp' => time(),
                'content_type' => 'application/json'
            ]);
            
            // Publish to the delayed queue
            $this->channel->basic_publish(
                $message,
                '', // Default exchange
                $delayedQueueName
            );
            
            Log::info("Delayed message published", [
                'queue' => $queue,
                'delayed_queue' => $delayedQueueName,
                'delay_ms' => $delayMs,
                'delay_seconds' => round($delayMs / 1000, 2),
                'priority' => $priority,
                'scheduled_delivery' => Carbon::now()->addMilliseconds($delayMs)->toDateTimeString()
            ]);
            \App\Services\Logging\RabbitMQLogService::for($queue)->info(
                'PUBLISH (delayed) — message scheduled in ' . round($delayMs / 1000, 2) . 's',
                array_merge($this->summarizeForLog($data), [
                    'delay_seconds'       => round($delayMs / 1000, 2),
                    'scheduled_delivery'  => Carbon::now()->addMilliseconds($delayMs)->toDateTimeString(),
                ])
            );

            return true;
            
        } catch (Exception $e) {
            Log::error('Failed to publish delayed message', [
                'error' => $e->getMessage(),
                'queue' => $queue,
                'delay_ms' => $delayMs
            ]);
            return false;
        }
    }

    /**
     * Check if we're running migrations
     */
    private function isRunningMigration()
    {
        // Check if we're in console and running migrate command
        if (php_sapi_name() === 'cli') {
            global $argv;
            if (isset($argv) && is_array($argv)) {
                foreach ($argv as $arg) {
                    if (strpos($arg, 'migrate') !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Set skip connection flag (useful for migrations)
     */
    public static function skipConnection($skip = true)
    {
        self::$skipConnection = $skip;
    }

    /**
     * Connect to RabbitMQ
     */
    public function connect()
    {
        // Skip if already connected or if we should skip
        if ($this->connected || self::$skipConnection || $this->isRunningMigration()) {
            return;
        }

        try {
            $this->connection = new AMQPStreamConnection(
                config('rabbitmq.host', env('RABBITMQ_HOST', 'localhost')),
                config('rabbitmq.port', env('RABBITMQ_PORT', 5672)),
                config('rabbitmq.user', env('RABBITMQ_USER', 'guest')),
                config('rabbitmq.password', env('RABBITMQ_PASSWORD', 'guest')),
                config('rabbitmq.vhost', env('RABBITMQ_VHOST', '/'))
            );
            
            $this->channel = $this->connection->channel();
            $this->connected = true;
            
            $this->declareQueues();
            
            Log::info('RabbitMQ connected successfully');
        } catch (Exception $e) {
            Log::error('RabbitMQ connection failed: ' . $e->getMessage());
            $this->connected = false;
            throw $e;
        }
    }

    /**
     * Declare all required queues with proper dead letter exchange
     */
    private function declareQueues()
    {
        try {
            // Create dead letter exchange first
            $this->channel->exchange_declare(
                'sms.dlx',
                'direct',
                false,  // passive - don't check if it exists
                true,   // durable
                false   // auto_delete
            );

            // SMS Outbound Queue with priorities and dead letter exchange
            $this->channel->queue_declare(
                env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Dead Letter Queue for permanently failed messages
            $this->channel->queue_declare(
                'sms.dead',
                false, 
                true, 
                false, 
                false
            );
            
            // Bind dead letter queue to exchange
            $this->channel->queue_bind('sms.dead', 'sms.dlx', 'sms.dead');

            // DLR Queue
            $this->channel->queue_declare(
                env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
                false, true, false, false
            );

            // Failed Queue with retry logic (temporary failures)
            $this->channel->queue_declare(
                env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
                false, true, false, false,
                false,
                new AMQPTable([
                    'x-message-ttl' => 300000, // 5 minutes before retry
                    'x-dead-letter-exchange' => '',
                    'x-dead-letter-routing-key' => env('RABBITMQ_SMS_QUEUE', 'sms.outbound')
                ])
            );

            // Priority Queue for urgent messages
            $this->channel->queue_declare(
                env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority'),
                false, true, false, false,
                false,
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Inbound SMS Queue for received messages
            $this->channel->queue_declare(
                env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 604800000, // 7 days in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Campaign Processing Queue for bulk file processing
            $this->channel->queue_declare(
                env('RABBITMQ_CAMPAIGN_QUEUE', 'campaign.process'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // DLR Callback Push Queue — drives dlr-callback:consume worker.
            // 7-day TTL because customer DLR webhooks may retry over hours/days
            // (wait_minutes * retries_left); DB row is source of truth, queue
            // message is the wakeup. Dead-letter to sms.dead for visibility.
            $this->channel->queue_declare(
                env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-message-ttl' => 604800000, // 7 days
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead',
                ])
            );

            // Email Notifications Queue for sending emails
            // Declare without extra args to match existing queue
            try {
                $this->channel->queue_declare(
                    'email.notifications',
                    false, // passive
                    true,  // durable
                    false, // exclusive
                    false  // auto_delete
                );
            } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
                // Queue exists with different params, reconnect channel and continue
                if (strpos($e->getMessage(), 'PRECONDITION_FAILED') !== false) {
                    $this->channel = $this->connection->channel();
                    Log::info('Using existing email.notifications queue configuration');
                } else {
                    throw $e;
                }
            }

            // Nexmo Delivery Reports Queue for processing delivery status updates
            $this->channel->queue_declare(
                env('RABBITMQ_NEXMO_DELIVERY_QUEUE', 'nexmo.delivery.reports'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Push Notifications Queue for mobile app push notifications
            $this->channel->queue_declare(
                env('RABBITMQ_PUSH_QUEUE', 'push.notifications'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Webhook DLR Queue for delivery receipts from Nexmo/Sinch
            $this->channel->queue_declare(
                env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Webhook Inbound Queue for inbound SMS from Nexmo/Sinch
            $this->channel->queue_declare(
                env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-max-priority' => 10,
                    'x-message-ttl' => 604800000, // 7 days in milliseconds
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );

            // Report generation queue (admin async reports -> reports:consume worker)
            $this->channel->queue_declare(
                env('RABBITMQ_REPORTS_QUEUE', 'reports.generate'),
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                new AMQPTable([
                    'x-message-ttl' => 86400000, // 24 hours
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ])
            );
        } catch (Exception $e) {
            Log::error('Failed to declare queues: ' . $e->getMessage());
            // Don't throw during queue declaration if it fails
            // This allows the system to work even if RabbitMQ is temporarily unavailable
        }
    }

    /**
     * Ensure connection before operations
     */
    private function ensureConnection()
    {
        if (!$this->connected && !self::$skipConnection && !$this->isRunningMigration()) {
            $this->connect();
        }
    }

    /**
     * Return pending-message + consumer counts for each queue (topic), for the
     * admin "Queues" monitor tab. Uses a PASSIVE queue_declare so it only reads
     * counts and never creates or mutates a queue.
     *
     * @param array<string,string> $queues  label => queueName
     * @return array<int,array{label:string,queue:string,messages:int,consumers:int,exists:bool}>
     */
    public function getQueuesStatus(array $queues): array
    {
        $this->ensureConnection();

        if (!$this->connected || !$this->channel) {
            throw new Exception('RabbitMQ is not connected');
        }

        $results = [];
        foreach ($queues as $label => $name) {
            try {
                // passive = true -> returns [queueName, messageCount, consumerCount]
                $info = $this->channel->queue_declare($name, true);
                $results[] = [
                    'label'     => $label,
                    'queue'     => $name,
                    'messages'  => isset($info[1]) ? (int) $info[1] : 0,
                    'consumers' => isset($info[2]) ? (int) $info[2] : 0,
                    'exists'    => true,
                ];
            } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
                // A passive declare on a missing queue closes the channel — reopen it
                // so the remaining queues can still be checked.
                try {
                    $this->channel = $this->connection->channel();
                } catch (\Throwable $re) {
                    // give up on the rest if we can't get a channel back
                    $results[] = ['label' => $label, 'queue' => $name, 'messages' => 0, 'consumers' => 0, 'exists' => false];
                    break;
                }
                $results[] = ['label' => $label, 'queue' => $name, 'messages' => 0, 'consumers' => 0, 'exists' => false];
            }
        }

        return $results;
    }

    /**
     * Browse up to $limit messages in a queue WITHOUT consuming them, for the admin
     * "Queues" tab. Unlike peekMessages(), it collects all messages first and requeues
     * them only at the end (basic_nack requeue=true) so it returns DISTINCT messages
     * rather than the same head message repeatedly. Side effect: browsed messages get
     * their redelivered flag set — acceptable for a read-only admin view.
     *
     * @return array<int,array{summary:array,payload:mixed,redelivered:bool}>
     */
    public function browseQueueMessages(string $queue, int $limit = 20): array
    {
        $this->ensureConnection();

        if (!$this->connected || !$this->channel) {
            throw new Exception('RabbitMQ is not connected');
        }

        $limit = max(1, min($limit, 50));
        $messages = [];
        $tags = [];

        try {
            for ($i = 0; $i < $limit; $i++) {
                $msg = $this->channel->basic_get($queue, false); // no_ack=false -> stays unacked
                if (!$msg) {
                    break;
                }
                $tags[] = $msg->getDeliveryTag();
                $decoded = json_decode($msg->body, true);
                $messages[] = [
                    'summary'     => is_array($decoded) ? $this->summarizeForLog($decoded) : ['raw' => mb_substr((string) $msg->body, 0, 200)],
                    'payload'     => is_array($decoded) ? $decoded : (string) $msg->body,
                    'redelivered' => (bool) ($msg->delivery_info['redelivered'] ?? false),
                ];
            }
        } finally {
            // Requeue everything we pulled, in one pass, so the queue is left intact.
            foreach ($tags as $tag) {
                try {
                    $this->channel->basic_nack($tag, false, true);
                } catch (\Throwable $e) {
                    // ignore — channel close will requeue any stragglers
                }
            }
        }

        return $messages;
    }

    /**
     * Publish message to queue with retry count
     */
    /**
     * Build a compact, human-readable summary of a queue payload for logging.
     *
     * Pulls only known identifying fields (never the full message body / SMS text
     * / email html) so each per-queue log line clearly shows WHAT was processed —
     * message id, mobile, status, campaign, recipient, etc. Also records the full
     * top-level key set as `payload_keys` so payload shape is visible even when
     * none of the curated keys are present.
     */
    /**
     * Recursively coerce every string in a payload to valid UTF-8 so json_encode()
     * can never fail (and silently publish an empty body). Legacy SMS text is often
     * stored Windows-1252 encoded (£ = 0xA3, smart quotes, etc.) — those bytes are
     * invalid UTF-8. Only non-UTF-8 strings are converted, so clean payloads are
     * untouched and multi-byte UTF-8 is preserved.
     */
    private function deepUtf8($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'deepUtf8'], $value);
        }
        if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        return $value;
    }

    private function summarizeForLog($data): array
    {
        if (!is_array($data)) {
            return ['payload' => 'unparseable'];
        }

        // Curated identifying keys (priority order) covering every queue we run.
        $keysOfInterest = [
            'queue_id', 'id', 'row_id', 'type', 'action',
            'message_id', 'messageId', 'sms_id', 'bigid', 'queue_message_id',
            'mobile', 'msisdn', 'to', 'dest', 'recipient',
            'sender', 'source', 'from',
            'status', 'deliverystatus', 'deliverystatus2', 'network',
            'mailable', 'subject', 'email',
            'campaign_id', 'campaign_file_id', 'file_id', 'batch', 'batch_size', 'total',
            'number', 'country', 'mo_http_url', 'url',
            'user_id', 'customer_id', 'count',
        ];

        $summary = [];
        foreach ($keysOfInterest as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '' && is_scalar($data[$k])) {
                $val = (string) $data[$k];
                if (strlen($val) > 120) {
                    $val = substr($val, 0, 117) . '...';
                }
                $summary[$k] = $val;
            }
        }

        $summary['payload_keys'] = implode(',', array_keys($data));

        return $summary;
    }

    public function publishToQueue($queue, $data, $priority = 5, $retryCount = 0)
    {
        // Skip if we're in migration mode
        if (self::$skipConnection || $this->isRunningMigration()) {
            return false;
        }

        $this->ensureConnection();
        
        if (!$this->connected) {
            Log::warning('Cannot publish to queue - RabbitMQ not connected');
            return false;
        }

        try {
            // Add retry count to data
            $data['retry_count'] = $retryCount;
            $data['max_retries'] = $this->maxRetries;
            $data['queued_at'] = $data['queued_at'] ?? Carbon::now()->toIso8601String();
            
            $messageBody = json_encode($data);
            if ($messageBody === false) {
                // A value (typically legacy SMS text stored Windows-1252 encoded,
                // e.g. £ = 0xA3) isn't valid UTF-8, so json_encode() returned false.
                // Sanitize to UTF-8 and retry. This MUST NOT publish an empty body:
                // the consumer json_decodes '' to null, logs it "unparseable" and
                // ACKs it away WITHOUT SENDING — the exact reason re-queued pending
                // SMS silently vanished instead of reaching Nexmo.
                $data = $this->deepUtf8($data);
                $messageBody = json_encode($data);
            }
            if (!is_string($messageBody) || $messageBody === '' || $messageBody === 'null') {
                Log::error('publishToQueue: refusing to publish empty/invalid body', [
                    'queue'    => $queue,
                    'queue_id' => $data['queue_id'] ?? null,
                    'json_err' => json_last_error_msg(),
                ]);
                return false;
            }

            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => $priority,
                'timestamp' => time(),
                'content_type' => 'application/json',
                'expiration' => '86400000' // Message expires after 24 hours
            ]);

            $this->channel->basic_publish($message, '', $queue);

            Log::info("Message published to queue: {$queue}", [
                'queue_id' => $data['queue_id'] ?? null,
                'retry_count' => $retryCount
            ]);
            \App\Services\Logging\RabbitMQLogService::for($queue)->info(
                'PUBLISH — message enqueued',
                array_merge($this->summarizeForLog($data), ['retry_count' => $retryCount])
            );

            return true;
        } catch (Exception $e) {
            Log::error('Failed to publish message to queue: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Non-blocking single-message pull (basic_get) for poll-style consumers such
     * as the persistent SMPP transceiver, which interleave reading DLRs off the
     * SMPP socket with draining queued sends. Returns the raw AMQPMessage (still
     * UNACKED) or null when the queue is empty. Caller MUST ackMessage()/nackMessage().
     *
     * @return \PhpAmqpLib\Message\AMQPMessage|null
     */
    public function getNextMessage($queue)
    {
        if (self::$skipConnection || $this->isRunningMigration()) {
            return null;
        }

        $this->ensureConnection();
        if (!$this->connected || !$this->channel) {
            return null;
        }

        try {
            // no_ack=false -> message stays unacked until we ack/nack it.
            return $this->channel->basic_get($queue, false);
        } catch (Exception $e) {
            Log::warning("getNextMessage failed for queue {$queue}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Acknowledge a message pulled via getNextMessage() (removes it from the queue).
     */
    public function ackMessage($message): void
    {
        try {
            if ($message && $this->channel) {
                $this->channel->basic_ack($message->getDeliveryTag());
            }
        } catch (Exception $e) {
            Log::warning('ackMessage failed: ' . $e->getMessage());
        }
    }

    /**
     * Negative-ack a message pulled via getNextMessage(). $requeue=true puts it
     * back on the queue for another attempt.
     */
    public function nackMessage($message, bool $requeue = true): void
    {
        try {
            if ($message && $this->channel) {
                $this->channel->basic_nack($message->getDeliveryTag(), false, $requeue);
            }
        } catch (Exception $e) {
            Log::warning('nackMessage failed: ' . $e->getMessage());
        }
    }

    /**
     * Consume messages from queue with proper acknowledgment
     */
    public function consumeFromQueue($queue, $callback, $prefetchCount = 1, $idleCallback = null)
    {
        // Skip if we're in migration mode
        if (self::$skipConnection || $this->isRunningMigration()) {
            return;
        }

        $this->ensureConnection();

        if (!$this->connected) {
            throw new Exception('Cannot consume from queue - RabbitMQ not connected');
        }

        try {
            // Set QoS - process one message at a time
            $this->channel->basic_qos(null, $prefetchCount, null);

            // Register consumer
            $this->channel->basic_consume(
                $queue,
                '',     // consumer tag
                false,  // no local
                false,  // no ack - manual acknowledgment
                false,  // exclusive
                false,  // no wait
                function ($message) use ($callback, $queue) {
                    $this->processMessage($message, $callback, $queue);
                }
            );

            // Wait for messages with periodic timeout for idle callback (e.g., DLR checking)
            while ($this->channel->is_consuming()) {
                try {
                    // Wait with 1 second timeout to allow periodic callbacks
                    $this->channel->wait(null, false, 1);
                } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                    // Timeout is expected - call idle callback if provided
                    if ($idleCallback && is_callable($idleCallback)) {
                        $idleCallback();
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to consume from queue: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single message with retry logic
     */
    private function processMessage($message, $callback, $queue)
    {
        $data = null;
        $qlog = \App\Services\Logging\RabbitMQLogService::for($queue);

        try {
            $data = json_decode($message->body, true);
            $retryCount = $data['retry_count'] ?? 0;
            $maxRetries = $data['max_retries'] ?? $this->maxRetries;

            $logContext = [
                'queue'       => $queue,
                'queue_id'    => $data['queue_id'] ?? null,
                'retry_count' => $retryCount,
                'max_retries' => $maxRetries,
            ];
            Log::info("Processing message from queue", $logContext);
            $qlog->info("PROCESSING — message received from queue", array_merge($this->summarizeForLog($data), [
                'retry_count' => $retryCount,
                'max_retries' => $maxRetries,
            ]));

            // Call the callback to process the message
            $result = $callback($data);

            if ($result === true) {
                // Success - acknowledge and remove from queue
                $message->ack();

                // Update status in database
                if (isset($data['queue_id'])) {
                    $this->updateMessageStatus($data['queue_id'], 'processed', 'Message processed successfully');
                }

                Log::info("Message processed and acknowledged", [
                    'queue_id' => $data['queue_id'] ?? null
                ]);
                $qlog->info("ACK — processed OK, removed from queue", $this->summarizeForLog($data));
            } elseif ($result === 'stop') {
                // 'stop' signal - acknowledge message and stop consuming
                $message->ack();

                Log::info("Batch processing stopped - message acknowledged", [
                    'queue_id' => $data['queue_id'] ?? null
                ]);

                // Cancel consumer to stop the while loop
                if ($this->channel) {
                    $this->channel->basic_cancel('');
                }
            } else {
                // Processing failed - check retry count
                $qlog->warning("Consumer returned false — handing to retry pipeline", array_merge($this->summarizeForLog($data), [
                    'retry_count' => $retryCount,
                ]));
                $this->handleFailedMessage($message, $data, $retryCount, $maxRetries, $queue);
            }
        } catch (Exception $e) {
            Log::error("Error processing message: " . $e->getMessage(), [
                'queue_id' => isset($data['queue_id']) ? $data['queue_id'] : null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $qlog->error("Exception in consumer callback — message NOT acknowledged", array_merge($this->summarizeForLog($data), [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]));

            // Email-alert any consumer exception. Universal — applies to every
            // consumer using consumeFromQueue(). Throttled per-subject so a
            // 100-msg burst of the same failure = ONE email, not 100.
            \App\Services\SMPP\SmppErrorAlertService::notifyTransient(
                "Queue processing error: {$queue}",
                "An exception was thrown while processing a message from queue '{$queue}'. The message will NOT be acknowledged — RabbitMQ will retry it with exponential backoff up to RABBITMQ_MAX_RETRIES, then dead-letter. Investigate the underlying error to prevent message loss.",
                [
                    'queue'         => $queue,
                    'queue_id'      => $data['queue_id'] ?? null,
                    'retry_count'   => $data['retry_count'] ?? 0,
                    'max_retries'   => $data['max_retries'] ?? $this->maxRetries,
                    'error'         => $e->getMessage(),
                    'error_file'    => $e->getFile() . ':' . $e->getLine(),
                ]
            );

            // Handle exception - check retry count
            if ($data) {
                $this->handleFailedMessage($message, $data, $data['retry_count'] ?? 0, $data['max_retries'] ?? $this->maxRetries, $queue, $e->getMessage());
            } else {
                // Permanent failure - can't even parse the message
                $message->ack(); // Remove from queue to prevent infinite loop
                Log::error("Message permanently failed - invalid format");
            }
        }
    }

    /**
     * Handle failed message with retry logic
     */
    private function handleFailedMessage($message, $data, $retryCount, $maxRetries, $queue, $error = null)
    {
        $qlog = \App\Services\Logging\RabbitMQLogService::for($queue);

        // Determine the appropriate failed queue based on the source queue
        $isCampaignQueue = $queue === 'campaign.process' ||
                           (isset($data['queue_id']) && str_starts_with($data['queue_id'], 'campaign_'));

        if ($retryCount >= $maxRetries) {
            // Max retries reached - acknowledge message to remove from queue and send to dead letter
            $message->ack();

            // Store in dead letter table or handle permanent failure
            $this->handlePermanentFailure($data, $error);

            Log::warning("Message permanently failed after {$maxRetries} retries", [
                'queue_id' => $data['queue_id'] ?? null,
                'queue' => $queue,
                'error' => $error
            ]);
            $qlog->error("DEAD-LETTER — message exhausted {$maxRetries} retries", array_merge($this->summarizeForLog($data), [
                'error' => $error,
            ]));

            // Permanent-failure alert. Throttled per-queue so even a burst of
            // dead-letters generates one email per cooldown window. Different
            // subject from the transient-error alert so operators can see both
            // "started failing" and "gave up" events independently.
            \App\Services\SMPP\SmppErrorAlertService::notifyTransient(
                "Queue message dead-lettered: {$queue}",
                "A message has reached {$maxRetries} retries on queue '{$queue}' and is being dead-lettered. The original message is preserved in the failure table for inspection but will no longer be retried automatically. Investigate why processing has been failing repeatedly.",
                [
                    'queue'       => $queue,
                    'queue_id'    => $data['queue_id'] ?? null,
                    'retry_count' => $retryCount,
                    'max_retries' => $maxRetries,
                    'error'       => $error,
                    'queued_at'   => $data['queued_at'] ?? null,
                ]
            );
        } else {
            // Acknowledge current message to remove from queue
            $message->ack();

            // Increment retry count
            $retryCount++;

            // Calculate delay based on retry count (exponential backoff)
            $delaySeconds = min(300, pow(2, $retryCount) * 10); // Max 5 minutes

            $qlog->warning("RETRY scheduled (attempt {$retryCount}/{$maxRetries}) in {$delaySeconds}s", array_merge($this->summarizeForLog($data), [
                'error' => $error,
            ]));
            
            // Update status in database
            if (isset($data['queue_id'])) {
                $this->updateMessageStatus($data['queue_id'], 'retrying', "Retry attempt {$retryCount} of {$maxRetries}", $retryCount);
            }
            
            // Re-publish to appropriate queue for retry.
            // A message must retry on ITS OWN queue's consumer — never cross pipelines.
            $isDlrQueue = str_contains(strtolower($queue), 'dlr')
                || $queue === 'nexmo.delivery.reports';

            if ($isCampaignQueue) {
                // Re-publish directly back to campaign queue with delay
                $this->publishToQueueWithDelay($queue, $data, $retryCount, $delaySeconds * 1000);
                Log::info("Campaign message scheduled for retry on campaign queue", [
                    'queue_id' => $data['queue_id'] ?? null,
                    'retry_count' => $retryCount,
                    'delay_seconds' => $delaySeconds
                ]);
            } elseif ($isDlrQueue) {
                // DLR failures (typically "no matching smsg_log record") must NOT go to the
                // SMS failed queue — that re-injects the DLR into sms.outbound where the SEND
                // worker rejects it as "Missing required fields" (a DLR has no 'message').
                // Retry on the DLR queue itself; unmatched DLRs dead-letter after maxRetries.
                $this->publishToQueueWithDelay($queue, $data, $retryCount, $delaySeconds * 1000);
                Log::info("DLR scheduled for retry on DLR queue", [
                    'queue' => $queue,
                    'message_id' => $data['message_id'] ?? null,
                    'retry_count' => $retryCount,
                    'delay_seconds' => $delaySeconds
                ]);
            } else {
                // SMS messages go to failed queue which routes back to SMS queue
                $this->publishToFailedQueue($data, $retryCount);
                Log::info("SMS message scheduled for retry", [
                    'queue_id' => $data['queue_id'] ?? null,
                    'retry_count' => $retryCount,
                    'delay_seconds' => $delaySeconds
                ]);
            }
        }
    }

    /**
     * Publish message to queue with delay (for retry)
     */
    private function publishToQueueWithDelay($queue, $data, $retryCount, $delayMs)
    {
        if (!$this->connected) {
            return false;
        }

        try {
            $data['retry_count'] = $retryCount;
            $data['retry_at'] = Carbon::now()->addMilliseconds($delayMs)->toIso8601String();
            
            // Use delayed message mechanism
            return $this->publishDelayedMessage($queue, $data, $delayMs, $data['priority'] ?? 5);
        } catch (Exception $e) {
            Log::error('Failed to publish delayed retry message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Publish message to failed queue for retry
     */
    private function publishToFailedQueue($data, $retryCount)
    {
        if (!$this->connected) {
            return;
        }

        try {
            $data['retry_count'] = $retryCount;
            $data['retry_at'] = Carbon::now()->addSeconds(pow(2, $retryCount) * 10)->toIso8601String();
            
            $messageBody = json_encode($data);
            
            // Calculate TTL for exponential backoff
            $ttl = min(300000, pow(2, $retryCount) * 10000); // Max 5 minutes in milliseconds
            
            $message = new AMQPMessage($messageBody, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => 5,
                'timestamp' => time(),
                'content_type' => 'application/json',
                'expiration' => strval($ttl)
            ]);
            
            $this->channel->basic_publish($message, '', env('RABBITMQ_FAILED_QUEUE', 'sms.failed'));
            
        } catch (Exception $e) {
            Log::error('Failed to publish to retry queue: ' . $e->getMessage());
        }
    }

    /**
     * Handle permanent failure
     */
    private function handlePermanentFailure($data, $error = null)
    {
        try {
            // Update message status in database
            if (isset($data['queue_id'])) {
                $this->updateMessageStatus($data['queue_id'], 'failed', $error ?? 'Max retries exceeded');
            }
            
            // NOTE: the legacy/OLD SYSTEM database has no sms_dead_letter table and
            // we do not want one. Permanent failures are tracked via updateMessageStatus()
            // above and the smsg_log update below; just log the dead letter for the record.
            Log::warning('SMS permanently failed (dead letter)', [
                'queue_id' => $data['queue_id'] ?? null,
                'mobile_number' => $data['mobile_number'] ?? null,
                'error' => $error ?? 'Max retries exceeded',
                'retry_count' => $data['retry_count'] ?? 0,
            ]);

            // Update smsg_log if applicable
            if (isset($data['smsg_log_id'])) {
                DB::table('smsg_log')
                    ->where('bigid', $data['smsg_log_id'])
                    ->where('migration_flag', 'new')
                    ->update([
                        'sentstatus' => 'fail',
                        'sentstatustext' => $error ?? 'Max retries exceeded',
                        'aggregator_dlrcode' => 'QUEUE_FAIL',
                        'aggregator_dlrmsg' => 'Failed after maximum retries',
                        'timesent' => Carbon::now()->format('YmdHis')
                    ]);
            }
            
        } catch (Exception $e) {
            Log::error('Failed to handle permanent failure: ' . $e->getMessage());
        }
    }

    /**
     * Update message status in database
     */
    private function updateMessageStatus($queueId, $status, $statusText = null, $retryCount = null)
    {
        // NO-OP. The sms_queue table is deprecated (removed from this project's design) and
        // no longer carries these columns, so this UPDATE was failing with a 1054
        // "Unknown column 'status_text'" warning on EVERY processed message — a failing DB
        // query + log spam per SMS. Message/delivery status is tracked in smsg_log by the
        // SMPP services, so there is nothing to persist here.
        return;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats($queue)
    {
        // Skip if we're in migration mode
        if (self::$skipConnection || $this->isRunningMigration()) {
            return [
                'queue' => $queue,
                'messages' => 0,
                'consumers' => 0,
                'error' => 'RabbitMQ connection skipped'
            ];
        }

        $this->ensureConnection();
        
        if (!$this->connected) {
            return [
                'queue' => $queue,
                'messages' => 0,
                'consumers' => 0,
                'error' => 'Not connected to RabbitMQ'
            ];
        }

        try {
            list($queueName, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $queue,
                true,  // passive - don't create queue
                false, false, false
            );

            return [
                'queue' => $queueName,
                'messages' => $messageCount,
                'consumers' => $consumerCount
            ];
        } catch (Exception $e) {
            Log::error('Failed to get queue stats: ' . $e->getMessage());
            return [
                'queue' => $queue,
                'messages' => 0,
                'consumers' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Purge queue (use with caution)
     */
    public function purgeQueue($queue)
    {
        // Skip if we're in migration mode
        if (self::$skipConnection || $this->isRunningMigration()) {
            return 0;
        }

        $this->ensureConnection();
        
        if (!$this->connected) {
            return 0;
        }

        try {
            $result = $this->channel->queue_purge($queue);
            $count = $result[1] ?? 0; // Message count is in index 1
            Log::info("Queue purged: {$queue}, count: {$count}");
            return $count;
        } catch (Exception $e) {
            Log::error('Failed to purge queue: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Peek at messages without consuming them
     */
    public function peekMessages($queue, $count = 10)
    {
        // Skip if we're in migration mode
        if (self::$skipConnection || $this->isRunningMigration()) {
            return [];
        }

        $this->ensureConnection();
        
        if (!$this->connected) {
            return [];
        }

        $messages = [];
        
        try {
            for ($i = 0; $i < $count; $i++) {
                $msg = $this->channel->basic_get($queue, false); // no_ack = false
                
                if (!$msg) {
                    break; // No more messages
                }
                
                $data = json_decode($msg->body, true);
                $messages[] = $data;
                
                // Reject and requeue the message (put it back)
                $this->channel->basic_nack($msg->getDeliveryTag(), false, true);
            }
            
            return $messages;
        } catch (Exception $e) {
            Log::error('Failed to peek messages: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Acknowledge specific message by queue ID
     */
    public function acknowledgeMessage($queueId)
    {
        try {
            $this->updateMessageStatus($queueId, 'acknowledged', 'Message manually acknowledged');
            return true;
        } catch (Exception $e) {
            Log::error('Failed to acknowledge message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Requeue failed messages for retry
     */
    public function requeueFailedMessages($limit = 100)
    {
        try {
            $failedMessages = DB::table('sms_queue')
                ->where('status', 'failed')
                ->where('retry_count', '<', $this->maxRetries)
                ->limit($limit)
                ->get();
            
            foreach ($failedMessages as $message) {
                $data = [
                    'queue_id' => $message->queue_id,
                    'mobile_number' => $message->mobile_number,
                    'message' => $message->message,
                    'retry_count' => $message->retry_count ?? 0,
                    'metadata' => json_decode($message->metadata, true)
                ];
                
                $this->publishToQueue(
                    env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
                    $data,
                    $message->priority ?? 5,
                    $message->retry_count ?? 0
                );
                
                $this->updateMessageStatus($message->queue_id, 'queued', 'Re-queued for processing');
            }
            
            return count($failedMessages);
        } catch (Exception $e) {
            Log::error('Failed to requeue messages: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Close connection
     */
    public function disconnect()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
            $this->connected = false;
            Log::info('RabbitMQ disconnected');
        } catch (Exception $e) {
            Log::error('Error disconnecting from RabbitMQ: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
