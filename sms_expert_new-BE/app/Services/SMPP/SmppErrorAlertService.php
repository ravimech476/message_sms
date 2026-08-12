<?php

namespace App\Services\SMPP;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends an email alert when any SMPP-side error occurs (connect, bind, send,
 * DLR receiver failures, etc.). Throttles by subject so a 5-minute spike of
 * the same failure doesn't flood the recipient inbox.
 *
 * Config (.env):
 *   SMPP_ALERT_ENABLED=true|false          master switch
 *   SMPP_ALERT_EMAIL=a@x.com,b@x.com       comma-separated recipients
 *   SMPP_ALERT_THROTTLE_MINUTES=15         per-subject throttle window
 *   SMPP_ALERT_FROM_NAME="SMS Expert"      from name (optional)
 *
 * Always wrapped in try/catch — alert delivery failures must never break the
 * SMPP send/receive path that triggered them.
 */
class SmppErrorAlertService
{
    public static function notify(string $subject, string $body, array $context = []): void
    {
        try {
            if (!env('SMPP_ALERT_ENABLED', false)) {
                return;
            }

            $recipients = self::recipients();
            if (empty($recipients)) {
                return;
            }

            // "One email per incident" — once an alert for this subject has
            // been sent we mark it ACTIVE in cache. We won't email again until
            // either (a) clear($subject) is called (a successful bind/send
            // happened, so the incident is over), or (b) the 24-hour safety
            // net expires (in case clear() never fires).
            $activeKey = self::activeKey($subject);
            if (Cache::has($activeKey)) {
                return;
            }

            // Short cooldown — prevents bursty re-alerts when something flaps
            // fail→success→fail rapidly. Within this window even a cleared
            // active flag won't re-email.
            $cooldownMinutes = (int) env('SMPP_ALERT_THROTTLE_MINUTES', 15);
            $cooldownKey = self::cooldownKey($subject);
            if (Cache::has($cooldownKey)) {
                return;
            }

            Cache::put($activeKey, true, now()->addHours(24));
            Cache::put($cooldownKey, true, now()->addMinutes($cooldownMinutes));

            // Branded templated email — same layout as
            // DeliveryReceiptFailureMail / WalletReminderMail / etc., not the
            // previous plain Mail::raw text.
            $env  = config('app.env', 'unknown');
            $host = gethostname() ?: 'unknown';

            $payload = [
                'subject_line'  => $subject,
                'body'          => $body,
                'context'       => $context,
                'env'           => $env,
                'host'          => $host,
                'sent_at'       => now()->toDateTimeString(),
                'throttled_for' => "Suppressed until SMPP recovers (next bind success clears the alert) or 24h elapses; minimum cooldown {$cooldownMinutes} min",
            ];

            $fromName    = env('SMPP_ALERT_FROM_NAME', config('mail.from.name', 'SMS Expert'));
            $fromAddress = config('mail.from.address');

            $mailable = new \App\Mail\SmppErrorAlertMail($payload);
            if ($fromAddress) {
                $mailable->from($fromAddress, $fromName);
            }

            Mail::to($recipients)->send($mailable);

            SmppLogger::forProvider('alerts')->info('SMPP alert sent', ['subject' => $subject, 'recipients' => $recipients]);
        } catch (Throwable $e) {
            SmppLogger::forProvider('alerts')->error('SMPP alert delivery failed', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Transient error alert — cooldown-only throttle, no active-until-recovery.
     *
     * Use this for errors that don't have a clean "recovered" signal:
     *   - generic RabbitMQ consumer exceptions (any queue, any payload)
     *   - stale watchdog heartbeats
     *   - other one-off failures handled by automatic retry mechanisms
     *
     * Behavior: one email per subject per SMPP_ALERT_THROTTLE_MINUTES (default
     * 15 min). After the cooldown expires, the next failure on the same subject
     * triggers a fresh email. No clear() needed — automatic.
     *
     * Same templated SmppErrorAlertMail layout as notify() — recipients see a
     * branded email with subject, body, and the context table.
     */
    public static function notifyTransient(string $subject, string $body, array $context = []): void
    {
        try {
            if (!env('SMPP_ALERT_ENABLED', false)) {
                return;
            }

            $recipients = self::recipients();
            if (empty($recipients)) {
                return;
            }

            $cooldownMinutes = (int) env('SMPP_ALERT_THROTTLE_MINUTES', 15);
            $cooldownKey = self::cooldownKey($subject);
            if (Cache::has($cooldownKey)) {
                return; // recent email sent for this subject, skip
            }
            Cache::put($cooldownKey, true, now()->addMinutes($cooldownMinutes));

            $env  = config('app.env', 'unknown');
            $host = gethostname() ?: 'unknown';

            $payload = [
                'subject_line'  => $subject,
                'body'          => $body,
                'context'       => $context,
                'env'           => $env,
                'host'          => $host,
                'sent_at'       => now()->toDateTimeString(),
                'throttled_for' => "No more emails for this subject for {$cooldownMinutes} minutes.",
            ];

            $fromName    = env('SMPP_ALERT_FROM_NAME', config('mail.from.name', 'SMS Expert'));
            $fromAddress = config('mail.from.address');

            $mailable = new \App\Mail\SmppErrorAlertMail($payload);
            if ($fromAddress) {
                $mailable->from($fromAddress, $fromName);
            }

            Mail::to($recipients)->send($mailable);

            SmppLogger::forProvider('alerts')->info('Transient error alert sent', ['subject' => $subject, 'recipients' => $recipients]);
        } catch (Throwable $e) {
            SmppLogger::forProvider('alerts')->error('Transient alert delivery failed', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark a subject as recovered. Called from successful-bind / successful-send
     * paths so the NEXT failure (if any) is allowed to email again. Without
     * this, the system would only email once per 24h regardless of recovery.
     */
    public static function clear(string $subject): void
    {
        try {
            Cache::forget(self::activeKey($subject));
            // Note: cooldown key is NOT cleared — it keeps preventing immediate
            // re-alert bursts even after a recovery.
        } catch (Throwable $e) {
            // ignore — recovery signalling is best-effort
        }
    }

    private static function activeKey(string $subject): string
    {
        return 'smpp-alert:active:' . md5($subject);
    }

    private static function cooldownKey(string $subject): string
    {
        return 'smpp-alert:cooldown:' . md5($subject);
    }

    private static function recipients(): array
    {
        $raw = (string) env('SMPP_ALERT_EMAIL', config('smpp.dlr.alert_email', ''));
        if (trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

}
