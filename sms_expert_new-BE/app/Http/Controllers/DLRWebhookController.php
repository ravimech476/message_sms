<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DeliveryStatusService;
use App\Services\Logging\ApiLogService;
use Carbon\Carbon;

/**
 * DLR Webhook Controller
 *
 * Handles delivery receipt webhooks from Vonage/Nexmo and other providers.
 * Uses DeliveryStatusService for OLD SYSTEM compatible processing.
 */
class DLRWebhookController extends Controller
{
    private DeliveryStatusService $deliveryStatusService;

    public function __construct(DeliveryStatusService $deliveryStatusService)
    {
        $this->deliveryStatusService = $deliveryStatusService;
    }

    /**
     * Handle DLR webhook from Vonage/Nexmo
     */
    public function handle(Request $request)
    {
        $apiLog = ApiLogService::for('dlr_webhook');
        $apiLog->info('DLR Webhook received', $request->all());

        try {
            // Vonage DLR parameters
            $messageId = $request->input('messageId') ?: $request->input('message-id');
            $to = $request->input('to') ?: $request->input('msisdn');
            $networkCode = $request->input('network-code') ?: $request->input('network_code');
            $price = $request->input('price');
            $status = $request->input('status');
            $scts = $request->input('scts'); // Submit time
            $errCode = $request->input('err-code') ?: $request->input('error_code') ?: '0';
            $messageTimestamp = $request->input('message-timestamp');
            $clientRef = $request->input('client-ref');

            // Map Vonage status to OLD SYSTEM format
            $mappedStatus = $this->deliveryStatusService->mapVonageStatus($status);

            // Prepare DLR data for processing
            $dlrData = [
                'message_id' => $messageId,
                'mobile_number' => $to,
                'status' => $mappedStatus['status'],
                'error_code' => $errCode ?: $mappedStatus['error_code'],
                'network_code' => $networkCode,
                'charge' => $price,
                'submit_date' => $scts ? Carbon::parse($scts)->format('YmdHis') : null,
                'done_date' => $messageTimestamp ? Carbon::parse($messageTimestamp)->format('YmdHis') : Carbon::now()->format('YmdHis'),
                'provider' => 'nexmo',
                'aggregator_code' => $errCode,
                'aggregator_msg' => $status,
                'retry' => '0',
                'raw_data' => $request->all(),
            ];

            // Process DLR using OLD SYSTEM logic
            $result = $this->deliveryStatusService->processDeliveryReceipt($dlrData);

            if ($result) {
                $apiLog->info('DLR processed successfully', [
                    'message_id' => $messageId,
                    'status' => $status
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'DLR processed successfully'
                ], 200);
            } else {
                $apiLog->warning('DLR processing returned false', [
                    'message_id' => $messageId,
                    'status' => $status
                ]);

                // Still return 200 to prevent retries from provider
                return response()->json([
                    'success' => false,
                    'message' => 'DLR record not found or already processed'
                ], 200);
            }

        } catch (\Exception $e) {
            $apiLog->error('DLR Webhook error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            Log::error("DLR Webhook error: " . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            // Still return 200 to prevent retries
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Handle DLR webhook from Sinch/mBlox
     */
    public function handleSinch(Request $request)
    {
        $apiLog = ApiLogService::for('dlr_webhook_sinch');
        $apiLog->info('Sinch DLR Webhook received', $request->all());

        try {
            // Sinch DLR parameters (XML or JSON format)
            $messageId = $request->input('BatchID') ?: $request->input('MsgReference') ?: $request->input('message_id');
            $mobileNumber = $request->input('SubscriberNumber') ?: $request->input('to');
            $status = $request->input('Status') ?: $request->input('status');
            $reasonCode = $request->input('Reason') ?: $request->input('reason') ?: '0';
            $timestamp = $request->input('TimeStamp') ?: $request->input('timestamp');
            $aggReasonCode = $request->input('AggregatorReasonCode') ?: '0';
            $aggReason = $request->input('AggregatorReason') ?: '';

            // Map Sinch status to OLD SYSTEM format
            $mappedStatus = $this->deliveryStatusService->mapSinchStatus($status, $reasonCode);

            // Prepare DLR data
            $dlrData = [
                'message_id' => $messageId,
                'mobile_number' => $mobileNumber,
                'status' => $mappedStatus['status'],
                'error_code' => $reasonCode,
                'done_date' => $timestamp ?: Carbon::now()->format('YmdHis'),
                'provider' => 'sinch',
                'aggregator_code' => $aggReasonCode,
                'aggregator_msg' => $aggReason ?: $status,
                'retry' => $request->input('Retry') ?: '0',
                'raw_data' => $request->all(),
            ];

            // Process DLR using OLD SYSTEM logic
            $result = $this->deliveryStatusService->processDeliveryReceipt($dlrData);

            $apiLog->info('Sinch DLR processed', [
                'message_id' => $messageId,
                'status' => $status,
                'result' => $result
            ]);

            return response()->json([
                'success' => $result,
                'message' => $result ? 'DLR processed successfully' : 'DLR processing failed'
            ], 200);

        } catch (\Exception $e) {
            $apiLog->error('Sinch DLR Webhook error', [
                'error' => $e->getMessage()
            ]);

            Log::error("Sinch DLR Webhook error: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Handle generic DLR webhook (auto-detect provider)
     */
    public function handleGeneric(Request $request)
    {
        // Detect provider from request
        if ($request->has('BatchID') || $request->has('WhichDeliverer')) {
            return $this->handleSinch($request);
        }

        // Default to Vonage/Nexmo
        return $this->handle($request);
    }

    /**
     * Test DLR endpoint
     */
    public function test(Request $request)
    {
        // Generate test DLR
        $testDlr = [
            'messageId' => 'test_' . uniqid(),
            'to' => '447777111111',
            'network-code' => '23410',
            'price' => '0.03330000',
            'status' => 'delivered',
            'scts' => Carbon::now()->subMinutes(2)->format('YmdHis'),
            'err-code' => '0',
            'message-timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        // Merge and process
        $request->merge($testDlr);

        return $this->handle($request);
    }
}
