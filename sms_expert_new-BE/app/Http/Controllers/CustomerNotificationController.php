<?php

namespace App\Http\Controllers;

use App\Models\NotificationRecipient;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class CustomerNotificationController extends Controller
{
    /**
     * Delivery methods that include web delivery
     */
    private const WEB_DELIVERY_METHODS = ['web', 'both', 'web_mobile', 'all'];

    /**
     * Get notifications for the current customer.
     * Only returns notifications where delivery_method includes web
     */
    public function getNotifications(Request $request)
    {
        try {
            $userInfo = Session::get('user_info');
            $userId = $userInfo['id'] ?? null;
            $userBigid = $userInfo['bigid'] ?? null;

            if (!$userId && !$userBigid) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);

            // Only get notifications that are meant for web delivery
            $query = NotificationRecipient::with(['notification' => function ($q) {
                    $q->where('status', 'sent')
                      ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
                }])
                ->where('web_delivered', true) // Only web-delivered notifications
                ->whereHas('notification', function ($q) {
                    $q->where('status', 'sent')
                      ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
                });

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('user_bigid', $userBigid);
            }

            $notifications = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $formattedNotifications = $notifications->map(function ($item) {
                return [
                    'id' => $item->id,
                    'notification_id' => $item->notification_id,
                    'title' => $item->notification->title ?? '',
                    'message' => $item->notification->message ?? '',
                    'type' => $item->notification->type ?? 'info',
                    'type_icon' => $item->notification->type_icon ?? 'notifications',
                    'type_color' => $item->notification->type_badge_color ?? 'primary',
                    'requires_acknowledgment' => $item->notification->requires_acknowledgment ?? false,
                    'is_read' => $item->is_read,
                    'is_acknowledged' => $item->is_acknowledged,
                    'read_at' => $item->read_at ? $item->read_at->diffForHumans() : null,
                    'acknowledged_at' => $item->acknowledged_at ? $item->acknowledged_at->diffForHumans() : null,
                    'created_at' => $item->created_at->diffForHumans(),
                    'created_at_formatted' => $item->created_at->format('M d, Y H:i'),
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
            Log::error('Failed to get notifications', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get notifications',
            ], 500);
        }
    }

    /**
     * Get unread notification count.
     * Only counts notifications where delivery_method includes web
     */
    public function getUnreadCount()
    {
        try {
            $userInfo = Session::get('user_info');
            $userId = $userInfo['id'] ?? null;
            $userBigid = $userInfo['bigid'] ?? null;

            if (!$userId && !$userBigid) {
                return response()->json([
                    'success' => false,
                    'count' => 0,
                ], 401);
            }

            // Only count web-delivered notifications
            $query = NotificationRecipient::where('is_read', false)
                ->where('web_delivered', true)
                ->whereHas('notification', function ($q) {
                    $q->where('status', 'sent')
                      ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
                });

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('user_bigid', $userBigid);
            }

            $count = $query->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get unread count', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead($id)
    {
        try {
            $userInfo = Session::get('user_info');
            $userId = $userInfo['id'] ?? null;
            $userBigid = $userInfo['bigid'] ?? null;

            $recipient = NotificationRecipient::where('id', $id)
                ->where(function ($q) use ($userId, $userBigid) {
                    if ($userId) {
                        $q->where('user_id', $userId);
                    } else {
                        $q->where('user_bigid', $userBigid);
                    }
                })
                ->first();

            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            $recipient->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'recipient_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as read',
            ], 500);
        }
    }

    /**
     * Acknowledge notification.
     */
    public function acknowledge($id)
    {
        try {
            $userInfo = Session::get('user_info');
            $userId = $userInfo['id'] ?? null;
            $userBigid = $userInfo['bigid'] ?? null;

            $recipient = NotificationRecipient::where('id', $id)
                ->where(function ($q) use ($userId, $userBigid) {
                    if ($userId) {
                        $q->where('user_id', $userId);
                    } else {
                        $q->where('user_bigid', $userBigid);
                    }
                })
                ->first();

            if (!$recipient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            $recipient->markAsAcknowledged();

            return response()->json([
                'success' => true,
                'message' => 'Notification acknowledged',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to acknowledge notification', [
                'recipient_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge',
            ], 500);
        }
    }

    /**
     * Get notifications requiring acknowledgment (for popup).
     * Only returns notifications where delivery_method includes web
     */
    public function getPendingAcknowledgement()
    {
        try {
            $userInfo = Session::get('user_info');
            $userId = $userInfo['id'] ?? null;
            $userBigid = $userInfo['bigid'] ?? null;

            if (!$userId && !$userBigid) {
                return response()->json([
                    'success' => false,
                    'notifications' => [],
                ], 401);
            }

            // Only get web-delivered notifications that require acknowledgment
            $query = NotificationRecipient::with('notification')
                ->where('is_acknowledged', false)
                ->where('web_delivered', true)
                ->whereHas('notification', function ($q) {
                    $q->where('status', 'sent')
                      ->where('requires_acknowledgment', true)
                      ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
                });

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('user_bigid', $userBigid);
            }

            $pendingNotifications = $query->orderBy('created_at', 'asc')->get();

            $formatted = $pendingNotifications->map(function ($item) {
                return [
                    'id' => $item->id,
                    'notification_id' => $item->notification_id,
                    'title' => $item->notification->title ?? '',
                    'message' => $item->notification->message ?? '',
                    'type' => $item->notification->type ?? 'info',
                    'type_icon' => $item->notification->type_icon ?? 'notifications',
                    'type_color' => $item->notification->type_badge_color ?? 'primary',
                    'created_at' => $item->created_at->diffForHumans(),
                    'created_at_formatted' => $item->created_at->format('M d, Y H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'notifications' => $formatted,
                'has_pending' => $formatted->count() > 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get pending acknowledgment notifications', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'notifications' => [],
                'has_pending' => false,
            ], 500);
        }
    }
}
