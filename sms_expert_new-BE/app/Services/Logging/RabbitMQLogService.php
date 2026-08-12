<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

/**
 * RabbitMQ Log Service
 *
 * Organizes queue logs by date and queue name:
 * storage/logs/{date}/rabbitmq/{QueueName}.log
 *
 * Mirrors the SMPP-provider logging structure (logs/{date}/smpp/{provider}.log)
 * and the cron logging idea (per-name files) so operators can:
 *   - find one queue's history without grepping laravel.log
 *   - retain or purge whole-date directories together
 *   - tail a specific queue in real time
 *
 * Example: storage/logs/2026-06-07/rabbitmq/dlr.callback.push.log
 */
class RabbitMQLogService
{
    protected string $queueName;
    protected string $logPath;
    protected string $dateFolder;

    public function __construct(string $queueName)
    {
        $this->queueName = $this->sanitizeName($queueName);
        $this->dateFolder = Carbon::now()->format('Y-m-d');
        $this->logPath = $this->getLogPath();
        $this->ensureDirectoryExists();
    }

    public static function for(string $queueName): self
    {
        return new self($queueName);
    }

    protected function getLogPath(): string
    {
        return storage_path("logs/{$this->dateFolder}/rabbitmq/{$this->queueName}.log");
    }

    protected function ensureDirectoryExists(): void
    {
        $directory = dirname($this->logPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Sanitize queue name for filename — preserve dots so 'dlr.callback.push'
     * stays readable. Strip anything else.
     */
    protected function sanitizeName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    }

    protected function formatMessage(string $level, string $message, array $context = []): string
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s.u');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        return "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
    }

    protected function write(string $level, string $message, array $context = []): void
    {
        try {
            File::append($this->logPath, $this->formatMessage($level, $message, $context));
        } catch (\Throwable $e) {
            // logging must never throw — fall back silently
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    public function getPath(): string
    {
        return $this->logPath;
    }

    /**
     * List all per-queue log files for a given date.
     */
    public static function getLogsForDate(string $date): array
    {
        $directory = storage_path("logs/{$date}/rabbitmq");
        $logs = [];
        if (File::isDirectory($directory)) {
            foreach (File::files($directory) as $file) {
                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $logs[$name] = $file->getPathname();
            }
        }
        return $logs;
    }

    /**
     * Date subdirectories under logs/ that contain a rabbitmq/ folder.
     * Same pattern as CronLogService::getAvailableDates so the admin UI
     * can render the same dropdown.
     */
    public static function getAvailableDates(): array
    {
        $root = storage_path('logs');
        $dates = [];
        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $folder) {
                $name = basename($folder);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) && File::isDirectory($folder . '/rabbitmq')) {
                    $dates[] = $name;
                }
            }
            rsort($dates);
        }
        return $dates;
    }

    /**
     * Delete logs/{date}/rabbitmq folders older than $days.
     * Call from a daily cron alongside other log retention.
     */
    public static function cleanOldLogs(int $days = 30): int
    {
        $root = storage_path('logs');
        $deleted = 0;
        $cutoff = Carbon::now()->subDays($days)->format('Y-m-d');

        if (File::isDirectory($root)) {
            foreach (File::directories($root) as $folder) {
                $name = basename($folder);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) && $name < $cutoff) {
                    $rabbitDir = $folder . '/rabbitmq';
                    if (File::isDirectory($rabbitDir)) {
                        File::deleteDirectory($rabbitDir);
                        $deleted++;
                    }
                }
            }
        }
        return $deleted;
    }
}
