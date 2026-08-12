<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use App\Models\User;

class AutoLoginController extends Controller
{
    /**
     * Step 1: Generate token and redirect to the other domain
     */
    public function generateTokenAndRedirect($username)
    {
        $user = User::where('uname', $username)->first();

        if (!$user) {
            return redirect()->back()->withErrors(['error' => 'User not found']);
        }

        if ($user->migration_flag === 'old') {
            // Build redirect URL with credentials for old system
            $oldSystemUrl = config('domains.old_sms_expert_dashboard_url');
            $redirectUrl = str_replace(
                ['{username}', '{password}'],
                [urlencode($user->uname), urlencode($user->pword)],
                $oldSystemUrl
            );
            return redirect()->away($redirectUrl);
        }

        // Always generate a new token for security
        $user->remember_token = Str::random(60);
        $user->save();

        // $customerDomains = config('custom.customer_domains');
        // $adminDomains    = config('custom.admin_domains');
        // $scheme = request()->getScheme();
        // // Pick first domain from config (or more advanced: pick based on user company)
        // if ($user->login_type === 'admin' && !empty($adminDomains)) {
        //     $autologinUrl =  $scheme . '://' . $adminDomains[0] . '/autologin?token=' . $user->remember_token;
        // } elseif (!empty($customerDomains)) {
        //     $autologinUrl =  $scheme . '://' . $customerDomains[0] . '/autologin?token=' . $user->remember_token;
        // } else {
        //     return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        // }


        $customerDomains = config('domains.customer_domains');
        $adminDomains    = config('domains.admin_domains');
        $scheme = request()->getScheme();

        // Pick first domain from config
        if ($user->login_type === 'admin' && !empty($adminDomains)) {
            $autologinUrl = $scheme . '://' . $adminDomains[0] . '/autologin?token=' . $user->remember_token;
        } elseif (!empty($customerDomains)) {
            $autologinUrl = $scheme . '://' . $customerDomains[0] . '/autologin?token=' . $user->remember_token;
        } else {
            return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        }


        return redirect()->away($autologinUrl);
    }

    /**
     * Step 2: Auto login on target domain
     */
    public function loginWithToken(Request $request)
    {
        $host = $request->getHost();

        $customerDomains = config('domains.customer');
        $adminDomains    = config('domains.admin');

        // 🔹 Set different session cookies dynamically
        if (in_array($host, $adminDomains)) {
            Config::set('session.cookie', 'admin_session');
        } elseif (in_array($host, $customerDomains)) {
            Config::set('session.cookie', 'customer_session');
        }

        $token = $request->query('token');
        if (!$token) {
            return redirect('/')->withErrors(['error' => 'Token missing']);
        }

        $user = User::where('remember_token', $token)->first();
        if (!$user) {
            return redirect('/')->withErrors(['error' => 'Invalid or expired token']);
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account disabled.');
        }

        // Store user info in session
        Session::put('user_info', [
            'contactname' => $user->contactname,
            'bigid'       => $user->bigid,
            'username'    => $user->uname,
            'login_type'  => $user->login_type,
        ]);

        Auth::login($user);

        // Check lockout
        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account locked.');
        }

