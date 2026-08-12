<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications';

    protected $fillable = [
        'title',
        'message',
        'type',
        'target_type',
        'delivery_method',
        'requires_acknowledgment',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'read_count',
        'acknowledged_count',
        'created_by',
    ];

    protected $casts = [
        'requires_acknowledgment' => 'boolean',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'read_count' => 'integer',
        'acknowledged_count' => 'integer',
    ];

    /**
     * Get the admin user who created this notification
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the notification recipients
     */
    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for scheduled notifications
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope for draft notifications
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Get notifications that are due to be sent
     */
    public function scopeDueForSending($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Get type badge color
     */
    public function getTypeBadgeColorAttribute()
    {
        return match($this->type) {
            'info' => 'primary',
            'warning' => 'warning',
            'success' => 'success',
            'danger' => 'danger',
            'announcement' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'draft' => 'secondary',
            'scheduled' => 'info',
            'sending' => 'warning',
            'sent' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get type icon
     */
    public function getTypeIconAttribute()
    {
        return match($this->type) {
            'info' => 'info',
            'warning' => 'warning',
            'success' => 'check_circle',
            'danger' => 'error',
            'announcement' => 'campaign',
            default => 'notifications',
        };
    }

    /**
     * Get actual read count from recipients table
     * This ensures we always have the correct count
     */
    public function getActualReadCountAttribute()
    {
        return $this->recipients()->where('is_read', true)->count();
    }

    /**
     * Get actual acknowledged count from recipients table
     * This ensures we always have the correct count
     */
    public function getActualAcknowledgedCountAttribute()
    {
        return $this->recipients()->where('is_acknowledged', true)->count();
    }

    /**
     * Calculate read percentage
     * Uses actual counts from recipients table for accuracy
     */
    public function getReadPercentageAttribute()
    {
        $totalRecipients = $this->total_recipients;
        
        // If total_recipients is 0, try to get from actual recipients count
        if ($totalRecipients === 0) {
            $totalRecipients = $this->recipients()->count();
        }
        
        if ($totalRecipients === 0) return 0;
        
        // Get actual read count from recipients table
        $readCount = $this->recipients()->where('is_read', true)->count();
        
        return round(($readCount / $totalRecipients) * 100, 1);
    }

    /**
     * Calculate acknowledged percentage
     * Uses actual counts from recipients table for accuracy
     */
    public function getAcknowledgedPercentageAttribute()
    {
        $totalRecipients = $this->total_recipients;
        
        // If total_recipients is 0, try to get from actual recipients count
        if ($totalRecipients === 0) {
            $totalRecipients = $this->recipients()->count();
        }
        
        if ($totalRecipients === 0) return 0;
        
        // Get actual acknowledged count from recipients table
        $acknowledgedCount = $this->recipients()->where('is_acknowledged', true)->count();
        
        return round(($acknowledgedCount / $totalRecipients) * 100, 1);
    }

    /**
     * Increment read count
     */
    public function incrementReadCount()
    {
        $this->increment('read_count');
    }

    /**
     * Increment acknowledged count
     */
    public function incrementAcknowledgedCount()
    {
        $this->increment('acknowledged_count');
    }

    /**
     * Sync counts from recipients table
     * Call this to ensure counts are accurate
     */
    public function syncCounts()
    {
        $this->update([
            'total_recipients' => $this->recipients()->count(),
            'read_count' => $this->recipients()->where('is_read', true)->count(),
            'acknowledged_count' => $this->recipients()->where('is_acknowledged', true)->count(),
        ]);
    }
}
