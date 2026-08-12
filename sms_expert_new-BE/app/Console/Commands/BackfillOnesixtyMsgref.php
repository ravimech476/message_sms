<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill smsg_log.onesixty_suppliermsgref = deliveryreceipt1 for rows that still need their
 * DLR matched fast.
 *
 * Targets ONLY the rows that matter:
 *   - deliveryreceipt1 IS NOT empty   (has a Vonage message id to match on)
 *   - deliveryreceipt2 IS empty       (final DLR not yet applied — still awaiting delivery status)
 *   - specific send days              (default 13,14,16 of the given month)
 *   - onesixty_suppliermsgref != deliveryreceipt1 (not already backfilled)
 *
 * DLR matching looks a message up by onesixty_suppliermsgref (varchar(36), INDEXED) for a
 * sub-ms seek. Copying the hex id from deliveryreceipt1 into onesixty_suppliermsgref lets those
 * pending rows match on the index instead of a full-table scan.
 *
 * Runs bounded UPDATE ... LIMIT batches so the huge table is never locked by one statement.
 * Safe to re-run: backfilled rows drop out of the WHERE.
 */
class BackfillOnesixtyMsgref extends Command
{
    protected $signature = 'dlr:backfill-msgref
                            {--month=      : Month prefix YYYYMM for the send date. Default: current month.}
                            {--days=13,14,16 : Comma-separated days of that month to include (e.g. 13,14,16).}
                            {--batch=5000  : Rows per UPDATE batch.}
                            {--sleep-ms=0  : Optional pause between batches, in milliseconds.}
                            {--dry-run     : Only report how many rows WOULD be backfilled; change nothing.}';

    protected $description = 'Copy deliveryreceipt1 into the indexed onesixty_suppliermsgref for pending rows (deliveryreceipt2 empty) on the given days';

    public function handle(): int
    {
        $batch   = max(100, (int) $this->option('batch'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $month   = $this->option('month') ?: date('Ym');

        if (!preg_match('/^\d{6}$/', $month)) {
            $this->error("Invalid --month '{$month}'. Expected YYYYMM (e.g. 202607).");
            return self::FAILURE;
        }

        // Parse + validate the day list into zero-padded 2-digit strings (01..31).
        $days = collect(explode(',', (string) $this->option('days')))
            ->map(fn ($d) => trim($d))
            ->filter(fn ($d) => $d !== '')
            ->map(fn ($d) => str_pad($d, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->values();

        foreach ($days as $d) {
            if (!preg_match('/^(0[1-9]|[12]\d|3[01])$/', $d)) {
                $this->error("Invalid day '{$d}'. Use days 1-31, e.g. --days=13,14,16.");
                return self::FAILURE;
            }
        }
        if ($days->isEmpty()) {
            $this->error('No valid --days given.');
            return self::FAILURE;
        }

        $dayPrefixes = $days->map(fn ($d) => $month . $d)->all(); // e.g. 20260713

        // Scope: pending rows (deliveryreceipt2 empty) that HAVE a hex id in deliveryreceipt1
        // but whose indexed column doesn't yet match it, on the requested days.
        $applyScope = function ($q) use ($dayPrefixes) {
            $q->whereNotNull('deliveryreceipt1')
              ->where('deliveryreceipt1', '<>', '')
              // deliveryreceipt2 empty = final DLR not applied yet (the rows still awaiting status)
              ->where(function ($w) {
                  $w->whereNull('deliveryreceipt2')->orWhere('deliveryreceipt2', '');
              })
              // not already backfilled
              ->where(function ($w) {
                  $w->whereNull('onesixty_suppliermsgref')
                    ->orWhereColumn('onesixty_suppliermsgref', '<>', 'deliveryreceipt1');
              })
              // only the requested send days
              ->where(function ($w) use ($dayPrefixes) {
                  foreach ($dayPrefixes as $prefix) {
                      $w->orWhere('timesubmitted', 'like', $prefix . '%');
                  }
              });
            return $q;
        };

        $scopeLabel = 'days ' . $days->implode(',') . " of {$month}, deliveryreceipt2 empty";
        $pending = $applyScope(DB::table('smsg_log'))->count();

        $this->info("Rows needing backfill ({$scopeLabel}): {$pending}");

        if ($pending === 0) {
            $this->info('Nothing to backfill.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('DRY RUN — no rows changed. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        $totalUpdated = 0;
        $round = 0;

        do {
            $affected = $applyScope(DB::table('smsg_log'))
                ->limit($batch)
                ->update([
                    'onesixty_suppliermsgref' => DB::raw('deliveryreceipt1'),
                ]);

            $totalUpdated += $affected;
            $round++;
            $this->line("  batch {$round}: updated {$affected} (running total {$totalUpdated})");

            if ($sleepMs > 0 && $affected > 0) {
                usleep($sleepMs * 1000);
            }
        } while ($affected > 0);

        $this->newLine();
        $this->info("Done. Backfilled {$totalUpdated} row(s) ({$scopeLabel}).");
        Log::info('dlr:backfill-msgref complete', [
            'scope'   => $scopeLabel,
            'updated' => $totalUpdated,
        ]);

        return self::SUCCESS;
    }
}
