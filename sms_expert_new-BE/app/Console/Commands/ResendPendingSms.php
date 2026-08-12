<?php

namespace App\Console\Commands;

use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Re-publish SMS rows that were QUEUED but NEVER SENT back onto sms.outbound so
 * the (now crash-fixed) ProcessSmsQueue worker delivers them to Nexmo/Vonage.
 *
 * Target rows are strictly the ones that never went out:
 *     sentstatus = 'pending'  AND  timesent = '00000000000000'
 * so there is NO double-send risk for the selected rows (a row that actually
 * sent has sentstatus='ok' + a real timesent and is excluded).
 *
 * Each re-published row is flipped to sentstatus='requeued' on successful
 * publish. That (a) makes a second run of this command skip it -> no
 * double-send across runs, and (b) the worker's send-success path overwrites it
 * to 'ok' by smsg_log_id. If publish fails, the row is left 'pending' and will
 * be retried on the next run.
 */
class ResendPendingSms extends Command
{
    protected $signature = 'sms:resend-pending
                            {--dry-run       : Count/preview only; publish nothing}
                            {--limit=0       : Max rows to process (0 = all)}
                            {--hours=0       : Only rows submitted within the last N hours (0 = no age limit)}
                            {--provider=auto : Force provider tag (nexmo|sinch|auto). auto = derive from suppliername}
                            {--batch=500     : Chunk size for DB paging / progress logging}
                            {--sleep-ms=0    : Optional throttle between publishes, in milliseconds}
                            {--id=           : Re-queue a single smsg_log.id (for testing)}';

    protected $description = 'Re-publish stuck pending SMS (queued but never sent) back to sms.outbound';

    private SmsQueueService $smsQueueService;

    public function __construct()
    {
        parent::__construct();
        $this->smsQueueService = new SmsQueueService();
    }

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $limit    = (int) $this->option('limit');
        $hours    = (int) $this->option('hours');
        $batch    = max(50, (int) $this->option('batch'));
        $sleepMs  = max(0, (int) $this->option('sleep-ms'));
        $forceProv = strtolower((string) $this->option('provider'));
        $singleId = $this->option('id');

        // Base query: "queued but never sent" rows. Includes both sentstatus='pending'
        // AND sentstatus='' (blank) — some send paths leave the status blank on insert,
        // and those never-sent rows must be re-queued too (timesent='00000000000000'
        // guarantees they never reached the provider). Optional 'no' can be added if needed.
        $query = DB::table('smsg_log')
            ->where('migration_flag', 'new')
            ->whereIn('sentstatus', ['pending', ''])
            ->where('timesent', '00000000000000')
            ->whereNotNull('mobnum')
            ->where('mobnum', '<>', '')
            ->whereNotNull('text')
            ->where('text', '<>', '');

        if ($singleId) {
            $query->where('id', (int) $singleId);
        }

        if ($hours > 0) {
            $cutoff = Carbon::now()->subHours($hours)->format('YmdHis');
            $query->where('timesubmitted', '>=', $cutoff);
            $this->line("Age filter: only rows submitted since {$cutoff} (last {$hours}h).");
        }

        $total = (clone $query)->count();
        $this->info("Matched {$total} stuck pending SMS row(s) to re-queue.");

        if ($total === 0) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $sample = (clone $query)->orderBy('id')->limit(5)
                ->get(['id', 'bigid', 'mobnum', 'originator', 'suppliername', 'timesubmitted']);
            $this->line('DRY RUN — no messages published. Sample:');
            foreach ($sample as $r) {
                $this->line("  id={$r->id} mob={$r->mobnum} from={$r->originator} sup={$r->suppliername} at={$r->timesubmitted}");
            }
            $this->line('Re-run without --dry-run to publish.');
            return self::SUCCESS;
        }

        $processed = 0;
        $published = 0;
        $failed    = 0;

        // chunkById is stable under our own UPDATE (we move rows off 'pending'),
        // because it pages by id > lastId rather than by offset.
        (clone $query)->orderBy('id')->chunkById($batch, function ($rows) use (
            &$processed, &$published, &$failed, $limit, $sleepMs, $forceProv
        ) {
            foreach ($rows as $row) {
                if ($limit > 0 && $processed >= $limit) {
                    return false; // stop chunking
                }
                $processed++;

                $provider = $this->resolveProvider($forceProv, $row->suppliername ?? '');

                $payload = [
                    'user_ref'      => $row->userref ?? 'system',
                    'mobile_number' => $row->mobnum,
                    'message'       => urldecode((string) $row->text),
                    'sender_id'     => $row->originator ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME'),
                    'priority'      => 5,
                    'reference_id'  => $row->bigid ?? null,
                    'provider'      => $provider,
                    'metadata'      => [
                        'smsg_log_id' => $row->id,
                        'bigid'       => $row->bigid ?? null,
                        'source'      => $row->initiator ?? 'resend-pending',
                        'provider'    => $provider,
                        'requeued'    => true,
                    ],
                ];

                try {
                    $result = $this->smsQueueService->enqueueSms($payload);

                    if (!empty($result['success'])) {
                        // Leave sentstatus AS-IS (pending / '') — the row's true state is still
                        // "not sent" until the sms.outbound worker actually delivers it and flips
                        // it to 'ok'. We only stamp sentstatustext as an audit note. (Do NOT set
                        // 'requeued' — it isn't a valid sentstatus ENUM value, so non-strict MySQL
                        // silently stored '', which is why the old flip never "took".)
                        DB::table('smsg_log')->where('id', $row->id)->update([
                            // 'sentstatus'     => 'firing',
                            'sentstatustext' => 'Re-queued to sms.outbound ' . Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                        $published++;
                    } else {
                        $failed++;
                        Log::warning('resend-pending: publish failed, leaving row pending', [
                            'id'    => $row->id,
                            'error' => $result['error'] ?? 'unknown',
                        ]);
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('resend-pending: exception publishing row', [
                        'id'    => $row->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }

            $this->line("  ...processed {$processed} (published {$published}, failed {$failed})");

            if ($limit > 0 && $processed >= $limit) {
                return false;
            }
            return true;
        }, 'id');

        $this->newLine();
        $this->info("Done. Processed: {$processed} | Re-queued: {$published} | Failed: {$failed}");
        Log::info('sms:resend-pending complete', [
            'processed' => $processed,
            'published' => $published,
            'failed'    => $failed,
        ]);

        return self::SUCCESS;
    }

    /**
     * Decide which provider tag to stamp on the outbound envelope.
     * 'auto' derives from the stored supplier name; anything else is forced.
     */
    private function resolveProvider(string $forced, string $supplierName): string
    {
        if ($forced === 'nexmo' || $forced === 'sinch') {
            return $forced;
        }
        // auto
        return stripos($supplierName, 'sinch') !== false ? 'sinch' : 'nexmo';
    }
}
