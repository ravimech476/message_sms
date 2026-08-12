<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Surgical, single-shot cleanup for two specific virtual numbers that were
 * appearing duplicated in /admin/virtual-numbers because their smsshortcodes
 * row had multiple historical itagg_instance assignments.
 *
 * Scope is intentionally narrow:
 *   - Only the two msisdns listed in TARGET_MSISDNS are touched.
 *   - Only itagg_instance rows are pruned — virtual_numbers and smsshortcodes
 *     are left alone.
 *   - For each affected smsshortcodes_id we keep MAX(id) (the most recent
 *     assignment) and remove the older duplicates.
 *
 * The accompanying code change in VirtualNumberController::index already
 * prevents this duplication from showing in the UI for ANY number going
 * forward, so this migration is purely a one-off tidy of the table for the
 * two numbers the user reported.
 */
return new class extends Migration
{
    private const TARGET_MSISDNS = [
        '447937946920',
        '447507332441',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('itagg_instance') || !Schema::hasTable('smsshortcodes')) {
            return;
        }

        $shortcodeIds = DB::table('smsshortcodes')
            ->whereIn('number', self::TARGET_MSISDNS)
            ->pluck('id')
            ->toArray();

        if (empty($shortcodeIds)) {
            Log::info('dedupe_itagg_instance_for_specific_numbers: no matching smsshortcodes — nothing to do.');
            return;
        }

        $deletedTotal = 0;
        foreach ($shortcodeIds as $scId) {
            $latestId = DB::table('itagg_instance')
                ->where('smsshortcodes_id', $scId)
                ->max('id');

            if (!$latestId) {
                continue;
            }

            $deleted = DB::table('itagg_instance')
                ->where('smsshortcodes_id', $scId)
                ->where('id', '!=', $latestId)
                ->delete();

            if ($deleted > 0) {
                Log::info('dedupe_itagg_instance: pruned older instance rows', [
                    'smsshortcodes_id' => $scId,
                    'kept_id'          => $latestId,
                    'deleted_count'    => $deleted,
                ]);
                $deletedTotal += $deleted;
            }
        }

        Log::info('dedupe_itagg_instance_for_specific_numbers: complete', [
            'msisdns'          => self::TARGET_MSISDNS,
            'shortcode_ids'    => $shortcodeIds,
            'rows_deleted'     => $deletedTotal,
        ]);
    }

    /**
     * Irreversible — we cannot resurrect the older itagg_instance rows we
     * pruned. Down() is intentionally a no-op so that a `migrate:rollback`
     * doesn't error out and the migration table stays consistent.
     */
    public function down(): void
    {
        // no-op
    }
};
