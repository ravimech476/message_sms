<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\RabbitMQService;

/**
 * One-shot backfill: scans delivery_receipt_push_log for rows still at
 * status='new' (left behind because the legacy cron was disabled before
 * the dlr-callback:consume worker existed) and publishes each row id to
 * the dlr.callback.push queue so the consumer picks them up.
 *
 * Safe to re-run — atomic claim in DlrCallbackPusher prevents double-sends.
 */
class BackfillDlrCallbacks extends Command
{
    protected $signature = 'dlr-callback:backfill
                            {--limit=1000 : Max rows to enqueue per run}
                            {--dry-run : Show count without publishing}';

    protected $description = 'Enqueue pending delivery_receipt_push_log rows (status=new) into RabbitMQ for the dlr-callback consumer';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        // Shared-DB rule: only enqueue rows for customers migrated to the NEW
        // system (users.migration_flag = 'new'). Non-migrated customers are still
        // handled by the OLD system off the same delivery_receipt_push_log table.
        // Mirrors DeliveryReceiptPushCommand::fetchPendingReceipts and the guard
        // in DlrCallbackPusher::processRow.
        $rows = DB::table('delivery_receipt_push_log as d')
            ->join('users as u', 'd.users_bigid', '=', 'u.bigid')
            ->select('d.id', 'd.smsg_log_bigid', 'd.msisdn', 'd.retries_left', 'd.dosendtime')
            ->where('d.status', 'new')
            ->where('d.retries_left', '>', 0)
            ->where('d.dosendtime', '<=', now())
            ->where('u.migration_flag', 'new')
            ->orderBy('d.id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No pending DLR callback rows to enqueue.');
            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Found {$rows->count()} pending rows.");
        if ($dryRun) {
            foreach ($rows->take(10) as $r) {
                $this->line("  id={$r->id}  bigid={$r->smsg_log_bigid}  msisdn={$r->msisdn}  retries={$r->retries_left}");
            }
            return Command::SUCCESS;
        }

        $queue = env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push');
        $rabbit = new RabbitMQService();

        $published = 0;
        foreach ($rows as $r) {
            $ok = $rabbit->publishToQueue($queue, [
                'row_id'    => $r->id,
                'bigid'     => $r->smsg_log_bigid,
                'msisdn'    => $r->msisdn,
                'queued_at' => now()->toIso8601String(),
                'backfill'  => true,
            ]);
            if ($ok !== false) {
                $published++;
            }
        }

        $this->info("Published {$published} of {$rows->count()} pending rows to {$queue}.");
        return Command::SUCCESS;
    }
}
