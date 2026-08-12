<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $cronJobs = [
            [
                'command' => 'exchange-rate:fetch',
                'name' => 'Exchange Rate Fetch',
                'description' => 'Fetch EUR to GBP exchange rate from API and update all country prices (daily at 00:15)',
                'enabled' => false,
                'schedule' => 'Daily (00:15)',
            ],
            [
                'command' => 'nexmo:fetch-pricing',
                'name' => 'Nexmo Pricing Fetch',
                'description' => 'Fetch country pricing from Nexmo API using highest network price per country. Skips manual prices. (daily at 00:30)',
                'enabled' => false,
                'schedule' => 'Daily (00:30)',
            ],
            [
                'command' => 'api:clean-error-logs',
                'name' => 'API Error Logs Cleanup',
                'description' => 'Clean up old API error logs from the database (daily at 03:00)',
                'enabled' => false,
                'schedule' => 'Daily (03:00)',
            ],
            [
                'command' => 'logs:clean',
                'name' => 'Cron/API Log Cleanup',
                'description' => 'Clean old cron job logs and API logs older than 30 days (daily at 03:30)',
                'enabled' => false,
                'schedule' => 'Daily (03:30)',
            ],
        ];

        foreach ($cronJobs as $cronJob) {
            // Check if command already exists
            $exists = DB::table('cron_job_settings')
                ->where('command', $cronJob['command'])
                ->exists();

            if (!$exists) {
                DB::table('cron_job_settings')->insert(array_merge($cronJob, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $commands = [
            'exchange-rate:fetch',
            'nexmo:fetch-pricing',
            'api:clean-error-logs',
            'logs:clean',
        ];

        DB::table('cron_job_settings')
            ->whereIn('command', $commands)
            ->delete();
    }
};
