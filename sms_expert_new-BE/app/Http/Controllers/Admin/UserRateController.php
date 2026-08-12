<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserRouteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * UserRateController - Admin controller for managing user SMS rates
 *
 * Implements OLD system rate management logic from:
 * - gajkdhgfajdhgfasjgfasjdhfgahdgfa.php (admin CRM)
 * - smsg_userroute table management
 */
class UserRateController extends Controller
{
    protected $userRouteService;

    public function __construct(UserRouteService $userRouteService)
    {
        $this->userRouteService = $userRouteService;
    }

    /**
     * Display all rates for a user
     *
     * GET /admin/user/{id}/rates
     */
    public function index($userId)
    {
        // Get user by id or bigid
        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $rates = $this->userRouteService->getUserRates($user->bigid);

        // Group rates by route and country for cleaner display
        $groupedRates = [];
        foreach ($rates as $rate) {
            $key = $rate->routenum . '_' . $rate->countrydialcode;
            if (!isset($groupedRates[$key])) {
                $groupedRates[$key] = [
                    'routenum' => $rate->routenum,
                    'countrydialcode' => $rate->countrydialcode,
                    'userprice' => $rate->userprice,
                    'costprice' => $rate->costprice,
                    'priority' => $rate->priority,
                    'shortinfo' => $rate->shortinfo,
                    'suppliername' => $rate->suppliername,
                    'profit' => round($rate->userprice - ($rate->costprice ?? 0), 4),
                    'origtype' => [],
                    'numbits' => []
                ];
            }
            if (!in_array($rate->origtype, $groupedRates[$key]['origtype'])) {
                $groupedRates[$key]['origtype'][] = $rate->origtype;
            }
            if (!in_array($rate->numbits, $groupedRates[$key]['numbits'])) {
                $groupedRates[$key]['numbits'][] = $rate->numbits;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'bigid' => $user->bigid,
                    'uname' => $user->uname ?? null,
                    'busname' => $user->busname ?? null
                ],
                'rates' => array_values($groupedRates),
                'raw_rates' => $rates
            ]
        ]);
    }

    /**
     * Add new rate for user
     *
     * POST /admin/user/{id}/rates
     *
     * Body:
     * {
     *   "routenum": 7002,
     *   "countrydialcode": "44",
     *   "userprice": 0.03,
     *   "priority": 1
     * }
     */
    public function store(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'routenum' => 'required|integer',
            'countrydialcode' => 'required|string|max:10',
            'userprice' => 'required|numeric|min:0|max:10',
            'priority' => 'integer|min:1|max:9999' // OLD SYSTEM priorities go up to 9999 (e.g. 9990, 9999)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if rate already exists - if so, UPDATE instead of rejecting (OLD SYSTEM behavior)
        $existingRate = DB::table('smsg_userroute')
            ->where('userref', $user->bigid)
            ->where('routenum', $request->routenum)
            ->where('countrydialcode', $request->countrydialcode)
            ->first();

        if ($existingRate) {
            // Rate exists - UPDATE it (OLD SYSTEM: allows updating existing rate)
            $updated = $this->userRouteService->updateUserRate(
                $user->bigid,
                $request->routenum,
                $request->countrydialcode,
                $request->userprice,
                $request->input('priority')
            );

            if ($updated > 0) {
                Log::info("Admin updated user rate", [
                    'admin_id' => auth()->id(),
                    'user_bigid' => $user->bigid,
                    'routenum' => $request->routenum,
                    'countrydialcode' => $request->countrydialcode,
                    'userprice' => $request->userprice
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Rate updated successfully (updated $updated record(s))"
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update rate'
            ], 500);
        }

        // Rate doesn't exist - ADD new rate
        $success = $this->userRouteService->addUserRate(
            $user->bigid,
            $request->routenum,
            $request->countrydialcode,
            $request->userprice,
            $request->input('priority', 1)
        );

        if ($success) {
            Log::info("Admin added user rate", [
                'admin_id' => auth()->id(),
                'user_bigid' => $user->bigid,
                'routenum' => $request->routenum,
                'countrydialcode' => $request->countrydialcode,
                'userprice' => $request->userprice
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rate added successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to add rate'
        ], 500);
    }

    /**
     * Update existing rate for user
     *
     * PUT /admin/user/{id}/rates/{routenum}/{countrydialcode}
     *
     * Body:
     * {
     *   "userprice": 0.035,
     *   "priority": 2
     * }
     */
    public function update(Request $request, $userId, $routenum, $countrydialcode)
    {
        $validator = Validator::make($request->all(), [
            'userprice' => 'required|numeric|min:0|max:10',
            'priority' => 'integer|min:1|max:9999' // OLD SYSTEM priorities go up to 9999 (e.g. 9990, 9999)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $updated = $this->userRouteService->updateUserRate(
            $user->bigid,
            $routenum,
            $countrydialcode,
            $request->userprice,
            $request->input('priority')
        );

        if ($updated > 0) {
            Log::info("Admin updated user rate", [
                'admin_id' => auth()->id(),
                'user_bigid' => $user->bigid,
                'routenum' => $routenum,
                'countrydialcode' => $countrydialcode,
                'new_price' => $request->userprice
            ]);

            return response()->json([
                'success' => true,
                'message' => "Updated $updated rate(s) successfully"
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No rates found to update'
        ], 404);
    }

    /**
     * Delete rate for user
     *
     * DELETE /admin/user/{id}/rates/{routenum}/{countrydialcode}?priority=1
     *
     * OLD SYSTEM LOGIC (from gajkdhgfajdhgfasjgfasjdhfgahdgfa.php):
     * DELETE FROM smsg_userroute WHERE userref = '$bigid' and priority = $ratespriority and countrydialcode = '44' and routenum = $ratesdel
     */
    public function destroy(Request $request, $userId, $routenum, $countrydialcode)
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Get optional priority parameter (matches old system delete logic)
        $priority = $request->query('priority');

        $deleted = $this->userRouteService->deleteUserRate(
            $user->bigid,
            $routenum,
            $countrydialcode,
            $priority
        );

        if ($deleted > 0) {
            Log::info("Admin deleted user rate", [
                'admin_id' => auth()->id(),
                'user_bigid' => $user->bigid,
                'routenum' => $routenum,
                'countrydialcode' => $countrydialcode
            ]);

            return response()->json([
                'success' => true,
                'message' => "Deleted $deleted rate(s) successfully"
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No rates found to delete'
        ], 404);
    }

    /**
     * Bulk add rates for user (when creating new customer)
     *
     * POST /admin/user/{id}/rates/bulk
     *
     * Body:
     * {
     *   "rates": [
     *     {"routenum": 7002, "countrydialcode": "44", "userprice": 0.03},
     *     {"routenum": 8, "countrydialcode": "all", "userprice": 0.05}
     *   ]
     * }
     */
    public function bulkStore(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'rates' => 'required|array|min:1',
            'rates.*.routenum' => 'required|integer',
            'rates.*.countrydialcode' => 'required|string|max:10',
            'rates.*.userprice' => 'required|numeric|min:0|max:10',
            'rates.*.priority' => 'integer|min:1|max:9999' // OLD SYSTEM priorities go up to 9999
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $successCount = 0;
        $failedRates = [];

        foreach ($request->rates as $rate) {
            $success = $this->userRouteService->addUserRate(
                $user->bigid,
                $rate['routenum'],
                $rate['countrydialcode'],
                $rate['userprice'],
                $rate['priority'] ?? 1
            );

            if ($success) {
                $successCount++;
            } else {
                $failedRates[] = $rate;
            }
        }

        Log::info("Admin bulk added user rates", [
            'admin_id' => auth()->id(),
            'user_bigid' => $user->bigid,
            'success_count' => $successCount,
            'failed_count' => count($failedRates)
        ]);

        return response()->json([
            'success' => true,
            'message' => "Added $successCount rate(s) successfully",
            'failed_rates' => $failedRates
        ]);
    }

    /**
     * Copy default rates to user
     *
     * POST /admin/user/{id}/rates/copy-defaults
     *
     * Body (optional):
     * {
     *   "default_price": 0.03
     * }
     */
    public function copyDefaults(Request $request, $userId)
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $defaultPrice = $request->input('default_price');

        $success = $this->userRouteService->copyDefaultRatesToUser(
            $user->bigid,
            $defaultPrice
        );

        if ($success) {
            Log::info("Admin copied default rates to user", [
                'admin_id' => auth()->id(),
                'user_bigid' => $user->bigid,
                'custom_price' => $defaultPrice
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Default rates copied successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to copy default rates'
        ], 500);
    }

    /**
     * Get available routes for dropdown/selection
     *
     * GET /admin/routes
     */
    public function getAvailableRoutes()
    {
        $routes = $this->userRouteService->getAvailableRoutes();

        return response()->json([
            'success' => true,
            'data' => $routes
        ]);
    }

    /**
     * Get pricing preview for user and phone number
     *
     * GET /admin/user/{id}/pricing-preview?phone=447912345678&routenum=7002
     */
    public function pricingPreview(Request $request, $userId)
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->orWhere('bigid', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $phoneNumber = $request->input('phone', '447912345678');
        $routenum = $request->input('routenum', 7002);
        $numParts = $request->input('num_parts', 1);
        $operator = $request->input('operator', 'vonage'); // vonage or sinch

        $pricing = $this->userRouteService->getPricingForPhoneNumber(
            $user->bigid,
            $phoneNumber,
            $routenum,
            7,        // numbits
            'alpha',  // origtype
            $operator // operator for country cost lookup
        );

        return response()->json([
            'success' => true,
            'data' => [
                'phone' => $phoneNumber,
                'routenum' => $routenum,
                'num_parts' => $numParts,
                'per_part' => $pricing,
                'total' => [
                    'userprice' => round($pricing['userprice'] * $numParts, 6),
                    'costprice' => round($pricing['costprice'] * $numParts, 6),
                    'profit' => round($pricing['profit'] * $numParts, 6)
                ]
            ]
        ]);
    }
}
