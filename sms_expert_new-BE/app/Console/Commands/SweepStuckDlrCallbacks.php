<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Queue\RabbitMQService;

/**
 * Backstop sweeper: finds rows in delivery_receipt_push_log stuck at
 * status='doing' for longer than the threshold (default 10 minutes) and
 * releases them back to status='new' so they can be retried.
 *
 * Rows can get stuck if a consumer process is SIGKILLed mid-process or
 * crashes between the atomic claim (UPDATE → 'doing') and the final
 * status update — the rollback in ConsumeDlrCallbacks's catch block
 * only fires for catchable exceptions, not for kill -9 or OOM.
 *
 * After releasing each row, re-publishes a queue message so the consumer
 * picks it up promptly instead of waiting for the next ad-hoc trigger.
 */
class SweepStuckDlrCallbacks extends Command
{
    protected $signature = 'dlr-callback:sweep-stuck
                            {--stuck-minutes=10 : How long a row must be at status=doing before it counts as stuck}
                            {--limit=500 : Max rows to release per run}
                            {--dry-run : Show what would be released without making changes}';

    protected $description = 'Release delivery_receipt_push_log rows stuck at status=doing back to new (handles crashed-mid-claim case)';

    public function handle(): int
    {
        $stuckMinutes = (int) $this->option('stuck-minutes');
        $limit        = (int) $this->option('limit');
        $dryRun       = (bool) $this->option('dry-run');

        $cutoff = now()->subMinutes($stuckMinutes);

        // last_connection_time is set when a worker actually opens the POST;
        // if it's null but status=doing, fall back to inserted_time.
        $rows = DB::table('delivery_receipt_push_log')
            ->select('id', 'smsg_log_bigid', 'msisdn', 'last_connection_time', 'inserted_time')
            ->where('status', 'doing')
            ->where(function ($q) use ($cutoff) {
                $q->where('last_connection_time', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('last_connection_time')
                         ->where('inserted_time', '<', $cutoff->format('YmdHis'));
                  });
            })
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No rows stuck at status='doing' for more than {$stuckMinutes} minutes.");
            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Found {$rows->count()} stuck rows.");

        if ($dryRun) {
            foreach ($rows->take(10) as $r) {
                $this->line("  id={$r->id}  bigid={$r->smsg_log_bigid}  msisdn={$r->msisdn}  last_seen={$r->last_connection_time}");
            }
            return Command::SUCCESS;
        }

        $queueName = env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push');
        $rabbit = null;
        try {
            $rabbit = new RabbitMQService();
        } catch (\Throwable $e) {
            Log::warning('Sweeper could not connect to RabbitMQ — rows will be released but not re-published; consumer will pick them up on next ad-hoc trigger', [
                'error' => $e->getMessage(),
            ]);
        }

        $released = 0;
        foreach ($rows as $r) {
            // Atomic release — only flip if still 'doing'.
            $ok = DB::table('delivery_receipt_push_log')
                ->where('id', $r->id)
                ->where('status', 'doing')
                ->update([
                    'status'     => 'new',
                    'dosendtime' => now(),
                ]);

            if ($ok !== 1) {
                continue; // someone else already updated it
            }
            $released++;

            if ($rabbit) {
                try {
                    $rabbit->publishToQueue($queueName, [
                        'row_id'    => $r->id,
                        'bigid'     => $r->smsg_log_bigid,
                        'msisdn'    => $r->msisdn,
                        'queued_at' => now()->toIso8601String(),
                        'swept'     => true,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Sweeper publish failed for row ' . $r->id, ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Released {$released} rows back to status='new'.");
        Log::info('DLR callback sweeper run', [
            'released'       => $released,
            'stuck_minutes'  => $stuckMinutes,
            'total_candidates' => $rows->count(),
        ]);

        return Command::SUCCESS;
    }
}
