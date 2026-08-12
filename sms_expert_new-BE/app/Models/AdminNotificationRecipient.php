<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotificationRecipient extends Model
{
    use HasFactory;

    protected $table = 'admin_notification_recipients';

    protected $fillable = [
        'notification_id',
        'user_id',
        'user_bigid',
        'is_read',
        'is_acknowledged',
        'read_at',
        'acknowledged_at',
        'email_sent',
        'email_sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_acknowledged' => 'boolean',
        'email_sent' => 'boolean',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'email_sent_at' => 'datetime',
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
     * Mark as read
     */
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
        return $this;
    }

    /**
     * Mark as acknowledged
     */
    public function markAsAcknowledged()
    {
        $this->update([
            'is_read' => true,
            'read_at' => $this->read_at ?? now(),
            'is_acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
        return $this;
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for unacknowledged notifications that require acknowledgement
     */
    public function scopeRequiringAcknowledgement($query)
    {
        return $query->where('is_acknowledged', false)
            ->whereHas('notification', function ($q) {
                $q->where('requires_acknowledgement', true);
            });
    }
}
