<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CronJobSetting;

class ReportCronJobsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cronJobs = [
            [
                'command' => 'report:daily-stats',
                'name' => 'Daily Stats Report',
                'description' => 'Generate and send daily SMS statistics report to management (runs at 6:00 AM)',
                'enabled' => true,
                'schedule' => 'Daily at 6:00 AM',
            ],
            [
                'command' => 'report:virtual-number-expiry',
                'name' => 'Virtual Number Expiry Report',
                'description' => 'Generate and send virtual number and keyword expiry report to care team (runs Monday at 6:00 AM)',
                'enabled' => true,
                'schedule' => 'Weekly on Monday at 6:00 AM',
            ],
            [
                'command' => 'alert:funds-check',
                'name' => 'Funds Alert Check',
                'description' => 'Check wallet values and send alerts if funds are low (runs at 6:00 AM, 5:00 PM, and hourly)',
                'enabled' => true,
                'schedule' => 'Twice daily (6:00 AM, 5:00 PM) + Hourly',
            ],
            [
                'command' => 'smpp:regular-checks',
                'name' => 'SMPP Regular Checks',
                'description' => 'Check CSN SMPP wallet balances, update local client wallets and add SMPP log records (runs hourly 6AM-9PM)',
                'enabled' => true,
                'schedule' => 'Hourly (6:00 AM - 9:00 PM)',
            ],
            [
                'command' => 'sms:heartbeat',
                'name' => 'SMS Heartbeat',
                'description' => 'Send test SMS to network SIMs, track delivery and manage alerts for route monitoring',
                'enabled' => false, // Disabled by default - enable when ready
                'schedule' => 'Every minute',
            ],
            [
                'command' => 'pooledvirts:monitor',
                'name' => 'Pooled Virtuals Monitor',
                'description' => 'Sweep funds from master to sub-accounts and check pool virtuals (fund sweep 2AM-11PM, pool check 6AM)',
                'enabled' => true,
                'schedule' => 'Every minute',
            ],
            [
                'command' => 'urlforward:process',
                'name' => 'URL Forward Daemon',
                'description' => 'Process incoming SMS URL forwarding requests with retry logic and failure notifications',
                'enabled' => true,
                'schedule' => 'Every minute',
            ],
            [
                'command' => 'campaign:report',
                'name' => 'Campaign Report',
                'description' => 'Generate DLR and click statistics for SMS campaigns (runs hourly 5AM-9PM)',
                'enabled' => true,
                'schedule' => 'Hourly (5:00 AM - 9:00 PM)',
            ],
        ];

        foreach ($cronJobs as $cronJob) {
            CronJobSetting::updateOrCreate(
                ['command' => $cronJob['command']],
                $cronJob
            );
        }

        $this->command->info('Report cron jobs seeded successfully!');
    }
}
