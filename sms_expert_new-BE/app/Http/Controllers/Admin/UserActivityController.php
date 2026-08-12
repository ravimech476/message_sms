<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserActivityController extends Controller
{
    /**
     * Display user activity logs
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'logs');
        $userType = $request->get('user_type', 'customer');
        
        return view('admin.settings.index', [
            'activeTab' => $tab,
            'userType' => $userType,
        ]);
    }

    /**
     * Get activity logs data (AJAX)
     */
    public function getData(Request $request)
    {
        try {
            $userType = $request->get('user_type', 'customer');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $userId = $request->get('user_id');
            $action = $request->get('action');
            $search = $request->get('search');
            $perPage = $request->get('per_page', 50);

            $query = UserActivityLog::query()
                ->ofUserType($userType)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            if ($userId && $userId !== 'all') {
                $query->where('user_id', $userId);
            }

            if ($action && $action !== 'all') {
                $query->where('action', $action);
            }

            if ($search) {
                $query->search($search);
            }

            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'pagination' => [
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch activity logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $userType = $request->get('user_type', 'customer');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // Build base query
            $query = UserActivityLog::query()->ofUserType($userType);

            // Only apply date filters if provided
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            } elseif ($startDate) {
                $query->whereDate('created_at', '>=', Carbon::parse($startDate)->startOfDay());
            } elseif ($endDate) {
                $query->whereDate('created_at', '<=', Carbon::parse($endDate)->endOfDay());
            }

            // Get total count first
            $totalActivities = (clone $query)->count();

            // Get statistics with safe defaults
            $statistics = [
                'total_activities' => $totalActivities,
                'unique_users' => (clone $query)->distinct()->count('user_id'),
                'total_queries' => (int)((clone $query)->sum('query_count') ?? 0),
                'failed_requests' => (clone $query)->where('response_status', '>=', 400)->count(),
                'avg_execution_time' => round((float)((clone $query)->avg('execution_time_ms') ?? 0), 2),
            ];

            // Top actions
            $topActions = (clone $query)
                ->select('action', DB::raw('count(*) as count'))
                ->whereNotNull('action')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Activity by hour
            $activityByHourData = (clone $query)
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
                ->whereNotNull('created_at')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->pluck('count', 'hour')
                ->toArray();

            // Fill missing hours with 0
            $activityByHour = [];
            for ($i = 0; $i < 24; $i++) {
                $activityByHour[$i] = $activityByHourData[$i] ?? 0;
            }

            // Activity by date
            $activityByDate = (clone $query)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->whereNotNull('created_at')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Most active users
            $mostActiveUsers = (clone $query)
                ->select('user_id', 'user_ref', DB::raw('count(*) as activity_count'))
                ->whereNotNull('user_id')
                ->groupBy('user_id', 'user_ref')
                ->orderBy('activity_count', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'statistics' => $statistics,
                'top_actions' => $topActions,
                'activity_by_hour' => $activityByHour,
                'activity_by_date' => $activityByDate,
                'most_active_users' => $mostActiveUsers,
            ]);

        } catch (\Exception $e) {
            Log::error('Statistics error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage(),
                'statistics' => [
                    'total_activities' => 0,
                    'unique_users' => 0,
                    'total_queries' => 0,
                    'failed_requests' => 0,
                    'avg_execution_time' => 0,
                ]
            ], 200); // Return 200 with zero stats instead of 500 error
        }
    }

    /**
     * Get all unique actions
     */
    public function getActions(Request $request)
    {
        try {
            $userType = $request->get('user_type', 'customer');

            $actions = UserActivityLog::query()
                ->ofUserType($userType)
                ->select('action')
                ->whereNotNull('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action');

            return response()->json([
                'success' => true,
                'actions' => $actions
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch actions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch actions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user list for filter
     */
    public function getUsers(Request $request)
    {
        try {
            $userType = $request->get('user_type', 'customer');

            $users = UserActivityLog::query()
                ->ofUserType($userType)
                ->select('user_id', 'user_ref')
                ->whereNotNull('user_ref')
                ->distinct()
                ->orderBy('user_ref')
                ->get()
                ->map(function ($log) {
                    $get_users = User::where('bigid', $log->user_ref)->first();
                    return [
                        'id' => $log->user_id,
                        'ref' => $log->user_ref,
                        'name' => $get_users ? ($get_users->busname ?? $get_users->contactname ?? $log->user_ref) : $log->user_ref,
                    ];
                })
                ->filter(function ($user) {
                    return !empty($user['ref']);
                })
                ->values();

            return response()->json([
                'success' => true,
                'users' => $users
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a single activity log
     */
    public function show($id)
    {
        try {
            $log = UserActivityLog::findOrFail($id);

            return response()->json([
                'success' => true,
                'log' => $log
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch activity log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Activity log not found'
            ], 404);
        }
    }

    /**
     * Export activity logs
     */
    public function export(Request $request)
    {
        try {
            $userType = $request->get('user_type', 'customer');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $query = UserActivityLog::query()
                ->ofUserType($userType)
                ->orderBy('created_at', 'desc');

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $logs = $query->limit(5000)->get();

            $filename = 'user_activity_logs_' . $userType . '_' . date('Y-m-d_His') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($logs) {
                $file = fopen('php://output', 'w');
                
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                fputcsv($file, [
                    'Timestamp',
                    'User Ref',
                    'Action',
                    'Page Name',
                    'HTTP Method',
                    'Description',
                    'IP Address',
                    'Status Code',
                    'Execution Time (ms)',
                    'Query Count',
                ]);

                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                        $log->user_ref,
                        $log->action,
                        $log->page_name,
                        $log->http_method,
                        $log->description,
                        $log->ip_address,
                        $log->response_status,
                        $log->execution_time_ms,
                        $log->query_count,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Failed to export logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete old activity logs
     */
    public function cleanOldLogs(Request $request)
    {
        try {
            $days = $request->get('days', 90);
            $cutoffDate = Carbon::now()->subDays($days);

            $deletedCount = UserActivityLog::where('created_at', '<', $cutoffDate)->delete();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} old activity logs.",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clean old logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clean old logs: ' . $e->getMessage()
            ], 500);
        }
    }
}
