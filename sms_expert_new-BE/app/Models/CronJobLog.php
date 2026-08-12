<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CronJobLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'command',
        'status',
        'started_at',
        'finished_at',
        'duration',
        'output',
        'error',
        'log_file'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Map cron commands to their log file names
     *
     * @return array
     */
    public static function getCronLogFileMapping(): array
    {
        return [
            'sms:process-scheduled' => 'scheduled-sms-processor.log',
            'emails:send-schedule' => 'emails-send-schedule.log',
            'sms:update-pricing' => 'sms-pricing-update.log',
            'wallet:send-reminders' => 'wallet-reminders.log',
            'delivery-receipt:push' => 'delivery-receipt-default.log',
            'virtualnumbers:sync' => 'virtualnumbers-sync.log',
            'nexmo:fetch-delivery-reports' => 'nexmo-delivery-reports.log',
            'nexmo:fetch-delivery-reports-daily' => 'nexmo-delivery-reports-daily.log',
            'db:tidy' => 'db-tidy.log',
        ];
    }

    /**
     * Get date-wise log path
     *
     * @param string $filename
     * @param string|null $date Optional date (defaults to today)
     * @return string
     */
    public static function getDateWiseLogPath(string $filename, ?string $date = null): string
    {
        $date = $date ?? date('Y-m-d');
        return storage_path("logs/{$date}/{$filename}");
    }

    /**
     * Get the latest log for a command
     */
    public static function getLatestForCommand($command)
    {
        return self::where('command', $command)
            ->orderBy('started_at', 'desc')
            ->first();
    }

    /**
     * Get duration in human readable format
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return '-';
        }

        if ($this->duration < 60) {
            return $this->duration . 's';
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return $minutes . 'm ' . $seconds . 's';
    }

    /**
     * Find log file in date-wise folders
     * 
     * @return string|null
     */
    public function findLogFile(): ?string
    {
        // First check if stored log_file path exists
        if (!empty($this->log_file) && file_exists($this->log_file)) {
            return $this->log_file;
        }

        // Try to find log file based on command and started_at date
        $mapping = self::getCronLogFileMapping();
        $logFileName = $mapping[$this->command] ?? null;
        
        if (!$logFileName) {
            return null;
        }

        // Get the date from started_at
        $date = $this->started_at ? $this->started_at->format('Y-m-d') : date('Y-m-d');
        
        // Try multiple possible paths using dynamic storage_path()
        $possiblePaths = [
            // Dated log path (HIGHEST PRIORITY)
            storage_path("logs/{$date}/{$logFileName}"),
            base_path("storage/logs/{$date}/{$logFileName}"),
            
            // Fallback to non-dated paths
            storage_path("logs/{$logFileName}"),
            base_path("storage/logs/{$logFileName}"),
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Check if log file exists
     */
    public function hasLogFile(): bool
    {
        return $this->findLogFile() !== null;
    }

    /**
     * Get log file content
     */
    public function getLogContent(): ?string
    {
        $logFile = $this->findLogFile();
        
        if ($logFile && file_exists($logFile)) {
            $fileSize = filesize($logFile);
            
            // For large files (> 1MB), only read last portion
            if ($fileSize > 1048576) {
                $file = new \SplFileObject($logFile, 'r');
                $file->seek(PHP_INT_MAX);
                $lastLine = $file->key();
                $startLine = max(0, $lastLine - 1000);
                
                $lines = [];
                $file->seek($startLine);
                while (!$file->eof()) {
                    $line = $file->fgets();
                    if ($line) {
                        $lines[] = $line;
                    }
                }
                return "... (showing last 1000 lines of " . round($fileSize / 1024, 2) . " KB file)\n\n" . implode('', $lines);
            }
            
            return file_get_contents($logFile);
        }
        
        return null;
    }

    /**
     * Get log file path (for display purposes)
     */
    public function getLogFilePath(): ?string
    {
        return $this->findLogFile();
    }

    /**
     * Check if the job is currently running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Clean up old logs (database and files)
     */
    public static function cleanup($days = 30)
    {
        $oldLogs = self::where('created_at', '<', now()->subDays($days))->get();
        
        // Delete log files
        foreach ($oldLogs as $log) {
            $logFile = $log->findLogFile();
            if ($logFile) {
                @unlink($logFile);
            }
        }
        
        // Delete database records
        self::where('created_at', '<', now()->subDays($days))->delete();
        
        // Clean up empty date directories
        $logsDir = storage_path('logs');
        $dateDirs = glob($logsDir . '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]', GLOB_ONLYDIR);
        foreach ($dateDirs as $dir) {
            // Only delete if directory is older than retention period and empty
            $dirDate = basename($dir);
            if (strtotime($dirDate) < strtotime("-{$days} days") && count(glob($dir . '/*')) === 0) {
                @rmdir($dir);
            }
        }
    }

    /**
     * Download log file as response
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|null
     */
    public function downloadLogFile()
    {
        $logFile = $this->findLogFile();
        
        if ($logFile && file_exists($logFile)) {
            $filename = basename($logFile);
            return response()->download($logFile, $filename);
        }
        
        return null;
    }
}
