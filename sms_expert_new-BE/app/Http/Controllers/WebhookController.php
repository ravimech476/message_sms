<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Vonage\SMS\Webhook\Factory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Mail\WebhookDataMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;


class WebhookController extends Controller
{
    public $statusDetails = [
        [
            "code" => 0,
            'nexmo_status' => 'delivered',
            "meaning" => "Delivered",
            "description" => "Delivered to mobile device (reason code 4)"
        ],
        [
            "code" => 1,
            'nexmo_status' => 'delivered',
            "meaning" => "Unknown",
            "description" => "Buffered: Phone related (reason code 1)"
        ],
        [
            "code" => 2,
            'nexmo_status' => 'delivered',
            "meaning" => "Absent Subscriber - Temporary",
            "description" => "Buffered: Deliverer Related: Message Within Operator (reason code 2)"
        ],
        [
            "code" => 3,
            'nexmo_status' => 'delivered',
            "meaning" => "Absent Subscriber - Permanent",
            "description" => "Operator Network Failure (reason code 25)"
        ]
    ];

    /**
     * Sinch delivery status mapping - OLD SYSTEM format
     * Uses capital letters: 'Delivered', 'Non Delivered', etc.
     */
    private $sinchStatusMap = [
        'DELIVERED' => 'Delivered',
        'DISPATCHED' => 'acked',           // Intermediate status
        'ABORTED' => 'Non Delivered',      // OLD SYSTEM: failure = Non Delivered
        'REJECTED' => 'Non Delivered',     // OLD SYSTEM: rejected = Non Delivered
        'CANCELLED' => 'Non Delivered',    // OLD SYSTEM: cancelled = Non Delivered
        'DELETED' => 'Non Delivered',      // OLD SYSTEM: deleted = Non Delivered
        'EXPIRED' => 'Non Delivered',      // OLD SYSTEM: expired = Non Delivered
        'UNKNOWN' => 'Unknown',
        'FAILED' => 'Non Delivered',       // OLD SYSTEM: failed = Non Delivered
    ];

