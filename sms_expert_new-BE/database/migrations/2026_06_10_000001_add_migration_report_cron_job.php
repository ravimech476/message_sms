<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register the migrated-customers daily report cron in the admin cron manager
     * (Settings > Monitor) so it can be enabled/disabled and monitored.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'migration:daily-report')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'     => 'migration:daily-report',
                'name'        => 'Migrated Customers Daily Report',
                'schedule'    => 'Daily (05:00 UK)',
                'description' => 'Emails a daily migration summary to MIGRATION_REPORT_EMAIL: total migrated customers, customers migrated yesterday, and customers still pending migration.',
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
            ->where('command', 'migration:daily-report')
            ->delete();
    }
};
