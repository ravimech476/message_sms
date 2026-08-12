<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class CustomerNotificationController extends Controller
{
    /**
     * Get current user's bigid from session
     */
    private function getUserBigid()
    {
        $userInfo = Session::get('user_info');
        return $userInfo['bigid'] ?? null;
    }

    /**
     * Get unread count for the notification bell
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $userBigid = $this->getUserBigid();
            
            if (!$userBigid) {
                return response()->json(['success' => false, 'count' => 0]);
            }

            // Only count notifications that should be delivered to web
            // delivery_method: web, both, web_mobile, all - all include web delivery
            // delivery_method: mobile, email - should NOT be shown on web
            $count = NotificationRecipient::where('user_bigid', $userBigid)
                ->where('is_read', false)
                ->where('web_delivered', true) // Only count if marked for web delivery
                ->whereHas('notification', function ($query) {
                    $query->where('status', 'sent')
                          ->whereIn('delivery_method', ['web', 'both', 'web_mobile', 'all']);
                })
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get unread count: ' . $e->getMessage());
            return response()->json(['success' => false, 'count' => 0]);
        }
    }

    /**
     * Get pending acknowledgement notifications (for popup)
     */
    public function getPendingAcknowledgement(Request $request)
    {
        try {
            $userBigid = $this->getUserBigid();
            
            if (!$userBigid) {
                return response()->json([
                    'success' => false,
                    'has_pending' => false,
                    'notifications' => []
                ]);
            }

            // Only get web-delivered notifications that require acknowledgment
            $pendingNotifications = NotificationRecipient::with('notification')
                ->where('user_bigid', $userBigid)
                ->where('is_acknowledged', false)
                ->where('web_delivered', true) // Only for web-delivered notifications
                ->whereHas('notification', function ($query) {
                    $query->where('status', 'sent')
                          ->where('requires_acknowledgment', true)
                          ->whereIn('delivery_method', ['web', 'both', 'web_mobile', 'all']);
                })
                ->orderBy('created_at', 'asc') // Show oldest first
                ->get();

            $formattedNotifications = $pendingNotifications->map(function ($recipient) {
                return [
                    'id' => $recipient->id,
                    'notification_id' => $recipient->notification_id,
                    'title' => $recipient->notification->title ?? '',
                    'message' => $recipient->notification->message ?? '',
                    'type' => $recipient->notification->type ?? 'info',
                    'requires_acknowledgment' => true,
                    'created_at_formatted' => $recipient->created_at->format('M d, Y h:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'has_pending' => $formattedNotifications->count() > 0,
                'notifications' => $formattedNotifications
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get pending acknowledgements: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'has_pending' => false,
                'notifications' => []
            ]);
        }
    }

    /**
     * Get notifications for the current customer (paginated)
     */
    public function getNotifications(Request $request)
    {
        try {
            $userBigid = $this->getUserBigid();
            
            if (!$userBigid) {
                return response()->json([
                    'success' => false,
                    'notifications' => [],
                    'pagination' => []
                ]);
            }

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);

            // Get notifications for web delivery
            // Only show notifications that have web_delivered = true
            $notifications = NotificationRecipient::with('notification')
                ->where('user_bigid', $userBigid)
                ->where('web_delivered', true) // Only web-delivered notifications
                ->whereHas('notification', function ($query) {
                    $query->where('status', 'sent')
                          ->whereIn('delivery_method', ['web', 'both', 'web_mobile', 'all']);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $typeIconMap = [
                'info' => 'info',
                'warning' => 'warning',
                'success' => 'check_circle',
                'danger' => 'error',
                'announcement' => 'campaign',
            ];

            $formattedNotifications = $notifications->getCollection()->map(function ($recipient) use ($typeIconMap) {
                $type = $recipient->notification->type ?? 'info';
                return [
                    'id' => $recipient->id,
                    'notification_id' => $recipient->notification_id,
                    'title' => $recipient->notification->title ?? '',
                    'message' => $recipient->notification->message ?? '',
                    'type' => $type,
                    'type_icon' => $typeIconMap[$type] ?? 'info',
                    'requires_acknowledgment' => $recipient->notification->requires_acknowledgment ?? false,
                    'is_read' => $recipient->is_read,
                    'is_acknowledged' => $recipient->is_acknowledged,
                    'created_at' => $recipient->created_at->diffForHumans(),
                    'created_at_formatted' => $recipient->created_at->format('M d, Y h:i A'),
                ];
            });

            return response()->json([
                'success' => true,
                'notifications' => $formattedNotifications,
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'notifications' => [],
                'error' => 'Failed to get notifications'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $userBigid = $this->getUserBigid();
            
            if (!$userBigid) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
            }

            $recipient = NotificationRecipient::where('id', $id)
                ->where('user_bigid', $userBigid)
                ->first();

            if (!$recipient) {
                return response()->json(['success' => false, 'error' => 'Notification not found'], 404);
            }

            $recipient->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Notification marked as read']);

        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to mark as read'], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $userBigid = $this->getUserBigid();
            
            if (!$userBigid) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
            }

            NotificationRecipient::where('user_bigid', $userBigid)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json(['success' => true, 'message' => 'All notifications marked as read']);

        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to mark all as read'], 500);
        }
    }

    /**
     * Acknowledge notification
     */
    public function acknowledge(Request $request, $id)
    {
        try {
            $userBigid = $this->getUserBigid();
            
            if (!$userBigid) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
            }

            $recipient = NotificationRecipient::where('id', $id)
                ->where('user_bigid', $userBigid)
                ->first();

            if (!$recipient) {
                return response()->json(['success' => false, 'error' => 'Notification not found'], 404);
            }

            $recipient->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
                'is_read' => true,
                'read_at' => $recipient->read_at ?? now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Notification acknowledged']);

        } catch (\Exception $e) {
            Log::error('Failed to acknowledge notification: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to acknowledge'], 500);
        }
    }
}
