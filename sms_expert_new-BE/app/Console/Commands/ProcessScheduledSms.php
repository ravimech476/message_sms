<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SMPPPoolManager;
use App\Services\SMPP\SinchSmppService;
use App\Traits\LogsCronActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * Process Scheduled SMS from smsg_log table
 * Handles both Nexmo and Sinch providers based on suppliername field
 */
class ProcessScheduledSms extends Command
{
    use LogsCronActivity;
    protected $signature = 'sms:process-scheduled
                            {--limit=100 : Maximum messages to process per run}
                            {--daemon : Run continuously as daemon}
                            {--provider= : Process only specific provider (nexmo/sinch)}';

    protected $description = 'Process scheduled SMS messages from smsg_log table (handles both Nexmo and Sinch)';

    private $nexmoSmpp;
    private $sinchSmpp;
    private $running = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->initCronLog('ProcessScheduledSms');

        $limit = (int) $this->option('limit');
        $daemon = $this->option('daemon');
        $provider = $this->option('provider');

        $this->cronStart([
            'limit' => $limit,
            'daemon' => $daemon,
            'provider' => $provider ?? 'all'
        ]);

        $this->info('Starting Scheduled SMS Processor...');
        $this->info('Handles both Nexmo and Sinch providers');

        if ($provider) {
            $this->info("Processing only: {$provider}");
            $this->cronInfo("Processing only provider: {$provider}");
        }

        // Initialize SMPP services
        try {
            $this->nexmoSmpp = new SMPPPoolManager();
            $this->info('Nexmo SMPP initialized');
            $this->cronInfo('Nexmo SMPP initialized');
        } catch (Exception $e) {
            $this->warn('Nexmo SMPP not available: ' . $e->getMessage());
            $this->cronWarning('Nexmo SMPP not available', ['error' => $e->getMessage()]);
            $this->nexmoSmpp = null;
        }

        try {
            $this->sinchSmpp = new SinchSmppService();
            $this->info('Sinch SMPP initialized');
            $this->cronInfo('Sinch SMPP initialized');
        } catch (Exception $e) {
            $this->warn('Sinch SMPP not available: ' . $e->getMessage());
            $this->cronWarning('Sinch SMPP not available', ['error' => $e->getMessage()]);
            $this->sinchSmpp = null;
        }

        if (!$this->nexmoSmpp && !$this->sinchSmpp) {
            $this->error('No SMPP services available. Exiting.');
            $this->cronFailed('No SMPP services available');
            return 1;
        }

        if ($daemon) {
            $this->runAsDaemon($limit, $provider);
        } else {
            $this->processMessages($limit, $provider);
        }

