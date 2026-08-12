<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\CronErrorNotificationMail;
use App\Models\CronJobLog;
use App\Models\CronJobSetting;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// Helper function for date-wise cron log paths
// Structure: storage/logs/{date}/cron/{CronName}.log
function getCronLogPath(string $cronName): string
{
    $dateFolder = storage_path('logs/' . date('Y-m-d') . '/cron');
    if (!is_dir($dateFolder)) {
        @mkdir($dateFolder, 0755, true);
    }
    // Sanitize cron name for filename
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $cronName) . '.log';
    return $dateFolder . '/' . $filename;
}

// Raw stdout/stderr capture path for a cron command.
// IMPORTANT: this MUST be a different file from getCronLogPath(). Commands that use
// CronLogService (LogsCronActivity trait) already write their structured log to
// {CronName}.log via File::append(). If ->appendOutputTo() also redirects the shell's
// stdout/stderr to that SAME file, Windows raises "Resource temporarily unavailable"
// (two writers, one handle) and the command aborts before doing any work. Capturing the
// raw console output in a separate {CronName}.output.log avoids that collision while still
// preserving catastrophic-crash output that the in-process logger can't record.
function getCronOutputPath(string $cronName): string
{
    $dateFolder = storage_path('logs/' . date('Y-m-d') . '/cron');
    if (!is_dir($dateFolder)) {
        @mkdir($dateFolder, 0755, true);
    }
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $cronName) . '.output.log';
    return $dateFolder . '/' . $filename;
}

// Legacy function - kept for backward compatibility
function getDateWiseLogPath(string $filename): string
{
    return getCronLogPath(pathinfo($filename, PATHINFO_FILENAME));
}

// Helper function for cron failure handling
function handleCronFailure(string $command, string $description): void
{
    if (!config('exception.email_enabled', false)) {
        return;
    }
    try {
        $cronData = [
            'command' => $command,
            'description' => $description,
            'timestamp' => now()->toDateTimeString(),
            'environment' => app()->environment(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
        ];
        $recipients = config('exception.email_recipients', []);
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                Mail::to(trim($recipient))->send(new CronErrorNotificationMail($cronData));
            }
        }
        Log::error('Cron job failed', $cronData);
    } catch (\Exception $e) {
        Log::error('Failed to send cron error notification', [
            'error' => $e->getMessage(),
            'command' => $command,
        ]);
    }
}

// Helper function to log cron start
function logCronStart(string $command): void
{
    try {
        CronJobLog::create([
            'command' => $command,
            'status' => 'running',
            'started_at' => now(),
        ]);
    } catch (\Exception $e) {
        Log::error("Failed to log cron start for {$command}: " . $e->getMessage());
    }
}

// Helper function to log cron success
function logCronSuccess(string $command): void
{
    try {
        CronJobLog::where('command', $command)
            ->where('status', 'running')
            ->latest()
            ->first()
            ?->update(['status' => 'success', 'finished_at' => now()]);
    } catch (\Exception $e) {
        Log::error("Failed to log cron success for {$command}: " . $e->getMessage());
    }
}

// Helper function to log cron failure
function logCronFailure(string $command): void
{
    try {
        CronJobLog::where('command', $command)
            ->where('status', 'running')
            ->latest()
            ->first()
            ?->update(['status' => 'failed', 'finished_at' => now()]);
    } catch (\Exception $e) {
        Log::error("Failed to log cron failure for {$command}: " . $e->getMessage());
    }
}

// Helper function to log cron skip (when disabled)
function logCronSkip(string $command): void
{
    try {
        CronJobLog::create([
            'command' => $command,
            'status' => 'skipped',
            'started_at' => now(),
            'finished_at' => now(),
            'output' => 'Cron job is disabled',
        ]);
    } catch (\Exception $e) {
        Log::info("Cron {$command} skipped (disabled)");
    }
}

