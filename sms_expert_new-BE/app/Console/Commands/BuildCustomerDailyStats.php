<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-computes the per-customer daily SMS card counts into customer_daily_stats, which the
 * customer dashboard (/dashboard) reads instead of scanning smsg_log live.
 *
 * Runs nightly (00:05) to roll up the just-completed day, with a 01:00 catch-up that re-does the
 * last few days so a missed midnight run self-heals. Only COMPLETE days up to YESTERDAY are stored
 * (today is still accumulating), which is why the dashboard shows "data up to <yesterday>".
 *
 * Card definitions (client spec), COUNT(*) of smsg_log rows per user/day:
 *   sent      = all rows
 *   delivered = deliverystatus2 NOT IN ('pending','')   (and not NULL)
 *   pending   = deliverystatus2 IN ('pending','')       (or NULL)
 *   blocklist = sentstatus = 'fail'   (the "Block List" card, was "Failed")
 *
 * Retention: keeps the last 365 days (smsg_log itself is tidied/archived — see OLD dbtidy.php),
 * pruned in batches like OLD's quicklimitsql().
 */
class BuildCustomerDailyStats extends Command
{
    protected $signature = 'customer:build-daily-stats
                            {--days=1 : Rebuild the last N complete days (up to yesterday)}
                            {--all : Rebuild the full history}
                            {--from= : Start date YYYY-MM-DD (use with --to for a bounded backfill chunk)}
                            {--to= : End date YYYY-MM-DD (capped at yesterday)}
                            {--prune : Also delete rows older than 365 days after building}
                            {--prune-only : Only prune old rows; do not rebuild}
                            {--sync-tables : Delete rows for any month whose smsg_log shard no longer exists}';

    protected $description = 'Roll up per-customer daily SMS stats into customer_daily_stats for the dashboard';

    private const RETENTION_DAYS = 365;
    private const DELETE_BATCH = 50000;
    private const UPSERT_CHUNK = 500;

