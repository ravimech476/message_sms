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
    public function index()
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

            // Get the current year and month
            $currentYear = now()->year;
            $currentMonth = now()->format('m');

            $allQuery = DB::table('smsg_log')
                ->selectRaw("
                    COUNT(*) as total_count,
                    SUM(profit) as total_profit,
                    SUM(costprice) as total_costprice,
                    SUM(userprice) as total_userprice
                    ")
                ->where('sentstatus', 'ok')
                ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $currentYear)
                ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $currentMonth)
                ->first();

            return view('admin.admin_dashboard', compact('user_contactname', 'remaining_wallet', 'bigid', 'allQuery'));
                // Additional data for modern dashboard
                $totalUsers = User::count();
                $activeUsers = User::where('status', 'active')->count();
                $todayStats = [
                    'sms_sent' => DB::table('smsg_log')
                        ->where('sentstatus', 'ok')
                        ->whereDate(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), today())
                        ->count(),
                ];
                $unreadNotifications = 0; // Placeholder for notifications
                $outstandingInvoices = 0; // Placeholder for outstanding invoices

                return view('admin.dashboard.modern-index', compact(
                    'user_contactname', 
                    'remaining_wallet', 
                    'bigid', 
                    'allQuery',
                    'totalUsers',
                    'activeUsers',
                    'todayStats',
                    'unreadNotifications',
                    'outstandingInvoices'
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

    public function getDailyCountsAdmin(Request $request)
    {
        $selectedYear = $request->query('year', Carbon::now()->year);

        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("DATE(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as date, COUNT(*) as total")
            ->where('sentstatus', 'ok')
            ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
            ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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

    public function getMonthlySmsProfitAdmin(Request $request)
    {
        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("
            DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
            SUM(profit) as total_profit
        ")
            ->where('sentstatus', 'ok')
            ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
            ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

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

    public function getMonthlySmsCostAdmin(Request $request)
    {

        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("
            DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
            SUM(costprice) as total_cost
        ")
            ->where('sentstatus', 'ok')
            ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
            ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

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

    public function getMonthlySmsUserPriceAdmin(Request $request)
    {
        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("
            DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
            SUM(userprice) as user_price
        ")
            ->where('sentstatus', 'ok')
            ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedYear)
            ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $selectedMonth)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

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

    public function getMonthlyCountsAdmin(Request $request)
    {

        ### Year Selected Based Controll
        $year = $request->input('year', now()->year); // Default to the current year if no year is selected

        $data = DB::table('smsg_log')
            ->selectRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month, COUNT(*) as total")
            ->where('sentstatus', 'ok')
            ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $year) // Filter by selected year
            ->groupByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b'), DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
            ->get();

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
}
