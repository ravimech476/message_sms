<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Mobile App Accounts Controller
 * 
 * Handles account management operations for mobile app
 */
class AccountsController extends Controller
{
    /**
     * Get all accounts (master and sub-accounts)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $username = $user->uname;

            // Get master and sub accounts
            $accounts = User::where(function ($query) use ($username) {
                $query->where('uname', $username)
                    ->orWhere('masteruname', $username);
            })
            ->select([
                'bigid',
                'uname',
                'contactname',
                'contactemail',
                'busname',
                'bulk_throughput',
                'smsg_wallet',
                'smsg_server1_sent',
                'smsg_server2_sent',
                'masteruname',
                DB::raw('(SELECT COUNT(*) FROM itagg_instance WHERE users_bigid = users.bigid AND active = 1) as keyword_count')
            ])
            ->orderByRaw("CASE WHEN uname = ? THEN 0 ELSE 1 END", [$username])
            ->orderBy('busname')
            ->get();

            // Format accounts for response
            $formattedAccounts = $accounts->map(function ($account) use ($username) {
                $wallet = $account->smsg_wallet - $account->smsg_server1_sent - $account->smsg_server2_sent;
                $isMaster = ($account->uname === $username && $account->masteruname === $username) || 
                           ($account->uname === $username);
                
                return [
                    'id' => $account->bigid,
                    'username' => $account->uname,
                    'contact_name' => urldecode($account->contactname ?? ''),
                    'email' => $account->contactemail,
                    'business_name' => urldecode($account->busname ?? ''),
                    'daily_limit' => (int) $account->bulk_throughput,
                    'daily_limit_formatted' => number_format($account->bulk_throughput),
                    'wallet_balance' => round($wallet, 2),
                    'wallet_balance_formatted' => '£' . number_format($wallet, 2),
                    'keywords' => (int) $account->keyword_count,
                    'is_master' => $isMaster,
                    'type' => $isMaster ? 'master' : 'sub',
                ];
            });

            // Calculate totals
            $totalWallet = $formattedAccounts->sum('wallet_balance');
            $totalAccounts = $formattedAccounts->count();
            $subAccounts = $formattedAccounts->where('is_master', false)->count();

            return response()->json([
                'status' => true,
                'message' => 'Accounts retrieved successfully',
                'data' => [
                    'accounts' => $formattedAccounts,
                    'statistics' => [
                        'total_accounts' => $totalAccounts,
                        'sub_accounts' => $subAccounts,
                        'total_wallet_balance' => round($totalWallet, 3),
                        'total_wallet_formatted' => '£' . number_format($totalWallet, 2),
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Accounts Index Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve accounts',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer wallet funds between accounts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transfer(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $username = $user->uname;
            
            $xferfrom = $request->input('from_account');
            $xferto = $request->input('to_account');
            $xferamount = floatval($request->input('amount', 0));

            // Validation
            if (empty($xferfrom) || empty($xferto)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please specify both source and destination accounts'
                ], 400);
            }

            if ($xferfrom === $xferto) {
                return response()->json([
                    'status' => false,
                    'message' => 'Source and destination accounts must be different'
                ], 400);
            }

            if ($xferamount <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please specify a valid amount to transfer'
                ], 400);
            }

            // Verify accounts belong to user
            $fromAccount = User::where('uname', $xferfrom)
                ->where(function ($query) use ($username) {
                    $query->where('masteruname', $username)
                        ->orWhere('uname', $username);
                })
                ->first();

            $toAccount = User::where('uname', $xferto)
                ->where(function ($query) use ($username) {
                    $query->where('masteruname', $username)
                        ->orWhere('uname', $username);
                })
                ->first();

            if (!$fromAccount) {
                return response()->json([
                    'status' => false,
                    'message' => 'Source account not found or unauthorized'
                ], 404);
            }

            if (!$toAccount) {
                return response()->json([
                    'status' => false,
                    'message' => 'Destination account not found or unauthorized'
                ], 404);
            }

            // Check source account balance
            $fromWallet = $fromAccount->smsg_wallet - $fromAccount->smsg_server1_sent - $fromAccount->smsg_server2_sent;
            
            if ($fromWallet < $xferamount) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient funds in source account. Available: £' . number_format($fromWallet, 2)
                ], 400);
            }

            // Perform transfer
            DB::beginTransaction();
            try {
                DB::table('users')->where('uname', $xferfrom)->decrement('smsg_wallet', $xferamount);
                DB::table('users')->where('uname', $xferto)->increment('smsg_wallet', $xferamount);

                // Log the transfer
                DB::table('money_transfer_logs')->insert([
                    'ip_address' => $request->ip(),
                    'from_account' => $xferfrom,
                    'to_account' => $xferto,
                    'created_by' => $username,
                    'created' => now(),
                    'amount' => $xferamount
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $fromBusname = urldecode($fromAccount->busname);
            $toBusname = urldecode($toAccount->busname);

            Log::info('Mobile Wallet Transfer', [
                'user' => $username,
                'from' => $xferfrom,
                'to' => $xferto,
                'amount' => $xferamount,
            ]);

            return response()->json([
                'status' => true,
                'message' => "£" . number_format($xferamount, 2) . " transferred successfully",
                'data' => [
                    'from_account' => [
                        'username' => $xferfrom,
                        'business_name' => $fromBusname,
                    ],
                    'to_account' => [
                        'username' => $xferto,
                        'business_name' => $toBusname,
                    ],
                    'amount' => $xferamount,
                    'amount_formatted' => '£' . number_format($xferamount, 2),
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Wallet Transfer Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to transfer funds',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can add sub-accounts
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function canAddSubAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $username = $user->uname;
            
            // Check if user is a master account
            $isMaster = ($user->masteruname === $username) &&
                (strpos($user->dashboardaccess ?? '', 'a') !== false);

            return response()->json([
                'status' => true,
                'data' => [
                    'can_add' => $isMaster,
                    'message' => $isMaster 
                        ? 'You can add sub-accounts' 
                        : 'You do not have permission to add sub-accounts'
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Can Add SubAccount Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to check permissions',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Add new sub-account
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $username = $user->uname;
            $userref = $user->bigid;

            // Check permissions
            $isMaster = ($user->masteruname === $username) &&
                (strpos($user->dashboardaccess ?? '', 'a') !== false);

            if (!$isMaster) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to add sub-accounts'
                ], 403);
            }

            // Validate input
            $contactname = $request->input('contact_name');
            $busname = $request->input('business_name');
            $email = $request->input('email');
            $phone = $request->input('phone', '');
            $mobile = $request->input('mobile', '');

            if (empty(trim($contactname))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Contact name is required'
                ], 400);
            }

            if (empty(trim($busname))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Business name is required'
                ], 400);
            }

            if (empty(trim($email)) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Valid email address is required'
                ], 400);
            }

            // Generate credentials
            $newuserid = md5(uniqid(rand(), 1));
            $newusr = substr($newuserid, 0, 8);
            $newpwd = substr($newuserid, 8, 8);

            // Generate unique keys
            $nfckey = $this->generateUniqueNfcKey();
            $newinvitecode = $this->generateUniqueInviteCode();
            $newsecretkey = strtoupper($this->generateUniqueId(32));

            $signip = $request->ip();

            DB::beginTransaction();
            try {
                // Insert user reminder
                DB::table('userreminder')->insert([
                    'usersbigidref' => $newuserid,
                    'reminderon' => 'n'
                ]);

                // Insert user option
                DB::table('useroption')->insert([
                    'userref' => $newuserid,
                    'api_premrate_blocked' => 1,
                    'can_use_location_lookup_api' => 'no',
                    'explanation' => 'sub account for master account: ' . $username . ' (created via mobile app)',
                    'sdf_lastupdated' => date('Y-m-d'),
                    'agreedcontracts_description' => '<br>' . date('F j, Y') . ' Agreed by Us.',
                    'agreedcontracts' => date('Y-m-d')
                ]);

                // New account's useroption → prime/rebuild its cache (Phase 2).
                app(\App\Services\TableCache::class)->rebuildUseroption($newuserid);

                // Insert affiliate invite
                DB::table('affiliateinvite')->insert([
                    'assigned_userref' => $newuserid,
                    'icode' => $newinvitecode,
                    'codenote' => 'first code for new client created in Mobile Campaign Manager',
                    'subdomain' => $newuserid
                ]);

                // Get settings
                $userSettings = $this->getSubAccountSettings($username);

                // Insert new user (OLD SYSTEM wallet fields initialization)
                DB::table('users')->insert([
                    'bigid' => $newuserid,
                    'uname' => $newusr,
                    'pword' => $newpwd,
                    'first_ip' => $signip,
                    'contactname' => urlencode($contactname),
                    'busname' => urlencode($busname),
                    'contactemail' => $email,
                    'phone' => $phone,
                    'mobilenumber' => $mobile,
                    'smsg_wallet' => $userSettings['smsg_wallet'],
                    'smsg_server1_sent' => 0,              // OLD SYSTEM: Server 1 sent amount
                    'smsg_server2_sent' => 0,              // OLD SYSTEM: Server 2 sent amount
                    'platkeywordwallet' => 0,              // OLD SYSTEM: Platform keyword wallet
                    'bulk_throughput' => $userSettings['bulk_throughput'],
                    'clientcommstatus' => 'cool',
                    'affiliateinvitecode' => '',
                    'user_type' => $userSettings['user_type'],
                    'datejoined' => time(),
                    'datefrozen' => time() + 31536000,
                    'bit_disabled' => 0,
                    'premium_throughput' => 0,
                    'daemonpriority' => $userSettings['daemonpriority'],
                    'masteruname' => $username,
                    'isnfcuser' => 'n',
                    'nfckey' => $nfckey,
                    'platinumaccess' => 'y',
                    'chargetype1' => $userSettings['chargetype1'],
                    'routetag' => $userSettings['routetag'],
                    '1s_preferredroute' => $userSettings['preferredroute'],
                    'dashboardaccess' => $userSettings['dashboardaccess'],
                    'itaggsecretkey' => $newsecretkey,
                    'role' => 'customer',
                    'login_type' => 'customer',
                ]);

                // Insert user routes
                $this->insertUserRoutes($newuserid, $userSettings['userprice']);

                DB::commit();

                Log::info('Mobile New sub-account created', [
                    'master_user' => $username,
                    'new_user' => $newusr,
                    'new_bigid' => $newuserid,
                    'contactname' => $contactname,
                    'busname' => $busname,
                    'ip' => $signip
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Sub-account created successfully',
                    'data' => [
                        'username' => $newusr,
                        'password' => $newpwd,
                        'contact_name' => $contactname,
                        'business_name' => $busname,
                        'email' => $email,
                    ],
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Throwable $ex) {
            Log::error('Mobile Add SubAccount Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to create sub-account',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique NFC key
     */
    private function generateUniqueNfcKey()
    {
        do {
            $nfckey = $this->generateUniqueId(6, 'chars');
            $exists = DB::table('users')->where('nfckey', $nfckey)->exists();
        } while ($exists);

        return $nfckey;
    }

