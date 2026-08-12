<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use App\Mail\ExceptionNotificationMail;
use App\Services\Queue\EmailQueueService;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Send email notification for exceptions
            $this->sendExceptionEmail($e);
        });
    }

    /**
     * Send email notification for exceptions
     *
     * @param Throwable $exception
     * @return void
     */
    protected function sendExceptionEmail(Throwable $exception): void
    {
        // Check if email notifications are enabled
        if (!config('exception.email_enabled', false)) {
            return;
        }

        // Check environment restrictions
        if (config('exception.email_only_production', false)) {
            $allowedEnvironments = ['production', 'staging','development']; // Adjust as needed
            if (!in_array(app()->environment(), $allowedEnvironments)) {
                return;
            }
        }

        // Skip certain exception types
        if ($this->shouldSkipException($exception)) {
            return;
        }

        // Apply throttling to prevent email flooding
        if ($this->isThrottled()) {
            Log::warning('Exception email notification throttled');
            return;
        }

        // Get recipients from config
        $recipients = config('exception.email_recipients', []);
        if (empty($recipients)) {
            return;
        }

        try {
            // Gather exception details
            $exceptionData = $this->getExceptionData($exception);

            // Send email to all recipients via RabbitMQ queue
            $emailQueueService = new EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\ExceptionNotificationMail',
                    trim($recipient),
                    ['exception_data' => $exceptionData],
                    [],
                    10 // High priority for exceptions
                );
            }

            Log::info('Exception email notification queued', [
                'exception' => get_class($exception),
                'recipients' => $recipients
            ]);

        } catch (\Exception $e) {
            // Log if email sending fails, but don't throw to avoid infinite loop
            Log::error('Failed to queue exception email notification', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if exception should be skipped for email notification
     *
     * @param Throwable $exception
     * @return bool
     */
    protected function shouldSkipException(Throwable $exception): bool
    {
        // Skip validation exceptions
        if ($exception instanceof ValidationException) {
            return true;
        }

        // Skip authentication exceptions
        if ($exception instanceof AuthenticationException) {
            return true;
        }

        // Skip 404 and other common HTTP exceptions
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            // Skip 4xx client errors (except 500 range)
            if ($statusCode >= 400 && $statusCode < 500) {
                return true;
            }
        }

        // Skip exceptions in dontReport array
        foreach ($this->dontReport as $type) {
            if ($exception instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if email notifications are throttled
     *
     * @return bool
     */
    protected function isThrottled(): bool
    {
        $throttleLimit = config('exception.email_throttle', 5);
        $cacheKey = 'exception_email_throttle';

        $count = Cache::get($cacheKey, 0);

        if ($count >= $throttleLimit) {
            return true;
        }

        // Increment counter (expires in 1 minute)
        Cache::put($cacheKey, $count + 1, now()->addMinute());

        return false;
    }

    /**
     * Get detailed exception data
     *
     * @param Throwable $exception
     * @return array
     */
    protected function getExceptionData(Throwable $exception): array
    {
        $request = request();
        $user = null;

        try {
            $user = auth()->user();
        } catch (\Exception $e) {
            // Auth might not be available in some contexts
        }

        // Get important request headers
        $headers = $this->getImportantHeaders($request);

        return [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception->getTrace()),
            'url' => $request->fullUrl() ?? 'N/A',
            'method' => $request->method() ?? 'N/A',
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->userAgent() ?? 'N/A',
            'referer' => $request->header('referer') ?? null,
            'user_id' => $user->id ?? null,
            'user_email' => $user->contactemail ?? $user->email ?? null,
            'user_name' => $user->uname ?? $user->name ?? null,
            'input' => $this->sanitizeInput($request->except(['password', 'password_confirmation', 'current_password', 'token', 'api_key'])),
            'headers' => $headers,
            'environment' => app()->environment(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'timestamp' => now()->format('Y-m-d H:i:s T'),
            'server' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'memory_usage' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
            'request_id' => $request->header('X-Request-ID') ?? uniqid('err_'),
        ];
    }

    /**
     * Get client IP address considering proxies
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    protected function getClientIp($request): string
    {
        // Check for proxy headers
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return $request->ip() ?? 'N/A';
    }

    /**
     * Get important request headers for debugging
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    protected function getImportantHeaders($request): array
    {
        $importantHeaders = [
            'Content-Type',
            'Accept',
            'Authorization',
            'X-Requested-With',
            'X-CSRF-TOKEN',
            'Origin',
            'X-Forwarded-For',
            'X-Real-IP',
        ];

        $headers = [];
        foreach ($importantHeaders as $headerName) {
            $value = $request->header($headerName);
            if ($value) {
                // Mask sensitive headers
                if (strtolower($headerName) === 'authorization') {
                    $headers[$headerName] = substr($value, 0, 20) . '...';
                } else {
                    $headers[$headerName] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Format exception trace for readability
     *
     * @param array $trace
     * @return array
     */
    protected function formatTrace(array $trace): array
    {
        // Limit trace to 10 most recent calls
        $trace = array_slice($trace, 0, 10);

        return array_map(function ($item) {
            return [
                'file' => $item['file'] ?? 'N/A',
                'line' => $item['line'] ?? 'N/A',
                'function' => ($item['class'] ?? '') . ($item['type'] ?? '') . ($item['function'] ?? ''),
            ];
        }, $trace);
    }

    /**
     * Sanitize input data
     *
     * @param array $input
     * @return array
     */
    protected function sanitizeInput(array $input): array
    {
        // Limit input size to prevent huge emails
        if (count($input) > 50) {
            return ['message' => 'Input data too large (truncated)'];
        }

        return $input;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $e)
    {
        // Handle 419 Page Expired (CSRF Token Mismatch) - redirect to login
        if ($e instanceof TokenMismatchException) {
            // For API requests, return JSON error
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Session expired. Please login again.',
                    'code' => 419
                ], 419);
            }

            // Determine which login page to redirect to based on URL path
            $path = $request->path();

            if (str_starts_with($path, 'admin')) {
                // Admin routes - redirect to admin login
                return redirect()->route('admin.login')
                    ->with('error', 'Your session has expired. Please login again.');
            } elseif (str_starts_with($path, 'campaign')) {
                // Campaign routes - redirect to campaign login
                return redirect()->route('campaign.login')
                    ->with('error', 'Your session has expired. Please login again.');
            } else {
                // Customer/default routes - redirect to customer login
                return redirect()->route('showloginform')
                    ->with('error', 'Your session has expired. Please login again.');
            }
        }

        // Log the exception context (skip for 419 as it's handled above)
        Log::error('Exception occurred', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        return parent::render($request, $e);
    }
}
