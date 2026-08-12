<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;
use App\Models\ItaggInstance;
use App\Models\SmsShortcode;
use Illuminate\Support\Facades\Session;
use App\Models\UserNote;
use App\Models\UserOption;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use App\Models\IpAddressResstriction;
use App\Models\BlockedUser;
use App\Models\UsersSessionLog;
use Illuminate\Support\Facades\DB;
use App\Models\UserMargin;
use App\Models\Country;
use App\Models\CustomerFlagLog;
use App\Models\CampaignFileMigration;
use App\Services\Queue\CampaignFileMigrationQueueService;
use App\Services\Queue\WebhookUpdateQueueService;




class AdminUserController extends Controller
{
    use LegacyCustomerList;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userInfo = Session::get('user_info');

        if (isset($userInfo['bigid'])) {
            $userref = $userInfo['bigid'];

            $perPage = (int) $request->get('per_page', 25);
            $search = trim((string) $request->get('search', ''));
            $filter = $request->get('filter', 'migrated');

            // Source: legacy OLD SYSTEM customer-listing SQL (shared trait)
            $legacy = $this->getLegacyCustomers();

            // Counts for the migrated/not_migrated tabs are derived from the legacy
            // result set so the tab badges always sum to the legacy total.
            $migratedCount = $legacy->where('migration_flag', 'new')->count();
            $notMigratedCount = $legacy->count() - $migratedCount;

            // Apply tab filter
            $all = $filter === 'not_migrated'
                ? $legacy->filter(fn ($c) => ($c->migration_flag ?? null) !== 'new')->values()
                : $legacy->filter(fn ($c) => ($c->migration_flag ?? null) === 'new')->values();

            if ($search !== '') {
                // Substring match from the FIRST character (no minimum length) so "Mar"
                // finds "Mark". Business/contact names are stored URL-encoded in the DB
                // (e.g. "Mark+test+account+2"), so those fields are urldecoded + whitespace-
                // normalised before matching — otherwise multi-word searches with spaces
                // never match. uname/email are matched as-is (emails may contain "+").
                $needle = preg_replace('/\s+/', ' ', mb_strtolower(trim($search)));

                $matcher = function ($c) use ($needle) {
                    foreach (['uname', 'contactemail'] as $f) {
                        $hay = mb_strtolower(trim((string) ($c->$f ?? '')));
                        if ($hay !== '' && str_contains($hay, $needle)) {
                            return true;
                        }
                    }
                    foreach (['contactname', 'busname'] as $f) {
                        $hay = preg_replace('/\s+/', ' ', mb_strtolower(trim(urldecode((string) ($c->$f ?? '')))));
                        if ($hay !== '' && str_contains($hay, $needle)) {
                            return true;
                        }
                    }
                    return false;
                };

                // 1) Filter the legacy/CRM list.
                $all = $all->filter($matcher)->values();

                // 2) Supplementary search of the users table so SUB-ACCOUNTS (and any
                //    users not in the CRM legacy list) are findable too. Sub-accounts are
                //    only rendered nested under their master, so without this a sub shown
                //    on the page (e.g. "e4ab0498") returns "No customers found" when searched.
                $isNew = $filter !== 'not_migrated';
                $esc = str_replace(['%', '_'], ['\%', '\_'], $needle);
                $like = '%' . $esc . '%';
                $likePlus = '%' . str_replace(' ', '+', $esc) . '%'; // URL-encoded busname/contactname

                $extra = DB::table('users')
                    ->where(function ($q) {
                        $q->whereNull('login_type')->orWhere('login_type', 'customer')->orWhere('login_type', '');
                    })
                    ->when(
                        $isNew,
                        fn ($q) => $q->where('migration_flag', 'new'),
                        fn ($q) => $q->where(fn ($qq) => $qq->where('migration_flag', 'old')->orWhereNull('migration_flag')->orWhere('migration_flag', ''))
                    )
                    ->where(function ($q) use ($like, $likePlus) {
                        $q->whereRaw('LOWER(uname) LIKE ?', [$like])
                          ->orWhereRaw('LOWER(contactemail) LIKE ?', [$like])
                          ->orWhereRaw('LOWER(busname) LIKE ?', [$like])
                          ->orWhereRaw('LOWER(busname) LIKE ?', [$likePlus])
                          ->orWhereRaw('LOWER(contactname) LIKE ?', [$like])
                          ->orWhereRaw('LOWER(contactname) LIKE ?', [$likePlus]);
                    })
                    ->select('id', 'uname', 'contactname', 'busname', 'contactemail', 'bigid', 'masteruname', 'migration_flag', 'customer_type')
                    ->limit(500)
                    ->get();

                // Exact PHP re-filter (decode names) + merge unique by uname.
                $existing = $all->pluck('uname')->map(fn ($u) => mb_strtolower(trim((string) $u)))->flip();
                foreach ($extra as $row) {
                    if (!$matcher($row)) {
                        continue;
                    }
                    $key = mb_strtolower(trim((string) ($row->uname ?? '')));
                    if ($key !== '' && $existing->has($key)) {
                        continue;
                    }
                    $all->push($row);
                    $existing->put($key, true);
                }
                $all = $all->values();
            }

            // Tree top-level: drop sub-accounts whose master is also in this list,
            // so each sub appears ONLY nested under its master (not floating as its
            // own top-level row). Orphan subs — whose master isn't in the list
            // (e.g. master is on the other migration tab) — are kept so nothing is
            // hidden. Matching is case-insensitive on uname/masteruname.
            $listUnames = $all->pluck('uname')
                ->filter()
                ->map(fn ($u) => mb_strtolower(trim((string) $u)))
                ->flip();
            $all = $all->reject(function ($c) use ($listUnames) {
                $mu = mb_strtolower(trim((string) ($c->masteruname ?? '')));
                $un = mb_strtolower(trim((string) ($c->uname ?? '')));
                return $mu !== '' && $mu !== $un && $listUnames->has($mu);
            })->values();

            $totalCount = $all->count();
            $page = max(1, (int) $request->get('page', 1));
            $items = $all->forPage($page, $perPage)->values();

            // Master/Sub-account tree enrichment.
            // A user is a SUB-account when users.masteruname points at another
            // user's uname (same rule the detail page uses). A MASTER is a uname
            // that other users point to. We fetch the sub-accounts for every
            // uname on this page in ONE query, then attach them so the view can
            // render each master with its children nested underneath (tree).
            $pageUnames = $items->pluck('uname')->filter()->values()->all();
            $subsByMaster = collect();
            if (!empty($pageUnames)) {
                $subsByMaster = DB::table('users')
                    ->whereIn('masteruname', $pageUnames)
                    ->whereColumn('masteruname', '!=', 'uname')
                    ->select('id', 'uname', 'busname', 'contactname', 'contactemail', 'masteruname', 'migration_flag', 'customer_type')
                    ->get()
                    ->groupBy('masteruname');
            }
            foreach ($items as $row) {
                $mu = trim((string) ($row->masteruname ?? ''));
                $kids = $subsByMaster->get($row->uname, collect());
                $row->is_sub       = $mu !== '' && $mu !== $row->uname;   // has a parent
                $row->parent_uname = $row->is_sub ? $mu : null;           // parent's uname
                $row->sub_accounts = $kids;                               // child rows
                $row->is_master    = $kids->count() > 0;                  // has children
            }

            $userData = new LengthAwarePaginator(
                $items,
                $totalCount,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('admin.user.index', compact(
                'userData',
                'totalCount',
                'perPage',
                'search',
                'filter',
                'migratedCount',
                'notMigratedCount'
            ));
        }

        return view('admin.user.index', [
            'userData' => collect([]),
            'totalCount' => 0,
            'perPage' => 25,
            'search' => '',
            'filter' => 'migrated',
            'migratedCount' => 0,
            'notMigratedCount' => 0
        ]);
    }

