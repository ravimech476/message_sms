<?php

namespace App\Console\Commands;

use App\Services\Queue\RabbitMQService;
use App\Services\Smpp\SmppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 — long-running consumer: pull SMS from sms.outbound and send via SMPP.
 * This is the production decoupling: the API/publisher never touches SMPP; this
 * worker does. Mirrors sms_expert's sms:process-queue.
 *
 *   docker exec silicon-sms-php php artisan smpp:consume
 *   docker exec silicon-sms-php php artisan smpp:consume --once     # process one and exit (testing)
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
            $id = $data['smsg_log_id'] ?? null;
            $to = $data['to'] ?? null;
            $from = $data['from'] ?? 'FootFall';
            $message = $data['message'] ?? '';

            if (!$to) {
                $this->warn('  message missing "to" — dropping');
                return true;
            }

            $this->line("→ smsg_log #{$id}: sending to {$to} ...");

            $smpp = new SmppService($bank);
            try {
                $smpp->connect();
                $messageId = $smpp->sendSms($to, $message, $from);
                $smpp->close();

                if ($id) {
                    DB::table('smsg_log')->where('id', $id)->update([
                        'sentstatus'              => 'ok',
                        'timesent'                => now()->format('YmdHis'),
                        'deliveryreceipt1'        => $messageId,
                        'onesixty_suppliermsgref' => $messageId,
                    ]);
                }
                $this->info("  ✅ sent — message_id={$messageId}");
                return true; // ack
            } catch (\Throwable $e) {
                $smpp->close();
                if ($id) {
                    DB::table('smsg_log')->where('id', $id)->update([
                        'sentstatus'     => 'fail',
                        'sentstatustext' => substr($e->getMessage(), 0, 250),
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
