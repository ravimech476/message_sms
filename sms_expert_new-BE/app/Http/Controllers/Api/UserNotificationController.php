<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserNotification;
use App\Models\UserFcmToken;

class UserNotificationController extends Controller
{
    /**
     * Get user notifications list
     */
    public function index(Request $request)
    {
        try {
            $userBigId = $request->attributes->get('user_bigid');
            $userId = $request->attributes->get('user_id');
            
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            $unreadOnly = $request->boolean('unread_only', false);
            $type = $request->input('type');

            $query = UserNotification::where('user_bigid', $userBigId)
                ->orderBy('created_at', 'desc');

            if ($unreadOnly) {
                $query->where('is_read', false);
            }

            if ($type) {
                $query->where('type', $type);
            }

            $notifications = $query->paginate($perPage, ['*'], 'page', $page);

            // Get unread count
            $unreadCount = UserNotification::where('user_bigid', $userBigId)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'total_pages' => $notifications->lastPage(),
                    'has_more' => $notifications->hasMorePages(),
                ],
                'unread_count' => $unreadCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching notifications', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
            ], 500);
        }
    }

    /**
     * Get single notification
     */
    public function show(Request $request, $id)
    {
        try {
            $userBigId = $request->attributes->get('user_bigid');

            $notification = UserNotification::where('id', $id)
                ->where('user_bigid', $userBigId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $notification,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching notification', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notification',
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $userBigId = $request->attributes->get('user_bigid');

            $notification = UserNotification::where('id', $id)
                ->where('user_bigid', $userBigId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $userBigId = $request->attributes->get('user_bigid');

            $updated = UserNotification::where('user_bigid', $userBigId)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Marked {$updated} notifications as read",
                'count' => $updated,
            ]);

        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userBigId = $request->attributes->get('user_bigid');

            $notification = UserNotification::where('id', $id)
                ->where('user_bigid', $userBigId)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted',
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting notification', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request)
    {
        try {
            $userBigId = $request->attributes->get('user_bigid');

            $count = UserNotification::where('user_bigid', $userBigId)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'unread_count' => $count,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting unread count', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
            ], 500);
        }
    }

    /**
     * Register/Update FCM token
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
            $userBigId = $request->attributes->get('user_bigid');
            $userId = $request->attributes->get('user_id');

            $deviceId = $request->input('device_id', 'default');

            // Update or create FCM token
            $token = UserFcmToken::updateOrCreate(
                [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                ],
                [
                    'user_bigid' => $userBigId,
                    'fcm_token' => $request->input('fcm_token'),
                    'device_name' => $request->input('device_name'),
                    'device_type' => $request->input('device_type'),
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );

            Log::info('FCM token registered', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'token_id' => $token->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error registering FCM token', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
            ], 500);
        }
    }

    /**
     * Unregister FCM token (logout)
     */
    public function unregisterFcmToken(Request $request)
    {
        $request->validate([
            'device_id' => 'nullable|string|max:255',
        ]);

        try {
            $userId = $request->attributes->get('user_id');
            $deviceId = $request->input('device_id', 'default');

            $deleted = UserFcmToken::where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->update(['is_active' => false]);

            Log::info('FCM token unregistered', [
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token unregistered successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error unregistering FCM token', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unregister FCM token',
            ], 500);
        }
    }
}