        return redirect('/dashboard');
    }

    //Campaign Redirect

    public function CampaignTokenAndRedirect($username)
    {
        $user = User::where('uname', $username)->first();

        if (!$user) {
            return redirect()->back()->withErrors(['error' => 'User not found']);
        }



        if ($user->migration_flag === 'old') {
            // Build redirect URL with credentials for old system
            $oldSystemUrl = config('domains.old_sms_expert_campaign_url');
            $redirectUrl = str_replace(
                ['{username}', '{password}'],
                [urlencode($user->uname), urlencode($user->pword)],
                $oldSystemUrl
            );
            return redirect()->away($redirectUrl);
        }

        // Always generate a new token for security
        $user->remember_token = Str::random(60);
        $user->save();

        // $customerDomains = config('custom.customer_domains');
        // $adminDomains    = config('custom.admin_domains');
        // $scheme = request()->getScheme();
        // // Pick first domain from config (or more advanced: pick based on user company)
        // if ($user->login_type === 'admin' && !empty($adminDomains)) {
        //     $autologinUrl =  $scheme . '://' . $adminDomains[0] . '/autologin?token=' . $user->remember_token;
        // } elseif (!empty($customerDomains)) {
        //     $autologinUrl =  $scheme . '://' . $customerDomains[0] . '/autologin?token=' . $user->remember_token;
        // } else {
        //     return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        // }


        $campaignDomains = config('domains.campaign_domains');
        $adminDomains    = config('domains.admin_domains');
        $scheme = request()->getScheme();

        // Pick first domain from config
        if ($user->login_type === 'admin' && !empty($adminDomains)) {
            $autologinUrl = $scheme . '://' . $adminDomains[0] . '/autologincampaign?token=' . $user->remember_token;
        } elseif (!empty($campaignDomains)) {
            $autologinUrl = $scheme . '://' . $campaignDomains[0] . '/autologincampaign?token=' . $user->remember_token;
        } else {
            return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        }


        return redirect()->away($autologinUrl);
    }

    /**
     * Step 2: Auto login on campaign domain
     */
    public function loginWithTokenCampaign(Request $request)
    {
        $host = $request->getHost();

        $campaignDomains = config('domains.campaign');
        $adminDomains    = config('domains.admin');

        // 🔹 Set different session cookies dynamically
        if (in_array($host, $adminDomains)) {
            Config::set('session.cookie', 'admin_session');
        } elseif (in_array($host, $campaignDomains)) {
            Config::set('session.cookie', 'campaign_session');
        }

        $token = $request->query('token');
        if (!$token) {
            return redirect('/')->withErrors(['error' => 'Token missing']);
        }

        $user = User::where('remember_token', $token)->first();
        if (!$user) {
            return redirect('/')->withErrors(['error' => 'Invalid or expired token']);
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account disabled.');
        }

        // Store user info in session
        Session::put('user_info', [
            'contactname' => $user->contactname,
            'bigid'       => $user->bigid,
            'username'    => $user->uname,
            'login_type'  => 'campaign',
        ]);

        Auth::login($user);

        // Check lockout
        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account locked.');
        }

        return redirect('/campaign/dashboard');
    }

    /**
     * Auto-login with credentials from URL (for old system redirect)
     * URL format: /autologin-credentials?user={username}&pass={password}
     */
    public function loginWithCredentials(Request $request)
    {
        $username = $request->query('user');
        $password = $request->query('pass');

        if (!$username || !$password) {
            return redirect('/')->withErrors(['error' => 'Missing credentials']);
        }

        $user = User::where('uname', $username)->first();

        if (!$user || $user->pword !== $password) {
            return redirect('/')->withErrors(['error' => 'Invalid credentials']);
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account disabled.');
        }

        // Store user info in session
        Session::put('user_info', [
            'contactname' => $user->contactname,
            'bigid'       => $user->bigid,
            'username'    => $user->uname,
            'login_type'  => $user->login_type ?? 'customer',
        ]);

        Auth::login($user);

        // Check lockout
        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account locked.');
        }

        return redirect('/dashboard');
    }

    /**
     * Auto-login to Campaign with credentials from URL (for old system redirect)
     * URL format: /autologin-campaign-credentials?usr={username}&pwd={password}
     */
    public function loginCampaignWithCredentials(Request $request)
    {
        $username = $request->query('usr');
        $password = $request->query('pwd');

        if (!$username || !$password) {
            return redirect('/')->withErrors(['error' => 'Missing credentials']);
        }

        $user = User::where('uname', $username)->first();

        if (!$user || $user->pword !== $password) {
            return redirect('/')->withErrors(['error' => 'Invalid credentials']);
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account disabled.');
        }

        // Store user info in session
        Session::put('user_info', [
            'contactname' => $user->contactname,
            'bigid'       => $user->bigid,
            'username'    => $user->uname,
            'login_type'  => 'campaign',
        ]);

        Auth::login($user);

        // Check lockout
        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account locked.');
        }

        return redirect('/campaign/dashboard');
    }
}