// Helper function to check if cron is enabled
function isCronEnabled(string $command): bool
{
    try {
        return CronJobSetting::isEnabled($command);
    } catch (\Exception $e) {
        // If table doesn't exist yet (before migration), return true
        return true;
    }
}

// =====================================================================
// SCHEDULED COMMANDS
// =====================================================================

// Process scheduled SMS messages every minute
Schedule::command('sms:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('sms:process-scheduled'))
    ->appendOutputTo(getCronOutputPath('ProcessScheduledSms'))
    ->before(fn() => logCronStart('sms:process-scheduled'))
    ->onSuccess(fn() => logCronSuccess('sms:process-scheduled'))
    ->onFailure(function () {
        logCronFailure('sms:process-scheduled');
        handleCronFailure('sms:process-scheduled', 'Process Scheduled SMS Failed');
    });

// Requeue today's TECHNICALLY-failed SMS at 23:00 — re-enqueues sends that failed for a transient
// reason (SMPP error, throttle, connection drop, UCS2 split, lock timeout) so they get another
// attempt. EXCLUDES blacklist (opt-out — compliance), insufficient funds, disabled and scheduled
// rows. Each retry updates the SAME smsg_log row (matched by bigid+id), so no duplicate/charge.
Schedule::command('sms:requeue-failed')
    ->dailyAt('23:00')
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('sms:requeue-failed'))
    ->appendOutputTo(getCronOutputPath('RequeueFailedSms'))
    ->before(fn() => logCronStart('sms:requeue-failed'))
    ->onSuccess(fn() => logCronSuccess('sms:requeue-failed'))
    ->onFailure(function () {
        logCronFailure('sms:requeue-failed');
        handleCronFailure('sms:requeue-failed', 'Requeue Failed SMS Failed');
    });

// Send scheduled emails every minute
Schedule::command('emails:send-schedule')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('emails:send-schedule'))
    ->appendOutputTo(getCronLogPath('SendScheduledEmails'))
    ->before(fn() => logCronStart('emails:send-schedule'))
    ->onSuccess(fn() => logCronSuccess('emails:send-schedule'))
    ->onFailure(function () {
        logCronFailure('emails:send-schedule');
        handleCronFailure('emails:send-schedule', 'Email Schedule Send Failed');
    });

// SMS pricing update daily at 00:05
Schedule::command('sms:update-pricing')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('sms:update-pricing'))
    ->appendOutputTo(getCronLogPath('UpdateSmsPricing'))
    ->before(fn() => logCronStart('sms:update-pricing'))
    ->onSuccess(fn() => logCronSuccess('sms:update-pricing'))
    ->onFailure(function () {
        logCronFailure('sms:update-pricing');
        handleCronFailure('sms:update-pricing', 'SMS Pricing Update Failed');
    });

// Rebuild the no-TTL TableCache reference caches (country, smsg_route, ofcom) daily at 00:10.
// Event-driven rebuild hooks keep these fresh on in-app edits; this daily pass is the safety net
// for rows edited DIRECTLY in the database (which bypass the hooks). Runs after sms:update-pricing.
Schedule::command('cache:rebuild-tables')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('cache:rebuild-tables'))
    ->appendOutputTo(getCronLogPath('RebuildTableCache'))
    ->before(fn() => logCronStart('cache:rebuild-tables'))
    ->onSuccess(fn() => logCronSuccess('cache:rebuild-tables'))
    ->onFailure(function () {
        logCronFailure('cache:rebuild-tables');
        handleCronFailure('cache:rebuild-tables', 'TableCache Rebuild Failed');
    });

// Per-customer dashboard rollup (customer_daily_stats). Nightly at 00:05 rolls up YESTERDAY
// (the just-completed day the /dashboard cards read from).
Schedule::command('customer:build-daily-stats --days=1')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('customer:build-daily-stats'))
    ->appendOutputTo(getCronLogPath('CustomerDailyStats'))
    ->before(fn() => logCronStart('customer:build-daily-stats'))
    ->onSuccess(fn() => logCronSuccess('customer:build-daily-stats'))
    ->onFailure(function () {
        logCronFailure('customer:build-daily-stats');
        handleCronFailure('customer:build-daily-stats', 'Customer Daily Stats Build Failed');
    });

