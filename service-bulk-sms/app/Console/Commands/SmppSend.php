<?php

namespace App\Console\Commands;

use App\Services\Smpp\SmppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 test: send ONE SMS over SMPP and record it in smsg_log — the full
 * single-message send path (bind → submit_sm → capture message-id → update row).
 *
 *   docker exec silicon-sms-php php artisan sms:smpp-send 919344312970 --message="Hello via SMPP" --from=FootFall
 *   docker exec silicon-sms-php php artisan sms:smpp-send 919344312970 --bank=a0
 */
class SmppSend extends Command
{
    protected $signature = 'sms:smpp-send
        {to : Destination MSISDN (international, digits)}
        {--from=FootFall : Sender ID (alphanumeric or numeric)}
        {--message=Test message from FootFall SMPP pipeline : Message text}
        {--bank= : Bank key from config/smpp_banks.php (else single-bind)}
        {--debug : Dump the raw SMPP PDU exchange}';

    protected $description = 'Send one SMS over SMPP (Vonage) and log it to smsg_log';

    public function handle()
    {
        $to = $this->argument('to');
        $from = $this->option('from');
        $message = $this->option('message');
        $bank = $this->option('bank');

        $now = now()->format('YmdHis');

        // 1) Create the smsg_log row (pending) — text stored URL-encoded (sms_expert parity)
        $smsgLogId = DB::table('smsg_log')->insertGetId([
            'bigid'         => '',
            'mobnum'        => $to,
            'text'          => urlencode($message),
            'sentstatustext' => '',
            'originator'    => $from,
            'numparts'      => 1,
            'timesubmitted' => $now,
            'dosendtime'    => $now,
            'sentstatus'    => 'pending',
            'initiator'     => 'SMPP',
            'suppliername'  => 'Vonage SMPP',
            'dayofyear'     => now()->format('Ymd'),
        ]);

        $this->info("smsg_log row #{$smsgLogId} created (pending). Binding SMPP" . ($bank ? " bank {$bank}" : '') . " ...");

        $smpp = new SmppService($bank ?: null);
        $smpp->debug = (bool) $this->option('debug');

        try {
            $smpp->connect();
            $this->line('  bound — sending submit_sm ...');

            $messageId = $smpp->sendSms($to, $message, $from);

            // Modern Vonage message-ids are 32-char hex UUIDs; the DLR references the same
            // id, so store it as-is for matching (older numeric-only ids get decimal-converted).
            $matchRef = SmppService::messageIdToDecimal($messageId);
            if (strlen($matchRef) > 36) {
                $matchRef = $messageId;
            }
            $suppliermsgref = (ctype_digit($matchRef) && strlen($matchRef) <= 18) ? $matchRef : 0;

            // 2) Update the row: sent OK + provider message-id + DLR-match ref
            DB::table('smsg_log')->where('id', $smsgLogId)->update([
                'sentstatus'              => 'ok',
                'timesent'                => now()->format('YmdHis'),
                'deliveryreceipt1'        => $messageId,
                'onesixty_suppliermsgref' => $matchRef,
                'suppliermsgref'          => $suppliermsgref,
            ]);

            $smpp->close();

            $this->info("  ✅ SENT — message_id={$messageId}");
            $this->info("  smsg_log #{$smsgLogId}: sentstatus=ok, onesixty_suppliermsgref={$matchRef}");
            return 0;
        } catch (\Throwable $e) {
            DB::table('smsg_log')->where('id', $smsgLogId)->update([
                'sentstatus'     => 'fail',
                'sentstatustext' => substr($e->getMessage(), 0, 250),
            ]);
            $smpp->close();
            $this->error("  ❌ SEND FAILED: " . $e->getMessage());
            return 1;
        }
    }
}
