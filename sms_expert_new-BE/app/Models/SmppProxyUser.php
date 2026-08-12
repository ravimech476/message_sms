<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SMPP Proxy User Model - OLD SYSTEM Compatible
 *
 * Manages SMPP proxy user configurations.
 * Based on Craig Rutherford's SMPP front end authentication.
 */
class SmppProxyUser extends Model
{
    use HasFactory;

    protected $table = 'smpp_proxy_users';

    protected $fillable = [
        'proxy_username',
        'proxy_password',
        'proxy_name',
        'allowed_ips',
        'notify_email',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get proxy user by username
     */
    public static function getByUsername(string $username): ?self
    {
        return static::where('proxy_username', $username)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Validate proxy user credentials
     */
    public static function validateCredentials(string $username, string $password): ?self
    {
        return static::where('proxy_username', $username)
            ->where('proxy_password', $password)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if IP address is allowed for this proxy user
     */
    public function isIpAllowed(string $ipAddress): bool
    {
        // If no IPs specified, allow all
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ipAddress, $this->allowed_ips);
    }
}
