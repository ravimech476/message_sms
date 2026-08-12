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
                'command' => 'report:daily-stats',
                'name' => 'Daily Stats Report',
                'description' => 'Generate and send daily SMS statistics report to management (runs at 6:00 AM)',
                'enabled' => false,
                'schedule' => 'Daily (06:00)',
            ],
            [
                'command' => 'report:virtual-number-expiry',
                'name' => 'Virtual Number Expiry Report',
                'description' => 'Generate and send virtual number and keyword expiry report to care team (runs Monday at 6:00 AM)',
                'enabled' => false,
                'schedule' => 'Monday (06:00)',
            ],
            [
                'command' => 'alert:funds-check',
                'name' => 'Funds Alert Check',
                'description' => 'Check wallet values and send alerts if funds are low (runs at 6:00 AM, 5:00 PM, and hourly)',
                'enabled' => false,
                'schedule' => 'Twice Daily + Hourly',
            ],
            [
                'command' => 'smpp:regular-checks',
                'name' => 'SMPP Regular Checks',
                'description' => 'Check CSN SMPP wallet balances, update local client wallets and add SMPP log records (runs hourly 6AM-9PM)',
                'enabled' => false,
                'schedule' => 'Hourly (6AM-9PM)',
            ],
            [
                'command' => 'sms:heartbeat',
                'name' => 'SMS Heartbeat',
                'description' => 'Send test SMS to network SIMs, track delivery and manage alerts for route monitoring',
                'enabled' => false,
                'schedule' => 'Every minute',
            ],
            [
                'command' => 'pooledvirts:monitor',
                'name' => 'Pooled Virtuals Monitor',
                'description' => 'Sweep funds from master to sub-accounts and check pool virtuals (fund sweep 2AM-11PM, pool check 6AM)',
                'enabled' => false,
                'schedule' => 'Every minute',
            ],
            [
                'command' => 'urlforward:process',
                'name' => 'URL Forward Daemon',
                'description' => 'Process incoming SMS URL forwarding requests with retry logic and failure notifications',
                'enabled' => false,
                'schedule' => 'Every minute',
            ],
            [
                'command' => 'campaign:report',
                'name' => 'Process Campaign Report',
                'description' => 'Generate DLR and click statistics for SMS campaigns (runs hourly 5AM-9PM)',
                'enabled' => false,
                'schedule' => 'Hourly (5AM-9PM)',
            ],
            [
                'command' => 'sms:process-scheduled',
                'name' => 'Process Scheduled SMS',
                'description' => 'Process scheduled SMS messages from smsg_log table - handles both Nexmo and Sinch providers',
                'enabled' => false,
                'schedule' => 'Every minute',
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
            'report:daily-stats',
            'report:virtual-number-expiry',
            'alert:funds-check',
            'smpp:regular-checks',
            'sms:heartbeat',
            'pooledvirts:monitor',
            'urlforward:process',
            'campaign:report',
        ];

        DB::table('cron_job_settings')
            ->whereIn('command', $commands)
            ->delete();
    }
};