// 01:00 CATCH-UP: re-roll the last 3 days (self-heals a missed 00:05 run), prune to 365 days,
// and sync-tables (drop rollup rows for any month whose smsg_log shard has been archived away).
Schedule::command('customer:build-daily-stats --days=3 --prune --sync-tables')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('customer:build-daily-stats'))
    ->appendOutputTo(getCronLogPath('CustomerDailyStatsCatchup'))
    ->onFailure(function () {
        logCronFailure('customer:build-daily-stats-catchup');
        handleCronFailure('customer:build-daily-stats', 'Customer Daily Stats Catch-up Failed');
    });

// Send wallet reminders daily at 06:15
Schedule::command('wallet:send-reminders')
    ->dailyAt('06:15')
    ->withoutOverlapping()
    ->when(fn() => isCronEnabled('wallet:send-reminders'))
    ->appendOutputTo(getCronLogPath('WalletReminders'))
    ->before(fn() => logCronStart('wallet:send-reminders'))
    ->onSuccess(fn() => logCronSuccess('wallet:send-reminders'))
    ->onFailure(function () {
        logCronFailure('wallet:send-reminders');
        handleCronFailure('wallet:send-reminders', 'Wallet Reminders Failed');
    });

// Delivery receipt push every 5 minutes
Schedule::command('delivery-receipt:push default')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('delivery-receipt:push'))
    ->appendOutputTo(getCronLogPath('DeliveryReceiptPush'))
    ->before(fn() => logCronStart('delivery-receipt:push'))
    ->onSuccess(fn() => logCronSuccess('delivery-receipt:push'))
    ->onFailure(function () {
        logCronFailure('delivery-receipt:push');
        handleCronFailure('delivery-receipt:push', 'Delivery Receipt Push Failed');
    });

// DLR callback row sweeper — every 5 minutes, releases rows stuck at
// status='doing' for >10 minutes. Backstop for the case where the consumer
// SIGKILL'd between atomic claim and final status update; the exception
// rollback inside ConsumeDlrCallbacks only fires for catchable Throwables.
Schedule::command('dlr-callback:sweep-stuck')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('dlr-callback:sweep-stuck'))
    ->appendOutputTo(getCronLogPath('DlrCallbackSweep'))
    ->before(fn() => logCronStart('dlr-callback:sweep-stuck'))
    ->onFailure(function () {
        logCronFailure('dlr-callback:sweep-stuck');
        handleCronFailure('dlr-callback:sweep-stuck', 'DLR Callback Sweeper Failed');
    });

// DLR callback consumer watchdog — every minute, mirrors OLD SYSTEM
// v3_itagg_daemon_dreceipt_push_monitor.php. Reads the consumer's heartbeat
// touchfile and alerts (via SmppErrorAlertService) if it's stale > 120s.
Schedule::command('dlr-callback:watchdog')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('dlr-callback:watchdog'))
    ->appendOutputTo(getCronLogPath('DlrCallbackWatchdog'))
    ->before(fn() => logCronStart('dlr-callback:watchdog'))
    ->onFailure(function () {
        logCronFailure('dlr-callback:watchdog');
        handleCronFailure('dlr-callback:watchdog', 'DLR Callback Consumer Frozen');
    });

// Periodic restart of the dlr-callback:consume worker — mirrors OLD SYSTEM
// twice-daily pkill of itagg_daemon_dreceipt_push_multi.php at 02:25 and 03:30.
// supervisor's autorestart=true brings the worker back up immediately.
// Uses supervisorctl on Linux; on Windows manually restart workers.
Schedule::exec('supervisorctl restart dlr-callback-consume')
    ->dailyAt('02:25')
    ->onOneServer()
    ->when(fn() => isCronEnabled('dlr-callback:restart') && PHP_OS_FAMILY === 'Linux')
    ->appendOutputTo(getCronLogPath('DlrCallbackRestart'));

