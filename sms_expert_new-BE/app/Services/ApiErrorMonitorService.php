<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Services\Queue\EmailQueueService;

/**
 * API Error Monitor Service
 * 
 * Monitors API errors and sends email notifications via RabbitMQ
 */
class ApiErrorMonitorService
{
    /**
     * Error severity levels
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Log and notify about API error
     */
    public function logError(
        Request $request,
        \Throwable $exception,
        ?array $responseData = null,
        ?int $statusCode = null
    ): void {
        try {
            $errorData = $this->buildErrorData($request, $exception, $responseData, $statusCode);
            
            // Log to file
            $this->logToFile($errorData);
            
            // Log to database
            $this->logToDatabase($errorData);
            
            // Check if should send email (with rate limiting)
            if ($this->shouldSendEmail($errorData)) {
                $this->sendEmailNotification($errorData);
            }
            
        } catch (\Throwable $e) {
            // Don't let monitoring errors break the app
            Log::error('API Error Monitor failed: ' . $e->getMessage());
        }
    }

    /**
     * Log API response error (non-exception errors)
     */
    public function logResponseError(
        Request $request,
        array $responseData,
        int $statusCode
    ): void {
        try {
            // Only log errors (4xx and 5xx status codes)
            if ($statusCode < 400) {
                return;
            }

            $errorData = [
                'type' => 'response_error',
                'timestamp' => now()->toIso8601String(),
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'user_id' => $request->user()?->id,
                    'user_bigid' => $request->user()?->bigid,
                ],
                'response' => [
                    'status_code' => $statusCode,
                    'message' => $responseData['message'] ?? 'Unknown error',
                    'errors' => $responseData['errors'] ?? [],
                ],
                'severity' => $this->determineSeverity($statusCode),
            ];

            // Log to file
            $this->logToFile($errorData);

            // Log to database
            $this->logToDatabase($errorData);

            // Send email for server errors (5xx)
            if ($statusCode >= 500 && $this->shouldSendEmail($errorData)) {
                $this->sendEmailNotification($errorData);
            }

        } catch (\Throwable $e) {
            Log::error('API Error Monitor failed: ' . $e->getMessage());
        }
    }

    /**
     * Build error data array
     */
    protected function buildErrorData(
        Request $request,
        \Throwable $exception,
        ?array $responseData,
        ?int $statusCode
    ): array {
        return [
            'type' => 'exception',
            'timestamp' => now()->toIso8601String(),
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user()?->id,
                'user_bigid' => $request->user()?->bigid,
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'body' => $this->sanitizeBody($request->all()),
            ],
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->getShortTrace($exception),
            ],
            'response' => [
                'status_code' => $statusCode ?? 500,
                'data' => $responseData,
            ],
            'severity' => $this->determineSeverityFromException($exception, $statusCode),
            'environment' => [
                'app_env' => config('app.env'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ];
    }

    /**
     * Sanitize headers (remove sensitive data)
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }
        
        return $headers;
    }

    /**
     * Sanitize request body (remove sensitive data)
     */
    protected function sanitizeBody(array $body): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'current_password', 'new_password', 'token', 'secret', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '[REDACTED]';
            }
        }
        
        return $body;
    }

    /**
     * Get shortened stack trace
     */
    protected function getShortTrace(\Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $shortTrace = [];
        
        foreach (array_slice($trace, 0, 10) as $frame) {
            $shortTrace[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }
        
        return $shortTrace;
    }

    /**
     * Determine severity from status code
     */
    protected function determineSeverity(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => self::SEVERITY_CRITICAL,
            $statusCode >= 400 => self::SEVERITY_MEDIUM,
            default => self::SEVERITY_LOW,
        };
    }

    /**
     * Determine severity from exception
     */
    protected function determineSeverityFromException(\Throwable $exception, ?int $statusCode): string
    {
        // Critical exceptions
        $criticalExceptions = [
            \PDOException::class,
            \Illuminate\Database\QueryException::class,
            \ErrorException::class,
        ];

        foreach ($criticalExceptions as $criticalClass) {
            if ($exception instanceof $criticalClass) {
                return self::SEVERITY_CRITICAL;
            }
        }

        if ($statusCode) {
            return $this->determineSeverity($statusCode);
        }

        return self::SEVERITY_HIGH;
    }

    /**
     * Log error to file
     */
    protected function logToFile(array $errorData): void
    {
        $logMessage = sprintf(
            "[%s] %s | %s %s | User: %s | Status: %s | %s",
            $errorData['severity'],
            $errorData['timestamp'],
            $errorData['request']['method'],
            $errorData['request']['path'],
            $errorData['request']['user_id'] ?? 'guest',
            $errorData['response']['status_code'] ?? 'N/A',
            $errorData['exception']['message'] ?? $errorData['response']['message'] ?? 'Unknown error'
        );

        Log::channel('api_errors')->error($logMessage, $errorData);
    }

    /**
     * Log error to database
     */
    protected function logToDatabase(array $errorData): void
    {
        try {
            \DB::table('api_error_logs')->insert([
                'type' => $errorData['type'],
                'severity' => $errorData['severity'],
                'method' => $errorData['request']['method'],
                'path' => $errorData['request']['path'],
                'url' => $errorData['request']['url'],
                'ip_address' => $errorData['request']['ip'],
                'user_id' => $errorData['request']['user_id'],
                'user_bigid' => $errorData['request']['user_bigid'],
                'status_code' => $errorData['response']['status_code'] ?? null,
                'error_message' => $errorData['exception']['message'] ?? $errorData['response']['message'] ?? null,
                'exception_class' => $errorData['exception']['class'] ?? null,
                'exception_file' => $errorData['exception']['file'] ?? null,
                'exception_line' => $errorData['exception']['line'] ?? null,
                'request_data' => json_encode($errorData['request']),
                'response_data' => json_encode($errorData['response']),
                'trace' => isset($errorData['exception']['trace']) ? json_encode($errorData['exception']['trace']) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log API error to database: ' . $e->getMessage());
        }
    }

    /**
     * Check if should send email (with rate limiting)
     */
    protected function shouldSendEmail(array $errorData): bool
    {
        // Check if email notifications are enabled
        if (!config('api_monitor.email_enabled', true)) {
            return false;
        }

        // Rate limiting: Max emails per hour
        $maxEmailsPerHour = config('api_monitor.max_emails_per_hour', 10);
        $cacheKey = 'api_error_emails_' . date('YmdH');
        
        $emailCount = Cache::get($cacheKey, 0);
        
        if ($emailCount >= $maxEmailsPerHour) {
            Log::warning('API error email rate limit reached');
            return false;
        }

        // Increment counter
        Cache::put($cacheKey, $emailCount + 1, 3600);

        // Only send for high/critical severity
        $minSeverity = config('api_monitor.min_email_severity', self::SEVERITY_HIGH);
        $severityOrder = [
            self::SEVERITY_LOW => 1,
            self::SEVERITY_MEDIUM => 2,
            self::SEVERITY_HIGH => 3,
            self::SEVERITY_CRITICAL => 4,
        ];

        return ($severityOrder[$errorData['severity']] ?? 0) >= ($severityOrder[$minSeverity] ?? 3);
    }

    /**
     * Send email notification via RabbitMQ
     */
    protected function sendEmailNotification(array $errorData): void
    {
        try {
            $recipients = config('api_monitor.email_recipients', []);
            
            if (empty($recipients)) {
                $recipients = [config('mail.from.address')];
            }

            // Use EmailQueueService to send via RabbitMQ
            $emailQueueService = new EmailQueueService();
            
            // Queue email for each recipient
            foreach ($recipients as $recipient) {
                $recipient = trim($recipient);
                if (empty($recipient)) continue;
                
                $emailQueueService->queueEmail(
                    \App\Mail\ApiErrorNotification::class,
                    $recipient,
                    ['errorData' => $errorData],
                    [], // cc recipients
                    10  // high priority for error notifications
                );
            }
            
            // Update database to mark email sent
            $this->markEmailSent($errorData);
            
            Log::info('API error notification queued to RabbitMQ', [
                'recipients' => $recipients,
                'severity' => $errorData['severity'],
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to queue API error notification email: ' . $e->getMessage());
            
            // Fallback: Try direct mail if RabbitMQ fails
            $this->sendEmailDirectFallback($errorData);
        }
    }

    /**
     * Fallback to direct email if RabbitMQ fails
     */
    protected function sendEmailDirectFallback(array $errorData): void
    {
        try {
            $recipients = config('api_monitor.email_recipients', []);
            
            if (empty($recipients)) {
                return;
            }

            \Mail::to($recipients)->send(new \App\Mail\ApiErrorNotification($errorData));
            
            Log::info('API error notification sent via direct mail (fallback)', [
                'recipients' => $recipients,
            ]);

        } catch (\Throwable $e) {
            Log::error('Fallback direct mail also failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark email as sent in database
     */
    protected function markEmailSent(array $errorData): void
    {
        try {
            \DB::table('api_error_logs')
                ->where('path', $errorData['request']['path'])
                ->where('created_at', '>=', now()->subSeconds(5))
                ->orderByDesc('id')
                ->limit(1)
                ->update([
                    'email_sent' => true,
                    'email_sent_at' => now(),
                ]);
        } catch (\Throwable $e) {
            // Silently fail - not critical
        }
    }
}
