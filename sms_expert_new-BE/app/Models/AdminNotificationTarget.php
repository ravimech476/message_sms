<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotificationTarget extends Model
{
    use HasFactory;

    protected $table = 'admin_notification_targets';

    protected $fillable = [
        'notification_id',
        'user_id',
        'user_bigid',
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
}
