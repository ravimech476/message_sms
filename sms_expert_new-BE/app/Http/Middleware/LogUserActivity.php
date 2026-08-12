<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Models\UserActivityLog;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    protected $startTime;
    protected $queries = [];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Start timing the request
        $this->startTime = microtime(true);

        // Enable query logging
        DB::enableQueryLog();

        // Process the request
        $response = $next($request);

        // Log the activity after the response is ready
        $this->logActivity($request, $response);

        return $response;
    }

    /**
     * Log user activity
     */
    protected function logActivity(Request $request, Response $response)
    {
        try {
            // Skip logging for certain routes
            if ($this->shouldSkipLogging($request)) {
                return;
            }

            // Determine user type and user info
            $userInfo = $this->getUserInfo($request);
            
            if (!$userInfo) {
                return;
            }

            // Get executed queries
            $queries = DB::getQueryLog();
            $formattedQueries = $this->formatQueries($queries);

            // Calculate execution time
            $executionTime = (microtime(true) - $this->startTime) * 1000;

            // Determine action and page name
            $action = $this->determineAction($request);
            $pageName = $this->determinePageName($request);

            // Sanitize request data
            $requestData = $this->sanitizeRequestData($request->all());

            // Create activity log
              $get_users = User::where('bigid', $userInfo['ref'])->first();
            UserActivityLog::create([
                'user_type' => $get_users->login_type=='admin' ? 'admin' : 'customer',
                'user_id' => $get_users->id,
                'user_ref' => $userInfo['ref'],
                'action' => $action,
                'page_url' => $request->fullUrl(),
                'page_name' => $pageName,
                'http_method' => $request->method(),
                'description' => $this->generateDescription($action, $request, $response),
                'request_data' => $requestData,
                'response_data' => $this->getResponseSummary($response),
                'queries_executed' => !empty($formattedQueries) ? json_encode($formattedQueries) : null,
                'query_count' => count($queries),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'session_id' => $request->session()->getId(),
                'response_status' => $response->getStatusCode(),
                'execution_time_ms' => round($executionTime, 2),
                'error_message' => $response->getStatusCode() >= 400 ? $this->getErrorMessage($response) : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log user activity: ' . $e->getMessage());
        }
    }

    protected function getUserInfo(Request $request)
    {
        if (Session::has('user_info')) {
            $userInfo = Session::get('user_info');
            return [
                'type' => 'customer',
                'id' => $userInfo['id'] ?? null,
                'ref' => $userInfo['bigid'] ?? null,
            ];
        }

        if (Session::has('admin_info')) {
            $adminInfo = Session::get('admin_info');
            return [
                'type' => 'admin',
                'id' => $adminInfo['id'] ?? null,
                'ref' => $adminInfo['username'] ?? $adminInfo['email'] ?? null,
            ];
        }

        if ($request->user()) {
            return [
                'type' => 'customer',
                'id' => $request->user()->id,
                'ref' => $request->user()->bigid ?? null,
            ];
        }

        return null;
    }

    protected function shouldSkipLogging(Request $request): bool
    {
        $skipPatterns = [
            '/assets/', '/css/', '/js/', '/images/', '/fonts/', '/_debugbar', '/favicon.ico', '/api/health', '/api/ping',
        ];

        $path = $request->path();

        foreach ($skipPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function determineAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        if (str_contains($path, 'login') && $method === 'POST') return 'login';
        if (str_contains($path, 'logout')) return 'logout';
        if (str_contains($path, 'sms') || str_contains($path, 'send')) return $method === 'POST' ? 'send_sms' : 'view_sms';
        if (str_contains($path, 'user') || str_contains($path, 'customer')) {
            if ($method === 'POST') return 'create_user';
            if ($method === 'PUT' || $method === 'PATCH') return 'update_user';
            if ($method === 'DELETE') return 'delete_user';
            return 'view_users';
        }
        if (str_contains($path, 'profile')) return $method === 'POST' || $method === 'PUT' || $method === 'PATCH' ? 'update_profile' : 'view_profile';
        if (str_contains($path, 'dashboard')) return 'view_dashboard';
        if (str_contains($path, 'settings')) return $method === 'POST' || $method === 'PUT' || $method === 'PATCH' ? 'update_settings' : 'view_settings';
        if (str_contains($path, 'report')) return 'view_report';
        if (str_contains($path, 'invoice')) return $method === 'POST' ? 'create_invoice' : 'view_invoice';

        switch ($method) {
            case 'GET': return 'view_page';
            case 'POST': return 'create_data';
            case 'PUT':
            case 'PATCH': return 'update_data';
            case 'DELETE': return 'delete_data';
            default: return 'unknown_action';
        }
    }

    protected function determinePageName(Request $request): string
    {
        $routeName = $request->route() ? $request->route()->getName() : null;
        if ($routeName) {
            return ucwords(str_replace(['.', '_', '-'], ' ', $routeName));
        }
        $path = trim($request->path(), '/');
        return ucwords(str_replace(['/', '_', '-'], ' ', $path)) ?: 'Home';
    }

    protected function generateDescription(string $action, Request $request, Response $response): string
    {
        $pageName = $this->determinePageName($request);
        $status = $response->getStatusCode();

        $descriptions = [
            'login' => 'User logged into the system',
            'logout' => 'User logged out from the system',
            'send_sms' => 'SMS message sent',
            'view_dashboard' => 'Viewed dashboard',
        ];

        $description = $descriptions[$action] ?? "{$request->method()} request to {$pageName}";
        if ($status >= 400) $description .= " (Failed with status {$status})";
        return $description;
    }

    protected function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'current_password', 'new_password', 'api_key', 'api_secret', 'secret', 'token'];
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) $data[$key] = '***REDACTED***';
        }
        if (strlen(json_encode($data)) > 10000) return ['_note' => 'Request data too large'];
        return $data;
    }

    protected function getResponseSummary(Response $response): ?array
    {
        try {
            $content = $response->getContent();
            if ($response->headers->get('Content-Type') && str_contains($response->headers->get('Content-Type'), 'application/json')) {
                $jsonData = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return [
                        'type' => 'json',
                        'status' => $response->getStatusCode(),
                        'has_data' => !empty($jsonData),
                    ];
                }
            }
            return [
                'type' => 'html',
                'status' => $response->getStatusCode(),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function formatQueries(array $queries): array
    {
        $formatted = [];
        foreach ($queries as $query) {
            $sql = $query['query'];
            foreach ($query['bindings'] as $binding) {
                $value = is_numeric($binding) ? $binding : "'{$binding}'";
                $sql = preg_replace('/\?/', $value, $sql, 1);
            }
            $formatted[] = ['query' => $sql, 'time_ms' => $query['time']];
        }
        if (count($formatted) > 50) {
            $formatted = array_slice($formatted, 0, 50);
            $formatted[] = ['note' => '... ' . (count($queries) - 50) . ' more queries'];
        }
        return $formatted;
    }

    protected function getErrorMessage(Response $response): ?string
    {
        try {
            $content = $response->getContent();
            if ($response->headers->get('Content-Type') && str_contains($response->headers->get('Content-Type'), 'application/json')) {
                $jsonData = json_decode($content, true);
                if (isset($jsonData['message'])) return $jsonData['message'];
                if (isset($jsonData['error'])) return $jsonData['error'];
            }
            return "HTTP {$response->getStatusCode()} Error";
        } catch (\Exception $e) {
            return null;
        }
    }
}
