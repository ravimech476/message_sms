<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CountryPricingController extends Controller
{
    /**
     * Update country base cost (EUR) - Manual update by admin
     */
    public function update(Request $request, $countryId)
    {
        $request->validate([
            'cost_price_eur' => 'required|numeric|min:0|max:999',
        ]);

        try {
            // Get country to check mode
            $country = DB::table('country')->where('id', $countryId)->first();

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            // Get current exchange rate
            $exchangeRate = DB::table('country')
                ->whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            $costEUR = floatval($request->cost_price_eur);
            $costGBP = round($costEUR * $exchangeRate, 4);

            // Update country pricing (manual update always allowed)
            DB::table('country')
                ->where('id', $countryId)
                ->update([
                    'cost_price_eur' => $costEUR,
                    'cost_price_gbp' => $costGBP,
                    'cost_per_sms' => $costEUR, // Keep for backward compatibility
                    'cost_price' => $costEUR, // Keep for backward compatibility
                    'exchange_rate_eur_to_gbp' => $exchangeRate,
                    'updated_at' => now(),
                ]);

            Log::info('Country pricing updated manually by admin', [
                'country_id' => $countryId,
                'country_name' => $country->name ?? 'Unknown',
                'price_mode' => $country->price_update_mode ?? 'api',
                'cost_eur' => $costEUR,
                'cost_gbp' => $costGBP,
                'exchange_rate' => $exchangeRate,
                'updated_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pricing updated successfully',
                    'data' => [
                        'cost_price_eur' => $costEUR,
                        'cost_price_gbp' => $costGBP,
                        'exchange_rate' => $exchangeRate,
                    ]
                ]);
            }

            return redirect()->back()->with('success', 'Country pricing updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update country pricing', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update pricing: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update pricing: ' . $e->getMessage());
        }
    }

    /**
     * Toggle API update setting for a specific country
     */
    public function toggleApiUpdate(Request $request)
    {
        try {
            $countryId = $request->input('country_id');
            $mode = $request->input('mode', 'api'); // 'api' or 'manual'
            $fetchPrice = $request->boolean('fetch_price', false);
            $isoCode = $request->input('iso_code');

            if (!$countryId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country ID is required'
                ], 400);
            }

            // Update the country's price update mode
            DB::table('country')
                ->where('id', $countryId)
                ->update([
                    'price_update_mode' => $mode,
                    'updated_at' => now(),
                ]);

            $country = DB::table('country')->where('id', $countryId)->first();
            $priceUpdated = false;
            $pricing = null;

            // If switching to API mode and fetch_price is true, fetch price from Nexmo
            // Pass forceUpdate=true since we're switching modes
            if ($mode === 'api' && $fetchPrice && $isoCode) {
                $pricingResult = $this->fetchPriceFromNexmo($countryId, $isoCode, true);
                if ($pricingResult['success']) {
                    $priceUpdated = true;
                    $pricing = $pricingResult['pricing'];
                }
            }

            Log::info('Country pricing mode changed', [
                'country_id' => $countryId,
                'country_name' => $country->name ?? 'Unknown',
                'mode' => $mode,
                'price_updated' => $priceUpdated,
                'changed_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $mode === 'api'
                        ? "API pricing enabled for {$country->name}"
                        : "Manual pricing enabled for {$country->name}",
                    'mode' => $mode,
                    'country_id' => $countryId,
                    'price_updated' => $priceUpdated,
                    'pricing' => $pricing,
                    'updated_at' => now()->toIso8601String(),
                ]);
            }

            return redirect()->back()->with('success', 'Country pricing mode updated');

        } catch (\Exception $e) {
            Log::error('Failed to toggle country pricing mode', [
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update setting');
        }
    }

    /**
     * Fetch price from Nexmo API for a specific country
     * Only allowed when country is in API mode or switching to API mode
     */
    private function fetchPriceFromNexmo($countryId, $isoCode, $forceUpdate = false)
    {
        try {
            // Check if country is in manual mode
            $country = DB::table('country')->where('id', $countryId)->first();

            if (!$country) {
                return ['success' => false, 'error' => 'Country not found'];
            }

            $currentMode = $country->price_update_mode ?? 'api';

            // Block API update if country is in manual mode (unless forcing for mode switch)
            if ($currentMode === 'manual' && !$forceUpdate) {
                Log::warning('Attempted to fetch Nexmo price for manual mode country', [
                    'country_id' => $countryId,
                    'country_name' => $country->name,
                    'iso_code' => $isoCode,
                ]);
                return [
                    'success' => false,
                    'error' => 'Cannot fetch API price - country is in Manual mode. Switch to API mode first.'
                ];
            }

            $apiKey = config('services.nexmo.key');
            $apiSecret = config('services.nexmo.secret');

            if (!$apiKey || !$apiSecret) {
                return ['success' => false, 'error' => 'Nexmo API credentials not configured'];
            }

            // Fetch price from Nexmo API
            $url = 'https://rest.nexmo.com/account/get-pricing/outbound/sms?' . http_build_query([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'country' => $isoCode
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: SMS-Expert/1.0'
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode !== 200) {
                Log::error('Nexmo API error', ['error' => $error, 'http_code' => $httpCode]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Nexmo API JSON parse error', [
                    'country' => $isoCode,
                    'error' => json_last_error_msg(),
                    'response_preview' => substr($response, 0, 500)
                ]);
                return ['success' => false, 'error' => 'Invalid JSON response'];
            }

            // Log raw response structure for debugging
            Log::info('Nexmo API raw response structure', [
                'country' => $isoCode,
                'has_networks' => isset($data['networks']),
                'networks_is_array' => isset($data['networks']) && is_array($data['networks']),
                'networks_count' => isset($data['networks']) && is_array($data['networks']) ? count($data['networks']) : 0,
                'default_price' => $data['defaultPrice'] ?? 'NOT SET',
                'country_name' => $data['countryName'] ?? $data['name'] ?? 'Unknown',
            ]);

            // Extract highest network price
            $highestPrice = null;
            $highestNetworkName = null;
            $networkCount = 0;
            $allPrices = [];

            if (isset($data['networks']) && is_array($data['networks'])) {
                $networkCount = count($data['networks']);

                foreach ($data['networks'] as $index => $network) {
                    $networkPrice = $network['smsPrice'] ?? $network['price'] ?? $network['mtPrice'] ?? null;
                    $networkName = $network['networkName'] ?? 'Unknown';

                    // Log first few networks for debugging
                    if ($index < 3) {
                        Log::info('Nexmo network price detail', [
                            'country' => $isoCode,
                            'network_index' => $index,
                            'network_name' => $networkName,
                            'smsPrice' => $network['smsPrice'] ?? 'NOT SET',
                            'price' => $network['price'] ?? 'NOT SET',
                            'mtPrice' => $network['mtPrice'] ?? 'NOT SET',
                            'extracted_price' => $networkPrice,
                        ]);
                    }

                    if ($networkPrice !== null && is_numeric($networkPrice)) {
                        $networkPrice = floatval($networkPrice);
                        $allPrices[] = ['name' => $networkName, 'price' => $networkPrice];

                        if ($highestPrice === null || $networkPrice > $highestPrice) {
                            $highestPrice = $networkPrice;
                            $highestNetworkName = $networkName;
                        }
                    }
                }

                // Log all prices for debugging
                Log::info('Nexmo API price extraction COMPLETE', [
                    'country' => $isoCode,
                    'network_count' => $networkCount,
                    'prices_extracted' => count($allPrices),
                    'default_price' => $data['defaultPrice'] ?? null,
                    'highest_price' => $highestPrice,
                    'highest_network' => $highestNetworkName,
                    'using_highest_not_default' => ($highestPrice !== null),
                    'top_5_prices' => array_slice(
                        collect($allPrices)->sortByDesc('price')->values()->toArray(),
                        0, 5
                    ),
                ]);
            } else {
                Log::warning('Nexmo API NO NETWORKS FOUND', [
                    'country' => $isoCode,
                    'networks_isset' => isset($data['networks']),
                    'networks_type' => isset($data['networks']) ? gettype($data['networks']) : 'not set',
                    'will_use_default_price' => $data['defaultPrice'] ?? null,
                ]);
            }

            // Fallback to default price
            if ($highestPrice === null) {
                $highestPrice = isset($data['defaultPrice']) ? floatval($data['defaultPrice']) : null;
                Log::warning('Nexmo API USING DEFAULT PRICE (no network prices found)', [
                    'country' => $isoCode,
                    'default_price' => $highestPrice,
                    'reason' => 'Either networks array empty or all prices were null/non-numeric'
                ]);
            }

            if ($highestPrice === null) {
                return ['success' => false, 'error' => 'No pricing data available'];
            }

            // Get exchange rate
            $exchangeRate = DB::table('country')
                ->whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            $costGBP = round($highestPrice * $exchangeRate, 4);

            // Update country pricing
            DB::table('country')
                ->where('id', $countryId)
                ->update([
                    'cost_price_eur' => $highestPrice,
                    'cost_price_gbp' => $costGBP,
                    'cost_per_sms' => $highestPrice,
                    'cost_price' => $highestPrice,
                    'exchange_rate_eur_to_gbp' => $exchangeRate,
                    'updated_at' => now(),
                ]);

            Log::info('Price fetched from Nexmo', [
                'country_id' => $countryId,
                'iso_code' => $isoCode,
                'cost_eur' => $highestPrice,
                'cost_gbp' => $costGBP,
            ]);

            return [
                'success' => true,
                'pricing' => [
                    'cost_price_eur' => $highestPrice,
                    'cost_price_gbp' => $costGBP,
                    'exchange_rate' => $exchangeRate,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch price from Nexmo', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bulk toggle API update setting for multiple countries
     */
    public function bulkToggleApiUpdate(Request $request)
    {
        try {
            $mode = $request->input('mode', 'api'); // 'api' or 'manual'
            $countryIds = $request->input('country_ids', []); // Empty = all countries

            $query = DB::table('country');

            if (!empty($countryIds)) {
                $query->whereIn('id', $countryIds);
            }

            $updated = $query->update([
                'price_update_mode' => $mode,
                'updated_at' => now(),
            ]);

            Log::info('Bulk country pricing mode changed', [
                'mode' => $mode,
                'count' => $updated,
                'changed_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Updated {$updated} countries to {$mode} mode",
                'count' => $updated,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk toggle pricing mode', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh price from Nexmo API for a specific country
     * Only allowed when country is in API mode
     */
    public function refreshPrice(Request $request, $countryId)
    {
        try {
            $country = DB::table('country')->where('id', $countryId)->first();

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            $currentMode = $country->price_update_mode ?? 'api';

            // Block refresh if country is in manual mode
            if ($currentMode === 'manual') {
                Log::warning('Attempted to refresh price for manual mode country', [
                    'country_id' => $countryId,
                    'country_name' => $country->name,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Cannot refresh price for {$country->name} - Country is in Manual mode. API updates are disabled."
                ], 403);
            }

            // Fetch price from Nexmo
            $result = $this->fetchPriceFromNexmo($countryId, $country->iso_code, false);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "Price refreshed for {$country->name}",
                    'pricing' => $result['pricing'],
                    'updated_at' => now()->toIso8601String(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch price: ' . ($result['error'] ?? 'Unknown error')
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to refresh country price', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update exchange rate and recalculate all country GBP prices
     */
    public function updateExchangeRate(Request $request)
    {
        $request->validate([
            'exchange_rate' => 'required|numeric|min:0.0001|max:2',
        ]);

        try {
            $newRate = floatval($request->exchange_rate);
            $updatedCount = 0;

            // Get all countries with EUR prices
            $countries = DB::table('country')
                ->whereNotNull('cost_price_eur')
                ->where('cost_price_eur', '>', 0)
                ->get();

            foreach ($countries as $country) {
                $costEUR = floatval($country->cost_price_eur);
                $costGBP = round($costEUR * $newRate, 4);

                DB::table('country')
                    ->where('id', $country->id)
                    ->update([
                        'cost_price_gbp' => $costGBP,
                        'exchange_rate_eur_to_gbp' => $newRate,
                        'updated_at' => now(),
                    ]);

                $updatedCount++;
            }

            Log::info('Exchange rate updated and all countries recalculated', [
                'new_rate' => $newRate,
                'countries_updated' => $updatedCount,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Exchange rate updated to {$newRate}",
                'exchange_rate' => $newRate,
                'updated_count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update exchange rate', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current pricing settings
     */
    public function getSettings()
    {
        $setting = DB::table('cron_job_settings')
            ->where('command', 'sms:update-pricing')
            ->first();

        $exchangeRate = DB::table('country')
            ->whereNotNull('exchange_rate_eur_to_gbp')
            ->where('exchange_rate_eur_to_gbp', '>', 0)
            ->orderBy('updated_at', 'desc')
            ->value('exchange_rate_eur_to_gbp') ?? 0.85;

        return response()->json([
            'api_enabled' => $setting ? (bool) $setting->enabled : true,
            'exchange_rate' => $exchangeRate,
            'last_updated' => $setting->updated_at ?? null,
        ]);
    }

    /**
     * Update Sinch pricing for a country (manual only - no API)
     */
    public function updateSinchPrice(Request $request, $countryId)
    {
        $request->validate([
            'sinch_cost_price_eur' => 'required|numeric|min:0|max:999',
        ]);

        try {
            $country = DB::table('country')->where('id', $countryId)->first();

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            // Get current exchange rate
            $exchangeRate = DB::table('country')
                ->whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            $costEUR = floatval($request->sinch_cost_price_eur);
            $costGBP = round($costEUR * $exchangeRate, 4);

            // Update Sinch pricing
            DB::table('country')
                ->where('id', $countryId)
                ->update([
                    'sinch_cost_price_eur' => $costEUR,
                    'sinch_cost_price_gbp' => $costGBP,
                    'sinch_price_updated_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('Sinch pricing updated manually by admin', [
                'country_id' => $countryId,
                'country_name' => $country->name ?? 'Unknown',
                'sinch_cost_eur' => $costEUR,
                'sinch_cost_gbp' => $costGBP,
                'exchange_rate' => $exchangeRate,
                'updated_by' => auth()->id(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sinch pricing updated successfully',
                    'data' => [
                        'sinch_cost_price_eur' => $costEUR,
                        'sinch_cost_price_gbp' => $costGBP,
                        'exchange_rate' => $exchangeRate,
                    ]
                ]);
            }

            return redirect()->back()->with('success', 'Sinch pricing updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update Sinch pricing', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update Sinch pricing: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update Sinch pricing: ' . $e->getMessage());
        }
    }

    /**
     * Copy all Nexmo prices to Sinch prices (bulk operation)
     */
    public function copyNexmoToSinch(Request $request)
    {
        try {
            // Get current exchange rate
            $exchangeRate = DB::table('country')
                ->whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            // Get all countries with Nexmo pricing
            $countries = DB::table('country')
                ->whereNotNull('cost_price_eur')
                ->where('cost_price_eur', '>', 0)
                ->get();

            $updatedCount = 0;

            foreach ($countries as $country) {
                $costEUR = floatval($country->cost_price_eur);
                $costGBP = round($costEUR * $exchangeRate, 4);

                DB::table('country')
                    ->where('id', $country->id)
                    ->update([
                        'sinch_cost_price_eur' => $costEUR,
                        'sinch_cost_price_gbp' => $costGBP,
                        'sinch_price_updated_at' => now(),
                        'updated_at' => now(),
                    ]);

                $updatedCount++;
            }

            Log::info('Copied Nexmo prices to Sinch for all countries', [
                'countries_updated' => $updatedCount,
                'exchange_rate' => $exchangeRate,
                'copied_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully copied Nexmo prices to Sinch for {$updatedCount} countries",
                'count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to copy Nexmo prices to Sinch', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to copy prices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug endpoint to test Nexmo pricing API
     * Returns raw API response and extracted highest network price
     */
    public function debugNexmoPricing(Request $request)
    {
        $apiKey = $request->query('api_key');
        $apiSecret = $request->query('api_secret');
        $countryCode = $request->query('country');

        // Validate inputs
        if (!$apiKey || !$apiSecret || !$countryCode) {
            return response()->json([
                'error' => 'Missing required parameters',
                'usage' => '/api/debug/nexmo-pricing?api_key=YOUR_KEY&api_secret=YOUR_SECRET&country=GB',
                'parameters' => [
                    'api_key' => 'Your Nexmo API key',
                    'api_secret' => 'Your Nexmo API secret',
                    'country' => 'ISO 2-letter country code (e.g., GB, US, DE)',
                ]
            ], 400);
        }

        try {
            // Build Nexmo API URL
            $url = 'https://rest.nexmo.com/account/get-pricing/outbound/sms?' . http_build_query([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'country' => strtoupper($countryCode)
            ]);

            // Make API request
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: SMS-Expert/1.0'
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return response()->json([
                    'error' => 'CURL Error',
                    'message' => $error,
                    'http_code' => $httpCode,
                ], 500);
            }

            if ($httpCode !== 200) {
                return response()->json([
                    'error' => 'API Error',
                    'http_code' => $httpCode,
                    'raw_response' => $response,
                ], $httpCode);
            }

            // Parse response
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Invalid JSON response',
                    'raw_response' => $response,
                ], 500);
            }

            // Extract pricing analysis
            $highestPrice = null;
            $highestNetworkName = null;
            $lowestPrice = null;
            $lowestNetworkName = null;
            $allNetworkPrices = [];

            if (isset($data['networks']) && is_array($data['networks'])) {
                foreach ($data['networks'] as $network) {
                    $networkPrice = $network['smsPrice'] ?? $network['price'] ?? $network['mtPrice'] ?? null;
                    $networkName = $network['networkName'] ?? 'Unknown';
                    $networkCode = $network['networkCode'] ?? 'N/A';

                    if ($networkPrice !== null && is_numeric($networkPrice)) {
                        $networkPrice = floatval($networkPrice);

                        $allNetworkPrices[] = [
                            'name' => $networkName,
                            'code' => $networkCode,
                            'price' => $networkPrice,
                        ];

                        if ($highestPrice === null || $networkPrice > $highestPrice) {
                            $highestPrice = $networkPrice;
                            $highestNetworkName = $networkName;
                        }

                        if ($lowestPrice === null || $networkPrice < $lowestPrice) {
                            $lowestPrice = $networkPrice;
                            $lowestNetworkName = $networkName;
                        }
                    }
                }

                // Sort by price descending
                usort($allNetworkPrices, function($a, $b) {
                    return $b['price'] <=> $a['price'];
                });
            }

            // Return comprehensive debug output
            return response()->json([
                'country' => strtoupper($countryCode),
                'country_name' => $data['countryName'] ?? $data['name'] ?? 'Unknown',
                'currency' => $data['currency'] ?? 'EUR',
                'default_price' => $data['defaultPrice'] ?? null,

                'analysis' => [
                    'highest_price' => [
                        'price' => $highestPrice,
                        'network' => $highestNetworkName,
                        'formatted' => $highestPrice !== null ? 'EUR ' . number_format($highestPrice, 5) : null,
                    ],
                    'lowest_price' => [
                        'price' => $lowestPrice,
                        'network' => $lowestNetworkName,
                        'formatted' => $lowestPrice !== null ? 'EUR ' . number_format($lowestPrice, 5) : null,
                    ],
                    'total_networks' => count($allNetworkPrices),
                    'price_range' => ($highestPrice !== null && $lowestPrice !== null)
                        ? 'EUR ' . number_format($lowestPrice, 5) . ' - EUR ' . number_format($highestPrice, 5)
                        : null,
                ],

                'top_10_expensive_networks' => array_slice($allNetworkPrices, 0, 10),

                'all_networks_sorted_by_price' => $allNetworkPrices,

                'raw_nexmo_response' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Copy single country's Nexmo price to Sinch price
     */
    public function copySingleNexmoToSinch(Request $request)
    {
        $request->validate([
            'country_id' => 'required|integer',
        ]);

        try {
            $countryId = $request->input('country_id');
            $country = DB::table('country')->where('id', $countryId)->first();

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            if (!$country->cost_price_eur || $country->cost_price_eur <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Nexmo price available for this country'
                ], 400);
            }

            // Get current exchange rate
            $exchangeRate = DB::table('country')
                ->whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            $costEUR = floatval($country->cost_price_eur);
            $costGBP = round($costEUR * $exchangeRate, 4);

            DB::table('country')
                ->where('id', $countryId)
                ->update([
                    'sinch_cost_price_eur' => $costEUR,
                    'sinch_cost_price_gbp' => $costGBP,
                    'sinch_price_updated_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('Copied Nexmo price to Sinch for single country', [
                'country_id' => $countryId,
                'country_name' => $country->name,
                'cost_eur' => $costEUR,
                'cost_gbp' => $costGBP,
                'copied_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Copied Nexmo price to Sinch for {$country->name}",
                'data' => [
                    'sinch_cost_price_eur' => $costEUR,
                    'sinch_cost_price_gbp' => $costGBP,
                    'exchange_rate' => $exchangeRate,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to copy Nexmo price to Sinch for country', [
                'country_id' => $request->input('country_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to copy price: ' . $e->getMessage()
            ], 500);
        }
    }
}