    /**
     * Delivery receipt webhook - handles both Nexmo and Sinch formats
     */
    public function deliveryReceipt(Request $request): JsonResponse
    {
        try {
            Log::info('DLR Webhook received:', $request->all());

            // Detect provider based on request format
            $provider = $this->detectDlrProvider($request);

            Log::info("DLR Provider detected: {$provider}");

            if ($provider === 'sinch') {
                return $this->processSinchDlr($request);
            } else {
                return $this->processNexmoDlr($request);
            }

        } catch (\Exception $e) {
            Log::error('Error processing DLR webhook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => 'Internal server error', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Detect DLR provider from request
     */
    private function detectDlrProvider(Request $request): string
    {
        // Sinch format: has 'type' field with 'recipient_delivery_report_sms' or 'batch_id'
        if ($request->has('type') && str_contains($request->input('type'), 'delivery_report')) {
            return 'sinch';
        }

        // Sinch format: has 'batch_id' field
        if ($request->has('batch_id')) {
            return 'sinch';
        }

        // Sinch format: status is uppercase (DELIVERED, FAILED, etc.)
        if ($request->has('status') && $request->has('recipient')) {
            $status = $request->input('status');
            if (strtoupper($status) === $status && in_array($status, array_keys($this->sinchStatusMap))) {
                return 'sinch';
            }
        }

        // Default to Nexmo
        return 'nexmo';
    }

    /**
     * Process Sinch DLR
     * Sinch format: {"id":"xxx","status":"DELIVERED","type":"recipient_delivery_report_sms","at":"2022-01-01T00:00:00.000Z","recipient":"+447xxx","code":0,"batch_id":"xxx","client_reference":"xxx"}
     */
    private function processSinchDlr(Request $request): JsonResponse
    {
        Log::info('Processing Sinch DLR', $request->all());

        // Get message ID from Sinch - could be 'id' or from the batch
        $messageId = $request->input('id') ?? $request->input('batch_id');
        $status = $request->input('status', 'UNKNOWN');
        $recipient = ltrim($request->input('recipient', ''), '+');
        $errorCode = $request->input('code', 0);
        $clientReference = $request->input('client_reference');
        $timestamp = $request->input('at');

        if (empty($messageId)) {
            Log::warning('Sinch DLR missing message ID');
            return response()->json(['status' => 'error', 'message' => 'Message ID required'], 400);
        }

        // Map Sinch status to internal format
        $mappedStatus = $this->sinchStatusMap[$status] ?? 'Unknown';

        // Find the SMS log record - ONLY NEW SYSTEM records (migration_flag = 'new')
        // This ensures OLD SYSTEM daemon handles its own records
        // First try by suppliermsgref (the message ID from Sinch)
        $smsgLog = DB::table('smsg_log')
            ->where('suppliermsgref', $messageId)
            ->where('migration_flag', 'new')  // Only NEW system records
            ->orderBy('id', 'DESC')
            ->first();

        // If not found, try by deliveryreceipt1
        if (!$smsgLog) {
            $smsgLog = DB::table('smsg_log')
                ->where('deliveryreceipt1', $messageId)
                ->where('migration_flag', 'new')  // Only NEW system records
                ->orderBy('id', 'DESC')
                ->first();
        }

        // Also check smpp_message_mapping table
        if (!$smsgLog) {
            $mapping = DB::table('smpp_message_mapping')
                ->where('message_id', $messageId)
                ->where('provider', 'sinch')
                ->first();

            if ($mapping) {
                $smsgLog = DB::table('smsg_log')
                    ->where('bigid', $mapping->bigid)
                    ->where('migration_flag', 'new')  // Only NEW system records
                    ->first();
            }
        }

        if (!$smsgLog) {
            Log::warning("Sinch DLR: No matching record found for messageId: $messageId");
            return response()->json(['status' => 'error', 'message' => 'Record not found'], 404);
        }

        // Parse timestamp
        // deliverytime2 = UK local time (Europe/London) the DLR was RECEIVED.
        // OLD SYSTEM parity: deliverytime2 = DLR RECEIVE moment in UK-local (never the carrier's
        // done_date, which Vonage sends in the destination tz — e.g. India = IST). The Sent-SMS
        // page shows it +1 min. See DeliveryStatusService::prepareUpdateData.
        $formattedDeliveryTime = Carbon::now('Europe/London')->format('YmdHi');

        // Build update data
        $updateData = [
            'deliverystatus2' => $mappedStatus,
            'deliverytime2' => $formattedDeliveryTime,
            'deliveryreceipt2' => $messageId,
            'delivery_reason' => $errorCode,
            'aggregator_dlrmsg' => $status,
        ];

        // Update status text based on delivery status
        if ($mappedStatus === 'Delivered') {
            $updateData['sentstatustext'] = 'Delivered via Sinch';
            $updateData['sentstatus'] = 'ok';
            $updateData['upstream_errormessage'] = 'Delivered to mobile device';
        } elseif (in_array($mappedStatus, ['Failed', 'Rejected', 'Expired', 'Aborted'])) {
            $updateData['sentstatustext'] = 'Delivery failed: ' . $status;
            $updateData['sentstatus'] = 'fail';
            $updateData['upstream_errormessage'] = 'Delivery failed: ' . $status;
        }

        // Update the record
        $updated = DB::table('smsg_log')
            ->where('id', $smsgLog->id)
            ->update($updateData);

        if ($updated) {
            Log::info("Sinch DLR: Successfully updated record ID: {$smsgLog->id}", [
                'status' => $mappedStatus,
                'message_id' => $messageId
            ]);

            // Handle DLR push callback if configured
            $this->handleDlrPushCallback($smsgLog, $mappedStatus, $messageId);

            return response()->json(['status' => 'success', 'message' => 'Record updated']);
        } else {
            Log::warning("Sinch DLR: Update skipped — no data changed for ID: {$smsgLog->id}");
            return response()->json(['status' => 'warning', 'message' => 'No changes made'], 200);
        }
    }

    /**
     * Process Nexmo/Vonage DLR
     */
    private function processNexmoDlr(Request $request): JsonResponse
    {
        if (!$request->has('messageId')) {
            Log::warning('Nexmo DLR missing messageId.');
            return response()->json(['status' => 'error', 'message' => 'messageId is required'], 400);
        }

        $messageId = trim($request->input('messageId'));
        $errorCode = $request->input('err-code');

        Log::info("DLR Webhook: Searching for messageId = $messageId (NEW system only)");

        // Try multiple lookup methods for Vonage DLR
        // Vonage may send message_id in different formats (hex or decimal)
        $smsgLog = null;

        // 1. Try onesixty_suppliermsgref first (decimal format stored during send)
        $smsgLog = DB::table('smsg_log')
            ->where('onesixty_suppliermsgref', $messageId)
            ->where('migration_flag', 'new')
            ->orderBy('id', 'DESC')
            ->first();

        if ($smsgLog) {
            Log::info("DLR Webhook: Found record by onesixty_suppliermsgref", ['messageId' => $messageId]);
        }

        // 2. deliveryreceipt1 fallback REMOVED — un-indexed (full scan). The onesixty_suppliermsgref
        // match above (same hex id, indexed) is the fast single-seek path.

        // 3. If message_id is decimal, convert to hex and try
        if (!$smsgLog && ctype_digit($messageId)) {
            // For large decimal numbers, use GMP if available
            if (strlen($messageId) > 15 && function_exists('gmp_strval')) {
                $hexMessageId = gmp_strval(gmp_init($messageId, 10), 16);
            } else {
                $hexMessageId = dechex((int)$messageId);
            }

            Log::debug("DLR Webhook: Trying decimal to hex conversion", [
                'decimal' => $messageId,
                'hex' => $hexMessageId
            ]);

            $smsgLog = DB::table('smsg_log')
                ->where('deliveryreceipt1', $hexMessageId)
                ->where('migration_flag', 'new')
                ->orderBy('id', 'DESC')
                ->first();

            if ($smsgLog) {
                Log::info("DLR Webhook: Found record by hex conversion", ['hex' => $hexMessageId]);
            }
        }

        // 4. Try smpp_message_mapping table
        if (!$smsgLog) {
            $mapping = DB::table('smpp_message_mapping')
                ->where('message_id', $messageId)
                ->first();

            // Also try with hex conversion if message_id is decimal
            if (!$mapping && ctype_digit($messageId)) {
                if (strlen($messageId) > 15 && function_exists('gmp_strval')) {
                    $hexMessageId = gmp_strval(gmp_init($messageId, 10), 16);
                } else {
                    $hexMessageId = dechex((int)$messageId);
                }
                $mapping = DB::table('smpp_message_mapping')
                    ->where('message_id', $hexMessageId)
                    ->first();
            }

            if ($mapping) {
                $smsgLog = DB::table('smsg_log')
                    ->where('bigid', $mapping->bigid)
                    ->where('migration_flag', 'new')
                    ->first();

                if ($smsgLog) {
                    Log::info("DLR Webhook: Found record via smpp_message_mapping", [
                        'messageId' => $messageId,
                        'bigid' => $mapping->bigid
                    ]);
                }
            }
        }

        if (!$smsgLog) {
            Log::warning("DLR Webhook: No matching record found for messageId: $messageId");
            return response()->json(['status' => 'error', 'message' => 'Record not found'], 404);
        }

        $deliveryTimestamp = $request['message-timestamp'];
        // deliverytime2 = UK local time (Europe/London) the DLR was RECEIVED.
        // OLD SYSTEM parity: deliverytime2 = DLR RECEIVE moment in UK-local (never the carrier's
        // done_date, which Vonage sends in the destination tz — e.g. India = IST). The Sent-SMS
        // page shows it +1 min. See DeliveryStatusService::prepareUpdateData.
        $formattedDeliveryTime = Carbon::now('Europe/London')->format('YmdHi');

        // Map Nexmo status to OLD SYSTEM format (capital letters)
        $nexmoStatus = strtolower($request['status']);
        $nexmoStatusMap = [
            'delivered' => 'Delivered',
            'expired' => 'Non Delivered',
            'failed' => 'Non Delivered',
            'rejected' => 'Non Delivered',
            'accepted' => 'acked',
            'buffered' => 'buffered smsc',
            'unknown' => 'Unknown',
        ];
        $mappedStatus = $nexmoStatusMap[$nexmoStatus] ?? 'Unknown';

        $updateData = [
            'deliverystatus2'         => $mappedStatus,  // OLD SYSTEM format
            'deliverytime2'           => $formattedDeliveryTime,
            'deliveryreceipt2'        => $messageId,
            'upstream_errormessage'   => 'Delivered to mobile device (reason code 4)',
            'delivery_reason'         => $request['err-code'],
            'onesixty_suppliermsgref' => $request['messageId'],
            'aggregator_dlrmsg'       => $request['status'],  // Keep original for reference
        ];

        // Only add cost/user/profit when delivered
        if ($mappedStatus === 'Delivered') {
            // Get the cost price from request (Nexmo sends it)
            $costPrice = (float) $request->input('price', 0);

            // Get user pricing
            $userPrice = $smsgLog->userprice ?? 0;

            // If user price not set, calculate from cost + margin
            if ($userPrice <= 0 && $costPrice > 0) {
                $user = DB::table('users')->where('bigid', $smsgLog->userref)->first();
                if ($user) {
                    $userMargin = DB::table('user_margin')
                        ->where('user_id', $user->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($userMargin) {
                        $marginAmount = $costPrice * ($userMargin->margin_percentage / 100);
                        $userPrice = round($costPrice + $marginAmount, 4);
                    } else {
                        $userPrice = round($costPrice * 1.25, 4); // Default 25% markup
                    }
                }
            }

            $updateData['costprice'] = $costPrice;
            if ($userPrice > 0) {
                $updateData['userprice'] = $userPrice;
                $updateData['profit'] = $userPrice - $costPrice;
            }
            $updateData['sentstatustext'] = 'SMS Sent';
        } else {
            $updateData['sentstatustext'] = $request['status'];
        }

        $updated = DB::table('smsg_log')
            ->where('id', $smsgLog->id)
            ->update($updateData);

        if ($updated) {
            Log::info("Successfully updated record ID: {$smsgLog->id}");

            // Handle DLR push callback if configured
            $this->handleDlrPushCallback($smsgLog, $request['status'], $messageId);

            return response()->json(['status' => 'success', 'message' => 'Record updated']);
        } else {
            Log::warning("Update skipped — no data changed for ID: {$smsgLog->id}");
            return response()->json(['status' => 'warning', 'message' => 'No changes made'], 200);
        }
    }

    /**
     * Handle DLR push callback to customer URL
     */
    private function handleDlrPushCallback($smsgLog, string $status, string $messageId): void
    {
        try {
            $dreceiptUrlDetails = app(\App\Services\TableCache::class)->useroption($smsgLog->userref); // cached per account (Phase 2)

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url, NOT useroption.dreceipt_push_url. Retry/daemon from useroption.
            $dreceiptUrl = $smsgLog->dreceipt_url ?? '';

            if (
                $dreceiptUrlDetails &&
                strlen($dreceiptUrl) > 10 &&
                $dreceiptUrlDetails->dreceipt_tries_num > 0
            ) {
                $time = Carbon::now()->format('YmdHis');
                $deliveryReceiptRef = $messageId ?? $smsgLog->deliveryreceipt1 ?? '';

                // Map status to standard format
                $dlrStatus = strtolower($status);
                if (in_array($dlrStatus, ['delivered', 'ok'])) {
                    $dlrStatus = 'delivered';
                } elseif (in_array($dlrStatus, ['failed', 'rejected', 'expired', 'aborted'])) {
                    $dlrStatus = 'failed';
                }

                $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                <itagg_delivery_receipt>
                    <version>1.1</version>
                    <msisdn>{$smsgLog->mobnum}</msisdn>
                    <submission_ref>{$deliveryReceiptRef}</submission_ref>
                    <status>{$dlrStatus}</status>
                    <reason>4</reason>
                    <gmt_timestamp>{$time}</gmt_timestamp>
                    <retry>0</retry>
                </itagg_delivery_receipt>";

                DB::table('delivery_receipt_push_log')->insert([
                    'thismsgreference' => $deliveryReceiptRef,
                    'msisdn' => $smsgLog->mobnum,
                    'smsg_log_bigid' => $smsgLog->bigid,
                    'users_bigid' => $smsgLog->userref,
                    'timestamp' => $time,
                    'status' => 'new',
                    'message_status' => $dlrStatus,
                    'reason' => '4',
                    'url' => $dreceiptUrl,
                    'inserted_time' => $time,
                    'retries_left' => $dreceiptUrlDetails->dreceipt_tries_num,
                    'wait_minutes' => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                    'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'xml' => $itagg_receipt_xml,
                    'dlr_daemon_id' => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                    'apitype' => $dreceiptUrlDetails->apitype ?? 'w'
                ]);

                Log::info("DLR push callback queued", [
                    'bigid' => $smsgLog->bigid,
                    'url' => $dreceiptUrl
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("DLR push callback failed: " . $e->getMessage());
        }
    }

    /**
     * Dedicated Sinch DLR endpoint
     */
    public function sinchDeliveryReceipt(Request $request): JsonResponse
    {
        Log::info('Sinch DLR endpoint called', $request->all());
        return $this->processSinchDlr($request);
    }
}