Schedule::exec('supervisorctl restart dlr-callback-consume')
    ->dailyAt('03:30')
    ->onOneServer()
    ->when(fn() => isCronEnabled('dlr-callback:restart') && PHP_OS_FAMILY === 'Linux')
    ->appendOutputTo(getCronLogPath('DlrCallbackRestart'));

// Sync virtual numbers daily at 02:30
Schedule::command('virtualnumbers:sync')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('virtualnumbers:sync'))
    ->appendOutputTo(getCronLogPath('VirtualNumbersSync'))
    ->before(fn() => logCronStart('virtualnumbers:sync'))
    ->onSuccess(fn() => logCronSuccess('virtualnumbers:sync'))
    ->onFailure(function () {
        logCronFailure('virtualnumbers:sync');
        handleCronFailure('virtualnumbers:sync', 'Virtual Numbers Sync Failed');
    });

// Prune stale RabbitMQ queues daily. Deletes ONLY queues that are empty, have no
// consumers, AND have been idle >= 180 min, AND are NOT in the protected core-queue
// allowlist (see PruneRabbitMQQueues). Stops orphan queues from OTHER apps sharing the
// broker piling up. Togglable in Process Monitor (cron key 'rabbitmq:prune-queues').
Schedule::command('rabbitmq:prune-queues --force --idle-minutes=180')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('rabbitmq:prune-queues'))
    ->appendOutputTo(getCronLogPath('RabbitMQPruneQueues'))
    ->before(fn() => logCronStart('rabbitmq:prune-queues'))
    ->onSuccess(fn() => logCronSuccess('rabbitmq:prune-queues'))
    ->onFailure(function () {
        logCronFailure('rabbitmq:prune-queues');
        handleCronFailure('rabbitmq:prune-queues', 'RabbitMQ Prune Queues Failed');
    });

// ── Nexmo/Vonage Reports-API DLR reconciliation — 3-tier overlapping window ──────────
// The Reports API window filters by SUBMIT time, and a DLR can finalise anywhere from a
// few seconds to hours after send. A single window can't be both gap-free AND efficient,
// so coverage is layered into 3 tiers. All share the SAME queue -> consumer -> idempotency
// guard (deliverytime2 set = skip), so the tier overlaps are free (no double updates):
//   FRESH    — every minute,   15-min lookback  → ~95% of DLRs (finalise within minutes)
//   BACKFILL — every 30 min,   4-hour lookback  → DLRs that settle 15 min–4 h after send
//   DAILY    — 02:00,          24-hour lookback → the long tail (validity-period expiries)
// Each tier has its OWN monitor toggle so they can be enabled/disabled independently:
//   FRESH -> 'nexmo:fetch-delivery-reports', BACKFILL -> 'nexmo:fetch-delivery-reports-backfill',
//   DAILY -> 'nexmo:fetch-delivery-reports-daily'.

// FRESH: every minute, small 15-min window (was 50 min — 3x oversized for a 1-min cadence).
Schedule::command('nexmo:fetch-delivery-reports --lookback-minutes=15')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('nexmo:fetch-delivery-reports'))
    ->appendOutputTo(getCronLogPath('NexmoDeliveryReports'))
    ->before(fn() => logCronStart('nexmo:fetch-delivery-reports'))
    ->onSuccess(fn() => logCronSuccess('nexmo:fetch-delivery-reports'))
    ->onFailure(function () {
        logCronFailure('nexmo:fetch-delivery-reports');
        handleCronFailure('nexmo:fetch-delivery-reports', 'Nexmo Delivery Reports Failed');
    });

