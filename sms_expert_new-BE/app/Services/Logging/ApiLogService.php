<?php

namespace App\Services\Logging;

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * API Log Service
 *
 * Organizes API logs by date first, then an "api" subfolder, then customer bigid:
 * storage/logs/{date}/api/{customer_bigid}.log
 *
 * Example: storage/logs/2026-05-01/api/abc123def456.log
 */
class ApiLogService
{
    protected string $customerBigId;
    protected string $logPath;
    protected string $dateFolder;

    public function __construct(string $customerBigId)
    {
        $this->customerBigId = $this->sanitizeBigId($customerBigId);
        $this->dateFolder = Carbon::now()->format('Y-m-d');
        $this->logPath = $this->getLogPath();
        $this->ensureDirectoryExists();
    }

    /**
     * Create a new instance for a specific customer
     */
    public static function for(string $customerBigId): self
    {
        return new self($customerBigId);
    }

    /**
     * Create instance from request (extracts bigid from user or params)
     */
    public static function fromRequest(Request $request, ?string $userBigId = null): self
    {
        $bigId = $userBigId
            ?? $request->input('userbigid')
            ?? $request->user()?->bigid
            ?? 'anonymous';

        return new self($bigId);
    }

    /**
     * Get the full log path
     */
    protected function getLogPath(): string
    {
        return storage_path("logs/{$this->dateFolder}/api/{$this->customerBigId}.log");
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
     * Sanitize bigid for filename
     */
    protected function sanitizeBigId(string $bigId): string
    {
        // Remove any invalid characters for filename
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $bigId);

        // If empty, use 'unknown'
        return !empty($sanitized) ? $sanitized : 'unknown';
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
     * Log API request
     */
    public function logRequest(Request $request, array $additionalContext = []): void
    {
        $context = array_merge([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'params' => $this->sanitizeParams($request->all()),
        ], $additionalContext);

        $this->write('REQUEST', 'API Request', $context);
    }

    /**
     * Log API response
     */
    public function logResponse(int $statusCode, $response, float $duration = null): void
    {
        $context = [
            'status_code' => $statusCode,
            'response' => is_string($response) ? $response : json_encode($response),
        ];

        if ($duration !== null) {
            $context['duration_ms'] = round($duration * 1000, 2);
        }

        $this->write('RESPONSE', 'API Response', $context);
    }

    /**
     * Log SMS submission
     */
    public function logSmsSubmission(array $params): void
    {
        $context = [
            'from' => $params['from'] ?? 'N/A',
            'to_count' => is_array($params['to'] ?? null)
                ? count($params['to'])
                : count(explode(',', $params['to'] ?? '')),
            'type' => $params['type'] ?? 'text',
            'route' => $params['route'] ?? 'd',
            'bigid' => $params['bigid'] ?? 'N/A',
        ];

        $this->write('SMS', 'SMS Submission', $context);
    }

    /**
     * Log SMS result
     */
    public function logSmsResult(string $bigid, int $errorCode, string $errorText, int $queued = 0, int $failed = 0): void
    {
        $context = [
            'bigid' => $bigid,
            'error_code' => $errorCode,
            'error_text' => $errorText,
            'queued' => $queued,
            'failed' => $failed,
            'success' => $errorCode === 0,
        ];

        $level = $errorCode === 0 ? 'INFO' : 'WARNING';
        $this->write($level, 'SMS Result', $context);
    }

    /**
     * Sanitize params (remove sensitive data)
     */
    protected function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['pwd', 'password', 'pword', 'itaggsecretkey', 'secret', 'token'];

        foreach ($sensitiveKeys as $key) {
            if (isset($params[$key])) {
                $params[$key] = '***HIDDEN***';
            }
        }

        // Truncate message text if too long
        if (isset($params['txt']) && strlen($params['txt']) > 100) {
            $params['txt'] = substr($params['txt'], 0, 100) . '...[truncated]';
        }

        return $params;
    }

    /**
     * Get the log file path
     */
    public function getPath(): string
    {
        return $this->logPath;
    }

    /**
     * Get logs for a specific date and customer
     */
    public static function getLogs(string $date, string $customerBigId): ?string
    {
        $sanitizedId = (new self($customerBigId))->sanitizeBigId($customerBigId);
        $path = storage_path("logs/{$date}/api/{$sanitizedId}.log");

        if (File::exists($path)) {
            return File::get($path);
        }

        return null;
    }

    /**
     * Get all API logs for a specific date
     */
    public static function getLogsForDate(string $date): array
    {
        $directory = storage_path("logs/{$date}/api");
        $logs = [];

        if (File::isDirectory($directory)) {
            $files = File::files($directory);
            foreach ($files as $file) {
                $bigId = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $logs[$bigId] = $file->getPathname();
            }
        }

        return $logs;
    }

    /**
     * Get available log dates
     */
    public static function getAvailableDates(): array
    {
        // Layout is logs/{date}/api — list date folders that actually contain an api subfolder.
        $logsBase = storage_path('logs');
        $dates = [];

        if (File::isDirectory($logsBase)) {
            foreach (File::directories($logsBase) as $folder) {
                $name = basename($folder);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) && File::isDirectory($folder . '/api')) {
                    $dates[] = $name;
                }
            }
            rsort($dates); // Most recent first
        }

        return $dates;
    }

    /**
     * Get all customers who have logs for a specific date
     */
    public static function getCustomersForDate(string $date): array
    {
        $directory = storage_path("logs/{$date}/api");
        $customers = [];

        if (File::isDirectory($directory)) {
            $files = File::files($directory);
            foreach ($files as $file) {
                $customers[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }
        }

        return $customers;
    }

    /**
     * Clean old logs (older than specified days)
     */
    public static function cleanOldLogs(int $days = 30): int
    {
        // Layout is logs/{date}/api — delete only the api subfolder of each old date folder
        // (the date folder may hold other log types too, so it is left intact).
        $logsBase = storage_path('logs');
        $deletedCount = 0;
        $cutoffDate = Carbon::now()->subDays($days)->format('Y-m-d');

        if (File::isDirectory($logsBase)) {
            foreach (File::directories($logsBase) as $folder) {
                $folderDate = basename($folder);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $folderDate) || $folderDate >= $cutoffDate) {
                    continue;
                }
                $apiDir = $folder . '/api';
                if (File::isDirectory($apiDir)) {
                    File::deleteDirectory($apiDir);
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Get log statistics for a customer on a date
     */
    public static function getStats(string $date, string $customerBigId): array
    {
        $content = self::getLogs($date, $customerBigId);

        if (!$content) {
            return ['total' => 0, 'requests' => 0, 'errors' => 0, 'sms_submitted' => 0];
        }

        $lines = explode("\n", $content);
        $stats = [
            'total' => count(array_filter($lines)),
            'requests' => 0,
            'errors' => 0,
            'warnings' => 0,
            'sms_submitted' => 0,
        ];

        foreach ($lines as $line) {
            if (str_contains($line, 'REQUEST:')) $stats['requests']++;
            if (str_contains($line, 'ERROR:')) $stats['errors']++;
            if (str_contains($line, 'WARNING:')) $stats['warnings']++;
            if (str_contains($line, 'SMS Submission')) $stats['sms_submitted']++;
        }

        return $stats;
    }
}
