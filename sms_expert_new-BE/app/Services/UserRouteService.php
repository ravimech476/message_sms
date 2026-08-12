<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CostPriceService;

/**
 * UserRouteService - Implements OLD system pricing logic using smsg_userroute table
 *
 * This service replicates the old Core PHP system's get_userroutes() function
 * and pricing calculation logic from smsg_routes.inc and smsg_2send_body.inc
 *
 * Pricing Logic:
 * - userprice: from smsg_userroute table (user-specific rate)
 * - costprice: from CostPriceService using per-country Nexmo rates (getnexmorates())
 */
class UserRouteService
{
    /**
     * Default user reference for fallback rates (from old system)
     */
    const DEFAULT_USER_REF = '11111111111111111111111111111111';

    /**
     * Default cost price if not found
     */
    const DEFAULT_COST_PRICE = 0.040;

    /**
     * Default user price if not found
     */
    const DEFAULT_USER_PRICE = 0.050;

    /**
     * Get user routes with pricing - replicates old get_userroutes() function
     *
     * @param string $userBigId User's bigid
     * @param string $countryDialCode Country dial code (e.g., '44')
     * @param int $numbits Message encoding (7=text, 8=binary, 16=unicode)
     * @param string $origtype Originator type ('alpha', 'msisdn', 'shortcode')
     * @return array Array of routes with pricing
     */
    public function getUserRoutes($userBigId, $countryDialCode = '44', $numbits = 7, $origtype = 'alpha')
    {
        $sql = "SELECT r.routenum, ur.userprice, ur.origtype, ur.countrydialcode,
                       ur.priority, r.shortinfo, r.longinfo, r.costprice, r.suppliername,
                       r.supplierrouteref
                FROM smsg_userroute AS ur
                JOIN smsg_route AS r ON ur.routenum = r.routenum
                WHERE (ur.countrydialcode = ? OR ur.countrydialcode = 'all')
                  AND ur.numbits = ?
                  AND (ur.userref = ? OR ur.userref = ?)
                  AND r.routestatus = 'live'
                ORDER BY
                    CASE WHEN ur.userref = ? THEN 0 ELSE 1 END,
                    ur.priority DESC,
                    ur.userprice ASC";

        $results = DB::select($sql, [
            $countryDialCode,
            $numbits,
            $userBigId,
            self::DEFAULT_USER_REF,
            $userBigId
        ]);

        return $this->formatRoutes($results);
    }

