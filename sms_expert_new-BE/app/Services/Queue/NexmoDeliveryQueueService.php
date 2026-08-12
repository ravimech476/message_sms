<?php

namespace App\Services\Queue;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class NexmoDeliveryQueueService
{
    private $rabbitMQService;
    private $queueName = 'nexmo.delivery.reports';

    /**
     * Status mapping from Nexmo to OLD SYSTEM format
     * OLD SYSTEM uses: 'Delivered', 'Non Delivered', 'Lost Notification' (capital letters)
     *
     * @var array
     */
    protected $statusMap = [
        'delivered' => 'Delivered',           // OLD SYSTEM format with capital D
        'expired' => 'Non Delivered',         // OLD SYSTEM format
        'deleted' => 'Non Delivered',         // OLD SYSTEM format
        'undelivered' => 'Non Delivered',     // OLD SYSTEM format
        'accepted' => 'accepted',             // Intermediate status
        'unknown' => 'Lost Notification',     // OLD SYSTEM format
        'rejected' => 'Non Delivered',        // OLD SYSTEM format
        'skipped' => 'Non Delivered',         // OLD SYSTEM format
        'failed' => 'Non Delivered',          // OLD SYSTEM format
        'buffered' => 'buffered',             // Intermediate status
    ];

    public function __construct(RabbitMQService $rabbitMQService)
    {
        $this->rabbitMQService = $rabbitMQService;
        $this->queueName = env('RABBITMQ_NEXMO_DELIVERY_QUEUE', 'nexmo.delivery.reports');
    }

