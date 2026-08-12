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
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;



class LoginController extends Controller
{
    // public function showLoginForm()
    // {
    //     return view('auth.login');
    // }

    public function showLoginForm()
    {
        $host = strtolower(request()->getHost());

        $customerDomains = config('domains.customer');
        $adminDomains    = config('domains.admin');
        $campaignDomains    = config('domains.campaign');

        if (in_array($host, $customerDomains)) {
            return view('auth.customer-login');
        } elseif (in_array($host, $adminDomains)) {
            // return view('auth.admin-login');
            return view('admin.auth.login');
        } elseif (in_array($host, $campaignDomains)) {
            return view('campaign.auth.login');
        }

        abort(404, 'Domain not recognized');
    }

    public function login(Request $request)
    {
        try {

            $validate = Validator::make($request->all(), [
                'userName' => 'required',
                'password' => 'required'
            ]);

            if ($validate->fails()) {
                return response()->json(['status' => false, 'message' => 'Validation Error', 'errors' => $validate->errors()], 403);
            }

            $user = User::where('uname', $request->userName)->first();

            if ($user && $user->pword === $request->password) {

                // Get host & domain rules
                $currentHost      = $request->getHost();
                $customerDomains  = config('domains.customer');
                $adminDomains     = config('domains.admin');
                $campaignDomains    = config('domains.campaign');

                //  Host-based login type restriction
                if (in_array($currentHost, $adminDomains)) {
                    if ($user->login_type !== 'admin') {
                        return back()->withErrors([
                            'userName' => 'Access denied. Admin credentials required.'
                        ]);
                    }
                } elseif (in_array($currentHost, $customerDomains)) {
                    if (!is_null($user->login_type) && $user->login_type !== 'customer') {
                        return back()->withErrors([
                            'userName' => 'Access denied. Dashboard credentials required.'
                        ]);
                    }
                }

                if ($user->migration_flag === 'old') {
                    // Build redirect URL with credentials for old system
                    $oldSystemUrl = config('domains.old_sms_expert_dashboard_url');
                    $redirectUrl = str_replace(
                        ['{username}', '{password}'],
                        [urlencode($user->uname), urlencode($request->password)],
                        $oldSystemUrl
                    );
                    return redirect()->away($redirectUrl);
                }

                //  IP restriction
                if ($user->ip_address_restriction == 1) {
                    $currentIp = $request->ip();
                    $ipAllowed = IpAddressResstriction::where('ip_address', $currentIp)
                        ->where('bigid', $user->bigid)
                        ->where('status', 1)
                        ->exists();

                    if ($ipAllowed == '1') {
                        return redirect('/')->with('error', 'Your access has been denied as you are trying to access the system from a restricted IP address. Please log in from an approved IP address.');
                    }
                }

                if ($user->bit_disabled == 1) {
                    return redirect('/')->with('error', 'Account is disabled.');
                }

                Session::put('user_info', [
                    'contactname' => $user->contactname,
                    'bigid' => $user->bigid,
                    'username' => $user->uname,
                    'login_type' => $user->login_type,
                ]);

                Auth::login($user);

                // ✅ Lockout checks
                $lockoutStatus = DB::table('useroption')
                    ->where('userref', $user->bigid)
                    ->select('profileupdate_lockout', 'clientcommfail')
                    ->first();

                if ($lockoutStatus->profileupdate_lockout == '1') {
                    return redirect()->route('profile.lock');
                }

                if ($lockoutStatus->clientcommfail == 'y') {
                    return redirect('/')->with('error', 'Account is locked.');
                }

                // ✅ Log customer session
                if ($user->login_type != 'admin') {
                    $timestamp = Carbon::now('Europe/London')->format('YmdHis');
                    UsersSessionLog::create([
                        'big_id'       => $user->bigid,
                        'ip_address'   => $request->ip(),
                        'itaggcustid'  => $user->bigid,
                        'status'       => 0,
                        'login_date'   => $timestamp,
                    ]);

                    // ✅ Check maintenance mode for customers
                    $maintenanceCheck = $this->checkMaintenanceMode($user);
                    if ($maintenanceCheck['is_maintenance']) {
                        // Store maintenance info in session
                        Session::put('maintenance_mode', [
                            'enabled' => true,
                            'message' => $maintenanceCheck['message'],
                            'end_time' => $maintenanceCheck['end_time'],
                        ]);
                        return redirect()->route('customer.maintenance');
                    }
                }

                // ✅ Redirect based on user type
                return redirect()->intended($user->login_type === 'admin' ? '/admin/dashboard' : '/dashboard');
            } else {
                return back()->withErrors([
                    'userName' => 'User name and Password do not match our records.',
                ]);
            }
        } catch (\Throwable $ex) {
            \Log::error('Customer login failed', [
                'username' => $request->input('userName'),
                'ip'       => $request->ip(),
                'host'     => $request->getHost(),
                'error'    => $ex->getMessage(),
                'file'     => $ex->getFile(),
                'line'     => $ex->getLine(),
                'trace'    => $ex->getTraceAsString(),
            ]);

            return back()->withErrors([
                'userName' => 'Something went wrong. Please contact the administrator.',
            ])->withInput();
        }
    }



