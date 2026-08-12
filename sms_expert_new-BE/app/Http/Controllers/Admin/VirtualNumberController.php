<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use App\Models\VirtualNumber;
use App\Models\ItaggInstance;
use App\Models\SmsShortcode;
use App\Services\NexmoService;
use App\Services\SinchService;
use App\Jobs\SyncVirtualNumbersJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VirtualNumberController extends Controller
{
    use LegacyCustomerList;

    protected $nexmoService;
    protected $sinchService;

    public function __construct(NexmoService $nexmoService, SinchService $sinchService)
    {
        $this->nexmoService = $nexmoService;
        $this->sinchService = $sinchService;
    }

    /**
     * Display a listing of virtual numbers from database with pagination and search
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 25);
            $search = $request->get('search', '');
            $operator = $request->get('operator', '');
            $customer = $request->get('customer', '');
            // Status filter: '' = all, 'available' = no customer assigned, 'assigned' = has a customer.
            $status = $request->get('status', '');


            // Why two leftJoinSubs:
            //   smsshortcodes can have many rows per number (history).
            //   itagg_instance can have many rows per smsshortcodes_id (history).
            // Joining either directly would multiply virtual_numbers rows and
            // make a single msisdn appear 2+ times in the table (the
            // 447937946920 / 447507332441 case). Pick the latest row of each
            // by MAX(id) BEFORE joining out to itagg_instance / users.
            $query = VirtualNumber::query()
                ->select(
                    'virtual_numbers.*',
                    'users.busname',
                    'users.bigid',
                    'smss.number as shortcode_number'
                )
                ->leftJoinSub(function ($sub) {
                    $sub->from('smsshortcodes')
                        ->select('number', \DB::raw('MAX(id) as latest_id'))
                        ->groupBy('number');
                }, 'latest', 'virtual_numbers.msisdn', '=', 'latest.number')
                ->leftJoin('smsshortcodes as smss', 'smss.id', '=', 'latest.latest_id')
                ->leftJoinSub(function ($sub) {
                    $sub->from('itagg_instance')
                        ->select('smsshortcodes_id', \DB::raw('MAX(id) as latest_inst_id'))
                        ->groupBy('smsshortcodes_id');
                }, 'inst_latest', 'smss.id', '=', 'inst_latest.smsshortcodes_id')
                ->leftJoin('itagg_instance', 'itagg_instance.id', '=', 'inst_latest.latest_inst_id')
                ->leftJoin('users', 'itagg_instance.users_bigid', '=', 'users.bigid')
                ->orderBy('smss.number', 'desc');

            // Filter by operator
            if (!empty($operator)) {
                $query->where('virtual_numbers.operator', $operator);
            }

            // Filter by customer (users.bigid)
            if (!empty($customer)) {
                $query->where('users.bigid', $customer);
            }

            // Status filter. "Available" = the unassigned pool: numbers with NO
            // customer (users.bigid NULL) OR held by the internal stock account
            // (73419c0c...e731838 — not a real customer). "Assigned" = held by a
            // real customer, which therefore excludes that stock account.
            $poolBigid = '73419c0c137c96c84a4490545e731838';
            if ($status === 'available') {
                $query->where(function ($q) use ($poolBigid) {
                    $q->whereNull('users.bigid')
                      ->orWhere('users.bigid', $poolBigid);
                });
            } elseif ($status === 'assigned') {
                $query->whereNotNull('users.bigid')
                      ->where('users.bigid', '!=', $poolBigid);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.busname', 'like', "%{$search}%")
                        ->orWhere('users.bigid', 'like', "%{$search}%")
                        ->orWhere('virtual_numbers.msisdn', 'like', "%{$search}%");
                });
            }

            $paginator = $query->paginate($perPage);
            $virtualNumbers = collect($paginator->items())->map(function ($number) {
                // URL decode the busname if it exists
                if (!empty($number->busname)) {
                    $number->busname = urldecode($number->busname);
                }
                return $number;
            })->all();

            $totalCount = $paginator->total();
            $customerList = $this->getVirtualNumberCustomerList();

            return view('admin.virtual-numbers.index', [
                'virtualNumbers' => $virtualNumbers,
                'totalCount' => $totalCount,
                'paginator' => $paginator,
                'perPage' => $perPage,
                'currentPage' => $paginator->currentPage(),
                'search' => $search,
                'operator' => $operator,
                'customer' => $customer,
                'status' => $status,
                'customerList' => $customerList,
                'error' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching virtual numbers: ' . $e->getMessage());

            return view('admin.virtual-numbers.index', [
                'virtualNumbers' => [],
                'totalCount' => 0,
                'paginator' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25, 1),
                'perPage' => 25,
                'currentPage' => 1,
                'search' => '',
                'operator' => '',
                'customer' => '',
                'status' => '',
                'customerList' => collect(),
                'error' => 'Failed to fetch virtual numbers. Please try again later.'
            ]);
        }
    }

    /**
     * Build the customer dropdown list for the Virtual Numbers filter:
     * pulled directly from the users table (all customer accounts, not just
     * those currently linked to a virtual number), so the dropdown is never
     * empty just because the latest-shortcode join doesn't reach a user.
     *
     * Each item carries a display_name based on users.contactname (urldecoded),
     * falling back to busname / uname / bigid.
     */
    private function getVirtualNumberCustomerList()
    {
        return $this->getLegacyCustomers()
            ->filter(fn ($row) => !empty($row->bigid))
            ->map(function ($row) {
                $contact = trim(urldecode($row->contactname ?? ''));
                $bus     = trim(urldecode($row->busname ?? ''));
                if ($contact !== '') {
                    $row->display_name = $bus !== '' ? "{$contact} ({$bus})" : $contact;
                } elseif ($bus !== '') {
                    $row->display_name = $bus;
                } else {
                    $row->display_name = $row->uname ?: $row->bigid;
                }
                return $row;
            })
            ->sortBy(fn ($r) => mb_strtolower((string) ($r->contactname ?? '~')))
            ->values();
    }




    /**
     * Get virtual numbers with keywords for a specific user
     */
    public function getVirtualNumbers($userId)
    {
        try {
            $user = \App\Models\User::findOrFail($userId);
            $bigid = $user->bigid;
            $today = date('Y-m-d');

            // Get all iTAGG instances with their shortcode information
            $itaggInstances = ItaggInstance::select(
                'itagg_instance.id',
                'itagg_instance.keyword',
                'itagg_instance.expiry',
                'itagg_instance.active',
                'itagg_instance.forwarding_email',
                'smsshortcodes.number',
                'smsshortcodes.id as shortcodeid',
                'smsshortcodes.whichoperator as theprovider',
                'smsshortcodes.pooled'
            )
                ->join('smsshortcodes', 'smsshortcodes.id', '=', 'itagg_instance.smsshortcodes_id')
                ->where('itagg_instance.users_bigid', $bigid)
                ->where('itagg_instance.status', '1')
                ->where(function ($query) use ($today) {
                    $query->where('itagg_instance.expiry', '>=', $today)
                        ->orWhere('itagg_instance.expiry', '1999-05-19')
                        ->orWhere('itagg_instance.expiry', '<=', $today);
                })
                ->orderBy('smsshortcodes.number', 'desc')
                ->orderBy('itagg_instance.active', 'desc')
                ->orderBy('itagg_instance.expiry', 'asc')
                ->orderBy('itagg_instance.keyword', 'asc')
                ->get();

            // Group by shortcode
            $groupedData = [];
            foreach ($itaggInstances as $instance) {
                $number = $instance->number;
                if (!isset($groupedData[$number])) {
                    $groupedData[$number] = [
                        'number' => $number,
                        'shortcodeid' => $instance->shortcodeid,
                        'provider' => $instance->theprovider,
                        'pooled' => $instance->pooled,
                        'keywords' => []
                    ];
                }
                $groupedData[$number]['keywords'][] = $instance;
            }

            return response()->json([
                'success' => true,
                'data' => array_values($groupedData),
                'today' => $today
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching virtual numbers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch virtual numbers'
            ], 500);
        }
    }

    /**
     * Add 12 months from today to keyword expiry
     */
    public function add12MonthsFromToday(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id'
            ]);

            $itaggId = $request->input('itagg_id');

            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);

            $number = $instance->smsshortcode->number;

            DB::table('itagg_instance')
                ->where('id', $itaggId)
                ->update([
                    'expiry' => DB::raw("DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 365 DAY), '%Y-%m-%d')")
                ]);

            if ($instance->active == 1) {
                DB::table('virtual_numbers')
                    ->where('msisdn', $number)
                    ->update(['is_active' => 1]);
            }

            return response()->json([
                'success' => true,
                'message' => '12 months added from today successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding 12 months from today: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expiry date'
            ], 500);
        }
    }

    /**
     * Add 12 months from current expiry date
     */
    public function add12MonthsFromExpiry(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id'
            ]);

            $itaggId = $request->input('itagg_id');

            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);

            $number = $instance->smsshortcode->number;

            $expiry = Carbon::parse($instance->expiry)->toDateString();
            $today  = Carbon::today()->toDateString();

            if ($expiry < $today) {

                VirtualNumber::where('msisdn', $number)
                    ->update(['is_active' => 0]);
            }

            // if ($instance->active == 1) {
            //     DB::table('virtual_numbers')
            //         ->where('msisdn', $number)
            //         ->update(['is_active' => 1]);
            // }

            DB::table('itagg_instance')
                ->where('id', $itaggId)
                ->update([
                    'expiry' => DB::raw("DATE_FORMAT(DATE_ADD(STR_TO_DATE(expiry, '%Y-%m-%d'), INTERVAL 365 DAY), '%Y-%m-%d')")
                ]);

            return response()->json([
                'success' => true,
                'message' => '12 months added from current expiry date successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding 12 months from expiry: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expiry date'
            ], 500);
        }
    }

    /**
     * Force expire a keyword (set to 1999-05-19)
     */
    public function forceExpiry(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id'
            ]);

            $itaggId = $request->input('itagg_id');

            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);

            $number = $instance->smsshortcode->number;


            ItaggInstance::where('id', $itaggId)
                ->update(['expiry' => '1999-05-19']);

            if ($instance->active == 1) {
                DB::table('virtual_numbers')
                    ->where('msisdn', $number)
                    ->update(['is_active' => 0]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Keyword force expired successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error forcing expiry: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to force expiry'
            ], 500);
        }
    }

    /**
     * Force unexpire a keyword
     */
    public function forceUnexpiry(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id'
            ]);

            $itaggId = $request->input('itagg_id');

            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);

            $number = $instance->smsshortcode->number;

            // Set expiry to today + 1 year
            DB::table('itagg_instance')
                ->where('id', $itaggId)
                ->update([
                    'expiry' => DB::raw("DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 365 DAY), '%Y-%m-%d')")
                ]);

            if ($instance->active == 1) {
                DB::table('virtual_numbers')
                    ->where('msisdn', $number)
                    ->update(['is_active' => 1]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Keyword unexpired successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error unexpiring keyword: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to unexpire keyword'
            ], 500);
        }
    }

    /**
     * Toggle suspension status of a keyword
     */
    public function toggleSuspension(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id',
                'active' => 'required|in:0,1'
            ]);

            $itaggId = $request->input('itagg_id');
            $active = $request->input('active');

            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);

            $number = $instance->smsshortcode->number;

            ItaggInstance::where('id', $itaggId)
                ->update(['active' => $active]);

            $status = $active == 1 ? 'unsuspended' : 'suspended';

            if ($status == 'unsuspended') {
                $isActive = 1;
            } elseif ($status == 'suspended') {
                $isActive = 0;
            }

            VirtualNumber::where('msisdn', $number)
                ->update(['is_active' => $isActive]);

            return response()->json([
                'success' => true,
                'message' => "Keyword {$status} successfully"
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling suspension: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update suspension status'
            ], 500);
        }
    }

    /**
     * Update expiry date to custom date
     */
    public function updateExpiryDate(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id',
                'expiry_date' => 'required|date|after:yesterday'
            ]);

            $itaggId = $request->input('itagg_id');
            $expiryDate = $request->input('expiry_date');
            list($day, $month, $year) = explode('-', $expiryDate);
            $expiryDate = "$year-$month-$day";

            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);

            $number = $instance->smsshortcode->number;

            if ($instance->active == 1) {
                DB::table('virtual_numbers')
                    ->where('msisdn', $number)
                    ->update(['is_active' => 1]);
            }

            ItaggInstance::where('id', $itaggId)
                ->update(['expiry' => $expiryDate]);

            return response()->json([
                'success' => true,
                'message' => 'Expiry date updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating expiry date: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expiry date'
            ], 500);
        }
    }

    /**
     * Remove a virtual number (force expiry + suspend)
     */
    public function removeNumber(Request $request)
    {
        try {
            $request->validate([
                'itagg_id' => 'required|integer|exists:itagg_instance,id'
            ]);

            $itaggId  = $request->input('itagg_id');
            $instance = ItaggInstance::with('smsshortcode')->find($itaggId);
            $number   = $instance?->smsshortcode?->number;

            // RELEASE BACK TO POOL instead of deleting: reassign the number to the
            // internal dedvirt repository account (steve1905, bigid 73419c0c...) and
            // keep it active, so it leaves THIS customer and becomes "available" for
            // re-assignment to another customer (the Virtual Numbers list treats the
            // repository's numbers as available).
            $poolBigid = '73419c0c137c96c84a4490545e731838';

            ItaggInstance::where('id', $itaggId)->update([
                'users_bigid' => $poolBigid,
                'active'      => 1,
            ]);

            if ($number) {
                DB::table('virtual_numbers')
                    ->where('msisdn', $number)
                    ->update(['is_active' => 1]);
            }

            Log::info('Virtual number released back to pool', [
                'itagg_id' => $itaggId,
                'msisdn'   => $number,
                'pool'     => $poolBigid,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Virtual number released back to the available pool'
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove number'
            ], 500);
        }
    }

    /**
     * Add dedicated virtual numbers to user
     * Integrated with legacy addvirts functionality
     */
    public function addVirtualNumbers(Request $request)
    {

        try {
            $request->validate([
                'user_id' => 'required|string|exists:users,id',
                'how_many_to_add' => 'required|integer|min:1',
                'add_ded_virt_type' => 'required|string|in:mBloxUK,NexmoUK,mBirdUSA',
                'country_code' => 'required|string',
                'new_virts_expiry_date' => 'required|date|after:yesterday',
                'new_virts_pooled' => 'nullable|integer|in:0,1'
            ]);

            $userId = $request->input('user_id');
            $howManyToAdd = $request->input('how_many_to_add');
            $addDedVirtType = $request->input('add_ded_virt_type');
            $countryCode = $request->input('country_code');
            $newVirtsExpiryDate = $request->input('new_virts_expiry_date');
            $newVirtsPooled = $request->input('new_virts_pooled', 0);

            // Get user details
            $user = \App\Models\User::findOrFail($userId);
            $userBigid = $user->bigid;

            // Determine the operator type based on the old logic
            $dedVirtTypeSql = '';
            switch ($addDedVirtType) {
                case 'mBloxUK':
                    $dedVirtTypeSql = 'mBlox';
                    break;
                case 'NexmoUK':
                    $dedVirtTypeSql = 'Nexmo';
                    break;
                case 'mBirdUSA':
                    $dedVirtTypeSql = 'mBird';
                    break;
                default:
                    $dedVirtTypeSql = 'QQQQQ';
            }

            // Call the addVirts method
            $result = $this->addVirts(
                $howManyToAdd,
                $dedVirtTypeSql,
                $userBigid,
                $addDedVirtType,
                $newVirtsExpiryDate,
                $newVirtsPooled,
                $countryCode,
                $user
            );

            if ($result['success']) {
                return back()->with('success_virtual', $result['message']);
            } else {
                return back()->with('error_virtual', $result['message']);
            }
        } catch (\Exception $e) {
            Log::error('Error adding virtual numbers: ' . $e->getMessage());
            return back()->with('error_virtual', 'Failed to add virtual numbers: ' . $e->getMessage());
        }
    }

    /**
     * Add virtual numbers (converted from legacy addvirts function)
     * 
     * @param int $howManyToAdd Number of virtual numbers to add
     * @param string $dedVirtTypeSql Operator type filter
     * @param string $userBigid User's bigid
     * @param string $addDedVirtType Type description for display
     * @param string $newVirtsExpiryDate Expiry date for the virtual numbers
     * @param int $newVirtsPooled Whether the number is pooled (0 or 1)
     * @param string $countryCode Country code filter
     * @param \App\Models\User $user User model instance
     * @return array Result with success status and message
     */
    private function addVirts(
        $howManyToAdd,
        $dedVirtTypeSql,
        $userBigid,
        $addDedVirtType,
        $newVirtsExpiryDate,
        $newVirtsPooled,
        $countryCode,
        $user
    ) {
        $successCount = 0;
        $failCount = 0;
        $messages = [];

        for ($i = 0; $i < $howManyToAdd; $i++) {
            try {
                // Query to find available unassigned virtual numbers
                $availableNumber = DB::table('itagg_instance as ii')
                    ->join('smsshortcodes as ss', 'ss.id', '=', 'ii.smsshortcodes_id')
                    ->join('users as u', 'u.bigid', '=', 'ii.users_bigid')
                    ->select(
                        'u.bigid',
                        'u.busname',
                        'u.contactname',
                        'u.contactemail',
                        'ss.number',
                        'ii.smsshortcodes_id',
                        'ii.expiry',
                        'ss.shortcode_notes',
                        'ss.whichoperator'
                    )
                    ->where(function ($query) {
                        $query->where('ii.keyword', '*')
                            ->orWhere(function ($q) {
                                $q->where('ii.smsshortcodes_id', 15)
                                    ->where('ii.keyword', 'demo');
                            })
                            ->orWhere(function ($q) {
                                $q->whereIn('ii.smsshortcodes_id', [156, 160])
                                    ->where('ii.keyword', 'steve');
                            });
                    })
                    ->where('ss.shortcode_notes', 'steve/iTagg - unassigned')
                    ->where('ss.number', 'like', $countryCode . '%')
                    ->where('ss.whichoperator', 'like', $dedVirtTypeSql . '%')
                    ->orderBy('ss.orderdate', 'asc')
                    ->first();

                if ($availableNumber && !empty($availableNumber->number)) {
                    // Update smsshortcodes table
                    $shortcodeNotes = substr(
                        urldecode($user->contactname) . ' / ' . urldecode($user->busname),
                        0,
                        255
                    );

                    DB::table('smsshortcodes')
                        ->where('number', $availableNumber->number)
                        ->update([
                            'shortcode_notes' => $shortcodeNotes,
                            'pooled' => $newVirtsPooled
                        ]);

                    // Update itagg_instance table
                    DB::table('itagg_instance')
                        ->where('smsshortcodes_id', $availableNumber->smsshortcodes_id)
                        ->update([
                            'users_bigid' => $userBigid,
                            'purchased' => date('Y-m-d'),
                            'expiry' => $newVirtsExpiryDate,
                            'modules_enabled' => 2,
                            'forwarding_email' => $user->contactemail,
                            'forwarding_url' => ''
                        ]);

                    $successCount++;
                    $messages[] = ($i + 1) . " New {$addDedVirtType} Ded Virt Added OK ({$availableNumber->number}).";

                    Log::info("Virtual number added successfully", [
                        'number' => $availableNumber->number,
                        'user_bigid' => $userBigid,
                        'type' => $addDedVirtType
                    ]);
                } else {
                    $failCount++;
                    $messages[] = ($i + 1) . " New {$addDedVirtType} Ded Virt Added FAILED - No available numbers.";

                    Log::warning("No available virtual number found", [
                        'iteration' => $i + 1,
                        'type' => $addDedVirtType,
                        'country_code' => $countryCode
                    ]);
                }
            } catch (\Exception $e) {
                $failCount++;
                $messages[] = ($i + 1) . " New {$addDedVirtType} Ded Virt Added FAILED - Error: " . $e->getMessage();

                Log::error("Error adding virtual number", [
                    'iteration' => $i + 1,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Count remaining available numbers
        $remainingCount = DB::table('itagg_instance as ii')
            ->join('smsshortcodes as ss', 'ss.id', '=', 'ii.smsshortcodes_id')
            ->where(function ($query) {
                $query->where('ii.keyword', '*')
                    ->orWhere(function ($q) {
                        $q->where('ii.smsshortcodes_id', 15)
                            ->where('ii.keyword', 'demo');
                    })
                    ->orWhere(function ($q) {
                        $q->whereIn('ii.smsshortcodes_id', [156, 160])
                            ->where('ii.keyword', 'steve');
                    });
            })
            ->where('ss.shortcode_notes', 'steve/iTagg - unassigned')
            ->where('ss.number', 'like', $countryCode . '%')
            ->where('ss.whichoperator', 'like', $dedVirtTypeSql . '%')
            ->count();

        $messages[] = "{$remainingCount} {$addDedVirtType} Ded Virts Remaining!";

        $finalMessage = implode("\n", $messages);

        Log::info("Add virtual numbers completed", [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'remaining' => $remainingCount
        ]);

        return [
            'success' => $successCount > 0,
            'message' => $finalMessage,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'remaining_count' => $remainingCount
        ];
    }

    /**
     * Sync virtual numbers from both Nexmo and Sinch (for manual sync button)
     */
    public function syncFromNexmo(Request $request)
    {
        try {
            // Dispatch the sync job for Nexmo
            SyncVirtualNumbersJob::dispatch();

            // Also sync Sinch numbers
            $this->syncSinchNumbers();

            return redirect()->route('admin.virtual-numbers.index')
                ->with('success', 'Sync job dispatched successfully for both Nexmo and Sinch. Numbers will be updated in a moment.');
        } catch (\Exception $e) {
            Log::error('Error syncing virtual numbers: ' . $e->getMessage());

            return redirect()->route('admin.virtual-numbers.index')
                ->with('error', 'Failed to sync virtual numbers. Please try again.');
        }
    }

    /**
     * Sync Sinch numbers to the database
     */
    private function syncSinchNumbers()
    {
        try {
            $result = $this->sinchService->getOwnedNumbers(100);

            if (!$result || !isset($result['activeNumbers'])) {
                Log::info('No Sinch numbers found to sync');
                return;
            }

            foreach ($result['activeNumbers'] as $sinchNumber) {
                $phoneNumber = $sinchNumber['phoneNumber'] ?? null;
                if (!$phoneNumber) {
                    continue;
                }

                // Clean phone number (remove + prefix for consistency)
                $msisdn = ltrim($phoneNumber, '+');

                // Check if already exists in virtual_numbers
                $exists = VirtualNumber::where('msisdn', $msisdn)->exists();

                if (!$exists) {
                    // Add to virtual_numbers table
                    VirtualNumber::create([
                        'msisdn' => $msisdn,
                        'operator' => 'Sinch',
                        'country' => $sinchNumber['regionCode'] ?? 'GB',
                        'type' => $this->mapSinchType($sinchNumber['type'] ?? 'MOBILE'),
                        'features' => $sinchNumber['capability'] ?? ['SMS'],
                        'mo_http_url' => $sinchNumber['smsConfiguration']['callbackUrl'] ?? null,
                        'is_active' => true,
                        'last_synced_at' => now(),
                    ]);

                    Log::info("Synced Sinch number: {$msisdn}");
                } else {
                    // Update existing record
                    VirtualNumber::where('msisdn', $msisdn)->update([
                        'last_synced_at' => now(),
                    ]);
                }

                // Check if exists in smsshortcodes
                $shortcodeExists = DB::table('smsshortcodes')
                    ->where('number', $msisdn)
                    ->exists();

                if (!$shortcodeExists) {
                    // Add to smsshortcodes table
                    $nextId = DB::table('smsshortcodes')->insertGetId([
                        'number' => $msisdn,
                        'cost' => 0,
                        'adult' => 0,
                        'must_respond' => 1,
                        'can_send' => 0,
                        'itagg_premiumroute' => 1,
                        'itagg_standardroute' => 1,
                        'module_restrict' => 8298495,
                        'show_cp_subkeyword_management' => 1,
                        'content_type_restrict' => '',
                        'is_dedicated' => 'yes',
                        'shortcode_notes' => 'steve/iTagg - unassigned',
                        'whichoperator' => 'Sinch/All',
                        'orderdate' => '00000000000000',
                        'datelastsent' => date("YmdHis"),
                        'datenextsendallowed' => date("YmdHis"),
                        'mblox_retire_date' => '00000000',
                        'pooled' => 0
                    ]);

                    // Insert into itagg_instance
                    DB::table('itagg_instance')->insert([
                        'smsshortcodes_id' => $nextId,
                        'users_bigid' => '73419c0c137c96c84a4490545e731838',
                        'purchased' => date("Y-m-d"),
                        'expiry' => '2050-01-20',
                        'modules_enabled' => 0,
                        'forwarding_email' => '',
                        'forwarding_url' => '',
                        'itagg_type_id' => 3,
                        'module_restrict' => 2,
                        'keyword' => '*',
                        'max_subkeywords' => 0
                    ]);

                    Log::info("Added Sinch number to smsshortcodes: {$msisdn}");
                }
            }

            Log::info('Sinch numbers sync completed');
        } catch (\Exception $e) {
            Log::error('Error syncing Sinch numbers: ' . $e->getMessage());
        }
    }

    /**
     * Map Sinch number type to system type
     */
    private function mapSinchType($sinchType)
    {
        $typeMap = [
            'MOBILE' => 'mobile-lvn',
            'LOCAL' => 'landline',
            'TOLL_FREE' => 'landline-toll-free'
        ];

        return $typeMap[strtoupper($sinchType)] ?? 'mobile-lvn';
    }

    /**
     * Export virtual numbers to CSV (opens cleanly in Excel).
     * Honours the same search / operator / customer / status filters as the index page.
     */
    public function export(Request $request)
    {
        try {
            $search   = $request->get('search', '');
            $operator = $request->get('operator', '');
            $customer = $request->get('customer', '');
            $status   = $request->get('status', '');

            // Mirrors the deduplicating JOIN pattern in index(): collapse
            // smsshortcodes to latest per number AND itagg_instance to latest
            // per smsshortcodes_id, otherwise numbers with multiple historical
            // assignments (e.g. 447937946920) appear as duplicate rows in the
            // CSV.
            $query = VirtualNumber::query()
                ->select(
                    'virtual_numbers.id',
                    'virtual_numbers.msisdn',
                    'virtual_numbers.operator',
                    'virtual_numbers.country',
                    'virtual_numbers.type',
                    'virtual_numbers.mo_http_url',
                    'virtual_numbers.is_active',
                    'virtual_numbers.last_synced_at',
                    'users.contactname',
                    'users.busname',
                    'users.bigid as user_bigid'
                )
                ->leftJoinSub(function ($sub) {
                    $sub->from('smsshortcodes')
                        ->select('number', \DB::raw('MAX(id) as latest_id'))
                        ->groupBy('number');
                }, 'latest', 'virtual_numbers.msisdn', '=', 'latest.number')
                ->leftJoin('smsshortcodes as smss', 'smss.id', '=', 'latest.latest_id')
                ->leftJoinSub(function ($sub) {
                    $sub->from('itagg_instance')
                        ->select('smsshortcodes_id', \DB::raw('MAX(id) as latest_inst_id'))
                        ->groupBy('smsshortcodes_id');
                }, 'inst_latest', 'smss.id', '=', 'inst_latest.smsshortcodes_id')
                ->leftJoin('itagg_instance', 'itagg_instance.id', '=', 'inst_latest.latest_inst_id')
                ->leftJoin('users', 'itagg_instance.users_bigid', '=', 'users.bigid');

            if (!empty($operator)) {
                $query->where('virtual_numbers.operator', $operator);
            }
            if (!empty($customer)) {
                $query->where('users.bigid', $customer);
            }
            if ($status === 'available') {
                $query->whereNull('users.bigid');
            } elseif ($status === 'assigned') {
                $query->whereNotNull('users.bigid');
            }
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('users.busname', 'like', $like)
                        ->orWhere('users.contactname', 'like', $like)
                        ->orWhere('users.bigid', 'like', $like)
                        ->orWhere('virtual_numbers.msisdn', 'like', $like);
                });
            }

            $filename = 'virtual-numbers-' . date('Y-m-d-His') . '.csv';
            $headers = [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'no-store, no-cache',
            ];

            $callback = function () use ($query) {
                $fh = fopen('php://output', 'w');
                // UTF-8 BOM so Excel renders accented characters / £ correctly.
                fwrite($fh, "\xEF\xBB\xBF");
                fputcsv($fh, [
                    'MSISDN',
                    'Customer Name',
                    'Business Name',
                    'Operator',
                    'Country',
                    'Type',
                    'Status',
                    'MO HTTP URL',
                    'Last Synced',
                ]);

                $query->orderBy('virtual_numbers.id')->chunk(500, function ($rows) use ($fh) {
                    foreach ($rows as $r) {
                        fputcsv($fh, [
                            $r->msisdn ?? '',
                            trim(urldecode($r->contactname ?? '')),
                            trim(urldecode($r->busname ?? '')),
                            $r->operator ?? '',
                            $r->country ?? '',
                            $r->type ?? '',
                            $r->is_active ? 'Active' : 'Inactive',
                            $r->mo_http_url ?? '',
                            $r->last_synced_at ? \Carbon\Carbon::parse($r->last_synced_at)->format('Y-m-d H:i:s') : '',
                        ]);
                    }
                });

                fclose($fh);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('Error exporting virtual numbers: ' . $e->getMessage());
            return redirect()->route('admin.virtual-numbers.index')
                ->with('error', 'Failed to export virtual numbers.');
        }
    }

    /**
     * Show form to search and add new number
     */
    public function create()
    {
        // Get webhook URLs for display
        $nexmoWebhookUrl = route('webhook.inbound-sms');

        // Sinch uses env variable or fallback to API route
        $sinchWebhookUrl = env('SINCH_INBOUND_WEBHOOK_URL');
        if (empty($sinchWebhookUrl)) {
            $sinchWebhookUrl = rtrim(config('app.url'), '/') . '/api/webhook/inbound-sms';
        }

        return view('admin.virtual-numbers.create', [
            'nexmoWebhookUrl' => $nexmoWebhookUrl,
            'sinchWebhookUrl' => $sinchWebhookUrl,
        ]);
    }

    /**
     * Search available numbers from Nexmo or Sinch
     */
    public function searchAvailableNumbers(Request $request)
    {
        try {
            $request->validate([
                'country' => 'required|string|size:2',
                'type' => 'nullable|string',
                'features' => 'nullable|string',
                'provider' => 'nullable|string|in:nexmo,sinch',
            ]);

            $country = strtoupper($request->input('country'));
            $type = $request->input('type', 'mobile-lvn');
            $features = $request->input('features', 'SMS,VOICE');
            $provider = $request->input('provider', 'nexmo');

            // Search based on provider
            if ($provider === 'sinch') {
                $result = $this->sinchService->searchAvailableNumbers($country, $type, $features);
            } else {
                $result = $this->nexmoService->searchAvailableNumbers($country, $type, $features);
            }

            if (!$result) {
                // Check if credentials are configured
                if ($provider === 'sinch') {
                    $projectId = config('sinch.numbers_project_id');
                    $keyId = config('sinch.numbers_key_id');
                    $keySecret = config('sinch.numbers_key_secret');

                    if (empty($projectId) || empty($keyId) || empty($keySecret)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Sinch Numbers API credentials not configured. Please set SINCH_NUMBERS_PROJECT_ID, SINCH_NUMBERS_KEY_ID, and SINCH_NUMBERS_KEY_SECRET in your .env file.'
                        ], 500);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to ' . ucfirst($provider) . ' API. Check logs for details.'
                ], 500);
            }

            if (!isset($result['numbers']) || empty($result['numbers'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available numbers found from ' . ucfirst($provider) . ' for ' . $country . ' (' . $type . ').'
                ], 404);
            }

            // Process numbers - Sinch already returns prices in local currency (GBP for UK)
            // Nexmo may need conversion from EUR
            $numbers = array_map(function($number) use ($provider, $country) {
                // Sinch already provides correct currency, no conversion needed
                if ($provider === 'sinch') {
                    return $number;
                }

                // For Nexmo, convert EUR to GBP if needed
                if (isset($number['cost']) && is_numeric($number['cost'])) {
                    $countryRecord = DB::table('country')
                        ->where('iso_code', $country)
                        ->first();

                    $exchangeRate = 0.85; // Default fallback rate EUR to GBP
                    if ($countryRecord && isset($countryRecord->exchange_rate_eur_to_gbp) && $countryRecord->exchange_rate_eur_to_gbp > 0) {
                        $exchangeRate = $countryRecord->exchange_rate_eur_to_gbp;
                    }

                    $costEur = floatval($number['cost']);
                    $costGbp = $costEur * $exchangeRate;
                    $number['cost'] = number_format($costGbp, 2);
                    $number['currency'] = 'GBP';
                }
                return $number;
            }, $result['numbers']);

            return response()->json([
                'success' => true,
                'numbers' => $numbers,
                'provider' => $provider
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching available numbers: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to search available numbers.'
            ], 500);
        }
    }

    /**
     * Buy and add a new number from Nexmo or Sinch
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'country' => 'required|string|size:2',
                'msisdn' => 'required|string',
                'provider' => 'nullable|string|in:nexmo,sinch',
            ]);

            $country = strtoupper($request->input('country'));
            $msisdn = $request->input('msisdn');
            $provider = $request->input('provider', 'nexmo');

            // Check if number already exists in database
            if (VirtualNumber::where('msisdn', $msisdn)->exists()) {
                return redirect()->back()
                    ->with('error', 'This number already exists in the database.');
            }

            // Buy number based on provider
            if ($provider === 'sinch') {
                $result = $this->sinchService->buyNumber($country, $msisdn);
                Log::info('response Sinch buy Number : ', $result ?? []);

                if (isset($result['error-code'])) {
                    $errorMsg = $result['error-code-label'] ?? 'Unknown error';

                    // Provide helpful message for common errors
                    if ($result['error-code'] == 404) {
                        // Check if it's a GB/UK number - these require compliance documentation
                        if (strtoupper($country) === 'GB') {
                            $errorMsg = 'UK mobile numbers require compliance documentation. Please upload KYC/compliance documents in your Sinch dashboard (https://dashboard.sinch.com) before purchasing UK numbers. If documents are already uploaded, the number may no longer be available - please search again.';
                        } else {
                            $errorMsg = 'This number is no longer available. Please search again and select a different number.';
                        }
                    }

                    return redirect()->back()
                        ->with('error', 'Failed to buy number from Sinch: ' . $errorMsg);
                }

                // Setup webhook for inbound SMS
                // Use configured webhook URL or generate from APP_URL
                $webhookUrl = env('SINCH_INBOUND_WEBHOOK_URL');
                if (empty($webhookUrl)) {
                    // Generate URL from APP_URL (should be public URL in production)
                    $baseUrl = rtrim(config('app.url'), '/');
                    $webhookUrl = $baseUrl . '/api/webhook/inbound-sms';
                }

                Log::info('Setting up Sinch webhook', [
                    'msisdn' => $msisdn,
                    'webhook_url' => $webhookUrl
                ]);

                $updateResult = $this->sinchService->updateNumber($country, $msisdn, $webhookUrl);
                Log::info('response Sinch update Number : ', $updateResult ?? []);

                if (isset($updateResult['error-code'])) {
                    Log::warning("Number purchased but webhook setup failed for {$msisdn}", [
                        'error' => $updateResult['error-code-label'] ?? 'Unknown'
                    ]);
                }

                $whichOperator = 'Sinch/All';
            } else {
                // Nexmo (default)
                $result = $this->nexmoService->buyNumber($country, $msisdn);
                Log::info('response Nexmo buy Number : ', $result);

                if (isset($result['error-code']) && $result['error-code'] != 200) {
                    return redirect()->back()
                        ->with('error', 'Failed to buy number: ' . ($result['error-code-label'] ?? 'Unknown error'));
                }

                // Setup webhook for inbound SMS
                $webhookUrl = route('webhook.inbound-sms');
                $updateResult = $this->nexmoService->updateNumber($country, $msisdn, $webhookUrl);
                Log::info('response Nexmo update Number : ', $result);

                if (isset($updateResult['error-code'])) {
                    Log::warning("Number purchased but webhook setup failed for {$msisdn}");
                }

                $whichOperator = 'Nexmo/all';
            }

            try {
                DB::beginTransaction();
                $nextId = DB::table('smsshortcodes')->insertGetId([
                    'number' => $msisdn,
                    'cost' => 0,
                    'adult' => 0,
                    'must_respond' => 1,
                    'can_send' => 0,
                    'itagg_premiumroute' => 1,
                    'itagg_standardroute' => 1,
                    'module_restrict' => 8298495,
                    'show_cp_subkeyword_management' => 1,
                    'content_type_restrict' => '',
                    'is_dedicated' => 'yes',
                    'shortcode_notes' => 'steve/iTagg - unassigned',
                    'whichoperator' => $whichOperator,
                    'orderdate' => '00000000000000',
                    'datelastsent' => date("YmdHis"),
                    'datenextsendallowed' => date("YmdHis"),
                    'mblox_retire_date' => '00000000',
                    'pooled' => 0
                ]);

                // Insert into itagg_instance
                DB::table('itagg_instance')->insert([
                    'smsshortcodes_id' => $nextId,
                    'users_bigid' => '73419c0c137c96c84a4490545e731838',
                    'purchased' => date("Y-m-d"),
                    'expiry' => '2050-01-20',
                    'modules_enabled' => 0,
                    'forwarding_email' => '',
                    'forwarding_url' => '',
                    'itagg_type_id' => 3,
                    'module_restrict' => 2,
                    'keyword' => '*',
                    'max_subkeywords' => 0
                ]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to insert smsshortcode/itagg_instance', [
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'input_number' => $msisdn ?? null,
                ]);
            }

            // Trigger sync to add to database
            SyncVirtualNumbersJob::dispatch();

            $providerLabel = ucfirst($provider);
            return redirect()->route('admin.virtual-numbers.index')
                ->with('success', "Number {$msisdn} purchased from {$providerLabel} successfully and webhook configured!");
        } catch (\Exception $e) {
            Log::error('Error adding new number: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to add new number. Please try again.');
        }
    }

    /**
     * Cancel and remove a number from all tables
     * Deletes from: virtual_numbers, smsshortcodes, itagg_instance
     * Handles both Nexmo and Sinch numbers
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $number = VirtualNumber::findOrFail($id);
            $msisdn = $number->msisdn;

            Log::info('Starting deletion process for number: ' . $msisdn);

            // Determine provider from smsshortcodes table
            $shortcode = DB::table('smsshortcodes')
                ->where('number', $msisdn)
                ->first();

            $provider = 'nexmo'; // Default
            if ($shortcode && isset($shortcode->whichoperator)) {
                $operator = strtolower($shortcode->whichoperator);
                if (str_contains($operator, 'sinch') || str_contains($operator, 'mblox')) {
                    $provider = 'sinch';
                }
            }

            // Cancel number from the appropriate provider
            if ($provider === 'sinch') {
                $result = $this->sinchService->cancelNumber($number->country, $msisdn);
                Log::info('response Sinch cancel Number : ', $result ?? []);
                if (isset($result['error-code'])) {
                    Log::warning('Sinch cancellation failed but continuing with local deletion: ' . ($result['error-code-label'] ?? 'Unknown error'));
                }
            } else {
                $result = $this->nexmoService->cancelNumber($number->country, $msisdn);
                Log::info('response Nexmo cancel Number : ', $result);
                if (isset($result['error-code'])) {
                    Log::warning('Nexmo cancellation failed but continuing with local deletion: ' . ($result['error-code-label'] ?? 'Unknown error'));
                }
            }

            // Step 1: Find all smsshortcodes with this number
            $shortcodes = DB::table('smsshortcodes')
                ->where('number', $msisdn)
                ->get();

            $shortcodeIds = $shortcodes->pluck('id')->toArray();

            Log::info('Found ' . count($shortcodeIds) . ' shortcode(s) for number: ' . $msisdn, ['shortcode_ids' => $shortcodeIds]);

            // Step 2: Delete from itagg_instance table (child records)
            if (!empty($shortcodeIds)) {
                $deletedItagg = DB::table('itagg_instance')
                    ->whereIn('smsshortcodes_id', $shortcodeIds)
                    ->delete();

                Log::info('Deleted ' . $deletedItagg . ' record(s) from itagg_instance');
            }

            // Step 3: Delete from smsshortcodes table (parent records)
            $deletedShortcodes = DB::table('smsshortcodes')
                ->where('number', $msisdn)
                ->delete();

            Log::info('Deleted ' . $deletedShortcodes . ' record(s) from smsshortcodes');

            // Step 4: Delete from virtual_numbers table
            $number->delete();

            Log::info('Deleted record from virtual_numbers for: ' . $msisdn);

            DB::commit();

            Log::info('Successfully completed deletion for number: ' . $msisdn);

            $providerLabel = ucfirst($provider);
            return redirect()->route('admin.virtual-numbers.index')
                ->with('success', "Number {$msisdn} cancelled from {$providerLabel} and removed from all tables successfully!");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling number: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return redirect()->back()
                ->with('error', 'Failed to cancel number: ' . $e->getMessage());
        }
    }

    /**
     * Manually add a number that was purchased directly from Sinch/Nexmo website
     * This does NOT call the provider's buy API - it just adds to local database
     */
    public function storeManual(Request $request)
    {
        try {
            $request->validate([
                'msisdn' => 'required|string|min:10|max:15',
                'country' => 'required|string|size:2',
                'provider' => 'required|string|in:nexmo,sinch',
                'type' => 'nullable|string',
                'features' => 'nullable|string',
                'configure_webhook' => 'nullable|boolean',
            ]);

            $msisdn = preg_replace('/[^0-9]/', '', $request->input('msisdn'));
            $country = strtoupper($request->input('country'));
            $provider = $request->input('provider');
            $type = $request->input('type', 'mobile-lvn');
            $features = $request->input('features', 'SMS');
            $configureWebhook = $request->boolean('configure_webhook', true);

            // Check if number already exists in smsshortcodes
            $existsShortcode = DB::table('smsshortcodes')
                ->where('number', $msisdn)
                ->exists();

            if ($existsShortcode) {
                return redirect()->back()
                    ->with('error', "Number {$msisdn} already exists in the system.");
            }

            // Check if number already exists in virtual_numbers
            if (VirtualNumber::where('msisdn', $msisdn)->exists()) {
                return redirect()->back()
                    ->with('error', "Number {$msisdn} already exists in virtual numbers table.");
            }

            // Determine whichoperator based on provider
            $whichOperator = ($provider === 'sinch') ? 'Sinch/All' : 'Nexmo/all';

            // Determine webhook URL
            $webhookUrl = null;
            if ($configureWebhook) {
                if ($provider === 'sinch') {
                    $webhookUrl = env('SINCH_INBOUND_WEBHOOK_URL');
                    if (empty($webhookUrl)) {
                        $baseUrl = rtrim(config('app.url'), '/');
                        $webhookUrl = $baseUrl . '/api/webhook/inbound-sms';
                    }
                } else {
                    $webhookUrl = route('webhook.inbound-sms');
                }
            }

            try {
                DB::beginTransaction();

                // Insert into smsshortcodes table
                $nextId = DB::table('smsshortcodes')->insertGetId([
                    'number' => $msisdn,
                    'cost' => 0,
                    'adult' => 0,
                    'must_respond' => 1,
                    'can_send' => 0,
                    'itagg_premiumroute' => 1,
                    'itagg_standardroute' => 1,
                    'module_restrict' => 8298495,
                    'show_cp_subkeyword_management' => 1,
                    'content_type_restrict' => '',
                    'is_dedicated' => 'yes',
                    'shortcode_notes' => 'steve/iTagg - unassigned',
                    'whichoperator' => $whichOperator,
                    'orderdate' => '00000000000000',
                    'datelastsent' => date("YmdHis"),
                    'datenextsendallowed' => date("YmdHis"),
                    'mblox_retire_date' => '00000000',
                    'pooled' => 0
                ]);

                // Insert into itagg_instance
                DB::table('itagg_instance')->insert([
                    'smsshortcodes_id' => $nextId,
                    'users_bigid' => '73419c0c137c96c84a4490545e731838', // Default unassigned user
                    'purchased' => date("Y-m-d"),
                    'expiry' => '2050-01-20',
                    'modules_enabled' => 0,
                    'forwarding_email' => '',
                    'forwarding_url' => '',
                    'itagg_type_id' => 3,
                    'module_restrict' => 2,
                    'keyword' => '*',
                    'max_subkeywords' => 0
                ]);

                // Insert into virtual_numbers table
                VirtualNumber::create([
                    'msisdn' => $msisdn,
                    'operator' => $provider === 'sinch' ? 'Sinch' : 'Nexmo',
                    'country' => $country,
                    'type' => $type,
                    'features' => explode(',', $features),
                    'mo_http_url' => $webhookUrl,
                    'is_active' => true,
                    'last_synced_at' => now(),
                ]);

                DB::commit();

                Log::info('Manual number added successfully', [
                    'msisdn' => $msisdn,
                    'provider' => $provider,
                    'country' => $country,
                    'shortcode_id' => $nextId
                ]);

                // Configure webhook with provider API
                $webhookStatus = '';
                if ($configureWebhook && $webhookUrl) {
                    try {
                        if ($provider === 'sinch') {
                            // For Sinch, need to format phone number with + prefix
                            $formattedMsisdn = '+' . $msisdn;
                            $updateResult = $this->sinchService->updateNumber($country, $formattedMsisdn, $webhookUrl);

                            Log::info('Sinch webhook configuration result', [
                                'msisdn' => $msisdn,
                                'webhook_url' => $webhookUrl,
                                'result' => $updateResult
                            ]);

                            if (isset($updateResult['error-code'])) {
                                $webhookStatus = ' (Warning: Webhook setup failed - ' . ($updateResult['error-code-label'] ?? 'Unknown error') . ')';
                            } else {
                                $webhookStatus = ' Webhook configured successfully!';
                            }
                        } else {
                            // Nexmo
                            $updateResult = $this->nexmoService->updateNumber($country, $msisdn, $webhookUrl);

                            Log::info('Nexmo webhook configuration result', [
                                'msisdn' => $msisdn,
                                'webhook_url' => $webhookUrl,
                                'result' => $updateResult
                            ]);

                            if (isset($updateResult['error-code']) && $updateResult['error-code'] != 200) {
                                $webhookStatus = ' (Warning: Webhook setup failed - ' . ($updateResult['error-code-label'] ?? 'Unknown error') . ')';
                            } else {
                                $webhookStatus = ' Webhook configured successfully!';
                            }
                        }
                    } catch (\Exception $webhookError) {
                        Log::error('Webhook configuration failed', [
                            'msisdn' => $msisdn,
                            'provider' => $provider,
                            'error' => $webhookError->getMessage()
                        ]);
                        $webhookStatus = ' (Warning: Webhook setup failed - ' . $webhookError->getMessage() . ')';
                    }
                }

                $providerLabel = ucfirst($provider);
                return redirect()->route('admin.virtual-numbers.index')
                    ->with('success', "Number {$msisdn} ({$providerLabel}) added manually to the system successfully!{$webhookStatus}");

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Failed to insert manual number', [
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'msisdn' => $msisdn,
                ]);

                return redirect()->back()
                    ->with('error', 'Failed to add number: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            Log::error('Error adding manual number: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to add number. Please try again.');
        }
    }

    /**
     * Update webhook URL for a number
     */
    public function updateWebhook(Request $request, $id)
    {
        try {
            $request->validate([
                'webhook_url' => 'nullable|url',
            ]);

            $number = VirtualNumber::findOrFail($id);
            $webhookUrl = $request->input('webhook_url', route('webhook.inbound-sms'));

            // Update webhook in Nexmo
            $result = $this->nexmoService->updateNumber($number->country, $number->msisdn, $webhookUrl);

            if (isset($result['error-code'])) {
                return redirect()->back()
                    ->with('error', 'Failed to update webhook: ' . ($result['error-code-label'] ?? 'Unknown error'));
            }

            // Update in database
            $number->update(['mo_http_url' => $webhookUrl]);

            return redirect()->back()
                ->with('success', 'Webhook URL updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating webhook: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to update webhook. Please try again.');
        }
    }

    /**
     * Get all active users for assign/reassign dropdown
     */
    public function getAllUsers()
    {
        try {
            $users = $this->getLegacyCustomers()
                ->map(function ($user) {
                    if (!empty($user->busname)) {
                        $user->busname = urldecode($user->busname);
                    }

                    // Legacy DB stores text as ISO-8859-1 (latin1). After urldecode,
                    // names with high bytes (£, accents) are NOT valid UTF-8, and
                    // json_encode aborts the whole response with "Malformed UTF-8
                    // characters". Convert any non-UTF-8 string field to UTF-8 so the
                    // endpoint can't 500 on a single bad row.
                    foreach (get_object_vars($user) as $key => $value) {
                        if (is_string($value) && $value !== '' && !mb_check_encoding($value, 'UTF-8')) {
                            $user->{$key} = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                        }
                    }

                    return $user;
                })
                ->values();

            // Belt-and-suspenders: substitute (don't throw on) any residual bad bytes.
            return response()->json([
                'success' => true,
                'users' => $users
            ], 200, [], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    /**
     * Assign a virtual number to a user (when currently unassigned)
     */
    public function assignNumber(Request $request)
    {
        try {
            $request->validate([
                'number' => 'required|string',
                'user_bigid' => 'required|string'
            ]);

            $number = $request->input('number');
            $userBigid = $request->input('user_bigid');

            // Get the user details
            $user = \App\Models\User::where('bigid', $userBigid)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get the shortcode id
            $shortcode = SmsShortcode::where('number', 'LIKE', $number)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$shortcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shortcode not found'
                ], 404);
            }

            // Call managepooledvirts logic
            $this->managepooledvirts($number, $userBigid, $user->busname);

            return response()->json([
                'success' => true,
                'message' => "Number {$number} assigned to {$user->busname} successfully"
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reassign a virtual number to a different user
     */
    public function reassignNumber(Request $request)
    {
        try {
            $request->validate([
                'number' => 'required|string',
                'user_bigid' => 'required|string'
            ]);

            $number = $request->input('number');
            $userBigid = $request->input('user_bigid');

            // Get the user details
            $user = \App\Models\User::where('bigid', $userBigid)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get the shortcode id
            $shortcode = SmsShortcode::where('number', 'LIKE', $number)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$shortcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shortcode not found'
                ], 404);
            }

            // Update the itagg_instance table
            DB::table('itagg_instance')
                ->where('smsshortcodes_id', $shortcode->id)
                ->update([
                    'users_bigid' => $userBigid,
                    'purchased'   => Carbon::today()->format('Y-m-d'),
                    'expiry'      => Carbon::today()->addYear()->format('Y-m-d'),
                ]);

            // Update shortcode notes
            DB::table('smsshortcodes')
                ->where('id', $shortcode->id)
                ->update([
                    'shortcode_notes' => $user->busname,
                    'pooled' => 0
                ]);

            return response()->json([
                'success' => true,
                'message' => "Number {$number} reassigned to {$user->busname} successfully"
            ]);
        } catch (\Exception $e) {
            Log::error('Error reassigning number: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reassign number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add Nexmo UK number to pool
     * Converts old mysql_* implementation to Laravel Query Builder
     */
    public function addNexmoUkToPool(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'msisdn' => 'required|string'
            ]);

            $msisdn = $request->input('msisdn');
            $pooled = 0;
            $today = date("Y-m-d");
            $pooloption = "addnexmouk";
            $num = $msisdn;



            // Insert into smsshortcodes
            $nextId = DB::table('smsshortcodes')->insertGetId([
                'number' => $num,
                'shortcode_touse' => 0,
                'smsenabled' => 0,
                'blocked' => 1,
                'ppp' => 0,
                'reply' => 1,
                'ussd' => 1,
                'whichoperator' => 'Nexmo/all',
                'pooled' => 1,
                'shortcode_notes' => '',
                'paidfor' => 'yes',
                'text' => 'steve/iTagg - unassigned',
                'country' => 'Nexmo/all',
                'orderdate' => '00000000000000',
                'setupdatetime' => date("YmdHis"),
                'lastchecked' => date("YmdHis"),
                'timestamp' => '00000000',
                'showonsite' => 0
            ]);

            // Insert into itagg_instance
            DB::table('itagg_instance')->insert([
                'smsshortcodes_id' => $nextId,
                'users_bigid' => '73419c0c137c96c84a4490545e731838',
                'purchased' => date("Y-m-d"),
                'expiry' => '2050-01-20',
                'modules_enabled' => 0,
                'forwarding_email' => '',
                'forwarding_url' => '',
                'itagg_type_id' => 3,
                'module_restrict' => 2,
                'keyword' => '*',
                'max_subkeywords' => 0
            ]);

            DB::commit();

            Log::info('Nexmo UK number added to pool successfully', [
                'msisdn' => $msisdn,
                'shortcode_id' => $nextId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Number added to pool successfully',
                'data' => [
                    'shortcode_id' => $nextId,
                    'msisdn' => $msisdn
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error adding Nexmo UK number to pool: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add number to pool: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manage pooled virtual numbers (implementation of managepooledvirts function)
     */
    private function managepooledvirts($number, $userBigid, $userName)
    {
        try {
            $notes = $userName;
            $pooled = 0;
            $today = date("Y-m-d");

            // Get the shortcode
            $shortcode = SmsShortcode::where('number', $number)->first();

            if (!$shortcode) {
                throw new \Exception("Shortcode not found");
            }

            // Update smsshortcodes
            DB::table('smsshortcodes')
                ->where('id', $shortcode->id)
                ->update([
                    'shortcode_notes' => $notes,
                    'orderdate' => date("YmdHis", time() + 7776000),
                    'pooled' => $pooled
                ]);

            // Update itagg_instance
            DB::table('itagg_instance')
                ->where('smsshortcodes_id', $shortcode->id)
                ->update([
                    'users_bigid' => $userBigid,
                    'purchased' => $today,
                    'expiry'      => Carbon::today()->addYear()->format('Y-m-d'),
                    'modules_enabled' => 0,
                    'forwarding_email' => '',
                    'forwarding_url' => ''
                ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error in managepooledvirts: ' . $e->getMessage());
            throw $e;
        }
    }
}
