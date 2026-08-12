<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Clean up old API error logs
 */
class CleanApiErrorLogs extends Command
{
    protected $signature = 'api:clean-error-logs 
                            {--days= : Number of days to keep logs (default: from config)}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Clean up old API error logs from database';

    public function handle()
    {
        $days = $this->option('days') ?? config('api_monitor.log_retention_days', 30);
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = Carbon::now()->subDays($days);
        
        $query = DB::table('api_error_logs')
            ->where('created_at', '<', $cutoffDate);
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No old error logs to clean up.');
            return 0;
        }
        
        if ($dryRun) {
            $this->info("[DRY RUN] Would delete {$count} error logs older than {$days} days.");
            return 0;
        }
        
        if (!$this->confirm("Delete {$count} error logs older than {$days} days?")) {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $deleted = $query->delete();
        
        $this->info("Successfully deleted {$deleted} old error logs.");
        
        return 0;
    }
}
