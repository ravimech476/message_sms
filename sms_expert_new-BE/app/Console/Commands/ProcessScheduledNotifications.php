<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Services\Queue\NotificationQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled notifications that are due to be sent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $this->info('Checking for scheduled notifications...');
        $this->info("Current server time: {$now->format('Y-m-d H:i:s')} ({$now->tzName})");
        
        Log::info('ProcessScheduledNotifications: Checking for scheduled notifications', [
            'current_time' => $now->format('Y-m-d H:i:s'),
            'timezone' => $now->tzName,
        ]);

        // Find all scheduled notifications - show what we're checking
        $allScheduled = AdminNotification::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->get();
        
        if ($allScheduled->isNotEmpty()) {
            $this->info("All scheduled notifications:");
            foreach ($allScheduled as $n) {
                $scheduledTime = Carbon::parse($n->scheduled_at);
                $isDue = $scheduledTime->lte($now);
                $this->info("  - #{$n->id}: '{$n->title}' scheduled for {$scheduledTime->format('Y-m-d H:i:s')} - " . ($isDue ? 'DUE' : 'NOT DUE (in ' . $scheduledTime->diffForHumans($now) . ')'));
                
                Log::info('ProcessScheduledNotifications: Checking notification', [
                    'id' => $n->id,
                    'title' => $n->title,
                    'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
                    'scheduled_at_raw' => $n->scheduled_at,
                    'current_time' => $now->format('Y-m-d H:i:s'),
                    'is_due' => $isDue,
                    'diff' => $scheduledTime->diffForHumans($now),
                ]);
            }
        }

        // Find all scheduled notifications that are due
        $notifications = AdminNotification::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($notifications->isEmpty()) {
            $this->info('No scheduled notifications to process at this time.');
            return 0;
        }

        $this->info("Found {$notifications->count()} notification(s) that are DUE to be sent.");
        Log::info("ProcessScheduledNotifications: Found {$notifications->count()} notification(s) to process");

        $notificationService = new NotificationQueueService();

        foreach ($notifications as $notification) {
            try {
                $scheduledTime = Carbon::parse($notification->scheduled_at);
                
                $this->info("Processing notification #{$notification->id}: {$notification->title}");
                $this->info("  - Scheduled for: {$scheduledTime->format('Y-m-d H:i:s')}");
                $this->info("  - Target type: {$notification->target_type}");
                $this->info("  - Delivery method: {$notification->delivery_method}");
                
                Log::info("ProcessScheduledNotifications: Processing notification", [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
                    'target_type' => $notification->target_type,
                    'delivery_method' => $notification->delivery_method,
                ]);

                // Get recipient IDs if target type is specific
                $recipientIds = [];
                if ($notification->target_type === 'specific') {
                    $recipientIds = $notification->recipients()->pluck('user_id')->toArray();
                    $this->info("  - Specific recipients: " . count($recipientIds));
                    Log::info("ProcessScheduledNotifications: Specific recipients", [
                        'count' => count($recipientIds),
                        'ids' => $recipientIds,
                    ]);
                }

                // Send the notification using the queue service
                $result = $notificationService->queueNotification($notification, $recipientIds);

                if ($result) {
                    $this->info("✓ Notification #{$notification->id} sent successfully!");
                    
                    Log::info('ProcessScheduledNotifications: Notification sent successfully', [
                        'notification_id' => $notification->id,
                        'title' => $notification->title,
                        'scheduled_at' => $notification->scheduled_at,
                        'delivery_method' => $notification->delivery_method,
                    ]);
                } else {
                    $this->error("✗ Failed to send notification #{$notification->id}");
                    
                    // Mark as draft for retry
                    $notification->update(['status' => 'draft']);
                    
                    Log::error('ProcessScheduledNotifications: Failed to send notification', [
                        'notification_id' => $notification->id,
                    ]);
                }

            } catch (\Exception $e) {
                $this->error("Error processing notification #{$notification->id}: " . $e->getMessage());
                
                Log::error('ProcessScheduledNotifications: Exception while processing', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info('Finished processing scheduled notifications.');
        return 0;
    }
}
