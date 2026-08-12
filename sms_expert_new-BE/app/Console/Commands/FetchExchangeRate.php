<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchExchangeRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rate:fetch {--convert-prices : Also convert all Vonage EUR prices to GBP}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch EUR to GBP exchange rate from API and update country table';

    /**
     * Exchange rate API URL (using frankfurter.app - free, no API key required)
     */
    protected $apiUrl = 'https://api.frankfurter.app/latest?from=EUR&to=GBP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching EUR to GBP exchange rate...');

        try {
            // Fetch exchange rate from API
            $response = Http::timeout(30)->get($this->apiUrl);

            if (!$response->successful()) {
                $this->error('Failed to fetch exchange rate from API. Status: ' . $response->status());
                Log::error('Exchange rate API error', ['status' => $response->status(), 'body' => $response->body()]);
                return 1;
            }

            $data = $response->json();

            if (!isset($data['rates']['GBP'])) {
                $this->error('Invalid response from API - GBP rate not found');
                Log::error('Exchange rate API invalid response', ['data' => $data]);
                return 1;
            }

            $exchangeRate = $data['rates']['GBP'];
            $this->info("Exchange Rate: 1 EUR = {$exchangeRate} GBP");

            // Update all countries with the new exchange rate
            $now = Carbon::now();
            $updated = DB::table('country')->update([
                'exchange_rate_eur_to_gbp' => $exchangeRate,
                'exchange_rate_updated_at' => $now,
            ]);

            // Country rows changed → rebuild the no-TTL country cache so sends see fresh prices.
            app(\App\Services\TableCache::class)->rebuildCountries();

            $this->info("Updated exchange rate for {$updated} countries");
            Log::info('Exchange rate updated', ['rate' => $exchangeRate, 'countries_updated' => $updated]);

            // Convert Vonage EUR prices to GBP if option is set
            if ($this->option('convert-prices')) {
                $this->convertVonagePrices($exchangeRate);
            }

            $this->info('Exchange rate fetch completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            Log::error('Exchange rate fetch exception', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Convert all Vonage EUR prices to GBP using the exchange rate
     */
    protected function convertVonagePrices($exchangeRate)
    {
        $this->info('Converting Vonage EUR prices to GBP...');

        // Update GBP prices where EUR price exists
        $updated = DB::table('country')
            ->whereNotNull('cost_price_eur')
            ->where('cost_price_eur', '>', 0)
            ->update([
                'cost_price_gbp' => DB::raw("ROUND(cost_price_eur * {$exchangeRate}, 6)"),
                'updated_at' => Carbon::now(),
            ]);

        $this->info("Converted {$updated} Vonage country prices from EUR to GBP");
        Log::info('Vonage prices converted', ['countries_updated' => $updated, 'rate' => $exchangeRate]);
    }
}
