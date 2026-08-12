<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Queue\RabbitMQService;
use Carbon\Carbon;
use Exception;

/**
 * Inbound SMS Webhook Controller
 * 
 * This controller receives inbound SMS webhooks from providers (Nexmo, OneSixty, etc.)
 * and queues them to RabbitMQ for sequential processing.
 * 
 * The actual processing is done by the InboundSmsProcessor service
 * which is consumed by the ProcessInboundSmsQueue artisan command.
 */
class InboundSmsWebhookController extends Controller
{
    const NO_OPERATOR_MESSAGE_ID = -1;

    protected $rabbitMQService;

    public function __construct()
    {
        try {
            $this->rabbitMQService = new RabbitMQService();
        } catch (Exception $e) {
            Log::error('Failed to initialize RabbitMQ service in InboundSmsWebhookController: ' . $e->getMessage());
            $this->rabbitMQService = null;
        }
    }

    /**
     * Handle inbound SMS webhook from multiple providers
     * Queues the request to RabbitMQ for sequential processing
     * 
     * Supports: Nexmo/Vonage, OneSixty, and generic SMPP
     */
    public function handle(Request $request)
    {
        $requestId = uniqid('inbound_', true);
        
        Log::info('[InboundSMS] Webhook received', [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'data' => $request->all()
        ]);

        try {
            // Parse incoming message based on provider
            $inboundData = $this->parseInboundMessage($request);
            
            if (!$inboundData) {
                Log::warning('[InboundSMS] Could not parse inbound message', [
                    'request_id' => $requestId,
                    'data' => $request->all()
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid request format'], 400);
            }

            // Add metadata
            $inboundData['request_id'] = $requestId;
            $inboundData['received_at'] = Carbon::now()->toIso8601String();
            $inboundData['ip_address'] = $request->ip();
            $inboundData['user_agent'] = $request->userAgent();
            $inboundData['raw_request'] = $request->all();

            // Queue to RabbitMQ for processing
            $queued = $this->queueForProcessing($inboundData);

            if ($queued) {
                Log::info('[InboundSMS] Message queued successfully', [
                    'request_id' => $requestId,
                    'source' => $inboundData['source'],
                    'dest' => $inboundData['dest']
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Message queued for processing',
                    'request_id' => $requestId
                ]);
            } else {
                // If RabbitMQ is unavailable, process directly (fallback)
                Log::warning('[InboundSMS] RabbitMQ unavailable, processing directly', [
                    'request_id' => $requestId
                ]);

                return $this->processFallback($inboundData);
            }

        } catch (Exception $e) {
            Log::error('[InboundSMS] Error handling webhook', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Queue inbound SMS to RabbitMQ for processing
     */
    protected function queueForProcessing(array $inboundData): bool
    {
        if (!$this->rabbitMQService) {
            return false;
        }

        try {
            $queueName = env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound');
            
            // Add queue metadata
            $queueData = [
                'queue_id' => $inboundData['request_id'],
                'type' => 'inbound_sms',
                'queued_at' => Carbon::now()->toIso8601String(),
                'data' => $inboundData
            ];

            // Publish to inbound queue with normal priority
            $result = $this->rabbitMQService->publishToQueue($queueName, $queueData, 5);

            return $result;
        } catch (Exception $e) {
            Log::error('[InboundSMS] Failed to queue message', [
                'request_id' => $inboundData['request_id'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Fallback: Process directly if RabbitMQ is unavailable
     */
    protected function processFallback(array $inboundData)
    {
        try {
            // Use the processor service directly
            $processor = app(\App\Services\InboundSmsProcessor::class);
            $processor->process($inboundData);

            return response()->json([
                'status' => 'success',
                'message' => 'Message processed directly (fallback)',
                'request_id' => $inboundData['request_id']
            ]);
        } catch (Exception $e) {
            Log::error('[InboundSMS] Fallback processing failed', [
                'request_id' => $inboundData['request_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Processing failed'], 500);
        }
    }

    /**
     * Parse inbound message from different providers
     */
    protected function parseInboundMessage(Request $request)
    {
        // OneSixty JSON format
        if ($request->has('JSON') && $request->input('JSON')) {
            return $this->parseOneSixtyMessage($request);
        }

        // Sinch format (has 'from', 'to', 'body', 'type')
        // Sinch sends: {"body":"message","from":"447xxx","to":"447xxx","type":"mo_text","id":"xxx"}
        if ($request->has('type') && in_array($request->input('type'), ['mo_text', 'mo_binary'])) {
            return $this->parseSinchMessage($request);
        }

        // Sinch alternative format (check for 'body' and 'from' without 'msisdn')
        if ($request->has('body') && $request->has('from') && $request->has('to') && !$request->has('msisdn')) {
            return $this->parseSinchMessage($request);
        }

        // Nexmo/Vonage format (query params or POST)
        if ($request->has('msisdn')) {
            return [
                'source' => $request->input('msisdn'),
                'dest' => $request->input('to'),
                'message' => $request->input('text'),
                'network' => $request->input('network', '99'),
                'operator_message_id' => $request->input('messageId', self::NO_OPERATOR_MESSAGE_ID),
                'operator_id' => '',
                'provider' => 'nexmo'
            ];
        }

        // Generic format (source, dest, message params)
        if ($request->has('source') && $request->has('dest') && $request->has('message')) {
            return [
                'source' => $request->input('source'),
                'dest' => $request->input('dest'),
                'message' => $request->input('message'),
                'network' => $request->input('network', '99'),
                'operator_message_id' => $request->input('operator_message_id', self::NO_OPERATOR_MESSAGE_ID),
                'operator_id' => $request->input('operatorId', ''),
                'provider' => 'generic'
            ];
        }

        return null;
    }

    /**
     * Parse Sinch inbound SMS format
     * Sinch MO format: {"body":"message","from":"447xxx","to":"447xxx","type":"mo_text","id":"xxx","operator_id":"xxx","received_at":"xxx"}
     */
    protected function parseSinchMessage(Request $request)
    {
        $from = $request->input('from', '');
        $to = $request->input('to', '');

        // Clean phone numbers (remove + prefix if present)
        $from = ltrim($from, '+');
        $to = ltrim($to, '+');

        return [
            'source' => $from,
            'dest' => $to,
            'message' => $request->input('body', ''),
            'network' => '99',
            'operator_message_id' => $request->input('id', self::NO_OPERATOR_MESSAGE_ID),
            'operator_id' => $request->input('operator_id', ''),
            'provider' => 'sinch',
            'sinch_type' => $request->input('type', 'mo_text'),
            'received_at' => $request->input('received_at'),
            'sent_at' => $request->input('sent_at'),
        ];
    }

    /**
     * Parse OneSixty JSON format
     */
    protected function parseOneSixtyMessage(Request $request)
    {
        $json = $request->input('JSON');
        $decoded = json_decode($json, true);

        if (!$decoded || count($decoded) < 3) {
            return null;
        }

        $source = $decoded[0];
        $dest = $decoded[1];
        $message = '';

        // Handle STOP command format for OneSixty
        if ($dest == '447777777777' && strtolower(trim($decoded[2])) == 'stop') {
            $message = isset($decoded[3]) ? trim($decoded[3]) . ' ' : '';
            $message .= $decoded[4] ?? '';
        } else {
            $message .= isset($decoded[2]) ? trim($decoded[2]) . ' ' : '';
            $message .= isset($decoded[3]) ? trim($decoded[3]) . ' ' : '';
            $message .= $decoded[4] ?? '';
        }

        $operatorMessageId = '1s-' . strtolower(substr(md5(uniqid(rand(), 1)), 0, 29));

        return [
            'source' => $source,
            'dest' => $dest,
            'message' => trim($message),
            'network' => '99',
            'operator_message_id' => $operatorMessageId,
            'operator_id' => '99',
            'provider' => 'onesixty'
        ];
    }

    /**
     * Test endpoint to verify webhook is working
     */
    public function test()
    {
        $rabbitMQStatus = 'disconnected';
        $queueStats = null;

        if ($this->rabbitMQService) {
            try {
                $queueStats = $this->rabbitMQService->getQueueStats(env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound'));
                $rabbitMQStatus = isset($queueStats['error']) ? 'error: ' . $queueStats['error'] : 'connected';
            } catch (Exception $e) {
                $rabbitMQStatus = 'error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Inbound SMS webhook endpoint is working',
            'timestamp' => now()->toDateTimeString(),
            'rabbitmq_status' => $rabbitMQStatus,
            'queue_stats' => $queueStats
        ]);
    }

    /**
     * Get queue statistics
     */
    public function stats()
    {
        if (!$this->rabbitMQService) {
            return response()->json([
                'status' => 'error',
                'message' => 'RabbitMQ not connected'
            ], 503);
        }

        try {
            $inboundStats = $this->rabbitMQService->getQueueStats(env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound'));
            $deadStats = $this->rabbitMQService->getQueueStats('sms.dead');

            return response()->json([
                'status' => 'success',
                'queues' => [
                    'inbound' => $inboundStats,
                    'dead_letter' => $deadStats
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
