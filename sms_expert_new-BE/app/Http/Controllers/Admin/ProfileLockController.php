<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Mail\ProfileUpdateConfirmationMail;
use App\Services\Queue\EmailQueueService;
use App\Models\UsersSessionLog;
use App\Models\BlockedUser;
use Illuminate\Support\Carbon;



class ProfileLockController extends Controller
{
    public function disableAccountManager(Request $request)
    {

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            return redirect()
                ->back()
                ->with('error_profile', 'User not found!')
                ->with('scroll_target', 'profile-section');
        }


        $user->bit_disabled = $request->setdisabled;


        $user->save();

        return redirect()
            ->back()
            ->with('success_profile', 'Status updated successfully!')
            ->with('scroll_target', 'profile-section');


        // return redirect()->back()->with('success', 'Status Updated.');

    }

    public function toggleAccountLock(Request $request)
    {

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            return redirect()
            ->back()
            ->with('error_profile', 'User not found!')
            ->with('scroll_target', 'profile-section');
        }

        DB::table('useroption')
            ->where('userref', $user->bigid)
            ->update(['clientcommfail' => $request->clientcommfail]);


            return redirect()
            ->back()
            ->with('success_profile', 'Status updated successfully!')
            ->with('scroll_target', 'profile-section');
    }

    public function toggleProfileLock(Request $request)
    {

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            return redirect()
            ->back()
            ->with('error_profile', 'User not found!')
            ->with('scroll_target', 'profile-section');
        }

        DB::table('useroption')
            ->where('userref', $user->bigid)
            ->update(['profileupdate_lockout' => $request->profileupdate_lockout]);


            return redirect()
            ->back()
            ->with('success_profile', 'Status updated successfully!')
            ->with('scroll_target', 'profile-section');
    }

    public function toggleApiType(Request $request)
    {

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            return redirect()
            ->back()
            ->with('error_profile', 'User not found!')
            ->with('scroll_target', 'profile-section');
        }

        DB::table('useroption')
            ->where('userref', $user->bigid)
            ->update(['apitype' => $request->apitype]);


            return redirect()
            ->back()
            ->with('success_profile', 'Status updated successfully!')
            ->with('scroll_target', 'profile-section');
    }

    public function forceLogout(Request $request)
    {
        $userref = $request->userbigid;
        $loginIp = $request->ip();
        $staffInitials = 'DevTeam';

        DB::table('users_session_logs')
            ->where('big_id', $userref)
            ->update([
                'force_logout' => 1,
                'force_logout_by_ip' => $loginIp,
                'force_logout_date' => DB::raw('unix_timestamp()'), // match OLD back-office (unix_timestamp())
                'force_logout_by' => $staffInitials,
                'status' => 1,
            ]);

            return redirect()
            ->back()
            ->with('success_profile', 'All sessions have been force logged out!')
            ->with('scroll_target', 'profile-section');
    }

    public function profileUpdate(Request $request)
    {

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            // return redirect()->back()->with('error', 'User not found');
            return redirect()
                ->back()
                ->with('error_profile', 'User not found!')
                ->with('scroll_target', 'profile-section');
        }

        DB::table('useroption')
            ->where('userref', $user->bigid)
            ->update([
                'profileupdate_lockout' => 0,
                'sdf_lastupdated' => date('Y-m-d'),
            ]);

        // return redirect()->back()->with('success', 'Status Updated.');
        return redirect()
        ->back()
        ->with('success_profile', 'Status updated successfully!')
        ->with('scroll_target', 'profile-section');
    }

    public function sendProfileUpdate($id)
    {
        $user = DB::table('users')->where('id', $id)->first();

        if (!$user) {
            // return back()->with('error', 'User not found.');
            return redirect()
                ->back()
                ->with('error_profile', 'User not found!')
                ->with('scroll_target', 'profile-section');
        }

        $pending = DB::table('itagg_profilepending')
            ->where('bigid', $user->bigid)
            ->orderByDesc('id')
            ->first();

        if (!$pending) {
            return redirect()
                ->back()
                ->with('error_profile', 'No pending profile update found!')
                ->with('scroll_target', 'profile-section');
            // return back()->with('error', 'No pending profile update found.');
        }

        $useroption = DB::table('useroption')->where('userref', $user->bigid)->first();
        $explanation = $useroption->explanation ?? '';

        $pending->changed_basicdetails = ($pending->busname !== $user->busname ||
            $pending->contactname !== $user->contactname ||
            $pending->address1 !== $user->address1 ||
            $pending->address2 !== $user->address2 ||
            $pending->town !== $user->town ||
            // $pending->city !== $user->city ||
            // $pending->county !== $user->county ||
            $pending->pcode !== $user->pcode ||
            $pending->country !== $user->country ||
            $pending->mobilenumber !== $user->mobilenumber ||
            $pending->phone !== $user->phone ||
            $pending->contactemail !== $user->contactemail ||
            $pending->explanation !== $explanation
        );

        $pending->changed_password = $pending->pword !== $user->pword;
        $pending->changed_ips = !empty($pending->ips);

        // The pending row's id IS the confirmation token, so route to confirmProfile
        // (the SAME link the customer-side ProfileConfirmationMail uses). Clicking it
        // applies the pending changes and clears profileupdate_lockout (= unlocks the
        // dashboard) — matching the old back-office profile_update_mail behaviour.
        $confirmationUrl = route('profile.confirm', ['token' => $pending->id]);

        if (!empty($user->contactemail)) {
            // Send profile update confirmation email via RabbitMQ queue
            $emailQueueService = new EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\ProfileUpdateConfirmationMail',
                $user->contactemail,
                [
                    'user' => $user->toArray(),
                    'pending' => $pending->toArray(),
                    'confirmation_url' => $confirmationUrl,
                ]
            );
        }

        return redirect()
        ->back()
        ->with('success_profile', 'Profile update confirmation email sent successfully!')
        ->with('scroll_target', 'profile-section');
    }

    public function forceLogoutCurrent(Request $request)
    {
        $userref = $request->userbigid;
        $loginIp = $request->ip();
        $staffInitials = 'DevTeam';

        DB::table('users_session_logs')
            ->where('big_id', $userref)
            ->update([
                'force_logout' => 1,
                'force_logout_by_ip' => $loginIp,
                'force_logout_date' => DB::raw('unix_timestamp()'), // match OLD back-office (unix_timestamp())
                'force_logout_by' => $staffInitials,
                'status' => 1,
            ]);

        session()->flash('activeTab', 'customer-current-log');

        return redirect()->back()->with('success', "Sessions have been force logged out.");
    }


    public function forceLogoutAndBlock(Request $request)
    {
        $usersSessionLog = UsersSessionLog::where('status', 0)
            ->where('id', $request->users_session_logs_id)
            ->first();

        if (!$usersSessionLog) {
            return back()->with('error', 'Session log not found or already logged out.');
        }

        $login_ip_address = request()->ip();
        $staffinitials = 'DevTeam';

        // Update users_session_logs
        $usersSessionLog->update([
            'force_logout'        => 1,
            'status'              => 1,
            'force_logout_by_ip'  => $login_ip_address,
            'force_logout_date'   => DB::raw('unix_timestamp()'), // match OLD back-office (unix_timestamp())
            'force_logout_by'     => $staffinitials,
        ]);

        // Insert into blocked_users
        BlockedUser::create([
            'users_session_logs_id' => $usersSessionLog->id,
            'itaggcustid'           => $usersSessionLog->itaggcustid,
            'ip_address'            => $usersSessionLog->ip_address,
            'big_id'                => $usersSessionLog->big_id,
            'blocked_by'            => $staffinitials,
            'blocked_by_ip'         => $login_ip_address,
            'blocked_date'          => Carbon::now('Europe/London')->format('YmdHis'),
            'status'                => 0,
        ]);

        session()->flash('activeTab', 'customer-current-log');

        return redirect()->back()->with('success', 'IP has been force logged out and blocked.');
    }


    public function unblockUser($id)
    {
        $blockedUser = BlockedUser::find($id);

        if (!$blockedUser) {
            return back()->with('error', 'Blocked user not found.');
        }

        $blockedUser->update([
            'status' => 1,
        ]);

        return back()->with('success', 'IP has been unblocked.');
    }
}