    /**
     * Get user price for specific route and country
     * This is the main pricing function - replicates old smsg_2send_body.inc logic
     *
     * @param string $userBigId User's bigid
     * @param int $routenum Route number
     * @param string $countryDialCode Country dial code
     * @param int $numbits Message encoding bits
     * @param string $origtype Originator type
     * @return array ['userprice' => float, 'costprice' => float, 'profit' => float, 'suppliername' => string]
     */
    public function getUserPriceForRoute($userBigId, $routenum, $countryDialCode, $numbits = 7, $origtype = 'alpha')
    {
        // First try to get user-specific rate (using LEFT JOIN so smsg_route is optional)
        // OLD SYSTEM: smsg_userroute is the primary source for userprice
        $userRate = DB::table('smsg_userroute as ur')
            ->leftJoin('smsg_route as r', function($join) use ($countryDialCode) {
                $join->on('ur.routenum', '=', 'r.routenum')
                     ->where(function($q) use ($countryDialCode) {
                         $q->where('r.countrydialcode', '=', $countryDialCode)
                           ->orWhere('r.countrydialcode', '=', 'all')
                           ->orWhereNull('r.countrydialcode');
                     });
            })
            ->where('ur.userref', $userBigId)
            ->where('ur.routenum', $routenum)
            ->where(function ($query) use ($countryDialCode) {
                $query->where('ur.countrydialcode', $countryDialCode)
                      ->orWhere('ur.countrydialcode', 'all');
            })
            ->where('ur.numbits', $numbits)
            ->where('ur.origtype', $origtype)
            ->orderBy('ur.priority', 'desc')
            ->first(['ur.userprice', 'r.costprice', 'r.suppliername', 'r.supplierrouteref']);

        // If no user-specific rate, try default rate
        if (!$userRate) {
            $userRate = DB::table('smsg_userroute as ur')
                ->leftJoin('smsg_route as r', function($join) use ($countryDialCode) {
                    $join->on('ur.routenum', '=', 'r.routenum')
                         ->where(function($q) use ($countryDialCode) {
                             $q->where('r.countrydialcode', '=', $countryDialCode)
                               ->orWhere('r.countrydialcode', '=', 'all')
                               ->orWhereNull('r.countrydialcode');
                         });
                })
                ->where('ur.userref', self::DEFAULT_USER_REF)
                ->where('ur.routenum', $routenum)
                ->where(function ($query) use ($countryDialCode) {
                    $query->where('ur.countrydialcode', $countryDialCode)
                          ->orWhere('ur.countrydialcode', 'all');
                })
                ->where('ur.numbits', $numbits)
                ->where('ur.origtype', $origtype)
                ->orderBy('ur.priority', 'desc')
                ->first(['ur.userprice', 'r.costprice', 'r.suppliername', 'r.supplierrouteref']);
        }

        // If still no rate, use defaults
        if (!$userRate) {
            Log::warning("No rate found for user: $userBigId, route: $routenum, country: $countryDialCode");
            return [
                'userprice' => self::DEFAULT_USER_PRICE,
                'costprice' => self::DEFAULT_COST_PRICE,
                'profit' => self::DEFAULT_USER_PRICE - self::DEFAULT_COST_PRICE,
                'suppliername' => 'unknown',
                'supplierrouteref' => ''
            ];
        }

        $userPrice = floatval($userRate->userprice);
        $costPrice = floatval($userRate->costprice);
        $profit = $userPrice - $costPrice;

        return [
            'userprice' => $userPrice,
            'costprice' => $costPrice,
            'profit' => $profit,
            'suppliername' => $userRate->suppliername ?? 'unknown',
            'supplierrouteref' => $userRate->supplierrouteref ?? ''
        ];
    }

    /**
     * Get pricing by phone number - determines country and gets appropriate rate
     *
     * PRICING LOGIC:
     * - userprice: from smsg_userroute table (user-specific rate)
     * - costprice: from country table based on operator:
     *   - Vonage: country.cost_price_gbp
     *   - Sinch: country.sinch_cost_price_gbp
     *
     * @param string $userBigId User's bigid
     * @param string $phoneNumber Phone number (with country code)
     * @param int $routenum Route number
     * @param int $numbits Message encoding
     * @param string $origtype Originator type
     * @param string $operator Operator name ('vonage' or 'sinch')
     * @return array Pricing details
     */
    public function getPricingForPhoneNumber($userBigId, $phoneNumber, $routenum = 7002, $numbits = 7, $origtype = 'alpha', $operator = 'vonage')
    {
        $countryDialCode = $this->extractCountryCode($phoneNumber);

        // Get userprice from smsg_userroute (user-specific rate)
        $userRouteData = $this->getUserPriceForRoute($userBigId, $routenum, $countryDialCode, $numbits, $origtype);

        // Get costprice from country table based on operator
        $costPriceService = app(CostPriceService::class);
        $costprice = $costPriceService->getCostPrice($phoneNumber, $operator, $routenum);

        // Recalculate profit with accurate costprice
        $userprice = $userRouteData['userprice'];
        $profit = $userprice - $costprice;

        // Determine supplier name based on operator
        $suppliername = $operator === CostPriceService::OPERATOR_SINCH ? 'Sinch SMPP' : 'Vonage SMPP';

        return [
            'userprice' => $userprice,
            'costprice' => $costprice,
            'profit' => $profit,
            'suppliername' => $suppliername,
            'supplierrouteref' => $userRouteData['supplierrouteref'] ?? ''
        ];
    }