// BACKFILL: every 30 min, 4-hour window — catches DLRs that finalise AFTER the FRESH window
// closes but well before the daily catch-up (retries, temporary absent subscriber). Cheap
// because it runs rarely; the idempotency guard dedupes against what FRESH already applied.
Schedule::command('nexmo:fetch-delivery-reports --lookback-minutes=240')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('nexmo:fetch-delivery-reports-backfill'))
    ->appendOutputTo(getCronLogPath('NexmoDeliveryReportsBackfill'))
    ->before(fn() => logCronStart('nexmo:fetch-delivery-reports-backfill'))
    ->onSuccess(fn() => logCronSuccess('nexmo:fetch-delivery-reports-backfill'))
    ->onFailure(function () {
        logCronFailure('nexmo:fetch-delivery-reports-backfill');
        handleCronFailure('nexmo:fetch-delivery-reports-backfill', 'Nexmo DLR Backfill Failed');
    });

// Daily 02:00 UK-time catch-up: re-fetch the last 24h of Nexmo DLRs and back-fill any
// SMS whose delivery status was missed by the every-minute cron above (pending,
// migration_flag='new'). Reuses the same queue -> NexmoDeliveryQueueService pipeline,
// which updates smsg_log, pushes the customer DLR webhook, and emails on push failure.
Schedule::command('nexmo:fetch-delivery-reports-daily')
    ->dailyAt('02:00')
    ->timezone('Europe/London')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('nexmo:fetch-delivery-reports-daily'))
    ->appendOutputTo(getCronLogPath('NexmoDeliveryReportsDaily'))
    ->before(fn() => logCronStart('nexmo:fetch-delivery-reports-daily'))
    ->onSuccess(fn() => logCronSuccess('nexmo:fetch-delivery-reports-daily'))
    ->onFailure(function () {
        logCronFailure('nexmo:fetch-delivery-reports-daily');
        handleCronFailure('nexmo:fetch-delivery-reports-daily', 'Nexmo Daily DLR Catch-up Failed');
    });

// Database Tidy daily at 02:30
Schedule::command('db:tidy --all')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('db:tidy'))
    ->appendOutputTo(getCronLogPath('DatabaseTidy'))
    ->before(fn() => logCronStart('db:tidy'))
    ->onSuccess(fn() => logCronSuccess('db:tidy'))
    ->onFailure(function () {
        logCronFailure('db:tidy');
        handleCronFailure('db:tidy', 'Database Tidy Failed');
    });

// XML to SMS Gateway every minute
Schedule::command('sms:xml-gateway --inbox --limit=20')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('sms:xml-gateway'))
    ->appendOutputTo(getCronLogPath('XmlToSmsGateway'))
    ->before(fn() => logCronStart('sms:xml-gateway'))
    ->onSuccess(fn() => logCronSuccess('sms:xml-gateway'))
    ->onFailure(function () {
        logCronFailure('sms:xml-gateway');
        handleCronFailure('sms:xml-gateway', 'XML to SMS Gateway Failed');
    });

// =====================================================================
// DAILY REPORTS & ALERTS
// =====================================================================

// Daily Stats Report - runs at 6:00 AM
Schedule::command('report:daily-stats')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('report:daily-stats'))
    ->appendOutputTo(getCronLogPath('DailyStatsReport'))
    ->before(fn() => logCronStart('report:daily-stats'))
    ->onSuccess(fn() => logCronSuccess('report:daily-stats'))
    ->onFailure(function () {
        logCronFailure('report:daily-stats');
        handleCronFailure('report:daily-stats', 'Daily Stats Report Failed');
    });

// Virtual Number Expiry Report - runs Monday at 6:00 AM
Schedule::command('report:virtual-number-expiry')
    ->weeklyOn(1, '06:00') // Monday at 6:00 AM
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('report:virtual-number-expiry'))
    ->appendOutputTo(getCronLogPath('VirtualNumberExpiryReport'))
    ->before(fn() => logCronStart('report:virtual-number-expiry'))
    ->onSuccess(fn() => logCronSuccess('report:virtual-number-expiry'))
    ->onFailure(function () {
        logCronFailure('report:virtual-number-expiry');
        handleCronFailure('report:virtual-number-expiry', 'Virtual Number Expiry Report Failed');
    });

