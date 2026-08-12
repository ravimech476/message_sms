<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserReminder;
use App\Models\UserOption;
use App\Models\UserNote;
use App\Models\SmsUserRoute;
use App\Models\AffiliateInvite;
use Illuminate\Support\Facades\DB;
use App\Models\ItaggInstance;
use Carbon\Carbon;
use App\Models\CustomerFlagLog;
use Illuminate\Support\Facades\Auth;


class AddCustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.customer.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // OLD SYSTEM: Only BigID, Username, Password are required
        // All other fields are optional
        $request->validate([
            'newuserid' => 'required|string|max:255',
            'newusr' => 'required|string|max:255',
            'newpwd' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {

            UserReminder::create([
                'usersbigidref' => $request->newuserid,
                'reminderon' => 'n',
            ]);

            UserOption::create([
                'userref' => $request->newuserid,
                'api_premrate_blocked' => 1,
                'can_use_location_lookup_api' => 'no',
                'explanation' => 'My SMS Expert Account.',
                'sdf_lastupdated' => now()->format('Y-m-d'),
                'agreedcontracts' => now()->format('Y-m-d'),
            ]);

            do {
                $newinvitecode = strtoupper(substr(md5(uniqid()), 0, 5));
                $exists = AffiliateInvite::where('icode', $newinvitecode)->exists();
            } while ($exists);

            AffiliateInvite::create([
                'assigned_userref' => $request->newuserid,
                'icode' => $newinvitecode,
                'codenote' => 'First code for new client created in Inspector',
                'subdomain' => $newinvitecode,
            ]);

            $signip = $_SERVER['REMOTE_ADDR'];

            // Generate unique nfckey (like old system)
            do {
                $nfckey = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                $nfcExists = User::where('nfckey', $nfckey)->exists();
            } while ($nfcExists);

            User::create([
                'bigid' => $request->newuserid,
                'uname' => $request->newusr,
                'pword' => $request->newpwd,
                'first_ip' => $signip,
                'contactname' => urlencode($request->contactname ?? ''),
                'busname' => urlencode($request->businessname ?? ''),
                'contactemail' => $request->theemail ?? '',
                'phone' => $request->thephone ?? '',
                'mobilenumber' => $request->themobile ?? '',
                'isnfcuser' => $request->theisnfcuser == 'y' ? 'y' : 'n',
                'nfckey' => $nfckey,
                'affiliateinvitecode' => $newinvitecode,
                'introducerref' => $request->theintroducercode ?? '',
                'user_type' => 'bespoke',
                'lab2id' => $request->thelab2id ?? '',
                'datejoined' => now()->timestamp,
                'datefrozen' => now()->addYear()->timestamp,
                'bit_disabled' => 0,
                'login_type' => 'customer',
                // OLD-system convention (its checks are case-sensitive):
                // Postpaid is stored as exactly 'Postpaid'; Prepaid is stored
                // as EMPTY STRING '' (the old system never stores 'Prepaid').
                'customer_type' => strtolower(trim($request->customer_type ?? '')) === 'postpaid' ? 'Postpaid' : '',
                'migration_flag' => 'new',

                // OLD SYSTEM: Wallet and pricing fields initialization (matching gajkdhgfajdhgfasjgfasjdhfgahdgfa.php)
                'smsg_wallet' => 0,
                'smsg_server1_sent' => 0,
                'smsg_server2_sent' => 0,
                'platkeywordwallet' => 0,
                'bulk_throughput' => 0,
                'premium_throughput' => 0,
                'daemonpriority' => 400,
                'platinumaccess' => 'y',
                'dashboardaccess' => 'mc',
                'masteruname' => $request->newusr,
                'clientcommstatus' => 'cool',

                // OLD SYSTEM: Route configuration (3 route sets)
                'chargetype1' => 'pps',
                'routetag' => 'd',
                '1s_preferredroute' => 7002,

                'chargetype2' => 'pps',
                'routetag2' => '0',
                'preferredroute2' => 0,

                'chargetype3' => 'pps',
                'routetag3' => '0',
                'preferredroute3' => 0,
            ]);

            $newuserid = $request->newuserid;
            $newstafftypesql = $request->newstafftype ?? 'usr'; // Default staff type

            // Default onboarding SMS rate for a NEW customer's UK routes. Was 0.03 (below our
            // £0.0457 cost price — see client request). Reads the admin-configurable onboarding
            // rate from Settings → Pricing ('onboarding_sms_rate'), else falls back to 0.0457.
            $defaultSmsRate = (float) \App\Models\CustomerSetting::getValue('onboarding_sms_rate', 0.0457);

            // Store SMS User Routes (OLD SYSTEM pricing logic using smsg_userroute table)
            // This matches the old Core PHP system's INSERT logic from gajkdhgfajdhgfasjgfasjdhfgahdgfa.php
            $routes = [
                ['routenum' => 7002, 'countrydialcode' => '44', 'numbits' => 7, 'origtype' => 'alpha', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7002, 'countrydialcode' => '44', 'numbits' => 7, 'origtype' => 'msisdn', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7002, 'countrydialcode' => '44', 'numbits' => 7, 'origtype' => 'shortcode', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7002, 'countrydialcode' => '44', 'numbits' => 8, 'origtype' => 'alpha', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7002, 'countrydialcode' => '44', 'numbits' => 8, 'origtype' => 'msisdn', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7002, 'countrydialcode' => '44', 'numbits' => 8, 'origtype' => 'shortcode', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7029, 'countrydialcode' => '44', 'numbits' => 7, 'origtype' => 'alpha', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7029, 'countrydialcode' => '44', 'numbits' => 7, 'origtype' => 'msisdn', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7029, 'countrydialcode' => '44', 'numbits' => 7, 'origtype' => 'shortcode', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7029, 'countrydialcode' => '44', 'numbits' => 8, 'origtype' => 'alpha', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7029, 'countrydialcode' => '44', 'numbits' => 8, 'origtype' => 'msisdn', 'userprice' => $defaultSmsRate, 'priority' => 1],
                ['routenum' => 7029, 'countrydialcode' => '44', 'numbits' => 8, 'origtype' => 'shortcode', 'userprice' => $defaultSmsRate, 'priority' => 1],
            ];

            foreach ($routes as $route) {
                SmsUserRoute::create([
                    'userref' => $newuserid,
                    'username' => 'special rate users',
                    'routenum' => $route['routenum'],
                    'countrydialcode' => $route['countrydialcode'],
                    'numbits' => $route['numbits'],
                    'origtype' => $route['origtype'],
                    'userprice' => $route['userprice'],
                    'priority' => $route['priority']
                ]);
            }

            // OLD SYSTEM: Insert users_notes records (matching gajkdhgfajdhgfasjgfasjdhfgahdgfa.php)
            // First note: staff type record
            UserNote::create([
                'users_bigid' => $newuserid,
                'notes' => $newstafftypesql,
                'staffinitials' => 'scp',
                'nextcontactdate' => '202601012100',
                'timelength' => '10',
                'insertdate' => '2025-01-01 00:00:00',
            ]);

            // Second note: follow-up record
            $nextcontactdate1 = Carbon::now()->format('Ymd') . '1938';
            UserNote::create([
                'users_bigid' => $newuserid,
                'notes' => 'follow',
                'staffinitials' => 'scp',
                'nextcontactdate' => $nextcontactdate1,
                'timelength' => '10',
            ]);

            DB::commit();

            return redirect()->route('admin.users')->with('success', 'New customer record saved successfully.');

            // return redirect()->back()->with('success', 'New client/lead record saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to save customer record.');
        }
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
        $user = User::findOrFail($id);

        // $request->validate([
        //     'busname' => 'required|string|max:200',
        //     'contactname' => 'required|string|max:50',
        //     'contactemail' => 'required|email|max:50',
        //     'phone' => 'nullable|string|max:50',
        //     'mobilenumber' => 'nullable|string|max:50',
        // ]);

        // Update user record.
        // busname/contactname are stored URL-encoded to match the create path
        // (AddCustomerController@store) and the legacy convention; the profile tab
        // urldecode()s them for display, so this keeps the round-trip consistent.
        $user->busname = urlencode($request->busname ?? '');
        $user->contactname = urlencode($request->contactname ?? '');
        $user->contactemail = $request->contactemail;
        $user->phone = $request->phone;
        $user->mobilenumber = $request->mobilenumber;
        // Only update customer_type when the submitting form actually sent it —
        // showo.blade.php posts to this same route WITHOUT the field, and the
        // old unguarded assignment wiped the value to NULL (which the OLD
        // system's case-sensitive == 'Postpaid' check then read as Prepaid).
        // OLD-system storage convention: Postpaid = exactly 'Postpaid';
        // Prepaid = EMPTY STRING '' (the old system never stores 'Prepaid').
        if ($request->filled('customer_type')) {
            $user->customer_type = strtolower(trim($request->customer_type)) === 'postpaid' ? 'Postpaid' : '';
        }
        $user->address1 = $request->address1;
        $user->address1  = $request->address1;
        $user->address2  = $request->address2 ?? '';
        $user->town = $request->town;
        // $user->city = $request->city ;
        //  $user->county' => $request->county ;
        $user->pcode = $request->pcode;
        $user->country = $request->country;
        // OLD SYSTEM parity (accountmanager gajkdhgfa...php): "Web (alt)" -> users.website,
        // "Emails (extra)" -> users.contactemail2. Stored plain (the old system trim()s, no urlencode).
        $user->website = $request->webalt ?? '';
        $user->contactemail2 = $request->contactemail2 ?? '';


        if ($request->input('changenewrate') === 'y') {
            $newrate = $request->input('newrate');

            $user->newrate = $newrate === '' ? 0 : $newrate;
        }


        // lab2id
        if ($request->input('changelab2id') === 'y') {
            $user->lab2id = $request->input('newlab2id');
        }

        // custtype
        if ($request->input('changecusttype') === 'y') {
            $user->custtype = $request->input('newcusttype');
        }

        // anondesc (we’ll urlencode on save to mimic legacy)
        if ($request->input('changeanondesc') === 'y') {
            $user->anondesc = urlencode($request->input('newanondesc'));
        }

        // shopify customer ID
        if ($request->input('changeshopifycustid') === 'y') {
            $user->shopify_cust_id = $request->input('newshopifycustid');
        }

        // daemon priority
        if ($request->input('changeformdaemonpriority') === 'y') {
            $user->daemonpriority = $request->input('newvaluedaemonpriority');
        }

        // dashboard access
        if ($request->input('changeformdashboardaccess') === 'y') {
            $user->dashboardaccess = $request->input('newvaluedashboardaccess', '');
        }

        // Save changes
        $user->save();

        return redirect()->route('admin.user.show', $user->id)->with('success', 'Customer updated successfully.');
        // return redirect()->route('admin.users')->with('success', 'Client updated successfully.');
        // return redirect()->back()->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function flagUpdate(Request $request, $id)
    {
        $request->validate([
            'migration_flag' => 'required|in:old,new'
        ]);

        $user = User::findOrFail($id);

        $oldFlag = $user->migration_flag;
        $newFlag = $request->migration_flag;

        // No change — nothing to do.
        if ($oldFlag === $newFlag) {
            return redirect()->back()
                ->with('success', 'Migration flag updated successfully.')
                ->with('activeTab', 'customer-current-flag');
        }

        // The customer's users row can be held by another (long/idle) transaction. The old plain
        // $user->save() then waited innodb_lock_wait_timeout (~50s) and threw SQLSTATE 1205, which
        // surfaced to the admin as an Internal Server Error on this line. We now:
        //   - apply the whole flag change ATOMICALLY (flag log + scheduled smsg_log + users) so a
        //     failure never leaves a half-migrated customer;
        //   - FAIL FAST (short lock wait) instead of hanging ~50s;
        //   - RETRY a few times (the lock is usually transient);
        //   - and, if the row is still busy, show a clean "try again" message, not a stack trace.
        $maxAttempts = 3;

        try {
            // Fail fast rather than waiting the default ~50s on a contended users row.
            try { DB::statement('SET innodb_lock_wait_timeout = 3'); } catch (\Throwable $ignore) {}

            for ($attempt = 1; ; $attempt++) {
                try {
                    DB::transaction(function () use ($user, $oldFlag, $newFlag, $request) {
                        // Flag change audit log.
                        CustomerFlagLog::create([
                            'customer_id' => $user->id,
                            'bigid'       => $user->bigid,
                            'old_flag'    => $oldFlag,
                            'new_flag'    => $newFlag,
                            'changed_by'  => Auth::id(),
                            'changed_ip'  => $request->ip(),
                            'changed_at'  => Carbon::now('Europe/London')->format('YmdHis'),
                        ]);

                        // NOTE: intentionally do NOT touch smsg_log here. Migrating a customer only
                        // changes their account routing flag; existing smsg_log rows keep their own
                        // migration_flag (per requirement — the flag change must not rewrite message rows).

                        // The actual users.migration_flag change (the statement that was timing out).
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['migration_flag' => $newFlag]);
                    });

                    return redirect()->back()
                        ->with('success', 'Migration flag updated successfully.')
                        ->with('activeTab', 'customer-current-flag');

                } catch (\Illuminate\Database\QueryException $e) {
                    // 1205 = lock wait timeout, 1213 = deadlock — both transient. Retry a couple of times.
                    $sqlErrno = $e->errorInfo[1] ?? null;
                    if (in_array($sqlErrno, [1205, 1213], true) && $attempt < $maxAttempts) {
                        usleep(300000); // 0.3s backoff, then retry a fresh transaction
                        continue;
                    }

                    \Illuminate\Support\Facades\Log::warning('flagUpdate: users row busy - migration not applied', [
                        'customer_id' => $user->id,
                        'bigid'       => $user->bigid,
                        'attempts'    => $attempt,
                        'sql_errno'   => $sqlErrno,
                        'error'       => $e->getMessage(),
                    ]);

                    return redirect()->back()
                        ->with('error', 'This customer record is currently busy (another operation is updating it), so the migration was not applied. No change was made - please try again in a moment.')
                        ->with('activeTab', 'customer-current-flag');
                }
            }
        } finally {
            // Restore normal lock-wait behaviour for any later queries on this connection.
            try { DB::statement('SET innodb_lock_wait_timeout = @@GLOBAL.innodb_lock_wait_timeout'); } catch (\Throwable $ignore) {}
        }
    }

    public function createKeyword(Request $request, string $id)
    {
        $get_user_id = $id;
        return view('admin.keyword.create', compact('get_user_id'));
    }

    public function storeKeyword(Request $request)
    {
        $nextcontactaboutrenewal = Carbon::now()->addDays(7)->format('Ymd');

        $user = User::findOrFail($request->userid);

        $record = new ItaggInstance();
        $record->users_bigid = $user->bigid;
        $record->keyword = $request->demokeyword;
        $record->purchased = $request->keywordstartdate;
        $record->expiry = $request->keywordenddate;
        $record->nextcontactaboutrenewal = $nextcontactaboutrenewal;
        $record->keylevel = '2011+';
        $record->forwarding_email = $request->theemail;
        $record->response_sender_id = 'iTAGG';
        $record->response_content = 'This is a demo auto-response. You can set your own response as required. Regards, iTAGG.';
        $record->smsshortcodes_id = 45;
        $record->itagg_type_id = 3;
        $record->max_subkeywords = 0;
        $record->itagg_purchasetype_id = 1;
        $record->active = 1;
        $record->modules_enabled = 3;
        $record->module_restrict = 319;
        $record->save();

        // OLD SYSTEM parity: registering a keyword consumes one from the customer's keyword
        // wallet (infopage_include_detail2.inc:8657 — "platkeywordwallet = platkeywordwallet - 1"),
        // so their "remaining un-registered keywords" (platkeywordwallet / platkeywordcost) goes
        // down. The customer-facing register flow already does this; mirror it here for the admin
        // "Add Keyword" path. Guard so it can't go negative.
        if (($user->platkeywordwallet ?? 0) > 0) {
            $user->decrement('platkeywordwallet', 1);
        }

        // Store active tab in session
        session()->flash('activeTab', 'customer-keywords');

        return redirect()->route('admin.user.show', $request->userid)->with('success', 'New keyword created successfully.');
        // return redirect()->route('keywords.view', $user->bigid)->with('success', 'New keyword record saved successfully.');
        // return redirect()->back()->with('success', 'New keyword record saved successfully.');
    }

    public function viewKeyword($bigid)
    {
        // Fetch all keywords for the given users_bigid
        // $keywords = ItaggInstance::where('users_bigid', $bigid)->get();
        $keywords = ItaggInstance::where('users_bigid', $bigid)
            ->where('status', 1)
            ->with('smsshortcode')
            ->get();

        $user = User::where('bigid', $bigid)->first();

        return view('admin.keyword.index', compact('keywords', 'bigid', 'user'));
    }
    // Show Edit Form
    public function editKeyword($id)
    {
        $keyword = ItaggInstance::findOrFail($id);
        return view('admin.keyword.show', compact('keyword'));
    }

    // Update Keyword
    public function updateKeyword(Request $request, $id)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
            'purchased' => 'required|date',
            'expiry' => 'required|date',
        ]);

        $keyword = ItaggInstance::findOrFail($id);
        $nextcontactaboutrenewal = Carbon::now()->addDays(7)->format('Ymd');
        $keyword->keyword = $request->keyword;
        $keyword->purchased = $request->purchased;
        $keyword->forwarding_email = $request->theemail;
        $keyword->expiry = $request->expiry;
        $keyword->nextcontactaboutrenewal = $nextcontactaboutrenewal;
        $keyword->save();

        $userid = User::where('bigid', $keyword->users_bigid)->first();

        // Store active tab in session
        session()->flash('activeTab', 'customer-keywords');

        return redirect()->route('admin.user.show', $userid)->with('success', 'Keyword updated successfully.');
        // return redirect()->route('keywords.view', $keyword->users_bigid)->with('success', 'Keyword updated successfully.');

        // return redirect()->route('keyword.edit', $id)->with('success', 'Keyword updated successfully.');
    }

    // Delete Keyword
    public function destroyKeyword($id)
    {
        $keyword = ItaggInstance::findOrFail($id);
        $keyword->delete();

        // Store active tab in session
        session()->flash('activeTab', 'customer-keywords');

        return redirect()->back()->with('success', 'Keyword deleted successfully.');
    }
}
