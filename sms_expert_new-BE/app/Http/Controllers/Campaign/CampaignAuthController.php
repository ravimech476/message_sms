<?php

namespace App\Http\Controllers\Campaign;

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
use App\Models\IpAddressResstriction;
use App\Models\UsersSessionLog;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;

class CampaignAuthController extends Controller
{
    public function campaignLoginForm()
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



    public function campaignLogin(Request $request)
    {
        try {

            /* ---------------------------------------------------------
         | Validation
        --------------------------------------------------------- */
            $validate = Validator::make($request->all(), [
                'userName' => 'required',
                'password' => 'required'
            ]);

            if ($validate->fails()) {
                return response()->json(['status' => false, 'message' => 'Validation Error', 'errors' => $validate->errors()], 403);
            }

            /* ---------------------------------------------------------
         | Campaign hidden field ONLY
        --------------------------------------------------------- */
            $campaignRequest = $request->input('request_login_type'); // only campaign sends this

            /* ---------------------------------------------------------
         | Fetch User
        --------------------------------------------------------- */
            $user = User::where('uname', $request->userName)->first();

            if (!$user || $user->pword !== $request->password) {
                return back()->withErrors([
                    'userName' => 'User name and Password do not match our records.',
                ]);
            }

            /* ---------------------------------------------------------
         | Domain Groups
        --------------------------------------------------------- */
            $currentHost      = $request->getHost();
            $customerDomains  = config('domains.customer');
            $adminDomains     = config('domains.admin');
            $campaignDomains  = config('domains.campaign');

            /* ---------------------------------------------------------
         | ADMIN LOGIN (no hidden field)
        --------------------------------------------------------- */
            if (in_array($currentHost, $adminDomains)) {

                if ($user->login_type !== 'admin') {
                    return back()->withErrors([
                        'userName' => 'Access denied. Admin credentials required.'
                    ]);
                }

                $finalLoginType = 'admin';
            } elseif (in_array($currentHost, $customerDomains)) {

                if (!is_null($user->login_type) && $user->login_type !== 'customer') {
                    return back()->withErrors([
                        'userName' => 'Access denied. Dashboard credentials required.'
                    ]);
                }

                $finalLoginType = 'customer'; // or null treated as customer
            } elseif (in_array($currentHost, $campaignDomains)) {

                // hidden field must be campaign
                if ($campaignRequest !== 'campaign') {
                    return back()->withErrors([
                        'userName' => 'Access denied. Campaign login required.'
                    ]);
                }

                // DB login_type must be customer OR null
                if (!is_null($user->login_type) && $user->login_type !== 'customer') {
                    return back()->withErrors([
                        'userName' => 'Access denied. Only customer accounts can access the campaign manager.'
                    ]);
                }

                $finalLoginType = 'campaign'; // Override DB login type
            }


            if ($user->migration_flag === 'old') {
                // Build redirect URL with credentials for old system
                $oldSystemUrl = config('domains.old_sms_expert_campaign_url');
                $redirectUrl = str_replace(
                    ['{username}', '{password}'],
                    [urlencode($user->uname), urlencode($request->password)],
                    $oldSystemUrl
                );
                return redirect()->away($redirectUrl);
            }

            // if ($user->migration_flag === 'old') {

            //     Session::put('user_info', [
            //         'contactname' => $user->contactname,
            //         'bigid' => $user->bigid,
            //         'username' => $user->uname,
            //         'login_type' => $user->login_type,
            //     ]);

            //     Auth::login($user);

            //     return redirect()->away(
            //         'https://www.devsmsexpert.com/accountmanager/index.php'
            //     );
            // }

            /* ---------------------------------------------------------
         | IP Restriction
        --------------------------------------------------------- */
            if ($user->ip_address_restriction == 1) {
                $currentIp = $request->ip();
                $ipAllowed = IpAddressResstriction::where('ip_address', $currentIp)
                    ->where('bigid', $user->bigid)
                    ->where('status', 1)
                    ->exists();

                if ($ipAllowed == '1') {
                    return redirect('/')->with(
                        'error',
                        'Your access has been denied as you are trying to access the system from a restricted IP address.'
                    );
                }
            }

            /* ---------------------------------------------------------
         | Disabled User
        --------------------------------------------------------- */
            if ($user->bit_disabled == 1) {
                return redirect('/')->with('error', 'Account is disabled.');
            }

            /* ---------------------------------------------------------
         | Store Session (admin/customer from DB, campaign from hidden)
        --------------------------------------------------------- */
            Session::put('user_info', [
                'contactname' => $user->contactname,
                'bigid'       => $user->bigid,
                'username'    => $user->uname,
                'login_type'  => $finalLoginType,
            ]);

            Auth::login($user);

            /* ---------------------------------------------------------
         | Lockout Checks
        --------------------------------------------------------- */
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

            /* ---------------------------------------------------------
         | Log Session (admin excluded)
        --------------------------------------------------------- */
            if ($finalLoginType !== 'admin') {
                $timestamp = Carbon::now('Europe/London')->format('YmdHis');
                UsersSessionLog::create([
                    'big_id'       => $user->bigid,
                    'ip_address'   => $request->ip(),
                    'itaggcustid'  => $user->bigid,
                    'status'       => 0,
                    'login_date'   => $timestamp,
                ]);

                //  Check maintenance mode for customers/campaign users
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

            /* ---------------------------------------------------------
         | FINAL REDIRECT
        --------------------------------------------------------- */
            if ($finalLoginType === 'admin') {
                return redirect()->intended('/admin/dashboard');
            } elseif ($finalLoginType === 'campaign') {
                return redirect()->intended('/campaign/dashboard');
            } else {
                return redirect()->intended('/dashboard');   // customer
            }
        } catch (\Throwable $ex) {
            \Log::error('Campaign login failed', [
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

    public function campaignLogout(Request $request)
    {
        Auth::logout();
        Session::flush();
        Session::forget('user_info');

        return redirect('/')->with('success', 'You have been successfully logged out.');
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
}
