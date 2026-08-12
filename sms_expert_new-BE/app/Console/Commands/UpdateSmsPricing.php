<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsCronExecution;

class UpdateSmsPricing extends Command
{
    use LogsCronExecution;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:update-pricing {--force : Force update even if manual mode is enabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update SMS pricing from Nexmo API for each country using the HIGHEST network price';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        return $this->executeWithLogging('sms:update-pricing', function () {
            $startTime = now();
            // Create dated log file path
            $logDate = $startTime->format('Y-m-d');
            $logDir = storage_path("logs/{$logDate}");
            $commandName = 'sms:update-pricing';

            // Create directory if it doesn't exist
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }

            // Sanitize command name for filename
            $logFileName = str_replace([':', ' '], ['_', '_'], $commandName) . '.log';
            $logFilePath = $logDir . '/' . $logFileName;
            $this->writeToLogFile($logFilePath,'Starting SMS pricing update using cURL...');

            try {
                // Check --force flag
                $forceUpdate = $this->option('force');

                if ($forceUpdate) {
                    $this->writeToLogFile($logFilePath, 'Force update flag detected. Will update ALL countries regardless of mode setting.');
                }

                // Count countries by mode
                $apiModeCount = DB::table('country')
                    ->where(function ($q) {
                        $q->where('price_update_mode', 'api')
                          ->orWhereNull('price_update_mode');
                    })
                    ->count();

                $manualModeCount = DB::table('country')
                    ->where('price_update_mode', 'manual')
                    ->count();

                $this->writeToLogFile($logFilePath, "Countries in API mode: {$apiModeCount}, Manual mode: {$manualModeCount}");

                if ($apiModeCount === 0 && !$forceUpdate) {
                    $this->writeToLogFile($logFilePath, 'ALL countries are in MANUAL mode. Skipping API update.');
                    $this->writeToLogFile($logFilePath, 'Use --force flag to override: php artisan sms:update-pricing --force');
                    $this->info('All countries are in manual mode. No API updates needed.');
                    return "Skipped: All countries in manual mode";
                }

                $this->writeToLogFile($logFilePath, 'Fetching prices from Nexmo API...');

                $apiKey    = config('services.nexmo.key');
                $apiSecret = config('services.nexmo.secret');

                if (!$apiKey || !$apiSecret) {
                     $this->writeToLogFile($logFilePath,'Nexmo API credentials not configured.');
                    $this->writeToLogFile($logFilePath, 'SMS Pricing Update: Nexmo API credentials missing');
                    return 1;
                }

                // Fetch EUR to GBP exchange rate at the start
                $exchangeRateData = $this->fetchExchangeRate();
                
                if (!$exchangeRateData['success']) {
                    $this->writeToLogFile($logFilePath, 'Failed to fetch exchange rate: ' . $exchangeRateData['error']);
                    $this->writeToLogFile($logFilePath, 'SMS Pricing Update: Exchange rate fetch failed');
                    return 1;
                }

                $exchangeRate = $exchangeRateData['rate'];
                $this->writeToLogFile($logFilePath, "EUR to GBP Exchange Rate: {$exchangeRate}");

                $countriesQuery = DB::table('country')
                    ->select('dialcode', 'id', 'name', 'iso_code', 'cost_per_sms', 'price_update_mode')
                    ->whereNotNull('dialcode')
                    ->where('dialcode', '!=', '');

                // If not forcing, only get API mode countries
                if (!$forceUpdate) {
                    $countriesQuery->where(function ($q) {
                        $q->where('price_update_mode', 'api')
                          ->orWhereNull('price_update_mode');
                    });
                }

                $countries = $countriesQuery->get();

                if ($countries->isEmpty()) {
                    $this->writeToLogFile($logFilePath, 'No countries to update (all may be in manual mode).');
                    return 0;
                }

                $this->writeToLogFile($logFilePath, "Processing {$countries->count()} countries...");

                $updated = 0;
                $skipped = 0;
                $failed = 0;
                $bar = $this->output->createProgressBar($countries->count());
                $bar->start();

                foreach ($countries as $country) {
                    try {
                        // Check if country is in manual mode (only relevant when --force is used)
                        $countryMode = $country->price_update_mode ?? 'api';
                        if ($countryMode === 'manual' && !$forceUpdate) {
                            $this->writeToLogFile($logFilePath, "\n⏭ Skipping {$country->name} ({$country->dialcode}) - Manual mode");
                            $skipped++;
                            $bar->advance();
                            continue;
                        }

                        $this->writeToLogFile($logFilePath, "\nUpdating pricing for: {$country->name} ({$country->dialcode})" . ($countryMode === 'manual' ? ' [FORCE]' : ''));

                        // Make API call using cURL
                        $result = $this->fetchPricingWithCurl($apiKey, $apiSecret, $country->iso_code);

                        if ($result['success']) {
                            $costEUR = $result['price'];
                            $networkCount = $result['network_count'] ?? 0;
                            $defaultPrice = $result['default_price'] ?? null;
                            $lowestPrice = $result['lowest_price'] ?? null;

                            if ($costEUR !== null) {
                                // Convert EUR to GBP (4 decimal places)
                                $costGBP = round($costEUR * $exchangeRate, 4);

                                // Log network pricing details
                                if ($networkCount > 1) {
                                    $this->writeToLogFile($logFilePath, "  → {$networkCount} networks found. Default: €{$defaultPrice}, Lowest: €{$lowestPrice}, Highest (used): €{$costEUR}");
                                }

                                if ($costEUR == $country->cost_per_sms) {
                                    $this->writeToLogFile($logFilePath, "⚠ Cost unchanged for {$country->name} ({$country->dialcode}) - Current: {$country->cost_per_sms} EUR, New: {$costEUR} EUR, GBP: {$costGBP}");
                                    $this->writeToLogFile($logFilePath, "SMS Pricing Update: Cost unchanged " . json_encode([
                                        'country' => $country->name,
                                        'dialcode' => $country->dialcode,
                                        'current_cost' => $country->cost_per_sms,
                                        'new_cost_eur' => $costEUR,
                                        'new_cost_gbp' => $costGBP,
                                        'exchange_rate' => $exchangeRate,
                                        'network_count' => $networkCount,
                                        'default_price' => $defaultPrice,
                                        'lowest_price' => $lowestPrice
                                    ]));

                                    // Update exchange rate and GBP even if EUR price is unchanged
                                    DB::table('country')
                                        ->where('id', $country->id)
                                        ->update([
                                            'cost_price_eur' => $costEUR,
                                            'cost_price_gbp' => $costGBP,
                                            'exchange_rate_eur_to_gbp' => $exchangeRate,
                                            'updated_at' => now()
                                        ]);

                                    $bar->advance();
                                    continue;
                                }

                                // Update database with EUR, GBP, and exchange rate
                                DB::table('country')
                                    ->where('id', $country->id)
                                    ->update([
                                        'cost_price' => $costEUR, // Keep for backward compatibility
                                        'cost_per_sms' => $costEUR, // Keep for backward compatibility
                                        'cost_price_eur' => $costEUR,
                                        'cost_price_gbp' => $costGBP,
                                        'exchange_rate_eur_to_gbp' => $exchangeRate,
                                        'updated_at' => now()
                                    ]);

                                $networkInfo = $networkCount > 1 ? " (Highest of {$networkCount} networks, Default was: €{$defaultPrice})" : "";
                                $this->writeToLogFile($logFilePath, "✓ Updated {$country->name} ({$country->dialcode}) - EUR: {$costEUR}, GBP: {$costGBP}, Rate: {$exchangeRate}{$networkInfo}");
                                $updated++;
                            } else {
                                $this->writeToLogFile($logFilePath, "⚠ No pricing data for {$country->name} ({$country->dialcode})");
                                $this->writeToLogFile($logFilePath, "SMS Pricing Update: No pricing data for country" . json_encode([
                                    'country' => $country->name,
                                    'dialcode' => $country->dialcode
                                ]));
                            }
                        } else {
                            $this->writeToLogFile($logFilePath, "✗ Failed to update {$country->name} ({$country->dialcode}) - Error: {$result['error']}");
                            $this->writeToLogFile($logFilePath, "SMS Pricing Update: API request failed" . json_encode([
                                'country' => $country->name,
                                'dialcode' => $country->dialcode,
                                'error' => $result['error']
                            ]));
                            $failed++;
                        }

                        $bar->advance();

                        // Add delay to avoid rate limiting (1 second between requests)
                        sleep(1);

                    } catch (\Exception $e) {
                        $this->writeToLogFile($logFilePath, "✗ Exception for {$country->name}: " . $e->getMessage());
                        $this->writeToLogFile($logFilePath, "SMS Pricing Update: Exception occurred" . json_encode([
                            'country' => $country->name,
                            'dialcode' => $country->dialcode,
                            'error' => $e->getMessage()
                        ]));
                        $failed++;
                        $bar->advance();
                    }
                }

                $bar->finish();

                $this->writeToLogFile($logFilePath, "\n\nSMS Pricing Update Completed!");
                $this->writeToLogFile($logFilePath, "EUR to GBP Exchange Rate: {$exchangeRate}");
                $this->writeToLogFile($logFilePath, "Updated: {$updated} countries");
                $this->writeToLogFile($logFilePath, "Failed: {$failed} countries");
                $this->writeToLogFile($logFilePath, "Skipped (Manual Mode): {$skipped} countries");

                $this->writeToLogFile($logFilePath, 'SMS Pricing Update completed' . json_encode([
                    'exchange_rate' => $exchangeRate,
                    'updated' => $updated,
                    'failed' => $failed,
                    'skipped' => $skipped,
                    'total' => $countries->count()
                ]));

                return "Success: Updated {$updated}, Failed: {$failed}, Skipped (Manual): {$skipped}, Rate: {$exchangeRate}";
            } catch (\Exception $e) {
                $this->writeToLogFile($logFilePath, 'Fatal error: ' . $e->getMessage());
                $this->writeToLogFile($logFilePath, 'SMS Pricing Update: Fatal error' . json_encode(['error' => $e->getMessage()]));
                throw $e;
            }
        });
    }

    /**
     * Fetch EUR to GBP exchange rate
     *
     * @return array
     */
    private function fetchExchangeRate()
    {
        try {
            // Using exchangerate-api.com (free tier allows 1,500 requests/month)
            $url = 'https://api.exchangerate-api.com/v4/latest/EUR';

            // Try with SSL verification first, then without if it fails
            $sslVerify = true;
            $attempts = 0;
            $maxAttempts = 2;
            $response = null;
            $httpCode = 0;
            $error = null;

            while ($attempts < $maxAttempts) {
                $attempts++;

                // Initialize cURL
                $ch = curl_init();

                // Set cURL options
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => $sslVerify,
                    CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'User-Agent: SMS-Expert/1.0'
                    ],
                ]);

                // Execute the request
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);

                // Close cURL handle
                curl_close($ch);

                // If SSL error, retry without SSL verification
                if ($error && strpos($error, 'SSL') !== false && $sslVerify) {
                    Log::warning('Exchange rate API SSL error, retrying without SSL verification: ' . $error);
                    $sslVerify = false;
                    continue;
                }

                // If no error, break the loop
                if (!$error && $httpCode === 200) {
                    break;
                }

                // If other error on first attempt, try without SSL
                if ($error && $sslVerify) {
                    $sslVerify = false;
                    continue;
                }

                break;
            }

            // Check for cURL errors
            if ($error) {
                // Try to get fallback rate from database
                $fallbackRate = $this->getFallbackExchangeRate();
                if ($fallbackRate) {
                    Log::warning('Using fallback exchange rate from database: ' . $fallbackRate);
                    return [
                        'success' => true,
                        'error' => null,
                        'rate' => $fallbackRate,
                        'fallback' => true
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $error,
                    'rate' => null
                ];
            }

            // Check HTTP status code
            if ($httpCode !== 200) {
                // Try to get fallback rate from database
                $fallbackRate = $this->getFallbackExchangeRate();
                if ($fallbackRate) {
                    Log::warning('Using fallback exchange rate from database: ' . $fallbackRate);
                    return [
                        'success' => true,
                        'error' => null,
                        'rate' => $fallbackRate,
                        'fallback' => true
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $httpCode,
                    'rate' => null
                ];
            }

            // Parse JSON response
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'JSON Parse Error: ' . json_last_error_msg(),
                    'rate' => null
                ];
            }

            // Extract GBP rate
            $rate = $data['rates']['GBP'] ?? null;

            if ($rate === null) {
                return [
                    'success' => false,
                    'error' => 'GBP rate not found in response',
                    'rate' => null
                ];
            }

            return [
                'success' => true,
                'error' => null,
                'rate' => $rate,
                'data' => $data
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'rate' => null
            ];
        }
    }

    /**
     * Get fallback exchange rate from database (last known rate)
     *
     * @return float|null
     */
    private function getFallbackExchangeRate()
    {
        try {
            $country = DB::table('country')
                ->whereNotNull('exchange_rate_eur_to_gbp')
                ->where('exchange_rate_eur_to_gbp', '>', 0)
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($country && $country->exchange_rate_eur_to_gbp > 0) {
                return floatval($country->exchange_rate_eur_to_gbp);
            }

            // Hardcoded fallback if no database rate available
            return 0.85;
        } catch (\Exception $e) {
            Log::error('Failed to get fallback exchange rate: ' . $e->getMessage());
            return 0.85; // Default fallback
        }
    }

    /**
     * Fetch pricing using cURL
     *
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $dialCode
     * @return array
     */
    private function fetchPricingWithCurl($apiKey, $apiSecret, $dialCode)
    {
        try {
            // Build the URL with query parameters
            $baseUrl = 'https://rest.nexmo.com/account/get-pricing/outbound/sms';
            $params = http_build_query([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'country' => $dialCode
            ]);
            $url = $baseUrl . '?' . $params;

            // Try with SSL verification first, then without if it fails (Windows compatibility)
            $sslVerify = true;
            $attempts = 0;
            $maxAttempts = 2;
            $response = null;
            $httpCode = 0;
            $error = null;

            while ($attempts < $maxAttempts) {
                $attempts++;

                // Initialize cURL
                $ch = curl_init();

                // Set cURL options
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => $sslVerify,
                    CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'User-Agent: SMS-Expert/1.0'
                    ],
                    CURLOPT_VERBOSE => false,
                    CURLOPT_FAILONERROR => false
                ]);

                // Execute the request
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);

                // Close cURL handle
                curl_close($ch);

                // If SSL error, retry without SSL verification
                if ($error && strpos($error, 'SSL') !== false && $sslVerify) {
                    Log::warning('Vonage API SSL error for ' . $dialCode . ', retrying without SSL verification: ' . $error);
                    $sslVerify = false;
                    continue;
                }

                // If no error, break the loop
                if (!$error && $httpCode === 200) {
                    break;
                }

                // If other error on first attempt, try without SSL
                if ($error && $sslVerify) {
                    $sslVerify = false;
                    continue;
                }

                break;
            }

            // Check for cURL errors
            if ($error) {
                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $error,
                    'price' => null
                ];
            }

            // Handle rate limiting (429) with retry
            if ($httpCode === 429) {
                // Wait and retry up to 3 times
                for ($retry = 1; $retry <= 3; $retry++) {
                    $waitTime = $retry * 5; // 5s, 10s, 15s
                    Log::warning("Rate limited for {$dialCode}, waiting {$waitTime}s before retry {$retry}/3");
                    sleep($waitTime);

                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS => 3,
                        CURLOPT_HTTPHEADER => [
                            'Accept: application/json',
                            'User-Agent: SMS-Expert/1.0'
                        ],
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200) {
                        break;
                    }
                }
            }

            // Check HTTP status code
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $httpCode . ' - Response: ' . $response,
                    'price' => null
                ];
            }

            // Parse JSON response
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'JSON Parse Error: ' . json_last_error_msg(),
                    'price' => null
                ];
            }

            // Extract the HIGHEST network price for the country
            // Vonage returns networks array with individual carrier prices
            $highestPrice = null;
            $lowestPrice = null;
            $networkCount = 0;
            $networkPrices = [];

            if (isset($data['networks']) && is_array($data['networks'])) {
                $networkCount = count($data['networks']);

                foreach ($data['networks'] as $network) {
                    // Get SMS price from network (could be 'smsPrice', 'price', or 'mtPrice')
                    $networkPrice = $network['smsPrice'] ?? $network['price'] ?? $network['mtPrice'] ?? null;

                    if ($networkPrice !== null && is_numeric($networkPrice)) {
                        $networkPrice = floatval($networkPrice);
                        $networkPrices[] = [
                            'name' => $network['networkName'] ?? $network['network'] ?? 'Unknown',
                            'code' => $network['networkCode'] ?? $network['mcc'] ?? '',
                            'price' => $networkPrice
                        ];

                        // Track highest price
                        if ($highestPrice === null || $networkPrice > $highestPrice) {
                            $highestPrice = $networkPrice;
                        }

                        // Track lowest price for logging
                        if ($lowestPrice === null || $networkPrice < $lowestPrice) {
                            $lowestPrice = $networkPrice;
                        }
                    }
                }
            }

            // If no network prices found, fall back to defaultPrice
            if ($highestPrice === null) {
                $highestPrice = isset($data['defaultPrice']) ? floatval($data['defaultPrice']) : null;
                if ($highestPrice === null && isset($data['default_price'])) {
                    $highestPrice = floatval($data['default_price']);
                }
            }

            // Get default price for comparison/logging
            $defaultPrice = $data['defaultPrice'] ?? $data['default_price'] ?? null;

            return [
                'success' => true,
                'error' => null,
                'price' => $highestPrice,
                'default_price' => $defaultPrice,
                'lowest_price' => $lowestPrice,
                'network_count' => $networkCount,
                'network_prices' => $networkPrices,
                'data' => $data
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'price' => null
            ];
        }
    }

    /**
     * Alternative method using cURL with POST request (if needed)
     *
     * @param string $apiKey
     * @param string $apiSecret
     * @param string $dialCode
     * @return array
     */
    private function fetchPricingWithCurlPost($apiKey, $apiSecret, $dialCode)
    {
        try {
            $url = 'https://rest.nexmo.com/account/get-pricing/outbound/sms';

            // Prepare POST data
            $postData = [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'country' => $dialCode
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set cURL options for POST
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'User-Agent: SMS-Expert/1.0'
                ],
                CURLOPT_VERBOSE => false,
                CURLOPT_FAILONERROR => false
            ]);

            // Execute the request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            // Get more info for debugging
            $info = curl_getinfo($ch);

            // Close cURL handle
            curl_close($ch);

            // Check for cURL errors
            if ($error) {
                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $error,
                    'price' => null,
                    'debug' => $info
                ];
            }

            // Check HTTP status code
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $httpCode,
                    'price' => null,
                    'response' => $response,
                    'debug' => $info
                ];
            }

            // Parse JSON response
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'JSON Parse Error: ' . json_last_error_msg(),
                    'price' => null
                ];
            }

            // Extract the price
            $price = $data['defaultPrice'] ?? $data['default_price'] ?? null;

            return [
                'success' => true,
                'error' => null,
                'price' => $price,
                'data' => $data
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'price' => null
            ];
        }
    }

    /**
     * Method to test with a single country (for debugging)
     *
     * @param string $dialCode
     * @return void
     */
    public function testSingleCountry($dialCode = 'US', $logFilePath)
    {
        $this->writeToLogFile($logFilePath,"Testing with country code: {$dialCode}");

        $apiKey    = config('services.nexmo.key');
        $apiSecret = config('services.nexmo.secret');

        if (!$apiKey || !$apiSecret) {
            $this->error('API credentials not configured.');
            return;
        }

        $this->info("Using GET method...");
        $resultGet = $this->fetchPricingWithCurl($apiKey, $apiSecret, $dialCode);

        if ($resultGet['success']) {
            $this->info("GET Success! Price: " . $resultGet['price']);
            $this->info("Full response: " . json_encode($resultGet['data'], JSON_PRETTY_PRINT));
        } else {
            $this->error("GET Failed: " . $resultGet['error']);
        }

        $this->info("\nUsing POST method...");
        $resultPost = $this->fetchPricingWithCurlPost($apiKey, $apiSecret, $dialCode);

        if ($resultPost['success']) {
            $this->info("POST Success! Price: " . $resultPost['price']);
            $this->info("Full response: " . json_encode($resultPost['data'], JSON_PRETTY_PRINT));
        } else {
            $this->error("POST Failed: " . $resultPost['error']);
            if (isset($resultPost['response'])) {
                $this->error("Response body: " . $resultPost['response']);
            }
            if (isset($resultPost['debug'])) {
                $this->info("Debug info: " . json_encode($resultPost['debug'], JSON_PRETTY_PRINT));
            }
        }
    }
}
