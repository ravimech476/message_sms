<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile App Delivery Receipt Controller
 * 
 * Handles delivery receipt URL configuration and testing
 */
class DeliveryReceiptController extends Controller
{
    /**
     * Get delivery receipt settings for the authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get user options from useroption table
            $userOption = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->first();

            // Get delivery receipt URL
            $deliveryUrl = '';
            if ($userOption && isset($userOption->dreceipt_push_url)) {
                $deliveryUrl = $userOption->dreceipt_push_url ?? '';
            }

            // Connection settings (these could be configurable per user in the future)
            $connectionSettings = [
                'attempts' => 1,
                'pause_minutes' => 10,
            ];

            // Test form defaults
            $testDefaults = [
                'msisdn' => '447777111111',
                'submission_reference' => '12345678901234567890123456789012',
            ];

            return response()->json([
                'status' => true,
                'message' => 'Delivery receipt settings retrieved successfully',
                'data' => [
                    'delivery_url' => $deliveryUrl,
                    'connection_settings' => $connectionSettings,
                    'test_defaults' => $testDefaults,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Delivery Receipt Index Error: ' . $ex->getMessage());
            Log::error('Mobile Delivery Receipt Index Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load delivery receipt settings',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Update delivery receipt URL
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUrl(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'url' => 'required|url|max:500',
            ], [
                'url.required' => 'Please enter a URL',
                'url.url' => 'Please enter a valid URL (must start with http:// or https://)',
                'url.max' => 'URL must not exceed 500 characters',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $url = $request->input('url');

            // Update or insert into useroption table
            $exists = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->exists();

            if ($exists) {
                DB::table('useroption')
                    ->where('userref', $user->bigid)
                    ->update([
                        'dreceipt_push_url' => $url,
                    ]);
            } else {
                DB::table('useroption')->insert([
                    'userref' => $user->bigid,
                    'dreceipt_push_url' => $url,
                ]);
            }

            // useroption changed → rebuild this account's cache so sends/DLRs use the new URL (Phase 2).
            app(\App\Services\TableCache::class)->rebuildUseroption($user->bigid);

            // Log the update
            Log::info('Delivery receipt URL updated', [
                'user_id' => $user->id,
                'bigid' => $user->bigid,
                'url' => $url,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Delivery receipt URL updated successfully',
                'data' => [
                    'delivery_url' => $url,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Delivery Receipt Update Error: ' . $ex->getMessage());
            Log::error('Mobile Delivery Receipt Update Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update delivery receipt URL',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Test the delivery receipt mechanism
     * 
     * Sends a fake delivery receipt to the configured URL
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function test(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get user options
            $userOption = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->first();

            if (!$userOption || empty($userOption->dreceipt_push_url)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No delivery receipt URL configured. Please set a URL first.',
                ], 422);
            }

            $deliveryUrl = $userOption->dreceipt_push_url;

            // Get test parameters (use defaults if not provided)
            $msisdn = $request->input('msisdn', '447777111111');
            $submissionRef = $request->input('submission_reference', '12345678901234567890123456789012');

            // Build test delivery receipt payload
            $payload = [
                'msisdn' => $msisdn,
                'submission_reference' => $submissionRef,
                'status' => 'DELIVRD',
                'status_code' => '0',
                'status_text' => 'Message delivered successfully',
                'delivery_time' => now()->format('Y-m-d H:i:s'),
                'test_mode' => true,
                'timestamp' => now()->timestamp,
            ];

            // Try to send the test request
            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'SMSExpert-DeliveryReceipt/1.0',
                        'X-Test-Mode' => 'true',
                    ])
                    ->post($deliveryUrl, $payload);

                $responseStatus = $response->status();
                $responseBody = $response->body();

                // Check if successful
                if ($response->successful()) {
                    Log::info('Delivery receipt test successful', [
                        'user_id' => $user->id,
                        'url' => $deliveryUrl,
                        'status' => $responseStatus,
                    ]);

                    return response()->json([
                        'status' => true,
                        'message' => 'Test delivery receipt sent successfully',
                        'data' => [
                            'url' => $deliveryUrl,
                            'payload_sent' => $payload,
                            'response_status' => $responseStatus,
                            'response_body' => $responseBody,
                            'success' => true,
                        ],
                    ], 200);
                } else {
                    // Request completed but with error status
                    return response()->json([
                        'status' => true,
                        'message' => 'Test sent but server returned an error',
                        'data' => [
                            'url' => $deliveryUrl,
                            'payload_sent' => $payload,
                            'response_status' => $responseStatus,
                            'response_body' => $responseBody,
                            'success' => false,
                        ],
                    ], 200);
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $error = 'Connection failed: ' . $e->getMessage();
                Log::warning('Delivery receipt test connection failed', [
                    'user_id' => $user->id,
                    'url' => $deliveryUrl,
                    'error' => $error,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Test attempted but connection failed',
                    'data' => [
                        'url' => $deliveryUrl,
                        'payload_sent' => $payload,
                        'response_status' => null,
                        'response_body' => null,
                        'success' => false,
                        'error' => $error,
                    ],
                ], 200);

            } catch (\Exception $e) {
                $error = $e->getMessage();
                Log::warning('Delivery receipt test error', [
                    'user_id' => $user->id,
                    'url' => $deliveryUrl,
                    'error' => $error,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Test attempted but encountered an error',
                    'data' => [
                        'url' => $deliveryUrl,
                        'payload_sent' => $payload,
                        'response_status' => null,
                        'response_body' => null,
                        'success' => false,
                        'error' => $error,
                    ],
                ], 200);
            }

        } catch (\Throwable $ex) {
            Log::error('Mobile Delivery Receipt Test Error: ' . $ex->getMessage());
            Log::error('Mobile Delivery Receipt Test Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to send test delivery receipt',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
