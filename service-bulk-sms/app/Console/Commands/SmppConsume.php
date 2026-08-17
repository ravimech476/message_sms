<?php

namespace App\Console\Commands;

use App\Models\MessageUpdate;
use App\Services\Queue\RabbitMQService;
use App\Services\Smpp\SmppService;
use Illuminate\Console\Command;

/**
 * Long-running consumer: pull SMS from sms.outbound and send via SMPP.
 * The API/publisher never touches SMPP; this binder does. After submitting, it
 * stamps message_updates.supplier_message_id so the DLR can match back.
 *
 *   docker exec silicon-sms-smpp-workers php artisan smpp:consume
 *
 * @author Anand Karthik
 */
class SmppConsume extends Command
{
    protected $signature = 'smpp:consume
        {--bank= : Bank key from config/smpp_banks.php}
        {--once : Process a single message then exit}';

    protected $description = 'Consume outbound SMS from RabbitMQ (sms.outbound) and send via SMPP';

    public function handle(RabbitMQService $rabbit)
    {
        $queue = config('rabbitmq.queues.outbound', 'sms.outbound');
        $bank = $this->option('bank') ?: null;
        $max = $this->option('once') ? 1 : 0;

        $this->info("SMPP consumer started — draining '{$queue}'" . ($bank ? " (bank {$bank})" : '') . ' ... Ctrl+C to stop.');

        $rabbit->consumeFromQueue($queue, function (array $data) use ($bank) {
            $updateId = $data['message_update_id'] ?? null;
            $to = $data['to'] ?? null;
            $from = $data['from'] ?? 'FootFall';
            $message = $data['message'] ?? '';

            if (!$to) {
                $this->warn('  message missing "to" — dropping');
                return true;
            }

            $this->line("→ message_update #{$updateId}: sending to {$to} ...");

            $smpp = new SmppService($bank);
            try {
                $smpp->connect();
                $messageId = $smpp->sendSms($to, $message, $from);
                $price = $smpp->getLastPrice();
                $smpp->close();

                if ($updateId) {
                    MessageUpdate::where('id', $updateId)->update([
                        'status'              => 'sent',
                        'supplier_message_id' => $messageId,
                        'cost_per_sms'        => $price,
                    ]);
                }
                $this->info("  ✅ sent — message_id={$messageId} cost={$price}");
                return true; // ack
            } catch (\Throwable $e) {
                $smpp->close();
                if ($updateId) {
                    MessageUpdate::where('id', $updateId)->update([
                        'status'      => 'failed',
                        'status_note' => substr($e->getMessage(), 0, 250),
                    ]);
                }
                $this->error('  ❌ ' . $e->getMessage());
                return true; // ack — don't requeue send failures (avoid infinite loop)
            }
        }, $max);

        $rabbit->close();
        $this->info('Consumer stopped.');
        return 0;
    }
}
