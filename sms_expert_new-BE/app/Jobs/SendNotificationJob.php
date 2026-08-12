<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\AdminNotificationRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Queue\EmailQueueService;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notificationId;

    /**
     * Create a new job instance.
     */
    public function __construct($notificationId)
    {
        $this->notificationId = $notificationId;
        $this->onQueue('notifications'); // Use RabbitMQ notifications queue
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $notification = AdminNotification::with('recipients.user')->find($this->notificationId);

            if (!$notification) {
                Log::error("Notification not found: {$this->notificationId}");
                return;
            }

            Log::info("Processing notification: {$notification->id} - {$notification->title}");

            $emailsQueued = 0;
            $emailsFailed = 0;

            $emailQueueService = new EmailQueueService();

            foreach ($notification->recipients as $recipient) {
                // Send email if enabled
                if ($notification->send_email && $recipient->user && $recipient->user->contactemail) {
                    try {
                        $this->sendEmailNotification($notification, $recipient, $emailQueueService);
                        $recipient->update([
                            'email_sent' => true,
                            'email_sent_at' => now(),
                        ]);
                        $emailsQueued++;
                    } catch (\Exception $e) {
                        Log::error("Failed to queue email to {$recipient->user->contactemail}: " . $e->getMessage());
                        $emailsFailed++;
                    }
                }
            }

            // Update notification status
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info("Notification {$notification->id} processed successfully. Emails queued: {$emailsQueued}, Failed: {$emailsFailed}");

        } catch (\Exception $e) {
            Log::error("Failed to process notification {$this->notificationId}: " . $e->getMessage());

            // Update notification status to failed
            $notification = AdminNotification::find($this->notificationId);
            if ($notification) {
                $notification->update(['status' => 'failed']);
            }

            throw $e;
        }
    }

    /**
     * Send email notification to a recipient via RabbitMQ queue
     */
    protected function sendEmailNotification(AdminNotification $notification, AdminNotificationRecipient $recipient, EmailQueueService $emailQueueService)
    {
        $user = $recipient->user;

        if (!$user || !$user->contactemail) {
            return;
        }

        $customerName = urldecode($user->busname ?: $user->contactname ?: 'Customer');

        // Queue the notification email via RabbitMQ
        $emailQueueService->queueEmail(
            'App\\Mail\\AdminNotificationMail',
            $user->contactemail,
            [
                'notification' => $notification->toArray(),
                'customer' => $user->toArray(),
                'recipient_id' => $recipient->id,
            ],
            [],
            8 // High priority for notifications
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotificationJob failed for notification {$this->notificationId}: " . $exception->getMessage());

        $notification = AdminNotification::find($this->notificationId);
        if ($notification) {
            $notification->update(['status' => 'failed']);
        }
    }
}
