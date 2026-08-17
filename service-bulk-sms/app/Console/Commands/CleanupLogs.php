<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deletes whole date-bucketed log folders (storage/logs/YYYY-MM-DD) older than
 * --days. Mirrors sms_expert's log retention. Scheduled daily; also runnable by hand.
 *
 *   php artisan logs:cleanup --days=14
 *
 * @author Anand Karthik
 */
class CleanupLogs extends Command
{
    protected $signature = 'logs:cleanup {--days=14 : Delete date folders older than this many days}';
    protected $description = 'Delete date-bucketed log folders older than N days';

    public function handle()
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = strtotime("-{$days} days");
        $base = storage_path('logs');
        $removed = 0;

        foreach (glob($base . '/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            // Only touch YYYY-MM-DD folders (never the loose laravel.log etc).
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $name)) {
                continue;
            }
            if (strtotime($name) < $cutoff) {
                $this->deleteDirectory($dir);
                $removed++;
                $this->line("  removed {$name}");
            }
        }

        $this->info("logs:cleanup done — removed {$removed} folder(s) older than {$days} days.");
        return 0;
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob($dir . '/*') as $item) {
            is_dir($item) ? $this->deleteDirectory($item) : @unlink($item);
        }
        @rmdir($dir);
    }
}
