<?php

namespace App\Jobs;

use App\Models\UserNotification;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $title;
    protected $message;
    protected $type;
    protected $data;

    public function __construct(int $userId, string $title, string $message, string $type = 'general', array $data = [])
    {
        $this->userId = $userId;
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->data = $data;
        $this->onQueue('push_notifications');
    }

    public function handle(): void
    {
        try {
            $pushService = app(PushNotificationService::class);
            $result = $pushService->sendToUser($this->userId, $this->title, $this->message, $this->type, $this->data);
            Log::info('Push notification sent via job', ['user_id' => $this->userId, 'result' => $result['success']]);
        } catch (\Exception $e) {
            Log::error('Push notification job failed', ['user_id' => $this->userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendPushNotificationJob failed', ['user_id' => $this->userId, 'error' => $exception->getMessage()]);
    }
}