    /**
     * Generate unique invite code
     */
    private function generateUniqueInviteCode()
    {
        do {
            $code = strtoupper($this->generateUniqueId(5));
            $exists = DB::table('affiliateinvite')->where('icode', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Generate unique ID
     */
    private function generateUniqueId($length = 32, $restrict = 'all')
    {
        if ($restrict == 'chars') {
            $allow = 'abcdefghjkmnpqrstvwxyz';
        } elseif ($restrict == 'nums') {
            $allow = '23456789';
        } else {
            $allow = 'abcdefghjkmnpqrstvwxyz23456789';
        }

        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $allow[random_int(0, strlen($allow) - 1)];
        }

        return $id;
    }

    /**
     * Get sub-account settings based on master account
     */
    private function getSubAccountSettings($masterUsername)
    {
        return [
            'smsg_wallet' => 0,
            'bulk_throughput' => 10000,
            'user_type' => 'freekey',
            'daemonpriority' => 400,
            'chargetype1' => 'pps',
            'routetag' => 'd',
            'preferredroute' => 7002,
            'dashboardaccess' => 'mc',
            'userprice' => '0.03'
        ];
    }

    /**
     * Insert user routes for new account
     */
    private function insertUserRoutes($userref, $userprice)
    {
        $routes = [
            ['routenum' => 7002, 'numbits' => 7, 'origtype' => 'alpha'],
            ['routenum' => 7002, 'numbits' => 7, 'origtype' => 'msisdn'],
            ['routenum' => 7002, 'numbits' => 7, 'origtype' => 'shortcode'],
            ['routenum' => 7002, 'numbits' => 8, 'origtype' => 'alpha'],
            ['routenum' => 7002, 'numbits' => 8, 'origtype' => 'msisdn'],
            ['routenum' => 7002, 'numbits' => 8, 'origtype' => 'shortcode'],
            ['routenum' => 7029, 'numbits' => 7, 'origtype' => 'alpha'],
            ['routenum' => 7029, 'numbits' => 7, 'origtype' => 'msisdn'],
            ['routenum' => 7029, 'numbits' => 7, 'origtype' => 'shortcode'],
            ['routenum' => 7029, 'numbits' => 8, 'origtype' => 'alpha'],
            ['routenum' => 7029, 'numbits' => 8, 'origtype' => 'msisdn'],
            ['routenum' => 7029, 'numbits' => 8, 'origtype' => 'shortcode'],
        ];

        foreach ($routes as $route) {
            DB::table('smsg_userroute')->insert([
                'userref' => $userref,
                'username' => 'special rate users',
                'routenum' => $route['routenum'],
                'countrydialcode' => '44',
                'numbits' => $route['numbits'],
                'origtype' => $route['origtype'],
                'userprice' => $userprice,
                'priority' => 1
            ]);
        }
    }
}