    /**
     * Calculate total cost for multiple phone numbers
     *
     * @param string $userBigId User's bigid
     * @param array $phoneNumbers Array of phone numbers
     * @param int $routenum Route number
     * @param int $numParts Number of message parts (for long SMS)
     * @param int $numbits Message encoding
     * @param string $origtype Originator type
     * @param string $operator Operator name ('vonage' or 'sinch')
     * @return array ['total_userprice' => float, 'total_costprice' => float, 'total_profit' => float, 'details' => array]
     */
    public function calculateTotalCost($userBigId, array $phoneNumbers, $routenum = 7002, $numParts = 1, $numbits = 7, $origtype = 'alpha', $operator = 'vonage')
    {
        $totalUserPrice = 0;
        $totalCostPrice = 0;
        $details = [];

        foreach ($phoneNumbers as $phoneNumber) {
            $pricing = $this->getPricingForPhoneNumber($userBigId, $phoneNumber, $routenum, $numbits, $origtype, $operator);

            $userPrice = $pricing['userprice'] * $numParts;
            $costPrice = $pricing['costprice'] * $numParts;

            $totalUserPrice += $userPrice;
            $totalCostPrice += $costPrice;

            $details[] = [
                'phone' => $phoneNumber,
                'userprice' => $userPrice,
                'costprice' => $costPrice,
                'profit' => $userPrice - $costPrice
            ];
        }

        return [
            'total_userprice' => round($totalUserPrice, 6),
            'total_costprice' => round($totalCostPrice, 6),
            'total_profit' => round($totalUserPrice - $totalCostPrice, 6),
            'details' => $details
        ];
    }

    /**
     * Extract country dial code from phone number
     *
     * @param string $phoneNumber Phone number
     * @return string Country dial code
     */
    public function extractCountryCode($phoneNumber)
    {
        // Clean phone number
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Remove leading zeros or plus
        if (substr($phoneNumber, 0, 2) === '00') {
            $phoneNumber = substr($phoneNumber, 2);
        }

        // Try to match country code (1-4 digits)
        $startBits = [
            substr($phoneNumber, 0, 1),
            substr($phoneNumber, 0, 2),
            substr($phoneNumber, 0, 3),
            substr($phoneNumber, 0, 4)
        ];

        $country = DB::table('country')
            ->whereIn('dialcode', $startBits)
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->first(['dialcode']);

        return $country ? $country->dialcode : '44'; // Default to UK
    }

    /**
     * Format routes array for output
     */
    private function formatRoutes($results)
    {
        $routes = [];
        foreach ($results as $row) {
            $routeNum = $row->routenum;

            if (!isset($routes[$routeNum])) {
                $routes[$routeNum] = [
                    'routenum' => $routeNum,
                    'userprice' => $row->userprice,
                    'costprice' => $row->costprice,
                    'countrydialcode' => $row->countrydialcode,
                    'priority' => $row->priority,
                    'shortinfo' => $row->shortinfo,
                    'longinfo' => $row->longinfo,
                    'suppliername' => $row->suppliername,
                    'origtype' => []
                ];
            }

            $routes[$routeNum]['origtype'][] = $row->origtype;
        }

        return array_values($routes);
    }

    /**
     * Get descriptive name for route number
     */
    public function getRouteDescriptiveName($routenum)
    {
        $routeNames = [
            2 => 'Standard',
            3 => 'Fixed Sender ID',
            4 => 'UK Bulk Marketing',
            6 => 'UK High Grade',
            7 => 'Direct',
            8 => 'Direct',
            9 => 'Personalised Route',
            10 => 'Personalised Route',
        ];

        if (isset($routeNames[$routenum])) {
            return $routeNames[$routenum];
        }

        if ($routenum >= 11 && $routenum <= 29) {
            return 'Personalised Route';
        }

        if ($routenum >= 3000 && $routenum <= 99999) {
            return 'Direct';
        }

        return 'No Description';
    }

    // ==========================================
    // ADMIN FUNCTIONS - Manage User Rates
    // ==========================================

    /**
     * Get all rates for a user (for admin display)
     *
     * @param string $userBigId User's bigid
     * @return \Illuminate\Support\Collection
     */
    public function getUserRates($userBigId)
    {
        return DB::table('smsg_userroute as ur')
            ->leftJoin('smsg_route as r', 'ur.routenum', '=', 'r.routenum')
            ->where('ur.userref', $userBigId)
            ->select(
                'ur.id',
                'ur.userref',
                'ur.routenum',
                'ur.countrydialcode',
                'ur.numbits',
                'ur.origtype',
                'ur.userprice',
                'ur.priority',
                'ur.username',
                'r.costprice',
                'r.shortinfo',
                'r.suppliername'
            )
            ->orderBy('ur.routenum')
            ->orderBy('ur.countrydialcode')
            ->orderBy('ur.priority', 'desc')
            ->get();
    }

