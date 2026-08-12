<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRecipient extends Model
{
    use HasFactory;

    protected $table = 'notification_recipients';

    /**
     * Delivery methods that include web delivery
     */
    public const WEB_DELIVERY_METHODS = ['web', 'both', 'web_mobile', 'all'];

    /**
     * Delivery methods that include mobile delivery
     */
    public const MOBILE_DELIVERY_METHODS = ['mobile', 'web_mobile', 'all'];

    protected $fillable = [
        'notification_id',
        'user_id',
        'user_bigid',
        'is_read',
        'is_acknowledged',
        'email_sent',
        'web_delivered',
        'push_sent',
        'read_at',
        'acknowledged_at',
        'email_sent_at',
        'push_sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_acknowledged' => 'boolean',
        'email_sent' => 'boolean',
        'web_delivered' => 'boolean',
        'push_sent' => 'boolean',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'push_sent_at' => 'datetime',
    ];

    /**
     * Get the notification
     */
    public function notification()
    {
        return $this->belongsTo(AdminNotification::class, 'notification_id');
    }

    /**
     * Get the user/customer
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for web-delivered notifications only
     */
    public function scopeWebDelivered($query)
    {
        return $query->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            });
    }

    /**
     * Scope for requiring acknowledgment
     */
    public function scopeRequiresAcknowledgment($query)
    {
        return $query->whereHas('notification', function ($q) {
            $q->where('requires_acknowledgment', true);
        })->where('is_acknowledged', false);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
            
            // Update parent notification read count
            $this->notification->incrementReadCount();
        }
    }

    /**
     * Mark notification as acknowledged
     */
    public function markAsAcknowledged()
    {
        if (!$this->is_acknowledged) {
            $this->update([
                'is_acknowledged' => true,
                'acknowledged_at' => now(),
            ]);
            
            // Also mark as read if not already
            if (!$this->is_read) {
                $this->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
                $this->notification->incrementReadCount();
            }
            
            // Update parent notification acknowledged count
            $this->notification->incrementAcknowledgedCount();
        }
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered()
    {
        $this->update(['web_delivered' => true]);
    }

    /**
     * Get unread notifications for a user (WEB ONLY)
     */
    public static function getUnreadForUser($userId)
    {
        return self::with('notification')
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get unread notifications for a user by bigid (WEB ONLY)
     */
    public static function getUnreadForUserByBigid($userBigid)
    {
        return self::with('notification')
            ->where('user_bigid', $userBigid)
            ->where('is_read', false)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get notifications requiring acknowledgment for a user (WEB ONLY)
     */
    public static function getPendingAcknowledgmentForUser($userId)
    {
        return self::with('notification')
            ->where('user_id', $userId)
            ->where('is_acknowledged', false)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->where('requires_acknowledgment', true)
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get notifications requiring acknowledgment for a user by bigid (WEB ONLY)
     */
    public static function getPendingAcknowledgmentForUserByBigid($userBigid)
    {
        return self::with('notification')
            ->where('user_bigid', $userBigid)
            ->where('is_acknowledged', false)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->where('requires_acknowledgment', true)
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all notifications for user (paginated) (WEB ONLY)
     */
    public static function getAllForUser($userId, $perPage = 15)
    {
        return self::with('notification')
            ->where('user_id', $userId)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get unread count for user (WEB ONLY)
     */
    public static function getUnreadCountForUser($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_read', false)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->count();
    }

    /**
     * Get unread count for user by bigid (WEB ONLY)
     */
    public static function getUnreadCountForUserByBigid($userBigid)
    {
        return self::where('user_bigid', $userBigid)
            ->where('is_read', false)
            ->where('web_delivered', true)
            ->whereHas('notification', function ($q) {
                $q->where('status', 'sent')
                  ->whereIn('delivery_method', self::WEB_DELIVERY_METHODS);
            })
            ->count();
    }
}
