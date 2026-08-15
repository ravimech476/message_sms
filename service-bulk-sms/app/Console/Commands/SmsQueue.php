<?php

namespace App\Console\Commands;

use App\Services\Queue\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 — publish an outbound SMS to RabbitMQ (sms.outbound).
 * Creates the smsg_log row (pending), then the smpp:consume worker sends it.
 *
 *   docker exec silicon-sms-php php artisan sms:queue 919344312970 --message="Hi via queue"
 */
class SmsQueue extends Command
{
    protected $signature = 'sms:queue
        {to : Destination MSISDN}
        {--from=FootFall : Sender ID}
        {--message=Test SMS via RabbitMQ pipeline : Message text}';

    protected $description = 'Publish an outbound SMS to the sms.outbound RabbitMQ queue';

    public function handle(RabbitMQService $rabbit)
    {
        $to = $this->argument('to');
        $from = $this->option('from');
        $message = $this->option('message');
        $now = now()->format('YmdHis');

        $smsgLogId = DB::table('smsg_log')->insertGetId([
            'bigid'          => '',
            'mobnum'         => $to,
            'text'           => urlencode($message),
            'sentstatustext' => '',
            'originator'     => $from,
            'numparts'       => 1,
            'timesubmitted'  => $now,
            'dosendtime'     => $now,
            'sentstatus'     => 'pending',
            'initiator'      => 'SMPP',
            'suppliername'   => 'Vonage SMPP',
            'dayofyear'      => now()->format('Ymd'),
        ]);

        $queue = config('rabbitmq.queues.outbound', 'sms.outbound');
        $ok = $rabbit->publishToQueue($queue, [
            'smsg_log_id' => $smsgLogId,
            'to'          => $to,
            'from'        => $from,
            'message'     => $message,
        ]);
        $rabbit->close();

        if ($ok) {
            $this->info("✅ Queued smsg_log #{$smsgLogId} → {$queue} (to {$to})");
            return 0;
        }
        $this->error('Failed to publish message');
        return 1;
    }
}
