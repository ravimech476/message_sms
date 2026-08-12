<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use App\Models\Country;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VonageCountryCostExport;
use App\Exports\VonageCountryCostTemplateExport;
use App\Imports\VonageCountryCostImport;
use Carbon\Carbon;

class VonageCostController extends Controller
{
    /**
     * Display Vonage cost management page
     */
    public function index()
    {
        $countries = Country::select(
            'id', 'name', 'iso_code', 'dialcode',
            'cost_price_eur', 'cost_price_gbp', 'price_update_mode', 'updated_at'
        )
            ->orderBy('name')
            ->get();

        // Get exchange rate
        $exchangeRate = Country::whereNotNull('exchange_rate_eur_to_gbp')
            ->where('exchange_rate_eur_to_gbp', '>', 0)
            ->orderBy('exchange_rate_updated_at', 'desc')
            ->value('exchange_rate_eur_to_gbp') ?? 0.85;

        return view('admin.cost.vonage.index', compact('countries', 'exchangeRate'));
    }

    /**
     * Update country-level Vonage cost (EUR only, GBP auto-calculated)
     * Sets price_update_mode = 'manual' to prevent API override
     */
    public function updateCountryCost(Request $request, $countryId)
    {
        $request->validate([
            'cost_price_eur' => 'required|numeric|min:0|max:999',
        ]);

        try {
            $country = Country::findOrFail($countryId);

            // Get exchange rate
            $exchangeRate = Country::whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('exchange_rate_updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            $costEUR = floatval($request->cost_price_eur);
            // Auto-calculate GBP from EUR
            $costGBP = round($costEUR * $exchangeRate, 6);

            $country->update([
                'cost_price_eur' => $costEUR,
                'cost_price_gbp' => $costGBP,
                'cost_per_sms' => $costEUR,
                'cost_price' => $costEUR,
                'price_update_mode' => 'manual', // Mark as manually updated
            ]);

            // Country row changed → rebuild the no-TTL country cache so sends see the new price.
            app(\App\Services\TableCache::class)->rebuildCountries();

            Log::info('Vonage country cost manually updated', [
                'country_id' => $countryId,
                'country_name' => $country->name,
                'cost_eur' => $costEUR,
                'cost_gbp' => $costGBP,
                'exchange_rate' => $exchangeRate,
                'price_update_mode' => 'manual',
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Cost updated for {$country->name} (Manual - API won't override)",
                'data' => [
                    'cost_price_eur' => $costEUR,
                    'cost_price_gbp' => $costGBP,
                    'price_update_mode' => 'manual',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update Vonage country cost', [
                'country_id' => $countryId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update cost: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset country price_update_mode to allow API updates
     */
    public function resetPriceMode(Request $request, $countryId)
    {
        try {
            $country = Country::findOrFail($countryId);

            $country->update([
                'price_update_mode' => null,
            ]);

            Log::info('Vonage country price mode reset', [
                'country_id' => $countryId,
                'country_name' => $country->name,
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$country->name} will now be updated by API",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch pricing from Nexmo API for a single country
     */
    public function fetchNexmoPricing(Request $request, $countryCode)
    {
        $apiKey = config('services.nexmo.key') ?: env('NEXMO_API_KEY');
        $apiSecret = config('services.nexmo.secret') ?: env('NEXMO_API_SECRET');

        if (empty($apiKey) || empty($apiSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Nexmo API credentials not configured'
            ], 400);
        }

        try {
            $country = Country::where('iso_code', strtoupper($countryCode))->first();

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => "Country not found: {$countryCode}"
                ], 404);
            }

            // Fetch from Nexmo API
            $response = Http::timeout(30)->get('https://rest.nexmo.com/account/get-pricing/outbound/sms', [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'country' => strtoupper($countryCode),
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nexmo API error: ' . $response->status()
                ], 500);
            }

            $data = $response->json();
            $networks = $data['networks'] ?? [];
            $defaultPrice = $data['defaultPrice'] ?? null;

            // Find highest network price
            $highestPrice = 0;
            $highestNetwork = null;
            $networkCount = count($networks);

            foreach ($networks as $network) {
                $price = floatval($network['price'] ?? 0);
                if ($price > $highestPrice) {
                    $highestPrice = $price;
                    $highestNetwork = $network['networkName'] ?? 'Unknown';
                }
            }

            if ($highestPrice <= 0 && $defaultPrice) {
                $highestPrice = floatval($defaultPrice);
                $highestNetwork = 'Default';
            }

            if ($highestPrice <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid price found from Nexmo API'
                ], 400);
            }

            // Get exchange rate
            $exchangeRate = Country::whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('exchange_rate_updated_at', 'desc')
                ->value('exchange_rate_eur_to_gbp') ?? 0.85;

            $priceEur = round($highestPrice, 6);
            $priceGbp = round($priceEur * $exchangeRate, 6);

            // Update country
            $country->update([
                'cost_price_eur' => $priceEur,
                'cost_price_gbp' => $priceGbp,
                'cost_per_sms' => $priceEur,
                'cost_price' => $priceEur,
                'price_update_mode' => 'api',
            ]);

            Log::info('Vonage country cost updated from Nexmo API', [
                'country_id' => $country->id,
                'country_name' => $country->name,
                'cost_eur' => $priceEur,
                'cost_gbp' => $priceGbp,
                'highest_network' => $highestNetwork,
                'network_count' => $networkCount,
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Fetched from Nexmo: EUR {$priceEur} (highest of {$networkCount} networks: {$highestNetwork})",
                'data' => [
                    'cost_price_eur' => $priceEur,
                    'cost_price_gbp' => $priceGbp,
                    'highest_network' => $highestNetwork,
                    'network_count' => $networkCount,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch Nexmo pricing', [
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch pricing from Nexmo API for all countries
     */
    public function fetchAllNexmoPricing(Request $request)
    {
        try {
            // Run the artisan command in background
            Artisan::call('nexmo:fetch-pricing');
            $output = Artisan::output();

            Log::info('Nexmo pricing fetch triggered', [
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nexmo pricing fetch completed. Countries with manual prices were skipped.',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch all Nexmo pricing', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download country cost template
     */
    public function downloadCountryTemplate()
    {
        return Excel::download(new VonageCountryCostTemplateExport, 'vonage_country_cost_template.xlsx');
    }

    /**
     * Export current country costs
     */
    public function exportCountryCosts()
    {
        $filename = 'vonage_country_costs_' . now()->format('Y-m-d_His') . '.xlsx';
        return Excel::download(new VonageCountryCostExport, $filename);
    }

    /**
     * Import country costs from Excel
     * Sets price_update_mode = 'manual' for imported countries
     */
    public function importCountryCosts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $import = new VonageCountryCostImport();
            Excel::import($import, $request->file('file'));

            $updatedCount = $import->getUpdatedCount();
            $errors = $import->getErrors();

            Log::info('Vonage country costs imported', [
                'updated_count' => $updatedCount,
                'errors_count' => count($errors),
                'admin_id' => session('admin_user')['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} country costs (marked as manual)",
                'updated_count' => $updatedCount,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to import Vonage country costs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import: ' . $e->getMessage()
            ], 500);
        }
    }
}
