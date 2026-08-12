<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SinchService
{
    protected $client;
    protected $servicePlanId;
    protected $apiToken;
    protected $baseUrl;
    protected $sender;

    // Sinch Numbers API credentials (separate from SMS API)
    protected $numbersProjectId;
    protected $numbersKeyId;
    protected $numbersKeySecret;
    protected $numbersBaseUrl;

    public function __construct()
    {
        $this->servicePlanId = config('sinch.service_plan_id');
        $this->apiToken = config('sinch.api_token');
        $this->baseUrl = rtrim(config('sinch.base_url'), '/');
        $this->sender = config('sinch.sender');

        // Numbers API config - uses different credentials
        $this->numbersProjectId = config('sinch.numbers_project_id', env('SINCH_NUMBERS_PROJECT_ID'));
        $this->numbersKeyId = config('sinch.numbers_key_id', env('SINCH_NUMBERS_KEY_ID'));
        $this->numbersKeySecret = config('sinch.numbers_key_secret', env('SINCH_NUMBERS_KEY_SECRET'));
        $this->numbersBaseUrl = config('sinch.numbers_base_url', 'https://numbers.api.sinch.com');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("{$this->servicePlanId}:{$this->apiToken}"),
            ],
        ]);
    }

    public function sendSMS($to, $message)
    {
        $payload = [
            'from' => $this->sender,
            'to' => [$to],
            'body' => $message,
        ];

        $response = $this->client->post("{$this->servicePlanId}/batches", [
            'json' => $payload,
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Search available numbers from Sinch
     * Sinch Numbers API: GET /v1/projects/{projectId}/availableNumbers
     */
    public function searchAvailableNumbers($country, $type = 'MOBILE', $capabilities = ['SMS'], $size = 10)
    {
        try {
            // Check if credentials are configured
            if (empty($this->numbersProjectId) || empty($this->numbersKeyId) || empty($this->numbersKeySecret)) {
                Log::error('Sinch Numbers API credentials not configured', [
                    'project_id_set' => !empty($this->numbersProjectId),
                    'key_id_set' => !empty($this->numbersKeyId),
                    'key_secret_set' => !empty($this->numbersKeySecret),
                ]);
                return null;
            }

            // Map type to Sinch format
            $sinchType = $this->mapNumberType($type);

            // Map capabilities - Sinch expects comma-separated string or repeated params
            $sinchCapabilities = $this->mapCapabilities($capabilities);

            $url = "{$this->numbersBaseUrl}/v1/projects/{$this->numbersProjectId}/availableNumbers";

            $queryParams = [
                'regionCode' => $country,
                'type' => $sinchType,
                'size' => $size
            ];

            // Add capabilities as repeated query parameters
            // Sinch API expects: ?capabilities=SMS&capabilities=VOICE
            $queryString = http_build_query($queryParams);
            foreach ($sinchCapabilities as $cap) {
                $queryString .= '&capability=' . urlencode($cap);
            }

            Log::info('Sinch Search Numbers Request', [
                'url' => $url . '?' . $queryString,
                'project_id' => $this->numbersProjectId,
                'country' => $country,
                'type' => $sinchType,
                'capabilities' => $sinchCapabilities,
            ]);

            $response = $this->getNumbersHttpClient()->get($url . '?' . $queryString);

            Log::info('Sinch Search Numbers Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Transform to match Nexmo format for consistency
                $numbers = [];
                if (isset($data['availableNumbers'])) {
                    foreach ($data['availableNumbers'] as $num) {
                        // Get monthly price (Sinch returns monthlyPrice object with currencyCode and amount)
                        $monthlyPrice = null;
                        $setupPrice = null;

                        if (isset($num['monthlyPrice']['amount'])) {
                            $monthlyPrice = $num['monthlyPrice']['amount'];
                        }
                        if (isset($num['setupPrice']['amount'])) {
                            $setupPrice = $num['setupPrice']['amount'];
                        }

                        $numbers[] = [
                            'country' => $num['regionCode'] ?? $country,
                            'msisdn' => $num['phoneNumber'] ?? '',
                            'type' => $this->reverseMapNumberType($num['type'] ?? 'MOBILE'),
                            'features' => $num['capability'] ?? ['SMS'],
                            'cost' => $monthlyPrice,
                            'setup_cost' => $setupPrice,
                            'currency' => $num['monthlyPrice']['currencyCode'] ?? 'GBP',
                            'supporting_documentation_required' => $num['supportingDocumentationRequired'] ?? false,
                        ];
                    }
                }

                return ['numbers' => $numbers];
            }

            Log::error('Sinch Search Numbers Error: ' . $response->status() . ' - ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Sinch Search Numbers Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get HTTP client with proper SSL configuration for Numbers API
     */
    private function getNumbersHttpClient()
    {
        $client = Http::withBasicAuth($this->numbersKeyId, $this->numbersKeySecret)
            ->timeout(30);

        // Disable SSL verification in local/development environment (Windows fix)
        if (app()->environment('local', 'development')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Buy/rent a number from Sinch
     * Sinch Numbers API: POST /v1/projects/{projectId}/availableNumbers/{phoneNumber}:rent
     */
    public function buyNumber($country, $msisdn)
    {
        try {
            // Clean the phone number format
            $phoneNumber = $this->formatPhoneNumber($msisdn);

            $url = "{$this->numbersBaseUrl}/v1/projects/{$this->numbersProjectId}/availableNumbers/{$phoneNumber}:rent";

            // Build the request body
            // Note: Sinch Numbers API uses snake_case for field names
            $body = [];

            // For SMPP users, service_plan_id is not required
            // But if we have one, add SMS configuration
            if (!empty($this->servicePlanId) && $this->servicePlanId !== 'your_service_plan_id_here' && $this->servicePlanId !== '') {
                $body['sms_configuration'] = [
                    'service_plan_id' => $this->servicePlanId
                ];
            }

            // Convert empty array to empty object for JSON
            $jsonBody = empty($body) ? '{}' : json_encode($body);

            Log::info('Sinch Buy Number Request', [
                'url' => $url,
                'phone' => $phoneNumber,
                'body' => $jsonBody,
            ]);

            // Send as JSON object (empty {} if no config)
            $response = $this->getNumbersHttpClient()
                ->withBody($jsonBody, 'application/json')
                ->post($url);

            if ($response->successful()) {
                Log::info('Sinch Buy Number Success: ', $response->json());
                return $response->json();
            }

            $errorMessage = 'Failed to buy number from Sinch';
            $responseData = $response->json();

            if (isset($responseData['error']['message']) && !empty($responseData['error']['message'])) {
                $errorMessage = $responseData['error']['message'];
            } elseif ($response->status() === 404) {
                // Check if it's a GB number (starts with +44)
                if (strpos($phoneNumber, '+44') === 0) {
                    $errorMessage = 'UK mobile numbers require compliance documentation to be uploaded in Sinch dashboard before purchase. If documentation is already uploaded, the number may no longer be available.';
                } else {
                    $errorMessage = 'Number is no longer available. Please search again and try a different number.';
                }
            } elseif ($response->status() === 403) {
                $errorMessage = 'Permission denied. Your Sinch account may not have the required permissions or compliance documentation for this number type.';
            }

            Log::error('Sinch Buy Number Error: ' . $response->status() . ' - ' . $response->body());
            return [
                'error-code' => $response->status(),
                'error-code-label' => $errorMessage
            ];
        } catch (\Exception $e) {
            Log::error('Sinch Buy Number Exception: ' . $e->getMessage());
            return [
                'error-code' => 500,
                'error-code-label' => $e->getMessage()
            ];
        }
    }

    /**
     * Release/cancel a number from Sinch
     * Sinch Numbers API: POST /v1/projects/{projectId}/activeNumbers/{phoneNumber}:release
     */
    public function cancelNumber($country, $msisdn)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($msisdn);

            $response = $this->getNumbersHttpClient()
                ->post("{$this->numbersBaseUrl}/v1/projects/{$this->numbersProjectId}/activeNumbers/{$phoneNumber}:release");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Sinch Cancel Number Error: ' . $response->status() . ' - ' . $response->body());
            return [
                'error-code' => $response->status(),
                'error-code-label' => 'Failed to cancel number from Sinch'
            ];
        } catch (\Exception $e) {
            Log::error('Sinch Cancel Number Exception: ' . $e->getMessage());
            return [
                'error-code' => 500,
                'error-code-label' => $e->getMessage()
            ];
        }
    }

    /**
     * Update number configuration (callback URL for inbound SMS)
     * Sinch Numbers API: PATCH /v1/projects/{projectId}/activeNumbers/{phoneNumber}
     */
    public function updateNumber($country, $msisdn, $callbackUrl = null)
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($msisdn);
            $url = "{$this->numbersBaseUrl}/v1/projects/{$this->numbersProjectId}/activeNumbers/{$phoneNumber}";

            // Build SMS configuration for webhook
            // Note: Sinch Numbers API uses snake_case for field names
            $smsConfig = [];

            // Add service plan ID if configured
            if (!empty($this->servicePlanId) && $this->servicePlanId !== 'your_service_plan_id_here' && $this->servicePlanId !== '') {
                $smsConfig['service_plan_id'] = $this->servicePlanId;
            }

            // Add callback URL for inbound SMS
            if ($callbackUrl) {
                $smsConfig['callback_url'] = $callbackUrl;
            }

            // Build request body
            $params = [];
            if (!empty($smsConfig)) {
                $params['sms_configuration'] = $smsConfig;
            }

            // Convert to proper JSON (empty {} if no params)
            $jsonBody = empty($params) ? '{}' : json_encode($params);

            Log::info('Sinch Update Number Request', [
                'url' => $url,
                'phone' => $phoneNumber,
                'callback_url' => $callbackUrl,
                'body' => $jsonBody,
            ]);

            $response = $this->getNumbersHttpClient()
                ->withBody($jsonBody, 'application/json')
                ->patch($url);

            Log::info('Sinch Update Number Response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Sinch Update Number Error: ' . $response->status() . ' - ' . $response->body());
            return [
                'error-code' => $response->status(),
                'error-code-label' => $response->json()['error']['message'] ?? 'Failed to update number in Sinch'
            ];
        } catch (\Exception $e) {
            Log::error('Sinch Update Number Exception: ' . $e->getMessage());
            return [
                'error-code' => 500,
                'error-code-label' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all active numbers from Sinch
     * Sinch Numbers API: GET /v1/projects/{projectId}/activeNumbers
     */
    public function getOwnedNumbers($size = 100, $pageToken = null)
    {
        try {
            $params = ['pageSize' => $size];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->getNumbersHttpClient()
                ->get("{$this->numbersBaseUrl}/v1/projects/{$this->numbersProjectId}/activeNumbers", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Sinch Get Owned Numbers Error: ' . $response->status() . ' - ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Sinch Get Owned Numbers Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Map number type from Nexmo format to Sinch format
     */
    private function mapNumberType($type)
    {
        $typeMap = [
            'mobile-lvn' => 'MOBILE',
            'landline' => 'LOCAL',
            'landline-toll-free' => 'TOLL_FREE',
            'mobile' => 'MOBILE',
            'local' => 'LOCAL',
            'toll-free' => 'TOLL_FREE'
        ];

        return $typeMap[strtolower($type)] ?? 'MOBILE';
    }

    /**
     * Reverse map number type from Sinch format to Nexmo format
     */
    private function reverseMapNumberType($type)
    {
        $typeMap = [
            'MOBILE' => 'mobile-lvn',
            'LOCAL' => 'landline',
            'TOLL_FREE' => 'landline-toll-free'
        ];

        return $typeMap[strtoupper($type)] ?? 'mobile-lvn';
    }

    /**
     * Map capabilities from Nexmo format to Sinch format
     */
    private function mapCapabilities($capabilities)
    {
        if (is_string($capabilities)) {
            $capabilities = explode(',', $capabilities);
        }

        $sinchCapabilities = [];

        foreach ($capabilities as $cap) {
            $cap = strtoupper(trim($cap));

            if (in_array($cap, ['SMS', 'VOICE', 'MMS'])) {
                $sinchCapabilities[] = $cap;
            }
        }

        // return implode(',', $sinchCapabilities);

        return $sinchCapabilities;
    }

    /**
     * Format phone number for Sinch API (ensure E.164 format with +)
     */
    private function formatPhoneNumber($msisdn)
    {
        // Remove any non-digit characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $msisdn);

        // Ensure it starts with +
        if (substr($cleaned, 0, 1) !== '+') {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }
}