    /**
     * Add new rate for user (admin function)
     * Replicates old admin panel logic from gajkdhgfajdhgfasjgfasjdhfgahdgfa.php
     *
     * @param string $userBigId User's bigid
     * @param int $routenum Route number
     * @param string $countryDialCode Country dial code
     * @param float $userPrice User price
     * @param int $priority Priority
     * @param string $username Optional username label
     * @return bool Success
     */
    public function addUserRate($userBigId, $routenum, $countryDialCode, $userPrice, $priority = 1, $username = 'special rate users')
    {
        // Insert for all origtype and numbits combinations (like old system)
        $insertData = [];

        foreach (['alpha', 'msisdn', 'shortcode'] as $origtype) {
            foreach ([7, 8] as $numbits) {
                $insertData[] = [
                    'userref' => $userBigId,
                    'username' => $username,
                    'routenum' => $routenum,
                    'countrydialcode' => $countryDialCode,
                    'numbits' => $numbits,
                    'origtype' => $origtype,
                    'userprice' => $userPrice,
                    'priority' => $priority
                ];
            }
        }

        try {
            DB::table('smsg_userroute')->insert($insertData);
            Log::info("Added user rates", ['userref' => $userBigId, 'routenum' => $routenum, 'price' => $userPrice]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to add user rate: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user rate
     *
     * @param string $userBigId User's bigid
     * @param int $routenum Route number
     * @param string $countryDialCode Country dial code
     * @param float $newPrice New user price
     * @param int|null $priority New priority (optional)
     * @return int Number of rows updated
     */
    public function updateUserRate($userBigId, $routenum, $countryDialCode, $newPrice, $priority = null)
    {
        $updateData = [
            'userprice' => $newPrice
        ];

        if ($priority !== null) {
            $updateData['priority'] = $priority;
        }

        return DB::table('smsg_userroute')
            ->where('userref', $userBigId)
            ->where('routenum', $routenum)
            ->where('countrydialcode', $countryDialCode)
            ->update($updateData);
    }

    /**
     * Delete user rate
     *
     * @param string $userBigId User's bigid
     * @param int $routenum Route number
     * @param string $countryDialCode Country dial code
     * @param int|null $priority Specific priority to delete (optional)
     * @return int Number of rows deleted
     */
    public function deleteUserRate($userBigId, $routenum, $countryDialCode, $priority = null)
    {
        $query = DB::table('smsg_userroute')
            ->where('userref', $userBigId)
            ->where('routenum', $routenum)
            ->where('countrydialcode', $countryDialCode);

        if ($priority !== null) {
            $query->where('priority', $priority);
        }

        return $query->delete();
    }

    /**
     * Copy default rates to new user
     *
     * @param string $userBigId New user's bigid
     * @param float $defaultPrice Optional default price (if not copying from default user)
     * @return bool Success
     */
    public function copyDefaultRatesToUser($userBigId, $defaultPrice = null)
    {
        if ($defaultPrice !== null) {
            // Create standard rates with provided price
            return $this->addUserRate($userBigId, 7002, '44', $defaultPrice) &&
                   $this->addUserRate($userBigId, 8, 'all', $defaultPrice);
        }

        // Copy from default user
        $defaultRates = DB::table('smsg_userroute')
            ->where('userref', self::DEFAULT_USER_REF)
            ->get();

        if ($defaultRates->isEmpty()) {
            Log::warning("No default rates found to copy");
            return false;
        }

        $insertData = [];

        foreach ($defaultRates as $rate) {
            $insertData[] = [
                'userref' => $userBigId,
                'username' => 'special rate users',
                'routenum' => $rate->routenum,
                'countrydialcode' => $rate->countrydialcode,
                'numbits' => $rate->numbits,
                'origtype' => $rate->origtype,
                'userprice' => $rate->userprice,
                'priority' => $rate->priority
            ];
        }

        try {
            DB::table('smsg_userroute')->insert($insertData);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to copy default rates: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available routes from smsg_route table
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAvailableRoutes()
    {
        return DB::table('smsg_route')
            ->where('routestatus', 'live')
            ->orderBy('routenum')
            ->get(['routenum', 'shortinfo', 'longinfo', 'costprice', 'suppliername', 'countrydialcode']);
    }
}
