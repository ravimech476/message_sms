<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\RabbitMQService;
use App\Services\DlrCallbackPusher;

/**
 * Long-running consumer for the dlr.callback.push queue.
 *
 * One message per row inserted into delivery_receipt_push_log by
 * DeliveryStatusService::processDlrPushCallback. The consumer loads the row
 * by id and runs DlrCallbackPusher::processRow which atomically claims it
 * (status new → doing), POSTs to the customer URL, and updates status.
 *
 * On retry-able failure (POST failed, retries_left > 0) the message is
 * republished to the same queue with delay = wait_minutes via
 * RabbitMQService::publishDelayedMessage. The row's status stays 'new' so
 * the legacy cron (delivery-receipt:push) can also pick it up as a
 * fallback if this consumer dies between attempts — the atomic claim
 * prevents double-sends if both run.
 *
 * Add to supervisor / start-local-workers.bat so it stays up.
 */
class ConsumeDlrCallbacks extends Command
{
    protected $signature = 'dlr-callback:consume
                            {--queue= : Queue name (defaults to RABBITMQ_DLR_CALLBACK_QUEUE)}
                            {--prefetch=5 : RabbitMQ prefetch count}';

    protected $description = 'Consume DLR callback queue and POST delivery receipts to customer URLs';

    private bool $shouldStop = false;

    public function __construct()
    {
        parent::__construct();

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'onPcntlSignal']);
            pcntl_signal(SIGINT, [$this, 'onPcntlSignal']);
        }
    }

    public function handle(): int
    {
        $queue = $this->option('queue') ?: env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push');
        $prefetch = (int) $this->option('prefetch');
        $heartbeatPath = self::heartbeatPath();

        $this->info("Starting DLR callback consumer...");
        $this->info("  Queue:     {$queue}");
        $this->info("  Prefetch:  {$prefetch}");
        $this->info("  Heartbeat: {$heartbeatPath}");
        $this->info("  Stop with Ctrl+C");

        // Initial heartbeat — mark this process as alive
        $this->touchHeartbeat($heartbeatPath);

        try {
            $rabbit = new RabbitMQService();
            $pusher = new DlrCallbackPusher();

            // Idle heartbeat — RabbitMQService's consume loop fires this on
            // every 1-second wait() timeout when no messages arrive. Without
            // it, an idle consumer with no traffic would look "frozen" to the
            // watchdog (which alerts at 120s stale).
            $idleCallback = function () use ($heartbeatPath) {
                $this->touchHeartbeat($heartbeatPath);
            };

            $rabbit->consumeFromQueue(
                $queue,
                function (array $data) use ($pusher, $rabbit, $queue, $heartbeatPath) {
                    // Heartbeat on every message too — keeps the touchfile
                    // current under load when the idle callback rarely fires.
                    $this->touchHeartbeat($heartbeatPath);

                    if ($this->shouldStop) {
                        return false;
                    }

                    $rowId = (int) ($data['row_id'] ?? 0);
                    if ($rowId <= 0) {
                        Log::warning('DLR callback consumer: payload missing row_id', $data);
                        return true; // ack — bad payload, don't requeue
                    }

                    try {
                        $result = $pusher->processRow($rowId);
                        $this->info("[{$rowId}] {$result}");

                        if ($result === 'retry') {
                            // Reload row to read its (just-updated) wait_minutes
                            $row = DB::table('delivery_receipt_push_log')->where('id', $rowId)->first();
                            $waitMinutes = (int) ($row->wait_minutes ?? 5);
                            $delayMs = max(60_000, $waitMinutes * 60_000);

                            $rabbit->publishDelayedMessage($queue, [
                                'row_id'    => $rowId,
                                'bigid'     => $data['bigid'] ?? null,
                                'msisdn'    => $data['msisdn'] ?? null,
                                'retry_of'  => $data['queued_at'] ?? null,
                            ], $delayMs);

                            Log::info('DLR callback requeued with delay', [
                                'row_id'  => $rowId,
                                'delay_s' => $delayMs / 1000,
                            ]);
                        }

                        // All defined outcomes (sent / retry / fail / skipped / missing)
                        // are expected end-states. ACK so the queue message is removed —
                        // DB row owns retry state, and 'retry' already republished with delay.
                        return true;
                    } catch (\Throwable $e) {
                        // UNEXPECTED exception (DB outage, network blip in the middle of an
                        // UPDATE, etc). The DB row may be stuck at status='doing' from the
                        // atomic claim earlier in processRow. We need the queue to retry
                        // this message later, but ALSO need to release the row so a future
                        // delivery (or the watchdog sweeper) can claim it again.
                        Log::error('DLR callback consumer exception', [
                            'row_id' => $rowId,
                            'error'  => $e->getMessage(),
                        ]);

                        // Best-effort rollback: only flip 'doing' → 'new'. If status is
                        // already 'processed'/'fail' the exception happened post-update
                        // and the row shouldn't be touched.
                        try {
                            DB::table('delivery_receipt_push_log')
                                ->where('id', $rowId)
                                ->where('status', 'doing')
                                ->update(['status' => 'new']);
                        } catch (\Throwable $rollbackEx) {
                            Log::warning('DLR callback rollback failed', [
                                'row_id' => $rowId,
                                'error'  => $rollbackEx->getMessage(),
                            ]);
                        }

                        // Return false → RabbitMQService::handleFailedMessage ACKs the
                        // current delivery but republishes the SAME payload with exponential
                        // backoff (10s, 20s, 40s, …, max 5min, up to RABBITMQ_MAX_RETRIES).
                        // After max retries the message lands in the failed/dead-letter
                        // queue instead of being silently lost. Atomic claim inside
                        // processRow prevents double-send if redelivery overlaps with a
                        // concurrent worker on the same row.
                        return false;
                    }
                },
                $prefetch,
                $idleCallback
            );
        } catch (\Throwable $e) {
            $this->error('Consumer error: ' . $e->getMessage());
            Log::error('DLR callback consumer fatal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }

        $this->info('DLR callback consumer stopped.');
        return Command::SUCCESS;
    }

    public function onPcntlSignal(int $signal): void
    {
        $this->info("\nReceived signal {$signal}, stopping...");
        $this->shouldStop = true;
    }

    /**
     * Path to the heartbeat touchfile. Watchdog command reads this to detect
     * frozen daemons. Public + static so the watchdog can read it without
     * needing to instantiate the command.
     */
    public static function heartbeatPath(): string
    {
        return storage_path('app/dlr-callback-heartbeat.touch');
    }

    private function touchHeartbeat(string $path): void
    {
        try {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents($path, getmypid() . "\n" . time());
        } catch (\Throwable $e) {
            // heartbeat write failure is non-fatal; main loop continues
        }
    }
}
