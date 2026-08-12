<?php

namespace App\Services\Queue;

use App\Services\SMPP\SMPPPoolManager;
use App\Services\SMPP\SinchSmppService;
use App\Services\DeliveryStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * SMS Queue Service
 * Manages SMS operations and integration between RabbitMQ and SMPP
 * Supports both Nexmo and Sinch SMPP providers
 *
 * NOTE: sms_queue table has been REMOVED from this project.
 * All SMS sending goes directly via SMPP services.
 * Tracking is done via smsg_log table only.
 */
class SmsQueueService
{
    private $rabbitMQ;
    private $smppPool;
    private $sinchSmpp;
    private DeliveryStatusService $deliveryStatusService;

    public function __construct()
    {
        try {
            $this->rabbitMQ = new RabbitMQService();
        } catch (Exception $e) {
            Log::warning("SmsQueueService: RabbitMQ not available - " . $e->getMessage());
            $this->rabbitMQ = null;
        }

        try {
            $this->smppPool = new SMPPPoolManager();
        } catch (Exception $e) {
            Log::warning("SmsQueueService: SMPPPoolManager not available - " . $e->getMessage());
            $this->smppPool = null;
        }

        $this->sinchSmpp = null; // Lazy load when needed
        $this->deliveryStatusService = new DeliveryStatusService();
    }