// Funds Alert Check - runs at 6:00 AM and 5:00 PM
Schedule::command('alert:funds-check --status')
    ->twiceDaily(6, 17) // 6:00 AM and 5:00 PM
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('alert:funds-check'))
    ->appendOutputTo(getCronLogPath('FundsAlertCheck'))
    ->before(fn() => logCronStart('alert:funds-check'))
    ->onSuccess(fn() => logCronSuccess('alert:funds-check'))
    ->onFailure(function () {
        logCronFailure('alert:funds-check');
        handleCronFailure('alert:funds-check', 'Funds Alert Check Failed');
    });

// Funds Alert (hourly check for critical alerts - excluding 6 AM and 5 PM)
Schedule::command('alert:funds-check')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(function () {
        $hour = (int) now()->format('G');
        // Skip at 6 AM and 5 PM (covered by status check above)
        return isCronEnabled('alert:funds-check') && !in_array($hour, [6, 17]);
    })
    ->appendOutputTo(getCronLogPath('FundsAlertHourly'))
    ->before(fn() => logCronStart('alert:funds-check-hourly'))
    ->onSuccess(fn() => logCronSuccess('alert:funds-check-hourly'))
    ->onFailure(function () {
        logCronFailure('alert:funds-check-hourly');
        handleCronFailure('alert:funds-check-hourly', 'Funds Alert Hourly Check Failed');
    });

// =====================================================================
// SMPP CHECKS
// =====================================================================

// SMPP Regular Checks - runs hourly from 6 AM to 9 PM (at minute 0)
// Original cron: 0 6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21 * * *
Schedule::command('smpp:regular-checks')
    ->hourlyAt(0)
    ->withoutOverlapping()
    ->runInBackground()
    ->when(function () {
        $hour = (int) now()->format('G');
        // Only run between 6 AM and 9 PM
        return isCronEnabled('smpp:regular-checks') && $hour >= 6 && $hour <= 21;
    })
    ->appendOutputTo(getCronLogPath('SmppRegularChecks'))
    ->before(fn() => logCronStart('smpp:regular-checks'))
    ->onSuccess(fn() => logCronSuccess('smpp:regular-checks'))
    ->onFailure(function () {
        logCronFailure('smpp:regular-checks');
        handleCronFailure('smpp:regular-checks', 'SMPP Regular Checks Failed');
    });

// =====================================================================
// SMS HEARTBEAT MONITORING
// =====================================================================

// SMS Heartbeat - runs every minute (sends at :00,:10,:20,:30,:40,:50 and monitors at :08,:18,:28,:38,:48,:58)
// Original cron: * * * * *
Schedule::command('sms:heartbeat')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('sms:heartbeat'))
    ->appendOutputTo(getCronLogPath('SmsHeartbeat'))
    ->before(fn() => logCronStart('sms:heartbeat'))
    ->onSuccess(fn() => logCronSuccess('sms:heartbeat'))
    ->onFailure(function () {
        logCronFailure('sms:heartbeat');
        handleCronFailure('sms:heartbeat', 'SMS Heartbeat Failed');
    });

// =====================================================================
// POOLED VIRTUALS MONITORING
// =====================================================================

// Pooled Virtuals Monitor - runs every minute (fund sweeping 2AM-11PM, pool check at 6AM)
// Original cron: * * * * *
Schedule::command('pooledvirts:monitor')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('pooledvirts:monitor'))
    ->appendOutputTo(getCronLogPath('PooledVirtsMonitor'))
    ->before(fn() => logCronStart('pooledvirts:monitor'))
    ->onSuccess(fn() => logCronSuccess('pooledvirts:monitor'))
    ->onFailure(function () {
        logCronFailure('pooledvirts:monitor');
        handleCronFailure('pooledvirts:monitor', 'Pooled Virts Monitor Failed');
    });

