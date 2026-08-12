<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\SmsQueueService;
use App\Traits\LogsCronActivity;
use Carbon\Carbon;

/**
 * Nightly requeue of TECHNICALLY-failed SMS (runs 23:00 UK).
 *
 * Re-enqueues today's sends that failed for a TRANSIENT/technical reason (SMPP submit error,
 * throttle-exhausted, connection drop, the UCS2 part-split bug, a lock timeout, ...) so they get
 * another delivery attempt.
 *
 * It DELIBERATELY EXCLUDES the "legitimate" failures that must NEVER be resent:
 *   - Blacklisted (STOP opt-out)          -> resending messages an opted-out recipient = COMPLIANCE breach
 *   - Insufficient funds / credit         -> would just fail again
 *   - Disabled account                    -> blocked
 *   - Scheduled-stop ("will not be sent") -> intentionally cancelled
 * It also never touches scheduled ('tomorrowonward') or already-sent ('ok') rows.
 *
 * Only rows now marked sentstatus='fail' are considered — reliable because the queue consumer
 * marks the smsg_log row 'fail' on an SMPP failure (OLD-system parity, ProcessSmsQueue). A 'fail'
 * row is also, by definition, no longer in the RabbitMQ queue, so requeueing can't duplicate an
 * in-flight message.
 *
 * Targeting dayofyear=<today> means each failed message is retried AT MOST ONCE (the night it
 * failed) — it can't loop forever, because the next day's run targets the next day's failures.
 *
 * Each row is re-enqueued to sms.outbound with reference_id=bigid and metadata.smsg_log_id=<id>,
 * so the retry UPDATES THE SAME smsg_log row (matched by the exact id) — no duplicate row, no
 * double charge.
 */
class RequeueFailedSms extends Command
{
    use LogsCronActivity;

    protected $signature = 'sms:requeue-failed
                            {--date= : Day to requeue as YYYYMMDD (dayofyear). Defaults to today (UK).}
                            {--limit=5000 : Max messages to requeue in one run.}
                            {--dry-run : Show what would be requeued without enqueuing or changing rows.}';

    protected $description = 'Re-enqueue today\'s technically-failed SMS (excludes blacklist / insufficient funds / disabled / scheduled).';

    public function handle(): int
    {
        $this->initCronLog('RequeueFailedSms');

        $day   = $this->option('date') ?: Carbon::now('Europe/London')->format('Ymd');
        $limit = max(1, (int) $this->option('limit'));
        $dry   = (bool) $this->option('dry-run');

        $this->cronStart(['day' => $day, 'limit' => $limit, 'dry_run' => $dry]);
        $this->info("Requeue failed SMS for day {$day} (limit {$limit})" . ($dry ? ' [DRY RUN]' : ''));

        // TECHNICAL failures only. COALESCE so NULL/empty sentstatustext still counts as retryable
        // (an empty reason is a technical fail, not a known rejection). The NOT LIKE list is the
        // "do-not-retry" set — blacklist first (compliance).
        $rows = DB::table('smsg_log')
            ->where('migration_flag', 'new')
            ->where('dayofyear', $day)
            ->where('sentstatus', 'fail')                 // failed AND out of the queue
            ->whereRaw("COALESCE(sentstatustext,'') NOT LIKE '%Blacklisted%'")
            ->whereRaw("COALESCE(sentstatustext,'') NOT LIKE '%insufficient%'")   // funds or credit
            ->whereRaw("COALESCE(sentstatustext,'') NOT LIKE '%disabled%'")
            ->whereRaw("COALESCE(sentstatustext,'') NOT LIKE '%will not be sent%'")
            ->select('id', 'bigid', 'userref', 'mobnum', 'text', 'originator', 'suppliername', 'initiator')
            ->limit($limit)
            ->get();

        $this->line("Candidates: {$rows->count()}");
        $this->cronInfo('Requeue candidates found', ['count' => $rows->count(), 'day' => $day]);

        $smsQueue = $dry ? null : new SmsQueueService();
        $requeued = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            // smsg_log.text is stored url-encoded; decode to the original message for re-send.
            $message  = urldecode((string) $row->text);
            $provider = stripos((string) $row->suppliername, 'sinch') !== false ? 'sinch' : 'nexmo';

            if (trim($message) === '' || trim((string) $row->mobnum) === '') {
                $skipped++;
                continue;
            }

            if ($dry) {
                $this->line("  would requeue #{$row->id} -> {$row->mobnum} via {$provider}");
                $requeued++;
                continue;
            }

            // Reset to a clean 'no' so the retry lifecycle (firing -> ok/fail) is clean and this run
            // never re-selects the row. (Nothing auto-sends a 'no' row — sends only happen via the
            // queue, which we trigger below.)
            DB::table('smsg_log')->where('id', $row->id)->update([
                'sentstatus'     => 'no',
                'sentstatustext' => '',
            ]);

            $res = $smsQueue->enqueueSms([
                'user_ref'      => $row->userref,
                'mobile_number' => $row->mobnum,
                'message'       => $message,
                'sender_id'     => $row->originator,
                'reference_id'  => $row->bigid,
                'provider'      => $provider,
                'priority'      => 5,
                'metadata'      => [
                    'provider'    => $provider,
                    'bigid'       => $row->bigid,
                    'smsg_log_id' => $row->id,   // exact row -> retry UPDATEs the same row (dup-safe)
                    'initiator'   => $row->initiator ?: 'RequeueFailed',
                    'requeued'    => true,
                ],
            ]);

            if (!empty($res['success'])) {
                $requeued++;
            } else {
                // Enqueue failed (e.g. RabbitMQ down): put the row back to 'fail' so a later run can
                // retry it, and record why.
                DB::table('smsg_log')->where('id', $row->id)->update([
                    'sentstatus'     => 'fail',
                    'sentstatustext' => 'requeue failed: ' . substr((string) ($res['error'] ?? 'unknown'), 0, 100),
                ]);
                $skipped++;
            }
        }

        $this->info("Requeued: {$requeued}  Skipped: {$skipped}");
        $this->cronEnd(['requeued' => $requeued, 'skipped' => $skipped, 'candidates' => $rows->count()]);

        return self::SUCCESS;
    }
}
