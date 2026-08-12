<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\User;

/**
 * Mobile App Dashboard Controller
 * 
 * Provides dashboard data for the mobile application
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard overview data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Calculate wallet balance
            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

            // Get date range from request or default to current month
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            
            // Parse dates or use defaults
            if ($startDate && $endDate) {
                try {
                    $dateFrom = Carbon::parse($startDate)->startOfDay();
                    $dateTo = Carbon::parse($endDate)->endOfDay();
                } catch (\Exception $e) {
                    // If invalid dates, fall back to current month
                    $dateFrom = now()->startOfMonth();
                    $dateTo = now()->endOfDay();
                }
            } else {
                // Default to current month
                $dateFrom = now()->startOfMonth();
                $dateTo = now()->endOfDay();
            }

            // Determine period label
            $periodLabel = $this->getPeriodLabel($dateFrom, $dateTo);

            // Get stats for the date range
            $periodStats = DB::table('smsg_log')
                ->selectRaw("
                    COUNT(*) as total_count,
                    SUM(CASE WHEN LOWER(deliverystatus2) = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                    SUM(CASE WHEN LOWER(deliverystatus2) IN ('failed', 'undelivered', 'rejected', 'expired') THEN 1 ELSE 0 END) as failed_count,
                    SUM(COALESCE(profit, 0)) as total_profit,
                    SUM(COALESCE(costprice, 0)) as total_cost,
                    SUM(COALESCE(userprice, 0)) as total_userprice
                ")
                ->where('sentstatus', 'ok')
                ->where('userref', $bigid)
                ->where(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), '>=', $dateFrom)
                ->where(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), '<=', $dateTo)
                ->first();

            // Get today's stats (always show today regardless of filter)
            $today = now()->format('Ymd');
            $todayStats = DB::table('smsg_log')
                ->selectRaw("
                    COUNT(*) as total_count,
                    SUM(CASE WHEN LOWER(deliverystatus2) = 'delivered' THEN 1 ELSE 0 END) as delivered_count
                ")
                ->where('sentstatus', 'ok')
                ->where('userref', $bigid)
                ->where(DB::raw("SUBSTRING(timesent, 1, 8)"), $today)
                ->first();

            // Get recent activity within the date range (last 5 SMS)
            $recentActivityQuery = DB::table('smsg_log')
                ->select('id', 'mobnum', 'text', 'deliverystatus2', 'timesent')
                ->where('userref', $bigid);
            
            // Apply date filter to recent activity if custom date range is specified
            if ($startDate && $endDate) {
                $recentActivityQuery
                    ->where(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), '>=', $dateFrom)
                    ->where(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), '<=', $dateTo);
            }
            
            $recentActivity = $recentActivityQuery
                ->orderBy('timesent', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'recipient' => $this->maskPhoneNumber($item->mobnum),
                        'message' => substr($item->text ?? '', 0, 50) . (strlen($item->text ?? '') > 50 ? '...' : ''),
                        'status' => $this->formatStatus($item->deliverystatus2),
                        'sent_at' => $this->formatTimestamp($item->timesent),
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'wallet' => [
                        'balance' => round($walletBalance, 2),
                        'total_wallet' => round($user->smsg_wallet, 2),
                        'used' => round($user->smsg_server1_sent + $user->smsg_server2_sent, 2),
                    ],
                    'filter' => [
                        'start_date' => $dateFrom->format('Y-m-d'),
                        'end_date' => $dateTo->format('Y-m-d'),
                        'period_label' => $periodLabel,
                        'is_custom' => ($startDate && $endDate) ? true : false,
                    ],
                    'period_stats' => [
                        'total_sms' => (int) ($periodStats->total_count ?? 0),
                        'delivered' => (int) ($periodStats->delivered_count ?? 0),
                        'failed' => (int) ($periodStats->failed_count ?? 0),
                        'delivery_rate' => $periodStats->total_count > 0 
                            ? round(($periodStats->delivered_count / $periodStats->total_count) * 100, 1) 
                            : 0,
                        'total_cost' => round($periodStats->total_userprice ?? 0, 2),
                    ],
                    'today' => [
                        'total_sms' => (int) ($todayStats->total_count ?? 0),
                        'delivered' => (int) ($todayStats->delivered_count ?? 0),
                    ],
                    'recent_activity' => $recentActivity,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Dashboard Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load dashboard data'
            ], 500);
        }
    }

    /**
     * Get period label based on date range
     */
    private function getPeriodLabel(Carbon $dateFrom, Carbon $dateTo): string
    {
        $now = now();
        
        // Check if it's today
        if ($dateFrom->isSameDay($dateTo) && $dateFrom->isSameDay($now)) {
            return 'Today';
        }
        
        // Check if it's yesterday
        if ($dateFrom->isSameDay($dateTo) && $dateFrom->isSameDay($now->copy()->subDay())) {
            return 'Yesterday';
        }
        
        // Check if it's current month
        if ($dateFrom->isSameMonth($now) && $dateTo->isSameMonth($now) && 
            $dateFrom->day === 1 && $dateTo->isSameDay($now)) {
            return 'This Month';
        }
        
        // Check if it's last 7 days
        if ($dateFrom->diffInDays($dateTo) === 6 && $dateTo->isSameDay($now)) {
            return 'Last 7 Days';
        }
        
        // Check if it's last 30 days
        if ($dateFrom->diffInDays($dateTo) === 29 && $dateTo->isSameDay($now)) {
            return 'Last 30 Days';
        }
        
        // Check if it's a specific month
        if ($dateFrom->day === 1 && $dateTo->day === $dateTo->daysInMonth && 
            $dateFrom->isSameMonth($dateTo)) {
            return $dateFrom->format('F Y');
        }
        
        // Custom date range
        return $dateFrom->format('d M') . ' - ' . $dateTo->format('d M Y');
    }

    /**
     * Get wallet balance
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function wallet(Request $request)
    {
        try {
            $user = $request->user();

            // Refresh user data
            $user = User::where('id', $user->id)->first();

            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

            return response()->json([
                'status' => true,
                'message' => 'Wallet data retrieved successfully',
                'data' => [
                    'balance' => round($walletBalance, 2),
                    'total_wallet' => round($user->smsg_wallet, 2),
                    'server1_sent' => round($user->smsg_server1_sent, 2),
                    'server2_sent' => round($user->smsg_server2_sent, 2),
                    'total_used' => round($user->smsg_server1_sent + $user->smsg_server2_sent, 2),
                    'currency' => 'GBP',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Wallet Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load wallet data'
            ], 500);
        }
    }

    /**
     * Get SMS statistics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Support both period-based and custom date range
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $period = $request->get('period', 'month'); // day, week, month, year
            
            if ($startDate && $endDate) {
                try {
                    $dateFrom = Carbon::parse($startDate)->startOfDay();
                    $dateTo = Carbon::parse($endDate)->endOfDay();
                } catch (\Exception $e) {
                    $dateFrom = $this->getDateFrom($period);
                    $dateTo = now();
                }
            } else {
                $dateFrom = $this->getDateFrom($period);
                $dateTo = now();
            }

            $stats = DB::table('smsg_log')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN LOWER(deliverystatus2) = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN LOWER(deliverystatus2) IN ('failed', 'undelivered', 'rejected', 'expired') THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN deliverystatus2 IS NULL OR deliverystatus2 = '' OR LOWER(deliverystatus2) IN ('pending', 'buffered', 'accepted') THEN 1 ELSE 0 END) as pending,
                    SUM(COALESCE(userprice, 0)) as total_cost,
                    SUM(COALESCE(profit, 0)) as total_profit
                ")
                ->where('sentstatus', 'ok')
                ->where('userref', $bigid)
                ->where(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), '>=', $dateFrom)
                ->where(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), '<=', $dateTo)
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'period' => ($startDate && $endDate) ? 'custom' : $period,
                    'from_date' => $dateFrom->format('Y-m-d'),
                    'to_date' => $dateTo->format('Y-m-d'),
                    'stats' => [
                        'total' => (int) ($stats->total ?? 0),
                        'delivered' => (int) ($stats->delivered ?? 0),
                        'failed' => (int) ($stats->failed ?? 0),
                        'pending' => (int) ($stats->pending ?? 0),
                        'delivery_rate' => $stats->total > 0 
                            ? round(($stats->delivered / $stats->total) * 100, 1) 
                            : 0,
                        'total_cost' => round($stats->total_cost ?? 0, 2),
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Stats Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load statistics'
            ], 500);
        }
    }

    /**
     * Get monthly SMS counts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyCounts(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $year = $request->get('year', now()->year);

            $data = DB::table('smsg_log')
                ->selectRaw("
                    DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month,
                    MONTH(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as month_num,
                    COUNT(*) as total,
                    SUM(CASE WHEN LOWER(deliverystatus2) = 'delivered' THEN 1 ELSE 0 END) as delivered
                ")
                ->where('sentstatus', 'ok')
                ->where('userref', $bigid)
                ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $year)
                ->groupByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b'), MONTH(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'))")
                ->orderBy('month_num')
                ->get();

            // Initialize all months with 0
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            
            foreach ($months as $index => $month) {
                $chartData[$month] = [
                    'total' => 0,
                    'delivered' => 0,
                ];
            }

            // Fill in actual data
            foreach ($data as $item) {
                $chartData[$item->month] = [
                    'total' => (int) $item->total,
                    'delivered' => (int) $item->delivered,
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Monthly counts retrieved successfully',
                'data' => [
                    'year' => (int) $year,
                    'months' => $months,
                    'totals' => array_column(array_values($chartData), 'total'),
                    'delivered' => array_column(array_values($chartData), 'delivered'),
                    'detailed' => $chartData,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Monthly Counts Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load monthly counts'
            ], 500);
        }
    }

    /**
     * Get daily SMS counts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailyCounts(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $year = $request->get('year', now()->year);
            $month = $request->get('month', now()->month);

            $data = DB::table('smsg_log')
                ->selectRaw("
                    DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
                    COUNT(*) as total,
                    SUM(CASE WHEN LOWER(deliverystatus2) = 'delivered' THEN 1 ELSE 0 END) as delivered
                ")
                ->where('sentstatus', 'ok')
                ->where('userref', $bigid)
                ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $year)
                ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $month)
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            // Initialize all days with 0
            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
            $dailyData = [];
            
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $dailyData[$i] = [
                    'total' => 0,
                    'delivered' => 0,
                ];
            }

            // Fill in actual data
            foreach ($data as $item) {
                $dailyData[$item->day] = [
                    'total' => (int) $item->total,
                    'delivered' => (int) $item->delivered,
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Daily counts retrieved successfully',
                'data' => [
                    'year' => (int) $year,
                    'month' => (int) $month,
                    'month_name' => Carbon::create($year, $month, 1)->format('F'),
                    'days' => array_keys($dailyData),
                    'totals' => array_column(array_values($dailyData), 'total'),
                    'delivered' => array_column(array_values($dailyData), 'delivered'),
                    'detailed' => $dailyData,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Daily Counts Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load daily counts'
            ], 500);
        }
    }

    /**
     * Get date from based on period
     */
    private function getDateFrom(string $period): Carbon
    {
        return match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
    }

    /**
     * Format timestamp from database format
     */
    private function formatTimestamp(?string $timestamp): ?string
    {
        if (!$timestamp) return null;
        
        try {
            return Carbon::createFromFormat('YmdHis', $timestamp)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Mask phone number for privacy
     */
    private function maskPhoneNumber(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 6) return $phone;
        
        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
    }

    /**
     * Format status for display
     */
    private function formatStatus(?string $status): string
    {
        if (!$status) return 'PENDING';
        
        $status = strtolower($status);
        
        return match($status) {
            'delivered' => 'DELIVERED',
            'failed', 'undelivered', 'rejected' => 'FAILED',
            'expired' => 'EXPIRED',
            'pending', 'buffered', 'accepted' => 'PENDING',
            default => strtoupper($status),
        };
    }
}
