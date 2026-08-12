<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

/**
 * Cron Log Service
 *
 * Organizes cron logs by date and cron name:
 * storage/logs/{date}/cron/{CronName}.log
 *
 * Mirrors the SMPP-provider (logs/{date}/smpp/{provider}.log) and RabbitMQ
 * (logs/{date}/rabbitmq/{queue}.log) layouts so every component's logs live
 * under the same date folder and a date can be retained/purged as one unit.
 *
 * Example: storage/logs/2026-05-01/cron/ProcessScheduledSms.log
 */
class CronLogService
{
    protected string $cronName;
    protected string $logPath;
    protected string $dateFolder;

    public function __construct(string $cronName)
    {
        $this->cronName = $this->sanitizeName($cronName);
        $this->dateFolder = Carbon::now()->format('Y-m-d');
        $this->logPath = $this->getLogPath();
        $this->ensureDirectoryExists();
    }

    /**
     * Create a new instance for a specific cron job
     */
    public static function for(string $cronName): self
    {
        return new self($cronName);
    }

    /**
     * Get the full log path
     */
    protected function getLogPath(): string
    {
        return storage_path("logs/{$this->dateFolder}/cron/{$this->cronName}.log");
    }

    /**
     * Ensure the directory exists
     */
    protected function ensureDirectoryExists(): void
    {
        $directory = dirname($this->logPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Sanitize cron name for filename
     */
    protected function sanitizeName(string $name): string
    {
        // Remove namespace if present
        if (str_contains($name, '\\')) {
            $parts = explode('\\', $name);
            $name = end($parts);
        }

        // Remove "Command" suffix if present
        $name = preg_replace('/Command$/', '', $name);

        // Remove any invalid characters
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    }

    /**
     * Format log message with timestamp
     */
    protected function formatMessage(string $level, string $message, array $context = []): string
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s.u');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';

        return "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
    }

    /**
     * Write to log file
     */
    protected function write(string $level, string $message, array $context = []): void
    {
        $formattedMessage = $this->formatMessage($level, $message, $context);
        File::append($this->logPath, $formattedMessage);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    /**
     * Log cron start
     */
    public function start(array $context = []): void
    {
        $this->write('INFO', "========== CRON JOB STARTED ==========", $context);
    }

    /**
     * Log cron end with summary
     */
    public function end(array $summary = []): void
    {
        $this->write('INFO', "========== CRON JOB COMPLETED ==========", $summary);
    }

    /**
     * Log cron failure
     */
    public function failed(string $error, array $context = []): void
    {
        $context['error'] = $error;
        $this->write('ERROR', "========== CRON JOB FAILED ==========", $context);
    }

    /**
     * Get the log file path
     */
    public function getPath(): string
    {
        return $this->logPath;
    }

    /**
     * Get logs for a specific date and cron name
     */
    public static function getLogs(string $date, string $cronName): ?string
    {
        $sanitizedName = (new self($cronName))->sanitizeName($cronName);
        $path = storage_path("logs/{$date}/cron/{$sanitizedName}.log");

        if (File::exists($path)) {
            return File::get($path);
        }

        return null;
    }

    /**
     * Get all cron logs for a specific date
     */
    public static function getLogsForDate(string $date): array
    {
        $directory = storage_path("logs/{$date}/cron");
        $logs = [];

        if (File::isDirectory($directory)) {
            $files = File::files($directory);
            foreach ($files as $file) {
                $cronName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $logs[$cronName] = $file->getPathname();
            }
        }

        return $logs;
    }

    /**
     * Get available log dates
     */
    public static function getAvailableDates(): array
    {
        $root = storage_path('logs');
        $dates = [];

        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $folder) {
                $name = basename($folder);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) && File::isDirectory($folder . '/cron')) {
                    $dates[] = $name;
                }
            }
            rsort($dates); // Most recent first
        }

        return $dates;
    }

    /**
     * Clean old logs (older than specified days)
     */
    public static function cleanOldLogs(int $days = 30): int
    {
        $root = storage_path('logs');
        $deletedCount = 0;
        $cutoffDate = Carbon::now()->subDays($days)->format('Y-m-d');

        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $folder) {
                $name = basename($folder);
                // Only delete the cron/ subfolder — rabbitmq/, smpp/ and
                // laravel.log share this date dir and must be left intact.
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) && $name < $cutoffDate) {
                    $cronDir = $folder . '/cron';
                    if (File::isDirectory($cronDir)) {
                        File::deleteDirectory($cronDir);
                        $deletedCount++;
                    }
                }
            }
        }

        return $deletedCount;
    }
}