    public function handle(): int
    {
        if ($this->option('prune-only')) {
            $this->pruneOld();
            if ($this->option('sync-tables')) {
                $this->syncToExistingTables();
            }
            return self::SUCCESS;
        }

        // Only complete days: never past yesterday 23:59:59 (today is still accumulating).
        $yesterdayEnd = Carbon::yesterday()->endOfDay();
        $endStamp = $yesterdayEnd->format('YmdHis');

        if ($this->option('from') && $this->option('to')) {
            // Bounded chunk — for spreading a large production backfill over month-sized ranges.
            $startStamp = Carbon::parse($this->option('from'))->startOfDay()->format('YmdHis');
            $toCarbon = Carbon::parse($this->option('to'))->endOfDay();
            if ($toCarbon->gt($yesterdayEnd)) {
                $toCarbon = $yesterdayEnd; // never past yesterday
            }
            $endStamp = $toCarbon->format('YmdHis');
            $this->info("Rebuilding customer daily stats from {$this->option('from')} to " . $toCarbon->toDateString() . '.');
        } elseif ($this->option('all')) {
            $startStamp = '00000000000000';
            $this->info('Rebuilding customer daily stats for the FULL history (up to yesterday).');
        } else {
            $days = max(1, (int) $this->option('days'));
            $startStamp = Carbon::now()->subDays($days)->startOfDay()->format('YmdHis');
            $this->info("Rebuilding customer daily stats for the last {$days} complete day(s) (since {$startStamp}).");
        }

        // Only the real current + monthly shard tables ('smsg_log' or 'smsg_log_YYMM').
        // The shared helper also matches backup_* / hyphenated tables which are not valid shards.
        $tables = array_filter(getSmsgLogTables(), function ($t) {
            return $t === 'smsg_log' || preg_match('/^smsg_log_\d{4}$/', $t);
        });
        if (empty($tables)) {
            $this->warn('No smsg_log tables found. Nothing to do.');
            return self::SUCCESS;
        }

        // Merge across all smsg_log% shard tables, keyed by "bigid|date".
        $acc = [];
        foreach ($tables as $tableName) {
            $this->line("  Aggregating {$tableName} ...");
            foreach ($this->aggregateTable($tableName, $startStamp, $endStamp) as $row) {
                if (empty($row->d) || $row->d === '0000-00-00' || empty($row->bigid)) {
                    continue; // unparseable timesent / missing user
                }
                $key = $row->bigid . '|' . $row->d;
                if (!isset($acc[$key])) {
                    $acc[$key] = ['bigid' => $row->bigid, 'd' => $row->d, 'sent' => 0, 'delivered' => 0, 'pending' => 0, 'blocklist' => 0];
                }
                $acc[$key]['sent']      += (int) $row->sent;
                $acc[$key]['delivered'] += (int) $row->delivered;
                $acc[$key]['pending']   += (int) $row->pending;
                $acc[$key]['blocklist'] += (int) $row->blocklist;
            }
        }

        $now = now();
        $rows = [];
        foreach ($acc as $r) {
            $rows[] = [
                'users_bigid'     => $r['bigid'],
                'stat_date'       => $r['d'],
                'sent_count'      => $r['sent'],
                'delivered_count' => $r['delivered'],
                'pending_count'   => $r['pending'],
                'blocklist_count' => $r['blocklist'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        $written = 0;
        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            DB::table('customer_daily_stats')->upsert(
                $chunk,
                ['users_bigid', 'stat_date'],
                ['sent_count', 'delivered_count', 'pending_count', 'blocklist_count', 'updated_at']
            );
            $written += count($chunk);
        }
        $this->info("Upserted {$written} customer/day rows.");

        if ($this->option('prune') || $this->option('all')) {
            $this->pruneOld();
        }

        if ($this->option('sync-tables')) {
            $this->syncToExistingTables();
        }

        return self::SUCCESS;
    }

    /**
     * Keep customer_daily_stats in step with the available smsg_log shards: delete every row whose
     * month no longer has a source table. Valid months = the CURRENT month (data lives in the main
     * `smsg_log` table) plus every archived shard `smsg_log_YYMM`. When dbtidy (or an admin) drops an
     * aged-out shard, its month's rollup rows are removed on the next run. Batched like pruneOld().
     */
    private function syncToExistingTables(): void
    {
        // Months that still have a source table, as 'YYMM' (e.g. '2607' = Jul 2026).
        $validMonths = [Carbon::now()->format('ym')]; // current month -> main smsg_log
        foreach (getSmsgLogTables() as $t) {
            if (preg_match('/^smsg_log_(\d{4})$/', $t, $m)) {
                $validMonths[] = $m[1];
            }
        }
        $validMonths = array_values(array_unique($validMonths));

        $placeholders = implode(',', array_fill(0, count($validMonths), '?'));
        $total = 0;
        do {
            $deleted = DB::table('customer_daily_stats')
                ->whereRaw("DATE_FORMAT(stat_date, '%y%m') NOT IN ({$placeholders})", $validMonths)
                ->limit(self::DELETE_BATCH)
                ->delete();
            $total += $deleted;
        } while ($deleted >= self::DELETE_BATCH);

        $this->info("Sync: removed {$total} rows for months with no smsg_log table. Kept months: " . implode(', ', $validMonths));
    }

    /**
     * Aggregate one smsg_log table by (userref, day).
     *
     * Buckets/bounds by dosendtime (a 'YmdHis' string), NOT timesent, because:
     *   - dosendtime is INDEXED, so the range WHERE is a fast index scan — non-matching monthly
     *     shards return instantly instead of full-scanning (timesent is not indexed), which is
     *     what makes the 1-year production backfill affordable.
     *   - dosendtime is ALWAYS set; timesent is empty ('00000000000000') for messages that never
     *     sent (e.g. some failures), which would drop them from the blocklist count.
     * For normal immediate sends dosendtime == timesent, so the day-buckets are identical.
     */
    private function aggregateTable(string $tableName, string $startStamp, string $endStamp)
    {
        return DB::select("
            SELECT
                userref AS bigid,
                DATE(STR_TO_DATE(dosendtime, '%Y%m%d%H%i%S')) AS d,
                COUNT(*) AS sent,
                SUM(CASE WHEN sentstatus <> 'fail' AND deliverystatus2 IS NOT NULL AND deliverystatus2 NOT IN ('pending','') THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN sentstatus <> 'fail' AND (deliverystatus2 IS NULL OR deliverystatus2 IN ('pending','')) THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN sentstatus = 'fail' THEN 1 ELSE 0 END) AS blocklist
            FROM `{$tableName}`
            WHERE dosendtime <> '' AND dosendtime >= ? AND dosendtime <= ?
            GROUP BY userref, d
        ", [$startStamp, $endStamp]);
    }

    /**
     * Delete rows older than the retention window, in batches (mirrors OLD dbtidy.php's
     * quicklimitsql so a big backlog never locks the table).
     */
    private function pruneOld(): void
    {
        $cutoff = Carbon::now()->subDays(self::RETENTION_DAYS)->format('Y-m-d');
        $total = 0;
        do {
            $deleted = DB::table('customer_daily_stats')
                ->where('stat_date', '<', $cutoff)
                ->limit(self::DELETE_BATCH)
                ->delete();
            $total += $deleted;
        } while ($deleted >= self::DELETE_BATCH);

        $this->info("Pruned {$total} rows older than {$cutoff} (retention " . self::RETENTION_DAYS . " days).");
    }
}
