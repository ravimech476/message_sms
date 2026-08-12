<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFcmToken extends Model
{
    use HasFactory;

    protected $table = 'user_fcm_tokens';

    protected $fillable = [
        'user_id',
        'device_id',
        'fcm_token',
        'device_name',
        'device_type',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Scope for active tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Get all active tokens for a user
     */
    public static function getActiveTokensForUser($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();
    }

    /**
     * Get all active tokens for a user by bigid
     */
    public static function getActiveTokensForUserBigId($userId)
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();
    }

    /**
     * Update last used timestamp
     */
    public function touchLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate token
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activate token
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }
}
