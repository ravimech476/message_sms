<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Detects migrated customers who are still sending SMS through the OLD API TODAY.
 *
 * The old system stamps smsg_log.migration_flag = 'old' on every send; the new
 * system stamps 'new'. So a migrated user (users.migration_flag = 'new') with
 * smsg_log rows stamped migration_flag = 'old' whose timesent is TODAY is still
 * using the old API right now.
 *
 * Logic:
 *   1) users.migration_flag = 'new'                     (customer has migrated)
 *   2) smsg_log.userref = users.bigid
 *      AND smsg_log.migration_flag = 'old'              (sent via the old API)
 *      AND smsg_log.timesent is on the scanned day      (today by default)
 *
 * Results are upserted into migrated_old_api_usage. The customer dashboard reads
 * that table and shows a once-per-day "please switch off the old API" banner.
 */
class DetectOldApiUsage extends Command
{
    protected $signature = 'alert:old-api-usage {--date= : Date (YYYY-MM-DD) to scan; defaults to today}';

    protected $description = 'Flag migrated customers still using the old SMS API TODAY (writes migrated_old_api_usage)';

    public function handle(): int
    {
        // Scan a single day (today by default). timesent is a 14-digit YYYYMMDDHHMMSS
        // string, so we bound it by [day 00:00:00, next day 00:00:00).
        try {
            $day = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        } catch (\Throwable $e) {
            $this->error('Invalid --date; use YYYY-MM-DD.');
            return self::FAILURE;
        }

        $dayStart  = $day->copy()->startOfDay()->format('YmdHis');        // YYYYMMDD000000
        $dayEnd    = $day->copy()->addDay()->startOfDay()->format('YmdHis'); // next-day 000000
        $this->info("Scanning for migrated customers still using the OLD API on {$day->toDateString()}.");

        // Merge results across all smsg_log% tables, keyed by user bigid.
        $usage = []; // bigid => ['last' => 'YmdHis', 'count' => int]

        // Logic (per requirement):
        //   1) user is migrated  -> users.migration_flag = 'new'
        //   2) their smsg_log rows stamped 'old' (sent via the old API)
        //   3) timesent falls on the scanned day (today)
        // A migrated customer with any such row today is still calling the old API.
        foreach ($this->getSmsgLogTables() as $table) {
            $rows = DB::select("
                SELECT s.userref AS bigid,
                       MAX(s.timesent) AS last_ts,
                       COUNT(*) AS cnt
                FROM {$table} s
                JOIN users u ON u.bigid = s.userref
                            AND u.migration_flag = 'new'
                WHERE s.migration_flag = 'old'
                  AND s.timesent >= ?
                  AND s.timesent <  ?
                GROUP BY s.userref
            ", [$dayStart, $dayEnd]);

            foreach ($rows as $r) {
                if (empty($r->bigid)) {
                    continue;
                }
                if (!isset($usage[$r->bigid])) {
                    $usage[$r->bigid] = ['last' => $r->last_ts, 'count' => 0];
                }
                $usage[$r->bigid]['count'] += (int) $r->cnt;
                if ($r->last_ts > $usage[$r->bigid]['last']) {
                    $usage[$r->bigid]['last'] = $r->last_ts;
                }
            }
        }

        if (empty($usage)) {
            $this->info('No migrated customers found using the old API in the window.');
            return self::SUCCESS;
        }

        $now = now();
        $count = 0;
        foreach ($usage as $bigid => $data) {
            $lastUsedAt = $this->parseStamp($data['last']);

            // updateOrInsert deliberately does NOT touch last_alert_shown_date,
            // which is owned by the dashboard's once-per-day logic.
            DB::table('migrated_old_api_usage')->updateOrInsert(
                ['user_bigid' => $bigid],
                [
                    'last_old_api_used_at' => $lastUsedAt,
                    'old_api_count'        => $data['count'],
                    'updated_at'           => $now,
                    'created_at'           => $now,
                ]
            );
            $count++;
        }

        $this->info("Flagged {$count} migrated customer(s) still using the old API.");
        return self::SUCCESS;
    }

    /**
     * Only smsg_log% tables that actually have a migration_flag column.
     * Archive tables (smsg_log_old, monthly archives) predate that column,
     * and the old-API rolling window only needs the live smsg_log anyway.
     */
    private function getSmsgLogTables(): array
    {
        $tables = DB::select("
            SELECT DISTINCT table_name AS name
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name LIKE 'smsg_log%'
            AND column_name = 'migration_flag'
            AND TABLE_SCHEMA = DATABASE()
        ");

        return array_map(fn($t) => $t->name, $tables);
    }

    /**
     * Convert a 'YmdHis' timesent string into a Y-m-d H:i:s datetime, or null.
     */
    private function parseStamp(?string $stamp): ?string
    {
        if (empty($stamp) || strlen($stamp) < 8) {
            return null;
        }
        try {
            return Carbon::createFromFormat('YmdHis', str_pad($stamp, 14, '0'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