    public function show(Request $request, $id)
    {

        $record = User::findOrFail($id);

        $keywords = ItaggInstance::where('users_bigid', $record->bigid)
            ->where('status', 1)
            ->get();

        // $shortcodes = SmsShortcode::all();
        $shortcodes = SmsShortcode::where('whichoperator', $record->id)->get();

        // Fetch client notes with proper BLOB handling - Latest first
        $client_notes_raw = DB::select(
            "SELECT 
                id,
                users_bigid,
                staffinitials,
                nextcontactdate,
                insertdate,
                CONVERT(notes USING utf8) as notes,
                timelength,
                myinsertdate,
                settonousrprenfc
            FROM users_notes 
            WHERE users_bigid = ? 
            ORDER BY insertdate DESC, id DESC",
            [$record->bigid]
        );

        // Convert to collection for easier handling in the view
        $client_notes = collect($client_notes_raw);

        $invoices = Invoice::select('id', 'userref', 'paiddate', 'easilyamount', 'invoicedate')
            ->with(['orderItems' => function ($query) {
                $query->select('id', 'invoiceref', 'status')
                    ->where('status', 'order');
            }])
            ->where('userref', $record->bigid)
            ->where('paiddate', 0)
            ->whereHas('orderItems', function ($query) {
                $query->where('status', 'order');
            })
            ->orderBy('id', 'desc')
            ->paginate(10);

        $user_options = UserOption::where('userref', $record->bigid)->first();

        $ipAddresses = IpAddressResstriction::where('status', 1)
            ->where('bigid', $record->bigid)
            ->orderBy('created', 'desc')
            ->get();

        $blockedUsers = BlockedUser::where('status', 0)
            ->where('big_id', $record->bigid)
            ->orderByDesc('id')
            ->get();

        $usersSessionLogs = UsersSessionLog::where('status', 0)
            ->where('big_id', $record->bigid)
            ->orderByDesc('id')
            ->get();

        // Get countries for the dropdown with pricing data (including Sinch pricing)
        $countries = Country::select('id', 'name', 'dialcode', 'iso_code', 'cost_per_sms', 'cost_price_eur', 'cost_price_gbp', 'sinch_cost_price_eur', 'sinch_cost_price_gbp', 'sinch_price_updated_at', 'exchange_rate_eur_to_gbp', 'price_update_mode', 'updated_at')
            ->whereNotNull('dialcode')
            ->where('dialcode', '!=', '')
            ->orderBy('name')
            ->get();

        // Get or create user margin
        $userMargin = UserMargin::where('user_id', $id)->first();
        if (!$userMargin) {
            $userMargin = new UserMargin([
                'user_id' => $id,
                'margin_percentage' => 0,
                'is_active' => 1
            ]);
        }

        // Get user-specific rates with country details including cost_per_sms
        $userRates = DB::table('user_cost')
            ->join('country', 'user_cost.country_id', '=', 'country.id')
            ->select(
                'user_cost.*',
                'country.name as country_name',
                'country.dialcode',
                'country.iso_code',
                'country.cost_per_sms'
            )
            ->where('user_cost.bigid', $record->id)
            ->orderBy('country.name')
            ->get()
            ->map(function ($rate) {
                // Convert to object format that matches the blade expectations
                $rate->country = (object)[
                    'name' => $rate->country_name,
                    'dialcode' => $rate->dialcode,
                    'iso_code' => $rate->iso_code,
                    'cost_per_sms' => $rate->cost_per_sms
                ];
                return $rate;
            });

        // Get default rate (you can store this in user options or use a default value)
        $defaultRate = $record->common_sms_rate ?? 0.03;

        // OLD SYSTEM: Get user routes from smsg_userroute table
        $userRoutes = DB::table('smsg_userroute as ur')
            ->leftJoin('smsg_route as r', function($join) {
                $join->on('ur.routenum', '=', 'r.routenum')
                     ->on('ur.countrydialcode', '=', 'r.countrydialcode');
            })
            ->where('ur.userref', $record->bigid)
            ->select(
                'ur.*',
                'r.shortinfo',
                'r.longinfo',
                'r.suppliername',
                'r.costprice'
            )
            ->orderBy('ur.routenum')
            ->orderBy('ur.priority')
            ->get();

        // OLD SYSTEM: Get default routes (userref = '11111111111111111111111111111111')
        $defaultRoutes = DB::table('smsg_userroute as ur')
            ->leftJoin('smsg_route as r', function($join) {
                $join->on('ur.routenum', '=', 'r.routenum')
                     ->on('ur.countrydialcode', '=', 'r.countrydialcode');
            })
            ->where('ur.userref', '11111111111111111111111111111111')
            ->select(
                'ur.*',
                'r.shortinfo',
                'r.longinfo',
                'r.suppliername',
                'r.costprice'
            )
            ->orderBy('ur.routenum')
            ->orderBy('ur.priority')
            ->get();

        // OLD SYSTEM: Get available routes from smsg_route table
        $availableRoutes = DB::table('smsg_route')
            ->where('routestatus', 'live')
            ->select('routenum', 'countrydialcode', 'shortinfo', 'longinfo', 'suppliername', 'costprice')
            ->orderBy('routenum')
            ->distinct()
            ->get();

        $subAccounts = DB::table('users')
            ->where('masteruname', $record->uname)
            ->where('uname', '!=', $record->uname)
            ->select('busname', 'contactname')
            ->get();

        // Fetch migration flag change history
        $flagLogs = CustomerFlagLog::where('customer_id', $record->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->view('admin.user.show', compact(
            'record',
            'keywords',
            'shortcodes',
            'client_notes',
            'invoices',
            'user_options',
            'ipAddresses',
            'blockedUsers',
            'usersSessionLogs',
            'countries',
            'userRates',
            'defaultRate',
            'userMargin',
            'subAccounts',
            'flagLogs',
            'userRoutes',
            'defaultRoutes',
            'availableRoutes'
        ))
            ->header('X-Record-ID', $id);

        // $record = User::findOrFail($id);

        // $keywords = ItaggInstance::where('users_bigid', $record->bigid)->where('status',1)->get();

        // $shortcodes = SmsShortcode::all();

        // $client_notes = UserNote::where('users_bigid', $record->bigid)->get();

        // $invoices = Invoice::with(['orderItems' => function($query) {
        //     $query->whereIn('status', ['invoice', 'order']);
        // }])
        // ->where('userref', $record->bigid)
        // ->where('paiddate', 0)
        // ->get();


        // return response()->view('admin.user.show', compact('record','keywords','shortcodes','client_notes'))
        //     ->header('X-Record-ID', $id);
    }

    /**
     * Update max card/PayPal purchase amount for a user
     */
    public function updateMaxCardPurchase(Request $request, $id)
    {
        // Validate the request
        $request->validate([
            'maxcardpurchase' => 'required|numeric|min:0'
        ]);

        // Get the user
        $user = User::findOrFail($id);

        // Get the user's bigid
        $userBigId = $user->bigid;

        // Update or create the user option record
        $userOption = UserOption::where('userref', $userBigId)->first();

        if ($userOption) {
            // Update existing record
            $userOption->maxcardpurchase = $request->maxcardpurchase;
            $userOption->save();
        } else {
            // Create new record if doesn't exist
            UserOption::create([
                'userref' => $userBigId,
                'maxcardpurchase' => $request->maxcardpurchase
            ]);
        }

        return redirect()->back()->with('success', 'Max Card/PayPal amount updated successfully to £' . number_format($request->maxcardpurchase, 2));
    }

    /**
     * Store a new user rate for a specific country
     */
    public function storeUserRate(Request $request, $id)
    {
        $request->validate([
            'country_id' => 'required|exists:country,id',
            'rate' => 'required|numeric|min:0'
        ]);

        $user = User::findOrFail($id);

        // Check if rate already exists for this country
        $existingRate = DB::table('user_cost')
            ->where('bigid', $user->id)
            ->where('country_id', $request->country_id)
            ->first();

        if ($existingRate) {
            return redirect()->back()->with('error', 'Rate already exists for this country. Please edit the existing rate.');
        }

        // Insert new rate
        DB::table('user_cost')->insert([
            'bigid' => $user->id,
            'country_id' => $request->country_id,
            'rate' => $request->rate,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => auth()->id() ?? null,
            'modified_by' => auth()->id() ?? null
        ]);

        return redirect()->back()->with('success', 'Country rate added successfully.');
    }

    /**
     * Update a user rate
     */
    public function updateUserRate(Request $request, $userId, $rateId)
    {
        $request->validate([
            'rate' => 'required|numeric|min:0'
        ]);

        $user = User::findOrFail($userId);

        DB::table('user_cost')
            ->where('id', $rateId)
            ->where('bigid', $user->id)
            ->update([
                'rate' => $request->rate,
                'updated_at' => now(),
                'modified_by' => auth()->id() ?? null
            ]);

        return redirect()->back()->with('success', 'Rate updated successfully.');
    }

    /**
     * Toggle user rate status
     */
    public function toggleUserRate($userId, $rateId)
    {
        $user = User::findOrFail($userId);

        $rate = DB::table('user_cost')
            ->where('id', $rateId)
            ->where('bigid', $user->id)
            ->first();

        if (!$rate) {
            return redirect()->back()->with('error', 'Rate not found.');
        }

        $newStatus = $rate->status == 1 ? 0 : 1;

        DB::table('user_cost')
            ->where('id', $rateId)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
                'modified_by' => auth()->id() ?? null
            ]);

