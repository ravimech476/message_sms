<?php

namespace App\Services\Queue;

use App\Models\AdminNotification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class NotificationQueueService
{
    protected $rabbitMQService;
    protected $emailQueueName = 'email.notifications'; // Use existing email queue
    protected $pushQueueName = 'push.notifications'; // Push notification queue for mobile

    public function __construct()
    {
        $this->rabbitMQService = app(RabbitMQService::class);
    }

    /**
     * Check if delivery method includes mobile push
     */
    private function shouldSendMobile(string $deliveryMethod): bool
    {
        return in_array($deliveryMethod, ['mobile', 'web_mobile', 'all']);
    }

    /**
     * Check if delivery method includes email
     */
    private function shouldSendEmail(string $deliveryMethod): bool
    {
        return in_array($deliveryMethod, ['email', 'both', 'all']);
    }

    /**
     * Check if delivery method includes web
     */
    private function shouldSendWeb(string $deliveryMethod): bool
    {
        return in_array($deliveryMethod, ['web', 'both', 'web_mobile', 'all']);
    }

    /**
     * Queue a notification for sending via RabbitMQ
     */
    public function queueNotification(AdminNotification $notification, array $recipientIds = [])
    {
        try {
            // Get recipients
            if ($notification->target_type === 'all') {
                $recipients = User::where(function ($q) {
                        $q->where('login_type', 'customer')
                            ->orWhereNull('login_type')
                            ->orWhere('login_type', '');
                    })
                    ->where('bit_disabled', 0)
                    ->get();
            } else {
                $recipients = User::whereIn('id', $recipientIds)
                    ->where('bit_disabled', 0)
                    ->get();
            }

            if ($recipients->isEmpty()) {
                Log::warning('No recipients found for notification', ['notification_id' => $notification->id]);
                return false;
            }

            // Update notification status
            $notification->update([
                'status' => 'sending',
                'total_recipients' => $recipients->count(),
            ]);

            $queuedCount = 0;

            $emailQueuedCount = 0;
            $pushQueuedCount = 0;
            $deliveryMethod = $notification->delivery_method;

            // Create recipient records and queue messages
            foreach ($recipients as $recipient) {
                // Create recipient record
                // Only mark web_delivered if delivery method includes web
                $shouldDeliverWeb = $this->shouldSendWeb($deliveryMethod);
                
                $recipientRecord = NotificationRecipient::updateOrCreate(
                    [
                        'notification_id' => $notification->id,
                        'user_id' => $recipient->id,
                    ],
                    [
                        'user_bigid' => $recipient->bigid,
                        'is_read' => false,
                        'is_acknowledged' => false,
                        'email_sent' => false,
                        'web_delivered' => $shouldDeliverWeb,
                        'push_sent' => false,
                    ]
                );
                
                Log::debug('Created notification recipient', [
                    'notification_id' => $notification->id,
                    'user_id' => $recipient->id,
                    'delivery_method' => $deliveryMethod,
                    'web_delivered' => $shouldDeliverWeb,
                    'should_send_mobile' => $this->shouldSendMobile($deliveryMethod),
                ]);

                // Queue email notification via RabbitMQ if required
                if ($this->shouldSendEmail($deliveryMethod)) {
                    // Trim and clean email
                    $email = trim($recipient->contactemail);
                    
                    // Skip if no valid email
                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        // Queue to existing email.notifications queue
                        $emailData = [
                            'type' => 'admin_notification',
                            'mailable_class' => 'App\\Mail\\AdminNotificationMail',
                            'recipient' => $email,
                            'cc_recipients' => [],
                            'data' => [
                                'notification_id' => $notification->id,
                                'recipient_id' => $recipientRecord->id,
                                'user_id' => $recipient->id,
                                'user_name' => $recipient->busname ?: $recipient->contactname ?: 'Customer',
                                'title' => $notification->title,
                                'message' => $notification->message,
                                'notification_type' => $notification->type,
                                'requires_acknowledgment' => $notification->requires_acknowledgment,
                            ],
                            'queued_at' => Carbon::now()->toIso8601String(),
                        ];

                        Log::info('Queueing notification email', [
                            'queue' => $this->emailQueueName,
                            'recipient' => $email,
                            'notification_id' => $notification->id,
                        ]);

                        // Publish to email queue
                        $result = $this->rabbitMQService->publishToQueue($this->emailQueueName, $emailData, 5);
                        
                        if ($result) {
                            $emailQueuedCount++;
                            Log::info('Email queued successfully', ['email' => $email]);
                        } else {
                            Log::error('Failed to queue email', ['email' => $email]);
                        }
                    } else {
                        Log::warning('Invalid or empty email, skipping', [
                            'user_id' => $recipient->id,
                            'email' => $recipient->contactemail,
                        ]);
                    }
                }

                // Queue mobile push notification via RabbitMQ if required
                if ($this->shouldSendMobile($deliveryMethod)) {
                    $pushData = [
                        'type' => 'admin_notification',
                        'user_id' => $recipient->id,
                        'user_bigid' => $recipient->bigid,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'notification_type' => $notification->type,
                        'data' => [
                            'notification_id' => (string) $notification->id,
                            'recipient_id' => (string) $recipientRecord->id,
                            'type' => $notification->type,
                            'requires_acknowledgment' => $notification->requires_acknowledgment ? 'true' : 'false',
                            'action' => 'view_notification',
                            'screen' => 'Notifications',
                        ],
                        'queued_at' => Carbon::now()->toIso8601String(),
                    ];

                    Log::info('Queueing mobile push notification', [
                        'queue' => $this->pushQueueName,
                        'user_id' => $recipient->id,
                        'user_bigid' => $recipient->bigid,
                        'notification_id' => $notification->id,
                        'recipient_record_id' => $recipientRecord->id,
                    ]);

                    // Publish to push notifications queue
                    $result = $this->rabbitMQService->publishToQueue($this->pushQueueName, $pushData, 7); // Higher priority
                    
                    if ($result) {
                        $pushQueuedCount++;
                        
                        // Update recipient record to indicate push notification is queued
                        NotificationRecipient::where('id', $recipientRecord->id)
                            ->update(['push_sent' => true, 'push_sent_at' => now()]);
                        
                        Log::info('Push notification queued successfully', [
                            'user_id' => $recipient->id,
                            'recipient_record_id' => $recipientRecord->id,
                        ]);
                    } else {
                        Log::error('Failed to queue push notification', [
                            'user_id' => $recipient->id,
                            'recipient_record_id' => $recipientRecord->id,
                        ]);
                    }
                }
            }

            $queuedCount = $emailQueuedCount + $pushQueuedCount;

            // Update notification status to sent
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Notification queued successfully', [
                'notification_id' => $notification->id,
                'recipients_count' => $recipients->count(),
                'emails_queued' => $emailQueuedCount,
                'push_notifications_queued' => $pushQueuedCount,
                'total_queued' => $queuedCount,
                'delivery_method' => $notification->delivery_method,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to queue notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $notification->update(['status' => 'draft']);
            return false;
        }
    }

    /**
     * Save a scheduled notification - just saves it, NEVER sends immediately
     * The ProcessScheduledNotifications command will send it at the scheduled time
     */
    public function queueScheduledNotification(AdminNotification $notification, array $recipientIds = [])
    {
        try {
            // NEVER send immediately - just save and let scheduler handle it
            // Update status to scheduled
            $notification->update(['status' => 'scheduled']);

            // If target type is specific, save the recipient IDs for later
            if ($notification->target_type === 'specific' && !empty($recipientIds)) {
                // Get user bigids for the recipient records
                $users = User::whereIn('id', $recipientIds)->get()->keyBy('id');
                
                foreach ($recipientIds as $userId) {
                    $user = $users->get($userId);
                    NotificationRecipient::updateOrCreate(
                        [
                            'notification_id' => $notification->id,
                            'user_id' => $userId,
                        ],
                        [
                            'user_bigid' => $user ? $user->bigid : null,
                            'is_read' => false,
                            'is_acknowledged' => false,
                            'email_sent' => false,
                            'web_delivered' => false,
                        ]
                    );
                }
            }

            Log::info('Notification scheduled successfully - will be sent at scheduled time', [
                'notification_id' => $notification->id,
                'scheduled_at' => $notification->scheduled_at,
                'will_send_in' => $notification->scheduled_at->diffForHumans(),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to schedule notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats()
    {
        return $this->rabbitMQService->getQueueStats($this->emailQueueName);
    }
}
