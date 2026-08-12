<?php

namespace App\Console\Commands;

use App\Services\DeliveryStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Process delivery receipts from the smsg_receipt_buffer_new TABLE — the OLD SYSTEM model,
 * with NO RabbitMQ.
 *
 * Mirrors daemon_dreceipt_inbound_buffer.php:
 *   1. SELECT rows with status='new'
 *   2. lock them: UPDATE ... SET status='doing'
 *   3. match onesixty_suppliermsgref (INDEXED) and UPDATE smsg_log   (via DeliveryStatusService)
 *   4. DELETE the processed rows from the buffer
 *
 * A DLR is stored in smsg_receipt_buffer_new.XMLDATA as JSON (message_id, mobile_number, status,
 * error_code, done_date, ...) by the SMPP receiver (SmsQueueService::storeDlrToBuffer).
 *
 * Run ONE instance (like the old single daemon). Matching is an indexed seek, so one worker
 * clears high volume easily. Use --continuous under supervisor, or a per-minute cron.
 */
class ProcessDlrBuffer extends Command
{
    protected $signature = 'dlr:process-buffer
                            {--limit=500   : Rows to claim & process per cycle}
                            {--continuous  : Keep looping (daemon mode) instead of a single pass}
                            {--sleep=2     : Seconds to wait when the buffer is empty (continuous mode)}
                            {--keep        : Mark rows status=done instead of deleting (debugging)}';

    protected $description = 'Process DLRs from the smsg_receipt_buffer_new table and update smsg_log (old-system model, no RabbitMQ)';

    private DeliveryStatusService $deliveryStatusService;
    private int $totalOk = 0;
    private int $totalNoMatch = 0;
    private int $totalBad = 0;

    public function __construct()
    {
        parent::__construct();
        $this->deliveryStatusService = new DeliveryStatusService();
    }

    public function handle(): int
    {
        $limit      = max(1, (int) $this->option('limit'));
        $continuous = (bool) $this->option('continuous');
        $sleep      = max(1, (int) $this->option('sleep'));
        $keep       = (bool) $this->option('keep');

        // Reclaim rows stuck in 'doing' from a previously crashed run so nothing is lost.
        // (Safe because this daemon runs as a SINGLE instance, like the old system.)
        $reclaimed = DB::table('smsg_receipt_buffer_new')->where('status', 'doing')->update(['status' => 'new']);
        if ($reclaimed > 0) {
            $this->info("Reclaimed {$reclaimed} stuck 'doing' row(s) back to 'new'.");
        }

        $this->info('DLR buffer processor started' . ($continuous ? ' (continuous)' : ' (single pass)') . '.');

        do {
            $count = $this->processCycle($limit, $keep);

            if ($count === 0 && $continuous) {
                sleep($sleep);
            }
        } while ($continuous);

        $this->info("Done. Updated: {$this->totalOk} | No-match: {$this->totalNoMatch} | Bad: {$this->totalBad}");
        return self::SUCCESS;
    }

    /**
     * Claim one batch of buffered DLRs, process them, and remove them. Returns the number
     * of rows claimed this cycle (0 = buffer empty).
     */
    private function processCycle(int $limit, bool $keep): int
    {
        // 1. Read a batch of new rows.
        $rows = DB::table('smsg_receipt_buffer_new')
            ->where('status', 'new')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $ids = $rows->pluck('id')->all();

        // 2. Lock them so a re-run / another pass won't pick them up.
        DB::table('smsg_receipt_buffer_new')->whereIn('id', $ids)->update(['status' => 'doing']);

        $finished = [];

        // 3. Process each. Any outcome (matched, no-match, or bad payload) removes the row —
        //    exactly like the old daemon, which never re-queues an unmatched DLR.
        foreach ($rows as $row) {
            try {
                $dlr = json_decode($row->XMLDATA, true);

                if (!is_array($dlr) || empty($dlr['message_id'])) {
                    $this->totalBad++;
                    $finished[] = $row->id;
                    continue;
                }

                $matched = $this->deliveryStatusService->processDeliveryReceiptLite($dlr);
                $matched ? $this->totalOk++ : $this->totalNoMatch++;
                $finished[] = $row->id;
            } catch (\Throwable $e) {
                // Log and drop it (don't let one poison row block the buffer).
                $this->totalBad++;
                $finished[] = $row->id;
                Log::error('dlr:process-buffer row failed', [
                    'id'    => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. Remove processed rows (or mark done with --keep).
        if (!empty($finished)) {
            if ($keep) {
                DB::table('smsg_receipt_buffer_new')->whereIn('id', $finished)->update(['status' => 'done']);
            } else {
                DB::table('smsg_receipt_buffer_new')->whereIn('id', $finished)->delete();
            }
        }

        $this->line("  cycle: {$rows->count()} processed (ok {$this->totalOk}, no-match {$this->totalNoMatch}, bad {$this->totalBad})");

        return $rows->count();
    }
}
