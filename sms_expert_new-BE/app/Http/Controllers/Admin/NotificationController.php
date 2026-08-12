<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use App\Models\AdminNotification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Services\Queue\NotificationQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationController extends Controller
{
    use LegacyCustomerList;

    protected $notificationQueueService;

    public function __construct()
    {
        $this->notificationQueueService = app(NotificationQueueService::class);
    }

    /**
     * Display a listing of notifications.
     */
    public function index(Request $request)
    {
        $query = AdminNotification::query()->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get per page value
        $perPage = $request->get('per_page', 15);

        // Get statistics counts (before pagination)
        $statsQuery = AdminNotification::query();
        if ($request->filled('status')) {
            $statsQuery->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $statsQuery->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $statsQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $statsQuery->whereDate('created_at', '<=', $request->date_to);
        }
        
        $sentCount = (clone $statsQuery)->where('status', 'sent')->count();
        $scheduledCount = (clone $statsQuery)->where('status', 'scheduled')->count();
        $draftCount = (clone $statsQuery)->where('status', 'draft')->count();
        $totalCount = (clone $statsQuery)->count();

        $notifications = $query->paginate($perPage);

        // Get customers for the create form
        $customers = $this->getLegacyCustomers();

        return view('admin.notifications.index', compact(
            'notifications', 
            'customers', 
            'perPage',
            'sentCount',
            'scheduledCount',
            'draftCount',
            'totalCount'
        ));
    }

    /**
     * Show the form for creating a new notification.
     */
    public function create()
    {
        $customers = $this->getLegacyCustomers();

        return view('admin.notifications.create', compact('customers'));
    }

    /**
     * Convert checkbox inputs to delivery_method
     */
    private function getDeliveryMethod(Request $request)
    {
        // If delivery_method is provided directly, use it
        if ($request->filled('delivery_method')) {
            return $request->delivery_method;
        }
        
        // Otherwise convert from checkboxes
        $sendWeb = $request->has('send_web');
        $sendEmail = $request->has('send_email');
        
        if ($sendWeb && $sendEmail) {
            return 'both';
        } elseif ($sendEmail) {
            return 'email';
        } else {
            return 'web'; // Default to web
        }
    }

    /**
     * Store a newly created notification.
     */
    public function store(Request $request)
    {
        // Convert checkboxes to delivery_method before validation
        $deliveryMethod = $this->getDeliveryMethod($request);
        $request->merge(['delivery_method' => $deliveryMethod]);

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,success,danger,announcement',
            'target_type' => 'required|in:all,specific',
            'delivery_method' => 'required|in:web,email,mobile,both,web_mobile,all',
            'scheduled_at' => 'nullable|date|after:now',
            'customer_ids' => 'required_if:target_type,specific|array',
            'customer_ids.*' => 'exists:users,id',
        ], [
            'scheduled_at.after' => 'The scheduled time must be in the future. Current server time is ' . Carbon::now()->format('d M Y, H:i:s') . ' (UK Time).',
        ]);

        try {
            $adminSession = Session::get('admin_user');
            
            // Determine status based on schedule and send_now
            $sendNow = $request->has('send_now') && $request->send_now == '1';
            $hasSchedule = $request->filled('scheduled_at');
            
            // Debug logging
            Log::info('NotificationController::store - Schedule Debug', [
                'send_now_param' => $request->send_now,
                'send_now_has' => $request->has('send_now'),
                'send_now_result' => $sendNow,
                'scheduled_at_raw' => $request->scheduled_at,
                'has_schedule' => $hasSchedule,
                'current_time' => Carbon::now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone'),
            ]);
            
            if ($sendNow) {
                $status = 'draft'; // Will be updated after sending
            } elseif ($hasSchedule) {
                $status = 'scheduled';
            } else {
                $status = 'draft';
            }
            
            $scheduledAt = $hasSchedule ? Carbon::parse($request->scheduled_at) : null;
            
            if ($scheduledAt) {
                Log::info('NotificationController::store - Scheduled time', [
                    'parsed_scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                    'scheduled_at_timezone' => $scheduledAt->tzName,
                ]);
            }
            
            $notification = AdminNotification::create([
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'target_type' => $request->target_type,
                'delivery_method' => $deliveryMethod,
                'requires_acknowledgment' => $request->has('requires_acknowledgment') || $request->has('requires_acknowledgement'),
                'status' => $status,
                'scheduled_at' => $scheduledAt,
                'created_by' => $adminSession['id'] ?? null,
            ]);

            $recipientIds = $request->target_type === 'all' ? [] : ($request->customer_ids ?? []);

            // Handle based on send option
            if ($sendNow) {
                // Send immediately
                $result = $this->notificationQueueService->queueNotification($notification, $recipientIds);
                if ($result) {
                    // Generate success message based on delivery method
                    $message = $this->getSuccessMessage($deliveryMethod);
                } else {
                    return back()->withInput()
                        ->with('error', 'Failed to send notification. Please check the logs for details.');
                }
            } elseif ($hasSchedule) {
                // Schedule for later
                $this->notificationQueueService->queueScheduledNotification($notification, $recipientIds);
                $scheduledTime = Carbon::parse($request->scheduled_at)->format('d M Y, h:i A');
                $message = "Notification scheduled for {$scheduledTime}. It will be sent automatically at that time.";
            } else {
                // Save as draft
                // Save recipient IDs if specific targeting
                if ($request->target_type === 'specific' && !empty($recipientIds)) {
                    $users = User::whereIn('id', $recipientIds)->get()->keyBy('id');
                    foreach ($recipientIds as $userId) {
                        $user = $users->get($userId);
                        NotificationRecipient::create([
                            'notification_id' => $notification->id,
                            'user_id' => $userId,
                            'user_bigid' => $user ? $user->bigid : null,
                            'is_read' => false,
                            'is_acknowledged' => false,
                            'email_sent' => false,
                            'web_delivered' => false,
                        ]);
                    }
                }
                $message = 'Notification saved as draft!';
            }

            return redirect()->route('admin.notifications.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->with('error', 'Failed to create notification: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified notification.
     */
    public function show($id)
    {
        $notification = AdminNotification::with(['recipients.user'])->findOrFail($id);
        
        return view('admin.notifications.show', compact('notification'));
    }

    /**
     * Show the form for editing the specified notification.
     */
    public function edit($id)
    {
        $notification = AdminNotification::with('recipients')->findOrFail($id);
        
        // Only draft or scheduled notifications can be edited
        if (!in_array($notification->status, ['draft', 'scheduled'])) {
            return redirect()->route('admin.notifications.index')
                ->with('error', 'Only draft or scheduled notifications can be edited.');
        }

        $customers = $this->getLegacyCustomers();

        $selectedCustomerIds = $notification->recipients->pluck('user_id')->toArray();

        return view('admin.notifications.edit', compact('notification', 'customers', 'selectedCustomerIds'));
    }

    /**
     * Update the specified notification.
     */
    public function update(Request $request, $id)
    {
        $notification = AdminNotification::findOrFail($id);

        // Only draft or scheduled notifications can be updated
        if (!in_array($notification->status, ['draft', 'scheduled'])) {
            return redirect()->route('admin.notifications.index')
                ->with('error', 'Only draft or scheduled notifications can be updated.');
        }

        // Convert checkboxes to delivery_method before validation
        $deliveryMethod = $this->getDeliveryMethod($request);
        $request->merge(['delivery_method' => $deliveryMethod]);

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,success,danger,announcement',
            'target_type' => 'required|in:all,specific',
            'delivery_method' => 'required|in:web,email,mobile,both,web_mobile,all',
            'scheduled_at' => 'nullable|date|after:now',
            'customer_ids' => 'required_if:target_type,specific|array',
            'customer_ids.*' => 'exists:users,id',
        ], [
            'scheduled_at.after' => 'The scheduled time must be in the future. Current server time is ' . Carbon::now()->format('d M Y, H:i:s') . ' (UK Time).',
        ]);

        try {
            // Determine status based on schedule and send_now
            $sendNow = $request->has('send_now') && $request->send_now == '1';
            $hasSchedule = $request->filled('scheduled_at');
            
            if ($sendNow) {
                $status = 'draft'; // Will be updated after sending
            } elseif ($hasSchedule) {
                $status = 'scheduled';
            } else {
                $status = 'draft';
            }

            $notification->update([
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'target_type' => $request->target_type,
                'delivery_method' => $deliveryMethod,
                'requires_acknowledgment' => $request->has('requires_acknowledgment') || $request->has('requires_acknowledgement'),
                'scheduled_at' => $hasSchedule ? Carbon::parse($request->scheduled_at) : null,
                'status' => $status,
            ]);

            // Clear old recipients
            $notification->recipients()->delete();
            
            $recipientIds = $request->target_type === 'all' ? [] : ($request->customer_ids ?? []);

            // Handle based on send option
            if ($sendNow) {
                // Send immediately
                $result = $this->notificationQueueService->queueNotification($notification, $recipientIds);
                if ($result) {
                    $message = 'Notification updated! ' . $this->getSuccessMessage($deliveryMethod);
                } else {
                    return back()->withInput()
                        ->with('error', 'Failed to send notification. Please check the logs for details.');
                }
            } elseif ($hasSchedule) {
                // Schedule for later
                $this->notificationQueueService->queueScheduledNotification($notification, $recipientIds);
                $scheduledTime = Carbon::parse($request->scheduled_at)->format('d M Y, h:i A');
                $message = "Notification updated and scheduled for {$scheduledTime}.";
            } else {
                // Save as draft with recipients
                if ($request->target_type === 'specific' && !empty($recipientIds)) {
                    $users = User::whereIn('id', $recipientIds)->get()->keyBy('id');
                    foreach ($recipientIds as $userId) {
                        $user = $users->get($userId);
                        NotificationRecipient::create([
                            'notification_id' => $notification->id,
                            'user_id' => $userId,
                            'user_bigid' => $user ? $user->bigid : null,
                            'is_read' => false,
                            'is_acknowledged' => false,
                            'email_sent' => false,
                            'web_delivered' => false,
                        ]);
                    }
                }
                $message = 'Notification updated successfully!';
            }

            return redirect()->route('admin.notifications.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to update notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->with('error', 'Failed to update notification: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified notification.
     */
    public function destroy($id)
    {
        try {
            $notification = AdminNotification::findOrFail($id);
            
            // Only draft, scheduled, or cancelled notifications can be deleted
            if (!in_array($notification->status, ['draft', 'scheduled', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft, scheduled, or cancelled notifications can be deleted.',
                ], 400);
            }

            $notification->recipients()->delete();
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send notification immediately.
     */
    public function sendNow($id)
    {
        try {
            $notification = AdminNotification::findOrFail($id);

            if (!in_array($notification->status, ['draft', 'scheduled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft or scheduled notifications can be sent.',
                ], 400);
            }

            $recipientIds = $notification->recipients->pluck('user_id')->toArray();
            
            $this->notificationQueueService->queueNotification($notification, $recipientIds);

            return response()->json([
                'success' => true,
                'message' => 'Notification is being sent!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get success message based on delivery method.
     */
    private function getSuccessMessage(string $deliveryMethod): string
    {
        return match($deliveryMethod) {
            'web' => 'Web notifications delivered successfully!',
            'email' => 'Email notifications sent successfully!',
            'mobile' => 'Mobile push notifications are being sent!',
            'both' => 'Web notifications delivered and emails are being sent.',
            'web_mobile' => 'Web notifications delivered and mobile push notifications are being sent!',
            'all' => 'All notifications sent! Web delivered, emails and mobile push notifications are being sent.',
            default => 'Notification sent successfully!',
        };
    }

    /**
     * Get notification statistics.
     */
    public function statistics(Request $request, $id)
    {
        try {
            $notification = AdminNotification::findOrFail($id);

            // Get email sent count
            $emailSentCount = $notification->recipients()->where('email_sent', true)->count();
            
            // Get push sent count
            $pushSentCount = $notification->recipients()->where('push_sent', true)->count();
            
            // Get actual read count from recipients
            $actualReadCount = $notification->recipients()->where('is_read', true)->count();
            
            // Get actual acknowledged count from recipients
            $actualAcknowledgedCount = $notification->recipients()->where('is_acknowledged', true)->count();
            
            // Sync the counts if they differ
            if ($notification->read_count != $actualReadCount || $notification->acknowledged_count != $actualAcknowledgedCount) {
                $notification->syncCounts();
                $notification->refresh();
            }

            // Get per page value
            $perPage = $request->get('per_page', 15);

            // Get detailed recipient list with pagination
            $recipients = $notification->recipients()
                ->with('user:id,bigid,busname,contactname,contactemail')
                ->paginate($perPage);

            return view('admin.notifications.statistics', compact('notification', 'recipients', 'emailSentCount', 'pushSentCount', 'perPage'));

        } catch (\Exception $e) {
            Log::error('Failed to get notification statistics', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.notifications.index')
                ->with('error', 'Failed to get statistics: ' . $e->getMessage());
        }
    }
}