        return redirect()->back()->with('success', 'Rate status updated successfully.');
    }

    /**
     * Delete a user rate
     */
    public function destroyUserRate($userId, $rateId)
    {
        $user = User::findOrFail($userId);

        DB::table('user_cost')
            ->where('id', $rateId)
            ->where('bigid', $user->id)
            ->delete();

        return redirect()->back()->with('success', 'Rate deleted successfully.');
    }

    /**
     * Update default SMS rate
     */
    public function updateDefaultRate(Request $request, $id)
    {
        $request->validate([
            'default_rate' => 'required|numeric|min:0'
        ]);

        $user = User::findOrFail($id);



        if ($user) {
            $user->common_sms_rate = $request->default_rate;
            $user->save();
        }

        return redirect()->back()->with('success', 'Default SMS rate updated successfully to £' . number_format($request->default_rate, 2));
    }

    /**
     * Store a new client note
     */
    public function storeClientNote(Request $request)
    {
        $request->validate([
            'notes' => 'required|string',
            'users_bigid' => 'required',
            'user_id' => 'required'
        ]);

        // Convert next contact date to YmdHis format if provided
        $nextContactDate = '';
        if ($request->nextcontactdate) {
            $nextContactDate = date('YmdHis', strtotime($request->nextcontactdate . ' 12:00:00'));
        } else {
            $nextContactDate = date('YmdHis', strtotime('+7 days'));
        }

        // Insert the note into users_notes table
        DB::table('users_notes')->insert([
            'users_bigid' => $request->users_bigid,
            'staffinitials' => $request->staffinitials ?? auth()->user()->name ?? 'admin',
            'nextcontactdate' => $nextContactDate,
            'notes' => $request->notes,
            'insertdate' => now(),
            'timelength' => '10',
            'myinsertdate' => date('YmdHis'),
            'settonousrprenfc' => '1'
        ]);

        return redirect()->back()
            ->with('activeTab', 'customer-client-note')
            ->with('success', 'Client note added successfully.');
    }

    /**
     * Delete a client note
     */
    public function destroyClientNote($id)
    {
        DB::table('users_notes')->where('id', $id)->delete();

        return redirect()->back()
            ->with('activeTab', 'customer-client-note')
            ->with('success', 'Client note deleted successfully.');
    }

    /**
     * Toggle WhatsApp enabled/disabled for a user
     */
    public function toggleWhatsApp(Request $request, $id)
    {
        try {
            $request->validate([
                'whatsapp_enabled' => 'required|in:yes,no'
            ]);

            $user = User::findOrFail($id);
            if ($request->whatsapp_enabled == 'yes') {
                $user->whatsapprate = $request->whatsapprate;
            }
            $user->whatsapp_enabled = $request->whatsapp_enabled;

            $user->save();

            $status = $request->whatsapp_enabled === 'yes' ? 'enabled' : 'disabled';

            return response()->json([
                'success' => true,
                'message' => "WhatsApp has been {$status} successfully",
                'whatsapp_enabled' => $request->whatsapp_enabled
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update WhatsApp status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Platinum Keyword Wallet for a user
     */
    public function updatePlatKeywordWallet(Request $request, $id)
    {
        $request->validate([
            'newplatwallet' => 'required|numeric|min:0',
            'changeplatwallet' => 'required|in:y'
        ]);

        try {
            $user = User::findOrFail($id);
            $oldValue = $user->platkeywordwallet ?? 0;
            $newValue = $request->newplatwallet;

            $user->platkeywordwallet = $newValue;
            $user->save();

            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('success', "Platinum Keyword Wallet updated successfully from {$oldValue} to {$newValue}");
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Failed to update Platinum Keyword Wallet: ' . $e->getMessage());
        }
    }

    /**
     * Pricing & Packages — OLD SYSTEM parity for the staff price/access fields on the customer
     * detail page (gajkdhgfajdhgfasjgfasjdhfgahdgfa.php). Saves:
     *   - users.smsinprice       (NFC Starter Pack Price)        — line 2195
     *   - users.silverprice      (Silver Upgrade Price)          — line 2201
     *   - users.goldprice        (Gold Upgrade Price)            — line 2207
     *   - users.platinumprice    (Platinum Upgrade Price)        — line 2213
     *   - itagg_instance.max_subkeywords (Sub Keywords)          — line 2372
     * These are exactly the columns the native Upgrade / NFC / keyword pages read.
     * NOTE: Max Card/Paypal Amount (useroption.maxcardpurchase) is owned by the profile
     * (invoices) tab; Platinum access + Plat Keyword Wallet (users.platinumaccess,
     * platkeywordwallet) are owned by the Wallet Balance tab — not duplicated here.
     */
    public function updateCustomerPricing(Request $request, $id)
    {
        $request->validate([
            'smsinprice'      => 'nullable|numeric|min:0',
            'silverprice'     => 'nullable|numeric|min:0',
            'goldprice'       => 'nullable|numeric|min:0',
            'platinumprice'   => 'nullable|numeric|min:0',
            'subkeywords'     => 'nullable|integer|min:0',
        ]);

        try {
            $user = User::findOrFail($id);

            $user->smsinprice    = $request->input('smsinprice', $user->smsinprice ?? 0);
            $user->silverprice   = $request->input('silverprice', $user->silverprice ?? 0);
            $user->goldprice     = $request->input('goldprice', $user->goldprice ?? 0);
            $user->platinumprice = $request->input('platinumprice', $user->platinumprice ?? 0);
            $user->save();

            // Sub Keywords -> itagg_instance.max_subkeywords (active 60300/80809/sharedvirt keywords),
            // matching OLD SYSTEM line 2372 (smsshortcodes_id IN (16,45,156,160), expiry >= today).
            if ($request->filled('subkeywords')) {
                DB::table('itagg_instance')
                    ->where('users_bigid', $user->bigid)
                    ->whereIn('smsshortcodes_id', [16, 45, 156, 160])
                    ->whereDate('expiry', '>=', now()->toDateString())
                    ->update(['max_subkeywords' => (int) $request->input('subkeywords')]);
            }

            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('success', 'Pricing & package settings updated.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Failed to update pricing: ' . $e->getMessage());
        }
    }

    /**
     * Routing configuration — OLD SYSTEM parity (gajkdhgfajdhgfasjgfasjdhfgahdgfa.php:2040-2082).
     * Three route slots (Charge/Tag/Route/HLR%/HLRUnknwn/randomize) + a 2-way split, saved to
     * the matching users columns. Each column is written only if it exists (Schema-guarded), so
     * this is safe across schema variations.
     */
    public function updateRoutingConfig(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $table = $user->getTable();

            $set = function ($col, $val) use ($user, $table) {
                if ($val !== null && $val !== '' && \Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                    $user->setAttribute($col, $val);
                }
            };

            // Route 1 (1s_*), Route 2, Route 3 — map UI fields to OLD SYSTEM columns.
            $routes = [
                1 => ['route' => '1s_preferredroute', 'hlr' => 'dohlr',  'rand' => 'randomizeroute',  'tag' => 'routetag',  'unk' => 'sendhlrunknowns',  'charge' => 'chargetype1'],
                2 => ['route' => 'preferredroute2',   'hlr' => 'dohlr2', 'rand' => 'randomizeroute2', 'tag' => 'routetag2', 'unk' => 'sendhlrunknowns2', 'charge' => 'chargetype2'],
                3 => ['route' => 'preferredroute3',   'hlr' => 'dohlr3', 'rand' => 'randomizeroute3', 'tag' => 'routetag3', 'unk' => 'sendhlrunknowns3', 'charge' => 'chargetype3'],
            ];
            foreach ($routes as $i => $cols) {
                $set($cols['route'], $request->input("preferredroute{$i}"));
                $set($cols['hlr'],   $request->input("dohlr{$i}"));
                $set($cols['rand'],  $request->input("randomizeroute{$i}"));
                $set($cols['tag'],   $request->input("routetag{$i}"));
                $set($cols['unk'],   $request->input("sendhlrunknowns{$i}"));
                $charge = $request->input("chargetype{$i}");
                if (in_array($charge, ['pps', 'ppd'], true)) {
                    $set($cols['charge'], $charge);
                }
            }

            // Split %
            $set('preferredroutesplit',  $request->input('preferredroutesplit'));
            $set('preferredroutesplit2', $request->input('preferredroutesplit2'));

            $user->save();

            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('success', 'Routing configuration updated.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Failed to update routing configuration: ' . $e->getMessage());
        }
    }

    /**
     * Update sub-account / pooled dedicated virtual number config (OLD SYSTEM parity).
     * Mirrors gajkdhg.php:2241 (changepooledvirts) + 2248 (changemasterintrorefs):
     *   users.subusrkey, numpooledvirtsUK, numpooledvirtsUSA, walletminlevel,
     *   masteruname, introducerref.
     */
    public function updateSubAccountConfig(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $table = $user->getTable();

            $set = function ($col, $val) use ($user, $table) {
                // Pooled/sub-account fields are intentionally writable to blank/0,
                // so only skip when the input key was not submitted at all (null).
                if ($val !== null && \Illuminate\Support\Facades\Schema::hasColumn($table, $col)) {
                    $user->setAttribute($col, $val);
                }
            };

            $set('subusrkey',        $request->input('subusrkey'));
            $set('numpooledvirtsUK', $request->input('numpooledvirtsUK'));
            $set('numpooledvirtsUSA', $request->input('numpooledvirtsUSA'));
            $set('walletminlevel',   $request->input('walletminlevel'));
            $set('masteruname',      $request->input('masteruname'));
            $set('introducerref',    $request->input('introducerref'));

            $user->save();

            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('success', 'Sub-account / pooled number configuration updated.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Failed to update sub-account configuration: ' . $e->getMessage());
        }
    }

    /**
     * Send Quick SMS from the admin customer-detail page (OLD SYSTEM parity —
     * gajkdhg.php:9581 "Send Quick SMS"). Sends NATIVELY via the customer's own
     * account/credentials — their sender ID, their wallet, logged under their
     * smsg_log — by reusing the canonical API send path SMSController::sendSmsApi.
     * (No external/old-system URL: it calls the new system's own send pipeline.)
     */
    public function quickSend(Request $request, $id)
    {
        $request->validate([
            'quick_from' => 'required|string|max:20',
            'quick_to'   => 'required|string|max:40',
            'quick_txt'  => 'required|string',
        ]);

        try {
            $user = User::findOrFail($id);

            if (empty($user->uname) || empty($user->pword)) {
                return redirect()->back()
                    ->with('activeTab', 'customer-keywords')
                    ->with('error', 'Quick SMS failed: this customer has no API username/password configured.');
            }

            // Reuse the canonical native API send path with the CUSTOMER'S credentials,
            // so the message is billed to their wallet and logged under their account.
            $apiRequest = \Illuminate\Http\Request::create('/api/smsg/sms.mes', 'GET', [
                'usr'  => $user->uname,
                'pwd'  => $user->pword,
                'from' => trim($request->input('quick_from')),
                'to'   => trim($request->input('quick_to')),
                'txt'  => $request->input('quick_txt'),
                'type' => 'text',
            ]);

            $response = app(\App\Http\Controllers\SMSController::class)->sendSmsApi($apiRequest);

            // OLD SYSTEM response body: line 1 = header, line 2 = "code|text|ref".
            $body  = method_exists($response, 'getContent') ? $response->getContent() : (string) $response;
            $lines = preg_split('/\r\n|\r|\n/', trim($body));
            $resultLine = $lines[1] ?? ($lines[0] ?? '');
            [$code, $text, $ref] = array_pad(explode('|', $resultLine), 3, '');

            if (trim($code) === '0') {
                return redirect()->back()
                    ->with('activeTab', 'customer-keywords')
                    ->with('success', 'Quick SMS submitted (ref: ' . trim($ref) . ').');
            }

            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Quick SMS failed: ' . trim($text) . ' (code ' . trim($code) . ').');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Quick SMS failed: ' . $e->getMessage());
        }
    }

    /**
     * Platinum Keyword Tools — WHOIS (OLD SYSTEM parity: plat_whois.mes / platinumlib.inc
     * platinum_whois). Checks keyword availability on 60300 for THIS customer. Native —
     * no external secure.itagg.com URL. Shortcode is fixed to 60300 (smsshortcodes_id 16),
     * matching platinum_opener()'s shortcode allow-list.
     */
    public function keywordWhois(Request $request, $id)
    {
        $request->validate([
            'keyword' => 'required|string|min:3|max:16|regex:/^[A-Za-z0-9]+$/',
        ]);

        $keyword = strtolower(trim($request->keyword));

        try {
            $taken = \Illuminate\Support\Facades\DB::table('itagg_instance')
                ->where('keyword', $keyword)
                ->where('smsshortcodes_id', 16) // 60300
                ->where('status', 1)
                ->exists();

            $label = strtoupper($keyword);
            if ($taken) {
                return redirect()->back()
                    ->with('activeTab', 'customer-keywords')
                    ->with('error', 'Whois: keyword "' . $label . '" is UNAVAILABLE on 60300 (already registered).');
            }

            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('success', 'Whois: keyword "' . $label . '" is AVAILABLE on 60300.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('activeTab', 'customer-keywords')
                ->with('error', 'Whois failed: ' . $e->getMessage());
        }
    }

    /**
     * Platinum Keyword Tools — REGISTER (OLD SYSTEM parity: plat_keyreg.mes / platinumlib.inc
     * regkeyword + chargeclient + moveuserfromlegacytofreekey). whois → wallet check →
     * create keyword (reuses SMSController::createKeywordForExistingUser which inserts the
     * 60300 + shared-virtual rows, decrements the keyword wallet, and promotes legacy→freekey).
     */
    public function keywordRegister(Request $request, $id)
    {
        $request->validate([
            'keyword' => 'required|string|min:3|max:16|regex:/^[A-Za-z0-9]+$/',
        ]);

        $keyword   = strtolower(trim($request->keyword));
        $shortcode = '60300';

        try {
            $user = \Illuminate\Support\Facades\DB::table('users')
                ->selectRaw("bigid, uname, pword, user_type, platinumaccess, contactemail,
                             (platkeywordwallet / NULLIF(platkeywordcost, 0)) as platkeywordsleft")
                ->where('id', $id)
                ->first();

            if (!$user) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Keyword registration failed: customer not found.');
            }

            // OLD SYSTEM platinum_opener(): platinumaccess must not be 'n'.
            if (($user->platinumaccess ?? '') !== 'y') {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Keyword registration requires Platinum access for this customer.');
            }

            // whois (regkeyword runs platinum_whois first).
            $taken = \Illuminate\Support\Facades\DB::table('itagg_instance')
                ->where('keyword', $keyword)
                ->where('smsshortcodes_id', 16)
                ->where('status', 1)
                ->exists();

            if ($taken) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Keyword "' . strtoupper($keyword) . '" is unavailable on 60300 (already registered).');
            }

            // OLD SYSTEM checkwalletfunds(): platkeywordwallet >= platkeywordcost.
            if (($user->platkeywordsleft ?? 0) < 1) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Insufficient keyword wallet — no remaining keyword allowance.');
            }

            $result = app(\App\Http\Controllers\SMSController::class)->createKeywordForExistingUser(
                $keyword,
                $user->contactemail,
                $user->bigid,
                $user->uname,
                $shortcode,
                $user->user_type
            );

            if (!empty($result['success'])) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('success', 'Keyword "' . strtoupper($keyword) . '" registered on 60300 (expires ' . $result['expiryDate'] . ').');
            }

            return redirect()->back()->with('activeTab', 'customer-keywords')
                ->with('error', 'Keyword creation failed (code ' . ($result['errorCode'] ?? '?') . ').');
        } catch (\Exception $e) {
            return redirect()->back()->with('activeTab', 'customer-keywords')
                ->with('error', 'Keyword registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Platinum Keyword Tools — RENEW (OLD SYSTEM parity: plat_keyrenew.mes / platinumlib.inc
     * renewkeyword + chargeclient + moveuserfromlegacytofreekey). Extends expiry by 12 months
     * on the 60300 row (+ shared-virtual 156 for silver/gold/platinum), charges one keyword
     * credit, promotes legacy→freekey.
     */
    public function keywordRenew(Request $request, $id)
    {
        $request->validate([
            'keyword' => 'required|string|min:3|max:16|regex:/^[A-Za-z0-9]+$/',
        ]);

        $keyword = strtolower(trim($request->keyword));

        try {
            $user = \Illuminate\Support\Facades\DB::table('users')
                ->selectRaw("bigid, user_type,
                             (platkeywordwallet / NULLIF(platkeywordcost, 0)) as platkeywordsleft")
                ->where('id', $id)
                ->first();

            if (!$user) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Keyword renewal failed: customer not found.');
            }

            // OLD SYSTEM checkwalletfunds().
            if (($user->platkeywordsleft ?? 0) < 1) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Insufficient keyword wallet — no remaining keyword allowance.');
            }

            // Silver/Gold/Platinum also own the shared-virtual row (156).
            $isSilverGoldPlat = in_array($user->user_type, ['silver', 'gold', 'platinum'], true);
            $codes = $isSilverGoldPlat ? [16, 156] : [16];

            // renewkeyword(): expiry = adddate(expiry, interval 1 year) for live keyword rows.
            $affected = \Illuminate\Support\Facades\DB::table('itagg_instance')
                ->where('users_bigid', $user->bigid)
                ->whereIn('smsshortcodes_id', $codes)
                ->where('keyword', $keyword)
                ->whereDate('expiry', '>=', now('Europe/London')->toDateString())
                ->update(['expiry' => \Illuminate\Support\Facades\DB::raw('adddate(expiry, interval 1 year)')]);

            if ($affected < 1) {
                return redirect()->back()->with('activeTab', 'customer-keywords')
                    ->with('error', 'Renewal failed: no active keyword "' . strtoupper($keyword) . '" found on 60300 for this customer.');
            }

            // chargeclient() + moveuserfromlegacytofreekey().
            \Illuminate\Support\Facades\DB::table('users')->where('bigid', $user->bigid)->decrement('platkeywordwallet', 1);
            \Illuminate\Support\Facades\DB::table('users')->where('bigid', $user->bigid)
                ->where('user_type', 'legacy')->update(['user_type' => 'freekey']);

            return redirect()->back()->with('activeTab', 'customer-keywords')
                ->with('success', 'Keyword "' . strtoupper($keyword) . '" renewed for 12 months (' . $affected . ' row(s) updated).');
        } catch (\Exception $e) {
            return redirect()->back()->with('activeTab', 'customer-keywords')
                ->with('error', 'Keyword renewal failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk migrate customers to new system
     */
    public function bulkMigrate(Request $request)
    {
        try {
            $customerIds = $request->input('customer_ids');
            $migrateCampaignFiles = $request->input('migrate_campaign_files', true);

            $explicit = ($customerIds !== 'all' && is_array($customerIds) && !empty($customerIds));

            // Build query for customers to migrate. Always: customer login type +
            // still on the old system (so already-migrated accounts are skipped).
            $query = User::where(function ($q) {
                $q->whereNull('login_type')
                    ->orWhere('login_type', 'customer');
            })
            ->where(function ($q) {
                $q->where('migration_flag', 'old')
                    ->orWhereNull('migration_flag');
            });

            if ($explicit) {
                // Admin explicitly picked these accounts in the migrate modal
                // (parent + sub-accounts, which can include disabled ones).
                // Honour that selection — do NOT exclude bit_disabled here.
                $query->whereIn('id', $customerIds);
            } else {
                // "Migrate All" — don't sweep in disabled accounts.
                $query->where('bit_disabled', 0);
            }

            // Get users before update (we need their bigids for file migration)
            $usersToMigrate = $query->get(['id', 'bigid', 'uname']);
            $count = $usersToMigrate->count();

            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No customers found to migrate.'
                ], 400);
            }

            // Get bigids for campaign file migration
            $userBigids = $usersToMigrate->pluck('bigid')->toArray();

            // Perform bulk update (users table doesn't have updated_at column).
            // migrated_at is what the dashboard's "still on old API" alert uses
            // to ignore smsg_log rows sent BEFORE migration.
            $updated = User::whereIn('id', $usersToMigrate->pluck('id')->toArray())
                ->update([
                    'migration_flag' => 'new',
                    'migrated_at'    => now(),
                ]);

            // Repoint each migrated customer's Nexmo virtual-number moHttpUrl
            // onto the new-system inbound webhook. Published to RabbitMQ as
            // one message per customer; the rabbitmq:consume-webhook-update
            // worker does the actual Nexmo /number/update calls in the
            // background. A single failure does not stall the whole batch
            // and Sinch numbers self-skip inside the consumer.
            try {
                $webhookQueueService = new WebhookUpdateQueueService();
                $newMoHttpUrl = route('sms.webhook.nexmo');
                foreach ($userBigids as $bigid) {
                    if (!empty($bigid)) {
                        $webhookQueueService->queueCustomerUpdate($bigid, $newMoHttpUrl);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to queue Nexmo webhook updates for migrated customers', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the migration response just because the webhook
                // updates couldn't be queued — they can be backfilled later.
            }

            // Queue campaign file migration if enabled
            $fileMigrationQueued = false;
            $batchId = null;
            if ($migrateCampaignFiles && !empty($userBigids)) {
                try {
                    $batchId = CampaignFileMigration::generateBatchId();
                    $queueService = new CampaignFileMigrationQueueService();
                    $result = $queueService->queueBatchMigration([
                        'batch_id' => $batchId,
                        'direction' => 'old_to_new',
                        'user_bigids' => $userBigids,
                    ]);
                    $fileMigrationQueued = $result['success'] ?? false;

                    \Illuminate\Support\Facades\Log::info('Campaign file migration queued for bulk migration', [
                        'batch_id' => $batchId,
                        'user_count' => count($userBigids),
                        'queued' => $fileMigrationQueued,
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Campaign file migration queue failed', [
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the whole migration just because file migration couldn't be queued
                }
            }

            // Log the migration
            \Illuminate\Support\Facades\Log::info('Bulk customer migration completed', [
                'migrated_count' => $updated,
                'migrated_by' => auth()->id(),
                'customer_ids' => $customerIds === 'all' ? 'all' : $customerIds,
                'file_migration_queued' => $fileMigrationQueued,
                'file_migration_batch_id' => $batchId,
            ]);

            $message = "{$updated} customer(s) migrated successfully to the new system.";
            if ($fileMigrationQueued) {
                $message .= " Campaign file migration has been queued in the background.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $updated,
                'file_migration_queued' => $fileMigrationQueued,
                'file_migration_batch_id' => $batchId,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Bulk migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
