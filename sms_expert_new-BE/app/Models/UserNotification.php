<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;

    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'user_bigid',
        'title',
        'message',
        'type',
        'icon',
        'data',
        'is_read',
        'read_at',
        'push_sent',
        'push_sent_at',
        'push_status',
        'push_error',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'push_sent' => 'boolean',
        'read_at' => 'datetime',
        'push_sent_at' => 'datetime',
    ];

    /**
     * Notification types
     */
    const TYPE_WALLET_LOW = 'wallet_low';
    const TYPE_WALLET_INSUFFICIENT = 'wallet_insufficient';
    const TYPE_THROUGHPUT_LIMIT = 'throughput_limit';
    const TYPE_SYSTEM = 'system';
    const TYPE_PROMO = 'promo';
    const TYPE_GENERAL = 'general';
    const TYPE_CAMPAIGN = 'campaign';
    const TYPE_DELIVERY = 'delivery';

    /**
     * Get the user that owns the notification
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
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
     * Scope for specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for user by bigid
     */
    public function scopeForUserBigId($query, $bigid)
    {
        return $query->where('user_bigid', $bigid);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Update push status
     */
    public function updatePushStatus($status, $error = null)
    {
        $this->update([
            'push_sent' => $status === 'sent',
            'push_sent_at' => $status === 'sent' ? now() : null,
            'push_status' => $status,
            'push_error' => $error,
        ]);
    }

    /**
     * Get icon for notification type
     */
    public static function getIconForType($type)
    {
        $icons = [
            self::TYPE_WALLET_LOW => 'wallet',
            self::TYPE_WALLET_INSUFFICIENT => 'wallet-warning',
            self::TYPE_THROUGHPUT_LIMIT => 'speedometer',
            self::TYPE_SYSTEM => 'information-circle',
            self::TYPE_PROMO => 'gift',
            self::TYPE_GENERAL => 'notifications',
            self::TYPE_CAMPAIGN => 'paper-plane',
            self::TYPE_DELIVERY => 'checkmark-done',
        ];

        return $icons[$type] ?? 'notifications';
    }
}
