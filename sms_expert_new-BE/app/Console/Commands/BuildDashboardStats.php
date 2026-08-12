<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Pre-computes the admin dashboard aggregates into the dashboard_daily_stats table.
 *
 * The dashboard previously ran live UNION queries across every smsg_log% table on
 * each request. This command runs once per day (and is re-runnable) to populate
 * per-day rows that the dashboard reads directly.
 *
 * Usage:
 *   php artisan dashboard:build-stats              # rolling window (last 40 days)
 *   php artisan dashboard:build-stats --days=60    # custom rolling window
 *   php artisan dashboard:build-stats --all        # full historical rebuild (one-time backfill)
 */
class BuildDashboardStats extends Command
{
    protected $signature = 'dashboard:build-stats
                            {--days=40 : Recompute the last N days (ignored when --all is set)}
                            {--all : Rebuild the full history across all smsg_log tables}';

    protected $description = 'Pre-compute admin dashboard SMS stats into dashboard_daily_stats';

    public function handle(): int
    {
        $rebuildAll = (bool) $this->option('all');
        $days = (int) $this->option('days');
        if ($days < 1) {
            $days = 40;
        }

        // Lower bound for timesent (stored as 'YmdHis' string). Null = full history.
        $sinceStamp = null;
        if (!$rebuildAll) {
            $sinceStamp = Carbon::now()->subDays($days)->startOfDay()->format('YmdHis');
            $this->info("Rebuilding dashboard stats for the last {$days} days (since {$sinceStamp}).");
        } else {
            $this->info('Rebuilding dashboard stats for the FULL history (this may take a while).');
        }

        $tables = $this->getSmsgLogTables();
        if (empty($tables)) {
            $this->warn('No smsg_log tables found. Nothing to do.');
            return self::SUCCESS;
        }

        // Aggregate per day, merged across all smsg_log% tables.
        // Keyed by date string 'Y-m-d'.
        $daily = [];

        foreach ($tables as $tableName) {
            $this->line("  Aggregating {$tableName} ...");
            $rows = $this->aggregateTable($tableName, $sinceStamp);

            foreach ($rows as $row) {
                // Skip rows whose timesent could not be parsed into a real date.
                if (empty($row->d) || $row->d === '0000-00-00') {
                    continue;
                }
                $date = $row->d;

                if (!isset($daily[$date])) {
                    $daily[$date] = [
                        'acked_sent_count'      => 0,
                        'acked_delivered_count' => 0,
                        'acked_profit'          => 0.0,
                        'acked_costprice'       => 0.0,
                        'acked_userprice'       => 0.0,
                        'ok_sent_count'         => 0,
                        'ok_row_count'          => 0,
                        'ok_delivered_count'    => 0,
                        'ok_profit'             => 0.0,
                        'ok_costprice'          => 0.0,
                        'ok_userprice'          => 0.0,
                    ];
                }

                $daily[$date]['acked_sent_count']      += (int)   $row->acked_sent;
                $daily[$date]['acked_delivered_count'] += (int)   $row->acked_delivered;
                $daily[$date]['acked_profit']          += (float) $row->acked_profit;
                $daily[$date]['acked_costprice']       += (float) $row->acked_cost;
                $daily[$date]['acked_userprice']       += (float) $row->acked_userprice;
                $daily[$date]['ok_sent_count']         += (int)   $row->ok_sent;
                $daily[$date]['ok_row_count']          += (int)   $row->ok_rows;
                $daily[$date]['ok_delivered_count']    += (int)   $row->ok_delivered;
                $daily[$date]['ok_profit']             += (float) $row->ok_profit;
                $daily[$date]['ok_costprice']          += (float) $row->ok_cost;
                $daily[$date]['ok_userprice']          += (float) $row->ok_userprice;
            }
        }

        if (empty($daily)) {
            $this->info('No rows found in the selected range. Nothing to store.');
            return self::SUCCESS;
        }

        $now = now();
        $count = 0;
        foreach ($daily as $date => $values) {
            DB::table('dashboard_daily_stats')->updateOrInsert(
                ['stat_date' => $date],
                array_merge($values, ['updated_at' => $now, 'created_at' => $now])
            );
            $count++;
        }

        $this->info("Done. Stored/updated {$count} day(s) of dashboard stats.");
        return self::SUCCESS;
    }

    /**
     * All smsg_log + smsg_log_* archive tables in the current schema.
     */
    private function getSmsgLogTables(): array
    {
        $tables = DB::select("
            SELECT table_name AS name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE table_name LIKE 'smsg_log%'
            AND TABLE_SCHEMA = DATABASE()
        ");

        return array_map(fn($t) => $t->name, $tables);
    }

    /**
     * Per-day conditional aggregation for a single smsg_log table.
     * Returns rows with: d, acked_*, ok_* columns.
     */
    private function aggregateTable(string $tableName, ?string $sinceStamp)
    {
        // Bound the scan by the raw timesent string (indexed, avoids STR_TO_DATE in WHERE).
        $where = "timesent IS NOT NULL AND timesent <> ''";
        $bindings = [];
        if ($sinceStamp !== null) {
            $where .= " AND timesent >= ?";
            $bindings[] = $sinceStamp;
        }

        $sql = "
            SELECT
                DATE(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) AS d,

                SUM(CASE WHEN deliverystatus1 = 'acked' THEN COALESCE(numparts, 1) ELSE 0 END) AS acked_sent,
                SUM(CASE WHEN deliverystatus1 = 'acked'
                          AND deliverystatus2 NOT IN ('pending', 'pendin', '')
                          AND deliverystatus2 IS NOT NULL
                         THEN COALESCE(numparts, 1) ELSE 0 END) AS acked_delivered,
                SUM(CASE WHEN deliverystatus1 = 'acked' THEN COALESCE(profit, 0) ELSE 0 END) AS acked_profit,
                SUM(CASE WHEN deliverystatus1 = 'acked' THEN COALESCE(costprice, 0) ELSE 0 END) AS acked_cost,
                SUM(CASE WHEN deliverystatus1 = 'acked' THEN COALESCE(userprice, 0) ELSE 0 END) AS acked_userprice,

                SUM(CASE WHEN sentstatus = 'ok' THEN COALESCE(numparts, 1) ELSE 0 END) AS ok_sent,
                SUM(CASE WHEN sentstatus = 'ok' THEN 1 ELSE 0 END) AS ok_rows,
                SUM(CASE WHEN sentstatus = 'ok'
                          AND deliverystatus2 NOT IN ('pending', 'pendin', '')
                          AND deliverystatus2 IS NOT NULL
                         THEN COALESCE(numparts, 1) ELSE 0 END) AS ok_delivered,
                SUM(CASE WHEN sentstatus = 'ok' THEN COALESCE(profit, 0) ELSE 0 END) AS ok_profit,
                SUM(CASE WHEN sentstatus = 'ok' THEN COALESCE(costprice, 0) ELSE 0 END) AS ok_cost,
                SUM(CASE WHEN sentstatus = 'ok' THEN COALESCE(userprice, 0) ELSE 0 END) AS ok_userprice
            FROM {$tableName}
            WHERE {$where}
            GROUP BY DATE(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'))
        ";

        return DB::select($sql, $bindings);
    }
}
