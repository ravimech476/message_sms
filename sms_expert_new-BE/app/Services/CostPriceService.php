<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Country;

/**
 * CostPriceService - Gets costprice from country table
 *
 * Costprice is determined by:
 * - For Vonage: country.cost_price_gbp (fetched from Nexmo API)
 * - For Sinch: country.sinch_cost_price_gbp
 * - Fallback to default rates if not found
 */
class CostPriceService
{
    /**
     * Operator constants
     */
    const OPERATOR_VONAGE = 'vonage';
    const OPERATOR_SINCH = 'sinch';

    /**
     * Default costprice when country not found
     */
    const DEFAULT_COSTPRICE = 0.040;

    /**
     * Default UK costprice (fallback)
     */
    const UK_DEFAULT_COSTPRICE = 0.0165;

    /**
     * Get costprice for a phone number
     *
     * @param string $phoneNumber Phone number with country code
     * @param string $operator Operator name ('vonage' or 'sinch')
     * @param int $routenum Route number (optional, for legacy compatibility)
     * @return float Costprice in GBP
     */
    public function getCostPrice($phoneNumber, $operator = self::OPERATOR_VONAGE, $routenum = null)
    {
        $countryCode = $this->extractCountryCode($phoneNumber);

        // Get cost from country table based on operator
        $costPrice = $this->getCostPriceFromCountryTable($countryCode, $operator);

        if ($costPrice !== null && $costPrice > 0) {
            Log::debug("CostPrice from country table", [
                'country_code' => $countryCode,
                'operator' => $operator,
                'cost_price_gbp' => $costPrice
            ]);
            return $costPrice;
        }

        // Fallback to legacy logic if country table doesn't have the price
        Log::warning("CostPrice not found in country table, using fallback", [
            'country_code' => $countryCode,
            'operator' => $operator
        ]);

        return $this->getFallbackCostPrice($countryCode, $routenum);
    }

    /**
     * Get costprice from country table
     *
     * @param string $countryCode Country dial code
     * @param string $operator Operator name
     * @return float|null Costprice or null if not found
     */
    public function getCostPriceFromCountryTable($countryCode, $operator = self::OPERATOR_VONAGE)
    {
        $country = Country::where('dialcode', $countryCode)->first();

        if (!$country) {
            return null;
        }

        if ($operator === self::OPERATOR_SINCH) {
            return $country->sinch_cost_price_gbp;
        }

        // Default to Vonage
        return $country->cost_price_gbp;
    }

    /**
     * Get costprice for Vonage
     *
     * @param string $phoneNumber Phone number
     * @return float Costprice in GBP
     */
    public function getVonageCostPrice($phoneNumber)
    {
        return $this->getCostPrice($phoneNumber, self::OPERATOR_VONAGE);
    }

    /**
     * Get costprice for Sinch
     *
     * @param string $phoneNumber Phone number
     * @return float Costprice in GBP
     */
    public function getSinchCostPrice($phoneNumber)
    {
        return $this->getCostPrice($phoneNumber, self::OPERATOR_SINCH);
    }

    /**
     * Get costprice by country ISO code
     *
     * @param string $isoCode Country ISO code (e.g., 'GB', 'US')
     * @param string $operator Operator name
     * @return float Costprice in GBP
     */
    public function getCostPriceByIsoCode($isoCode, $operator = self::OPERATOR_VONAGE)
    {
        $country = Country::where('iso_code', strtoupper($isoCode))->first();

        if (!$country) {
            Log::warning("Country not found by ISO code", ['iso_code' => $isoCode]);
            return self::DEFAULT_COSTPRICE;
        }

        if ($operator === self::OPERATOR_SINCH) {
            return $country->sinch_cost_price_gbp ?: self::DEFAULT_COSTPRICE;
        }

        return $country->cost_price_gbp ?: self::DEFAULT_COSTPRICE;
    }

    /**
     * Get costprice by country dial code
     *
     * @param string $dialCode Country dial code (e.g., '44', '1')
     * @param string $operator Operator name
     * @return float Costprice in GBP
     */
    public function getCostPriceByDialCode($dialCode, $operator = self::OPERATOR_VONAGE)
    {
        $country = Country::where('dialcode', $dialCode)->first();

        if (!$country) {
            Log::warning("Country not found by dial code", ['dialcode' => $dialCode]);
            return self::DEFAULT_COSTPRICE;
        }

        if ($operator === self::OPERATOR_SINCH) {
            return $country->sinch_cost_price_gbp ?: self::DEFAULT_COSTPRICE;
        }

        return $country->cost_price_gbp ?: self::DEFAULT_COSTPRICE;
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

        // Remove leading zeros
        if (substr($phoneNumber, 0, 2) === '00') {
            $phoneNumber = substr($phoneNumber, 2);
        }

        // Try to match country code (1-4 digits)
        $startBits = [
            substr($phoneNumber, 0, 4),
            substr($phoneNumber, 0, 3),
            substr($phoneNumber, 0, 2),
            substr($phoneNumber, 0, 1)
        ];

        $country = DB::table('country')
            ->whereIn('dialcode', $startBits)
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->first(['dialcode']);

        return $country ? $country->dialcode : '44'; // Default to UK
    }

    /**
     * Fallback costprice logic when country table doesn't have the price
     *
     * @param string $countryCode Country dial code
     * @param int|null $routenum Route number
     * @return float Costprice
     */
    protected function getFallbackCostPrice($countryCode, $routenum = null)
    {
        // UK default
        if ($countryCode === '44') {
            return self::UK_DEFAULT_COSTPRICE;
        }

        // Cheapest live smsg_route costprice for this dialcode — served from the no-TTL
        // TableCache (precomputed with the same live/0.001<x<0.5/cheapest filters) instead
        // of a per-message DB query.
        $cost = app(\App\Services\TableCache::class)->cheapestRouteCost($countryCode);

        if ($cost !== null && $cost > 0) {
            return floatval($cost);
        }

        return self::DEFAULT_COSTPRICE;
    }

    /**
     * Get full pricing (userprice, costprice, profit) for a message
     *
     * @param string $userBigId User's bigid
     * @param string $phoneNumber Phone number
     * @param string $operator Operator name ('vonage' or 'sinch')
     * @param int $routenum Route number
     * @param int $numbits Encoding bits
     * @param string $origtype Originator type
     * @return array ['userprice' => float, 'costprice' => float, 'profit' => float]
     */
    public function getFullPricing($userBigId, $phoneNumber, $operator = self::OPERATOR_VONAGE, $routenum = 7002, $numbits = 7, $origtype = 'alpha')
    {
        // Get costprice from country table
        $costprice = $this->getCostPrice($phoneNumber, $operator);

        // Get userprice from smsg_userroute (user-specific rate)
        $userRouteService = app(UserRouteService::class);
        $userPricing = $userRouteService->getUserPriceForRoute($userBigId, $routenum, $this->extractCountryCode($phoneNumber), $numbits, $origtype);

        $userprice = $userPricing['userprice'];
        $profit = $userprice - $costprice;

        return [
            'userprice' => round($userprice, 6),
            'costprice' => round($costprice, 6),
            'profit' => round($profit, 6),
            'suppliername' => $operator === self::OPERATOR_SINCH ? 'Sinch SMPP' : 'Vonage SMPP'
        ];
    }
}
