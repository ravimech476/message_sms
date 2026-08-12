<?php

namespace App\Services\Queue;

use App\Models\UserNotification;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class PushNotificationQueueService
{
    protected $rabbitMQService;
    protected $pushQueueName = 'push.notifications';

    public function __construct()
    {
        $this->rabbitMQService = app(RabbitMQService::class);
    }

    /**
     * Queue a push notification for sending via RabbitMQ
     *
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $data Additional data
     * @param int $priority Queue priority (1-10)
     * @return bool
     */
    public function queuePushNotification(
        int $userId,
        string $title,
        string $message,
        string $type = 'general',
        array $data = [],
        int $priority = 5
    ): bool {
        try {
            // Get user details
            $user = DB::table('users')->where('id', $userId)->first();
            
            if (!$user) {
                Log::warning('User not found for push notification queue', ['user_id' => $userId]);
                return false;
            }

            $queueData = [
                'type' => 'push_notification',
                'user_id' => $userId,
                'user_bigid' => $user->bigid,
                'title' => $title,
                'message' => $message,
                'notification_type' => $type,
                'data' => $data,
                'queued_at' => Carbon::now()->toIso8601String(),
            ];

            $result = $this->rabbitMQService->publishToQueue(
                $this->pushQueueName,
                $queueData,
                $priority
            );

            if ($result) {
                Log::info('Push notification queued', [
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title,
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to queue push notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Queue push notification by user bigid
     *
     * @param string $userBigId User BigID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $data Additional data
     * @param int $priority Queue priority
     * @return bool
     */
    public function queuePushNotificationByBigId(
        string $userBigId,
        string $title,
        string $message,
        string $type = 'general',
        array $data = [],
        int $priority = 5
    ): bool {
        try {
            $user = DB::table('users')->where('bigid', $userBigId)->first();
            
            if (!$user) {
                Log::warning('User not found for push notification queue', ['bigid' => $userBigId]);
                return false;
            }

            return $this->queuePushNotification(
                $user->id,
                $title,
                $message,
                $type,
                $data,
                $priority
            );

        } catch (Exception $e) {
            Log::error('Failed to queue push notification by bigid', [
                'bigid' => $userBigId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Queue wallet low balance notification
     */
    public function queueWalletLowNotification(string $userBigId, string $balance, string $username): bool
    {
        return $this->queuePushNotificationByBigId(
            $userBigId,
            'Low Wallet Balance Alert',
            "Your SMS wallet balance is low (£{$balance}). Top up now to continue sending messages.",
            UserNotification::TYPE_WALLET_LOW,
            [
                'balance' => $balance,
                'username' => $username,
                'action' => 'top_up_wallet',
                'screen' => 'BuySms',
            ],
            8 // High priority
        );
    }

    /**
     * Queue wallet insufficient funds notification
     */
    public function queueWalletInsufficientNotification(string $userBigId, string $username): bool
    {
        return $this->queuePushNotificationByBigId(
            $userBigId,
            'Insufficient Funds Alert',
            "SMS send failed due to insufficient wallet funds. Please top up your account to continue sending.",
            UserNotification::TYPE_WALLET_INSUFFICIENT,
            [
                'username' => $username,
                'action' => 'top_up_wallet',
                'screen' => 'BuySms',
            ],
            9 // Very high priority
        );
    }

    /**
     * Queue throughput limit notification
     */
    public function queueThroughputLimitNotification(string $userBigId, string $username, int $limit, int $sent): bool
    {
        return $this->queuePushNotificationByBigId(
            $userBigId,
            'Daily SMS Limit Reached',
            "You have reached your daily SMS limit of {$limit} messages. The limit will reset at midnight.",
            UserNotification::TYPE_THROUGHPUT_LIMIT,
            [
                'username' => $username,
                'limit' => $limit,
                'sent' => $sent,
                'action' => 'view_limits',
                'screen' => 'Dashboard',
            ],
            7 // High priority
        );
    }

    /**
     * Queue system notification
     */
    public function queueSystemNotification(string $userBigId, string $title, string $message, array $data = []): bool
    {
        return $this->queuePushNotificationByBigId(
            $userBigId,
            $title,
            $message,
            UserNotification::TYPE_SYSTEM,
            $data,
            5 // Normal priority
        );
    }

    /**
     * Queue bulk push notifications to multiple users
     */
    public function queueBulkPushNotifications(
        array $userIds,
        string $title,
        string $message,
        string $type = 'general',
        array $data = [],
        int $priority = 5
    ): array {
        $results = [
            'total' => count($userIds),
            'queued' => 0,
            'failed' => 0,
        ];

        foreach ($userIds as $userId) {
            if ($this->queuePushNotification($userId, $title, $message, $type, $data, $priority)) {
                $results['queued']++;
            } else {
                $results['failed']++;
            }
        }

        Log::info('Bulk push notifications queued', $results);
        return $results;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        return $this->rabbitMQService->getQueueStats($this->pushQueueName);
    }
}
