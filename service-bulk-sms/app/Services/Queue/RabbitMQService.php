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
            $ok = false;
            if (is_array($data)) {
                try {
                    $ok = (bool) $callback($data);
                } catch (\Throwable $e) {
                    $ok = false;
                }
            } else {
                // unparseable body — ack to drop it (don't loop forever)
                $ok = true;
            }

            if ($ok) {
                $message->ack();
            } else {
                $message->nack(true); // requeue
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
