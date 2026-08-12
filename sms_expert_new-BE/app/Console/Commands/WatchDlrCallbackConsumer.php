<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Console\Commands\ConsumeDlrCallbacks;
use App\Services\SMPP\SmppErrorAlertService;

/**
 * Watchdog for the dlr-callback:consume worker.
 *
 * Mirrors OLD SYSTEM v3_itagg_daemon_dreceipt_push_monitor.php which runs
 * every minute from cron, reads the dlrPushDaemon-<name>.touch file, and
 * restarts the daemon if the touch is older than 120 seconds.
 *
 * Our version: reads the heartbeat file written by ConsumeDlrCallbacks,
 * alerts if it's stale or missing. Supervisor handles the actual restart
 * (autorestart=true), so we don't shell out to kill/restart — we just
 * notify so an operator can investigate.
 */
class WatchDlrCallbackConsumer extends Command
{
    protected $signature = 'dlr-callback:watchdog
                            {--stale-seconds=120 : Heartbeat staleness threshold}';

    protected $description = 'Watchdog: alerts if dlr-callback:consume heartbeat is stale (matches OLD SYSTEM 120s rule)';

    public function handle(): int
    {
        $threshold = (int) $this->option('stale-seconds');
        $path = ConsumeDlrCallbacks::heartbeatPath();

        if (!file_exists($path)) {
            $msg = "Heartbeat file does not exist at {$path} — consumer has not started or is broken.";
            $this->warn($msg);
            Log::warning('DLR callback consumer watchdog: heartbeat missing', ['path' => $path]);
            SmppErrorAlertService::notify(
                'DLR callback consumer heartbeat missing',
                $msg . "\n\nExpected the dlr-callback:consume worker to write this file on every message. If it never starts, customer DLR webhooks are not being delivered.",
                ['path' => $path]
            );
            return Command::FAILURE;
        }

        $lines = @file($path);
        if (!$lines || count($lines) < 2) {
            $this->warn("Heartbeat file present but malformed.");
            return Command::FAILURE;
        }

        $pid = trim($lines[0]);
        $lastTouch = (int) trim($lines[1]);
        $ageSeconds = time() - $lastTouch;

        $this->line("PID:        {$pid}");
        $this->line("Last touch: " . date('Y-m-d H:i:s', $lastTouch));
        $this->line("Age:        {$ageSeconds}s (threshold {$threshold}s)");

        if ($ageSeconds > $threshold) {
            $msg = "Heartbeat is {$ageSeconds}s old (> {$threshold}s threshold). Consumer is likely frozen.";
            $this->error($msg);
            Log::error('DLR callback consumer watchdog: heartbeat stale', [
                'pid'        => $pid,
                'age_s'      => $ageSeconds,
                'threshold'  => $threshold,
                'last_touch' => date('Y-m-d H:i:s', $lastTouch),
            ]);
            SmppErrorAlertService::notify(
                'DLR callback consumer is frozen',
                $msg . "\n\nThe dlr-callback:consume process (PID {$pid}) hasn't written its heartbeat since " . date('Y-m-d H:i:s', $lastTouch) . ".\n\nSupervisor should auto-restart it — investigate why it stopped processing messages (RabbitMQ disconnect? DB timeout? PHP error loop?).",
                [
                    'pid'        => $pid,
                    'age_s'      => $ageSeconds,
                    'threshold'  => $threshold,
                    'last_touch' => date('Y-m-d H:i:s', $lastTouch),
                ]
            );
            return Command::FAILURE;
        }

        $this->info("OK");
        return Command::SUCCESS;
    }
}
