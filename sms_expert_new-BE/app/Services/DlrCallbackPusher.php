<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Per-row processor for delivery_receipt_push_log entries. Shared by the
 * legacy cron (delivery-receipt:push) and the new RabbitMQ consumer
 * (dlr-callback:consume) so both call sites POST identically and update the
 * row identically.
 *
 * Claims rows atomically (UPDATE ... WHERE status='new') so the cron and
 * queue consumer can't race on the same row if both are running.
 */
class DlrCallbackPusher
{
    public int $curlTimeout = 10;
    public int $curlConnectTimeout = 5;

    /**
     * Process one row. Returns one of:
     *   'sent'       — POST returned 2xx, row status=processed
     *   'retry'      — POST failed, retries_left > 0, dosendtime moved forward, status stays 'new'
     *   'fail'       — POST failed, retries_left hit 0, status='fail'
     *   'skipped'    — could not claim the row (already 'doing'/'processed'/'fail', or another worker grabbed it)
     *   'missing'    — row id doesn't exist
     *
     * Returned status drives the queue consumer's decision on whether to republish with delay.
     */
    public function processRow(int $rowId): string
    {
        $row = DB::table('delivery_receipt_push_log')->where('id', $rowId)->first();
        if (!$row) {
            return 'missing';
        }

        // Shared-DB migration guard. The OLD and NEW systems run off the SAME
        // database and BOTH read delivery_receipt_push_log. The OLD system still
        // owns delivery-receipt forwarding (and the "Delivery Receipt Forwarding
        // Failed" email) for customers that have NOT been migrated. So the NEW
        // system must only forward / notify for customers whose
        // users.migration_flag = 'new'. For everyone else we return WITHOUT
        // claiming or modifying the row — leaving it status='new' so the OLD
        // system's push daemon still picks it up. (Changing the status here would
        // starve the OLD system and the customer would never get their DLR.)
        // Because we return before the POST, the failure email is likewise never
        // sent from the NEW system for non-migrated customers.
        if (!$this->isMigratedCustomer($row->users_bigid)) {
            Log::info('DLR callback skipped — customer not migrated (handled by OLD system)', [
                'row_id'      => $rowId,
                'users_bigid' => $row->users_bigid,
            ]);
            return 'skipped';
        }

        // Atomic claim — only proceed if we successfully flip 'new' → 'doing'.
        $claimed = DB::table('delivery_receipt_push_log')
            ->where('id', $rowId)
            ->where('status', 'new')
            ->update(['status' => 'doing']);

        if ($claimed !== 1) {
            return 'skipped';
        }

        $startTime = microtime(true);
        $postData = $this->preparePostData($row);
        $response = $this->sendCurlRequest($row->url, $postData, $row);
        $responseTime = round(microtime(true) - $startTime, 3);

        if ($response['success']) {
            DB::table('delivery_receipt_push_log')
                ->where('id', $row->id)
                ->update([
                    'status'                     => 'processed',
                    'server_response'            => substr($response['result'] ?? '', 0, 1000),
                    'last_connection_time'       => now(),
                    'last_server_response_speed' => $responseTime,
                ]);

            Log::info('DLR callback POST succeeded', [
                'row_id'    => $row->id,
                'url'       => $row->url,
                'http_code' => $response['http_code'],
                'time'      => $responseTime,
            ]);
            return 'sent';
        }

        $retriesLeft = max(0, ((int) $row->retries_left) - 1);
        $newStatus = $retriesLeft > 0 ? 'new' : 'fail';
        $waitMinutes = (int) ($row->wait_minutes ?? 5);
        $nextAttempt = Carbon::now()->addMinutes($waitMinutes);

        $errorMessage = $response['error'] ?: ('HTTP ' . $response['http_code']);
        $serverResponse = $response['result'] ?: $errorMessage;

        DB::table('delivery_receipt_push_log')
            ->where('id', $row->id)
            ->update([
                'status'                     => $newStatus,
                'server_response'            => substr($serverResponse, 0, 1000),
                'last_connection_time'       => now(),
                'retries_left'               => $retriesLeft,
                'dosendtime'                 => $nextAttempt,
                'last_server_response_speed' => $responseTime,
            ]);

        Log::warning('DLR callback POST failed', [
            'row_id'        => $row->id,
            'url'           => $row->url,
            'http_code'     => $response['http_code'],
            'error'         => $errorMessage,
            'retries_left'  => $retriesLeft,
            'next_attempt'  => $nextAttempt->toDateTimeString(),
        ]);

        // Once retries hit 0 the row is permanently failed — send the
        // "Delivery Receipt Forwarding Failed" email, matching OLD SYSTEM
        // DeliveryReceiptPushCommand::sendFailureNotification.
        if ($retriesLeft <= 0) {
            $this->sendFailureNotification($row, $response, $retriesLeft);
        }

        return $retriesLeft > 0 ? 'retry' : 'fail';
    }

