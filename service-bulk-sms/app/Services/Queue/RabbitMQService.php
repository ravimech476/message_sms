<?php

namespace App\Services\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * RabbitMQ bus (Phase 3) — ported from sms_expert's RabbitMQService.
 *
 * Publishes outbound SMS to `sms.outbound` and DLRs to `sms.dlr`; long-running
 * consumers drain them. Uses php-amqplib against the `rabbitmq` docker service.
 */
class RabbitMQService
{
    private ?AMQPStreamConnection $connection = null;
    /** @var \PhpAmqpLib\Channel\AMQPChannel|null */
    private $channel = null;

    private function connect(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            return;
        }

        // Retry so a not-yet-ready broker (e.g. RabbitMQ still booting after
        // `docker-compose up`) is waited out instead of crashing the worker and
        // firing a false crash-alert. Only throws after ~retries×delay seconds.
        $retries = max(1, (int) config('rabbitmq.connect_retries', 10));
        $delay   = max(1, (int) config('rabbitmq.connect_retry_delay', 3));
        $lastError = null;

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $this->connection = new AMQPStreamConnection(
                    config('rabbitmq.host', 'rabbitmq'),
                    (int) config('rabbitmq.port', 5672),
                    config('rabbitmq.user', 'guest'),
                    config('rabbitmq.password', 'guest'),
                    config('rabbitmq.vhost', '/'),
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    3.0,
                    3.0,
                    null,
                    false,
                    (int) config('rabbitmq.heartbeat', 30)
                );
                $this->channel = $this->connection->channel();
                return;
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt < $retries) {
                    \Illuminate\Support\Facades\Log::warning(
                        "RabbitMQ not ready (attempt {$attempt}/{$retries}) — retrying in {$delay}s: " . $e->getMessage()
                    );
                    sleep($delay);
                }
            }
        }

        // Broker genuinely unreachable after all retries — let it bubble
        // (supervisor restarts the worker; a sustained outage is alert-worthy).
        throw $lastError;
    }

    private function declareQueue(string $queue): void
    {
        // durable queue so messages survive a broker restart
        $this->channel->queue_declare($queue, false, true, false, false);
    }

    /**
     * Publish a JSON payload to a queue. Refuses to publish an empty/invalid body
     * (mirrors sms_expert's guard against the £/UTF-8 silent-drop bug).
     */
    public function publishToQueue(string $queue, array $data): bool
    {
        $this->connect();
        $this->declareQueue($queue);

        $json = json_encode($data);
        if ($json === false || $json === '' || $json === 'null') {
            // repair malformed UTF-8 and retry once
            $data = $this->deepUtf8($data);
            $json = json_encode($data);
            if ($json === false || $json === '' || $json === 'null') {
                return false;
            }
        }

        $msg = new AMQPMessage($json, [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type'  => 'application/json',
        ]);
        $this->channel->basic_publish($msg, '', $queue);

        \App\Services\Logging\ComponentLogger::rabbitmq($queue)->info('PUBLISH', [
            'to'   => $data['to'] ?? null,
            'from' => $data['from'] ?? null,
            'ref'  => $data['message_update_id'] ?? null,
        ]);
        return true;
    }

    /**
     * Consume a queue. $callback receives the decoded array and must return true
     * (ack) or false (nack/requeue). Blocks forever.
     */
    public function consumeFromQueue(string $queue, callable $callback, int $maxMessages = 0): void
    {
        $this->connect();
        $this->declareQueue($queue);
        $this->channel->basic_qos(null, (int) config('rabbitmq.prefetch', 5), null);

        $consumerTag = 'footfall-' . getmypid();
        $processed = 0;

        $this->channel->basic_consume($queue, $consumerTag, false, false, false, false, function (AMQPMessage $message) use ($callback, $maxMessages, &$processed, $consumerTag) {
            $data = json_decode($message->getBody(), true);
            $log = \App\Services\Logging\ComponentLogger::rabbitmq($queue);
            $ok = false;
            if (is_array($data)) {
                try {
                    $ok = (bool) $callback($data);
                } catch (\Throwable $e) {
                    $ok = false;
                    $log->error('CONSUME exception', ['error' => $e->getMessage()]);
                }
            } else {
                // unparseable body — ack to drop it (don't loop forever)
                $ok = true;
                $log->warning('CONSUME unparseable body — dropped');
            }

            if ($ok) {
                $message->ack();
                $log->info('ACK', ['to' => $data['to'] ?? null, 'ref' => $data['message_update_id'] ?? null]);
            } else {
                $message->nack(true); // requeue
                $log->warning('NACK — requeued', ['to' => $data['to'] ?? null]);
            }

            $processed++;
            if ($maxMessages > 0 && $processed >= $maxMessages) {
                $message->getChannel()->basic_cancel($consumerTag);
            }
        });

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function close(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function deepUtf8($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'deepUtf8'], $value);
        }
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        return $value;
    }
}
