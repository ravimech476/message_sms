<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\ItaggProfilePending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;



class AutoLoginController extends Controller
{
    /**
     * Step 1: Generate token and redirect to autologin
     */
    public function generateTokenAndRedirect($username)
    {
        $user = User::where('uname', $username)->first();

        if ($user) {
            if (!$user->remember_token) {
                $user->remember_token = Str::random(60);
                $user->save();
            }

            $autologinUrl = route('autologin', ['token' => $user->remember_token]);
            return redirect()->to($autologinUrl);
        }

        return redirect('/')->withErrors(['error' => 'User not found']);
    }

    /**
     * Step 2: Auto-login using token
     */
    public function loginWithToken(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return redirect('/')->withErrors(['error' => 'Token is missing']);
        }

        $user = User::where('remember_token', $token)->first();

        if (!$user) {
            return redirect('/')->withErrors(['error' => 'Invalid or expired token']);
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

        $pendingProfile = \App\Models\ItaggProfilePending::where('bigid', $user->bigid)->first();

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

        return $user->login_type === 'admin'
            ? redirect('http://secure.smsexpert.nedtechnology.co.in/admin/dashboard')
            : redirect('http://smsexpert.nedtechnology.co.in:8000/dashboard');


        // return $user->login_type === 'admin'
        //     ? redirect('/admin/dashboard')
        //     : redirect('/dashboard');
    }
}
