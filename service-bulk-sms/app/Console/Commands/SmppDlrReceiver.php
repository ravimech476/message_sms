<?php

namespace App\Console\Commands;

use App\Services\Smpp\SmppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — DLR receiver: bind_receiver on a bank, read deliver_sm delivery
 * receipts, and buffer each into smsg_receipt_buffer_new (XMLDATA=JSON, status='new').
 * A separate `dlr:process-buffer` worker matches them back to smsg_log.
 * Mirrors sms_expert's SMPP receiver + smsg_receipt_buffer_new write.
 *
 *   docker exec silicon-sms-php php artisan smpp:dlr-receiver
 *   docker exec silicon-sms-php php artisan smpp:dlr-receiver --seconds=60   # testing
 */
class SmppDlrReceiver extends Command
{
    protected $signature = 'smpp:dlr-receiver
        {--bank= : Bank key from config/smpp_banks.php}
        {--seconds=0 : Run for N seconds then exit (0 = forever)}';

    protected $description = 'Bind as SMPP receiver and buffer delivery receipts (DLRs) into smsg_receipt_buffer_new';

    public function handle()
    {
        $bank = $this->option('bank') ?: null;
        $seconds = (int) $this->option('seconds');

        $this->info('Binding SMPP receiver' . ($bank ? " (bank {$bank})" : '') . ' ...');

        $smpp = new SmppService($bank);
        try {
            $smpp->connectReceiver();
        } catch (\Throwable $e) {
            $this->error('bind_receiver failed: ' . $e->getMessage());
            return 1;
        }

        $this->info('bound_receiver — listening for DLRs' . ($seconds > 0 ? " for {$seconds}s" : ' (Ctrl+C to stop)') . ' ...');

        $count = 0;
        $smpp->listenForDlr(function (array $dlr) use (&$count) {
            DB::table('smsg_receipt_buffer_new')->insert([
                'XMLDATA' => json_encode([
                    'message_id' => $dlr['message_id'] ?? null,
                    'status'     => $dlr['status'] ?? null,
                    'done_date'  => $dlr['done_date'] ?? null,
                    'err'        => $dlr['err'] ?? null,
                ]),
                'status' => 'new',
            ]);
            $count++;
            $this->line("  ← DLR buffered: id={$dlr['message_id']} stat={$dlr['status']} err={$dlr['err']}");
        }, $seconds);

        $smpp->close();
        $this->info("Receiver stopped. Buffered {$count} DLR(s).");
        return 0;
    }
}
