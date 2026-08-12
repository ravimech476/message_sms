<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $table = 'user_activity_logs';

    protected $fillable = [
        'user_type',
        'user_id',
        'user_ref',
        'action',
        'page_url',
        'page_name',
        'http_method',
        'description',
        'request_data',
        'response_data',
        'queries_executed',
        'query_count',
        'ip_address',
        'user_agent',
        'session_id',
        'response_status',
        'execution_time_ms',
        'error_message',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with the activity log
     */
    public function user()
    {
        if ($this->user_type === 'customer') {
            return $this->belongsTo(User::class, 'user_id', 'id');
        }
        // For admin, you might have a separate admin model or use the same User model
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Scope to filter by user type
     */
    public function scopeOfUserType($query, $type)
    {
        return $query->where('user_type', $type);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        return $query;
    }

    /**
     * Scope to filter by action
     */
    public function scopeAction($query, $action)
    {
        if ($action && $action !== 'all') {
            return $query->where('action', $action);
        }
        return $query;
    }

    /**
     * Scope to search
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('page_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('user_ref', 'like', "%{$search}%");
            });
        }
        return $query;
    }

    /**
     * Get formatted queries
     */
    public function getFormattedQueriesAttribute()
    {
        if (empty($this->queries_executed)) {
            return [];
        }

        // If it's already an array (JSON), return it
        if (is_array($this->queries_executed)) {
            return $this->queries_executed;
        }

        // If it's a JSON string, decode it
        if ($this->isJson($this->queries_executed)) {
            return json_decode($this->queries_executed, true);
        }

        // If it's a plain text, split by lines
        return array_filter(explode("\n", $this->queries_executed));
    }

    /**
     * Check if string is JSON
     */
    private function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
