<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\BulkThroughputWarningMail;
use App\Jobs\SendEmailJob;
use App\Services\Queue\EmailQueueService;
use App\Services\Queue\PushNotificationQueueService;
use Carbon\Carbon;

class BulkThroughputService
{
    /**
     * Check if user has exceeded bulk throughput limit
     *
     * @param string $userBigId
     * @return array
     */
    public function checkAndUpdateThroughput(string $userBigId): array
    {
        // Get user details
        $user = DB::table('users')
            ->select(
                'smsg_wallet', 'smsg_server1_sent', 'smsg_server2_sent', 'bigid', 
                'agencyref', 'uname', 'bulk_throughput', 'bulk_throughput_lastwarned',
                'contactemail', 'contactname', 'busname', 'bulksms_daily_tally',
                'bulksms_tally_last_reset', 'user_type', 'masteruname'
            )
            ->where('bigid', $userBigId)
            ->first();

        if (!$user) {
            Log::error('User not found for bulk throughput check', ['user_bigid' => $userBigId]);
            return [
                'allowed' => false,
                'message' => 'User not found',
                'reason' => 'user_not_found'
            ];
        }

        // Check and reset daily tally if needed
        $lastMidnight = Carbon::today()->startOfDay();
        $lastReset = $user->bulksms_tally_last_reset ? Carbon::parse($user->bulksms_tally_last_reset) : null;
        
        if (!$lastReset || $lastReset->lt($lastMidnight)) {
            // Reset the tally for this user. Same soft-write rule as incrementTally(): this is the
            // first send of the day and must never be blocked/failed by a locked users row. Fail
            // fast and, if the DB write can't happen, still treat the tally as reset in-memory
            // (a new day) so THIS send proceeds; the DB reset simply retries on the next send.
            try {
                try {
                    DB::statement('SET innodb_lock_wait_timeout = 2');
                } catch (\Throwable $ignore) {
                }

                DB::table('users')
                    ->where('bigid', $userBigId)
                    ->update([
                        'bulksms_tally_last_reset' => Carbon::now(),
                        'bulksms_daily_tally' => 0
                    ]);

                Log::info('Reset daily SMS tally', [
                    'user' => $user->uname,
                    'bigid' => $userBigId
                ]);
            } catch (\Throwable $e) {
                Log::warning('Daily SMS tally reset skipped (users row busy/locked) - send continues', [
                    'bigid' => $userBigId,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                try {
                    DB::statement('SET innodb_lock_wait_timeout = @@GLOBAL.innodb_lock_wait_timeout');
                } catch (\Throwable $ignore) {
                }
            }

            // New day: treat the counter as 0 for this send regardless of whether the DB write landed.
            $bulksmsDailyTally = 0;
        } else {
            $bulksmsDailyTally = $user->bulksms_daily_tally;
        }

        // OLD SYSTEM: If bulk_throughput is 0 or not set, it means NO sending allowed (throughput exceeded)
        // User must have a bulk_throughput > 0 to send SMS
        if (!$user->bulk_throughput || $user->bulk_throughput == 0) {
            Log::warning('SMS sending blocked - no bulk throughput limit configured', [
                'user' => $user->uname,
                'bigid' => $userBigId,
                'bulk_throughput' => $user->bulk_throughput
            ]);

            return [
                'allowed' => false,
                'message' => 'There was an error while submitting: throughput exceeded. Daily Bulk SMS Limit is not configured.',
                'reason' => 'throughput_not_configured',
                'current_tally' => $bulksmsDailyTally,
                'limit' => 0
            ];
        }

        // Check if limit has been exceeded
        if ($bulksmsDailyTally >= $user->bulk_throughput) {
            // Check if we need to send warning email and push notification
            $this->sendWarningNotificationsIfNeeded($user, $bulksmsDailyTally);
            
            return [
                'allowed' => false,
                'message' => 'Daily SMS limit exceeded',
                'reason' => 'limit_exceeded',
                'current_tally' => $bulksmsDailyTally,
                'limit' => $user->bulk_throughput
            ];
        }

        // Increment tally and allow sending
        $this->incrementTally($userBigId);
        
        return [
            'allowed' => true,
            'message' => 'Within daily limit',
            'current_tally' => $bulksmsDailyTally + 1,
            'limit' => $user->bulk_throughput,
            'remaining' => $user->bulk_throughput - ($bulksmsDailyTally + 1)
        ];
    }

    /**
     * Increment the daily tally for a user.
     *
     * OLD SYSTEM parity: OLD ran this as a plain autocommit fire-and-forget query
     * (smssend.inc:672 `update users set bulksms_daily_tally = bulksms_daily_tally + 1 ...`).
     * Because OLD used autocommit + sequential daemons, the users-row lock was held for only
     * microseconds, so this counter could never block or fail a send.
     *
     * In the new (Laravel/InnoDB, highly concurrent) stack the same users row can be held by
     * another open transaction. A plain ->increment() would then wait innodb_lock_wait_timeout
     * (~50s) and throw SQLSTATE 1205, which ABORTED the whole send — that is exactly what failed
     * Arun Estates' sends. The daily tally is only a SOFT rate-limit, so it must behave like OLD:
     * never block the send and never fail it.
     *
     * We therefore (1) fail fast — shrink the lock wait to a couple of seconds for this statement
     * so a busy row never stalls the request — and (2) swallow-and-log any error so the SMS always
     * goes out regardless of whether the counter could be updated. The session lock-wait timeout is
     * restored afterwards so the rest of the request keeps normal locking behaviour.
     *
     * @param string $userBigId
     */
    private function incrementTally(string $userBigId): void
    {
        try {
            // Fail fast instead of waiting the default ~50s on a contended users row.
            try {
                DB::statement('SET innodb_lock_wait_timeout = 2');
            } catch (\Throwable $ignore) {
                // Non-MySQL driver / not permitted — fall through with the default timeout.
            }

            DB::table('users')
                ->where('bigid', $userBigId)
                ->increment('bulksms_daily_tally');
        } catch (\Throwable $e) {
            // Soft counter — never let a locked/slow users row drop or delay the actual SMS.
            Log::warning('bulksms_daily_tally not incremented (users row busy/locked) - send continues', [
                'bigid' => $userBigId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Restore normal lock-wait behaviour for any later queries on this connection.
            try {
                DB::statement('SET innodb_lock_wait_timeout = @@GLOBAL.innodb_lock_wait_timeout');
            } catch (\Throwable $ignore) {
                // Best effort — the connection is short-lived and will be recycled anyway.
            }
        }
    }

    /**
     * Send warning notifications (email and push) if needed (not more than once per 24 hours)
     *
     * @param object $user
     * @param int $currentTally
     */
    private function sendWarningNotificationsIfNeeded($user, int $currentTally): void
    {
        $needToSend = false;
        
        if (empty($user->bulk_throughput_lastwarned)) {
            // Never warned before
            $needToSend = true;
        } else {
            $lastWarned = Carbon::parse($user->bulk_throughput_lastwarned);
            $hoursSinceWarning = $lastWarned->diffInHours(Carbon::now());
            
            if ($hoursSinceWarning >= 24) {
                // More than 24 hours since last warning
                $needToSend = true;
            }
        }

        if ($needToSend) {
            // Send push notification to mobile app
            $this->sendPushNotification($user, $currentTally);
            
            // Send email notification
            $this->sendWarningEmail($user, $currentTally);

            // Update last warned timestamp
            DB::table('users')
                ->where('bigid', $user->bigid)
                ->update([
                    'bulk_throughput_lastwarned' => Carbon::now()
                ]);
        }
    }

    /**
     * Send push notification for throughput limit
     *
     * @param object $user
     * @param int $currentTally
     */
    private function sendPushNotification($user, int $currentTally): void
    {
        try {
            $pushQueueService = new PushNotificationQueueService();
            $pushQueued = $pushQueueService->queueThroughputLimitNotification(
                $user->bigid,
                $user->uname,
                $user->bulk_throughput,
                $currentTally
            );
            
            if ($pushQueued) {
                Log::info('Throughput limit push notification queued', [
                    'user' => $user->uname,
                    'bigid' => $user->bigid,
                    'limit' => $user->bulk_throughput,
                    'tally' => $currentTally
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to queue push notification for throughput limit', [
                'user' => $user->uname,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send warning email for throughput limit
     *
     * @param object $user
     * @param int $currentTally
     */
    private function sendWarningEmail($user, int $currentTally): void
    {
        try {
            $userData = [
                'contact_name' => urldecode($user->contactname),
                'username' => $user->uname,
                'business_name' => urldecode($user->busname),
                'bulk_throughput' => $user->bulk_throughput,
                'messages_sent_today' => $currentTally
            ];
            
            $supportData = array_merge($userData, [
                'contact_name' => 'Support Team',
                'user_email' => $user->contactemail,
                'user_bigid' => $user->bigid
            ]);
            
            // Check if RabbitMQ is available
            $useQueue = env('RABBITMQ_ENABLED', true);
            
            if ($useQueue) {
                try {
                    // Try to use RabbitMQ queue
                    $emailQueue = new EmailQueueService();
                    
                    // Queue user email
                    $userQueued = $emailQueue->queueEmail(
                        BulkThroughputWarningMail::class,
                        $user->contactemail,
                        $userData,
                        [],  // No CC
                        8    // High priority
                    );
                    
                    // Queue support email
                    $supportEmail = env('SUPPORT_EMAIL', 'anand@nedholdings.com');
                    $supportQueued = $emailQueue->queueEmail(
                        BulkThroughputWarningMail::class,
                        $supportEmail,
                        $supportData,
                        [],  // No CC
                        7    // Slightly lower priority
                    );
                    
                    if ($userQueued && $supportQueued) {
                        Log::info('Bulk throughput warning emails queued via RabbitMQ', [
                            'user' => $user->uname,
                            'email' => $user->contactemail,
                            'limit' => $user->bulk_throughput,
                            'tally' => $currentTally
                        ]);
                    } else {
                        throw new \Exception('Failed to queue one or more emails');
                    }
                } catch (\Exception $queueException) {
                    // Fallback to Laravel queue if RabbitMQ fails
                    Log::warning('RabbitMQ unavailable for throughput warning, using Laravel queue', [
                        'error' => $queueException->getMessage()
                    ]);
                    
                    // Queue user email via Laravel queue
                    SendEmailJob::dispatch(
                        BulkThroughputWarningMail::class,
                        $user->contactemail,
                        $userData
                    )->onConnection('emails')->onQueue('emails');
                    
                    // Queue support email via Laravel queue
                    $supportEmail = env('SUPPORT_EMAIL', 'anand@nedholdings.com');
                    SendEmailJob::dispatch(
                        BulkThroughputWarningMail::class,
                        $supportEmail,
                        $supportData
                    )->onConnection('emails')->onQueue('emails');
                    
                    Log::info('Bulk throughput warning emails queued via Laravel queue', [
                        'user' => $user->uname,
                        'email' => $user->contactemail
                    ]);
                }
            } else {
                // Send emails synchronously (original behavior)
                Mail::to($user->contactemail)
                    ->send(new BulkThroughputWarningMail($userData));

                // Send notification to support
                $supportEmail = env('SUPPORT_EMAIL', 'anand@nedholdings.com');
                Mail::to($supportEmail)
                    ->send(new BulkThroughputWarningMail($supportData));
                
                Log::info('Bulk throughput warning emails sent synchronously', [
                    'user' => $user->uname,
                    'email' => $user->contactemail
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process bulk throughput warning email', [
                'user' => $user->uname,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get current throughput status for a user
     *
     * @param string $userBigId
     * @return array
     */
    public function getThroughputStatus(string $userBigId): array
    {
        $user = DB::table('users')
            ->select('bulk_throughput', 'bulksms_daily_tally', 'bulksms_tally_last_reset')
            ->where('bigid', $userBigId)
            ->first();

        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }

        // Check if tally needs reset
        $lastMidnight = Carbon::today()->startOfDay();
        $lastReset = $user->bulksms_tally_last_reset ? Carbon::parse($user->bulksms_tally_last_reset) : null;
        
        $currentTally = $user->bulksms_daily_tally;
        if (!$lastReset || $lastReset->lt($lastMidnight)) {
            $currentTally = 0;
        }

        // OLD SYSTEM: If bulk_throughput is 0, it means NOT configured (no sending allowed)
        if (!$user->bulk_throughput || $user->bulk_throughput == 0) {
            return [
                'status' => 'not_configured',
                'limit' => 0,
                'used' => $currentTally,
                'remaining' => 0,
                'percentage_used' => 100,
                'message' => 'Daily Bulk SMS Limit is not configured. Please contact support.'
            ];
        }

        return [
            'status' => 'ok',
            'limit' => $user->bulk_throughput,
            'used' => $currentTally,
            'remaining' => max(0, $user->bulk_throughput - $currentTally),
            'percentage_used' => round(($currentTally / $user->bulk_throughput) * 100, 2)
        ];
    }
}
