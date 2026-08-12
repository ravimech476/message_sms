<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer Features Model - OLD SYSTEM Compatible
 *
 * Manages customer-specific feature flags and settings.
 * Replaces hardcoded conditions from OLD SYSTEM.
 */
class CustomerFeature extends Model
{
    use HasFactory;

    protected $table = 'customer_features';

    protected $fillable = [
        'user_bigid',
        'username',
        'master_username',
        'utf8_decode',
        'priority_queue',
        'priority_daemon_id',
        'priority_route',
        'route_override',
        'debug_mode',
        'test_mode',
        'route_fix_enabled',
        'route_fix_from',
        'route_fix_to',
        'route_fix_notify',
        'route_fix_notify_email',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'utf8_decode' => 'boolean',
        'priority_queue' => 'boolean',
        'priority_daemon_id' => 'integer',
        'route_override' => 'boolean',
        'debug_mode' => 'boolean',
        'test_mode' => 'boolean',
        'route_fix_enabled' => 'boolean',
        'route_fix_notify' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get feature by user bigid
     */
    public static function getByBigid(string $bigid): ?self
    {
        return static::where('user_bigid', $bigid)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get feature by username
     */
    public static function getByUsername(string $username): ?self
    {
        return static::where('username', $username)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get feature by master username (for sub-accounts)
     */
    public static function getByMasterUsername(string $masterUsername): ?self
    {
        return static::where('master_username', $masterUsername)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if customer has UTF-8 decode enabled
     */
    public static function hasUtf8Decode(string $bigid, ?string $masterUsername = null): bool
    {
        $feature = static::getByBigid($bigid);
        if ($feature && $feature->utf8_decode) {
            return true;
        }

        if ($masterUsername) {
            $masterFeature = static::getByMasterUsername($masterUsername);
            if ($masterFeature && $masterFeature->utf8_decode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if customer has priority queue enabled
     */
    public static function hasPriorityQueue(string $bigid, ?string $route = null): ?int
    {
        $feature = static::getByBigid($bigid);
        if ($feature && $feature->priority_queue) {
            if ($feature->priority_route === null || strtolower($route) === strtolower($feature->priority_route)) {
                return $feature->priority_daemon_id ?? 100;
            }
        }
        return null;
    }

    /**
     * Check if customer has route override enabled
     */
    public static function hasRouteOverride(string $bigid): bool
    {
        $feature = static::getByBigid($bigid);
        return $feature && $feature->route_override;
    }

    /**
     * Check if customer has debug mode enabled
     */
    public static function hasDebugMode(string $bigid): bool
    {
        $feature = static::getByBigid($bigid);
        return $feature && $feature->debug_mode;
    }

    /**
     * Check if customer is in test mode (skip actual SMS)
     */
    public static function isTestMode(string $bigid): bool
    {
        $feature = static::getByBigid($bigid);
        return $feature && $feature->test_mode;
    }

    /**
     * Check and apply route fix
     *
     * @return array ['fixed' => bool, 'new_route' => string|null, 'notify' => bool, 'email' => string|null]
     */
    public static function checkRouteFix(string $bigidOrUsername, string $route): array
    {
        $feature = static::getByBigid($bigidOrUsername);
        if (!$feature) {
            $feature = static::getByUsername($bigidOrUsername);
        }

        if ($feature && $feature->route_fix_enabled && $feature->route_fix_from === $route) {
            return [
                'fixed' => true,
                'new_route' => $feature->route_fix_to,
                'notify' => $feature->route_fix_notify,
                'email' => $feature->route_fix_notify_email,
            ];
        }

        return ['fixed' => false, 'new_route' => null, 'notify' => false, 'email' => null];
    }

    /**
     * Relationship with User model
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_bigid', 'bigid');
    }
}
