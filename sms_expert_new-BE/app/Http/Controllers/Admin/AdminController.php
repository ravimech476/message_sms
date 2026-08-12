<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

class AdminController extends Controller
{
    public function index(Request $request)
    {
        #### New Admin Dashboard ####
        $userInfo = Session::get('user_info');
        if (isset($userInfo['contactname'])) {
            $user_contactname = Session::get('user_info')['contactname'];
            $bigid = Session::get('user_info')['bigid'];
            // $user = User::with('reminders')->where('bigid', $bigid)->first();
            $user = User::where('bigid', $bigid)->first();
            if ($user) {
                $smsg_wallet = $user->smsg_wallet;
                $smsg_server1_sent = $user->smsg_server1_sent;
                $smsg_server2_sent = $user->smsg_server2_sent;

                $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;
            }

            // $pendingProfile = ItaggProfilePending::where('bigid', $user->bigid)->first();

            // Get selected year and month from request, default to current
            $selectedYear = $request->get('year', now()->year);
            $selectedMonth = $request->get('month', now()->format('m'));

            $allQuery = $this->getMonthlySmsSummary($selectedYear, $selectedMonth);

            // Use sent_count from allQuery (filtered by selected month/year)
            $totalSentCount = $allQuery->sent_count ?? 0;

            // Available years for dropdown (last 3 years)
            $availableYears = range(now()->year, now()->year - 2);

            // Dashboard stats are pre-computed by the daily midnight cron, so the
            // latest complete day of data is yesterday.
            $dataTillDate = Carbon::yesterday()->format('jS M Y');

            return view('admin.admin_dashboard', compact(
                'user_contactname',
                'remaining_wallet',
                'bigid',
                'allQuery',
                'totalSentCount',
                'selectedYear',
                'selectedMonth',
                'availableYears',
                'dataTillDate'
            ));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        Session::flush();

        return redirect('/');
    }

    /**
     * Get SMS summary data for AJAX requests (dashboard cards)
     */
    public function getSmsSummary(Request $request)
    {
        $selectedYear = $request->query('year', now()->year);
        $selectedMonth = $request->query('month', now()->format('m'));

        $summary = $this->getMonthlySmsSummary($selectedYear, $selectedMonth);

        return response()->json([
            'success' => true,
            'data' => [
                'sent_count' => number_format($summary->sent_count ?? 0),
                'delivered_count' => number_format($summary->delivered_count ?? 0),
                'total_profit' => number_format($summary->total_profit ?? 0, 4),
                'total_costprice' => number_format($summary->total_costprice ?? 0, 4),
                'total_userprice' => number_format($summary->total_userprice ?? 0, 4),
            ],
            'year' => $selectedYear,
            'month' => $selectedMonth,
        ]);
    }

    ### SMS Delivered Counts ###

    public function getDailyCountsAdmin(Request $request)
    {
        $selectedYear = $request->query('year', Carbon::now()->year);

        $selectedMonth = $request->query('month', Carbon::now()->month);

        $data = $this->getDailyDeliveredSmsCount($selectedYear, $selectedMonth);


        // $data = DB::table('smsg_log')
        //     ->selectRaw("DATE(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as date, COUNT(*) as total")
        //     ->where('sentstatus', 'ok')
        //     ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
        //     ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
        //     ->groupBy('date')
        //     ->orderBy('date')
        //     ->get();

        // Convert to a key-value pair (day as key)
        $result = [];
        foreach ($data as $row) {
            $day = (int)Carbon::parse($row->date)->format('d'); // Extract day
            $result[$day] = (int)$row->total;
        }

        // Fill missing days with zero
        $daysInMonth = Carbon::create($selectedYear, $selectedMonth, 1)->daysInMonth;
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $result[$i] = $result[$i] ?? 0; // Default to 0 if no data for the day
        }

        // Return as a JSON response
        return response()->json($result);
    }

    ### SMS Profit ###
    public function getMonthlySmsProfitAdmin(Request $request)
    {
        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);

        $data = $this->getMonthlySmsProfitByDay($selectedYear, $selectedMonth);


        // $data = DB::table('smsg_log')
        //     ->selectRaw("
        //     DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
        //     SUM(profit) as total_profit
        // ")
        //     ->where('sentstatus', 'ok')
        //     ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
        //     ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
        //     ->groupBy('day')
        //     ->orderBy('day')
        //     ->get();

        // Format the response with profit totals for each day
        $daysInMonth = Carbon::create($selectedYear, $selectedMonth)->daysInMonth;
        $profits = array_fill(1, $daysInMonth, 0); // Initialize all days with 0 profit

        foreach ($data as $record) {
            $profits[$record->day] = (float) $record->total_profit;
        }

