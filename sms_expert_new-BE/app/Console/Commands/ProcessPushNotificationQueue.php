<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class ProcessPushNotificationQueue extends Command
{
    protected $signature = 'queue:push-notifications 
                            {--once : Process one message and exit}
                            {--limit=0 : Process limited number of messages (0 = unlimited)}';

    protected $description = 'Process push notifications from RabbitMQ queue';

    protected $rabbitMQService;
    protected $pushService;
    protected $processedCount = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting push notification queue consumer...');
        Log::info('Push notification queue consumer started');

        $this->rabbitMQService = app(RabbitMQService::class);
        $this->pushService = app(PushNotificationService::class);

        $once = $this->option('once');
        $limit = (int) $this->option('limit');

        try {
            if ($once) {
                $this->processOnce();
            } else {
                $this->processQueue($limit);
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Push notification queue consumer error', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }

    protected function processOnce()
    {
        $this->info('Processing single message...');
        
        $this->rabbitMQService->consumeFromQueue(
            'push.notifications',
            function ($data) {
                $this->processMessage($data);
                return true;
            },
            1
        );
    }

    protected function processQueue($limit = 0)
    {
        $this->info('Processing queue continuously' . ($limit > 0 ? " (limit: {$limit})" : '') . '...');
        
        $this->rabbitMQService->consumeFromQueue(
            'push.notifications',
            function ($data) use ($limit) {
                $result = $this->processMessage($data);
                $this->processedCount++;

                if ($limit > 0 && $this->processedCount >= $limit) {
                    $this->info("Reached limit of {$limit} messages");
                    return false; // Stop consuming
                }

                return $result;
            },
            1
        );
    }

    protected function processMessage(array $data): bool
    {
        try {
            $userId = $data['user_id'] ?? null;
            $title = $data['title'] ?? 'Notification';
            $message = $data['message'] ?? '';
            $type = $data['notification_type'] ?? 'general';
            $extraData = $data['data'] ?? [];

            if (!$userId) {
                Log::warning('Push notification missing user_id', ['data' => $data]);
                return true; // Acknowledge to remove from queue
            }

            $this->info("Processing notification for user {$userId}: {$title}");

            $result = $this->pushService->sendToUser($userId, $title, $message, $type, $extraData);

            if ($result['success']) {
                $this->info("✓ Notification sent successfully");
                Log::info('Push notification processed', [
                    'user_id' => $userId,
                    'notification_id' => $result['notification_id'] ?? null,
                ]);
            } else {
                $this->warn("✗ Notification stored but push may have failed: " . ($result['message'] ?? ''));
                Log::warning('Push notification processed with issues', [
                    'user_id' => $userId,
                    'result' => $result,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            $this->error("Error processing message: " . $e->getMessage());
            Log::error('Push notification processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return false; // Will be retried
        }
    }
}