// =====================================================================
// URL FORWARD DAEMON
// =====================================================================

// URL Forward Daemon - runs every minute to process incoming SMS URL forwards
// Original cron: * * * * * (daemon process, now converted to scheduled task)
Schedule::command('urlforward:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('urlforward:process'))
    ->appendOutputTo(getCronLogPath('UrlForwardDaemon'))
    ->before(fn() => logCronStart('urlforward:process'))
    ->onSuccess(fn() => logCronSuccess('urlforward:process'))
    ->onFailure(function () {
        logCronFailure('urlforward:process');
        handleCronFailure('urlforward:process', 'URL Forward Daemon Failed');
    });

// =====================================================================
// CAMPAIGN REPORT
// =====================================================================

// Campaign Report - runs hourly from 5 AM to 9 PM (at minute 0)
// Original cron: 0 5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21 * * *
Schedule::command('campaign:report')
    ->hourlyAt(0)
    ->withoutOverlapping()
    ->runInBackground()
    ->when(function () {
        $hour = (int) now()->format('G');
        // Only run between 5 AM and 9 PM
        return isCronEnabled('campaign:report') && $hour >= 5 && $hour <= 21;
    })
    ->appendOutputTo(getCronLogPath('CampaignReport'))
    ->before(fn() => logCronStart('campaign:report'))
    ->onSuccess(fn() => logCronSuccess('campaign:report'))
    ->onFailure(function () {
        logCronFailure('campaign:report');
        handleCronFailure('campaign:report', 'Campaign Report Generation Failed');
    });

// =====================================================================
// SCHEDULED NOTIFICATIONS
// =====================================================================

// Process scheduled notifications - runs every minute to check for due notifications
Schedule::command('notifications:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('notifications:process-scheduled'))
    ->appendOutputTo(getCronLogPath('ScheduledNotifications'))
    ->before(fn() => logCronStart('notifications:process-scheduled'))
    ->onSuccess(fn() => logCronSuccess('notifications:process-scheduled'))
    ->onFailure(function () {
        logCronFailure('notifications:process-scheduled');
        handleCronFailure('notifications:process-scheduled', 'Scheduled Notifications Processing Failed');
    });

// =====================================================================
// API ERROR LOG CLEANUP
// =====================================================================

// Clean up old API error logs - runs daily at 3:00 AM
Schedule::command('api:clean-error-logs')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('api:clean-error-logs'))
    ->appendOutputTo(getCronLogPath('ApiErrorCleanup'))
    ->before(fn() => logCronStart('api:clean-error-logs'))
    ->onSuccess(fn() => logCronSuccess('api:clean-error-logs'))
    ->onFailure(function () {
        logCronFailure('api:clean-error-logs');
        handleCronFailure('api:clean-error-logs', 'API Error Logs Cleanup Failed');
    });

// =====================================================================
// CRON & API LOG CLEANUP
// =====================================================================

// Clean old cron and API logs - runs daily at 03:30 AM
Schedule::command('logs:clean --days=30 --type=all')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('logs:clean'))
    ->appendOutputTo(getCronLogPath('CleanOldLogs'))
    ->before(fn() => logCronStart('logs:clean'))
    ->onSuccess(fn() => logCronSuccess('logs:clean'))
    ->onFailure(function () {
        logCronFailure('logs:clean');
        handleCronFailure('logs:clean', 'Cron/API Log Cleanup Failed');
    });

// =====================================================================
// EXCHANGE RATE UPDATE
// =====================================================================

// Fetch EUR to GBP exchange rate and convert Vonage prices - runs daily at 00:15
Schedule::command('exchange-rate:fetch --convert-prices')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('exchange-rate:fetch'))
    ->appendOutputTo(getCronLogPath('ExchangeRateFetch'))
    ->before(fn() => logCronStart('exchange-rate:fetch'))
    ->onSuccess(fn() => logCronSuccess('exchange-rate:fetch'))
    ->onFailure(function () {
        logCronFailure('exchange-rate:fetch');
        handleCronFailure('exchange-rate:fetch', 'Exchange Rate Fetch Failed');
    });

