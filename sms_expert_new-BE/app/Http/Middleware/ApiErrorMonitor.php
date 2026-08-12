<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ApiErrorMonitorService;

/**
 * API Error Monitor Middleware
 * 
 * Monitors API responses and logs errors, sends email notifications for failures
 */
class ApiErrorMonitor
{
    protected ApiErrorMonitorService $errorMonitor;

    public function __construct(ApiErrorMonitorService $errorMonitor)
    {
        $this->errorMonitor = $errorMonitor;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            
            // Check if response is an error
            $statusCode = $response->getStatusCode();
            
            if ($statusCode >= 400) {
                $this->handleErrorResponse($request, $response, $statusCode);
            }
            
            return $response;
            
        } catch (\Throwable $exception) {
            // Log the exception
            $this->errorMonitor->logError($request, $exception, null, 500);
            
            // Re-throw to let Laravel handle it
            throw $exception;
        }
    }

    /**
     * Handle error response
     */
    protected function handleErrorResponse(Request $request, Response $response, int $statusCode): void
    {
        try {
            $content = $response->getContent();
            $responseData = json_decode($content, true) ?? ['message' => 'Unknown error'];
            
            // Log response errors (5xx are critical, 4xx are logged but may not send email)
            $this->errorMonitor->logResponseError($request, $responseData, $statusCode);
            
        } catch (\Throwable $e) {
            // Don't let monitoring errors affect the response
            \Log::warning('API Error Monitor middleware failed: ' . $e->getMessage());
        }
    }
}
