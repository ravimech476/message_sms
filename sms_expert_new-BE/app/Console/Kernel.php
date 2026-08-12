<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SendSmsCommand::class,
        \App\Console\Commands\SendScheduledMessages::class,
        \App\Console\Commands\SendScheduledEmails::class,
        \App\Console\Commands\CheckPendingSMS::class,
        \App\Console\Commands\UpdateSmsPricing::class,
        \App\Console\Commands\UpdateSmsPricingCurl::class,
        \App\Console\Commands\DeliveryReceiptPushCommand::class,
        \App\Console\Commands\DeliveryReceiptMonitorCommand::class,
        \App\Console\Commands\ConsumeEmailQueueCommand::class,
        \App\Console\Commands\TestEmailQueueCommand::class,
        \App\Console\Commands\EmailQueueStatusCommand::class,
        \App\Console\Commands\ProcessEmailQueueCommand::class,
        \App\Console\Commands\ResetEmailQueueCommand::class,
        \App\Console\Commands\SendWalletRemindersCommand::class,
        \App\Console\Commands\FetchPendingDlrs::class,
        \App\Console\Commands\SmppDlrReceiver::class,
        \App\Console\Commands\TestDlrUpdate::class,
        \App\Console\Commands\ProcessScheduledSms::class,
        \App\Console\Commands\RequeueFailedSms::class,
        \App\Console\Commands\CampaignQueueConsumer::class,
        \App\Console\Commands\SyncVirtualNumbers::class,
        \App\Console\Commands\FetchNexmoDeliveryReports::class,
        \App\Console\Commands\DatabaseTidy::class,
        \App\Console\Commands\ProcessInboundSmsQueue::class,
        \App\Console\Commands\TestSmsSendingService::class,
        \App\Console\Commands\CampaignReportDaemon::class,
        \App\Console\Commands\XmlToSmsGateway::class,
        \App\Console\Commands\DailyStatsReportCommand::class,
        \App\Console\Commands\VirtualNumberExpiryReportCommand::class,
        \App\Console\Commands\FundsAlertCommand::class,
        \App\Console\Commands\SmppRegularChecksCommand::class,
        \App\Console\Commands\SmsHeartbeatCommand::class,
        \App\Console\Commands\PooledVirtsMonitorCommand::class,
        \App\Console\Commands\UrlForwardDaemonCommand::class,
        \App\Console\Commands\ProcessScheduledNotifications::class,
    ];

    /**
     * Define the application's command schedule.
     * 
     * NOTE: All schedules are defined in routes/console.php
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // All scheduled commands are defined in routes/console.php
        // This method is kept empty intentionally
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        // Load the routes for console commands (includes schedules)
        require base_path('routes/console.php');
    }
}