    /**
     * Send the customer-facing failure notification email when retries are
     * exhausted. Matches the existing OLD SYSTEM Mailable
     * (App\Mail\DeliveryReceiptFailureMail) so the rendered email is
     * identical regardless of which code path triggered it.
     *
     * Wrapped in try/catch — email-send failures must not break the
     * push pipeline (the row is already updated to status='fail').
     */
    private function sendFailureNotification($row, array $response, int $retriesLeft): void
    {
        try {
            if (!$this->shouldSendNotification($row->users_bigid)) {
                return;
            }

            $recipient = $this->getNotificationRecipient($row->users_bigid);
            if (!$recipient || empty($recipient['email'])) {
                Log::warning('DLR callback failure email skipped — no recipient', [
                    'row_id'      => $row->id,
                    'users_bigid' => $row->users_bigid,
                ]);
                return;
            }

            $emailQueue = new \App\Services\Queue\EmailQueueService();
            $emailQueue->queueEmail(
                \App\Mail\DeliveryReceiptFailureMail::class,
                $recipient['email'],
                [
                    'url'           => $row->url,
                    'xml'           => $row->xml,
                    'error'         => $response['error'] ?? '',
                    'errno'         => $response['errno'] ?? 0,
                    'result'        => $response['result'] ?? '',
                    'http_code'     => $response['http_code'] ?? 0,
                    'retries_left'  => $retriesLeft,
                    'daemon_name'   => $row->dlr_daemon_id ?? 'default',
                    'receipt_id'    => $row->id,
                    'contact_email' => $recipient['email'],
                    'contact_name'  => $recipient['name'] ?? 'Customer',
                ],
                config('delivery_receipt.cc_emails', [])
            );

            Log::info('DLR callback failure notification queued', [
                'row_id'    => $row->id,
                'recipient' => $recipient['email'],
            ]);
        } catch (\Throwable $e) {
            Log::error('DLR callback failure notification queue exception', [
                'row_id' => $row->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Shared-DB migration guard used by processRow(). Returns true only when the
     * customer has been migrated to the NEW system (users.migration_flag = 'new').
     * Non-migrated customers (NULL / '' / 'old') are still handled by the OLD
     * system on the same database, so the NEW system must not forward their DLRs
     * or send them the "Delivery Receipt Forwarding Failed" email.
     */
    private function isMigratedCustomer($usersBigid): bool
    {
        if ($usersBigid === null || $usersBigid === '') {
            return false;
        }

        $flag = DB::table('users')->where('bigid', $usersBigid)->value('migration_flag');

        return $flag === 'new';
    }

    /**
     * Per-customer opt-out: any user_bigid listed in
     * config('delivery_receipt.excluded_notification_users') will not receive
     * failure emails. Matches OLD SYSTEM behavior.
     */
    private function shouldSendNotification($userId): bool
    {
        $excluded = config('delivery_receipt.excluded_notification_users', []);
        return !in_array($userId, $excluded);
    }

    /**
     * Pick the failure-notification recipient:
     *   1. config('delivery_receipt.special_recipients')[$userId] — per-user override
     *   2. users.contactemail / users.contactname
     *   3. config('delivery_receipt.default_recipient') — global fallback
     */
    private function getNotificationRecipient($userId): ?array
    {
        $special = config('delivery_receipt.special_recipients', []);
        if (isset($special[$userId])) {
            return $special[$userId];
        }

        $user = DB::table('users')
            ->where('bigid', $userId)
            ->first(['contactname', 'contactemail']);

        if ($user && !empty($user->contactemail)) {
            return [
                'email' => $user->contactemail,
                'name'  => $user->contactname ?? 'Customer',
            ];
        }

        return config('delivery_receipt.default_recipient');
    }

    /**
     * Build POST payload — XML for apitype=x, form-data for apitype=w (white-label).
     * Mirrors DeliveryReceiptPushCommand::preparePostData / prepareWhiteLabelData.
     */
    private function preparePostData($row): array
    {
        if (($row->apitype ?? 'x') === 'w') {
            $xmlData = $this->parseDeliveryReceiptXml($row->xml);

            $dlrstatus = strtolower($xmlData['status'] ?? 'unknown');
            $dlrstatus = str_replace(['non delivered', 'lost notification'], ['not delivered', 'unknown'], $dlrstatus);
            if (!in_array($dlrstatus, ['delivered', 'not delivered', 'unknown', 'buffered'])) {
                $dlrstatus = 'unknown';
            }

            return [
                'msisdn'    => $xmlData['msisdn'] ?? '',
                'subid'     => $xmlData['submission_ref'] ?? '',
                'dlrstatus' => $dlrstatus,
                'dlrcode'   => $xmlData['reason'] ?? '0',
            ];
        }

        return ['xml' => $row->xml];
    }

    private function parseDeliveryReceiptXml($xml): array
    {
        try {
            $xml = preg_replace('/>\s+</', '><', trim((string) $xml));
            $obj = simplexml_load_string($xml);
            if ($obj === false) {
                return [];
            }
            return [
                'msisdn'         => (string) $obj->msisdn,
                'submission_ref' => (string) $obj->submission_ref,
                'status'         => (string) $obj->status,
                'reason'         => (string) $obj->reason,
                'gmt_timestamp'  => (string) ($obj->gmt_timestamp ?? ''),
                'retry'          => (string) ($obj->retry ?? '0'),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function sendCurlRequest(string $url, array $postData, $row): array
    {
        $ch = curl_init();
        $isXml = isset($postData['xml']);

        if ($isXml) {
            $xmlData = preg_replace('/>\s+</', '><', trim((string) $postData['xml']));
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => 1,
                CURLOPT_POSTFIELDS     => $xmlData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $this->curlTimeout,
                CURLOPT_CONNECTTIMEOUT => $this->curlConnectTimeout,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/xml; charset=ISO-8859-1',
                    'Content-Length: ' . strlen($xmlData),
                    'Accept: */*',
                ],
            ]);
        } else {
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => 1,
                CURLOPT_POSTFIELDS     => http_build_query($postData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $this->curlTimeout,
                CURLOPT_CONNECTTIMEOUT => $this->curlConnectTimeout,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
        }

        $result   = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success'   => ($errno === CURLE_OK && $httpCode >= 200 && $httpCode < 300),
            'result'    => $result,
            'errno'     => $errno,
            'error'     => $error,
            'http_code' => $httpCode,
        ];
    }
}
