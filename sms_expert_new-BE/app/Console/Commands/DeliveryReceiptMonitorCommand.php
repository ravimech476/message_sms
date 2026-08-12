<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeliveryReceiptMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery-receipt:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor delivery receipt push daemons health';

    /**
     * Maximum allowed time (in seconds) since last touch
     */
    private $maxStaleTime = 300; // 5 minutes

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking delivery receipt daemon health...');
        
        $touchFileDir = storage_path('app/touchfiles');
        
        if (!is_dir($touchFileDir)) {
            $this->warn('No touchfile directory found. No daemons appear to be running.');
            return 0;
        }

        $touchFiles = glob($touchFileDir . '/dlrPushDaemon-*.touch');
        
        if (empty($touchFiles)) {
            $this->warn('No daemon touchfiles found.');
            return 0;
        }

        $healthReport = [];
        
        foreach ($touchFiles as $touchFile) {
            $daemonName = $this->extractDaemonName($touchFile);
            $status = $this->checkDaemonHealth($touchFile, $daemonName);
            $healthReport[] = $status;
            
            $this->displayStatus($status);
        }

        // Log summary
        $this->logHealthSummary($healthReport);

        return 0;
    }

    /**
     * Extract daemon name from touchfile path
     */
    private function extractDaemonName($touchFile): string
    {
        preg_match('/dlrPushDaemon-(.+)\.touch$/', basename($touchFile), $matches);
        return $matches[1] ?? 'unknown';
    }

    /**
     * Check daemon health from touchfile
     */
    private function checkDaemonHealth($touchFile, $daemonName): array
    {
        if (!file_exists($touchFile)) {
            return [
                'daemon' => $daemonName,
                'status' => 'missing',
                'message' => 'Touchfile does not exist'
            ];
        }

        $lines = file($touchFile);
        
        if (count($lines) < 2) {
            return [
                'daemon' => $daemonName,
                'status' => 'invalid',
                'message' => 'Invalid touchfile format'
            ];
        }

        $pid = trim($lines[0]);
        $lastTouch = (int)trim($lines[1]);
        $timeSinceTouch = time() - $lastTouch;
        
        // Check if process is still running
        $processRunning = $this->isProcessRunning($pid);
        
        if (!$processRunning) {
            return [
                'daemon' => $daemonName,
                'pid' => $pid,
                'status' => 'dead',
                'message' => 'Process is not running',
                'last_touch' => Carbon::createFromTimestamp($lastTouch)->toDateTimeString()
            ];
        }

        if ($timeSinceTouch > $this->maxStaleTime) {
            return [
                'daemon' => $daemonName,
                'pid' => $pid,
                'status' => 'stale',
                'message' => "No activity for {$timeSinceTouch} seconds",
                'last_touch' => Carbon::createFromTimestamp($lastTouch)->toDateTimeString()
            ];
        }

        return [
            'daemon' => $daemonName,
            'pid' => $pid,
            'status' => 'healthy',
            'message' => 'Running normally',
            'last_touch' => Carbon::createFromTimestamp($lastTouch)->toDateTimeString(),
            'seconds_since_touch' => $timeSinceTouch
        ];
    }

    /**
     * Check if process is running
     */
    private function isProcessRunning($pid): bool
    {
        if (empty($pid) || !is_numeric($pid)) {
            return false;
        }

        // On Unix-like systems
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
            return count($output) > 1;
        } else {
            return file_exists("/proc/{$pid}");
        }
    }

    /**
     * Display status to console
     */
    private function displayStatus($status)
    {
        $icon = match($status['status']) {
            'healthy' => '✓',
            'stale' => '⚠',
            'dead' => '✗',
            'missing' => '?',
            default => '!'
        };

        $color = match($status['status']) {
            'healthy' => 'info',
            'stale' => 'warn',
            'dead', 'missing' => 'error',
            default => 'comment'
        };

        $message = "[{$icon}] Daemon: {$status['daemon']} - {$status['message']}";
        
        if (isset($status['pid'])) {
            $message .= " (PID: {$status['pid']})";
        }
        
        if (isset($status['last_touch'])) {
            $message .= " - Last activity: {$status['last_touch']}";
        }

        $this->line($message, $color);
    }

    /**
     * Log health summary
     */
    private function logHealthSummary($healthReport)
    {
        $summary = [
            'healthy' => 0,
            'stale' => 0,
            'dead' => 0,
            'missing' => 0,
            'invalid' => 0
        ];

        foreach ($healthReport as $status) {
            $summary[$status['status']]++;
        }

        $this->info("\nHealth Summary:");
        $this->table(
            ['Status', 'Count'],
            array_map(fn($k, $v) => [ucfirst($k), $v], array_keys($summary), $summary)
        );

        // Log to file if there are issues
        if ($summary['stale'] > 0 || $summary['dead'] > 0 || $summary['missing'] > 0) {
            Log::channel('delivery_receipt')->warning('Daemon health check found issues', $summary);
        }
    }
}
