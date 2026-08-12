<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ItaggProfilePending;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\IpAddressResstriction;

class AdminAuthController extends Controller
{
    /**
     * Show the admin login form.
     */
    public function adminShowLoginForm()
    {
        $host = strtolower(request()->getHost());

        $customerDomains = config('domains.customer');
        $adminDomains    = config('domains.admin');
        $campaignDomains = config('domains.campaign');

        // Load correct login screen by customer
        if (in_array($host, $customerDomains)) {
            return view('auth.customer-login');
        }
        // Load correct login screen by admin
        if (in_array($host, $adminDomains)) {
            return view('admin.auth.login');
        }
        // Load correct login screen by campaign
        if (in_array($host, $campaignDomains)) {
            return view('campaign.auth.login');
        }

        // If admin already logged in → send to dashboard
        if (Auth::check() && Auth::user()->login_type === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        // Invalid domain
        abort(404, 'Domain not recognized');
    }

    /**
     * Handle admin login request.
     */
    public function login(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'userName' => 'required',
                'password' => 'required'
            ]);

            if ($validate->fails()) {
                return back()->withErrors($validate->errors())->withInput();
            }

            $user = User::where('uname', $request->userName)->first();

            if (!$user) {
                return back()->withErrors([
                    'userName' => 'Admin username and password do not match our records.',
                ])->withInput();
            }

            // Check password - support both plain text (old) and md5 (new) passwords
            $passwordMatch = false;
            if ($user->pword === $request->password) {
                // Plain text password match (old system)
                $passwordMatch = true;
            } elseif ($user->pword === md5($request->password)) {
                // MD5 password match (new admin users)
                $passwordMatch = true;
            }

            if ($passwordMatch) {

                // Check if user is admin
                if ($user->login_type !== 'admin') {
                    return back()->withErrors([
                        'userName' => 'Access denied. Admin credentials required.',
                    ])->withInput();
                }

                // Check IP address restriction flag on user
                if ($user->ip_address_restriction == 1) {
                    $currentIp = $request->ip();

                    $ipAllowed = IpAddressResstriction::where('ip_address', $currentIp)
                        ->where('bigid', $user->bigid)
                        ->where('status', 1)
                        ->exists();

                    if (!$ipAllowed) {
                        return back()->withErrors([
                            'userName' => 'Your access has been denied as you are trying to access the system from a restricted IP address. Please log in from an approved IP address.',
                        ])->withInput();
                    }
                }

                if ($user->bit_disabled == 1) {
                    return back()->withErrors([
                        'userName' => 'Admin account is disabled.',
                    ])->withInput();
                }

                // Check lockout status (only for users with bigid in useroption table)
                if ($user->bigid) {
                    $lockoutStatus = DB::table('useroption')
                        ->where('userref', $user->bigid)
                        ->select('profileupdate_lockout', 'clientcommfail')
                        ->first();

                    if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
                        return back()->withErrors([
                            'userName' => 'Admin account is locked.',
                        ])->withInput();
                    }
                }

                // Set session data
                Session::put('user_info', [
                    'contactname' => $user->contactname,
                    'bigid' => $user->bigid,
                    'username' => $user->uname,
                    'login_type' => $user->login_type,
                ]);

                // Set admin_user session for permission checking
                // Load permissions from database
                $adminPermissions = $user->adminPermissions;
                $permissionsArray = [];
                
