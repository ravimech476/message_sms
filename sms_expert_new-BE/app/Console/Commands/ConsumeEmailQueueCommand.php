<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConsumeEmailQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consume-emails {--timeout=60} {--max-messages=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume email messages from RabbitMQ queue';

    private $connection;
    private $channel;
    private $queueName = 'email.notifications';
    private $messagesProcessed = 0;
    private $maxMessages = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $this->maxMessages = (int) $this->option('max-messages');

        $qlog = \App\Services\Logging\RabbitMQLogService::for($this->queueName);
        $qlog->info('Consumer started', ['timeout' => $timeout]);

        try {
            // Connect to RabbitMQ
            $this->connectToRabbitMQ();
            
            // Check if there are messages in the queue
            list($queue, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $this->queueName,
                true,   // passive - just check
                false,
                false,
                false
            );
            
            $this->info("Connected to RabbitMQ. Queue has {$messageCount} messages.");
            
            if ($messageCount == 0) {
                $this->info('No messages in queue. Waiting for new messages...');
            }
            
            $this->info('Press Ctrl+C to exit');
            
            // Set up consumer
            $callback = function ($msg) {
                $this->processMessage($msg);
                $this->messagesProcessed++;
                
                // Check if we've reached max messages
                if ($this->maxMessages > 0 && $this->messagesProcessed >= $this->maxMessages) {
                    $this->info("Processed {$this->maxMessages} messages. Exiting...");
                    $this->channel->basic_cancel($msg->delivery_info['consumer_tag']);
                }
            };
            
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,  // no_local
                false,  // no_ack - we'll manually acknowledge
                false,  // exclusive
                false,  // nowait
                $callback
            );
            
            // Keep consuming messages with timeout handling
            while ($this->channel->is_consuming()) {
                try {
                    $this->channel->wait(null, false, $timeout);
                } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                    // Timeout is normal when no messages
                    $this->info("No messages received for {$timeout} seconds. Still listening...");
                    continue;
                }
            }
            
            $this->info("Total messages processed: {$this->messagesProcessed}");
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Email queue consumer error', ['error' => $e->getMessage()]);
            $qlog->error('Consumer fatal error', ['error' => $e->getMessage(), 'error_at' => $e->getFile().':'.$e->getLine()]);
            return 1;
        } finally {
            $this->cleanup();
        }

        return 0;
    }
    
    /**
     * Connect to RabbitMQ
     */
    private function connectToRabbitMQ()
    {
        $host = env('RABBITMQ_HOST', '127.0.0.1');
        $port = env('RABBITMQ_PORT', 5672);
        $user = env('RABBITMQ_USER', 'guest');
        $password = env('RABBITMQ_PASSWORD', 'guest');
        $vhost = env('RABBITMQ_VHOST', '/');
        
        $this->connection = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $vhost
        );
        
        $this->channel = $this->connection->channel();
        
        // Try to declare/check queue
        try {
            // Just declare the queue with basic settings
            $this->channel->queue_declare(
                $this->queueName,
                false,  // passive
                true,   // durable
                false,  // exclusive
                false   // auto_delete
            );
        } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
            if (strpos($e->getMessage(), 'PRECONDITION_FAILED') !== false) {
                // Queue exists with different parameters, recreate channel and continue
                $this->channel = $this->connection->channel();
                Log::info('Using existing queue configuration');
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Process a message from the queue
     */
    private function processMessage($msg)
    {
        try {
            $data = json_decode($msg->body, true);
            
            if (!$data) {
                throw new \Exception('Invalid message format');
            }
            
            $messageType = $data['type'] ?? 'email';
            
            // Handle scheduled notification trigger
            if ($messageType === 'scheduled_notification' || (isset($data['action']) && $data['action'] === 'send_notification')) {
                $this->processScheduledNotification($data, $msg);
                return;
            }
            
            // Handle regular email messages
            if (!isset($data['recipient']) || !isset($data['mailable_class'])) {
                $this->info('Skipping message - missing recipient or mailable_class');
                $msg->ack();
                return;
            }
            
            $this->info('Processing email to: ' . (is_array($data['recipient']) ? $data['recipient'][0] : $data['recipient']));
            
            // Create the mailable based on the class
            $mailableClass = $data['mailable_class'];
            $mailable = $this->createMailable($mailableClass, $data['data']);
            
            $mail = Mail::to($data['recipient']);
            
            if (!empty($data['cc_recipients'])) {
                $mail->cc($data['cc_recipients']);
            }
            
            $mail->send($mailable);
            
            $this->info('✓ Email sent successfully');
            
            // Update notification recipient if this is an admin notification
            if ($mailableClass === 'App\\Mail\\AdminNotificationMail' && isset($data['data']['recipient_id'])) {
                $this->updateNotificationRecipient($data['data']['recipient_id']);
            }
            
            // Acknowledge the message
            $msg->ack();

            Log::info('Email sent from RabbitMQ queue', [
                'mailable' => $mailableClass,
                'recipient' => is_array($data['recipient']) ? $data['recipient'][0] : $data['recipient']
            ]);
            \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                ->info('ACK — email sent', ['mailable' => $mailableClass]);

        } catch (\Exception $e) {
            $this->error('Failed to process message: ' . $e->getMessage());
            Log::error('Failed to process email from queue', [
                'error' => $e->getMessage(),
                'message' => $msg->body
            ]);
            \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                ->error('NACK (requeue=true) — email send failed', [
                    'error'    => $e->getMessage(),
                    'error_at' => $e->getFile().':'.$e->getLine(),
                ]);

            // Reject the message and requeue it
            $msg->nack(true);
        }
    }

    /**
     * Process a scheduled notification trigger
     */
    private function processScheduledNotification($data, $msg)
    {
        try {
            $notificationId = $data['notification_id'] ?? null;
            $recipientIds = $data['recipient_ids'] ?? [];
            
            if (!$notificationId) {
                $this->error('Scheduled notification missing notification_id');
                $msg->ack();
                return;
            }
            
            $this->info("Processing scheduled notification #{$notificationId}");
            
            // Find the notification
            $notification = \App\Models\AdminNotification::find($notificationId);
            
            if (!$notification) {
                $this->error("Notification #{$notificationId} not found");
                $msg->ack();
                return;
            }
            
            // Check if notification is still scheduled (not cancelled)
            if ($notification->status !== 'scheduled') {
                $this->info("Notification #{$notificationId} status is '{$notification->status}', skipping");
                $msg->ack();
                return;
            }
            
            // Send the notification now
            $notificationService = new \App\Services\Queue\NotificationQueueService();
            $result = $notificationService->queueNotification($notification, $recipientIds);
            
            if ($result) {
                $this->info("✓ Scheduled notification #{$notificationId} sent successfully");
            } else {
                $this->error("Failed to send scheduled notification #{$notificationId}");
            }
            
            // Acknowledge the message
            $msg->ack();
            
        } catch (\Exception $e) {
            $this->error('Failed to process scheduled notification: ' . $e->getMessage());
            Log::error('Failed to process scheduled notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            // Acknowledge to prevent infinite retry
            $msg->ack();
        }
    }

    /**
     * Update notification recipient after email is sent
     */
    private function updateNotificationRecipient($recipientId)
    {
        try {
            \App\Models\NotificationRecipient::where('id', $recipientId)->update([
                'email_sent' => true,
                'email_sent_at' => now(),
            ]);
            
            $this->info('✓ Notification recipient updated');
            
        } catch (\Exception $e) {
            Log::error('Failed to update notification recipient', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create a mailable instance based on the class name and data
     *
     * @param string $mailableClass
     * @param array $data
     * @return mixed
     */
    private function createMailable(string $mailableClass, array $data)
    {
        // Handle different mailable classes
        switch ($mailableClass) {
            case 'App\\Mail\\OrderConfirmationMail':
                return new \App\Mail\OrderConfirmationMail(
                    $data['recipient'] ?? $data['to_address'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['user_contact_name'] ?? '',
                    $data['invoice_id'] ?? 0
                );

            case 'App\\Mail\\InvoiceMail':
                // Reconstruct the user and invoice objects from arrays
                $user = isset($data['user']) ? (object) $data['user'] : null;
                $invoice = isset($data['invoice']) ? (object) $data['invoice'] : null;

                // For invoice object, also convert orderItems if it exists
                if ($invoice && isset($data['invoice']['order_items'])) {
                    $invoice->orderItems = (object) $data['invoice']['order_items'];
                }

                return new \App\Mail\InvoiceMail(
                    $data['recipient'] ?? $data['to_address'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['invoice_id'] ?? 0,
                    $user,
                    $invoice
                );

            case 'App\\Mail\\InvoiceCopyMail':
                // Reconstruct the user and invoice objects from arrays
                $user = isset($data['user']) ? (object) $data['user'] : null;
                $invoice = isset($data['invoice']) ? (object) $data['invoice'] : null;

                if ($invoice && isset($data['invoice']['order_items'])) {
                    $invoice->orderItems = (object) $data['invoice']['order_items'];
                }

                return new \App\Mail\InvoiceCopyMail(
                    $data['recipient'] ?? $data['to_address'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['invoice_id'] ?? 0,
                    $user,
                    $invoice
                );

            case 'App\\Mail\\BulkThroughputWarningMail':
                return new \App\Mail\BulkThroughputWarningMail($data);

            case 'App\\Mail\\AdminUserCreatedMail':
                return new \App\Mail\AdminUserCreatedMail($data);

            case 'App\\Mail\\AdminNotificationMail':
                return new \App\Mail\AdminNotificationMail($data);

            case 'App\\Mail\\ApiErrorNotification':
                $errorData = $data['errorData'] ?? $data;
                return new \App\Mail\ApiErrorNotification($errorData);

            case 'App\\Mail\\ForgotPasswordMail':
                return new \App\Mail\ForgotPasswordMail(
                    $data['to_address'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['user_name'] ?? '',
                    $data['user_id'] ?? 0
                );

            case 'App\\Mail\\ProfileConfirmationMail':
                return new \App\Mail\ProfileConfirmationMail(
                    $data['to_address'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['form_data'] ?? [],
                    $data['confirmation_token'] ?? ''
                );

            case 'App\\Mail\\ProfileUpdateConfirmationMail':
                $user = isset($data['user']) ? (object) $data['user'] : null;
                $pending = isset($data['pending']) ? (object) $data['pending'] : null;
                return new \App\Mail\ProfileUpdateConfirmationMail(
                    $user,
                    $pending,
                    $data['confirmation_url'] ?? ''
                );

            case 'App\\Mail\\ExceptionNotificationMail':
                return new \App\Mail\ExceptionNotificationMail($data['exception_data'] ?? $data);

            case 'App\\Mail\\CronErrorNotificationMail':
                return new \App\Mail\CronErrorNotificationMail($data['cron_data'] ?? $data);

            case 'App\\Mail\\DeliveryReceiptFailureMail':
                return new \App\Mail\DeliveryReceiptFailureMail($data);

            case 'App\\Mail\\InsufficientFundsAlertMail':
                return new \App\Mail\InsufficientFundsAlertMail($data);

            case 'App\\Mail\\WalletReminderMail':
                return new \App\Mail\WalletReminderMail($data);

            case 'App\\Mail\\WalletReminderReportMail':
                return new \App\Mail\WalletReminderReportMail($data);

            case 'App\\Mail\\BulkEmail':
                return new \App\Mail\BulkEmail(
                    $data['subject'] ?? '',
                    $data['message_content'] ?? ''
                );

            case 'App\\Mail\\AdminSendLogin':
                $user = isset($data['user']) ? (object) $data['user'] : null;
                return new \App\Mail\AdminSendLogin($user, $data['cc_email'] ?? '');

            case 'App\\Mail\\ContractSignedMail':
                return new \App\Mail\ContractSignedMail($data);

            case 'App\\Mail\\ContractAgreementMail':
                return new \App\Mail\ContractAgreementMail($data);

            case 'App\\Mail\\DailyStatsReportMail':
                return new \App\Mail\DailyStatsReportMail($data['report_data'] ?? $data);

            case 'App\\Mail\\DatabaseTidyReportMail':
                return new \App\Mail\DatabaseTidyReportMail($data['report_data'] ?? $data);

            case 'App\\Mail\\InboundSmsNotificationMail':
                return new \App\Mail\InboundSmsNotificationMail($data['sms_data'] ?? $data);

            case 'App\\Mail\\StopNotificationMail':
                return new \App\Mail\StopNotificationMail($data['stop_data'] ?? $data);

            case 'App\\Mail\\RouteFixNotificationMail':
                return new \App\Mail\RouteFixNotificationMail($data['route_data'] ?? $data);

            case 'App\\Mail\\FundsAlertMail':
                return new \App\Mail\FundsAlertMail($data['alert_data'] ?? $data);

            case 'App\\Mail\\PooledVirtsAlertMail':
                return new \App\Mail\PooledVirtsAlertMail($data['alert_data'] ?? $data);

            case 'App\\Mail\\SmppProxyAuthErrorMail':
                return new \App\Mail\SmppProxyAuthErrorMail($data['error_data'] ?? $data);

            case 'App\\Mail\\SmppChecksReportMail':
                return new \App\Mail\SmppChecksReportMail($data['report_data'] ?? $data);

            case 'App\\Mail\\SmsHeartbeatAlertMail':
                return new \App\Mail\SmsHeartbeatAlertMail($data['alert_data'] ?? $data);

            case 'App\\Mail\\UrlForwardAlertMail':
                return new \App\Mail\UrlForwardAlertMail($data['alert_data'] ?? $data);

            case 'App\\Mail\\VirtualNumberExpiryReportMail':
                return new \App\Mail\VirtualNumberExpiryReportMail($data['report_data'] ?? $data);

            case 'App\\Mail\\XmlGatewayNotificationMail':
                return new \App\Mail\XmlGatewayNotificationMail($data['notification_data'] ?? $data);

            default:
                // For other mailables, try to instantiate with the data array
                return new $mailableClass($data);
        }
    }
    
    /**
     * Clean up connections
     */
    private function cleanup()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            Log::error('Error during cleanup: ' . $e->getMessage());
        }
    }
}
