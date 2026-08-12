<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ServerSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_type',
        'host',
        'port',
        'username',
        'password',
        'campaign_file_path',
        'connection_type',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'port' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Set encrypted password
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get decrypted password
     */
    public function getDecryptedPassword(): ?string
    {
        if (!empty($this->attributes['password'])) {
            try {
                return Crypt::decryptString($this->attributes['password']);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get old server settings
     */
    public static function getOldServer(): ?self
    {
        return self::where('server_type', 'old_server')->first();
    }

    /**
     * Get new server settings
     */
    public static function getNewServer(): ?self
    {
        return self::where('server_type', 'new_server')->first();
    }

    /**
     * Update test status
     */
    public function updateTestStatus(bool $success, string $message): void
    {
        $this->update([
            'last_tested_at' => now(),
            'last_test_status' => $success ? 'success' : 'failed',
            'last_test_message' => $message,
        ]);
    }
}
