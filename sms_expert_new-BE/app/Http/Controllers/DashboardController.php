<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ItaggProfilePending;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\IpAddressResstriction;
use App\Models\UsersSessionLog;

class DashboardController extends Controller
{
    protected $userBigId;

    public function index(Request $request)
    {
        $userInfo = Session::get('user_info');
        if (!isset($userInfo['contactname'])) {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }

        $user_contactname = $userInfo['contactname'];
        $bigid = $userInfo['bigid'];

        // Get user wallet info
        $user = User::where('bigid', $bigid)->first();
        if ($user) {
            $smsg_wallet = $user->smsg_wallet;
            $smsg_server1_sent = $user->smsg_server1_sent;
            $smsg_server2_sent = $user->smsg_server2_sent;
            $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;
        }

        // Security checks
        if ($user->ip_address_restriction == 1) {
            $currentIp = $request->ip();
            $ipAllowed = IpAddressResstriction::where('ip_address', $currentIp)
                ->where('bigid', $user->bigid)
                ->where('status', 1)
                ->exists();
            if ($ipAllowed == '1') {
                return redirect('/')->with('error', 'Your access has been denied as you are trying to access the system from a restricted IP address.');
            }
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account is disabled.');
        }

        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account is locked.');
        }

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        // Resolve + validate the date range server-side. The dates come from the URL query string
        // (start_date / end_date), so the browser pickers can be bypassed (e.g. a hand-edited URL
        // like ?start_date=2026-07-27&end_date=2026-07-25). resolveDateRange() safely parses them,
        // caps the end at yesterday, and guarantees start <= end. $maxDate is passed to the view so
        // the pickers also can't select beyond the data cutoff.
        [$startDate, $endDate, $maxDate] = $this->resolveDateRange($request);

        // Convert dates for database query (YmdHis format)
        $startDateDb = Carbon::parse($startDate)->format('Ymd') . '000000';
        $endDateDb = Carbon::parse($endDate)->format('Ymd') . '235959';

        // Get comprehensive SMS statistics
        $smsStats = $this->getSmsStatistics($bigid, $startDateDb, $endDateDb);

        // Get daily trends for charts
        $dailyTrends = $this->getDailyTrends($bigid, $startDate, $endDate);

        // Get monthly trends for comparison
        $monthlyTrends = $this->getMonthlyTrends($bigid);

        // Check tour status for first-time users
        $showCustomerTour = false;
        $userOption = DB::table('useroption')
            ->where('userref', $bigid)
            ->first();
        if ($userOption && !$userOption->customer_tour_completed) {
            $showCustomerTour = true;
        }

        // Once-per-day alert for migrated customers still using the old API.
        $oldApiAlert = $this->getOldApiUsageAlert($bigid);

