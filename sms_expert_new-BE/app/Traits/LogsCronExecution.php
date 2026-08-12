<?php

namespace App\Traits;

use App\Models\CronJobLog;
use App\Services\Queue\EmailQueueService;
use Illuminate\Support\Facades\Log;

trait LogsCronExecution
{
    /**
     * Execute a command with automatic logging to database and file
     *
     * @param string $commandName The artisan command name (e.g., 'sms:update-pricing')
     * @param callable $callback The actual command logic to execute
     * @return int Command exit code (0 for success, 1 for failure)
     */
    protected function executeWithLogging(string $commandName, callable $callback): int
    {
        $startTime = now();
        $status = 'failed';
        $output = '';
        $error = null;
        $exception = null;

        // Create dated log file path
        $logDate = $startTime->format('Y-m-d');
        $logDir = storage_path("logs/{$logDate}");

        // Create directory if it doesn't exist
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Sanitize command name for filename
        $logFileName = str_replace([':', ' '], ['_', '_'], $commandName) . '.log';
        $logFilePath = $logDir . '/' . $logFileName;

        try {
            // Start logging
            $this->writeToLogFile($logFilePath, "=== Cron Job Started: {$commandName} ===");
            $this->writeToLogFile($logFilePath, "Start Time: " . $startTime->format('Y-m-d H:i:s'));
            $this->writeToLogFile($logFilePath, str_repeat('-', 60));

            // Capture output buffer
            ob_start();

            // Execute the command
            $result = $callback();

            // Get any output that was echoed
            $consoleOutput = ob_get_clean();

            // Determine success based on return value
            if (is_string($result)) {
                $output = $result;
                $status = 'success';
            } elseif ($result === 0 || $result === true || $result === null) {
                $status = 'success';
                $output = 'Command completed successfully';
            } else {
                $status = 'failed';
                $output = 'Command returned non-zero exit code: ' . $result;
            }

            // Add console output if any
            if (!empty($consoleOutput)) {
                $output .= "\n\nConsole Output:\n" . $consoleOutput;
            }

            // Write to log file
            $this->writeToLogFile($logFilePath, "\nStatus: SUCCESS");
            $this->writeToLogFile($logFilePath, "Output: " . $output);
        } catch (\Exception $e) {
            $status = 'failed';
            $error = $e->getMessage();
            $output = 'Exception: ' . $e->getMessage();
            $exception = $e;

            // Write error to log file
            $this->writeToLogFile($logFilePath, "\nStatus: FAILED");
            $this->writeToLogFile($logFilePath, "Error: " . $error);
            $this->writeToLogFile($logFilePath, "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")");
            $this->writeToLogFile($logFilePath, "Stack Trace:\n" . $e->getTraceAsString());

            // Log the error to Laravel log
            Log::error("Cron command failed: {$commandName}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            $finishTime = now();
            $duration = $finishTime->diffInSeconds($startTime);

            // Write completion to log file
            $this->writeToLogFile($logFilePath, str_repeat('-', 60));
            $this->writeToLogFile($logFilePath, "Finish Time: " . $finishTime->format('Y-m-d H:i:s'));
            $this->writeToLogFile($logFilePath, "Duration: {$duration} seconds");
            $this->writeToLogFile($logFilePath, "=== Cron Job Completed ===\n");

            // Log to database
            CronJobLog::create([
                'command' => $commandName,
                'status' => $status,
                'started_at' => $startTime,
                'finished_at' => $finishTime,
                'duration' => $duration,
                'output' => $output,
                'error' => $error,
                'log_file' => $logFilePath,
            ]);

            // Send error notification email if failed
            if ($status === 'failed') {
                $this->sendCronFailureNotification($commandName, $error, $output, $exception, $duration);
            }
        }

        return $status === 'success' ? 0 : 1;
    }

    /**
     * Send cron failure notification email
     *
     * @param string $commandName
     * @param string|null $error
     * @param string $output
     * @param \Exception|null $exception
     * @param int $duration
     * @return void
     */
    protected function sendCronFailureNotification(
        string $commandName,
        ?string $error,
        string $output,
        ?\Exception $exception,
        int $duration
    ): void {
        // Check if cron error emails are enabled
        if (!config('exception.email_enabled', true)) {
            return;
        }

        $recipients = config('exception.email_recipients', []);
        if (empty($recipients)) {
            return;
        }

        try {
            // Build cron error data
            $cronData = [
                'command' => $commandName,
                'description' => "Cron job '{$commandName}' failed to complete successfully.",
                'environment' => app()->environment(),
                'app_name' => config('app.name', 'SMS Expert'),
                'app_url' => config('app.url'),
                'timestamp' => now()->format('Y-m-d H:i:s T'),
                'execution_time' => $duration . ' seconds',
                'error_output' => $output,
                'server_ip' => $this->getServerIp(),
                'hostname' => gethostname(),
                'server' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => $this->formatBytes(memory_get_peak_usage(true)),
                    'disk_free' => $this->formatBytes(disk_free_space(base_path())),
                ],
            ];

            // Add exception details if available
            if ($exception) {
                $cronData['exception'] = [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $this->formatExceptionTrace($exception->getTrace()),
                ];
            }

            // Queue email notifications
            $emailQueueService = new EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\CronErrorNotificationMail',
                    trim($recipient),
                    ['cron_data' => $cronData],
                    [],
                    10 // High priority
                );
            }