    // public function login(Request $request)
    // {
    //     try {
    //         $validate = Validator::make($request->all(), [
    //             'userName' => 'required',
    //             'password' => 'required'
    //         ]);

    //         if ($validate->fails()) {
    //             return response()->json(['status' => false, 'message' => 'Validation Error', 'errors' => $validate->errors()], 403);
    //         }

    //         $user = User::where('uname', $request->userName)->first();

    //         if ($user && $user->pword === $request->password) {

    //             //  Check IP address restriction flag on user
    //             if ($user->ip_address_restriction == 1) {

    //                 $currentIp = $request->ip();

    //                 $ipAllowed = IpAddressResstriction::where('ip_address', $currentIp)
    //                     ->where('bigid', $user->bigid)
    //                     ->where('status', 1)
    //                     ->exists();
    //                 if ($ipAllowed == '1') {
    //                     return redirect('/')->with('error', 'Your access has been denied as you are trying to access the system from a restricted IP address. Please log in from an approved IP address.');
    //                 }
    //             }

    //             if ($user->bit_disabled == 1) {
    //                 return redirect('/')->with('error', 'Account is disabled.');
    //             }

    //             Session::put('user_info', [
    //                 'contactname' => $user->contactname,
    //                 'bigid' => $user->bigid,
    //                 'username' => $user->uname,
    //                 'login_type' => $user->login_type,
    //             ]);

    //             Auth::login($user);

    //             // Check for pending profile updates
    //             $pendingProfile = ItaggProfilePending::where('bigid', $user->bigid)->first();

    //             // if ($pendingProfile) {
    //             //     return redirect()->route('profile.lock');
    //             // }

    //             $lockoutStatus = DB::table('useroption')
    //                 ->where('userref', $user->bigid)
    //                 ->select('profileupdate_lockout', 'clientcommfail')
    //                 ->first();

    //             if ($lockoutStatus->profileupdate_lockout == '1') {
    //                 return redirect()->route('profile.lock');
    //             }

    //             if ($lockoutStatus->clientcommfail == 'y') {
    //                 return redirect('/')->with('error', 'Account is locked.');
    //             }


    //             if ($user->login_type != 'admin') {

    //                 $timestamp = Carbon::now('Europe/London')->format('YmdHis');

    //                 UsersSessionLog::create([
    //                     'big_id'       => $user->bigid,
    //                     'ip_address'   => $request->ip(),
    //                     'itaggcustid'  => $user->bigid,
    //                     'status'       => 0,
    //                     'login_date'   => $timestamp,
    //                 ]);
    //             }


