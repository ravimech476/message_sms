<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the daily DLR catch-up cron in cron_job_settings for EXISTING databases.
 *
 * The seed in create_cron_job_settings_table only runs on a fresh install, so editing
 * it does nothing on a DB where that migration already ran ("Nothing to migrate"). This
 * migration inserts the new row idempotently, and also re-aligns the every-minute fetch
 * row's label/enabled in case an older DB still shows the stale "Every 3/5 minutes".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'nexmo:fetch-delivery-reports-daily')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'  => 'nexmo:fetch-delivery-reports-daily',
                'name'     => 'Daily DLR Catch-up (24h)',
                'schedule' => 'Daily (02:00 UK)',
                'enabled'  => 1,
            ]);
        }

        // Keep the every-minute fetch row consistent on older DBs.
        DB::table('cron_job_settings')
            ->where('command', 'nexmo:fetch-delivery-reports')
            ->update(['schedule' => 'Every minute', 'enabled' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_job_settings')) {
            DB::table('cron_job_settings')
                ->where('command', 'nexmo:fetch-delivery-reports-daily')
                ->delete();
        }
    }
};
