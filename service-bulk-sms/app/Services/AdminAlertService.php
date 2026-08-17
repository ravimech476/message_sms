<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Emails the admin when the application hits an unhandled exception — anywhere
 * (web, queue, console, workers). Throttled per unique error so a crash loop
 * can't flood the inbox. All mail errors are swallowed so alerting can never
 * break the app's own error handling.
 */
class AdminAlertService
{
    public function notifyException(Throwable $e): void
    {
        if (!config('alert.enabled')) {
            return;
        }
        $to = config('alert.email');
        if (empty($to)) {
            return;
        }

        // Ignore ordinary 4xx HTTP errors (404, 419, 403…) — not real crashes.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return;
        }

        // Throttle: one email per unique error per window.
        $key = 'admin-alert:' . md5(get_class($e) . '|' . $e->getFile() . '|' . $e->getLine() . '|' . $e->getMessage());
        if (Cache::has($key)) {
            return;
        }
        Cache::put($key, 1, now()->addMinutes((int) config('alert.throttle_minutes', 15)));

        $app = config('app.name', 'Application');
        $env = config('app.env', 'production');
        $subject = "[{$app}] Error: " . class_basename($e) . ' — ' . Str::limit($e->getMessage(), 80);

        $where = 'console / queue worker';
        try {
            if (!app()->runningInConsole() && request()) {
                $where = request()->method() . ' ' . request()->fullUrl();
            }
        } catch (Throwable $ignore) {
            // no request context
        }

        $body = implode("\n", [
            "Application : {$app} ({$env})",
            "Exception   : " . get_class($e),
            "Message     : " . $e->getMessage(),
            "Location    : " . $e->getFile() . ':' . $e->getLine(),
            "Where       : " . $where,
            "Time        : " . now()->toDateTimeString(),
            "",
            "Stack trace (top 25 lines):",
            implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 25)),
        ]);

        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                foreach (preg_split('/[,;]\s*/', (string) $to) as $addr) {
                    $addr = trim($addr);
                    if ($addr !== '') {
                        $m->to($addr);
                    }
                }
                $m->subject($subject);
            });
        } catch (Throwable $mailError) {
            Log::warning('AdminAlertService: could not send crash email — ' . $mailError->getMessage());
        }
    }
}
