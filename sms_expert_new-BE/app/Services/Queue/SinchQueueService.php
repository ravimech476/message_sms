<?php

namespace App\Services\Queue;

use App\Services\SMPP\SinchSmppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * Sinch SMS Queue Service
 * Manages SMS operations for Sinch SMPP
 *
 * NOTE: sms_queue table has been REMOVED from this project.
 * All SMS sending goes directly via Sinch SMPP service.
 * Tracking is done via smsg_log table only.
 */
class SinchQueueService
{
    private $rabbitMQ;
    private $sinchSmpp;

    const QUEUE_NAME = 'sms.sinch.outbound';
    const QUEUE_PRIORITY = 'sms.sinch.priority';
    const QUEUE_FAILED = 'sms.sinch.failed';
    const QUEUE_DLR = 'sms.sinch.dlr';

    public function __construct()
    {
        try {
            $this->rabbitMQ = new RabbitMQService();
        } catch (Exception $e) {
            Log::warning("SinchQueueService: RabbitMQ not available - " . $e->getMessage());
            $this->rabbitMQ = null;
        }

        $this->sinchSmpp = null; // Lazy load
    }

    /**
     * Send SMS directly via Sinch SMPP (no queue table used)
     * This method maintains backward compatibility but sends directly instead of queueing
     */
    public function queueSms(array $data): array
    {
        try {
            // Validate required fields
            $mobileNumber = $data['mobile_number'] ?? null;
            $message = $data['message'] ?? null;

            if (empty($mobileNumber) || empty(trim($mobileNumber))) {
                return [
                    'success' => false,
                    'error' => 'Mobile number is required'
                ];
            }

            if (empty($message) || empty(trim($message))) {
                return [
                    'success' => false,
                    'error' => 'Message is required'
                ];
            }

            // Generate unique queue ID for tracking
            $queueId = $this->generateQueueId();

            // Extract schedule info
            $scheduleDeliveryTime = $data['schedule_delivery_time'] ?? null;
            $scheduledAt = $data['scheduled_at'] ?? null;

            // If scheduled for future, don't send now - smsg_log handles scheduling
            if (!empty($scheduledAt)) {
                $scheduledTime = Carbon::parse($scheduledAt);
                if ($scheduledTime->isFuture()) {
                    Log::info("Sinch SMS scheduled for future - tracked via smsg_log", [
                        'queue_id' => $queueId,
                        'scheduled_at' => $scheduledAt,
                        'mobile' => $mobileNumber
                    ]);

                    return [
                        'success' => true,
                        'queue_id' => $queueId,
                        'message' => 'SMS scheduled for Sinch delivery (via smsg_log)',
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
                'sender_id' => $data['sender_id'] ?? env('SINCH_SMPP_SENDER', 'SMSEXPERT'),
                'priority' => $data['priority'] ?? 5,
                'reference_id' => $data['reference_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'schedule_delivery_time' => $scheduleDeliveryTime,
                'provider' => 'sinch',
                'metadata' => $data['metadata'] ?? [],
            ];

            // Send directly via Sinch SMPP
            $result = $this->processSms($smsData);

            if ($result) {
                return [
                    'success' => true,
                    'queue_id' => $queueId,
                    'message' => 'SMS sent via Sinch SMPP',
                    'scheduled' => false
                ];
            } else {
                return [
                    'success' => false,
                    'queue_id' => $queueId,
                    'error' => 'Failed to send via Sinch SMPP'
                ];
            }

        } catch (Exception $e) {
            Log::error("Sinch Queue: Failed to send SMS - " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process SMS - send directly via Sinch SMPP
     */
    public function processSms(array $data): bool
    {
        try {
            Log::info("Sinch Queue: Processing SMS directly", ['queue_id' => $data['queue_id'] ?? 'unknown']);

            // Initialize Sinch SMPP service
            if (!$this->sinchSmpp) {
                $this->sinchSmpp = new SinchSmppService();
            }

            // Determine initiator
            $initiator = 'ControlPanel';
            if (isset($data['metadata']['api_request']) && $data['metadata']['api_request']) {
                $initiator = 'ExternalAPI';
            } elseif (isset($data['metadata']['source'])) {
                $initiator = ($data['metadata']['source'] == 'api') ? 'ExternalAPI' : 'ControlPanel';
            }

            // Send via Sinch SMPP
            $result = $this->sinchSmpp->sendSMS(
                $data['mobile_number'],
                $data['message'],
                $data['sender_id'],
                $data['priority'] ?? 5,
                $data['queue_id'] ?? null,
                $initiator,
                $data['reference_id'] ?? null,
                $data['schedule_delivery_time'] ?? null
            );

            if ($result['success']) {
                Log::info("Sinch Queue: SMS sent successfully", [
                    'queue_id' => $data['queue_id'] ?? 'unknown',
                    'message_id' => $result['message_id'] ?? ''
                ]);
                return true;
            } else {
                Log::error("Sinch Queue: SMS send failed", [
                    'queue_id' => $data['queue_id'] ?? 'unknown',
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                return false;
            }

        } catch (Exception $e) {
            Log::error("Sinch Queue: Failed to process SMS", [
                'queue_id' => $data['queue_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Generate unique queue ID
     */
    private function generateQueueId(): string
    {
        return 'sinch_' . uniqid() . '_' . Carbon::now()->format('YmdHis');
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (substr($number, 0, 1) === '0') {
            $number = '44' . substr($number, 1);
        }

        return $number;
    }

    /**
     * Get queue statistics (simplified without sms_queue)
     */
    public function getQueueStats(): array
    {
        return [
            'pending' => DB::table('smsg_log')
                ->where('suppliername', 'Sinch SMPP')
                ->where('sentstatus', 'pending')
                ->count(),
            'sent' => DB::table('smsg_log')
                ->where('suppliername', 'Sinch SMPP')
                ->where('sentstatus', 'ok')
                ->whereDate('created_at', Carbon::today())
                ->count(),
            'failed' => DB::table('smsg_log')
                ->where('suppliername', 'Sinch SMPP')
                ->where('sentstatus', 'fail')
                ->whereDate('created_at', Carbon::today())
                ->count()
        ];
    }
}
