<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Delivery Status Service
 *
 * Handles delivery receipt processing matching OLD SYSTEM logic exactly.
 *
 * OLD SYSTEM Flow:
 * 1. DLR received via SMPP or webhook
 * 2. Inserted into smsg_receipt_buffer
 * 3. daemon_dreceipt_inbound_buffer.php processes buffer
 * 4. Updates smsg_log with deliverystatus1/2, upstream_errormessage
 * 5. Handles wallet refunds for failed deliveries
 * 6. Pushes DLR to customer URL if configured
 */
class DeliveryStatusService
{
    /** Reused RabbitMQ connection for DLR-callback publishing (created once, not per DLR). */
    private $rabbit = null;

    /**
     * Process a delivery receipt and update smsg_log
     * Matches OLD SYSTEM daemon_dreceipt_inbound_buffer.php logic
     *
     * @param array $dlrData DLR data containing:
     *   - message_id: Supplier message reference (suppliermsgref/onesixty_suppliermsgref)
     *   - mobile_number: MSISDN
     *   - status: SMPP status (DELIVRD, UNDELIV, etc.) or reason code
     *   - error_code: Numeric reason code from supplier
     *   - done_date: Timestamp of delivery
     *   - provider: 'nexmo', 'sinch', 'mblox', 'other'
     * @return bool Success status
     */
    public function processDeliveryReceipt(array $dlrData): bool
    {
        try {
            $messageId = $dlrData['message_id'] ?? '';
            $mobileNumber = $dlrData['mobile_number'] ?? '';
            $status = $dlrData['status'] ?? '';
            $errorCode = $dlrData['error_code'] ?? 0;
            $provider = $dlrData['provider'] ?? 'nexmo';

            if (empty($messageId)) {
                Log::warning('DLR missing message_id', $dlrData);
                return false;
            }

            // Find the smsg_log record
            $smsgLog = $this->findSmsgLogRecord($messageId, $mobileNumber);

            if (!$smsgLog) {
                Log::warning('DLR: smsg_log record not found', [
                    'message_id' => $messageId,
                    'mobile' => $mobileNumber
                ]);
                return false;
            }

            // Do NOT reject on migration_flag here. The record was matched by its Vonage
            // message_id (deliveryreceipt1 / onesixty_suppliermsgref) — columns that are only
            // ever populated by NEW-system SMPP sends — so this DLR belongs to the new system
            // regardless of how the row's migration_flag got tagged. The old strict check
            // dropped ~2,000 DLRs/day for new-system sends that were (mis)tagged 'old',
            // leaving deliverystatus2 stuck 'pending' and looping the DLR in the retry queue.
            if (isset($smsgLog->migration_flag) && $smsgLog->migration_flag !== 'new') {
                Log::debug('DLR: processing message_id-matched record despite migration_flag != new', [
                    'bigid' => $smsgLog->bigid,
                    'migration_flag' => $smsgLog->migration_flag,
                ]);
            }

            // Determine statnum (1 = intermediate, 2 = final)
            $statnum = $this->determineStatNum($status, $errorCode);

            // Map status to old system values
            $deliveryStatus = $this->mapToDeliveryStatus($status, $errorCode);

            // Get upstream error message (matching OLD SYSTEM getUpstreamErrormessage function)
            $upstreamErrorMessage = $this->getUpstreamErrorMessage($errorCode);

            // Get retry flag
            $retry = $dlrData['retry'] ?? '0';

            // Prepare update data
            $updateData = $this->prepareUpdateData($statnum, $deliveryStatus, $errorCode, $upstreamErrorMessage, $retry, $dlrData);

            // Begin transaction
            DB::beginTransaction();

            try {
                // Update smsg_log
                $this->updateSmsgLog($smsgLog, $updateData);

                // Handle wallet refund for failed deliveries
                $this->handleWalletRefund($smsgLog, $deliveryStatus, $errorCode);

                // Insert into delivery_receipt_push_log if customer has DLR push configured
                $this->processDlrPushCallback($smsgLog, $deliveryStatus, $errorCode, $updateData);

                // Update master_msisdn for permanent failures (like OLD SYSTEM)
                $this->updateMasterMsisdn($mobileNumber, $errorCode);

                DB::commit();

                Log::info('DLR processed successfully (OLD SYSTEM logic)', [
                    'bigid' => $smsgLog->bigid,
                    'statnum' => $statnum,
                    'status' => $deliveryStatus,
                    'error_code' => $errorCode
                ]);

                return true;

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('DLR processing failed', [
                'error' => $e->getMessage(),
                'data' => $dlrData
            ]);
            return false;
        }
    }

    /**
     * LITE processing — mirrors the OLD daemon (daemon_dreceipt_inbound_buffer.php): match the
     * indexed onesixty_suppliermsgref and do ONE direct UPDATE on smsg_log. NO per-row
     * transaction, NO wallet work, and NO inline RabbitMQ (the push callback is insert-only).
     * This is what the table-based dlr:process-buffer daemon uses so a 500-row batch never
     * stalls or crashes.
     *
     * @return bool true if a row matched and was updated
     */
    public function processDeliveryReceiptLite(array $dlrData): bool
    {
        try {
            $messageId = $dlrData['message_id'] ?? '';
            if (empty($messageId)) {
                return false;
            }

            $smsgLog = $this->findSmsgLogRecord($messageId, $dlrData['mobile_number'] ?? '');
            if (!$smsgLog) {
                return false; // no matching row (archived / unknown) — nothing to update
            }

            $status    = $dlrData['status'] ?? '';
            $errorCode = $dlrData['error_code'] ?? 0;

            $statnum        = $this->determineStatNum($status, $errorCode);
            $deliveryStatus = $this->mapToDeliveryStatus($status, $errorCode);
            $upstreamMsg    = $this->getUpstreamErrorMessage($errorCode);
            $retry          = $dlrData['retry'] ?? '0';

            $updateData = $this->prepareUpdateData($statnum, $deliveryStatus, $errorCode, $upstreamMsg, $retry, $dlrData);

            // ONE UPDATE — exactly like the old daemon. No transaction wrapper.
            $this->updateSmsgLog($smsgLog, $updateData);

            // Permanent-absent (reason 23) -> master_msisdn, like the old system. Cheap conditional.
            $this->updateMasterMsisdn($dlrData['mobile_number'] ?? '', $errorCode);

            // Customer DLR push: insert the push_log row so the push consumer/cron sends it.
            // skipPublish=true -> NEVER touch RabbitMQ here (per-row RabbitMQ was crashing the batch).
            try {
                $this->processDlrPushCallback($smsgLog, $deliveryStatus, $errorCode, $updateData, true);
            } catch (\Throwable $e) {
                Log::warning('DLR lite: push_log insert failed (non-fatal)', ['error' => $e->getMessage()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('DLR lite processing failed', [
                'error'      => $e->getMessage(),
                'message_id' => $dlrData['message_id'] ?? null,
            ]);
            return false;
        }
    }

    /**
     * Find the smsg_log record for a DLR — by the INDEXED onesixty_suppliermsgref column ONLY.
     *
     * onesixty_suppliermsgref is varchar(36) AND indexed, and every send now stores the exact
     * Vonage hex message_id there (same value as deliveryreceipt1). So this is a single sub-ms
     * INDEXED seek. The old fallbacks (deliveryreceipt1, suppliermsgref, hex-convert, and the
     * `mobnum LIKE '%…'`) were UN-indexed full scans of ~2M rows — 13-17s EACH per the slow
     * query log — and ran for every archived/unknown DLR. They are removed. Legacy rows that
     * don't have onesixty_suppliermsgref populated are handled by `dlr:backfill-msgref`.
     */
    private function findSmsgLogRecord(string $messageId, string $mobileNumber)
    {
        if ($messageId === '') {
            return null;
        }

        $record = DB::table('smsg_log')
            ->where('onesixty_suppliermsgref', $messageId)
            ->first();

        if ($record) {
            Log::debug('DLR: Found record by onesixty_suppliermsgref (indexed)', ['message_id' => $messageId]);
            return $record;
        }

        Log::warning('DLR: No record found for message_id', [
            'message_id' => $messageId,
            'mobile'     => $mobileNumber,
        ]);

        return null;
    }

    /**
     * Determine which status column to update (statnum 1 or 2)
     * OLD SYSTEM: statnum 1 for intermediate (acked, buffered), statnum 2 for final
     */
    private function determineStatNum(string $status, $errorCode): int
    {
        $status = strtoupper($status);
        $errorCode = (int) $errorCode;

        // Intermediate statuses go to deliverystatus1
        if (in_array($status, ['ACKED', 'ACCEPTD']) || $errorCode == 3) {
            return 1;
        }

        // Buffered statuses go to deliverystatus1
        if (in_array($status, ['BUFFERED']) || in_array($errorCode, [1, 2, 7])) {
            return 1;
        }

        // Final statuses go to deliverystatus2
        return 2;
    }

    /**
     * Map SMPP/provider status to OLD SYSTEM deliverystatus values
     * Matches getXmlStatusForReasonCode() from old system
     */
    private function mapToDeliveryStatus(string $status, $errorCode): string
    {
        $status = strtoupper($status);
        $errorCode = (int) $errorCode;

        // First check SMPP status codes
        $smppMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Non Delivered',
            'DELETED' => 'Non Delivered',
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'acked',
            'ACKED' => 'acked',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Non Delivered',
            // Vonage sends stat:FAILED ("Message not delivered") — see their DLR status list. It was
            // previously unmapped, so FAILED DLRs fell through to the default 'Unknown' below, which
            // is why the new system showed ~1200 "Unknown"/day (mostly real failures) while the OLD
            // csn3 path — keyed on numeric reason codes only — never produced it. FAILED = not delivered.
            'FAILED'  => 'Non Delivered',
        ];

        if (isset($smppMap[$status])) {
            return $smppMap[$status];
        }

        // Map by reason code (OLD SYSTEM getXmlStatusForReasonCode logic)
        if ($errorCode >= 8) {
            return 'Non Delivered';
        }

        switch ($errorCode) {
            case 1:
                return 'buffered phone';
            case 2:
                return 'buffered smsc';
            case 3:
                return 'acked'; // Delivered to network
            case 4:
                return 'Delivered';
            case 5:
                return 'Lost Notification';
            case 6:
                return 'Unknown';
            case 7:
                return 'Buffered';
        }

        // Genuinely unrecognised DLR state (not a known Vonage status string, error code 0). Log it
        // so we can see what's actually falling through instead of silently bucketing as 'Unknown' —
        // this default is what previously absorbed FAILED and inflated the "Unknown" count.
        \Illuminate\Support\Facades\Log::warning('DLR: unmapped delivery status, defaulting to Unknown', [
            'status'     => $status,
            'error_code' => $errorCode,
        ]);

        return 'Unknown';
    }

    /**
     * Get human-readable error message
     * Matches OLD SYSTEM getUpstreamErrormessage() function exactly
     */
    public function getUpstreamErrorMessage($errorCode): string
    {
        $errorCode = (int) $errorCode;

        if ($errorCode === 0 || $errorCode === '') {
            return '';
        }

        $messages = [
            1 => 'Buffered: Phone related (reason code 1)',
            2 => 'Buffered: Deliverer Related: Message Within Operator (reason code 2)',
            3 => 'Delivered to network (reason code 3)',
            4 => 'Delivered to mobile device (reason code 4)',
            5 => 'Failed, no further information available (reason code 5)',
            6 => 'The final status of the message is unknown (reason code 6)',
            7 => 'Buffered: Credit related - Message may be being retried (reason code 7)',
            8 => 'Message expired within the operator and failure reason is unknown (reason code 8)',
            20 => 'Permanent Operator error (reason code 20)',
            21 => 'Credit related: Message has been retried by operator (reason code 21)',
            22 => 'Credit related: Message has NOT been retried (reason code 22)',
            23 => 'Absent Subscriber Permanent (reason code 23)',
            24 => 'Absent Subscriber Temporary (reason code 24)',
            25 => 'Operator Network Failure (reason code 25)',
            26 => 'Phone Related Error (reason code 26)',
            27 => 'Barred by User / Permanent Phone related error (reason code 27)',
            28 => 'Anti-Spam (reason code 28)',
            29 => 'Content Related (reason code 29)',
            33 => 'Previously had parental lock applied (reason code 33)',
            34 => 'Previously failed to age verify (reason code 34)',
            35 => 'Previously not age verified (reason code 35)',
            36 => 'Temporary communication error with AV platform (reason code 36)',
            37 => 'Failed, no further information available (reason code 37-1s)',
            38 => 'Buffered: Deliverer Related (reason code 38-1s)',
            39 => 'The final status of the message is unknown (reason code 39-1s)',
            40 => 'Failed, no further information available (reason code 40-1s)',
            41 => 'The final status of the message is unknown (reason code 41-1s)',
            42 => 'The final status of the message is unknown (reason code 42-1s)',
        ];

        return $messages[$errorCode] ?? "Unknown reason. (reason code {$errorCode})";
    }

    /**
     * Prepare update data for smsg_log
     */
    private function prepareUpdateData(int $statnum, string $deliveryStatus, $errorCode, string $upstreamErrorMessage, string $retry, array $dlrData): array
    {
        // OLD SYSTEM parity (observed): "Date Finalised" tracks ~the SEND time and is never
        // the carrier's local time. Vonage sends the SMPP done_date in the DESTINATION
        // carrier's timezone (e.g. India = IST "done date:2607221857" = 18:57) with NO tz
        // marker, so storing it raw made foreign deliveries show carrier-local time. OLD's
        // Date Finalised comes out ~send+1min for foreign numbers too, so we anchor the
        // delivery time to the DLR RECEIVE moment in UK-local (the Sent-SMS page then adds
        // the +1 minute seconds-compensation). 12-digit YYYYMMDDHHMM (no seconds), matching
        // the display's CONCAT(deliverytime2,'00'). This is OLD's SCP20140425 approach and is
        // immune to per-carrier done_date timezones (UK, India, anywhere → ~send time).
        // NOTE: for a badly delayed DLR this shows the receive time, not the true delivery
        // moment — the deliberate OLD trade-off (see daemon_dreceipt_inbound_buffer.php:244-245).
        $timestamp = Carbon::now('Europe/London')->format('YmdHi');

        $updateData = [
            'upstream_errormessage' => $upstreamErrorMessage,
            'delivery_reason' => $errorCode,
            'retry' => $retry,
        ];

        // Set aggregator fields
        $updateData['aggregator_dlrcode'] = $dlrData['aggregator_code'] ?? $errorCode;
        $updateData['aggregator_dlrmsg'] = $dlrData['aggregator_msg'] ?? $deliveryStatus;

        // Set delivery status based on statnum
        if ($statnum == 1) {
            $updateData['deliverystatus1'] = $deliveryStatus;
            $updateData['deliverytime1'] = $timestamp;
            $updateData['deliveryreceipt1'] = $dlrData['message_id'] ?? '';
            // Keep onesixty_suppliermsgref (indexed match key) == deliveryreceipt1.
            $updateData['onesixty_suppliermsgref'] = $dlrData['message_id'] ?? '';
        } else {
            $updateData['deliverystatus2'] = $deliveryStatus;
            // OLD SYSTEM parity: deliverytime2 = the provider's actual delivery "done date"
            // in GMT/UTC, 12-digit YYYYMMDDHHMM (daemon_dreceipt_inbound_buffer.php). $timestamp
            // above is the normalised UTC done_date — the same value used for deliverytime1.
            // EVERY display site MUST convert this UTC value to Europe/London (DST-safe) before
            // showing it — see SMSController sent/showSmsDetails, Api/Mobile/SentSmsController &
            // SmsController, CampaignDashboardController. (Supersedes the 2026-06-08 UK-local
            // receive-time rule; the client now wants OLD-SYSTEM parity.)
            $updateData['deliverytime2'] = $timestamp;
            $updateData['deliveryreceipt2'] = $dlrData['message_id'] ?? '';
        }

        return $updateData;
    }

    /**
     * Normalise a DLR "done date" to the OLD SYSTEM storage shape for
     * deliverytime1 / deliverytime2: 12-digit YYYYMMDDHHMM in GMT/UTC, no seconds.
     *
     * OLD SYSTEM stores the raw SMPP "done date:YYMMDDHHMM" (GMT) widened to the
     * century (YYYYMMDDHHMM). The Sent-SMS detail page relies on this exact width —
     * it does CONCAT(deliverytime2,'00') to add the missing seconds and adds the
     * BST hour. 14-digit or local-time values render blank/wrong, so every DLR
     * write funnels through here.
     *
     * Accepts Carbon, 10-digit YYMMDDHHMM, 12/14-digit compact stamps, or any
     * parseable datetime string. Compact all-digit values are assumed to already
     * be GMT (as the provider sends them); other strings are converted to UTC.
     */
    private function normaliseDlrTimeToGmt12($value): string
    {
        try {
            if ($value instanceof Carbon) {
                return $value->copy()->setTimezone('UTC')->format('YmdHi');
            }

            if (is_string($value)) {
                $value = trim($value);

                if (ctype_digit($value)) {
                    if (strlen($value) === 10) {        // YYMMDDHHMM (SMPP done date, GMT)
                        return '20' . $value;           // -> YYYYMMDDHHMM
                    }
                    if (strlen($value) >= 12) {         // YYYYMMDDHHMM(SS) — drop any seconds
                        return substr($value, 0, 12);
                    }
                    // shorter / malformed — fall through to now()
                } else {
                    return Carbon::parse($value)->setTimezone('UTC')->format('YmdHi');
                }
            }
        } catch (\Throwable $e) {
            // fall through to now()
        }

        return Carbon::now('UTC')->format('YmdHi');
    }

    /**
     * Update smsg_log record
     */
    private function updateSmsgLog($smsgLog, array $updateData): void
    {
        DB::table('smsg_log')
            ->where('id', $smsgLog->id)
            ->update($updateData);
    }

    /**
     * Handle wallet refund for failed deliveries
     *
     * OLD SYSTEM BEHAVIOR (verified from smsg_2send_body.inc and daemon_dreceipt_inbound_buffer.php):
     * - Wallet IS refunded when SMS FAILS TO SEND (upstream provider rejects) - handled in SmsSendingService
     * - Wallet is NOT refunded when DLR shows "Non Delivered" - the SMS was sent, provider charged
     *
     * This method intentionally does NOT refund wallet on DLR failure.
     * The message was successfully sent to the network, so the service was consumed.
     *
     * @see smsg_2send_body.inc lines 2525-2542 for send failure refund logic
     * @see daemon_dreceipt_inbound_buffer.php - no wallet refund on DLR processing
     */
    private function handleWalletRefund($smsgLog, string $deliveryStatus, $errorCode): void
    {
        // OLD SYSTEM: DLR processing does NOT refund wallet
        // The message was successfully submitted to the upstream provider,
        // so the customer is charged regardless of delivery outcome.
        //
        // Wallet refunds only happen when:
        // 1. SMS fails to SEND to upstream provider (handled in SmsSendingService)
        // 2. NOT when DLR shows Non Delivered (message was sent but not delivered to handset)
        //
        // This matches the OLD SYSTEM behavior where:
        // - smsg_2send_body.inc refunds when mBlox/upstream rejects the message
        // - daemon_dreceipt_inbound_buffer.php does NOT refund on DLR failure

        Log::debug('DLR: No wallet refund on delivery failure (OLD SYSTEM behavior)', [
            'bigid' => $smsgLog->bigid,
            'status' => $deliveryStatus,
            'error_code' => $errorCode,
            'reason' => 'SMS was successfully submitted to upstream provider'
        ]);

        // No refund action - this is intentional
        return;
    }

    /**
     * Process DLR push callback to customer URL
     * Matches OLD SYSTEM delivery_receipt_push_log insertion
     */
    private function processDlrPushCallback($smsgLog, string $deliveryStatus, $errorCode, array $updateData, bool $skipPublish = false): void
    {
        // Get user options for DLR push (retry/daemon settings only) — cached per account (Phase 2).
        $userOptions = app(\App\Services\TableCache::class)->useroption($smsgLog->userref);

        // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): the push URL is
        // the PER-MESSAGE smsg_log.dreceipt_url — resolved at send time from the request param or,
        // if none, the useroption.dreceipt_push_url default (SmsSendingService::getDreceiptUrl /
        // SMSController). OLD explicitly stopped reading useroption.dreceipt_push_url here. The
        // retry/daemon settings still come from useroption.
        $dreceiptUrl = $smsgLog->dreceipt_url ?? '';

        // Check if DLR push is configured
        if (!$userOptions ||
            strlen($dreceiptUrl) <= 10 ||
            ($userOptions->dreceipt_tries_num ?? 0) <= 0 ||
            intval($userOptions->dreceipt_retries_wait_mins ?? -1) < 0) {
            return;
        }

        $now = Carbon::now('Europe/London');
        $timestamp = $now->format('YmdHis');
        $deliveryReceipt = $updateData['deliveryreceipt2'] ?? $updateData['deliveryreceipt1'] ?? $smsgLog->bigid;

        // Create XML for delivery receipt (OLD SYSTEM format v1.1)
        $itaggReceiptXml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<itagg_delivery_receipt>
    <version>1.1</version>
    <msisdn>{$smsgLog->mobnum}</msisdn>
    <submission_ref>{$smsgLog->bigid}</submission_ref>
    <status>{$deliveryStatus}</status>
    <reason>{$errorCode}</reason>
    <gmt_timestamp>{$timestamp}</gmt_timestamp>
    <retry>{$updateData['retry']}</retry>
</itagg_delivery_receipt>";

        // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:312): plain INSERT of the push
        // row — no dedup SELECT and no unique constraint, exactly like OLD. OLD filters duplicate
        // DLRs UPSTREAM (its transaction-id cache) and then inserts the push row directly, so the
        // per-DLR "SELECT EXISTS(...)" guard (a full table scan / slow query) is removed entirely.
        $pushLogId = DB::table('delivery_receipt_push_log')->insertGetId([
            'thismsgreference' => $deliveryReceipt,
            'msisdn' => $smsgLog->mobnum,
            'smsg_log_bigid' => $smsgLog->bigid,
            'users_bigid' => $smsgLog->userref,
            'timestamp' => $timestamp,
            'status' => 'new',
            'message_status' => $deliveryStatus,
            'reason' => $errorCode,
            'url' => $dreceiptUrl,
            'inserted_time' => $timestamp,
            'retries_left' => $userOptions->dreceipt_tries_num,
            'wait_minutes' => $userOptions->dreceipt_retries_wait_mins,
            'dosendtime' => $now->format('Y-m-d H:i:s'),
            'xml' => $itaggReceiptXml,
            'dlr_daemon_id' => $userOptions->dlr_daemon_id ?? 'default',
            'apitype' => $userOptions->apitype ?? 'w'
        ]);

        Log::info('DLR push callback row inserted', [
            'row_id' => $pushLogId,
            'bigid'  => $smsgLog->bigid,
            'url'    => $dreceiptUrl,
        ]);

        // Event-driven push — publish to RabbitMQ so a long-running consumer
        // processes it within seconds instead of waiting up to 5 min for the
        // legacy delivery-receipt:push cron. Failure to publish is non-fatal:
        // the row stays status='new' and the cron (if enabled) will pick it
        // up on its next tick as a fallback.
        // Two safeguards so the callback NEVER stalls DLR processing:
        //   1) DLR_CALLBACK_PUBLISH=false -> skip the publish entirely. The push_log row stays
        //      status='new' and dlr-callback:consume / the delivery-receipt push cron picks it
        //      up. Use this with the table-based DLR flow to keep DLR processing free of
        //      per-row RabbitMQ work.
        //   2) Otherwise reuse ONE cached connection — opening `new RabbitMQService()` per DLR
        //      meant ~500 fresh AMQP connections per batch, which stalled the buffer processor.
        if (!$skipPublish && filter_var(env('DLR_CALLBACK_PUBLISH', true), FILTER_VALIDATE_BOOLEAN)) {
            try {
                if ($this->rabbit === null) {
                    $this->rabbit = new \App\Services\Queue\RabbitMQService();
                }
                $queue = env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push');
                $this->rabbit->publishToQueue($queue, [
                    'row_id'    => $pushLogId,
                    'bigid'     => $smsgLog->bigid,
                    'msisdn'    => $smsgLog->mobnum,
                    'queued_at' => $now->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('DLR push callback queue publish failed (row will retry via cron if enabled)', [
                    'row_id' => $pushLogId,
                    'error'  => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update master_msisdn for permanent absent subscribers
     * Matches OLD SYSTEM logic for reason code 23
     */
    private function updateMasterMsisdn(string $mobileNumber, $errorCode): void
    {
        // Only update for permanent absent subscriber (error code 23)
        if ((int)$errorCode !== 23) {
            return;
        }

        $cleanNumber = preg_replace('/[^0-9]/', '', $mobileNumber);
        $now = Carbon::now()->format('YmdHis');

        // Try to update existing record
        $updated = DB::table('master_msisdn')
            ->where('msisdn', $cleanNumber)
            ->update([
                'hlrstatus2' => DB::raw('hlrstatus'),
                'netid2' => DB::raw('netid'),
                'lastlookupdate2' => DB::raw('lastlookupdate'),
                'hlrstatus' => '1sdead',
                'netid' => '99',
                'lastlookupdate' => $now,
                'otherdata' => "SUPPLIERDLR:PERMABSENT({$errorCode})"
            ]);

        // Insert if not exists
        if (!$updated) {
            DB::table('master_msisdn')->insertOrIgnore([
                'msisdn' => $cleanNumber,
                'hlrstatus' => '1sdead',
                'netid' => '99',
                'lastlookupdate' => $now,
                'otherdata' => "SUPPLIERDLR:PERMABSENT({$errorCode})"
            ]);
        }

        Log::info('Master MSISDN marked as dead', [
            'msisdn' => $cleanNumber,
            'reason' => $errorCode
        ]);
    }

    /**
     * Map Vonage/Nexmo webhook status to SMPP status
     */
    public function mapVonageStatus(string $vonageStatus): array
    {
        $statusMap = [
            'delivered' => ['status' => 'DELIVRD', 'error_code' => 4],
            'expired' => ['status' => 'EXPIRED', 'error_code' => 8],
            'failed' => ['status' => 'UNDELIV', 'error_code' => 5],
            'rejected' => ['status' => 'REJECTD', 'error_code' => 27],
            'accepted' => ['status' => 'ACCEPTD', 'error_code' => 3],
            'buffered' => ['status' => 'ACCEPTD', 'error_code' => 2],
            'unknown' => ['status' => 'UNKNOWN', 'error_code' => 6],
        ];

        $lowerStatus = strtolower($vonageStatus);
        return $statusMap[$lowerStatus] ?? ['status' => 'UNKNOWN', 'error_code' => 6];
    }

    /**
     * Map Sinch/mBlox status to SMPP status
     */
    public function mapSinchStatus(string $sinchStatus, $reasonCode = 0): array
    {
        // Sinch uses similar reason codes to mBlox
        return [
            'status' => $this->mapToDeliveryStatus($sinchStatus, $reasonCode),
            'error_code' => (int) $reasonCode
        ];
    }
}
