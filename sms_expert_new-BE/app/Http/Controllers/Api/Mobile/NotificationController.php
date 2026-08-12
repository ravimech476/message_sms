<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\UserNotification;
use App\Models\UserFcmToken;

/**
 * Mobile App Notification Controller
 * 
 * Handles notifications for the mobile application
 * - Admin notifications (from admin_notifications table)
 * - User notifications (push notifications from user_notifications table)
 */
class NotificationController extends Controller
{
    /**
     * Get all notifications (both admin and user notifications)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $userId = $user->id;

            // Pagination
            $page = (int) $request->get('page', 1);
            $perPage = min((int) $request->get('per_page', 20), 50);
            $offset = ($page - 1) * $perPage;

            // Filter
            $unreadOnly = $request->boolean('unread_only', false);
            $source = $request->get('source'); // 'admin', 'push', or null for all

            $notifications = collect();
            $total = 0;

            // Get admin notifications if not filtering by push only
            if ($source !== 'push') {
                $adminNotifications = $this->getAdminNotifications($bigid, $unreadOnly, $page, $perPage);
                $notifications = $notifications->merge($adminNotifications['items']);
                $total += $adminNotifications['total'];
            }

            // Get user/push notifications if not filtering by admin only
            if ($source !== 'admin') {
                $userNotifications = $this->getUserNotifications($userId, $bigid, $unreadOnly, $page, $perPage);
                $notifications = $notifications->merge($userNotifications['items']);
                $total += $userNotifications['total'];
            }

            // Sort all by created_at descending
            $notifications = $notifications->sortByDesc('created_at')->values();

            // Apply pagination manually if getting both sources
            if (!$source) {
                $notifications = $notifications->slice($offset, $perPage)->values();
            }

            $totalPages = ceil($total / $perPage);

            return response()->json([
                'status' => true,
                'message' => 'Notifications retrieved successfully',
                'data' => [
                    'items' => $notifications,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Notifications Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load notifications'
            ], 500);
        }
    }

    /**
     * Get admin notifications
     * Uses notification_recipients table which links to admin_notifications
     * Only returns notifications where delivery_method includes mobile (mobile, web_mobile, all)
     */
    private function getAdminNotifications($bigid, $unreadOnly, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;

        // Query notification_recipients joined with admin_notifications
        // Filter by delivery methods that include mobile: mobile, web_mobile, all
        $query = DB::table('notification_recipients as nr')
            ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
            ->where('nr.user_bigid', $bigid)
            ->where('n.status', 'sent')
            ->whereIn('n.delivery_method', ['mobile', 'web_mobile', 'all']); // Only mobile-enabled notifications

        if ($unreadOnly) {
            $query->where('nr.is_read', false);
        }

        $total = $query->count();

        $items = $query
            ->select([
                'nr.id as recipient_id',
                'n.id',
                'n.title',
                'n.message',
                'n.type',
                'n.delivery_method',
                'n.requires_acknowledgment',
                'n.created_at',
                'n.sent_at',
                'nr.is_read',
                'nr.is_acknowledged',
                'nr.read_at',
                'nr.acknowledged_at',
                'nr.push_sent',
            ])
            ->orderBy('n.created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => 'admin_' . $notification->recipient_id,
                    'notification_id' => $notification->id,
                    'recipient_id' => $notification->recipient_id,
                    'source' => 'admin',
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'message_preview' => substr(strip_tags($notification->message), 0, 100) . (strlen($notification->message) > 100 ? '...' : ''),
                    'type' => $notification->type,
                    'icon' => $this->getIconForType($notification->type),
                    'priority' => 'normal',
                    'requires_acknowledgement' => (bool) $notification->requires_acknowledgment,
                    'is_read' => (bool) $notification->is_read,
                    'is_acknowledged' => (bool) $notification->is_acknowledged,
                    'read_at' => $notification->read_at ? Carbon::parse($notification->read_at)->toIso8601String() : null,
                    'acknowledged_at' => $notification->acknowledged_at ? Carbon::parse($notification->acknowledged_at)->toIso8601String() : null,
                    'created_at' => Carbon::parse($notification->created_at)->toIso8601String(),
                    'time_ago' => Carbon::parse($notification->created_at)->diffForHumans(),
                    'push_sent' => (bool) $notification->push_sent,
                    'data' => [
                        'notification_id' => (string) $notification->id,
                        'recipient_id' => (string) $notification->recipient_id,
                        'type' => $notification->type,
                        'action' => 'view_notification',
                        'screen' => 'Notifications',
                    ],
                ];
            });

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get user/push notifications
     */
    private function getUserNotifications($userId, $bigid, $unreadOnly, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;

        $query = UserNotification::where('user_bigid', $bigid);

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $total = $query->count();

        $items = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => 'push_' . $notification->id,
                    'notification_id' => $notification->id,
                    'source' => 'push',
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'message_preview' => substr(strip_tags($notification->message), 0, 100) . '...',
                    'type' => $notification->type,
                    'icon' => $notification->icon ?? $this->getIconForType($notification->type),
                    'priority' => 'normal',
                    'requires_acknowledgement' => false,
                    'is_read' => $notification->is_read,
                    'is_acknowledged' => true, // Push notifications don't require acknowledgement
                    'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                    'acknowledged_at' => null,
                    'created_at' => $notification->created_at->toIso8601String(),
                    'time_ago' => $notification->created_at->diffForHumans(),
                    'data' => $notification->data,
                ];
            });

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get icon for notification type
     */
    private function getIconForType($type)
    {
        $icons = [
            'wallet_low' => 'wallet',
            'wallet_insufficient' => 'wallet-warning',
            'throughput_limit' => 'speedometer',
            'system' => 'information-circle',
            'info' => 'information-circle',
            'warning' => 'warning',
            'urgent' => 'alert-circle',
            'promo' => 'gift',
            'general' => 'notifications',
            'campaign' => 'paper-plane',
            'delivery' => 'checkmark-done',
        ];

        return $icons[$type] ?? 'notifications';
    }

    /**
     * Get unread notification count
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $userId = $user->id;

            // Count admin notifications (only mobile-enabled: mobile, web_mobile, all)
            $adminCount = DB::table('notification_recipients as nr')
                ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
                ->where('nr.user_bigid', $bigid)
                ->where('nr.is_read', false)
                ->where('n.status', 'sent')
                ->whereIn('n.delivery_method', ['mobile', 'web_mobile', 'all']) // Only mobile-enabled
                ->count();

            // Count user/push notifications
            $pushCount = UserNotification::where('user_bigid', $bigid)
                ->where('is_read', false)
                ->count();

            // Count requiring acknowledgement (admin only, mobile-enabled)
            $acknowledgementRequired = DB::table('notification_recipients as nr')
                ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
                ->where('nr.user_bigid', $bigid)
                ->where('nr.is_acknowledged', false)
                ->where('n.status', 'sent')
                ->where('n.requires_acknowledgment', true)
                ->whereIn('n.delivery_method', ['mobile', 'web_mobile', 'all']) // Only mobile-enabled
                ->count();

            return response()->json([
                'status' => true,
                'message' => 'Unread count retrieved successfully',
                'data' => [
                    'unread_count' => $adminCount + $pushCount,
                    'admin_unread' => $adminCount,
                    'push_unread' => $pushCount,
                    'acknowledgement_required' => $acknowledgementRequired,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Unread Count Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to get unread count'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     * 
     * @param Request $request
     * @param string $id Format: 'admin_{id}' or 'push_{id}' or just numeric id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Parse the ID
            if (str_starts_with($id, 'push_')) {
                $notificationId = (int) str_replace('push_', '', $id);
                return $this->markPushAsRead($notificationId, $bigid);
            } elseif (str_starts_with($id, 'admin_')) {
                $notificationId = (int) str_replace('admin_', '', $id);
                return $this->markAdminAsRead($notificationId, $bigid);
            } else {
                // Try admin first, then push
                $adminResult = $this->markAdminAsRead((int) $id, $bigid);
                if ($adminResult->getStatusCode() === 200) {
                    return $adminResult;
                }
                return $this->markPushAsRead((int) $id, $bigid);
            }

        } catch (\Throwable $ex) {
            \Log::error('Mobile Mark Read Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    private function markAdminAsRead($id, $bigid)
    {
        // Check if this is a recipient_id (from the 'admin_X' format)
        // The $id here could be the recipient_id from notification_recipients table
        $recipient = DB::table('notification_recipients as nr')
            ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
            ->where('nr.id', $id)
            ->where('nr.user_bigid', $bigid)
            ->where('n.status', 'sent')
            ->select('nr.*', 'n.id as admin_notification_id')
            ->first();

        // If not found by recipient_id, try by notification_id
        if (!$recipient) {
            $recipient = DB::table('notification_recipients as nr')
                ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
                ->where('nr.notification_id', $id)
                ->where('nr.user_bigid', $bigid)
                ->where('n.status', 'sent')
                ->select('nr.*', 'n.id as admin_notification_id')
                ->first();
        }

        if (!$recipient) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        // Only update if not already read
        if (!$recipient->is_read) {
            DB::table('notification_recipients')
                ->where('id', $recipient->id)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                    'updated_at' => now(),
                ]);

            // Increment read_count on admin_notifications table
            DB::table('admin_notifications')
                ->where('id', $recipient->admin_notification_id)
                ->increment('read_count');

            \Log::info('Notification marked as read', [
                'recipient_id' => $recipient->id,
                'notification_id' => $recipient->admin_notification_id,
                'user_bigid' => $bigid,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read'
        ], 200);
    }

    private function markPushAsRead($id, $bigid)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_bigid', $bigid)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read'
        ], 200);
    }

    /**
     * Mark all notifications as read
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            $adminCount = 0;
            $pushCount = 0;

            // Mark all admin notifications as read (only mobile-enabled)
            // First get the IDs and notification_ids, then update
            $unreadRecipients = DB::table('notification_recipients as nr')
                ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
                ->where('nr.user_bigid', $bigid)
                ->where('nr.is_read', false)
                ->where('n.status', 'sent')
                ->whereIn('n.delivery_method', ['mobile', 'web_mobile', 'all'])
                ->select('nr.id', 'nr.notification_id')
                ->get();

            if ($unreadRecipients->count() > 0) {
                $recipientIds = $unreadRecipients->pluck('id')->toArray();
                
                // Update recipients as read
                $adminCount = DB::table('notification_recipients')
                    ->whereIn('id', $recipientIds)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                        'updated_at' => now(),
                    ]);

                // Increment read_count for each notification
                $notificationIds = $unreadRecipients->pluck('notification_id')->unique()->toArray();
                foreach ($notificationIds as $notificationId) {
                    $count = $unreadRecipients->where('notification_id', $notificationId)->count();
                    DB::table('admin_notifications')
                        ->where('id', $notificationId)
                        ->increment('read_count', $count);
                }

                \Log::info('All admin notifications marked as read', [
                    'user_bigid' => $bigid,
                    'count' => $adminCount,
                ]);
            }

            // Mark all push notifications as read
            $pushCount = UserNotification::where('user_bigid', $bigid)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'status' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => $adminCount + $pushCount,
                    'admin_marked' => $adminCount,
                    'push_marked' => $pushCount,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Mark All Read Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to mark notifications as read'
            ], 500);
        }
    }

    /**
     * Acknowledge notification (admin notifications only)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function acknowledge(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Parse ID if it has prefix
            if (str_starts_with($id, 'admin_')) {
                $id = (int) str_replace('admin_', '', $id);
            }

            // Find the recipient record - $id could be recipient_id or notification_id
            $recipient = DB::table('notification_recipients as nr')
                ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
                ->where('nr.id', $id)
                ->where('nr.user_bigid', $bigid)
                ->where('n.status', 'sent')
                ->where('n.requires_acknowledgment', true)
                ->select('nr.*', 'n.id as admin_notification_id')
                ->first();

            // If not found by recipient_id, try by notification_id
            if (!$recipient) {
                $recipient = DB::table('notification_recipients as nr')
                    ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
                    ->where('nr.notification_id', $id)
                    ->where('nr.user_bigid', $bigid)
                    ->where('n.status', 'sent')
                    ->where('n.requires_acknowledgment', true)
                    ->select('nr.*', 'n.id as admin_notification_id')
                    ->first();
            }

            if (!$recipient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Notification not found or does not require acknowledgement'
                ], 404);
            }

            // Track what we need to increment
            $shouldIncrementRead = !$recipient->is_read;
            $shouldIncrementAck = !$recipient->is_acknowledged;

            DB::table('notification_recipients')
                ->where('id', $recipient->id)
                ->update([
                    'is_read' => true,
                    'read_at' => $recipient->read_at ?? now(),
                    'is_acknowledged' => true,
                    'acknowledged_at' => now(),
                    'updated_at' => now(),
                ]);

            // Increment counts on admin_notifications table
            if ($shouldIncrementRead) {
                DB::table('admin_notifications')
                    ->where('id', $recipient->admin_notification_id)
                    ->increment('read_count');
            }
            
            if ($shouldIncrementAck) {
                DB::table('admin_notifications')
                    ->where('id', $recipient->admin_notification_id)
                    ->increment('acknowledged_count');
            }

            \Log::info('Notification acknowledged', [
                'recipient_id' => $recipient->id,
                'notification_id' => $recipient->admin_notification_id,
                'user_bigid' => $bigid,
                'read_incremented' => $shouldIncrementRead,
                'ack_incremented' => $shouldIncrementAck,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Notification acknowledged successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Acknowledge Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to acknowledge notification'
            ], 500);
        }
    }

    /**
     * Register FCM token for push notifications
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:500',
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|in:ios,android',
        ]);

        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $userId = $user->id;
            $deviceId = $request->input('device_id', 'default');

            UserFcmToken::updateOrCreate(
                [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                ],
                [
                    'user_bigid' => $bigid,
                    'fcm_token' => $request->input('fcm_token'),
                    'device_name' => $request->input('device_name'),
                    'device_type' => $request->input('device_type'),
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );

            \Log::info('FCM token registered via mobile API', [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'FCM token registered successfully',
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile FCM Token Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to register FCM token'
            ], 500);
        }
    }

    /**
     * Unregister FCM token (on logout)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unregisterFcmToken(Request $request)
    {
        try {
            $user = $request->user();
            $userId = $user->id;
            $deviceId = $request->input('device_id', 'default');

            UserFcmToken::where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->update(['is_active' => false]);

            \Log::info('FCM token unregistered', [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'FCM token unregistered successfully',
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile FCM Unregister Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to unregister FCM token'
            ], 500);
        }
    }

    /**
     * Delete a push notification
     * 
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Parse the ID
            if (str_starts_with($id, 'push_')) {
                $notificationId = (int) str_replace('push_', '', $id);
            } else {
                $notificationId = (int) $id;
            }

            $notification = UserNotification::where('id', $notificationId)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$notification) {
                return response()->json([
                    'status' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'status' => true,
                'message' => 'Notification deleted successfully',
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Delete Notification Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }

    /**
     * Get single notification by ID
     * 
     * @param Request $request
     * @param string $id Format: 'admin_{id}' or 'push_{id}' or just numeric id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $userId = $user->id;

            // Parse the ID to determine type
            if (str_starts_with($id, 'push_')) {
                $notificationId = (int) str_replace('push_', '', $id);
                return $this->getPushNotification($notificationId, $bigid);
            } elseif (str_starts_with($id, 'admin_')) {
                $recipientId = (int) str_replace('admin_', '', $id);
                return $this->getAdminNotification($recipientId, $bigid);
            } else {
                // Try admin first (by recipient_id), then by notification_id, then push
                $numericId = (int) $id;
                
                // Try as recipient_id
                $adminResult = $this->getAdminNotification($numericId, $bigid);
                if ($adminResult->getStatusCode() === 200) {
                    return $adminResult;
                }
                
                // Try as notification_id
                $adminResult = $this->getAdminNotificationByNotificationId($numericId, $bigid);
                if ($adminResult->getStatusCode() === 200) {
                    return $adminResult;
                }
                
                // Try as push notification
                return $this->getPushNotification($numericId, $bigid);
            }

        } catch (\Throwable $ex) {
            \Log::error('Mobile Get Notification Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to get notification'
            ], 500);
        }
    }

    /**
     * Get single admin notification by recipient ID
     */
    private function getAdminNotification($recipientId, $bigid)
    {
        $notification = DB::table('notification_recipients as nr')
            ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
            ->where('nr.id', $recipientId)
            ->where('nr.user_bigid', $bigid)
            ->where('n.status', 'sent')
            ->select([
                'nr.id as recipient_id',
                'n.id',
                'n.title',
                'n.message',
                'n.type',
                'n.delivery_method',
                'n.requires_acknowledgment',
                'n.created_at',
                'n.sent_at',
                'nr.is_read',
                'nr.is_acknowledged',
                'nr.read_at',
                'nr.acknowledged_at',
                'nr.push_sent',
            ])
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification retrieved successfully',
            'data' => [
                'id' => 'admin_' . $notification->recipient_id,
                'notification_id' => $notification->id,
                'recipient_id' => $notification->recipient_id,
                'source' => 'admin',
                'title' => $notification->title,
                'message' => $notification->message,
                'message_preview' => substr(strip_tags($notification->message), 0, 100) . (strlen($notification->message) > 100 ? '...' : ''),
                'type' => $notification->type,
                'icon' => $this->getIconForType($notification->type),
                'priority' => 'normal',
                'requires_acknowledgement' => (bool) $notification->requires_acknowledgment,
                'is_read' => (bool) $notification->is_read,
                'is_acknowledged' => (bool) $notification->is_acknowledged,
                'read_at' => $notification->read_at ? Carbon::parse($notification->read_at)->toIso8601String() : null,
                'acknowledged_at' => $notification->acknowledged_at ? Carbon::parse($notification->acknowledged_at)->toIso8601String() : null,
                'created_at' => Carbon::parse($notification->created_at)->toIso8601String(),
                'time_ago' => Carbon::parse($notification->created_at)->diffForHumans(),
                'push_sent' => (bool) $notification->push_sent,
                'data' => [
                    'notification_id' => (string) $notification->id,
                    'recipient_id' => (string) $notification->recipient_id,
                    'type' => $notification->type,
                    'action' => 'view_notification',
                    'screen' => 'NotificationDetail',
                ],
            ],
        ], 200);
    }

    /**
     * Get single admin notification by notification_id (admin_notifications.id)
     */
    private function getAdminNotificationByNotificationId($notificationId, $bigid)
    {
        $notification = DB::table('notification_recipients as nr')
            ->join('admin_notifications as n', 'n.id', '=', 'nr.notification_id')
            ->where('nr.notification_id', $notificationId)
            ->where('nr.user_bigid', $bigid)
            ->where('n.status', 'sent')
            ->select([
                'nr.id as recipient_id',
                'n.id',
                'n.title',
                'n.message',
                'n.type',
                'n.delivery_method',
                'n.requires_acknowledgment',
                'n.created_at',
                'n.sent_at',
                'nr.is_read',
                'nr.is_acknowledged',
                'nr.read_at',
                'nr.acknowledged_at',
                'nr.push_sent',
            ])
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification retrieved successfully',
            'data' => [
                'id' => 'admin_' . $notification->recipient_id,
                'notification_id' => $notification->id,
                'recipient_id' => $notification->recipient_id,
                'source' => 'admin',
                'title' => $notification->title,
                'message' => $notification->message,
                'message_preview' => substr(strip_tags($notification->message), 0, 100) . (strlen($notification->message) > 100 ? '...' : ''),
                'type' => $notification->type,
                'icon' => $this->getIconForType($notification->type),
                'priority' => 'normal',
                'requires_acknowledgement' => (bool) $notification->requires_acknowledgment,
                'is_read' => (bool) $notification->is_read,
                'is_acknowledged' => (bool) $notification->is_acknowledged,
                'read_at' => $notification->read_at ? Carbon::parse($notification->read_at)->toIso8601String() : null,
                'acknowledged_at' => $notification->acknowledged_at ? Carbon::parse($notification->acknowledged_at)->toIso8601String() : null,
                'created_at' => Carbon::parse($notification->created_at)->toIso8601String(),
                'time_ago' => Carbon::parse($notification->created_at)->diffForHumans(),
                'push_sent' => (bool) $notification->push_sent,
                'data' => [
                    'notification_id' => (string) $notification->id,
                    'recipient_id' => (string) $notification->recipient_id,
                    'type' => $notification->type,
                    'action' => 'view_notification',
                    'screen' => 'NotificationDetail',
                ],
            ],
        ], 200);
    }

    /**
     * Get single push notification
     */
    private function getPushNotification($id, $bigid)
    {
        $notification = UserNotification::where('id', $id)
            ->where('user_bigid', $bigid)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification retrieved successfully',
            'data' => [
                'id' => 'push_' . $notification->id,
                'notification_id' => $notification->id,
                'source' => 'push',
                'title' => $notification->title,
                'message' => $notification->message,
                'message_preview' => substr(strip_tags($notification->message), 0, 100) . '...',
                'type' => $notification->type,
                'icon' => $notification->icon ?? $this->getIconForType($notification->type),
                'priority' => 'normal',
                'requires_acknowledgement' => false,
                'is_read' => $notification->is_read,
                'is_acknowledged' => true,
                'read_at' => $notification->read_at ? $notification->read_at->toIso8601String() : null,
                'acknowledged_at' => null,
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $notification->created_at->diffForHumans(),
                'data' => $notification->data,
            ],
        ], 200);
    }

    /**
     * Test FCM connection (for debugging)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testFcm(Request $request)
    {
        try {
            $pushService = new \App\Services\PushNotificationService();
            $result = $pushService->testConnection();

            return response()->json([
                'status' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 500);

        } catch (\Throwable $ex) {
            \Log::error('FCM Test Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'FCM test failed: ' . $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Send test notification to current user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendTestNotification(Request $request)
    {
        try {
            $user = $request->user();
            
            $pushService = new \App\Services\PushNotificationService();
            $result = $pushService->sendToUser(
                $user->id,
                'Test Notification',
                'This is a test notification from SMS Expert.',
                'system',
                ['test' => 'true']
            );

            return response()->json([
                'status' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Send Test Notification Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to send test notification: ' . $ex->getMessage()
            ], 500);
        }
    }
}