    //             // ✅ Redirect based on user type
    //             return redirect()->intended($user->login_type === 'admin' ? '/admin/dashboard' : '/dashboard');
    //         } else {
    //             return back()->withErrors([
    //                 'userName' => 'User name and Password do not match our records.',
    //             ]);
    //         }
    //     } catch (\Throwable $ex) {
    //         return back()->withErrors([
    //             'userName' => 'Please check the database connection',
    //         ]);
    //     }
    // }

    // below code before ip restriction code
    // public function login(Request $request)
    // {
    //     try {
    //         $validate = Validator::make($request->all(), [
    //             'userName' => 'required',
    //             'password' => 'required'
    //         ]);

    //         if ($validate->fails()) {
    //             return response()->json(['status' => false, 'message' => 'validation Error', 'errors' => $validate->errors()], 403);
    //         }
    //         $user = User::where('uname', $request->userName)->first();

    //         if ($user && $user->pword === $request->password) {
    //             Session::put('user_info', [
    //                 'contactname' => $user->contactname,
    //                 'bigid' => $user->bigid,
    //                 'username' => $user->uname,
    //                 'login_type' => $user->login_type, // Store user type in session
    //             ]);

    //             Auth::login($user); // Ensure the user is authenticated
    //             // session()->regenerate(); // Prevent session loss

    //             // Check if there is a pending profile update
    //             $pendingProfile = ItaggProfilePending::where('bigid', $user->bigid)->first();

    //             if ($pendingProfile) {
    //                 // Redirect to profile lock page
    //                 return redirect()->route('profile.lock');
    //             }

    //             // Redirect based on login_type
    //             if ($user->login_type === 'admin') {
    //                 return redirect()->intended('/admin/dashboard');
    //             } else {
    //                 return redirect()->intended('/dashboard');
    //             }
    //         } else {
    //             return back()->withErrors([
    //                 'userName' => 'User name and Password does not match with our record.',
    //             ]);
    //         }


    //         // if (($user) && ($user->pword === $request->password)) {
    //         //     Session::put('user_info', [
    //         //         'contactname' => $user->contactname,
    //         //         'bigid' => $user->bigid,
    //         //         'username' => $user->uname,
    //         //          'user_type' => $user->user_type,
    //         //     ]);

    //         //     // Check if there is a pending profile update
    //         //     $pendingProfile = ItaggProfilePending::where('bigid', $user->bigid)->first();

    //         //     if ($pendingProfile) {
    //         //         // Redirect to profile lock page
    //         //         return redirect()->route('profile.lock');
    //         //     } else {
    //         //         // Redirect to dashboard
    //         //         return redirect()->intended('/dashboard');
    //         //     }
    //         // } else {
    //         //     return back()->withErrors([
    //         //         'userName' => 'User name and Password does not match with our record.',
    //         //     ]);
    //         // }
    //     } catch (\Throwable $ex) {
    //         return back()->withErrors([
    //             'userName' => 'Please check the database connection',
    //         ]);
    //     }
    // }

    public function dashboard(Request $request)
    {
        #### New Dashboard ####
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

            // $usersSessionLog = UsersSessionLog::where('big_id', $user->bigid)
            //     ->where('status', 1)
            //     ->orderByDesc('id')
            //     ->first();

            // if ($usersSessionLog) {
            //     return redirect('/')->with('error', 'You have been logged out. Please login again.');
            // }



            $pendingProfile = ItaggProfilePending::where('bigid', $user->bigid)->first();

            if ($user->ip_address_restriction == 1) {

                $currentIp = $request->ip();

                $ipAllowed = IpAddressResstriction::where('ip_address', $currentIp)
                    ->where('bigid', $user->bigid)
                    ->where('status', 1)
                    ->exists();
                if ($ipAllowed == '1') {
                    return redirect('/')->with('error', 'Your access has been denied as you are trying to access the system from a restricted IP address. Please log in from an approved IP address.');
                }
            }

            if ($user->bit_disabled == 1) {
                return redirect('/')->with('error', 'Account is disabled.');
            }

            $lockoutStatus = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->select('profileupdate_lockout', 'clientcommfail')
                ->first();

            if ($lockoutStatus->clientcommfail == 'y') {
                return redirect('/')->with('error', 'Account is locked.');
            }

            if ($lockoutStatus->profileupdate_lockout == '1') {
                // Redirect to profile lock page
                return redirect()->route('profile.lock');
            } else {
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
                    ->where('userref', $bigid)
                    ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $currentYear)
                    ->whereMonth(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $currentMonth)
                    ->first();

                return view('customer.new_dashboard', compact('user_contactname', 'remaining_wallet', 'bigid', 'allQuery'));


                // Redirect to dashboard
                // $data = DB::table('smsg_log')
                // ->selectRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month, COUNT(*) as total")
                // ->where('sentstatus', 'ok')
                // ->where('userref', $bigid)
                // ->groupByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b')")
                // ->orderByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
                // ->get();


                // // Extract months and totals for the chart
                // $months = $data->pluck('month')->toArray();
                // $totals = $data->pluck('total')->toArray();   
            }
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        Session::flush();
        Session::forget('user_info');

