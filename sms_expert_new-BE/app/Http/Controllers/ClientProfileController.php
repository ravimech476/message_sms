<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use App\Models\ItaggProfilePending;
use App\Models\ItaggIpSecurity;
use Illuminate\Support\Facades\Redirect;
use App\Mail\ProfileConfirmationMail;
use App\Services\Queue\EmailQueueService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;


class ClientProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
         $bigid = Session::get('user_info')['bigid'];
         $user = User::with('options')->where('bigid', $bigid)->first();
         $ips = ItaggIpSecurity::where('users_bigid', $bigid)->pluck('ip');
         $get_description = $user->options->first();

         return view('customer.profile.index', compact('bigid','user','ips','get_description'));
        } else {
            return redirect('/')->with('error', 'You need to log in to access this page.');
        }
        
    }


    public function submitProfile(Request $request)
    {
        
        // Retrieve user information from session
        $userInfo = Session::get('user_info');
        if (isset($userInfo['bigid'])) {
            $bigid = $userInfo['bigid'];
            $user = User::with('options')->where('bigid', $bigid)->first();
            $get_description = $user->options->first();
        }
  
        // Gather the data from the request
        $data = $request->only([
            'service_description',
            'busname',
            'contactname',
            'address1',
            'address2',
            'town',
            'city',
            'county',
            'pcode',
            'country',
            'mobilenumber',
            'phone',
            'contactemail',
            'defaultsenderid',
            'newpassword1',
            'newpassword2',
            'iplist'
        ]);
        

        $successMessages = [];
        $errorMessages = [];

        // Check if profile details have changed
        if (
            $data['service_description'] != $get_description->explanation ||
            $data['busname'] != $user->busname ||
            $data['contactname'] != $user->contactname ||
            $data['address1'] != $user->address1 ||
            $data['address2'] != $user->address2 ||
            $data['town'] != $user->town ||
            // $data['city'] != $user->city ||
            // $data['county'] != $user->county ||
            $data['pcode'] != $user->pcode ||
            $data['country'] != $user->country ||
            $data['mobilenumber'] != $user->mobilenumber ||
            $data['phone'] != $user->phone ||
            $data['contactemail'] != $user->contactemail ||
            (!empty($data['newpassword1']) && !empty($data['newpassword2']) && $data['newpassword1'] != $user->pword)
        ) {
            if(!empty($user->contactemail)){
               $toAddress = $user->contactemail;
            }
            else {
                $toAddress = $data['contactemail'];
            }
           
            $ccAddress = '';
            $ipString = !empty($data['iplist']) ? implode(',', $data['iplist']) : '';
    
            try {

                // If there were any profile changes, insert into the itagg_profilepending table
                $confirmationToken = md5(uniqid(rand(), 1));
    
                DB::table('itagg_profilepending')->insert([
                    'id' => $confirmationToken,
                    'bigid' => $userInfo['bigid'],
                    'busname' => urlencode($data['busname']),
                    'contactname' => urlencode($data['contactname']),
                    'address1' => urlencode($data['address1']),
                    'address2' => urlencode($data['address2']),
                    'town' => urlencode($data['town']),
                    // 'city' => urlencode($data['city']),
                    // 'county' => urlencode($data['county']),
                    'pcode' => urlencode($data['pcode']),
                    'country' => urlencode($data['country']),
                    'mobilenumber' => urlencode($data['mobilenumber']),
                    'phone' => urlencode($data['phone']),
                    'contactemail' => urlencode($data['contactemail']),
                    'pword' => urlencode($data['newpassword1'] ?? $user->pword), // Assuming you have a default value for this column
                    'ips' => $ipString , // Store IPs as a comma-separated string
                    'explanation' => urlencode($data['service_description']), // Assuming you have a default value for this column
                ]);

                DB::table('useroption')
                ->where('userref',  $userInfo['bigid'])
                ->update(['profileupdate_lockout' => '1']);

                // useroption changed → rebuild this account's cache (Phase 2).
                app(\App\Services\TableCache::class)->rebuildUseroption($userInfo['bigid']);

                // Send confirmation email via RabbitMQ queue
                $emailQueueService = new EmailQueueService();
                $emailQueueService->queueEmail(
                    'App\\Mail\\ProfileConfirmationMail',
                    $toAddress,
                    [
                        'to_address' => $toAddress,
                        'cc_address' => $ccAddress,
                        'form_data' => $data,
                        'confirmation_token' => $confirmationToken,
                    ],
                    $ccAddress ? [$ccAddress] : []
                );
                $successMessages[] = 'Confirmation email has been sent.';
                return redirect()->route('profile.lock');
            } catch (Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                $errorMessages[] = 'Failed to send email. Please try again.';
            }
        }
    
        // Check if "defaultsenderid" has changed
        if ($data['defaultsenderid'] != $get_description->defaultsenderid) {
            $get_description->defaultsenderid = $data['defaultsenderid'];
            $get_description->save();
            $successMessages[] = 'Sender Id updated successfully.';
        } else {
            $errorMessages[] = 'No changes detected.';
        }

        // Determine the redirect based on outcomes
        if (!empty($successMessages)) {
            return redirect()->back()->with('success', implode(' ', $successMessages));
        }
    
        if (!empty($errorMessages)) {
            return redirect()->back()->with('error', implode(' ', $errorMessages));
        }
    
        return redirect()->back()->with('info', 'No changes detected.');
    }


    public function confirmProfile($token)
    {
        // Find the pending profile changes by the token
        $pendingProfile = ItaggProfilePending::where('id', $token)->first();
    
        if (!$pendingProfile) {
            return redirect()->route('profile.linkExpired');
        }
    
        // Find the user based on 'bigid' from the pending changes
        // $user = User::where('bigid', $pendingProfile->bigid)->first();
        $user = User::with('options')->where('bigid', $pendingProfile->bigid)->first();
    
        if (!$user) {
            return redirect()->route('profile.linkExpired');
        }
    
        // OLD-SYSTEM PARITY (cp2_profile.inc:216-223): a change to a "key element"
        // — contact name or business name — forces a contract re-sign. Capture this
        // BEFORE applying the update, while $user still holds the old values.
        $resignContracts = (urldecode($pendingProfile->contactname) != $user->contactname)
                        || (urldecode($pendingProfile->busname) != $user->busname);

        // Update the user's profile with the pending data
        $user->update([
            'busname' => urldecode($pendingProfile->busname),
            'contactname' => urldecode($pendingProfile->contactname),
            'address1' => urldecode($pendingProfile->address1),
            'address2' => urldecode($pendingProfile->address2),
            'town' => urldecode($pendingProfile->town),
            // 'city' => urldecode($pendingProfile->city),
            // 'county' => urldecode($pendingProfile->county),
            'pcode' => urldecode($pendingProfile->pcode),
            'country' => urldecode($pendingProfile->country),
            'mobilenumber' => urldecode($pendingProfile->mobilenumber),
            'phone' => urldecode($pendingProfile->phone),
            'contactemail' => urldecode($pendingProfile->contactemail),
            'pword' =>  urldecode($pendingProfile->pword),
        ]);

        DB::table('useroption')
        ->where('userref',  $pendingProfile->bigid)
        ->update(['profileupdate_lockout' => '0']);

        // Force a contract re-sign if a key element changed — mirrors the legacy
        // ContractManager gate: agreedcontracts = NULL + a dated reason. The
        // gatekeeper middleware then routes the customer to the contracts page,
        // and signing clears both columns again.
        if ($resignContracts) {
            DB::table('useroption')
                ->where('userref', $pendingProfile->bigid)
                ->update([
                    'agreedcontracts'             => null,
                    'agreedcontracts_description' => now('Europe/London')->format('dMy') . ' : Key elements of your profile have been changed',
                ]);
        }

        // useroption changed (lockout / contracts) → rebuild this account's cache (Phase 2).
        app(\App\Services\TableCache::class)->rebuildUseroption($pendingProfile->bigid);

        // Now update the 'explanation' column in the related options table
        if ($user->options) {
            $user->options->first()->update([
                'explanation' => urldecode($pendingProfile->explanation),
            ]);
        }

        //ips update
        $ips = explode(',', $pendingProfile->ips); // Split the IPs into an array

        // Get the list of selected IPs (trim each IP to ensure no extra spaces)
        $selectedIps = array_map('trim', $ips);

        // Get the current IPs stored in the database for this user (by bigid)
        $currentIps = DB::table('itagg_ipsecurity')
                        ->where('users_bigid', $pendingProfile->bigid)
                        ->pluck('ip')
                        ->toArray();

        // Delete the IPs that are no longer in the selected list
        DB::table('itagg_ipsecurity')
            ->where('users_bigid', $pendingProfile->bigid)
            ->whereNotIn('ip', $selectedIps)
            ->delete();

        // Insert new IPs that don't already exist in the database
        foreach ($selectedIps as $ip) {
            if (!in_array($ip, $currentIps)) {
                DB::table('itagg_ipsecurity')->insert([
                    'users_bigid' => $pendingProfile->bigid,
                    'ip' => $ip,
                ]);
            }
        }

        // foreach ($ips as $ip) {
        //     DB::table('itagg_ipsecurity')->insert([
        //         'users_bigid' => $pendingProfile->bigid,
        //         'ip' => trim($ip), // Insert each IP
        //     ]);
        // }
    
        // Delete the pending record after successful update
        $pendingProfile->delete();
    
        return redirect()->route('profile.update.confirm');
        
    }


    public function ProfileLock()
    {
        $userInfo = Session::get('user_info');

        if (!isset($userInfo['bigid'])) {
            return redirect('/');
        }

        $bigid = $userInfo['bigid'];
        $user  = User::with('options')->where('bigid', $bigid)->first();

        // The lock page is otherwise a STATIC view — refreshing /profile_lock always
        // rendered "locked" regardless of the DB. Re-check the live flag: if it has
        // already been cleared (customer confirmed, or an admin unlocked), send them
        // to the dashboard instead of leaving them stuck on this page.
        $locked = DB::table('useroption')->where('userref', $bigid)->value('profileupdate_lockout');
        if ($locked != '1') {
            return redirect()->route('dashboard');
        }

        return view('customer.profile_lock.lock_page', compact('bigid', 'user'));
    }

    public function linkExpired()
    {
       return view('customer.profile_lock.profile_expired');
    }

    public function confirmProfileUpdate()
    {
        // Show the confirmation view
        return view('customer.profile_lock.profile_confirm');

    }


    

    /**
     * Show the form for creating a new resource.
     */
    // public function create()
    // {
    //     //
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
