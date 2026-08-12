<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register the old-API-usage detection cron in the admin cron manager.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'alert:old-api-usage')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'     => 'alert:old-api-usage',
                'name'        => 'Old API Usage Alert',
                'schedule'    => 'Daily (00:10)',
                'description' => 'Detects migrated customers still sending SMS through the old API (smsg_log.migration_flag = old) and flags them so the customer dashboard can show a once-per-day switch reminder.',
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
            ->where('command', 'alert:old-api-usage')
            ->delete();
    }
};