// =====================================================================
// DASHBOARD STATS
// =====================================================================

// Build admin dashboard daily stats - runs daily at midnight (00:00)
// Recomputes a rolling window so the dashboard reads pre-aggregated data
// instead of running heavy live queries across all smsg_log tables.
Schedule::command('dashboard:build-stats')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('dashboard:build-stats'))
    ->appendOutputTo(getCronLogPath('DashboardStats'))
    ->before(fn() => logCronStart('dashboard:build-stats'))
    ->onSuccess(fn() => logCronSuccess('dashboard:build-stats'))
    ->onFailure(function () {
        logCronFailure('dashboard:build-stats');
        handleCronFailure('dashboard:build-stats', 'Dashboard Stats Build Failed');
    });

// Monthly safety-net rebuild — runs --all on the 1st of every month at 02:00.
// Ensures any month that the daily 40-day window missed (e.g. when a new
// smsg_log_YYMM archive rolls over) gets backfilled automatically.
// First-time install still needs a manual: `php artisan dashboard:build-stats --all`.
Schedule::command('dashboard:build-stats --all')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('dashboard:build-stats-full'))
    ->appendOutputTo(getCronLogPath('DashboardStatsFull'))
    ->before(fn() => logCronStart('dashboard:build-stats-full'))
    ->onSuccess(fn() => logCronSuccess('dashboard:build-stats-full'))
    ->onFailure(function () {
        logCronFailure('dashboard:build-stats-full');
        handleCronFailure('dashboard:build-stats-full', 'Dashboard Stats Full Rebuild Failed');
    });

// =====================================================================
// OLD API USAGE ALERT (migrated customers still on the old API)
// =====================================================================

// Detect migrated customers still sending via the old API - runs daily at 00:10
Schedule::command('alert:old-api-usage')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('alert:old-api-usage'))
    ->appendOutputTo(getCronLogPath('OldApiUsageAlert'))
    ->before(fn() => logCronStart('alert:old-api-usage'))
    ->onSuccess(fn() => logCronSuccess('alert:old-api-usage'))
    ->onFailure(function () {
        logCronFailure('alert:old-api-usage');
        handleCronFailure('alert:old-api-usage', 'Old API Usage Detection Failed');
    });

// =====================================================================
// NEXMO PRICING UPDATE
// =====================================================================

// Fetch Vonage/Nexmo country pricing from API - runs daily at 00:30 (after exchange rate)
// Uses highest network price per country, skips manually set prices
Schedule::command('nexmo:fetch-pricing')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('nexmo:fetch-pricing'))
    ->appendOutputTo(getCronLogPath('NexmoPricingFetch'))
    ->before(fn() => logCronStart('nexmo:fetch-pricing'))
    ->onSuccess(fn() => logCronSuccess('nexmo:fetch-pricing'))
    ->onFailure(function () {
        logCronFailure('nexmo:fetch-pricing');
        handleCronFailure('nexmo:fetch-pricing', 'Nexmo Pricing Fetch Failed');
    });

// =====================================================================
// MIGRATED CUSTOMERS DAILY REPORT
// =====================================================================

// Emails total migrated, yesterday's migrations and pending-to-migrate counts
// to MIGRATION_REPORT_EMAIL - runs 05:00 UK (Europe/London).
Schedule::command('migration:daily-report')
    ->dailyAt('05:00')
    ->timezone('Europe/London')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(fn() => isCronEnabled('migration:daily-report'))
    ->appendOutputTo(getCronLogPath('MigratedCustomersReport'))
    ->before(fn() => logCronStart('migration:daily-report'))
    ->onSuccess(fn() => logCronSuccess('migration:daily-report'))
    ->onFailure(function () {
        logCronFailure('migration:daily-report');
        handleCronFailure('migration:daily-report', 'Migrated Customers Report Failed');
    });