                if ($adminPermissions) {
                    $permissionsArray = [
                        // Menu permissions
                        'can_view_dashboard' => (bool) $adminPermissions->can_view_dashboard,
                        'can_view_customers' => (bool) $adminPermissions->can_view_customers,
                        'can_view_customer_emails' => (bool) $adminPermissions->can_view_customer_emails,
                        'can_view_virtual_numbers' => (bool) $adminPermissions->can_view_virtual_numbers,
                        'can_view_reports' => (bool) $adminPermissions->can_view_reports,
                        'can_view_settings' => (bool) $adminPermissions->can_view_settings,
                        'can_manage_admin_users' => (bool) $adminPermissions->can_manage_admin_users,
                        'can_manage_contracts' => (bool) ($adminPermissions->can_manage_contracts ?? false),
                        'can_manage_cost' => (bool) ($adminPermissions->can_manage_cost ?? false),
                        'can_manage_global_pricing' => (bool) ($adminPermissions->can_manage_global_pricing ?? false),
                        // Customer tab permissions
                        'can_view_customer_profile' => (bool) $adminPermissions->can_view_customer_profile,
                        'can_edit_customer_profile' => (bool) $adminPermissions->can_edit_customer_profile,
                        'can_view_customer_keywords' => (bool) $adminPermissions->can_view_customer_keywords,
                        'can_edit_customer_keywords' => (bool) $adminPermissions->can_edit_customer_keywords,
                        'can_view_customer_virtual_numbers' => (bool) $adminPermissions->can_view_customer_virtual_numbers,
                        'can_edit_customer_virtual_numbers' => (bool) $adminPermissions->can_edit_customer_virtual_numbers,
                        'can_view_customer_notes' => (bool) $adminPermissions->can_view_customer_notes,
                        'can_edit_customer_notes' => (bool) $adminPermissions->can_edit_customer_notes,
                        'can_view_customer_wallet' => (bool) $adminPermissions->can_view_customer_wallet,
                        'can_edit_customer_wallet' => (bool) $adminPermissions->can_edit_customer_wallet,
                        'can_view_customer_logs' => (bool) $adminPermissions->can_view_customer_logs,
                        'can_view_customer_reports' => (bool) $adminPermissions->can_view_customer_reports,
                        'can_view_customer_flag' => (bool) $adminPermissions->can_view_customer_flag,
                        // Action permissions
                        'can_create_customers' => (bool) $adminPermissions->can_create_customers,
                        'can_delete_customers' => (bool) $adminPermissions->can_delete_customers,
                        'can_export_data' => (bool) $adminPermissions->can_export_data,
                        // Settings tab permissions
                        'can_view_system_status' => (bool) ($adminPermissions->can_view_system_status ?? false),
                        'can_view_system_logs' => (bool) ($adminPermissions->can_view_system_logs ?? false),
                        'can_view_user_activity' => (bool) ($adminPermissions->can_view_user_activity ?? false),
                        'can_view_env_variables' => (bool) ($adminPermissions->can_view_env_variables ?? false),
                        'can_manage_cache' => (bool) ($adminPermissions->can_manage_cache ?? false),
                        'can_view_process_monitor' => (bool) ($adminPermissions->can_view_process_monitor ?? false),
                        'can_view_queues' => (bool) ($adminPermissions->can_view_queues ?? false),
                        'can_manage_customer_settings' => (bool) ($adminPermissions->can_manage_customer_settings ?? false),
                        'can_manage_server_settings' => (bool) ($adminPermissions->can_manage_server_settings ?? false),
                        'can_run_queries' => (bool) ($adminPermissions->can_run_queries ?? false),
                        'can_manage_dlr_update' => (bool) ($adminPermissions->can_manage_dlr_update ?? false),
                        // Customer settings sub-permissions
                        'can_manage_general_customer_settings' => (bool) ($adminPermissions->can_manage_general_customer_settings ?? false),
                        'can_manage_global_maintenance' => (bool) ($adminPermissions->can_manage_global_maintenance ?? false),
                        'can_manage_customer_maintenance' => (bool) ($adminPermissions->can_manage_customer_maintenance ?? false),
                        // Notification permission
                        'can_manage_notifications' => (bool) ($adminPermissions->can_manage_notifications ?? false),
                        // Reports tab permissions
                        'can_view_postpay_report' => (bool) ($adminPermissions->can_view_postpay_report ?? false),
                        'can_view_daily_sms_report' => (bool) ($adminPermissions->can_view_daily_sms_report ?? false),
                        'can_view_money_transfer_report' => (bool) ($adminPermissions->can_view_money_transfer_report ?? false),
                        'can_view_monthly_sales_report' => (bool) ($adminPermissions->can_view_monthly_sales_report ?? false),
                        'can_view_daemon_report' => (bool) ($adminPermissions->can_view_daemon_report ?? false),
                        'can_view_customer_rate' => (bool) ($adminPermissions->can_view_customer_rate ?? false),
                    ];
                }
                
                Session::put('admin_user', [
                    'id' => $user->id,
                    'bigid' => $user->bigid,
                    'username' => $user->uname,
                    'name' => $user->contactname,
                    'email' => $user->contactemail,
                    'role' => $user->role,
                    'login_type' => $user->login_type,
                    'permissions' => $permissionsArray,
                ]);

                // Update last login info using DB query (since timestamps are disabled)
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'last_login_at' => now(),
                        'last_login_ip' => $request->ip(),
                    ]);

                Auth::login($user);

                // Redirect to the page the admin originally tried to reach (stored by
                // AdminMiddleware via guest()), falling back to the admin dashboard.
                return redirect()->intended(route('admin.dashboard'))->with('success', 'Welcome back, ' . $user->contactname . '!');
            } else {
                return back()->withErrors([
                    'userName' => 'Admin username and password do not match our records.',
                ])->withInput();
            }
        } catch (\Throwable $ex) {
            \Log::error('Admin login failed', [
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

    /**
     * Handle admin logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        Session::forget('user_info');
        Session::forget('admin_user');
        Session::flush();

        return redirect('/')->with('success', 'You have been successfully logged out.');
        //  return redirect()->route('admin.login')->with('success', 'You have been successfully logged out.');
    }
}
