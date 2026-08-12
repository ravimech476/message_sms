<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the Nexmo DLR BACKFILL tier as its own row in cron_job_settings so it shows on
 * Admin → Settings → Monitor and can be toggled independently.
 *
 * There are three Nexmo DLR runs (routes/console.php):
 *   FRESH    'nexmo:fetch-delivery-reports'          every minute, 15-min lookback  (already listed)
 *   BACKFILL 'nexmo:fetch-delivery-reports-backfill' every 30 min, 4-HOUR lookback  (this row)
 *   DAILY    'nexmo:fetch-delivery-reports-daily'    02:00, 24-hour lookback         (already listed)
 *
 * FRESH and BACKFILL run the SAME artisan command with different --lookback-minutes, so BACKFILL
 * gets a distinct toggle key ('...-backfill'); its schedule when()-gate was pointed at this key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'nexmo:fetch-delivery-reports-backfill')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'  => 'nexmo:fetch-delivery-reports-backfill',
                'name'     => 'Nexmo DLR Backfill (4-hour window)',
                'schedule' => 'Every 30 minutes (4-hour lookback)',
                'enabled'  => 1,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_job_settings')) {
            DB::table('cron_job_settings')
                ->where('command', 'nexmo:fetch-delivery-reports-backfill')
                ->delete();
        }
    }
};
