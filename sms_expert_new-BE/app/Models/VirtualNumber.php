<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'msisdn',
        'operator',
        'country',
        'type',
        'features',
        'mo_http_url',
        'messages_webhooks',
        'voice_webhooks',
        'limitations',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'features' => 'array',
        'messages_webhooks' => 'array',
        'voice_webhooks' => 'array',
        'limitations' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Scope to get active numbers only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search by number, country or type
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('msisdn', 'like', "%{$search}%")
              ->orWhere('country', 'like', "%{$search}%")
              ->orWhere('type', 'like', "%{$search}%");
        });
    }
}
