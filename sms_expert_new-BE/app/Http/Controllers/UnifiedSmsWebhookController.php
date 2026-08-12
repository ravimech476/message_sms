<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Services\DeliveryStatusService;
use App\Services\InboundSmsProcessor;
use App\Services\Queue\WebhookQueueService;
use Carbon\Carbon;

/**
 * Unified SMS Webhook Controller
 *
 * Handles BOTH DLR (Delivery Receipts) and Inbound SMS (MO) from:
 * - Nexmo/Vonage
 * - Sinch
 *
 * Both providers send DLR and MO to the same endpoint.
 * This controller detects the message type and queues to RabbitMQ for background processing.
 */
class UnifiedSmsWebhookController extends Controller
{
    protected DeliveryStatusService $deliveryStatusService;
    protected ?WebhookQueueService $webhookQueueService = null;

    public function __construct(DeliveryStatusService $deliveryStatusService)
    {
        $this->deliveryStatusService = $deliveryStatusService;

        // Initialize webhook queue service (may fail if RabbitMQ is down)
        try {
            $this->webhookQueueService = app(WebhookQueueService::class);
        } catch (\Exception $e) {
            Log::warning('[UnifiedWebhook] WebhookQueueService not available, will process directly', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Main webhook handler - auto-detects provider and message type
     */
    public function handle(Request $request): JsonResponse
    {
        $requestId = uniqid('webhook_', true);

        Log::info('[UnifiedWebhook] Request received', [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

        try {
            // Detect provider
            $provider = $this->detectProvider($request);

            // Detect message type (DLR or Inbound SMS)
            $messageType = $this->detectMessageType($request, $provider);

            Log::info("[UnifiedWebhook] Detected", [
                'request_id' => $requestId,
                'provider' => $provider,
                'type' => $messageType
            ]);

            // Route to appropriate handler
            if ($messageType === 'dlr') {
                return $this->handleDlr($request, $provider, $requestId);
            } else {
                return $this->handleInboundSms($request, $provider, $requestId);
            }

        } catch (\Exception $e) {
            Log::error('[UnifiedWebhook] Error', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 200); // Return 200 to prevent retries
        }
    }

    /**
     * Detect provider from request
     */
    private function detectProvider(Request $request): string
    {
        // Sinch indicators
        if ($request->has('type') && str_contains($request->input('type'), 'sms')) {
            return 'sinch';
        }
        if ($request->has('batch_id')) {
            return 'sinch';
        }
        if ($request->has('body') && $request->has('from') && $request->has('to') && $request->has('id')) {
            // Sinch inbound format: {"type":"mo_text","body":"message","from":"+447xxx","to":"447xxx","id":"xxx"}
            return 'sinch';
        }

        // Check for uppercase status (Sinch style)
        if ($request->has('status') && $request->has('recipient')) {
            $status = $request->input('status');
            if (strtoupper($status) === $status) {
                return 'sinch';
            }
        }

        // Default to Nexmo/Vonage
        return 'nexmo';
    }

    /**
     * Detect message type (DLR or Inbound SMS)
     */
    private function detectMessageType(Request $request, string $provider): string
    {
        if ($provider === 'sinch') {
            return $this->detectSinchMessageType($request);
        }

        return $this->detectNexmoMessageType($request);
    }

    /**
     * Detect Sinch message type
     */
    private function detectSinchMessageType(Request $request): string
    {
        $type = $request->input('type', '');

        // DLR types
        if (str_contains($type, 'delivery_report') || str_contains($type, 'recipient_delivery')) {
            return 'dlr';
        }

        // Has batch_id + status = DLR
        if ($request->has('batch_id') && $request->has('status') && !$request->has('body')) {
            return 'dlr';
        }

        // MO (inbound) types
        if (str_contains($type, 'mo_') || $type === 'inbound') {
            return 'inbound';
        }

        // Has body = inbound SMS
        if ($request->has('body') || $request->has('message')) {
            return 'inbound';
        }

        // Default to DLR if has status
        if ($request->has('status')) {
            return 'dlr';
        }

        return 'inbound';
    }

    /**
     * Detect Nexmo message type
     */
    private function detectNexmoMessageType(Request $request): string
    {
        // DLR indicators
        if ($request->has('messageId') && $request->has('status')) {
            return 'dlr';
        }
        if ($request->has('message-id') && $request->has('status')) {
            return 'dlr';
        }
        if ($request->has('err-code')) {
            return 'dlr';
        }

        // Inbound SMS indicators
        if ($request->has('text') || $request->has('message')) {
            return 'inbound';
        }
        if ($request->has('msisdn') && $request->has('to') && !$request->has('status')) {
            return 'inbound';
        }
        if ($request->has('keyword')) {
            return 'inbound';
        }

        // Default to DLR if has messageId
        if ($request->has('messageId') || $request->has('message-id')) {
            return 'dlr';
        }

        return 'inbound';
    }

    /**
     * Handle DLR (Delivery Receipt) - Queue for background processing
     */
    private function handleDlr(Request $request, string $provider, string $requestId): JsonResponse
    {
        Log::info("[UnifiedWebhook] Queuing DLR for background processing", [
            'request_id' => $requestId,
            'provider' => $provider
        ]);

        // Try to queue for background processing
        if ($this->webhookQueueService) {
            $dlrData = $this->extractDlrData($request, $provider);
            $queued = $this->webhookQueueService->queueDlr($dlrData, $provider, $requestId);

            if ($queued) {
                return response()->json([
                    'status' => 'queued',
                    'message' => 'DLR queued for processing',
                    'request_id' => $requestId
                ], 200);
            }
        }

        // Fallback: Process directly if queue is not available
        Log::warning("[UnifiedWebhook] Queue not available, processing DLR directly", [
            'request_id' => $requestId
        ]);

        if ($provider === 'sinch') {
            return $this->processSinchDlr($request, $requestId);
        }

        return $this->processNexmoDlr($request, $requestId);
    }

    /**
     * Handle Inbound SMS (MO) - Queue for background processing
     */
    private function handleInboundSms(Request $request, string $provider, string $requestId): JsonResponse
    {
        Log::info("[UnifiedWebhook] Queuing Inbound SMS for background processing", [
            'request_id' => $requestId,
            'provider' => $provider
        ]);

        // Try to queue for background processing
        if ($this->webhookQueueService) {
            $inboundData = $this->extractInboundData($request, $provider);
            $queued = $this->webhookQueueService->queueInboundSms($inboundData, $provider, $requestId);

            if ($queued) {
                return response()->json([
                    'status' => 'queued',
                    'message' => 'Inbound SMS queued for processing',
                    'request_id' => $requestId
                ], 200);
            }
        }

        // Fallback: Process directly if queue is not available
        Log::warning("[UnifiedWebhook] Queue not available, processing Inbound SMS directly", [
            'request_id' => $requestId
        ]);

        if ($provider === 'sinch') {
            return $this->processSinchInbound($request, $requestId);
        }

        return $this->processNexmoInbound($request, $requestId);
    }

    /**
     * Extract DLR data from request for queuing
     */
    private function extractDlrData(Request $request, string $provider): array
    {
        $data = $request->all();
        $data['_provider'] = $provider;
        $data['_received_at'] = Carbon::now()->toIso8601String();
        return $data;
    }

    /**
     * Extract Inbound SMS data from request for queuing
     */
    private function extractInboundData(Request $request, string $provider): array
    {
        $data = $request->all();
        $data['_provider'] = $provider;
        $data['_received_at'] = Carbon::now()->toIso8601String();
        return $data;
    }

    /**
     * Process Nexmo DLR
     */
    private function processNexmoDlr(Request $request, string $requestId): JsonResponse
    {
        $messageId = $request->input('messageId') ?: $request->input('message-id');
        $status = $request->input('status');
        $to = $request->input('to') ?: $request->input('msisdn');
        $errCode = $request->input('err-code') ?: '0';
        $timestamp = $request->input('message-timestamp');

        if (empty($messageId)) {
            Log::warning("[UnifiedWebhook] Nexmo DLR missing messageId", ['request_id' => $requestId]);
            return response()->json(['status' => 'error', 'message' => 'messageId required'], 200);
        }

        // Map status
        $mappedStatus = $this->deliveryStatusService->mapVonageStatus($status);

        // Prepare DLR data
        $dlrData = [
            'message_id' => $messageId,
            'mobile_number' => $to,
            'status' => $mappedStatus['status'],
            'error_code' => $errCode ?: $mappedStatus['error_code'],
            'done_date' => $timestamp ? Carbon::parse($timestamp)->format('YmdHis') : Carbon::now()->format('YmdHis'),
            'provider' => 'nexmo',
            'aggregator_code' => $errCode,
            'aggregator_msg' => $status,
            'retry' => '0',
            'raw_data' => $request->all(),
        ];

        // Process using DeliveryStatusService
        $result = $this->deliveryStatusService->processDeliveryReceipt($dlrData);

        Log::info("[UnifiedWebhook] Nexmo DLR processed", [
            'request_id' => $requestId,
            'message_id' => $messageId,
            'status' => $status,
            'result' => $result
        ]);

        return response()->json([
            'status' => $result ? 'success' : 'warning',
            'message' => $result ? 'DLR processed' : 'DLR record not found'
        ], 200);
    }

    /**
     * Process Sinch DLR
     */
    private function processSinchDlr(Request $request, string $requestId): JsonResponse
    {
        $messageId = $request->input('id') ?: $request->input('batch_id');
        $status = $request->input('status', 'UNKNOWN');
        $recipient = ltrim($request->input('recipient', ''), '+');
        $errorCode = $request->input('code', 0);
        $timestamp = $request->input('at');

        if (empty($messageId)) {
            Log::warning("[UnifiedWebhook] Sinch DLR missing message ID", ['request_id' => $requestId]);
            return response()->json(['status' => 'error', 'message' => 'Message ID required'], 200);
        }

        // Map status
        $mappedStatus = $this->deliveryStatusService->mapSinchStatus($status, $errorCode);

        // Prepare DLR data
        $dlrData = [
            'message_id' => $messageId,
            'mobile_number' => $recipient,
            'status' => $mappedStatus['status'],
            'error_code' => $errorCode,
            'done_date' => $timestamp ? Carbon::parse($timestamp)->format('YmdHis') : Carbon::now()->format('YmdHis'),
            'provider' => 'sinch',
            'aggregator_code' => $errorCode,
            'aggregator_msg' => $status,
            'retry' => '0',
            'raw_data' => $request->all(),
        ];

        // Process using DeliveryStatusService
        $result = $this->deliveryStatusService->processDeliveryReceipt($dlrData);

        Log::info("[UnifiedWebhook] Sinch DLR processed", [
            'request_id' => $requestId,
            'message_id' => $messageId,
            'status' => $status,
            'result' => $result
        ]);

        return response()->json([
            'status' => $result ? 'success' : 'warning',
            'message' => $result ? 'DLR processed' : 'DLR record not found'
        ], 200);
    }

    /**
     * Process Nexmo Inbound SMS
     */
    private function processNexmoInbound(Request $request, string $requestId): JsonResponse
    {
        $from = $request->input('msisdn');
        $to = $request->input('to');
        $text = $request->input('text') ?: $request->input('message');
        $messageId = $request->input('messageId') ?: $request->input('message-id') ?: uniqid('nexmo_mo_');
        $keyword = $request->input('keyword');
        $timestamp = $request->input('message-timestamp');

        Log::info("[UnifiedWebhook] Nexmo Inbound SMS", [
            'request_id' => $requestId,
            'from' => $from,
            'to' => $to,
            'text' => substr($text ?? '', 0, 50)
        ]);

        // Prepare inbound data
        $inboundData = [
            'message_id' => $messageId,
            'source' => $from,
            'dest' => $to,
            'message' => $text,
            'keyword' => $keyword,
            'provider' => 'nexmo',
            'received_at' => $timestamp ? Carbon::parse($timestamp)->toDateTimeString() : Carbon::now()->toDateTimeString(),
            'raw_data' => $request->all(),
        ];

        // Process inbound SMS
        $result = $this->processInboundMessage($inboundData);

        return response()->json([
            'status' => 'success',
            'message' => 'Inbound SMS received',
            'request_id' => $requestId
        ], 200);
    }

    /**
     * Process Sinch Inbound SMS
     */
    private function processSinchInbound(Request $request, string $requestId): JsonResponse
    {
        $from = ltrim($request->input('from', ''), '+');
        $to = ltrim($request->input('to', ''), '+');
        $text = $request->input('body') ?: $request->input('message');
        $messageId = $request->input('id') ?: uniqid('sinch_mo_');
        $timestamp = $request->input('received_at');

        Log::info("[UnifiedWebhook] Sinch Inbound SMS", [
            'request_id' => $requestId,
            'from' => $from,
            'to' => $to,
            'text' => substr($text ?? '', 0, 50)
        ]);

        // Prepare inbound data
        $inboundData = [
            'message_id' => $messageId,
            'source' => $from,
            'dest' => $to,
            'message' => $text,
            'provider' => 'sinch',
            'received_at' => $timestamp ? Carbon::parse($timestamp)->toDateTimeString() : Carbon::now()->toDateTimeString(),
            'raw_data' => $request->all(),
        ];

        // Process inbound SMS
        $result = $this->processInboundMessage($inboundData);

        return response()->json([
            'status' => 'success',
            'message' => 'Inbound SMS received',
            'request_id' => $requestId
        ], 200);
    }

    /**
     * Process inbound message (store and trigger keyword actions)
     */
    private function processInboundMessage(array $inboundData): bool
    {
        try {
            // Try to use InboundSmsProcessor if available
            if (class_exists(\App\Services\InboundSmsProcessor::class)) {
                $processor = app(\App\Services\InboundSmsProcessor::class);
                return $processor->process($inboundData);
            }

            // Fallback: Store in smsg_inbound table
            $this->storeInboundSms($inboundData);
            return true;

        } catch (\Exception $e) {
            Log::error("[UnifiedWebhook] Failed to process inbound SMS", [
                'error' => $e->getMessage(),
                'data' => $inboundData
            ]);
            return false;
        }
    }

    /**
     * Store inbound SMS in database
     */
    private function storeInboundSms(array $data): void
    {
        $now = Carbon::now();
        $cleanFrom = preg_replace('/[^0-9]/', '', $data['source'] ?? '');
        $cleanTo = preg_replace('/[^0-9]/', '', $data['dest'] ?? '');

        // Find the shortcode/virtual number owner
        $shortcode = DB::table('smsshortcodes')
            ->where('number', $cleanTo)
            ->orWhere('number', ltrim($cleanTo, '44'))
            ->first();

        $userRef = $shortcode->bigid ?? null;

        // Insert into smsg_inbound
        DB::table('smsg_inbound')->insert([
            'bigid' => md5(uniqid('', true)),
            'userref' => $userRef,
            'mobnum' => $cleanFrom,
            'shortcode' => $cleanTo,
            'text' => $data['message'] ?? '',
            'operator_msgid' => $data['message_id'] ?? '',
            'timereceived' => $now->format('YmdHis'),
            'provider' => $data['provider'] ?? 'unknown',
            'created_at' => $now,
        ]);

        Log::info("[UnifiedWebhook] Inbound SMS stored", [
            'from' => $cleanFrom,
            'to' => $cleanTo,
            'userref' => $userRef
        ]);
    }

    /**
     * Test endpoint
     */
    public function test(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Unified SMS Webhook is active',
            'endpoints' => [
                'combined' => '/api/sms/webhook',
                'nexmo' => '/api/sms/webhook/nexmo',
                'sinch' => '/api/sms/webhook/sinch',
            ]
        ]);
    }

    /**
     * Nexmo-specific endpoint (alias)
     */
    public function nexmo(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * Sinch-specific endpoint (alias)
     */
    public function sinch(Request $request): JsonResponse
    {
        return $this->handle($request);
    }
}
