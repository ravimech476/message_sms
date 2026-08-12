<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NexmoService
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://rest.nexmo.com';
    private $authHeader;
    private $verifySSL;

    public function __construct()
    {
        // Use separate Virtual Numbers API credentials from .env
        $this->apiKey = env('NEXMO_VN_API_KEY', env('NEXMO_API_KEY', 'df003a59'));
        $this->apiSecret = env('NEXMO_VN_API_SECRET', env('NEXMO_API_SECRET', 'cH7bWuTkAF5zXZ5Q'));

        // Basic auth header
        $this->authHeader = 'Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret);

        // Disable SSL verification on Windows/development (fixes cacert.pem issue)
        $this->verifySSL = !(PHP_OS_FAMILY === 'Windows' || env('APP_ENV') === 'development' || env('APP_ENV') === 'local');
    }

    /**
     * Get HTTP client with common options
     */
    private function httpClient()
    {
        return Http::withHeaders([
            'authorization' => $this->authHeader,
        ])->withOptions([
            'verify' => $this->verifySSL,
        ])->timeout(30);
    }

    /**
     * Get all virtual numbers from Nexmo with pagination
     */
    public function getNumbers($index = 1, $size = 100)
    {
        try {
            $response = $this->httpClient()
                ->get($this->baseUrl . '/account/numbers', [
                    'index' => $index,
                    'size' => $size
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nexmo API Error: ' . $response->status() . ' - ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Nexmo Service Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all virtual numbers (fetch all pages)
     */
    public function getAllNumbers()
    {
        $allNumbers = [];
        $page = 1;
        $size = 100;

        do {
            // Nexmo API expects page and size parameters
            $result = $this->getNumbers($page, $size);

            if (!$result || !isset($result['numbers'])) {
                break;
            }

            $numbers = $result['numbers'];
            $totalCount = $result['count'] ?? 0;

            $allNumbers = array_merge($allNumbers, $numbers);

            // Stop if fetched all numbers
            if (count($allNumbers) >= $totalCount) {
                break;
            }

            $page++;
        } while (!empty($numbers));

        return $allNumbers;
    }


    /**
     * Buy a new number
     */
    public function buyNumber($country, $msisdn, $targetApiKey = null)
    {
        try {
            $response = $this->httpClient()
                ->asForm()
                ->post($this->baseUrl . '/number/buy', [
                    'country' => $country,
                    'msisdn' => $msisdn,
                    'target_api_key' => $targetApiKey
                ]);

            if ($response->successful()) {
                Log::info('response Nexmo Buy Number : ', $response->json());
                return $response->json();
            }

            Log::error('Nexmo Buy Number Error: ' . $response->status() . ' - ' . $response->body());
            return [
                'error-code' => $response->status(),
                'error-code-label' => 'Failed to buy number'
            ];
        } catch (\Exception $e) {
            Log::error('Nexmo Buy Number Exception: ' . $e->getMessage());
            return [
                'error-code' => 500,
                'error-code-label' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a number
     */
    public function cancelNumber($country, $msisdn, $targetApiKey = null)
    {
        try {
            $response = $this->httpClient()
                ->asForm()
                ->post($this->baseUrl . '/number/cancel', [
                    'country' => $country,
                    'msisdn' => $msisdn,
                    'target_api_key' => $targetApiKey
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nexmo Cancel Number Error: ' . $response->status() . ' - ' . $response->body());
            return [
                'error-code' => $response->status(),
                'error-code-label' => 'Failed to cancel number'
            ];
        } catch (\Exception $e) {
            Log::error('Nexmo Cancel Number Exception: ' . $e->getMessage());
            return [
                'error-code' => 500,
                'error-code-label' => $e->getMessage()
            ];
        }
    }

    /**
     * Update number settings (webhook URLs)
     */
    public function updateNumber($country, $msisdn, $moHttpUrl = null, $moSmppSysType = null, $voiceCallbackType = null, $voiceCallbackValue = null, $voiceStatusCallback = null)
    {
        try {
            $params = [
                'country' => $country,
                'msisdn' => $msisdn,
            ];

            if ($moHttpUrl) {
                $params['moHttpUrl'] = $moHttpUrl;
            }

            if ($moSmppSysType) {
                $params['moSmppSysType'] = $moSmppSysType;
            }

            if ($voiceCallbackType) {
                $params['voiceCallbackType'] = $voiceCallbackType;
            }

            if ($voiceCallbackValue) {
                $params['voiceCallbackValue'] = $voiceCallbackValue;
            }

            if ($voiceStatusCallback) {
                $params['voiceStatusCallback'] = $voiceStatusCallback;
            }

            $response = $this->httpClient()
                ->asForm()
                ->post($this->baseUrl . '/number/update', $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nexmo Update Number Error: ' . $response->status() . ' - ' . $response->body());
            return [
                'error-code' => $response->status(),
                'error-code-label' => 'Failed to update number'
            ];
        } catch (\Exception $e) {
            Log::error('Nexmo Update Number Exception: ' . $e->getMessage());
            return [
                'error-code' => 500,
                'error-code-label' => $e->getMessage()
            ];
        }
    }

    /**
     * Search available numbers to buy
     */
    public function searchAvailableNumbers($country, $type = 'mobile-lvn', $features = 'SMS,VOICE', $size = 10)
    {
        try {
            $response = $this->httpClient()
                ->get($this->baseUrl . '/number/search', [
                    'country' => $country,
                    'type' => $type,
                    'features' => $features,
                    'size' => $size
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Nexmo Search Numbers Error: ' . $response->status() . ' - ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Nexmo Search Numbers Exception: ' . $e->getMessage());
            return null;
        }
    }
}
