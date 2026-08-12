<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Logging\CronLogService;
use App\Services\Logging\ApiLogService;
use App\Traits\LogsCronActivity;

class CleanOldLogs extends Command
{
    use LogsCronActivity;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clean
                            {--days=30 : Number of days to keep logs}
                            {--type=all : Type of logs to clean (cron, api, or all)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old cron and API logs older than specified days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->initCronLog('CleanOldLogs');

        $days = (int) $this->option('days');
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->cronStart(['days' => $days, 'type' => $type, 'dry_run' => $dryRun]);

        $this->info("Cleaning logs older than {$days} days...");
        $this->cronInfo("Starting log cleanup", ['days' => $days, 'type' => $type, 'dry_run' => $dryRun]);

        $totalDeleted = 0;

        if ($type === 'all' || $type === 'cron') {
            if ($dryRun) {
                $this->info('[DRY RUN] Would clean cron logs...');
                $this->cronInfo('[DRY RUN] Would clean cron logs');
            } else {
                $cronDeleted = CronLogService::cleanOldLogs($days);
                $this->info("Cleaned {$cronDeleted} cron log folder(s)");
                $this->cronInfo("Cleaned cron logs", ['deleted_folders' => $cronDeleted]);
                $totalDeleted += $cronDeleted;
            }
        }

        if ($type === 'all' || $type === 'api') {
            if ($dryRun) {
                $this->info('[DRY RUN] Would clean API logs...');
                $this->cronInfo('[DRY RUN] Would clean API logs');
            } else {
                $apiDeleted = ApiLogService::cleanOldLogs($days);
                $this->info("Cleaned {$apiDeleted} API log folder(s)");
                $this->cronInfo("Cleaned API logs", ['deleted_folders' => $apiDeleted]);
                $totalDeleted += $apiDeleted;
            }
        }

        $this->info("Total: {$totalDeleted} folder(s) cleaned");
        $this->cronEnd(['total_deleted' => $totalDeleted]);

        return Command::SUCCESS;
    }
}
