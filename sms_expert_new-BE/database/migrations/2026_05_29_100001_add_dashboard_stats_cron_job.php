<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register the dashboard-stats cron in the admin cron manager.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'dashboard:build-stats')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'     => 'dashboard:build-stats',
                'name'        => 'Build Dashboard Stats',
                'schedule'    => 'Daily (00:00)',
                'description' => 'Pre-computes admin dashboard SMS stats (sent, delivered, profit, cost, user price) into dashboard_daily_stats so the dashboard reads aggregated data instead of querying all smsg_log tables live.',
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
            ->where('command', 'dashboard:build-stats')
            ->delete();
    }
};
