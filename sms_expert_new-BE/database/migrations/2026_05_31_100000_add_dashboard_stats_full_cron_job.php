<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register the monthly --all rebuild of dashboard stats so it appears in
     * the admin Process Monitor → Cron Jobs toggle list.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'dashboard:build-stats-full')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'     => 'dashboard:build-stats-full',
                'name'        => 'Build Dashboard Stats (Full Rebuild)',
                'schedule'    => 'Monthly on the 1st (02:00)',
                'description' => 'Safety-net full rebuild — runs dashboard:build-stats --all to backfill any month the daily 40-day rolling window may have missed (e.g. when a new smsg_log_YYMM archive rolls over).',
                'enabled'     => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        DB::table('cron_job_settings')
            ->where('command', 'dashboard:build-stats-full')
            ->delete();
    }
};
