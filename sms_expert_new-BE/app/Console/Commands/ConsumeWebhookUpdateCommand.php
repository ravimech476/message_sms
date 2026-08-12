<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\NexmoService;

/**
 * Consumes nexmo.webhook.update — one message per migrated customer.
 *
 * For each message: resolves the customer's Nexmo virtual numbers (the same
 * join chain VirtualNumberController::index uses) and calls Nexmo
 * /number/update for each one to flip its moHttpUrl onto the new-system
 * inbound webhook.
 *
 * Sinch numbers are skipped — only Nexmo numbers expose moHttpUrl.
 *
 * Long-running: meant to live under supervisor like the other
 * rabbitmq:consume-* commands.
 */
class ConsumeWebhookUpdateCommand extends Command
{
    protected $signature = 'rabbitmq:consume-webhook-update
                            {--timeout=60 : Idle timeout in seconds before logging a heartbeat}
                            {--max-messages=0 : Stop after N messages (0 = unlimited)}';

    protected $description = 'Consume Nexmo virtual-number webhook-update tasks from RabbitMQ';

    private $connection;
    private $channel;
    private string $queueName = 'nexmo.webhook.update';
    private int $messagesProcessed = 0;
    private int $maxMessages = 0;
    private NexmoService $nexmoService;

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $this->maxMessages = (int) $this->option('max-messages');
        $this->nexmoService = new NexmoService();

        $qlog = \App\Services\Logging\RabbitMQLogService::for($this->queueName);
        $qlog->info('Consumer started', ['timeout' => $timeout, 'max_messages' => $this->maxMessages]);

        try {
            $this->connectToRabbitMQ();

            [, $messageCount] = $this->channel->queue_declare(
                $this->queueName,
                true,
                false,
                false,
                false
            );

            $this->info("Connected to RabbitMQ. {$messageCount} messages waiting on {$this->queueName}.");
            $this->info('Press Ctrl+C to exit');

            $callback = function ($msg) {
                $this->processMessage($msg);
                $this->messagesProcessed++;

                if ($this->maxMessages > 0 && $this->messagesProcessed >= $this->maxMessages) {
                    $this->info("Processed {$this->maxMessages} messages. Exiting...");
                    $this->channel->basic_cancel($msg->delivery_info['consumer_tag']);
                }
            };

            // One unacked message at a time so a slow Nexmo call doesn't
            // starve another worker.
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,  // no_local
                false,  // no_ack — we ack manually after work succeeds
                false,  // exclusive
                false,  // nowait
                $callback
            );

