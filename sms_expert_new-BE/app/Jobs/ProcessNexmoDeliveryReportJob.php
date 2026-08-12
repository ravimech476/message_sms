<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DeliveryStatusService;
use App\Services\SmppLogger;
use Carbon\Carbon;

class ProcessNexmoDeliveryReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The record data from Nexmo API
     *
     * @var array
     */
    protected $record;

    /**
     * Status mapping from Nexmo to OLD SYSTEM delivery status
     * This matches the OLD SYSTEM getXmlStatusForReasonCode logic
     *
     * @var array
     */
    protected $statusMap = [
        'delivered' => 'Delivered',
        'expired' => 'Non Delivered',    // OLD SYSTEM: expired = Non Delivered
        'deleted' => 'Non Delivered',     // OLD SYSTEM: deleted = Non Delivered
        'undelivered' => 'Non Delivered',
        'accepted' => 'acked',            // OLD SYSTEM: accepted = acked
        'unknown' => 'Unknown',
        'rejected' => 'Non Delivered',    // OLD SYSTEM: rejected = Non Delivered
        'skipped' => 'Non Delivered',
        'failed' => 'Non Delivered',
        'buffered' => 'buffered smsc',    // OLD SYSTEM buffered status
    ];

    /**
     * Error code mapping from Nexmo status to OLD SYSTEM reason codes
     *
     * @var array
     */
    protected $errorCodeMap = [
        'delivered' => 4,    // Delivered to mobile device
        'expired' => 8,      // Message expired
        'deleted' => 20,     // Permanent Operator error
        'undelivered' => 5,  // Failed, no further info
        'accepted' => 3,     // Delivered to network (acked)
        'unknown' => 6,      // Final status unknown
        'rejected' => 27,    // Barred by User
        'skipped' => 20,     // Permanent failure
        'failed' => 5,       // Failed
        'buffered' => 2,     // Buffered at SMSC
    ];

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * Create a new job instance.
     *
     * @param array $record The delivery report record from Nexmo API
     */
    public function __construct(array $record)
    {
        $this->record = $record;
        
        // Set the queue for RabbitMQ
        $this->onQueue('nexmo_delivery_reports');
    }

    /**
     * Execute the job (uses DeliveryStatusService for OLD SYSTEM compatible processing)
     */
    public function handle(): void
    {
        // Use provider-specific logger (logs to logs/{date}/smpp/nexmo.log)
        $logger = SmppLogger::nexmo();

        try {
            $messageId = $this->record['message_id'] ?? null;
            $status = strtolower($this->record['status'] ?? 'unknown');
            $totalPrice = $this->record['total_price'] ?? 0;
            $dateFinalized = $this->record['date_finalized'] ?? null;
            $mobileNumber = $this->record['to'] ?? $this->record['mobile_number'] ?? '';

            if (!$messageId) {
                $logger->warning('Skipping record with no message_id', [
                    'record' => $this->record
                ]);
                return;
            }

            // Map status to OLD SYSTEM format
            $deliveryStatus = $this->statusMap[$status] ?? 'Unknown';
            $errorCode = $this->errorCodeMap[$status] ?? 6;

            // Format delivery time to YmdHis format
            // OLD SYSTEM: deliverytime2 is stored as 12-digit YYYYMMDDHHMM in GMT (no seconds).
            $deliveryTime = Carbon::now('Europe/London')->format('YmdHi');
            if ($dateFinalized) {
                try {
                    $deliveryTime = Carbon::parse($dateFinalized)->setTimezone('UTC')->format('YmdHi');
                } catch (\Exception $e) {
                    $logger->warning("Failed to parse date: {$dateFinalized}");
                }
            }

            // Use DeliveryStatusService for OLD SYSTEM compatible processing
            $deliveryStatusService = app(DeliveryStatusService::class);

            $dlrPayload = [
                'message_id' => $messageId,
                'mobile_number' => $mobileNumber,
                'status' => strtoupper($status) === 'DELIVERED' ? 'DELIVRD' : strtoupper($status),
                'error_code' => $errorCode,
                'done_date' => $deliveryTime,
                'provider' => 'nexmo',
                'aggregator_code' => $errorCode,
                'aggregator_msg' => $deliveryStatus,
                'retry' => '0',
                'charge' => $totalPrice,
                'raw_data' => $this->record,
            ];

            $result = $deliveryStatusService->processDeliveryReceipt($dlrPayload);

            if ($result) {
                // Log DLR to provider-specific log and DLR log
                $logger->logDlr($messageId, $deliveryStatus, [
                    'error_code' => $errorCode,
                    'delivery_time' => $deliveryTime,
                    'mobile' => substr($mobileNumber, 0, -4) . '****',
                    'charge' => $totalPrice,
                    'processed_by' => 'DeliveryStatusService',
                ]);
            } else {
                // Fallback to direct database update if record not found via service
                $updated = $this->updateDatabase($messageId, $deliveryStatus, $deliveryTime, $totalPrice, $errorCode);

                if ($updated) {
                    $logger->logDlr($messageId, $deliveryStatus, [
                        'error_code' => $errorCode,
                        'delivery_time' => $deliveryTime,
                        'processed_by' => 'fallback',
                    ]);
                } else {
                    $logger->debug('Record not found or already updated', [
                        'message_id' => $messageId,
                    ]);
                }
            }

        } catch (\Exception $e) {
            $logger->logError('Error processing DLR', null, [
                'message_id' => $this->record['message_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Update database record (fallback method with OLD SYSTEM fields)
     *
     * @param string $messageId
     * @param string $deliveryStatus
     * @param string|null $deliveryTime
     * @param float $totalPrice
     * @param int $errorCode
     * @return bool
     */
    protected function updateDatabase($messageId, $deliveryStatus, $deliveryTime, $totalPrice, $errorCode = 6)
    {
        try {
            // Match by onesixty_suppliermsgref FIRST — it is varchar(36) AND indexed and now
            // holds the exact hex message_id (same as deliveryreceipt1), so this is an indexed
            // seek instead of a full-table scan. deliveryreceipt1 is the legacy-row fallback.
            // ONLY process NEW SYSTEM records - OLD SYSTEM daemon handles its own records.
            $notFinalised = function ($query) {
                $query->whereNull('deliverytime2')->orWhere('deliverytime2', '');
            };

            // Match ONLY by the INDEXED onesixty_suppliermsgref (holds the exact hex message_id) —
            // a single sub-ms seek. The deliveryreceipt1 / suppliermsgref fallbacks were
            // un-indexed full scans and are removed.
            $record = DB::table('smsg_log')
                ->where('onesixty_suppliermsgref', $messageId)
                ->where('migration_flag', 'new')
                ->where($notFinalised)
                ->first();

            if (!$record) {
                return false;
            }

            // Get upstream error message using DeliveryStatusService logic
            $deliveryStatusService = app(DeliveryStatusService::class);
            $upstreamErrorMessage = $deliveryStatusService->getUpstreamErrorMessage($errorCode);

            // Update the record with OLD SYSTEM compatible fields
            $affected = DB::table('smsg_log')
                ->where('id', $record->id)
                ->update([
                    'deliverystatus2' => $deliveryStatus,
                    'deliveryreceipt2' => $messageId,
                    'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
                    'upstream_errormessage' => $upstreamErrorMessage,
                    'delivery_reason' => $errorCode,
                    'aggregator_dlrcode' => $errorCode,
                    'aggregator_dlrmsg' => $deliveryStatus,
                ]);

            return $affected > 0;

        } catch (\Exception $e) {
            $logger = SmppLogger::nexmo();
            $logger->logError('Database update error', null, [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * The job failed to process.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        $logger = SmppLogger::nexmo();
        $logger->logError('Job permanently failed', null, [
            'message_id' => $this->record['message_id'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'record' => $this->record,
        ]);
    }
}