            Log::info("Cron failure notification queued for: {$commandName}");

        } catch (\Exception $e) {
            Log::error("Failed to send cron failure notification: " . $e->getMessage());
        }
    }

    /**
     * Format exception trace for email
     *
     * @param array $trace
     * @return array
     */
    protected function formatExceptionTrace(array $trace): array
    {
        return array_map(function ($item) {
            return [
                'file' => $item['file'] ?? 'N/A',
                'line' => $item['line'] ?? 'N/A',
                'function' => ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? ''),
            ];
        }, array_slice($trace, 0, 10));
    }

    /**
     * Get server IP address
     *
     * @return string
     */
    protected function getServerIp(): string
    {
        if (!empty($_SERVER['SERVER_ADDR'])) {
            return $_SERVER['SERVER_ADDR'];
        }

        $hostname = gethostname();
        $ip = gethostbyname($hostname);

        return $ip !== $hostname ? $ip : 'N/A';
    }

    /**
     * Format bytes to human readable
     *
     * @param int|float $bytes
     * @return string
     */
    protected function formatBytes($bytes): string
    {
        if ($bytes === false) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Write content to log file with retry logic for file locking
     *
     * @param string $filePath
     * @param string $content
     * @return void
     */
    private function writeToLogFile(string $filePath, string $content): void
    {
        $maxRetries = 3;
        $retryDelay = 100000; // 100ms in microseconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $handle = @fopen($filePath, 'a');
                if ($handle === false) {
                    if ($attempt < $maxRetries) {
                        usleep($retryDelay * $attempt);
                        continue;
                    }
                    // Fall back to Laravel log on final failure
                    Log::warning("Cron log file write failed: {$filePath}", ['content' => substr($content, 0, 200)]);
                    return;
                }

                // Try to get exclusive lock (non-blocking on Windows)
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    fwrite($handle, $content . "\n");
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    return;
                } else {
                    fclose($handle);
                    if ($attempt < $maxRetries) {
                        usleep($retryDelay * $attempt);
                        continue;
                    }
                    // Fall back to Laravel log on final failure
                    Log::warning("Cron log file lock failed: {$filePath}", ['content' => substr($content, 0, 200)]);
                    return;
                }
            } catch (\Exception $e) {
                if ($attempt >= $maxRetries) {
                    Log::warning("Cron log file exception: {$filePath}", ['error' => $e->getMessage()]);
                    return;
                }
                usleep($retryDelay * $attempt);
            }
        }
    }
}
