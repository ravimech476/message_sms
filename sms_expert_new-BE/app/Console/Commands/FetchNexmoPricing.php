<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use Carbon\Carbon;

class FetchNexmoPricing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexmo:fetch-pricing
                            {--country= : Specific country ISO code to fetch (e.g., GB, US)}
                            {--force : Force update even if manually set}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch SMS pricing from Nexmo API and update country costs (uses highest network price per country)';

    /**
     * Nexmo API credentials
     */
    protected $apiKey;
    protected $apiSecret;

    /**
     * Exchange rate EUR to GBP
     */
    protected $exchangeRate;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->apiKey = config('services.nexmo.key') ?: env('NEXMO_API_KEY');
        $this->apiSecret = config('services.nexmo.secret') ?: env('NEXMO_API_SECRET');

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            $this->error('Nexmo API credentials not configured. Please set NEXMO_API_KEY and NEXMO_API_SECRET in .env');
            return 1;
        }

        // Get exchange rate
        $this->exchangeRate = Country::whereNotNull('exchange_rate_eur_to_gbp')
            ->where('exchange_rate_eur_to_gbp', '>', 0)
            ->orderBy('exchange_rate_updated_at', 'desc')
            ->value('exchange_rate_eur_to_gbp') ?? 0.85;

        $this->info("Using exchange rate: 1 EUR = {$this->exchangeRate} GBP");

        $specificCountry = $this->option('country');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($specificCountry) {
            // Fetch single country
            $this->fetchCountryPricing(strtoupper($specificCountry), $force, $dryRun);
        } else {
            // Fetch all countries
            $this->fetchAllCountriesPricing($force, $dryRun);
        }

        $this->info('Nexmo pricing fetch completed!');
        return 0;
    }

    /**
     * Fetch pricing for a single country
     */
    protected function fetchCountryPricing($countryCode, $force = false, $dryRun = false)
    {
        $country = Country::where('iso_code', $countryCode)->first();

        if (!$country) {
            $this->error("Country not found: {$countryCode}");
            return;
        }

        // Check if manually updated
        if (!$force && $country->price_update_mode === 'manual') {
            $this->warn("Skipping {$country->name} ({$countryCode}) - manually updated. Use --force to override.");
            return;
        }

        $this->info("Fetching pricing for {$country->name} ({$countryCode})...");

        try {
            $response = Http::timeout(30)->get('https://rest.nexmo.com/account/get-pricing/outbound/sms', [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
                'country' => $countryCode,
            ]);

            if (!$response->successful()) {
                $this->error("API error for {$countryCode}: " . $response->status());
                Log::error('Nexmo pricing API error', [
                    'country' => $countryCode,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return;
            }

            $data = $response->json();
            $this->processPricingData($country, $data, $dryRun);

        } catch (\Exception $e) {
            $this->error("Exception for {$countryCode}: " . $e->getMessage());
            Log::error('Nexmo pricing fetch exception', [
                'country' => $countryCode,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fetch pricing for all countries
     */
    protected function fetchAllCountriesPricing($force = false, $dryRun = false)
    {
        $this->info('Fetching full pricing list from Nexmo API...');

        try {
            $response = Http::timeout(60)->get('https://rest.nexmo.com/account/get-full-pricing/outbound/sms', [
                'api_key' => $this->apiKey,
                'api_secret' => $this->apiSecret,
            ]);

            if (!$response->successful()) {
                $this->error("API error: " . $response->status());
                Log::error('Nexmo full pricing API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return;
            }

            $data = $response->json();

            if (!isset($data['countries']) || !is_array($data['countries'])) {
                $this->error('Invalid API response - no countries array');
                return;
            }

            $this->info("Received pricing for " . count($data['countries']) . " countries");

            $updatedCount = 0;
            $skippedCount = 0;
            $notFoundCount = 0;

            $progressBar = $this->output->createProgressBar(count($data['countries']));
            $progressBar->start();

            foreach ($data['countries'] as $countryData) {
                $countryCode = $countryData['countryCode'] ?? null;

                if (!$countryCode) {
                    $progressBar->advance();
                    continue;
                }

                $country = Country::where('iso_code', $countryCode)->first();

                if (!$country) {
                    $notFoundCount++;
                    $progressBar->advance();
                    continue;
                }

                // Check if manually updated
                if (!$force && $country->price_update_mode === 'manual') {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                if ($this->processPricingData($country, $countryData, $dryRun)) {
                    $updatedCount++;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Summary:");
            $this->info("  - Updated: {$updatedCount} countries");
            $this->info("  - Skipped (manual): {$skippedCount} countries");
            $this->info("  - Not in database: {$notFoundCount} countries");

            Log::info('Nexmo pricing fetch completed', [
                'updated' => $updatedCount,
                'skipped_manual' => $skippedCount,
                'not_found' => $notFoundCount
            ]);

        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
            Log::error('Nexmo full pricing fetch exception', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Process pricing data for a country
     * Uses the HIGHEST network price as the country price
     */
    protected function processPricingData($country, $data, $dryRun = false)
    {
        $networks = $data['networks'] ?? [];
        $defaultPrice = $data['defaultPrice'] ?? null;

        // Find the highest network price
        $highestPrice = 0;
        $highestNetwork = null;

        foreach ($networks as $network) {
            $price = floatval($network['price'] ?? 0);
            if ($price > $highestPrice) {
                $highestPrice = $price;
                $highestNetwork = $network['networkName'] ?? 'Unknown';
            }
        }

        // If no network prices, use default price
        if ($highestPrice <= 0 && $defaultPrice) {
            $highestPrice = floatval($defaultPrice);
            $highestNetwork = 'Default';
        }

        if ($highestPrice <= 0) {
            $this->line("  No valid price for {$country->name}");
            return false;
        }

        // Nexmo returns price in EUR
        $priceEur = round($highestPrice, 6);
        $priceGbp = round($priceEur * $this->exchangeRate, 6);

        $networkCount = count($networks);
        $this->line("  {$country->name}: EUR {$priceEur} (highest of {$networkCount} networks: {$highestNetwork}) = GBP {$priceGbp}");

        if (!$dryRun) {
            $country->update([
                'cost_price_eur' => $priceEur,
                'cost_price_gbp' => $priceGbp,
                'price_update_mode' => 'api',
                'updated_at' => Carbon::now(),
            ]);
        }

        return true;
    }
}
