<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FundsAlertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:funds-check {--debug : Run in debug mode} {--status : Check status only without alerts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check wallet values and send alerts if funds are low';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $debug = $this->option('debug');
        $statusOnly = $this->option('status');

        $this->info('Starting Funds Check...');

        try {
            $currentHour = (int) Carbon::now()->format('G');

            // Determine alert levels based on time or debug mode
            if ($currentHour == 6 || $currentHour == 17 || $debug || $statusOnly) {
                // Status check mode - higher thresholds (won't trigger easily)
                $allowedLevel1 = 500000000;
                $allowedLevel2 = 100000000;
                $allowedLevel3 = 100000000;
                $allowedLevel4 = 100000000;
                $smsFrom = 'FundsStatus';
            } else {
                // Alert mode - normal thresholds
                $allowedLevel1 = 500;
                $allowedLevel2 = 100000;
                $allowedLevel3 = 0;
                $allowedLevel4 = 250;
                $smsFrom = 'FundsAlert';
            }

            $alerts = [];
            $doSend = false;

            // Check various wallet/fund levels here
            // This is a placeholder - you need to add actual fund checking logic
            // based on your specific requirements (e.g., checking Vonage/Nexmo balance)

            // Example: Check total client wallet exposure
            $walletExposure = $this->getTotalWalletExposure();
            $this->info("Total Wallet Exposure: £" . number_format($walletExposure, 2));

            // Example: Check supplier balance (if API available)
            // $supplierBalance = $this->checkSupplierBalance();

            // Add your specific fund checking logic here
            // if ($someBalance < $allowedLevel1) {
            //     $alerts[] = "Warning: Some balance is low: £" . number_format($someBalance, 2);
            //     $doSend = true;
            // }

            if ($statusOnly) {
                $this->displayStatus($walletExposure);
                return 0;
            }

            if ($doSend && !empty($alerts)) {
                $this->sendAlerts($alerts, $smsFrom, $debug);
            } else {
                $this->info('No alerts to send. All funds are within acceptable levels.');
            }

            $this->info('Funds Check completed successfully!');
        } catch (\Exception $e) {
            $this->error('Error checking funds: ' . $e->getMessage());
            \Log::error('FundsAlert Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    /**
     * Get total wallet exposure
     * Only includes users with migration_flag = 'new' (migrated to new system)
     */
    protected function getTotalWalletExposure()
    {
        $excludedBigids = [
            'q43786f4ae53946dfa8aa3def2fbd53e',
            '6641b01402fe76dd6656c16bc9c38700',
            '65f050e205dff82f529eae1c6c133bb9',
            '73419c0c137c96c84a4490545e731838',
            'v9vex6kfd8d424b6978je2er53c65dfb',
            'a33b52c6e9gd72f94fe6dbb6ccfdc57c',
        ];

        // Use CAST to SIGNED to prevent "DECIMAL UNSIGNED value is out of range" error
        // when smsg_server1_sent + smsg_server2_sent > smsg_wallet
        $result = DB::table('users')
            ->selectRaw('SUM(GREATEST(CAST(smsg_wallet AS SIGNED) - CAST(smsg_server1_sent AS SIGNED) - CAST(smsg_server2_sent AS SIGNED), 0)) as thewallet')
            ->whereNotIn('bigid', $excludedBigids)
            ->where('migration_flag', 'new') // Only process users migrated to new system
            ->first();

        return $result->thewallet ?? 0;
    }

    /**
     * Display current status
     */
    protected function displayStatus($walletExposure)
    {
        $this->newLine();
        $this->info('=== FUNDS STATUS REPORT ===');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Client Wallet Exposure', '£' . number_format($walletExposure, 2)],
                ['Check Time', Carbon::now()->format('Y-m-d H:i:s')],
            ]
        );

        $this->newLine();
        $this->info('Add additional balance checks as needed.');
    }

    /**
     * Send alerts via email and/or SMS
     */
    protected function sendAlerts($alerts, $smsFrom, $debug = false)
    {
        $this->warn('Sending alerts:');
        foreach ($alerts as $alert) {
            $this->line(" - {$alert}");
        }

        // Send email alert
        $recipients = $debug
            ? [config('reports.debug_recipient')]
            : config('reports.funds_alert_recipients');

        $alertData = [
            'subject' => "SMS Expert {$smsFrom} Alert",
            'alert_type' => $smsFrom,
            'alerts' => $alerts,
            'wallet_exposure' => $this->getTotalWalletExposure(),
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        try {
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\FundsAlertMail',
                    trim($recipient),
                    ['alert_data' => $alertData],
                    [],
                    10 // High priority
                );
            }

            $this->info('Email alert queued to: ' . implode(', ', $recipients));
        } catch (\Exception $e) {
            $this->error('Failed to queue alert email: ' . $e->getMessage());
        }

        // Optionally send SMS alert
        // $this->sendSmsAlert($alertMessage, $smsFrom);
    }

    /**
     * Send SMS alert (optional)
     */
    protected function sendSmsAlert($message, $smsFrom)
    {
        // Implement SMS sending logic here if needed
        // Example using your existing SMS infrastructure:
        /*
        try {
            $smsService = app(\App\Services\Queue\SmsQueueService::class);
            $smsService->queueSms([
                'mobile_number' => config('alerts.sms_number'),
                'message' => substr($message, 0, 160),
                'sender_id' => $smsFrom,
                'priority' => 1,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send SMS alert: ' . $e->getMessage());
        }
        */
    }
}