    /**
     * Send SMS directly via SMPP (no queue table used)
     * This method maintains backward compatibility but sends directly instead of queueing
     *
     * @deprecated Use direct SMPP service calls instead (sendViaSinch, sendViaNexmo)
     */
    public function queueSms($data)
    {
        try {
            // Validate required fields
            $mobileNumber = $data['mobile_number'] ?? null;
            $message = $data['message'] ?? null;

            if (empty($mobileNumber) || empty(trim($mobileNumber))) {
                Log::warning("queueSms: Empty mobile number, skipping", $data);
                return [
                    'success' => false,
                    'error' => 'Mobile number is required'
                ];
            }

            if (empty($message) || empty(trim($message))) {
                Log::warning("queueSms: Empty message, skipping", $data);
                return [
                    'success' => false,
                    'error' => 'Message is required'
                ];
            }

            // Generate unique ID for tracking
            $queueId = $this->generateQueueId();

            // Extract schedule_delivery_time from data
            $scheduleDeliveryTime = $data['schedule_delivery_time'] ?? null;
            $scheduledAt = $data['scheduled_at'] ?? null;

            // If scheduled, don't send now - just return success (smsg_log handles scheduling)
            if (!empty($scheduledAt)) {
                $scheduledTime = Carbon::parse($scheduledAt);
                if ($scheduledTime->isFuture()) {
                    Log::info("SMS scheduled for future - tracked via smsg_log", [
                        'queue_id' => $queueId,
                        'scheduled_at' => $scheduledAt,
                        'mobile' => $mobileNumber
                    ]);

                    return [
                        'success' => true,
                        'queue_id' => $queueId,
                        'message' => 'SMS scheduled for future delivery (via smsg_log)',
                        'scheduled' => true
                    ];
                }
            }

            // Prepare SMS data
            $smsData = [
                'queue_id' => $queueId,
                'user_ref' => $data['user_ref'] ?? 'system',
                'mobile_number' => $this->formatPhoneNumber($data['mobile_number']),
                'message' => $data['message'],
                'sender_id' => $data['sender_id'] ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME'),
                'priority' => $data['priority'] ?? 5,
                'reference_id' => $data['reference_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'schedule_delivery_time' => $scheduleDeliveryTime,
                'metadata' => $data['metadata'] ?? [],
            ];

            // Determine provider from metadata or default to nexmo
            $provider = $data['provider'] ?? ($data['metadata']['provider'] ?? 'nexmo');

            // Determine initiator based on source
            $initiator = 'ControlPanel';
            if (isset($smsData['metadata']['api_request']) && $smsData['metadata']['api_request']) {
                $initiator = 'ExternalAPI';
            } elseif (isset($smsData['metadata']['source'])) {
                if ($smsData['metadata']['source'] == 'api') {
                    $initiator = 'ExternalAPI';
                }
            }

            // Unique smsg_log row id — when the same number appears several times in
            // one request, bigid+mobnum is NOT unique, so the SMPP duplicate guard must
            // key off this row id or it skips every repeat after the first.
            $smsgLogId = $smsData['metadata']['smsg_log_id'] ?? ($data['metadata']['smsg_log_id'] ?? null);

            // Send directly via appropriate SMPP provider
            if ($provider === 'sinch') {
                $result = $this->sendViaSinch(
                    $smsData['mobile_number'],
                    $smsData['message'],
                    $smsData['sender_id'],
                    $smsData['priority'],
                    $queueId,
                    $initiator,
                    $smsData['reference_id'],
                    $scheduleDeliveryTime,
                    $smsgLogId
                );
            } else {
                $result = $this->sendViaNexmo(
                    $smsData['mobile_number'],
                    $smsData['message'],
                    $smsData['sender_id'],
                    $smsData['priority'],
                    $queueId,
                    $initiator,
                    $smsData['reference_id'],
                    $scheduleDeliveryTime,
                    $smsgLogId
                );
            }

            if ($result['success']) {
                Log::info("SMS sent directly via SMPP", [
                    'queue_id' => $queueId,
                    'message_id' => $result['message_id'] ?? '',
                    'provider' => $provider,
                    'mobile' => $smsData['mobile_number']
                ]);

                return [
                    'success' => true,
                    'queue_id' => $queueId,
                    'message_id' => $result['message_id'] ?? '',
                    'message' => 'SMS sent via ' . $provider . ' SMPP',
                    'scheduled' => false
                ];
            } else {
                Log::error("Failed to send SMS via SMPP", [
                    'queue_id' => $queueId,
                    'provider' => $provider,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                return [
                    'success' => false,
                    'queue_id' => $queueId,
                    'error' => $result['error'] ?? 'Failed to send via SMPP'
                ];
            }

        } catch (Exception $e) {
            Log::error("SmsQueueService: Failed to send SMS - " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Publish an SMS to the RabbitMQ outbound queue for ASYNCHRONOUS delivery by the
     * ProcessSmsQueue worker (which holds a persistent SMPP bind). This is the correct
     * path for a web/API request: it returns in milliseconds instead of blocking the
     * request on a synchronous SMPP connect+submit (the ~100s-timeout problem, and the
     * per-request bind churn). The worker consumes 'sms.outbound' and routes to
     * Nexmo/Sinch by the 'provider' field.
     *
     * The published envelope matches ProcessSmsQueue::processSmsMessage() exactly
     * (see also ProcessInboundSms::sendAutoReply()).
     *
     * @return array ['success' => bool, 'queue_id' => string|null, 'error' => string|null]
     */
    public function enqueueSms($data)
    {
        try {
            $mobileNumber = $data['mobile_number'] ?? null;
            $message = $data['message'] ?? null;

            if (empty($mobileNumber) || empty(trim($mobileNumber))) {
                Log::warning("enqueueSms: Empty mobile number, skipping", $data);
                return ['success' => false, 'error' => 'Mobile number is required'];
            }

            if (empty($message) || empty(trim($message))) {
                Log::warning("enqueueSms: Empty message, skipping", $data);
                return ['success' => false, 'error' => 'Message is required'];
            }

            if (!$this->rabbitMQ) {
                return ['success' => false, 'error' => 'RabbitMQ not available'];
            }

            $queueId = $this->generateQueueId();
            $provider = $data['provider'] ?? ($data['metadata']['provider'] ?? 'nexmo');

            $smsData = [
                'queue_id'      => $queueId,
                'user_ref'      => $data['user_ref'] ?? 'system',
                'mobile_number' => $this->formatPhoneNumber($data['mobile_number']),
                'message'       => $data['message'],
                'sender_id'     => $data['sender_id'] ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME'),
                'priority'      => $data['priority'] ?? 5,
                'reference_id'  => $data['reference_id'] ?? null,
                'provider'      => $provider,
                'metadata'      => $data['metadata'] ?? [],
            ];

            // Single outbound queue; the worker routes to Nexmo/Sinch by 'provider'.
            $queueName = env('RABBITMQ_SMS_QUEUE', 'sms.outbound');

            $published = $this->rabbitMQ->publishToQueue($queueName, $smsData, $smsData['priority']);

            if ($published) {
                Log::info("SMS enqueued for async SMPP delivery", [
                    'queue_id' => $queueId,
                    'queue'    => $queueName,
                    'provider' => $provider,
                    'mobile'   => $smsData['mobile_number'],
                ]);
                return ['success' => true, 'queue_id' => $queueId, 'queued' => true];
            }

            return [
                'success'  => false,
                'queue_id' => $queueId,
                'error'    => 'Failed to publish to queue (broker unavailable)',
            ];
        } catch (Exception $e) {
            Log::error("SmsQueueService: enqueueSms failed - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send SMS via Sinch SMPP
     */
    private function sendViaSinch($mobile, $message, $senderId, $priority, $queueId, $initiator, $referenceId, $scheduleDeliveryTime, $smsgLogId = null)
    {
        // Lazy load Sinch service
        if (!$this->sinchSmpp) {
            $this->sinchSmpp = new SinchSmppService();
        }

        return $this->sinchSmpp->sendSMS(
            $mobile,
            $message,
            $senderId,
            $priority,
            $queueId,
            $initiator,
            $referenceId,
            $scheduleDeliveryTime,
            $smsgLogId
        );
    }

    /**
     * Send SMS via Nexmo SMPP
     */
    private function sendViaNexmo($mobile, $message, $senderId, $priority, $queueId, $initiator, $referenceId, $scheduleDeliveryTime, $smsgLogId = null)
    {
        if (!$this->smppPool) {
            return [
                'success' => false,
                'error' => 'SMPP Pool Manager not available'
            ];
        }

        return $this->smppPool->sendSMS(
            $mobile,
            $message,
            $senderId,
            $priority,
            $queueId,
            $initiator,
            $referenceId,
            $scheduleDeliveryTime,
            $smsgLogId
        );
    }

    /**
     * Process SMS from queue (Consumer) - kept for backward compatibility
     * NOTE: This now processes directly without sms_queue table
     */
    public function processSms($data)
    {
        try {
            Log::info("Processing SMS directly", ['queue_id' => $data['queue_id'] ?? 'unknown']);

            // Determine provider (default to nexmo)
            $provider = $data['provider'] ?? 'nexmo';

            // Determine initiator based on source
            $initiator = 'ControlPanel';
            if (isset($data['metadata']['api_request']) && $data['metadata']['api_request']) {
                $initiator = 'ExternalAPI';
            } elseif (isset($data['metadata']['source'])) {
                if ($data['metadata']['source'] == 'api') {
                    $initiator = 'ExternalAPI';
                }
            }

            // Extract schedule_delivery_time for SMPP
            $scheduleDeliveryTime = $data['schedule_delivery_time'] ?? null;

            // Route to appropriate SMPP provider
            if ($provider === 'sinch') {
                $result = $this->sendViaSinch(
                    $data['mobile_number'],
                    $data['message'],
                    $data['sender_id'],
                    $data['priority'] ?? 5,
                    $data['queue_id'] ?? null,
                    $initiator,
                    $data['reference_id'] ?? null,
                    $scheduleDeliveryTime
                );
            } else {
                $result = $this->sendViaNexmo(
                    $data['mobile_number'],
                    $data['message'],
                    $data['sender_id'],
                    $data['priority'] ?? 5,
                    $data['queue_id'] ?? null,
                    $initiator,
                    $data['reference_id'] ?? null,
                    $scheduleDeliveryTime
                );
            }

            if ($result['success']) {
                Log::info("SMS sent successfully", [
                    'queue_id' => $data['queue_id'] ?? 'unknown',
                    'message_id' => $result['message_id'] ?? '',
                    'provider' => $provider
                ]);
                return true;
            } else {
                Log::error("SMS send failed", [
                    'queue_id' => $data['queue_id'] ?? 'unknown',
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                return false;
            }

        } catch (Exception $e) {
            Log::error("Failed to process SMS", [
                'queue_id' => $data['queue_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process delivery receipt (OLD SYSTEM compatible via DeliveryStatusService)
     */
    /**
     * OLD-SYSTEM DLR path (no RabbitMQ): store a received DLR into the smsg_receipt_buffer_new
     * TABLE. The dlr:process-buffer daemon then reads it, matches the indexed
     * onesixty_suppliermsgref, updates smsg_log, and deletes the row — exactly like
     * daemon_dreceipt_inbound_buffer.php. Use this from the SMPP DLR receiver instead of
     * publishing to sms.dlr when you want the table-based flow.
     *
     * @return bool true if the DLR was buffered
     */
    public function storeDlrToBuffer($data): bool
    {
        try {
            DB::table('smsg_receipt_buffer_new')->insert([
                'XMLDATA'     => json_encode($data),
                'status'      => 'new',
                'processtime' => Carbon::now()->format('YmdHi'),
            ]);
            return true;
        } catch (Exception $e) {
            Log::error('storeDlrToBuffer failed: ' . $e->getMessage(), ['message_id' => $data['message_id'] ?? null]);
            return false;
        }
    }

    public function processDlr($data)
    {
        try {
            Log::info("Processing DLR (OLD SYSTEM logic)", $data);

            // Map SMPP status to OLD SYSTEM error code
            $status = $data['status'] ?? 'UNKNOWN';
            $errorCode = $data['error_code'] ?? $this->mapSmppStatusToErrorCode($status);

            // Prepare DLR payload for DeliveryStatusService
            $dlrPayload = [
                'message_id' => $data['message_id'],
                'mobile_number' => $data['mobile_number'] ?? '',
                'status' => $status,
                'error_code' => $errorCode,
                'done_date' => $data['done_date'] ?? Carbon::now()->format('YmdHis'),
                'provider' => $data['provider'] ?? 'nexmo',
                'aggregator_code' => $data['error_code'] ?? $errorCode,
                'aggregator_msg' => $data['status_text'] ?? $status,
                'retry' => $data['retry'] ?? '0',
                'raw_data' => $data,
            ];

            // Use DeliveryStatusService for OLD SYSTEM compatible processing
            // This handles: smsg_log update, wallet refund, DLR push callback
            $result = $this->deliveryStatusService->processDeliveryReceipt($dlrPayload);

            // Store DLR in sms_dlr table for tracking
            DB::table('sms_dlr')->insertOrIgnore([
                'message_id' => $data['message_id'],
                'queue_id' => $data['queue_id'] ?? '',
                'mobile_number' => $data['mobile_number'] ?? '',
                'status' => $data['status'],
                'status_text' => $data['status_text'] ?? '',
                'error_code' => $data['error_code'] ?? null,
                'submit_date' => isset($data['submit_date']) ? Carbon::parse($data['submit_date']) : null,
                'done_date' => isset($data['done_date']) ? Carbon::parse($data['done_date']) : null,
                'network_code' => $data['network_code'] ?? null,
                'charge' => $data['charge'] ?? 0,
                'raw_dlr' => json_encode($data['raw_dlr'] ?? $data),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Push DLR notification if environment webhook configured
            $this->pushDlrNotification($data);

            return $result;

        } catch (Exception $e) {
            Log::error("Failed to process DLR: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Map SMPP status to OLD SYSTEM error/reason code
     */
    private function mapSmppStatusToErrorCode(string $status): int
    {
        $statusMap = [
            'DELIVRD' => 4,   // Delivered to mobile device
            'EXPIRED' => 8,   // Message expired
            'DELETED' => 20,  // Permanent Operator error
            'UNDELIV' => 5,   // Failed, no further info
            'ACCEPTD' => 3,   // Delivered to network (acked)
            'ACKED'   => 3,   // Delivered to network
            'UNKNOWN' => 6,   // Final status unknown
            'REJECTD' => 27,  // Barred by User
            'BUFFERED' => 2,  // Buffered at SMSC
            'SKIPPED' => 20,  // Skipped = permanent failure
        ];

        return $statusMap[strtoupper($status)] ?? 6;
    }

    /**
     * Generate unique queue ID
     */
    private function generateQueueId()
    {
        return 'sms_' . uniqid() . '_' . time();
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber($number)
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Convert UK numbers starting with 0 to 44
        if (substr($number, 0, 1) === '0') {
            $number = '44' . substr($number, 1);
        }

        return $number;
    }

    /**
     * Map DLR status to internal status (OLD SYSTEM compatible)
     */
    private function mapDlrStatus($dlrStatus)
    {
        // Map to simple status for internal use
        $statusMap = [
            'DELIVRD' => 'delivered',
            'EXPIRED' => 'failed',     // OLD SYSTEM: expired = failed
            'DELETED' => 'failed',
            'UNDELIV' => 'failed',
            'ACCEPTD' => 'sent',
            'ACKED'   => 'sent',
            'UNKNOWN' => 'unknown',
            'REJECTD' => 'failed',
            'BUFFERED' => 'buffered',
            'SKIPPED' => 'failed'
        ];

        return $statusMap[strtoupper($dlrStatus)] ?? 'unknown';
    }

    /**
     * Map DLR status for smsg_log table (OLD SYSTEM compatible)
     * @deprecated Use DeliveryStatusService instead
     */
    private function mapDlrStatusForSmsgLog($dlrStatus)
    {
        // OLD SYSTEM status mapping
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Non Delivered',  // OLD SYSTEM: expired = Non Delivered
            'DELETED' => 'Non Delivered',  // OLD SYSTEM: deleted = Non Delivered
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'acked',          // OLD SYSTEM: accepted = acked
            'ACKED'   => 'acked',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Non Delivered',  // OLD SYSTEM: rejected = Non Delivered
            'BUFFERED' => 'buffered smsc',
            'SKIPPED' => 'Non Delivered'
        ];

        return $statusMap[strtoupper($dlrStatus)] ?? 'Unknown';
    }

    /**
     * Update smsg_log with DLR
     * @deprecated Use DeliveryStatusService.processDeliveryReceipt() instead
     */
    private function updateSmsgLogDlr($data)
    {
        // This method is deprecated - processDlr now uses DeliveryStatusService
        // Kept for backward compatibility only
        try {
            $deliveryStatus = $this->mapDlrStatusForSmsgLog($data['status']);
            $errorCode = $data['error_code'] ?? $this->mapSmppStatusToErrorCode($data['status']);
            $upstreamErrorMessage = $this->deliveryStatusService->getUpstreamErrorMessage($errorCode);

            DB::table('smsg_log')
                ->where('suppliermsgref', $data['message_id'])
                ->update([
                    'deliverystatus2' => $deliveryStatus,
                    'upstream_errormessage' => $upstreamErrorMessage,
                    'delivery_reason' => $errorCode,
                    'aggregator_dlrcode' => $data['error_code'] ?? 0,
                    'aggregator_dlrmsg' => $data['status_text'] ?? $data['status']
                ]);
        } catch (Exception $e) {
            Log::warning("Failed to update smsg_log with DLR: " . $e->getMessage());
        }
    }

    /**
     * Push DLR notification to webhook
     */
    private function pushDlrNotification($data)
    {
        // Check if DLR webhook is configured
        $webhookUrl = env('DLR_WEBHOOK_URL');

        if (empty($webhookUrl)) {
            return;
        }

        try {
            // Prepare webhook payload
            $payload = [
                'message_id' => $data['message_id'],
                'queue_id' => $data['queue_id'] ?? '',
                'status' => $data['status'],
                'mobile_number' => $data['mobile_number'] ?? '',
                'error_code' => $data['error_code'] ?? null,
                'submit_date' => $data['submit_date'] ?? null,
                'done_date' => $data['done_date'] ?? null,
                'timestamp' => Carbon::now()->toIso8601String()
            ];

            // Send webhook (using curl for simplicity).
            // IMPORTANT: this runs INLINE in the DLR consumer loop, so a slow/unreachable
            // webhook throttles DLR processing (an unreachable URL at the old 10s timeout
            // dropped throughput to ~0.2 DLR/s). Keep the connect + total timeouts tight so
            // one bad endpoint can never stall the queue. Customer DLR callbacks are handled
            // separately by the dlr.callback.push queue, so this env-webhook is best-effort.
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                Log::info("DLR webhook sent successfully", ['message_id' => $data['message_id']]);
            } else {
                Log::warning("DLR webhook failed", [
                    'message_id' => $data['message_id'],
                    'http_code' => $httpCode
                ]);
            }
        } catch (Exception $e) {
            Log::error("Failed to send DLR webhook: " . $e->getMessage());
        }
    }

    /**
     * Get statistics (simplified without sms_queue)
     */
    public function getStatistics()
    {
        $stats = [
            'smpp_pool_stats' => $this->smppPool ? $this->smppPool->getStatistics() : [],
            'database_stats' => [
                'pending' => DB::table('smsg_log')->where('sentstatus', 'pending')->count(),
                'sent' => DB::table('smsg_log')->where('sentstatus', 'ok')->count(),
                'failed' => DB::table('smsg_log')->where('sentstatus', 'fail')->count(),
                'delivered' => DB::table('sms_dlr')->where('status', 'DELIVRD')->count(),
            ],
        ];

        return $stats;
    }

    /**
     * Clean old records (only cleans sms_dlr now)
     */
    public function cleanOldRecords($days = 30)
    {
        $cutoffDate = Carbon::now()->subDays($days);

        // Clean old DLR records
        $deletedDlr = DB::table('sms_dlr')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        Log::info("Cleaned old DLR records", [
            'dlr_records' => $deletedDlr
        ]);

        return [
            'dlr_records' => $deletedDlr
        ];
    }
}
