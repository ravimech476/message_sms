<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;

/**
 * Daily DLR catch-up.
 *
 * The every-minute `nexmo:fetch-delivery-reports` cron occasionally fails (rate limit,
 * clock skew, transient API errors) and a late DLR can slip through its short window.
 * This job runs once a day at 02:00 UK time with a 24-hour lookback so any SMS whose
 * delivery status never got updated (still pending, migration_flag='new') is back-filled.
 *
 * It reuses the proven pipeline end-to-end: the fetch queues the records to
 * `nexmo.delivery.reports`, and NexmoDeliveryQueueService then
 *   - updates smsg_log.deliverystatus2 / deliverytime2 / upstream_errormessage,
 *   - pushes the customer DLR webhook (dlr.callback.push), and
 *   - emails on push failure.
 * Its idempotency gate skips rows already finalised, so only genuinely-pending
 * messages are updated — exactly the "get the ones that were missed" behaviour.
 */
class FetchNexmoDeliveryReportsDaily extends Command
{
    use LogsCronExecution;

    protected $signature = 'nexmo:fetch-delivery-reports-daily
                            {--lookback-minutes=1440 : Lookback window in minutes (default 1440 = 24 hours).}';

    protected $description = 'Daily 02:00 UK catch-up: re-fetch the last 24h of Nexmo DLRs and update any SMS whose status was missed by the every-minute cron.';

    public function handle()
    {
        return $this->executeWithLogging('nexmo:fetch-delivery-reports-daily', function () {
            // Baseline window (24h). But WIDEN it to reach the OLDEST still-pending message so
            // we back-fill old pending DLRs (e.g. 18th June and before), not just the last day.
            $baseLookback = max(2, (int) ($this->option('lookback-minutes') ?: 1440));
            $lookback     = $this->lookbackToCoverOldestPending($baseLookback);

            $this->info("Daily DLR catch-up: re-fetching the last {$lookback} minutes (widened to cover oldest pending)...");
            Log::info('nexmo:fetch-delivery-reports-daily starting', ['lookback_minutes' => $lookback]);

            // Reuse the canonical fetch command (clock-skew re-anchor, 429 backoff,
            // idempotency-safe queueing) with a wide lookback. The consumer back-fills
            // only the still-pending rows, pushes the customer webhook and emails on failure.
            $exit = Artisan::call('nexmo:fetch-delivery-reports', [
                '--lookback-minutes' => $lookback,
            ]);

            $output = trim(Artisan::output());
            if ($output !== '') {
                $this->line($output);
            }

            Log::info('nexmo:fetch-delivery-reports-daily finished', ['exit_code' => $exit]);

            return $exit === 0
                ? 'Daily DLR catch-up complete.'
                : 'Daily DLR catch-up finished with issues (see nexmo:fetch-delivery-reports output).';
        });
    }

    /**
     * Work out how far back the Reports API window must reach so it covers EVERY
     * still-pending message — not just the last 24h. We find the oldest pending row
     * (our send: migration_flag='new', sentstatus='ok', deliverystatus2 not finalised)
     * and return the minutes from then until now, with:
     *   - a floor of $baseLookback (always at least 24h), and
     *   - a ceiling of NEXMO_REPORTS_CATCHUP_MAX_MINUTES (default 30 days) — Nexmo's
     *     Reports API doesn't retain data forever and a huge window is costly.
     *
     * timesent is a 14-digit YYYYMMDDHHMMSS string stored in Europe/London.
     */
    private function lookbackToCoverOldestPending(int $baseLookback): int
    {
        $maxLookback = max($baseLookback, (int) env('NEXMO_REPORTS_CATCHUP_MAX_MINUTES', 43200)); // 30 days

        try {
            $oldest = DB::table('smsg_log')
                ->where('migration_flag', 'new')   // messages we sent via the new system
                ->where('sentstatus', 'ok')        // actually went out
                ->where(function ($q) {            // not yet finalised = still pending
                    $q->whereNull('deliverystatus2')
                      ->orWhere('deliverystatus2', '')
                      ->orWhere('deliverystatus2', 'pending');
                })
                ->whereNotNull('timesent')
                ->where('timesent', '<>', '')
                ->where('timesent', '<>', '00000000000000')
                ->min('timesent');
        } catch (\Throwable $e) {
            Log::warning('Daily DLR catch-up: could not query oldest pending, using base lookback', ['error' => $e->getMessage()]);
            return $baseLookback;
        }

        if (empty($oldest)) {
            $this->info('No pending messages found — using base lookback.');
            return $baseLookback;
        }

        try {
            $oldestTime = Carbon::createFromFormat('YmdHis', str_pad((string) $oldest, 14, '0'), 'Europe/London');
        } catch (\Throwable $e) {
            return $baseLookback;
        }

        // +60 min margin so the window comfortably includes the oldest send.
        $minutesBack = (int) ceil(now('Europe/London')->diffInMinutes($oldestTime)) + 60;

        $lookback = min(max($baseLookback, $minutesBack), $maxLookback);

        $this->info("Oldest pending message: {$oldestTime->toDateTimeString()} → lookback {$lookback} min "
            . '(' . round($lookback / 1440, 1) . ' day(s)).');

        return $lookback;
    }
}
