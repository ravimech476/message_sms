<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignFileMigration extends Model
{
    use HasFactory;

    protected $fillable = [
        'migration_batch_id',
        'user_bigid',
        'direction',
        'filename',
        'source_path',
        'destination_path',
        'status',
        'status_message',
        'file_size',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'file_size' => 'integer',
    ];

    /**
     * Scope for pending migrations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed migrations
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed migrations
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for specific batch
     */
    public function scopeForBatch($query, string $batchId)
    {
        return $query->where('migration_batch_id', $batchId);
    }

    /**
     * Scope for direction
     */
    public function scopeDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    /**
     * Get related user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_bigid', 'bigid');
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(string $message = null, int $fileSize = null): void
    {
        $this->update([
            'status' => 'completed',
            'status_message' => $message,
            'file_size' => $fileSize,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as skipped
     */
    public function markAsSkipped(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'status_message' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'status_message' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Generate batch ID
     */
    public static function generateBatchId(): string
    {
        return 'batch_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Get batch statistics
     */
    public static function getBatchStats(string $batchId): array
    {
        $stats = self::forBatch($batchId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => array_sum($stats),
            'pending' => $stats['pending'] ?? 0,
            'processing' => $stats['processing'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'skipped' => $stats['skipped'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
        ];
    }
}
