<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Mail\AdminSendLogin;
use App\Services\Queue\EmailQueueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\UserNote;
use App\Models\UserReminder;

class AdminEmailController extends Controller
{
    public function sendLogin($id)
    {
        $user = User::find($id);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        // Check if contact email exists
        if (empty($user->contactemail)) {
            return back()->with('error', 'User does not have a contact email.');
        }
        $ccEmail = '';

        // Send login email via RabbitMQ queue
        $emailQueueService = new EmailQueueService();
        $emailQueueService->queueEmail(
            'App\\Mail\\AdminSendLogin',
            $user->contactemail,
            [
                'user' => $user->toArray(),
                'cc_email' => $ccEmail,
            ],
            $ccEmail ? [$ccEmail] : []
        );

        // Update the user's forgot password flags
        $user->update([
            'forgot_password1_link' => 0,
            'forgot_password1_submit' => 0,
        ]);

        return back()->with('success', 'Login details sent to ' . $user->contactemail);
    }

    public function killUser(Request $request)
    {
        $username = $request->query('username');
        $userbigid = $request->query('userbigid');
        $killrecord = $request->query('killrecord');

        if ($killrecord !== 'ohyes') {
            return back()->with('error', 'Invalid kill command.');
        }

        // Fetch user
        $user = User::where('bigid', $userbigid)->where('uname', $username)->first();
        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        // Store old values
        $smsg_walletold = $user->smsg_wallet;
        $smsg_server1_sentold = $user->smsg_server1_sent;
        $smsg_server2_sentold = $user->smsg_server2_sent;
        $walletloanold = $user->walletloan;

        // Create removal notes
        $removalnotes = now()->format('j/n/y g:ia') . " Auto: \n\nKilled Account.\n\nwallet: smsg_wallet: $smsg_walletold, smsg_server1_sent: $smsg_server1_sentold, smsg_server2_sent: $smsg_server2_sentold, walletloan: $walletloanold";

        // Insert user note
        UserNote::create([
            'users_bigid'     => $userbigid,
            'notes'           => $removalnotes,
            'staffinitials'   => 'Steve Procter',
            'nextcontactdate' => '202601012100',
            'timelength'      => '10',
        ]);

        // Delete unwanted notes
        UserNote::where('users_bigid', $userbigid)
            ->where(function ($query) {
                $query->whereIn('notes', [
                    'usr', 'usr2', 'usr3', 'usr4', 'usr5', 'usr6', 'usr7', 'usr8', 'usr9', 'usr10', 'usr11', 'usr12',
                    'nousr', 'nousr2', 'nousr3', 'nousr4', 'nousr5', 'nousr6', 'nousr7', 'nousr8', 'nousr9', 'nousr10', 'nousr11', 'nousr12'
                ])
                    ->orWhere('notes', 'like', 'ust%')
                    ->orWhere('notes', 'like', 'noust%');
            })->delete();

        UserNote::where('users_bigid', $userbigid)
            ->whereIn(DB::raw('RIGHT(nextcontactdate, 4)'), ['2000', '2100', '1938', '0600'])
            ->whereIn('notes', ['CONTACT (VERY URGENT)', 'follow'])
            ->delete();

        // Update user record
        $user->update([
            'bulk_throughput'     => 0,
            'smsg_wallet'         => 0,
            'platinumaccess'      => 'n',
            'bit_disabled'        => 1,
            'platkeywordwallet'   => 0,
            'shopify_cust_id'     => '',
        ]);

        // Update user reminders
        UserReminder::where('usersbigidref', $userbigid)->update(['reminderon' => 'n']);

        return back()->with('success', 'User killed and notes cleaned.');
    }


    public function killClose(Request $request)
    {
        $userbigid = $request->get('userbigid');
        $username = $request->get('username');

        if ($request->get('killrecord') === 'ohyes') {
            $user = User::where('bigid', $userbigid)->where('uname', $username)->first();

            if (!$user) {
                return back()->with('error', 'User not found.');
            }


            // Backup old values
            $smsg_walletold = $user->smsg_wallet;
            $smsg_server1_sentold = $user->smsg_server1_sent;
            $smsg_server2_sentold = $user->smsg_server2_sent;
            $walletloanold = $user->walletloan;

            // Log note
            $removalnotes = now()->format("j/n/y g:ia") . " Auto: \n\nKilled Account.\n\nwallet: smsg_wallet: $smsg_walletold, smsg_server1_sent: $smsg_server1_sentold, smsg_server2_sent: $smsg_server2_sentold, walletloan: $walletloanold";

            UserNote::create([
                'users_bigid' => $userbigid,
                'notes' => $removalnotes,
                'staffinitials' => 'Steve Procter',
                'nextcontactdate' => '202601012100',
                'timelength' => '10',
            ]);

            // Delete specific notes
            UserNote::where('users_bigid', $userbigid)
                ->where(function ($q) {
                    $q->whereIn('notes', [
                        'usr', 'usr2', 'usr3', 'usr4', 'usr5', 'usr6', 'usr7', 'usr8', 'usr9', 'usr10', 'usr11', 'usr12',
                        'nousr', 'nousr2', 'nousr3', 'nousr4', 'nousr5', 'nousr6', 'nousr7', 'nousr8', 'nousr9', 'nousr10', 'nousr11', 'nousr12'
                    ])
                        ->orWhere('notes', 'like', 'ust%')
                        ->orWhere('notes', 'like', 'noust%');
                })
                ->delete();

            UserNote::where('users_bigid', $userbigid)
                ->whereIn(DB::raw('RIGHT(nextcontactdate, 4)'), ['2000', '2100', '1938', '0600'])
                ->whereIn('notes', ['CONTACT (VERY URGENT)', 'follow'])
                ->delete();

            // Update user
            $user->update([
                'bulk_throughput'     => 0,
                'smsg_wallet'         => 0,
                'platinumaccess'      => 'n',
                'bit_disabled'        => 1,
                'platkeywordwallet'   => 0,
                'shopify_cust_id'     => '',
            ]);

            // Disable reminders
            UserReminder::where('usersbigidref', $userbigid)->update(['reminderon' => 'n']);
        }

        return redirect()->route('admin.users')->with('success', 'User killed successfully.');
    }


    public function trackWallet(Request $request)
    {
        $username = $request->query('username');
        $userbigid = $request->query('userbigid');
        $newtrackwallet = $request->query('trackwallet');

        if (!in_array($newtrackwallet, ['y', 'n'])) {
            return back()->with('error', 'Invalid trackwallet value.');
        }

        $user = User::where('uname', $username)
            ->where('bigid', $userbigid)
            ->first();

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        $user->trackwallet = $newtrackwallet;
        $user->save();

        return back()->with('success', 'Wallet track status updated.');
    }

    public function toggleAccountManager(Request $request)
    {
        // $request->validate([
        //     'userbigid' => 'required|numeric',
        //     'changeacntmgr' => 'required|in:enable,disable',
        //     'username' => 'required|string'
        // ]);

        $user = User::where('bigid', $request->userbigid)->first();

        if (!$user) {
            return redirect()->back()->with('error', 'User not found');
        }

        if ($request->changeacntmgr === 'enable') {
            $user->masteruname = $user->uname;
        } else {
            $user->masteruname = '';
        }

        $user->save();

        return redirect()->back()->with('success', 'Account manager status updated.');
    }
}
