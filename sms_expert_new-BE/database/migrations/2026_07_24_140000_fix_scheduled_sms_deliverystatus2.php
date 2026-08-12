<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time, per-customer data fix for Arun Estates (userref 840b33214cf157e10e558f59f06189e7).
 *
 * BACKGROUND: this customer had new-system scheduled ("send at time") SMS stuck at
 * sentstatus='tomorrowonward' that never fired (the scheduler bug — they sat "In Transit").
 * Originally this migration RESET deliverystatus2 so they would be picked up and sent.
 *
 * UPDATED PER CUSTOMER REQUEST (25 Jul 2026): Arun Estates has asked for these stuck scheduled
 * messages NOT to be sent — they are time-sensitive and no longer wanted. So instead of making
 * them sendable, we now STOP them: flip sentstatus to 'fail', set sentstatustext to a clear reason
 * ("Scheduled SMS failed - will not be sent now."), and clear deliverystatus2 to ''. The scheduler
 * keys on sentstatus='tomorrowonward', so 'fail' removes them from pickup and they will never send;
 * clearing deliverystatus2 drops the stale 'scheduled'/'pending' value so the row shows cleanly as
 * failed with the reason. userprice is already 0 on scheduled rows — nothing charged.
 *
 * Scope: userref = <Arun Estates bigid>  |  migration_flag = 'new'  |  dayofyear = '20260724'
 * |  sentstatus = 'tomorrowonward' (this customer's still-unsent new-system scheduled rows for the
 * affected day). Already-sent rows (sentstatus='ok'/'fail'/...) are untouched. On the local/dev DB
 * this affects 0 rows (the customer only exists on production), so it is a safe no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('smsg_log')) {
            return;
        }

        DB::table('smsg_log')
            ->where('userref', '840b33214cf157e10e558f59f06189e7')
            ->where('migration_flag', 'new')
            // ->where('dayofyear', '20260724')
            ->where('sentstatus', 'tomorrowonward')
            ->update([
                'sentstatus'      => 'fail',
                'sentstatustext'  => 'Scheduled SMS failed - will not be sent now.',
                'deliverystatus2' => '',
            ]);
    }

    public function down(): void
    {
        // No safe rollback — once flipped to 'fail' these cannot be reliably distinguished from
        // genuine failures for this customer, so reverting would wrongly resurrect real failures. No-op.
    }
};