            while ($this->channel->is_consuming()) {
                try {
                    $this->channel->wait(null, false, $timeout);
                } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                    // Heartbeat — supervisor sees stdout, keeps us alive
                    $this->info("Idle: no messages for {$timeout}s. Still listening...");
                    continue;
                }
            }

            $this->info("Total messages processed: {$this->messagesProcessed}");
        } catch (\Exception $e) {
            $this->error('Consumer error: ' . $e->getMessage());
            Log::error('Webhook update consumer error', ['error' => $e->getMessage()]);
            $qlog->error('Consumer fatal error', ['error' => $e->getMessage(), 'error_at' => $e->getFile().':'.$e->getLine()]);
            return 1;
        } finally {
            $this->cleanup();
        }

        return 0;
    }

    private function connectToRabbitMQ(): void
    {
        $host = env('RABBITMQ_HOST', '127.0.0.1');
        $port = env('RABBITMQ_PORT', 5672);
        $user = env('RABBITMQ_USER', 'guest');
        $password = env('RABBITMQ_PASSWORD', 'guest');
        $vhost = env('RABBITMQ_VHOST', '/');

        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $this->channel = $this->connection->channel();

        $this->channel->queue_declare(
            $this->queueName,
            false,
            true,
            false,
            false
        );
    }

    private function processMessage($msg): void
    {
        try {
            $data = json_decode($msg->body, true);
            if (!$data || ($data['type'] ?? '') !== 'update_mohttp') {
                $this->warn('Discarding malformed message: ' . substr($msg->body, 0, 200));
                $msg->ack();
                return;
            }

            $userBigid  = (string) ($data['user_bigid'] ?? '');
            $webhookUrl = (string) ($data['webhook_url'] ?? '');

            if ($userBigid === '' || $webhookUrl === '') {
                $this->warn('Discarding message missing user_bigid/webhook_url');
                $msg->ack();
                return;
            }

            $this->runUpdate($userBigid, $webhookUrl);

            $msg->ack();
            \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                ->info('ACK — webhook URL update succeeded', ['user_bigid' => $userBigid]);
        } catch (\Throwable $e) {
            // Requeue so the message gets retried by another worker.
            // basic_nack(requeue=true) — Nexmo flakes occasionally; we
            // don't want to lose webhook updates on a transient blip.
            Log::error('Webhook update consumer: exception processing message', [
                'error' => $e->getMessage(),
                'body'  => substr($msg->body, 0, 500),
            ]);
            \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                ->error('NACK (requeue=true) — exception processing message', [
                    'error'    => $e->getMessage(),
                    'error_at' => $e->getFile().':'.$e->getLine(),
                ]);
            $this->error('Exception: ' . $e->getMessage());
            $msg->nack(false, true);
        }
    }

    /**
     * Resolve the customer's Nexmo virtual numbers and update each one.
     *
     * Join chain (same as VirtualNumberController::index):
     *   virtual_numbers
     *     ↔ smsshortcodes (latest row per number)
     *     ↔ itagg_instance.smsshortcodes_id
     *     ↔ users.bigid
     */
    private function runUpdate(string $userBigid, string $webhookUrl): void
    {
        $numbers = DB::table('virtual_numbers')
            ->leftJoinSub(function ($sub) {
                $sub->from('smsshortcodes')
                    ->select('number', DB::raw('MAX(id) as latest_id'))
                    ->groupBy('number');
            }, 'latest', 'virtual_numbers.msisdn', '=', 'latest.number')
            ->leftJoin('smsshortcodes as smss', 'smss.id', '=', 'latest.latest_id')
            ->leftJoin('itagg_instance', 'smss.id', '=', 'itagg_instance.smsshortcodes_id')
            ->where('itagg_instance.users_bigid', $userBigid)
            ->where('virtual_numbers.operator', 'Nexmo')
            ->select('virtual_numbers.msisdn', 'virtual_numbers.country')
            ->distinct()
            ->get();

        if ($numbers->isEmpty()) {
            $this->info("No Nexmo numbers for user {$userBigid} — nothing to update.");
            Log::info('Webhook update: no Nexmo numbers for customer', [
                'user_bigid' => $userBigid,
            ]);
            return;
        }

        $updated = 0;
        $failed  = 0;
        foreach ($numbers as $row) {
            if (empty($row->msisdn) || empty($row->country)) {
                $failed++;
                Log::warning('Webhook update: skipping number with missing msisdn/country', [
                    'user_bigid' => $userBigid,
                    'msisdn'     => $row->msisdn ?? null,
                    'country'    => $row->country ?? null,
                ]);
                continue;
            }

            $result = $this->nexmoService->updateNumber(
                $row->country,
                $row->msisdn,
                $webhookUrl
            );

            $errorCode = $result['error-code'] ?? null;
            $ok = ((string) $errorCode === '200') || isset($result['msisdn']);

            if ($ok) {
                $updated++;
                DB::table('virtual_numbers')
                    ->where('msisdn', $row->msisdn)
                    ->update(['mo_http_url' => $webhookUrl]);
            } else {
                $failed++;
                Log::warning('Webhook update: Nexmo /number/update failed', [
                    'user_bigid'  => $userBigid,
                    'msisdn'      => $row->msisdn,
                    'country'     => $row->country,
                    'error_code'  => $errorCode,
                    'error_label' => $result['error-code-label'] ?? null,
                ]);
            }
        }

        $this->info("user {$userBigid}: {$updated} updated, {$failed} failed (of {$numbers->count()})");
        Log::info('Webhook update: customer processed', [
            'user_bigid'  => $userBigid,
            'total'       => $numbers->count(),
            'updated'     => $updated,
            'failed'      => $failed,
            'webhook_url' => $webhookUrl,
        ]);
    }

    private function cleanup(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            // ignore
        }
    }
}