        return response()->json([
            'categories' => array_keys($profits),
            'data' => array_values($profits)
        ]);
    }

    ### SMS Cost ###
    public function getMonthlySmsCostAdmin(Request $request)
    {

        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);

        $data = $this->getMonthlySmsCostByDay($selectedYear, $selectedMonth);

        // $data = DB::table('smsg_log')
        //     ->selectRaw("
        //     DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
        //     SUM(costprice) as total_cost
        // ")
        //     ->where('sentstatus', 'ok')
        //     ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
        //     ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
        //     ->groupBy('day')
        //     ->orderBy('day')
        //     ->get();

        // Format the response with profit totals for each day
        $daysInMonth = Carbon::create($selectedYear, $selectedMonth)->daysInMonth;
        $profits = array_fill(1, $daysInMonth, 0); // Initialize all days with 0 profit

        foreach ($data as $record) {
            $profits[$record->day] = (float) $record->total_cost;
        }

        return response()->json([
            'categories' => array_keys($profits),
            'data' => array_values($profits)
        ]);
    }

    ### SMS User Price ###
    public function getMonthlySmsUserPriceAdmin(Request $request)
    {
        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);

        $data = $this->getMonthlySmsUserPriceByDay($selectedYear, $selectedMonth);

        // $data = DB::table('smsg_log')
        //     ->selectRaw("
        //     DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
        //     SUM(userprice) as user_price
        // ")
        //     ->where('sentstatus', 'ok')
        //     ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
        //     ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
        //     ->groupBy('day')
        //     ->orderBy('day')
        //     ->get();

        // Format the response with profit totals for each day
        $daysInMonth = Carbon::create($selectedYear, $selectedMonth)->daysInMonth;
        $profits = array_fill(1, $daysInMonth, 0); // Initialize all days with 0 profit

        foreach ($data as $record) {
            $profits[$record->day] = (float) $record->user_price;
        }

        return response()->json([
            'categories' => array_keys($profits),
            'data' => array_values($profits)
        ]);
    }

    ### Total month SMS Count ###
    public function getMonthlyCountsAdmin(Request $request)
    {

        ### Year Selected Based Controll
        $year = $request->input('year', now()->year); // Default to the current year if no year is selected


        $data = $this->getYearlySmsCountByMonth($year);

        // $data = DB::table('smsg_log')
        //     ->selectRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month, COUNT(*) as total")
        //     ->where('sentstatus', 'ok')
        //     ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $year) // Filter by selected year
        //     ->groupByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b'), DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
        //     ->orderByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
        //     ->get();

        // Initialize an array with 12 months set to 0
        $chartData = array_fill(0, 12, 0);
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        foreach ($data as $item) {
            $index = array_search($item->month, $months);
            if ($index !== false) {
                $chartData[$index] = $item->total;
            }
        }

        return response()->json($chartData);
    }

    ### Admin Header Monthly Counts (reads pre-computed dashboard_daily_stats) ###
    private function getMonthlySmsSummary($year, $month)
    {
        $row = DB::table('dashboard_daily_stats')
            ->whereYear('stat_date', $year)
            ->whereMonth('stat_date', $month)
            ->selectRaw('
                COALESCE(SUM(acked_sent_count), 0)      AS total_count,
                COALESCE(SUM(acked_profit), 0)          AS total_profit,
                COALESCE(SUM(acked_costprice), 0)       AS total_costprice,
                COALESCE(SUM(acked_userprice), 0)       AS total_userprice,
                COALESCE(SUM(acked_sent_count), 0)      AS sent_count,
                COALESCE(SUM(acked_delivered_count), 0) AS delivered_count
            ')
            ->first();

        return $row ?: (object) [
            'total_count'     => 0,
            'total_profit'    => 0,
            'total_costprice' => 0,
            'total_userprice' => 0,
            'sent_count'      => 0,
            'delivered_count' => 0,
        ];
    }

    /**
     * Get total sent SMS count (all time, no date filter)
     */
    private function getTotalSentCount()
    {
        return (int) DB::table('dashboard_daily_stats')->sum('acked_sent_count');
    }


    ### SMS Delivered Chart Query###

    private function getDailyDeliveredSmsCount($year, $month)
    {
        return DB::table('dashboard_daily_stats')
            ->whereYear('stat_date', $year)
            ->whereMonth('stat_date', $month)
            ->selectRaw('stat_date AS date, ok_sent_count AS total')
            ->orderBy('stat_date')
            ->get();
    }

    ### SMS Profit Chart Query ###

    private function getMonthlySmsProfitByDay($year, $month)
    {
        return DB::table('dashboard_daily_stats')
            ->whereYear('stat_date', $year)
            ->whereMonth('stat_date', $month)
            ->selectRaw('DAY(stat_date) AS day, ok_profit AS total_profit')
            ->orderBy('stat_date')
            ->get();
    }

    ### SMS Cost Chart Query ###
    private function getMonthlySmsCostByDay($year, $month)
    {
        return DB::table('dashboard_daily_stats')
            ->whereYear('stat_date', $year)
            ->whereMonth('stat_date', $month)
            ->selectRaw('DAY(stat_date) AS day, ok_costprice AS total_cost')
            ->orderBy('stat_date')
            ->get();
    }

    ### SMS User Price Chart Query ###
    private function getMonthlySmsUserPriceByDay($year, $month)
    {
        return DB::table('dashboard_daily_stats')
            ->whereYear('stat_date', $year)
            ->whereMonth('stat_date', $month)
            ->selectRaw('DAY(stat_date) AS day, ok_userprice AS user_price')
            ->orderBy('stat_date')
            ->get();
    }

     ### Total Month SMS Count Bar Query ###
    private function getYearlySmsCountByMonth($year)
    {
        return DB::table('dashboard_daily_stats')
            ->whereYear('stat_date', $year)
            ->selectRaw("
                DATE_FORMAT(stat_date, '%b') AS month,
                DATE_FORMAT(stat_date, '%Y-%m') AS month_order,
                SUM(ok_row_count) AS total
            ")
            ->groupByRaw("DATE_FORMAT(stat_date, '%b'), DATE_FORMAT(stat_date, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(stat_date, '%Y-%m')")
            ->get();
    }
}