        return 0;
    }

    private function runAsDaemon(int $limit, ?string $provider)
    {
        $this->info('Running in daemon mode. Press Ctrl+C to stop.');

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->running = false;
                $this->info('Received shutdown signal...');
            });
            pcntl_signal(SIGTERM, function () {
                $this->running = false;
                $this->info('Received termination signal...');
            });
        }

        while ($this->running) {
            try {
                $processed = $this->processPendingMessages($limit, $provider);

                if ($processed === 0) {
                    sleep(1);
                }

                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

            } catch (Exception $e) {
                Log::error('Scheduled SMS Daemon Error: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                sleep(5);
            }
        }

        $this->info('Daemon stopped.');
    }

    private function processMessages(int $limit, ?string $provider)
    {
        $this->info("Processing up to {$limit} scheduled messages...");
        $this->cronInfo("Processing up to {$limit} scheduled messages");

        $totalProcessed = $this->processPendingMessages($limit, $provider);

        $this->info("Completed: {$totalProcessed} processed.");
        $this->cronEnd(['processed' => $totalProcessed]);
    }

    private function processPendingMessages(int $limit, ?string $provider): int
    {
        $processed = 0;
        $now = Carbon::now('Europe/London');

        // Build query for scheduled messages that are due.
        //
        // A scheduled ("send at time") row is reliably identified by sentstatus='tomorrowonward'
        // across EVERY send path — but they disagree on deliverystatus2:
        //   CampaignQueueService  -> deliverystatus2 = 'scheduled'
        //   Dashboard / Mobile    -> deliverystatus2 = 'tomorrowonward'
        //   API (SmsSendingService) -> deliverystatus2 = 'pending'
        // Filtering on deliverystatus2='scheduled' (the previous behaviour) therefore matched ONLY
        // campaign rows and left every API/dashboard/mobile scheduled SMS stuck forever ("in transit",
        // never sent at its send-at time). We now key ONLY on sentstatus='tomorrowonward' (which flips
        // to 'sending'/'ok' once sent, so rows are never re-picked), regardless of deliverystatus2.
        $query = DB::table('smsg_log')
            ->where('sentstatus', 'tomorrowonward')
            ->where('migration_flag', 'new')
            ->where('dosendtime', '<=', $now->format('YmdHis'))
            ->orderBy('dosendtime', 'asc')
            ->limit($limit);

        // Filter by provider if specified
        if ($provider === 'sinch') {
            $query->where(function ($q) {
                $q->where('suppliername', 'Sinch SMPP')
                    ->orWhere('aggregator_dlrmsg', 'like', 'Scheduled - %mblox%')
                    ->orWhere('aggregator_dlrmsg', 'like', 'Scheduled - %sinch%');
            });
        } elseif ($provider === 'nexmo') {
            $query->where(function ($q) {
                $q->where('suppliername', 'Vonage SMPP')
                    ->orWhere('suppliername', 'Nexmo SMPP')
                    ->orWhere('aggregator_dlrmsg', 'like', 'Scheduled - %nexmo%')
                    ->orWhereNull('suppliername')
                    ->orWhere('suppliername', '');
            });
        }

        $messages = $query->get();

        $this->line("Found {$messages->count()} scheduled messages to process");
        $this->cronInfo("Found messages to process", ['count' => $messages->count()]);

        $sent = 0;
        $failed = 0;

        foreach ($messages as $message) {
            try {
                $bigid = $message->bigid;
                $mobile = $message->mobnum;

                // Determine provider from suppliername or aggregator_dlrmsg
                $messageProvider = $this->determineProvider($message);

                $this->line("Processing: {$bigid} -> {$mobile} via {$messageProvider}");

                // OLD SYSTEM parity (smsg_2send_body.inc:2229-2250): re-check the customer's
                // wallet at SEND time. A scheduled row passed a wallet check when it was created,
                // but the customer may have spent the balance in the meantime. If they can no
                // longer cover this message, fail it — do NOT send and do NOT charge — exactly as
                // OLD does when its daemon fires a scheduled message with insufficient funds.
                if (!$this->walletCanCover($message)) {
                    // OLD SYSTEM byte-exact (smsg_2send_body.inc:2245-2250). OLD's insufficient-funds
                    // UPDATE sets ONLY these three columns and builds sentstatustext by concatenating
                    // onto the existing value:  concat(sentstatustext, ' ', <datenow>, ' smsg_err: <msg>').
                    // costprice/userprice/profit are left as-is (already 0 from the scheduled insert —
                    // "no cost incurred or charged"), and deliverystatus2 is not touched, exactly like OLD.
                    $datenow = Carbon::now('Europe/London')->format('YmdHis');
                    DB::table('smsg_log')
                        ->where('id', $message->id)
                        ->where('migration_flag', 'new')
                        ->update([
                            'sentstatus'     => 'fail',
                            'sentstatustext' => DB::raw(
                                "concat(sentstatustext, ' ', '{$datenow}', "
                                . "' smsg_err: Failure due to insufficient funds for this batch. sms not sent. no cost incurred or charged.')"
                            ),
                            'timesent'       => $datenow,
                        ]);

                    $this->warn("  ✗ Insufficient funds — not sent (id {$message->id})");
                    $this->cronWarning('Scheduled SMS failed: insufficient funds at send time', [
                        'id'      => $message->id,
                        'userref' => $message->userref,
                        'mobile'  => $mobile,
                    ]);
                    $failed++;
                    continue; // move to next message; nothing sent, nothing charged
                }

                // Mark as processing
                DB::table('smsg_log')
                    ->where('id', $message->id)
                    ->where('migration_flag', 'new')
                    ->update([
                        'sentstatus' => 'sending',
                        'sentstatustext' => "Sending via {$messageProvider} SMPP"
                    ]);

                // Send via appropriate SMPP
                if ($messageProvider === 'sinch') {
                    $result = $this->sendViaSinch($message);
                } else {
                    $result = $this->sendViaNexmo($message);
                }

                if ($result['success']) {
                    DB::table('smsg_log')
                        ->where('id', $message->id)
                        ->where('migration_flag', 'new')
                        ->update([
                            'sentstatus' => 'ok',
                            // OLD SYSTEM: successful sends leave sentstatustext EMPTY. Every OLD
                            // "sentstatus='ok'" UPDATE (smsg_2send_body.inc:1924, amd_fire.inc:242,
                            // mblox_fire.inc:429, ...) sets status/refs/prices but never writes a
                            // success message into sentstatustext — it stays '' from insert. Only
                            // failures/blacklist/disabled populate it. Keep parity so the sent-log
                            // display (which shows sentstatustext only when non-empty) is clean.
                            'sentstatustext' => '',
                            'suppliermsgref' => $result['message_id'] ?? '',
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                            'deliverystatus2' => 'acked',  // OLD SYSTEM: sent to network, awaiting DLR
                        ]);

                    $this->info("  ✓ Sent: " . ($result['message_id'] ?? 'success'));
                    $processed++;
                    $sent++;
                } else {
                    DB::table('smsg_log')
                        ->where('id', $message->id)
                        ->where('migration_flag', 'new')
                        ->update([
                            'sentstatus' => 'fail',
                            'sentstatustext' => "{$messageProvider} SMPP failed: " . ($result['error'] ?? 'Unknown'),
                            'deliverystatus2' => 'Non Delivered',
                        ]);

                    $this->warn("  ✗ Failed: " . ($result['error'] ?? 'Unknown error'));
                    $this->cronWarning("SMS send failed", [
                        'bigid' => $bigid,
                        'mobile' => $mobile,
                        'provider' => $messageProvider,
                        'error' => $result['error'] ?? 'Unknown'
                    ]);
                    $failed++;
                }

            } catch (Exception $e) {
                $this->error("  ✗ Exception: " . $e->getMessage());
                Log::error('Scheduled SMS Process Error', [
                    'bigid' => $message->bigid ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                $this->cronError("SMS process exception", [
                    'bigid' => $message->bigid ?? 'unknown',
                    'error' => $e->getMessage()
                ]);

                DB::table('smsg_log')
                    ->where('id', $message->id)
                    ->where('migration_flag', 'new')
                    ->update([
                        'sentstatus' => 'fail',
                        'sentstatustext' => 'Exception: ' . $e->getMessage(),
                        'deliverystatus2' => 'Non Delivered',
                    ]);
                $failed++;
            }
        }

        if ($messages->count() > 0) {
            $this->cronInfo("Batch processing completed", [
                'total' => $messages->count(),
                'sent' => $sent,
                'failed' => $failed
            ]);
        }

        return $processed;
    }

    /**
     * Send-time wallet re-check — OLD SYSTEM parity (smsg_2send_body.inc:2229-2250).
     *
     * Returns true if the customer's live wallet can still cover this scheduled message, false if
     * it must be failed. The check is SILENT (no 102 alert email) to match OLD's send-time path —
     * OLD only raises out-of-funds alerts at schedule/submission time (WalletValidationService),
     * not when the daemon fires a due message.
     *
     * Balance formula matches the rest of the system and OLD:
     *     balance = smsg_wallet - smsg_server1_sent - smsg_server2_sent
     * Rate source matches the schedule-time validator (WalletValidationService::calculateTotalSmsCost):
     *     user_cost (per destination country) -> users.common_sms_rate -> 0.009 default,
     * multiplied by the stored numparts. OLD also imposes an 8p absolute-minimum wallet floor.
     */
    private function walletCanCover($message): bool
    {
        $user = DB::table('users')
            ->select('id', 'smsg_wallet', 'smsg_server1_sent', 'smsg_server2_sent', 'common_sms_rate')
            ->where('bigid', $message->userref)
            ->first();

        // No resolvable customer -> cannot validate; fail safe (OLD would not send either).
        if (!$user) {
            return false;
        }

        $balance = (float) $user->smsg_wallet
            - (float) $user->smsg_server1_sent
            - (float) $user->smsg_server2_sent;

        // Per-part rate: default -> country-specific user_cost override (same order as the
        // schedule-time validator, so send-time uses the identical pricing the customer was
        // validated against at submission).
        $ratePerPart = (float) ($user->common_sms_rate ?: 0.009);

        $digits  = preg_replace('/[^0-9]/', '', (string) $message->mobnum);
        $country = app(\App\Services\TableCache::class)->countryForNumber($digits);
        if ($country) {
            $userRate = DB::table('user_cost')
                ->where('bigid', $user->id)
                ->where('country_id', $country->id)
                ->where('status', 1)
                ->value('rate');
            if ($userRate && $userRate > 0) {
                $ratePerPart = (float) $userRate;
            }
        }

        $numParts = max(1, (int) ($message->numparts ?: 1));
        $cost     = round($ratePerPart * $numParts, 4);

        // Must cover the message cost AND clear OLD's 8p absolute-minimum wallet level.
        return $balance >= $cost && $balance >= 0.08;
    }

    private function determineProvider($message): string
    {
        $suppliername = strtolower($message->suppliername ?? '');
        $dlrmsg = strtolower($message->aggregator_dlrmsg ?? '');

        // Check for Sinch/mBlox indicators
        if (str_contains($suppliername, 'sinch') ||
            str_contains($dlrmsg, 'mblox') ||
            str_contains($dlrmsg, 'sinch')) {
            return 'sinch';
        }

        // Default to Nexmo
        return 'nexmo';
    }

    private function sendViaSinch($message): array
    {
        if (!$this->sinchSmpp) {
            return ['success' => false, 'error' => 'Sinch SMPP not available'];
        }

        return $this->sinchSmpp->sendSMS(
            $message->mobnum,
            // smsg_log.text is stored url-encoded (SmsSendingService:807 `urlencode($txt)`;
            // spaces -> '+', GSM specials -> '%XX'). The immediate-send path decodes it back
            // before dispatch (SmsSendingService:1506 `urldecode(...)`); the scheduler reads the
            // stored (encoded) value, so it must urldecode too or the handset receives literal
            // '+' for spaces (e.g. "test+again+second+sms").
            urldecode($message->text),
            $message->originator,
            5,
            null,
            'Scheduler',
            $message->bigid,
            null
        );
    }

    private function sendViaNexmo($message): array
    {
        if (!$this->nexmoSmpp) {
            return ['success' => false, 'error' => 'Nexmo SMPP not available'];
        }

        return $this->nexmoSmpp->sendSMS(
            $message->mobnum,
            // See sendViaSinch(): stored text is url-encoded, decode before dispatch to match
            // the immediate-send path (SmsSendingService:1506) and avoid literal '+' on handset.
            urldecode($message->text),
            $message->originator,
            5,
            null,
            'Scheduler',
            $message->bigid,
            null
        );
    }
}
