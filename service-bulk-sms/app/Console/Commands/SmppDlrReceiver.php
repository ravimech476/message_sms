<?php

namespace App\Console\Commands;

use App\Services\Smpp\DeliveryStatusService;
use App\Services\Smpp\SmppService;
use Illuminate\Console\Command;

/**
 * DLR receiver: bind_receiver on a bank, read deliver_sm delivery receipts, and
 * apply each directly to message_updates (match supplier_message_id → set
 * delivered_at + status). No buffer table — the update is a fast indexed seek.
 *
 *   docker exec silicon-sms-smpp-workers php artisan smpp:dlr-receiver --bank=a0
 *   docker exec silicon-sms-smpp-workers php artisan smpp:dlr-receiver --seconds=60   # testing
 */
class SmppDlrReceiver extends Command
{
    protected $signature = 'smpp:dlr-receiver
        {--bank= : Bank key from config/smpp_banks.php}
        {--seconds=0 : Run for N seconds then exit (0 = forever)}';

    protected $description = 'Bind as SMPP receiver and apply delivery receipts (DLRs) to message_updates';

    public function handle(DeliveryStatusService $status)
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

        $matched = 0;
        $seen = 0;
        $smpp->listenForDlr(function (array $dlr) use (&$matched, &$seen, $status) {
            $seen++;
            $ok = $status->apply($dlr);
            $ok ? $matched++ : null;
            $this->line("  ← DLR id={$dlr['message_id']} stat={$dlr['status']} " . ($ok ? 'matched' : 'no-match'));
            \App\Services\Logging\ComponentLogger::smpp()->info('DLR received', [
                'id' => $dlr['message_id'] ?? null, 'status' => $dlr['status'] ?? null,
                'matched' => $ok, 'bank' => $this->option('bank'),
            ]);
        }, $seconds);

        $smpp->close();
        $this->info("Receiver stopped. Saw {$seen} DLR(s), matched {$matched}.");
        return 0;
    }
}
