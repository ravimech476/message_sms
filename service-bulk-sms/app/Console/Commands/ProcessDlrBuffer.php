<?php

namespace App\Console\Commands;

use App\Services\Smpp\DeliveryStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — DLR buffer processor: claim 'new' rows from smsg_receipt_buffer_new,
 * match each back to smsg_log by message-id, and write delivery status.
 * Mirrors sms_expert's buffer-drain worker. Decoupled from the receiver so a slow
 * DB update never blocks the SMPP socket.
 *
 *   docker exec silicon-sms-php php artisan dlr:process-buffer
 *   docker exec silicon-sms-php php artisan dlr:process-buffer --continuous
 */
class ProcessDlrBuffer extends Command
{
    protected $signature = 'dlr:process-buffer
        {--limit=200 : Max rows per batch}
        {--continuous : Keep polling instead of exiting when empty}
        {--sleep=2 : Seconds to sleep between polls in --continuous mode}';

    protected $description = 'Match buffered DLRs (smsg_receipt_buffer_new) back to smsg_log delivery status';

    public function handle(DeliveryStatusService $status)
    {
        $limit = max(1, (int) $this->option('limit'));
        $continuous = (bool) $this->option('continuous');
        $sleep = max(1, (int) $this->option('sleep'));

        $this->info('DLR buffer processor started' . ($continuous ? ' (continuous)' : '') . ' ...');

        do {
            // Reclaim rows stuck in 'doing' from a crashed prior run.
            DB::table('smsg_receipt_buffer_new')->where('status', 'doing')->update(['status' => 'new']);

            $rows = DB::table('smsg_receipt_buffer_new')
                ->where('status', 'new')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                if (!$continuous) {
                    break;
                }
                sleep($sleep);
                continue;
            }

            $ids = $rows->pluck('id')->all();
            DB::table('smsg_receipt_buffer_new')->whereIn('id', $ids)->update(['status' => 'doing']);

            $matched = 0;
            $unmatched = 0;
            foreach ($rows as $row) {
                $dlr = json_decode($row->XMLDATA, true) ?: [];
                $ok = $status->apply($dlr);
                $ok ? $matched++ : $unmatched++;

                // enum is new|doing|done — a DLR is "processed" either way; the
                // unmatched count is surfaced in the batch summary below.
                DB::table('smsg_receipt_buffer_new')->where('id', $row->id)->update([
                    'status'      => 'done',
                    'processtime' => now()->format('YmdHi'),
                ]);
            }

            $this->info("  batch of {$rows->count()}: {$matched} matched, {$unmatched} unmatched");
        } while ($continuous);

        $this->info('DLR buffer processor finished.');
        return 0;
    }
}
