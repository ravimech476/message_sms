<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\AdminNotificationController;
use App\Models\AdminNotification;
use App\Services\Queue\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessNotificationQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:notifications 
                            {--once : Process one message and exit}
                            {--timeout=60 : Consumer timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process admin notifications from RabbitMQ queue';

    protected $rabbitMQService;

    /**
     * Create a new command instance.
     */
    public function __construct(RabbitMQService $rabbitMQService)
    {
        parent::__construct();
        $this->rabbitMQService = $rabbitMQService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $once = $this->option('once');
        $timeout = (int) $this->option('timeout');

        $this->info('Starting notification queue consumer...');

        try {
            $callback = function ($msg) use ($once) {
                $this->processMessage($msg);
                
                if ($once) {
                    // Stop consuming after one message
                    $msg->getChannel()->basic_cancel($msg->getConsumerTag());
                }
            };

            $this->rabbitMQService->consumeMessages('notifications_queue', $callback, $timeout);

        } catch (\Exception $e) {
            $this->error('Error consuming notifications: ' . $e->getMessage());
            Log::error('Notification queue consumer error: ' . $e->getMessage());
            return 1;
        }

        $this->info('Notification queue consumer stopped.');
        return 0;
    }

    /**
     * Process a single message
     */
    protected function processMessage($msg)
    {
        try {
            $data = json_decode($msg->getBody(), true);

            if (!isset($data['notification_id'])) {
                $this->warn('Invalid message: missing notification_id');
                $msg->ack();
                return;
            }

            $notificationId = $data['notification_id'];
            $this->info("Processing notification #{$notificationId}");

            $notification = AdminNotification::find($notificationId);

            if (!$notification) {
                $this->warn("Notification #{$notificationId} not found");
                $msg->ack();
                return;
            }

            // Process the notification
            $controller = app(AdminNotificationController::class);
            $controller->processNotificationDirectly($notification);

            $this->info("Notification #{$notificationId} processed successfully");
            $msg->ack();

        } catch (\Exception $e) {
            $this->error('Error processing notification: ' . $e->getMessage());
            Log::error('Notification processing error: ' . $e->getMessage(), [
                'message' => $msg->getBody(),
            ]);
            
            // Reject and requeue
            $msg->nack(true);
        }
    }
}
