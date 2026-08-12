<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMargin extends Model
{
    use HasFactory;

    protected $table = 'user_margin';

    protected $fillable = [
        'user_id',
        'margin_percentage',
        'is_active',
    ];

    protected $casts = [
        'margin_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the margin.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate user rate for a given base cost (returns 4 decimal places)
     *
     * @param float $baseCost
     * @return float
     */
    public function calculateUserRate($baseCost)
    {
        if (!$this->is_active) {
            return round($baseCost, 4);
        }

        $marginAmount = $baseCost * ($this->margin_percentage / 100);
        return round($baseCost + $marginAmount, 4);
    }

    /**
     * Get or create margin for a user
     *
     * @param int $userId
     * @return UserMargin
     */
    public static function getOrCreateForUser($userId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            ['margin_percentage' => 0, 'is_active' => 1]
        );
    }
}