        // Return the smart dashboard
        return view('dashboard', compact(
            'user_contactname',
            'remaining_wallet',
            'bigid',
            'smsStats',
            'dailyTrends',
            'monthlyTrends',
            'startDate',
            'endDate',
            'maxDate',
            'showCustomerTour',
            'oldApiAlert'
        ));
    }

    /**
     * Returns an alert message if this migrated customer is still using the old API,
     * but only once per calendar day. Reads the migrated_old_api_usage table that the
     * alert:old-api-usage cron populates. Returns null when there is nothing to show.
     */
    private function getOldApiUsageAlert($bigid): ?string
    {
        try {
            $row = DB::table('migrated_old_api_usage')
                ->where('user_bigid', $bigid)
                ->first();

            if (!$row || !$row->last_old_api_used_at) {
                return null;
            }

            // Only alert while old-API usage is recent (within the last 7 days).
            if (Carbon::parse($row->last_old_api_used_at)->lt(Carbon::now()->subDays(7))) {
                return null;
            }

            // Show only once per calendar day.
            $today = Carbon::today()->toDateString();
            if ($row->last_alert_shown_date === $today) {
                return null;
            }

            DB::table('migrated_old_api_usage')
                ->where('user_bigid', $bigid)
                ->update([
                    'last_alert_shown_date' => $today,
                    'updated_at' => now(),
                ]);

            return 'You are still sending SMS through the old API. Your account has been migrated to the new platform — please update your integration to the new API to avoid disruption.';
        } catch (\Throwable $e) {
            // Table may not exist yet (before migration) - fail silently.
            return null;
        }
    }

    /**
     * Get all smsg_log tables (smsg_log, smsg_log_2510, smsg_log_2511, etc.)
     */
    private function getSmsgLogTables()
    {
        $tables = DB::select("
            SELECT table_name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE table_name LIKE 'smsg_log%'
            AND TABLE_SCHEMA = DATABASE()
        ");

        return collect($tables)->map(function ($table) {
            return $table->table_name ?? $table->TABLE_NAME;
        })->toArray();
    }

    /**
     * Build consolidated UNION ALL query for all smsg_log tables
     */
    private function getConsolidatedQuery($bigid, $selectFields = '*', $conditions = [])
    {
        $tables = $this->getSmsgLogTables();
        
        $queries = collect($tables)->map(function ($tableName) use ($bigid, $selectFields, $conditions) {
            $query = "SELECT {$selectFields} FROM {$tableName} WHERE userref = '{$bigid}'";
            foreach ($conditions as $condition) {
                $query .= " AND {$condition}";
            }
            return $query;
        });

        return implode(' UNION ALL ', $queries->toArray());
    }

    private function getSmsStatistics($bigid, $startDate, $endDate)
    {
        // Build conditions for date range
        $dateConditions = [
            "timesent >= '{$startDate}'",
            "timesent <= '{$endDate}'"
        ];

        // ---- 4 dashboard cards: read ONLY from the pre-aggregated customer_daily_stats table ----
        // Populated nightly by customer:build-daily-stats; holds only COMPLETE days up to
        // YESTERDAY (today is still accumulating). No live smsg_log overlay — the cards are strictly
        // day-1 data straight from the table, so the range is capped at yesterday.
        // Card definitions (client spec): sent = COUNT(*); delivered = non-fail & delivered;
        // pending = non-fail & (pending/''); blocklist ("Block List") = sentstatus='fail'.
        $startDay  = Carbon::createFromFormat('YmdHis', $startDate)->toDateString();
        $endDayReq = Carbon::createFromFormat('YmdHis', $endDate)->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $endDay = ($endDayReq < $yesterday) ? $endDayReq : $yesterday; // never past yesterday

        $cards = DB::table('customer_daily_stats')
            ->where('users_bigid', $bigid)
            ->whereBetween('stat_date', [$startDay, $endDay])
            ->selectRaw('COALESCE(SUM(sent_count),0) AS sent, COALESCE(SUM(delivered_count),0) AS delivered, '
                . 'COALESCE(SUM(pending_count),0) AS pending, COALESCE(SUM(blocklist_count),0) AS blocklist')
            ->first();

        $totalSent = (int) ($cards->sent ?? 0);
        $delivered = (int) ($cards->delivered ?? 0);
        $pending   = (int) ($cards->pending ?? 0);
        $blocklist = (int) ($cards->blocklist ?? 0);
        $failed    = $blocklist; // backward-compat alias for existing view/consumers

        // Financial data
        $financialQuery = $this->getConsolidatedQuery(
            $bigid, 
            'SUM(userprice) as total_spent, SUM(costprice) as total_cost, SUM(profit) as total_profit, AVG(userprice) as avg_cost_per_sms', 
            $dateConditions
        );
        $financialResult = DB::select("
            SELECT 
                SUM(total_spent) as total_spent, 
                SUM(total_cost) as total_cost, 
                SUM(total_profit) as total_profit, 
                AVG(avg_cost_per_sms) as avg_cost_per_sms 
            FROM ({$financialQuery}) as combined
        ");
        $financialData = $financialResult[0] ?? null;

        // Today's stats for comparison
        $todayStart = Carbon::now()->format('Ymd') . '000000';
        $todayEnd = Carbon::now()->format('Ymd') . '235959';

        $todayConditions = [
            "timesent >= '{$todayStart}'",
            "timesent <= '{$todayEnd}'"
        ];

        $todayQuery = $this->getConsolidatedQuery(
            $bigid,
            "COUNT(*) as today_sent,
             SUM(CASE WHEN deliverystatus2 IN ('Delivered', 'delivered', 'ok') THEN 1 ELSE 0 END) as today_delivered,
             SUM(userprice) as today_spent",
            $todayConditions
        );
        $todayResult = DB::select("
            SELECT 
                SUM(today_sent) as today_sent, 
                SUM(today_delivered) as today_delivered, 
                SUM(today_spent) as today_spent 
            FROM ({$todayQuery}) as combined
        ");
        $todayStats = $todayResult[0] ?? null;

        // Calculate delivery rate
        $deliveryRate = $totalSent > 0 ? round(($delivered / $totalSent) * 100, 2) : 0;
        $blocklistRate = $totalSent > 0 ? round(($blocklist / $totalSent) * 100, 2) : 0;

        return [
            'total_sent' => $totalSent,
            'delivered' => $delivered,
            'pending' => $pending,
            'failed' => $failed,          // backward-compat alias
            'blocklist' => $blocklist,    // "Block List" card (was "Failed")
            'delivery_rate' => $deliveryRate,
            'blocklist_rate' => $blocklistRate,
            // Cards are strictly day-1 from customer_daily_stats (capped at yesterday). The view
            // shows a "Data up to <date>" note so customers know today is not included.
            'data_up_to' => $endDay,
            'total_spent' => $financialData->total_spent ?? 0,
            'total_cost' => $financialData->total_cost ?? 0,
            'total_profit' => $financialData->total_profit ?? 0,
            'avg_cost_per_sms' => $financialData->avg_cost_per_sms ?? 0,
            'today_sent' => $todayStats->today_sent ?? 0,
            'today_delivered' => $todayStats->today_delivered ?? 0,
            'today_spent' => $todayStats->today_spent ?? 0
        ];
    }

    private function getDailyTrends($bigid, $startDate, $endDate)
    {
        $startDateDb = Carbon::parse($startDate)->format('Ymd') . '000000';
        $endDateDb = Carbon::parse($endDate)->format('Ymd') . '235959';

        $conditions = [
            "timesent >= '{$startDateDb}'",
            "timesent <= '{$endDateDb}'"
        ];

        $selectFields = "
            DATE(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as date,
            COUNT(*) as total_sent,
            SUM(CASE WHEN deliverystatus2 IN ('Delivered', 'delivered', 'ok') THEN 1 ELSE 0 END) as delivered,
            SUM(userprice) as spent,
            SUM(costprice) as cost
        ";

        $unionQuery = $this->getConsolidatedQuery($bigid, $selectFields, $conditions);

        $data = DB::select("
            SELECT 
                date,
                SUM(total_sent) as total_sent,
                SUM(delivered) as delivered,
                SUM(spent) as spent,
                SUM(cost) as cost
            FROM ({$unionQuery}) as combined
            GROUP BY date
            ORDER BY date
        ");

        return collect($data);
    }

    private function getMonthlyTrends($bigid)
    {
        $currentYear = Carbon::now()->year;
        $startDateDb = $currentYear . '0101000000';
        $endDateDb = $currentYear . '1231235959';

        $conditions = [
            "timesent >= '{$startDateDb}'",
            "timesent <= '{$endDateDb}'"
        ];

        $selectFields = "
            DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m') as month,
            COUNT(*) as total_sent,
            SUM(CASE WHEN deliverystatus2 IN ('Delivered', 'delivered', 'ok') THEN 1 ELSE 0 END) as delivered,
            SUM(userprice) as spent
        ";

        $unionQuery = $this->getConsolidatedQuery($bigid, $selectFields, $conditions);

        $data = DB::select("
            SELECT 
                month,
                SUM(total_sent) as total_sent,
                SUM(delivered) as delivered,
                SUM(spent) as spent
            FROM ({$unionQuery}) as combined
            GROUP BY month
            ORDER BY month
        ");

        $dataCollection = collect($data);

        // Fill missing months with zero data
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthKey = $currentYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $monthData = $dataCollection->firstWhere('month', $monthKey);

            $months[] = [
                'month' => Carbon::createFromFormat('Y-m', $monthKey)->format('M'),
                'total_sent' => $monthData->total_sent ?? 0,
                'delivered' => $monthData->delivered ?? 0,
                'spent' => $monthData->spent ?? 0
            ];
        }

        return $months;
    }

    // API endpoints for AJAX requests
    /**
     * Resolve and VALIDATE the dashboard date range from the request.
     *
     * The dates arrive as raw query-string params (start_date / end_date), e.g.
     *   /dashboard?start_date=2026-07-27&end_date=2026-07-25
     * so the browser date-pickers can be bypassed entirely (hand-edited URL). ALL validation must
     * therefore happen here, server-side:
     *   - unparseable / garbage dates fall back to a safe default (never throw a 500);
     *   - neither date may be after "yesterday" (dashboard data is complete-days-only, up to yesterday);
     *   - the range must be start <= end. A reversed range (e.g. start=27, end=25) is SWAPPED so it
     *     stays a valid range instead of silently collapsing to a single day.
     *
     * @return array{0:string,1:string,2:string} [startDate 'Y-m-d', endDate 'Y-m-d', maxDate 'Y-m-d']
     */
    private function resolveDateRange(Request $request): array
    {
        $maxDate = Carbon::yesterday()->format('Y-m-d');

        $parse = function ($value, string $default): string {
            try {
                return $value ? Carbon::parse($value)->format('Y-m-d') : $default;
            } catch (\Throwable $e) {
                return $default; // garbage input -> safe default, no 500
            }
        };

        $startDate = $parse($request->input('start_date'), Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $parse($request->input('end_date'),   Carbon::now()->endOfMonth()->format('Y-m-d'));

        // ISO yyyy-mm-dd strings compare chronologically, so string comparison is safe here.
        // Neither date may exceed the data cutoff (yesterday).
        if ($startDate > $maxDate) { $startDate = $maxDate; }
        if ($endDate   > $maxDate) { $endDate   = $maxDate; }

        // Reversed range (start after end) -> swap so it is always a valid start <= end range.
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate, $maxDate];
    }

    public function getDashboardStats(Request $request)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'];

        // Validate the URL-supplied date range server-side (see resolveDateRange()).
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $startDateDb = Carbon::parse($startDate)->format('Ymd') . '000000';
        $endDateDb = Carbon::parse($endDate)->format('Ymd') . '235959';

        $smsStats = $this->getSmsStatistics($bigid, $startDateDb, $endDateDb);

        return response()->json($smsStats);
    }

    public function getDashboardCharts(Request $request)
    {
        $userInfo = Session::get('user_info');
        $bigid = $userInfo['bigid'];

        // Validate the URL-supplied date range server-side (see resolveDateRange()).
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $dailyTrends = $this->getDailyTrends($bigid, $startDate, $endDate);
        $monthlyTrends = $this->getMonthlyTrends($bigid);

        return response()->json([
            'daily' => $dailyTrends,
            'monthly' => $monthlyTrends
        ]);
    }
}
