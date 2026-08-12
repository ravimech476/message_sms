<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailLog;
use App\Services\Queue\EmailQueueService;
use Carbon\Carbon;
use App\Mail\BulkEmail;
use Illuminate\Support\Facades\Log;



class SendScheduledEmails extends Command
{
    protected $signature = 'emails:send-schedule';
    protected $description = 'Send all scheduled emails whose time has come';

    public function handle()
    {
        // Fetch unsent SMS messages from the database
        $now = Carbon::now('Europe/London')->format('YmdHis');

        $emails = EmailLog::where('status', 'scheduled')
            ->where('sent_at', '<=', $now)
            ->get();

        if ($emails->isEmpty()) {
            Log::info('There is no email pending');
            $this->info('There is no email pending');
            return;
        }

        $emailQueueService = new EmailQueueService();

        foreach ($emails as $emailLog) {
            try {
                // Send email via RabbitMQ queue
                $emailQueueService->queueEmail(
                    'App\\Mail\\BulkEmail',
                    $emailLog->to,
                    [
                        'subject' => $emailLog->subject,
                        'message_content' => $emailLog->message,
                    ]
                );

                // Laravel log
                Log::info("Email queued to: $emailLog->to | Subject: $emailLog->subject");

                // Update status
                $emailLog->update([
                    'status' => 'queued',
                ]);

                $this->info("Email queued to: {$emailLog->to}");
            } catch (\Exception $e) {
                $emailLog->update([
                    'status' => 'failed',
                ]);

                Log::error("Failed to queue email to: {$emailLog->to} | Error: {$e->getMessage()}");
                $this->error("Failed to queue email to: {$emailLog->to}");
            }
        }
    }
}
