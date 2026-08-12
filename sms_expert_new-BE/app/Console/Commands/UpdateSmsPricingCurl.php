<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateSmsPricingCurl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:update-pricing-curl 
                            {--test : Run in test mode with limited countries}
                            {--country= : Test with specific country code}
                            {--proxy= : Use proxy server (format: http://proxy:port)}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update SMS pricing from Nexmo API using native cURL with advanced options';

    /**
     * Proxy configuration
     */
    private $proxyUrl = null;
    private $verboseMode = false;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->verboseMode = $this->option('verbose');
        $this->proxyUrl = $this->option('proxy');
        
        if ($this->option('test')) {
            return $this->runTestMode();
        }
        
        if ($countryCode = $this->option('country')) {
            return $this->testSingleCountry($countryCode);
        }
        
        return $this->runFullUpdate();
    }

    /**
     * Run full update for all countries
     */
    private function runFullUpdate()
    {
        $this->info('Starting SMS pricing update using cURL...');
        if ($this->proxyUrl) {
            $this->info("Using proxy: {$this->proxyUrl}");
        }
        
        try {
            $apiKey    = config('services.nexmo.key');
            $apiSecret = config('services.nexmo.secret');

            if (!$apiKey || !$apiSecret) {
                $this->error('Nexmo API credentials not configured in config/services.php');
                $this->error('Please ensure NEXMO_KEY and NEXMO_SECRET are set in your .env file');
                return 1;
            }

            $countries = DB::table('country')
                ->select('dialcode', 'id', 'name')
                ->whereNotNull('dialcode')
                ->where('dialcode', '!=', '')
                ->get();

            if ($countries->isEmpty()) {
                $this->warn('No countries found in the database.');
                return 0;
            }

            $this->info("Found {$countries->count()} countries to update");
            
            $updated = 0;
            $failed = 0;
            $skipped = 0;
            
            $bar = $this->output->createProgressBar($countries->count());
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $bar->setMessage('Starting...');
            $bar->start();

            foreach ($countries as $country) {
                $bar->setMessage("Processing {$country->name} ({$country->dialcode})");
                
                try {
                    $result = $this->fetchPricingWithCurl($apiKey, $apiSecret, $country->dialcode);
                    
                    if ($result['success']) {
                        $cost = $result['price'];
                        
                        if ($cost !== null && $cost > 0) {
                            DB::table('country')
                                ->where('id', $country->id)
                                ->update([
                                    'cost_price' => $cost,
                                    'updated_at' => now()
                                ]);
                            
                            if ($this->verboseMode) {
                                $this->line("\n✓ Updated {$country->name} ({$country->dialcode}) - Cost: {$cost}");
                            }
                            $updated++;
                        } else {
                            if ($this->verboseMode) {
                                $this->line("\n⚠ No valid pricing for {$country->name} ({$country->dialcode})");
                            }
                            $skipped++;
                        }
                    } else {
                        if ($this->verboseMode) {
                            $this->line("\n✗ Failed {$country->name} ({$country->dialcode}): {$result['error']}");
                        }
                        Log::error("SMS Pricing Update Failed", [
                            'country' => $country->name,
                            'dialcode' => $country->dialcode,
                            'error' => $result['error']
                        ]);
                        $failed++;
                    }
                    
                    $bar->advance();
                    
                    // Rate limiting
                    usleep(100000); // 100ms delay
                    
                } catch (\Exception $e) {
                    if ($this->verboseMode) {
                        $this->line("\n✗ Exception for {$country->name}: " . $e->getMessage());
                    }
                    Log::error("SMS Pricing Update Exception", [
                        'country' => $country->name,
                        'error' => $e->getMessage()
                    ]);
                    $failed++;
                    $bar->advance();
                }
            }

            $bar->finish();
            
            $this->info("\n\n========================================");
            $this->info("SMS Pricing Update Completed!");
            $this->info("========================================");
            $this->info("✓ Updated: {$updated} countries");
            $this->warn("⚠ Skipped: {$skipped} countries (no pricing data)");
            $this->error("✗ Failed: {$failed} countries");
            $this->info("========================================\n");
            
            // Log summary
            Log::info('SMS Pricing Update Completed', [
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
                'total' => $countries->count()
            ]);

            return $failed > 0 ? 1 : 0;
            
        } catch (\Exception $e) {
            $this->error('Fatal error: ' . $e->getMessage());
            Log::error('SMS Pricing Update Fatal Error', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Fetch pricing using cURL with advanced options
     */
    private function fetchPricingWithCurl($apiKey, $apiSecret, $dialCode)
    {
        try {
            // Build URL
            $baseUrl = 'https://rest.nexmo.com/account/get-pricing/outbound/sms';
            $params = http_build_query([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'country' => $dialCode
            ]);
            $url = $baseUrl . '?' . $params;
            
            // Initialize cURL
            $ch = curl_init();
            
            // Basic options
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: SMS-Expert/1.0 (Laravel)',
                    'Cache-Control: no-cache'
                ],
                CURLOPT_ENCODING => '', // Enable all supported encoding types
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Force IPv4
            ];
            
            // Add proxy if configured
            if ($this->proxyUrl) {
                $options[CURLOPT_PROXY] = $this->proxyUrl;
                $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
            }
            
            // Enable verbose mode if requested
            if ($this->verboseMode) {
                $options[CURLOPT_VERBOSE] = true;
                $verbose = fopen('php://temp', 'w+');
                $options[CURLOPT_STDERR] = $verbose;
            }
            
            curl_setopt_array($ch, $options);
            
            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            
            // Get additional info for debugging
            $info = [
                'total_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
                'connect_time' => curl_getinfo($ch, CURLINFO_CONNECT_TIME),
                'size_download' => curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
                'speed_download' => curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD),
            ];
            
            curl_close($ch);
            
            // Show verbose output if enabled
            if ($this->verboseMode && isset($verbose)) {
                rewind($verbose);
                $verboseLog = stream_get_contents($verbose);
                if ($verboseLog) {
                    $this->info("cURL Debug Info:\n" . $verboseLog);
                }
            }
            
            // Check for cURL errors
            if ($errno) {
                return [
                    'success' => false,
                    'error' => "cURL Error ({$errno}): {$error}",
                    'price' => null,
                    'info' => $info
                ];
            }
            
            // Check HTTP status
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => "HTTP {$httpCode}: {$response}",
                    'price' => null,
                    'info' => $info
                ];
            }
            
            // Parse JSON
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'JSON Error: ' . json_last_error_msg(),
                    'price' => null,
                    'info' => $info
                ];
            }
            
            // Extract price (handle different response formats)
            $price = $data['defaultPrice'] ?? 
                    $data['default_price'] ?? 
                    $data['mt'] ?? 
                    null;
            
            return [
                'success' => true,
                'error' => null,
                'price' => $price,
                'currency' => $data['currency'] ?? null,
                'data' => $data,
                'info' => $info
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
     * Test mode with limited countries
     */
    private function runTestMode()
    {
        $this->info('Running in TEST MODE - Testing with 5 countries only');
        
        $testCountries = ['US', 'GB', 'IN', 'CA', 'AU'];
        $apiKey = config('services.nexmo.key');
        $apiSecret = config('services.nexmo.secret');
        
        if (!$apiKey || !$apiSecret) {
            $this->error('API credentials not configured!');
            return 1;
        }
        
        $this->table(
            ['Country', 'Status', 'Price', 'Currency', 'Time (s)'],
            array_map(function($code) use ($apiKey, $apiSecret) {
                $result = $this->fetchPricingWithCurl($apiKey, $apiSecret, $code);
                return [
                    $code,
                    $result['success'] ? '✓ Success' : '✗ Failed',
                    $result['price'] ?? 'N/A',
                    $result['currency'] ?? 'N/A',
                    round($result['info']['total_time'] ?? 0, 3)
                ];
            }, $testCountries)
        );
        
        return 0;
    }

    /**
     * Test single country
     */
    private function testSingleCountry($countryCode)
    {
        $this->info("Testing single country: {$countryCode}");
        
        $apiKey = config('services.nexmo.key');
        $apiSecret = config('services.nexmo.secret');
        
        if (!$apiKey || !$apiSecret) {
            $this->error('API credentials not configured!');
            return 1;
        }
        
        $result = $this->fetchPricingWithCurl($apiKey, $apiSecret, $countryCode);
        
        if ($result['success']) {
            $this->info("✓ Success!");
            $this->info("Price: " . ($result['price'] ?? 'N/A'));
            $this->info("Currency: " . ($result['currency'] ?? 'N/A'));
            
            if ($this->verboseMode) {
                $this->info("Full Response:");
                $this->line(json_encode($result['data'], JSON_PRETTY_PRINT));
                $this->info("\nRequest Info:");
                $this->table(
                    ['Metric', 'Value'],
                    collect($result['info'])->map(function($value, $key) {
                        return [str_replace('_', ' ', ucfirst($key)), round($value, 3)];
                    })->toArray()
                );
            }
        } else {
            $this->error("✗ Failed: " . $result['error']);
        }
        
        return $result['success'] ? 0 : 1;
    }
}
