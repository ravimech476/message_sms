<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportJob extends Model
{
    protected $table = 'report_jobs';

    protected $fillable = [
        'admin_bigid', 'admin_name', 'email', 'report_type', 'report_name',
        'date_from', 'date_to', 'customer_ids', 'search', 'status',
        'file_name', 'file_path', 'row_count', 'error_message',
        'requested_at', 'completed_at',
    ];

    protected $casts = [
        'date_from'    => 'date',
        'date_to'      => 'date',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_FAILED     = 'failed';

    /** Human label for each report type. */
    public static function typeLabel(string $type): string
    {
        return [
            'postpay'        => 'Post Pay Report',
            'daily_sms'      => 'Daily SMS Report',
            'money_transfer' => 'Money Transferred Report',
            'monthly_sales'  => 'Sales Report',
        ][$type] ?? $type;
    }

    /** customer_ids stored as a JSON array. */
    public function getCustomerIdsArrayAttribute(): array
    {
        $decoded = json_decode($this->customer_ids ?? '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->file_path;
    }
}