        return redirect('/')->with('success', 'You have been successfully logged out.');
        // return redirect('/')->with('success', 'You have been successfully logged out.');
    }

    public function forgot_password(Request $request)
    {

        return view('auth.forgot-password');
    }

    public function new_dashboard()
    {
        #### Old Dashboard ####
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

            $pendingProfile = ItaggProfilePending::where('bigid', $user->bigid)->first();

            $lockoutStatus = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->select('profileupdate_lockout', 'clientcommfail')
                ->first();

            if ($lockoutStatus->profileupdate_lockout == '1') {
                // Redirect to profile lock page
                return redirect()->route('profile.lock');
            } else {
                // Redirect to dashboard
                return view('customer.dashboard', compact('user_contactname', 'remaining_wallet'));
            }
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
    }


    public function getMonthlyCounts(Request $request)
    {
        $bigid = Session::get('user_info')['bigid'];

        ### Year Selected Based Controll
        $year = $request->input('year', now()->year); // Default to the current year if no year is selected

        $data = DB::table('smsg_log')
            ->selectRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month, COUNT(*) as total")
            ->where('sentstatus', 'ok')
            ->where('userref', $bigid)
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

        ### Current Year Based Controll

        // $currentYear = now()->year;

        // $data = DB::table('smsg_log')
        //     ->selectRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month, COUNT(*) as total")
        //     ->where('sentstatus', 'ok')
        //     ->where('userref', $bigid)
        //     ->whereYear(DB::raw("STR_TO_DATE(timesent, '%Y%m%d%H%i%S')"), $currentYear) 
        //     ->groupByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b'), DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
        //     ->orderByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
        //     ->get();

        // // Initialize an array with 12 months set to 0
        // $chartData = array_fill(0, 12, 0);
        // $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // foreach ($data as $item) {
        //     $index = array_search($item->month, $months);
        //     if ($index !== false) {
        //         $chartData[$index] = $item->total;
        //     }
        // }

        // return response()->json($chartData); // Example output: [10, 20, 30, ..., 0]

        ### Get Full Month Based Controll
        // $data = DB::table('smsg_log')
        //     ->selectRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b') as month, COUNT(*) as total")
        //     ->where('sentstatus', 'ok')
        //     ->where('userref', $bigid)
        //     ->groupByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%b'), DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
        //     ->orderByRaw("DATE_FORMAT(STR_TO_DATE(timesent, '%Y%m%d%H%i%S'), '%Y-%m')")
        //     ->get();

        // // Initialize an array with 12 months set to 0
        // $chartData = array_fill(0, 12, 0);
        // $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // foreach ($data as $item) {
        //     $index = array_search($item->month, $months);
        //     if ($index !== false) {
        //         $chartData[$index] = $item->total;
        //     }
        // }

        // return response()->json($chartData); // Example output: [10, 20, 30, ..., 0]
    }

    public function getDailyCounts(Request $request)
    {

        $bigid = Session::get('user_info')['bigid'];
        $selectedYear = $request->query('year', Carbon::now()->year);

        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("DATE(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as date, COUNT(*) as total")
            ->where('sentstatus', 'ok')
            ->where('userref', $bigid)
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

    public function getMonthlySmsProfit(Request $request)
    {

        $bigid = Session::get('user_info')['bigid'];


        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("
            DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
            SUM(profit) as total_profit
        ")
            ->where('sentstatus', 'ok')
            ->where('userref', $bigid)
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

    public function getMonthlySmsCost(Request $request)
    {

        $bigid = Session::get('user_info')['bigid'];


        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("
            DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
            SUM(costprice) as total_cost
        ")
            ->where('sentstatus', 'ok')
            ->where('userref', $bigid)
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

    public function getMonthlySmsUserPrice(Request $request)
    {

        $bigid = Session::get('user_info')['bigid'];


        $selectedYear = $request->query('year', Carbon::now()->year);
        $selectedMonth = $request->query('month', Carbon::now()->month);


        $data = DB::table('smsg_log')
            ->selectRaw("
            DAY(STR_TO_DATE(timesent, '%Y%m%d%H%i%S')) as day,
            SUM(userprice) as user_price
        ")
            ->where('sentstatus', 'ok')
            ->where('userref', $bigid)
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

    /**
     * Check if customer is in maintenance mode
     */
    private function checkMaintenanceMode($user): array
    {
        $result = [
            'is_maintenance' => false,
            'message' => '',
            'end_time' => null,
        ];

        try {
            // First check global maintenance mode
            $globalMaintenance = CustomerSetting::getValue('global_maintenance_mode', false);

            if ($globalMaintenance) {
                $result['is_maintenance'] = true;
                $result['message'] = CustomerSetting::getValue('maintenance_message', 'The site is currently under maintenance. Please try again later.');
                return $result;
            }

            // Check customer-specific maintenance by user_id
            $customerMaintenance = CustomerMaintenance::where('is_enabled', true)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('user_bigid', $user->bigid);
                })
                ->where(function ($q) {
                    $q->whereNull('start_time')
                        ->orWhere('start_time', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_time')
                        ->orWhere('end_time', '>=', now());
                })
                ->first();

            if ($customerMaintenance) {
                $result['is_maintenance'] = true;
                $result['message'] = $customerMaintenance->maintenance_message
                    ?: CustomerSetting::getValue('maintenance_message', 'The site is currently under maintenance. Please try again later.');
                $result['end_time'] = $customerMaintenance->end_time;
            }
        } catch (\Exception $e) {
            // If tables don't exist yet, just continue without maintenance check
            \Log::warning('Maintenance mode check failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Show maintenance page
     */
    public function showMaintenancePage()
    {
        $maintenanceInfo = Session::get('maintenance_mode');

        if (!$maintenanceInfo || !$maintenanceInfo['enabled']) {
            return redirect()->route('dashboard');
        }

        $message = $maintenanceInfo['message'] ?? 'The site is currently under maintenance. Please try again later.';
        $endTime = $maintenanceInfo['end_time'] ?? null;

        return view('auth.maintenance', compact('message', 'endTime'));
    }

    /**
     * Check maintenance and redirect if cleared
     */
    public function checkMaintenanceStatus()
    {
        $userInfo = Session::get('user_info');

        if (!$userInfo) {
            return redirect('/');
        }

        $user = User::where('bigid', $userInfo['bigid'])->first();

        if (!$user) {
            return redirect('/');
        }

        $maintenanceCheck = $this->checkMaintenanceMode($user);

        if (!$maintenanceCheck['is_maintenance']) {
            Session::forget('maintenance_mode');
            return redirect()->route('dashboard')->with('success', 'Maintenance mode has ended. Welcome back!');
        }

        return response()->json([
            'is_maintenance' => true,
            'message' => $maintenanceCheck['message'],
            'end_time' => $maintenanceCheck['end_time'] ? $maintenanceCheck['end_time']->toIso8601String() : null,
        ]);
    }
}