    /**
     * Queue a single delivery report record for processing
     *
     * @param array $record The delivery report record from Nexmo API
     * @return bool
     */
    public function queueDeliveryReport(array $record): bool
    {
        try {
            $messageId = $record['message_id'] ?? null;
            
            if (!$messageId) {
                Log::warning('NexmoDeliveryQueueService: Skipping record with no message_id', [
                    'record' => $record
                ]);
                return false;
            }

            $data = [
                'queue_id' => 'nexmo_dlr_' . $messageId . '_' . time(),
                'message_id' => $messageId,
                'status' => $record['status'] ?? 'unknown',
                'total_price' => $record['total_price'] ?? 0,
                'date_finalized' => $record['date_finalized'] ?? null,
                'record' => $record,
                'queued_at' => Carbon::now()->toIso8601String(),
            ];

            $result = $this->rabbitMQService->publishToQueue(
                $this->queueName,
                $data,
                5 // priority
            );

            if ($result) {
                Log::info('NexmoDeliveryQueueService: Record queued for processing', [
                    'message_id' => $messageId,
                    'queue' => $this->queueName
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error('NexmoDeliveryQueueService: Failed to queue delivery report', [
                'message_id' => $record['message_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Queue multiple delivery report records for processing
     *
     * @param array $records Array of delivery report records
     * @return array ['queued' => int, 'failed' => int]
     */
    public function queueBatchDeliveryReports(array $records): array
    {
        $queued = 0;
        $failed = 0;

        foreach ($records as $record) {
            if ($this->queueDeliveryReport($record)) {
                $queued++;
            } else {
                $failed++;
            }
        }

        Log::info('NexmoDeliveryQueueService: Batch queued', [
            'queued' => $queued,
            'failed' => $failed,
            'total' => count($records)
        ]);

        return ['queued' => $queued, 'failed' => $failed];
    }

    /**
     * Process a delivery report from the queue
     *
     * @param array $data The message data from the queue
     * @return bool
     */
    public function processDeliveryReport(array $data): bool
    {
        try {
            $record = $data['record'] ?? $data;
            $messageId = $data['message_id'] ?? $record['message_id'] ?? null;
            $status = strtolower($data['status'] ?? $record['status'] ?? 'unknown');
            $totalPrice = $data['total_price'] ?? $record['total_price'] ?? 0;
            $dateFinalized = $data['date_finalized'] ?? $record['date_finalized'] ?? null;
            $mobileNumber = $data['to'] ?? $record['to'] ?? $data['mobile_number'] ?? $record['mobile_number'] ?? '';

            if (!$messageId) {
                Log::warning('NexmoDeliveryQueueService: Cannot process record with no message_id');
                return true; // Return true to acknowledge and remove from queue
            }

            // Idempotency gate. The fetch cron (nexmo:fetch-delivery-reports) re-queries an
            // overlapping window every run, so the SAME DLR arrives repeatedly. We must skip a
            // row that's ALREADY finalised (deliverytime2 set) to avoid duplicate
            // delivery_receipt_push_log rows / double webhook pushes.
            //
            // IMPORTANT: match the row using the SAME id columns the canonical processor uses
            // (deliveryreceipt1 = hex, onesixty_suppliermsgref = decimal, suppliermsgref), and
            // also the decimal<->hex conversion. The previous version keyed ONLY on
            // deliveryreceipt1, so when Vonage's report id matched via the decimal column it
            // found nothing, logged "already finalised or row not found", and SILENTLY DROPPED
            // the DLR — that's the regression that stopped statuses updating. We now only skip
            // when we positively find the row AND it is already finalised; otherwise we let
            // DeliveryStatusService (which has the full matching) handle it.
            $hexFromDecimal = null;
            if (ctype_digit((string) $messageId)) {
                $hexFromDecimal = (strlen((string) $messageId) > 15 && function_exists('gmp_strval'))
                    ? gmp_strval(gmp_init((string) $messageId, 10), 16)
                    : dechex((int) $messageId);
            }

            // Match ONLY by the INDEXED onesixty_suppliermsgref (holds the exact hex message_id).
            // The old OR across deliveryreceipt1 / suppliermsgref forced a full-table scan.
            $existingRow = DB::table('smsg_log')
                ->where('onesixty_suppliermsgref', $messageId)
                ->first();

            if ($existingRow && !empty($existingRow->deliverytime2)) {
                Log::info('NexmoDeliveryQueueService: DLR already finalised, skipping', [
                    'message_id' => $messageId,
                ]);
                return true;
            }

            // Provider's "done date" in GMT/UTC, 12-digit (OLD SYSTEM parity).
            $deliveryTime = Carbon::now('Europe/London')->format('YmdHi');
            if ($dateFinalized) {
                try {
                    $deliveryTime = Carbon::parse($dateFinalized)->setTimezone('UTC')->format('YmdHi');
                } catch (Exception $e) {
                    Log::warning("NexmoDeliveryQueueService: Failed to parse date: {$dateFinalized}");
                }
            }

            // Route through the SAME canonical processor the SMPP DLR path uses, so this cron
            // path behaves identically: it updates deliverystatus2 + upstream_errormessage +
            // deliverytime2 (GMT) + wallet handling, AND queues the customer DLR webhook push
            // via RabbitMQ (dlr.callback.push). mapVonageStatus() turns the Nexmo word status
            // into the SMPP code + error_code that drives getUpstreamErrorMessage().
            $service = app(\App\Services\DeliveryStatusService::class);
            $mapped = $service->mapVonageStatus($status); // ['status' => SMPP code, 'error_code' => N]

            $dlrPayload = [
                'message_id'      => $messageId,
                'mobile_number'   => $mobileNumber,
                'status'          => $mapped['status'],
                'error_code'      => $mapped['error_code'],
                'done_date'       => $deliveryTime,
                'provider'        => 'nexmo',
                'aggregator_code' => $mapped['error_code'],
                'aggregator_msg'  => $this->statusMap[$status] ?? 'Unknown',
                'retry'           => '0',
                'charge'          => $totalPrice,
                'raw_data'        => $record,
            ];

            $ok = $service->processDeliveryReceipt($dlrPayload);

            if ($ok) {
                Log::info('NexmoDeliveryQueueService: DLR processed via DeliveryStatusService', [
                    'message_id' => $messageId,
                    'status'     => $mapped['status'],
                ]);
            } else {
                // Fallback for edge cases where the service could not match the row.
                $this->updateDatabase($messageId, $this->statusMap[$status] ?? 'Unknown', $deliveryTime, $totalPrice, $mapped['error_code']);
            }

            return true; // Return true to acknowledge message

        } catch (Exception $e) {
            Log::error('NexmoDeliveryQueueService: Error processing record', [
                'message_id' => $data['message_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return false to trigger retry
            return false;
        }
    }

    /**
     * Update database record
     *
     * @param string $messageId
     * @param string $deliveryStatus
     * @param string|null $deliveryTime
     * @param float $totalPrice
     * @return bool
     */
    protected function updateDatabase($messageId, $deliveryStatus, $deliveryTime, $totalPrice, $errorCode = 6): bool
    {
        try {
            // Find record where deliveryreceipt1 matches message_id
            // and deliverytime2 is null or empty
            $record = DB::table('smsg_log')
                ->where('onesixty_suppliermsgref', $messageId)
                ->where('migration_flag', 'new')
                ->where(function ($query) {
                    $query->whereNull('deliverytime2')
                          ->orWhere('deliverytime2', '');
                })
                ->first();

            if (!$record) {
                return false;
            }

            // upstream_errormessage — human-readable reason, same mapping as the SMPP DLR path
            // (DeliveryStatusService::getUpstreamErrorMessage). Was previously NOT written here.
            $upstreamErrorMessage = app(\App\Services\DeliveryStatusService::class)->getUpstreamErrorMessage($errorCode);

            // OLD SYSTEM parity: deliverytime2 = DLR RECEIVE moment in UK-local, NOT the carrier's
            // done_date (Vonage sends it in the destination tz — e.g. India = IST). Immune to
            // per-carrier timezones; the Sent-SMS page adds +1 min. See
            // DeliveryStatusService::prepareUpdateData.
            $finalDeliveryTime = Carbon::now('Europe/London')->format('YmdHi');

            // Update the record
            $affected = DB::table('smsg_log')
                ->where('onesixty_suppliermsgref', $messageId)
                ->where('migration_flag', 'new')
                ->where(function ($query) {
                    $query->whereNull('deliverytime2')
                          ->orWhere('deliverytime2', '');
                })
                ->update([
                    'deliverystatus2' => $deliveryStatus,
                    'deliveryreceipt2' => $messageId,
                    'deliverytime2' => $finalDeliveryTime,        // GMT/UTC; display converts +1h BST
                    'upstream_errormessage' => $upstreamErrorMessage,
                    'delivery_reason' => $errorCode,
                ]);

            // Insert into delivery_receipt_push_log + RabbitMQ push if customer has DLR configured
            if ($affected > 0) {
                $this->processDlrPushCallback($record, $deliveryStatus, $messageId, $finalDeliveryTime, $errorCode);
            }

            return $affected > 0;

        } catch (Exception $e) {
            Log::error('NexmoDeliveryQueueService: Database update error', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process DLR push callback to customer URL
     * Matches OLD SYSTEM delivery_receipt_push_log insertion
     *
     * @param object $smsgLog The smsg_log record
     * @param string $deliveryStatus Mapped delivery status
     * @param string $deliveryReceipt The message ID / delivery receipt
     * @param string|null $deliveryTime Delivery time in YmdHi format
     */
    protected function processDlrPushCallback($smsgLog, string $deliveryStatus, string $deliveryReceipt, ?string $deliveryTime, $errorCode = ''): void
    {
        try {
            // Get user options for DLR push (retry/daemon settings only) — cached per account (Phase 2).
            $userOptions = app(\App\Services\TableCache::class)->useroption($smsgLog->userref);

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url (resolved at send from the request param or the
            // useroption default), NOT useroption.dreceipt_push_url. Retry/daemon come from useroption.
            $dreceiptUrl = $smsgLog->dreceipt_url ?? '';

            // Check if DLR push is configured
            if (!$userOptions ||
                strlen($dreceiptUrl) <= 10 ||
                ($userOptions->dreceipt_tries_num ?? 0) <= 0 ||
                intval($userOptions->dreceipt_retries_wait_mins ?? -1) < 0) {
                return;
            }

            $time = Carbon::now()->format('YmdHis');

            // Create XML for delivery receipt (OLD SYSTEM format v1.1)
            $itaggReceiptXml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<itagg_delivery_receipt>
    <version>1.1</version>
    <msisdn>{$smsgLog->mobnum}</msisdn>
    <submission_ref>{$smsgLog->bigid}</submission_ref>
    <status>{$deliveryStatus}</status>
    <reason>{$errorCode}</reason>
    <gmt_timestamp>{$time}</gmt_timestamp>
    <retry>0</retry>
</itagg_delivery_receipt>";

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:312): plain INSERT of the push
            // row — no dedup SELECT and no unique constraint. OLD filters duplicate DLRs upstream
            // (transaction-id cache) and inserts directly, so the per-DLR SELECT EXISTS(...) guard
            // (a full table scan / slow query from this consumer) is removed.
            $pushLogId = DB::table('delivery_receipt_push_log')->insertGetId([
                'thismsgreference' => $deliveryReceipt,
                'msisdn' => $smsgLog->mobnum,
                'smsg_log_bigid' => $smsgLog->bigid,
                'users_bigid' => $smsgLog->userref,
                'timestamp' => $time,
                'status' => 'new',
                'message_status' => $deliveryStatus,
                'reason' => $errorCode,
                'url' => $dreceiptUrl,
                'inserted_time' => Carbon::now()->format('YmdHis'),
                'retries_left' => $userOptions->dreceipt_tries_num,
                'wait_minutes' => $userOptions->dreceipt_retries_wait_mins,
                'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                'xml' => $itaggReceiptXml,
                'dlr_daemon_id' => $userOptions->dlr_daemon_id ?? 'default',
                'apitype' => $userOptions->apitype ?? 'w'
            ]);

            Log::info('NexmoDeliveryQueueService: DLR push callback row inserted', [
                'row_id' => $pushLogId,
                'bigid' => $smsgLog->bigid,
                'url' => $dreceiptUrl
            ]);

            // Event-driven push — publish to RabbitMQ so the dlr-callback consumer POSTs to the
            // customer URL within seconds (same as the SMPP DLR path). Non-fatal: if publish
            // fails the row stays status='new' for the cron fallback.
            try {
                $queue = env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push');
                $this->rabbitMQService->publishToQueue($queue, [
                    'row_id'    => $pushLogId,
                    'bigid'     => $smsgLog->bigid,
                    'msisdn'    => $smsgLog->mobnum,
                    'queued_at' => Carbon::now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('NexmoDeliveryQueueService: DLR push queue publish failed (row will retry via cron if enabled)', [
                    'row_id' => $pushLogId,
                    'error'  => $e->getMessage(),
                ]);
            }

        } catch (Exception $e) {
            // Log error but don't throw - DLR push is secondary to main status update
            Log::error('NexmoDeliveryQueueService: Failed to insert delivery_receipt_push_log', [
                'bigid' => $smsgLog->bigid ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get queue statistics
     *
     * @return array
     */
    public function getQueueStats(): array
    {
        return $this->rabbitMQService->getQueueStats($this->queueName);
    }

    /**
     * Peek at messages in the queue without consuming them
     *
     * @param int $count
     * @return array
     */
    public function peekMessages(int $count = 10): array
    {
        return $this->rabbitMQService->peekMessages($this->queueName, $count);
    }
}
