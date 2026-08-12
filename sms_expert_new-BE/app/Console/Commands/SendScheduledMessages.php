<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SMPP\SMPPPoolManager;
use App\Services\SMPP\SinchSmppService;
use App\Traits\LogsCronActivity;
use Carbon\Carbon;
use Exception;

class SendScheduledMessages extends Command
{
    use LogsCronActivity;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:send-schedule {--limit=100 : Maximum messages to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled messages based on the send_at time (supports both Nexmo and Sinch)';

    protected $nexmoSmpp;
    protected $sinchSmpp;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Cron Run Functionality - Every 5 minutes
     */
    public function handle()
    {
        $this->initCronLog('SendScheduledMessages');

        $limit = (int) $this->option('limit');
        $this->cronStart(['limit' => $limit]);

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

        // Fetch unsent SMS messages from the database
        $now = Carbon::now('Europe/London')->format('YmdHis');

        $messages = DB::table('smsg_log')
            ->where('dosendtime', '<=', $now)
            ->where('sentstatus', 'tomorrowonward')
            ->where('migration_flag', 'new')
            ->limit($limit)
            ->get();

        $this->info("Found {$messages->count()} scheduled messages to process");
        $this->cronInfo("Found messages to process", ['count' => $messages->count()]);

        $sent = 0;
        $failed = 0;
        $blocked = 0;

        foreach ($messages as $message) {
            // Check if the number is blacklisted
            $normalizedNumber = ltrim($message->mobnum, '+');

            $isBlacklisted = DB::table('itagg_outbound_blacklist')
                ->where('msisdn', $normalizedNumber)
                ->where('users_bigid', $message->userref)
                ->exists();

            if ($isBlacklisted) {
                // Update the status to 'blocked' if the number is in the blacklist
                DB::table('smsg_log')->where('bigid', $message->bigid)->update([
                    'sentstatus' => 'fail',
                    'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                    'sentstatustext' => 'Blacklisted Number',
                    'deliverystatus2' => 'Non Delivered',  // OLD SYSTEM format
                    'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // GMT/UTC (display converts +1h BST)
                ]);

                $this->info('SMS not sent to blacklisted number ' . $message->mobnum);
                Log::info('SMS not sent to blacklisted number ' . $message->mobnum);
                $this->cronWarning('Blacklisted number skipped', ['mobile' => $message->mobnum, 'bigid' => $message->bigid]);
                $blocked++;
                continue;
            }

            // Check wallet balance
            $user = DB::table('users')->where('bigid', $message->userref)->first();

            if (!$user || $user->smsg_wallet < 0.60) {
                // Not enough funds
                DB::table('smsg_log')->where('bigid', $message->bigid)->update([
                    'sentstatus' => 'fail',
                    'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                    'sentstatustext' => 'Insufficient Balance',
                    'deliverystatus2' => 'Non Delivered',  // OLD SYSTEM format
                    'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // GMT/UTC (display converts +1h BST)
                ]);

                Log::warning("User {$message->userref} has insufficient balance. SMS not sent to {$message->mobnum}");
                $this->warn("Insufficient balance for user {$message->userref}, SMS not sent.");
                $this->cronWarning('Insufficient balance', ['userref' => $message->userref, 'mobile' => $message->mobnum]);
                $failed++;
                continue;
            }

            // Determine provider from originator (sender ID) by checking smsshortcodes.whichoperator
            $originator = $message->originator ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME');
            $provider = $this->determineProviderFromOriginator($originator);
            $this->line("Processing: {$message->bigid} -> {$message->mobnum} via {$provider}");

            // Mark as sending
            DB::table('smsg_log')->where('bigid', $message->bigid)->update([
                'sentstatus' => 'sending',
                'sentstatustext' => "Sending via {$provider} SMPP"
            ]);

            // Send the SMS using appropriate SMPP provider
            if ($provider === 'sinch') {
                $result = $this->sendViaSinch($message, $originator);
            } else {
                $result = $this->sendViaNexmo($message, $originator);
            }

            // Update the status in the database
            if ($result['success']) {
                DB::table('smsg_log')->where('bigid', $message->bigid)->update([
                    'sentstatus' => 'ok',
                    'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                    'sentstatustext' => "Sent via {$provider} SMPP",
                    'suppliermsgref' => $result['message_id'] ?? '',
                    'deliverystatus2' => 'acked',  // OLD SYSTEM: sent to network, awaiting DLR
                ]);
                $this->info("  ✓ Sent SMS to {$message->mobnum} via {$provider}");
                $sent++;
            } else {
                DB::table('smsg_log')->where('bigid', $message->bigid)->update([
                    'sentstatus' => 'fail',
                    'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                    'sentstatustext' => "{$provider} SMPP error: " . ($result['error'] ?? 'Unknown'),
                    'deliverystatus2' => 'Non Delivered',  // OLD SYSTEM format
                    'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // GMT/UTC (display converts +1h BST)
                ]);

                Log::error('Failed to send SMS to ' . $message->mobnum . ': ' . ($result['error'] ?? 'Unknown'));
                $this->error("  ✗ Failed to send SMS to {$message->mobnum}: " . ($result['error'] ?? 'Unknown'));
                $this->cronError('SMS send failed', [
                    'bigid' => $message->bigid,
                    'mobile' => $message->mobnum,
                    'provider' => $provider,
                    'error' => $result['error'] ?? 'Unknown'
                ]);
                $failed++;
            }
        }

        $this->info('SMS sending process completed.');
        $this->cronEnd([
            'total' => $messages->count(),
            'sent' => $sent,
            'failed' => $failed,
            'blocked' => $blocked
        ]);
        return 0;
    }

    /**
     * Determine SMPP provider based on originator (from) number
     * Checks smsshortcodes.whichoperator field to determine if Sinch or Nexmo
     *
     * @param string $from The originator/sender ID
     * @return string 'sinch' or 'nexmo'
     */
    private function determineProviderFromOriginator(string $from): string
    {
        // If alphanumeric sender ID, default to nexmo
        if (!preg_match('/^[0-9]+$/', $from)) {
            return 'nexmo';
        }

        // Format UK mobile numbers
        if (substr($from, 0, 2) === '07') {
            $from = '447' . substr($from, 2);
        }

        // Check smsshortcodes table for this number
        $shortcode = DB::table('smsshortcodes')
            ->where('number', $from)
            ->first();

        if ($shortcode && !empty($shortcode->whichoperator)) {
            $operator = strtolower($shortcode->whichoperator);

            // Check for Sinch/mBlox indicators
            if (str_contains($operator, 'sinch') || str_contains($operator, 'mblox')) {
                Log::info("Scheduled SMS: Provider determined from originator", [
                    'from' => $from,
                    'operator' => $shortcode->whichoperator,
                    'provider' => 'sinch'
                ]);
                return 'sinch';
            }
        }

        // Default to nexmo
        return 'nexmo';
    }

    /**
     * Send SMS via Sinch SMPP
     */
    private function sendViaSinch($message, string $originator): array
    {
        if (!$this->sinchSmpp) {
            return ['success' => false, 'error' => 'Sinch SMPP not available'];
        }

        try {
            return $this->sinchSmpp->sendSMS(
                $message->mobnum,
                $message->text,
                $originator,
                5, // priority
                null, // queueId
                'Scheduler',
                $message->bigid, // referenceId
                null // scheduleDeliveryTime - sending now
            );
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send SMS via Nexmo SMPP
     */
    private function sendViaNexmo($message, string $originator): array
    {
        if (!$this->nexmoSmpp) {
            return ['success' => false, 'error' => 'Nexmo SMPP not available'];
        }

        try {
            return $this->nexmoSmpp->sendSMS(
                $message->mobnum,
                $message->text,
                $originator,
                5, // priority
                null, // queueId
                'Scheduler',
                $message->bigid, // referenceId
                null // scheduleDeliveryTime - sending now
            );
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
