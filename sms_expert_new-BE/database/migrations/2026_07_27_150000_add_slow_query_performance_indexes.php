<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the FULL-TABLE-SCAN queries found in the production slow-query log
 * (2026-07-27). All three are frequent, non-locking SELECTs that were scanning entire tables and
 * adding large, constant load to the server (which slows everything else, including the UPDATEs and
 * whatever holds row locks):
 *
 *   1) users_session_logs — force_logout lookup by (big_id, itaggcustid) examined 45,128 rows
 *      (only PRIMARY(id) existed). Add composite index (big_id, itaggcustid).
 *
 *   2) delivery_receipt_push_log — the "already Delivered?" EXISTS filters (smsg_log_bigid,
 *      message_status), but the only related index is on a DIFFERENT column (users_bigid), so it had
 *      no usable index. This runs many times per second during DLR push processing. Add composite
 *      index (smsg_log_bigid, message_status).
 *
 *   3) users — the wallet-min-level reminder scanned all 40,052 rows because `walletminlevel` is not
 *      indexed. The other predicate ((smsg_wallet - smsg_server1_sent - smsg_server2_sent) <
 *      walletminlevel) is a computed expression that cannot be indexed, but indexing walletminlevel
 *      lets MySQL first restrict to the few rows with walletminlevel > 0 and evaluate the expression
 *      only on those. This query fires every 1-2 seconds, so the saving is large.
 *
 * Indexes are added CREATE-IF-MISSING so the migration is safe to re-run and safe if some already
 * exist on production. Adding a secondary index uses InnoDB online DDL (concurrent DML allowed).
 * NOTE: if delivery_receipt_push_log is very large on production, prefer running that one ALTER
 * manually with pt-online-schema-change (or during a quiet window) to be extra safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('users_session_logs', 'idx_uslog_bigid_custid', '(big_id, itaggcustid)');
        $this->addIndexIfMissing('delivery_receipt_push_log', 'idx_drpl_bigid_status', '(smsg_log_bigid, message_status)');
        $this->addIndexIfMissing('users', 'idx_users_walletminlevel', '(walletminlevel)');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('users_session_logs', 'idx_uslog_bigid_custid');
        $this->dropIndexIfExists('delivery_receipt_push_log', 'idx_drpl_bigid_status');
        $this->dropIndexIfExists('users', 'idx_users_walletminlevel');
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $index]
        );
        return $row && (int) $row->c > 0;
    }

    private function addIndexIfMissing(string $table, string $index, string $cols): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }
        // $index and $cols are hard-coded constants above (never user input), so safe to inline.
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` {$cols}");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::hasTable($table) && $this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
