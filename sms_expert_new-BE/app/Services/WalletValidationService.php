<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\InsufficientFundsAlertMail;
use App\Jobs\SendEmailJob;
use App\Services\Queue\EmailQueueService;
use App\Services\Queue\PushNotificationQueueService;
use Carbon\Carbon;

class WalletValidationService
{
    /**
     * Check if user has sufficient wallet balance for SMS
     *
     * @param string $userBigId
     * @param int $messageCount Number of messages to send
     * @param array $phoneNumbers Array of phone numbers to calculate country-specific rates
     * @return array
     */
    public function validateWalletBalance(string $userBigId, int $messageCount = 1, array $phoneNumbers = []): array
    {
        // Get user wallet details
        $user = DB::table('users')
            ->select(
                'id',
                'smsg_wallet', 
                'smsg_server1_sent', 
                'smsg_server2_sent', 
                'bigid', 
                'uname', 
                'contactemail', 
                'contactname', 
                'busname',
                'common_sms_rate'
            )
            ->where('bigid', $userBigId)
            ->first();

        if (!$user) {
            Log::error('User not found for wallet validation', ['user_bigid' => $userBigId]);
            return [
                'has_funds' => false,
                'message' => 'User not found',
                'reason' => 'user_not_found'
            ];
        }

        // Calculate current wallet balance
        $currentWallet = sprintf("%.2f", $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent);
        
        // Calculate the total cost based on country-specific rates
        $totalCost = $this->calculateTotalSmsCost($user, $phoneNumbers);
        
        // Use calculated cost or fallback to minimum
        $minRequired = $totalCost > 0 ? $totalCost : ($messageCount * 0.009);
        $absoluteMinimum = 0.08; // 8p minimum wallet level
        
        // Check if wallet has sufficient funds
        if ($currentWallet < $minRequired || $currentWallet < $absoluteMinimum) {
            // Send alert if needed
            $this->alertAndRegister($userBigId, $user->uname, $user->contactemail, $user->contactname);
            
            Log::warning('Insufficient wallet funds', [
                'user' => $user->uname,
                'bigid' => $userBigId,
                'current_wallet' => $currentWallet,
                'required' => $minRequired,
                'message_count' => $messageCount
            ]);
            
            return [
                'has_funds' => false,
                'message' => 'Insufficient wallet funds',
                'reason' => 'insufficient_funds',
                'current_balance' => $currentWallet,
                'required_amount' => max($minRequired, $absoluteMinimum),
                'shortage' => max($minRequired, $absoluteMinimum) - $currentWallet
            ];
        }

        return [
            'has_funds' => true,
            'message' => 'Sufficient funds available',
            'current_balance' => $currentWallet,
            'required_amount' => $minRequired,
            'remaining_after' => $currentWallet - $minRequired
        ];
    }

    /**
     * Send alert notifications for insufficient funds
     *
     * @param string $usersBigId
     * @param string $username
     * @param string $email
     * @param string $contactName
     */
    private function alertAndRegister(string $usersBigId, string $username, string $email = null, string $contactName = null): void
    {
        // Get user notification preferences
        $userOptions = DB::table('useroption')
            ->select(
                'immediateOutOfFundsNotificationMSISDN',
                'immediateOutOfFundsNotificationNetId',
                'immediateSMSReminderon',
                'immediateOutOfFundsNotificationEmail',
                'immediateEmailReminderon',
                'lastSent102Error'
            )
            ->where('userref', $usersBigId)
            ->first();

        if (!$userOptions) {
            // Create default options if they don't exist
            DB::table('useroption')->insert([
                'userref' => $usersBigId,
                'immediateEmailReminderon' => 'y',
                'immediateSMSReminderon' => 'n',
                'immediateOutOfFundsNotificationEmail' => $email,
                'lastSent102Error' => null,
            ]);
            
            $userOptions = DB::table('useroption')->where('userref', $usersBigId)->first();
        }

        // Check if both notifications are disabled
        if ($userOptions->immediateEmailReminderon == 'n' && $userOptions->immediateSMSReminderon == 'n') {
            Log::info('User has disabled all insufficient funds notifications', ['user' => $username]);
            return;
        }

        // Check time since last notification (prevent spam - 1 hour minimum gap)
        if ($userOptions->lastSent102Error) {
            $lastSent = Carbon::parse($userOptions->lastSent102Error);
            $hoursSinceLastSend = $lastSent->diffInSeconds(Carbon::now());
            
            if ($hoursSinceLastSend < 3600) { // Less than 1 hour
                Log::info('Too soon since last insufficient funds alert', [
                    'user' => $username,
                    'seconds_since_last' => $hoursSinceLastSend
                ]);
                return;
            }
        }

        $sentSomething = false;

        // Send push notification to mobile app
        try {
            $pushQueueService = new PushNotificationQueueService();
            $pushQueued = $pushQueueService->queueWalletInsufficientNotification($usersBigId, $username);
            
            if ($pushQueued) {
                Log::info('Insufficient funds push notification queued', [
                    'user' => $username,
                    'bigid' => $usersBigId
                ]);
                $sentSomething = true;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to queue push notification for insufficient funds', [
                'user' => $username,
                'error' => $e->getMessage()
            ]);
        }

        // Send email notification if enabled
        if ($userOptions->immediateEmailReminderon == 'y') {
            $notificationEmail = $userOptions->immediateOutOfFundsNotificationEmail ?: $email;
            
            if ($notificationEmail) {
                try {
                    // Check if RabbitMQ is available
                    $useQueue = env('RABBITMQ_ENABLED', true);
                    
                    if ($useQueue) {
                        try {
                            // Try to use RabbitMQ queue
                            $emailQueue = new EmailQueueService();
                            $queued = $emailQueue->queueEmail(
                                InsufficientFundsAlertMail::class,
                                $notificationEmail,
                                [
                                    'username' => $username,
                                    'contact_name' => $contactName ?: 'Customer',
                                    'login_url' => config('app.url') . '/login'
                                ],
                                [],  // No CC recipients
                                8    // High priority
                            );
                            
                            if ($queued) {
                                Log::info('Insufficient funds email queued via RabbitMQ', [
                                    'user' => $username,
                                    'email' => $notificationEmail
                                ]);
                                $sentSomething = true;
                            } else {
                                throw new \Exception('Failed to queue email');
                            }
                        } catch (\Exception $queueException) {
                            // Fallback to Laravel queue if RabbitMQ fails
                            Log::warning('RabbitMQ unavailable, using Laravel queue', [
                                'error' => $queueException->getMessage()
                            ]);
                            
                            SendEmailJob::dispatch(
                                InsufficientFundsAlertMail::class,
                                $notificationEmail,
                                [
                                    'username' => $username,
                                    'contact_name' => $contactName ?: 'Customer',
                                    'login_url' => config('app.url') . '/login'
                                ]
                            )->onConnection('emails')->onQueue('emails');
                            
                            Log::info('Insufficient funds email queued via Laravel queue', [
                                'user' => $username,
                                'email' => $notificationEmail
                            ]);
                            $sentSomething = true;
                        }
                    } else {
                        // Send email synchronously (original behavior)
                        Mail::to($notificationEmail)
                            ->send(new InsufficientFundsAlertMail([
                                'username' => $username,
                                'contact_name' => $contactName ?: 'Customer',
                                'login_url' => config('app.url') . '/login'
                            ]));
                        
                        Log::info('Insufficient funds email sent synchronously', [
                            'user' => $username,
                            'email' => $notificationEmail
                        ]);
                        $sentSomething = true;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send insufficient funds email', [
                        'user' => $username,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Send SMS notification if enabled
        if ($userOptions->immediateSMSReminderon == 'y' && $userOptions->immediateOutOfFundsNotificationMSISDN) {
            try {
                $this->sendSmsNotification(
                    $userOptions->immediateOutOfFundsNotificationMSISDN,
                    $userOptions->immediateOutOfFundsNotificationNetId,
                    "SMS Expert Alert: SMS Send error due to insufficient funds. A/c: {$username}. Go to " . config('app.url') . ", click Login, buy credit on SMS Wallet page."
                );
                
                Log::info('Insufficient funds SMS sent', [
                    'user' => $username,
                    'msisdn' => $userOptions->immediateOutOfFundsNotificationMSISDN
                ]);
                
                $sentSomething = true;
            } catch (\Exception $e) {
                Log::error('Failed to send insufficient funds SMS', [
                    'user' => $username,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Update last sent timestamp
        if ($sentSomething) {
            DB::table('useroption')
                ->where('userref', $usersBigId)
                ->update([
                    'lastSent102Error' => Carbon::now(),
                ]);

            // useroption changed → rebuild this account's cache (Phase 2).
            app(\App\Services\TableCache::class)->rebuildUseroption($usersBigId);
        }
    }

    /**
     * Send SMS notification for insufficient funds
     *
     * @param string $msisdn
     * @param string $networkId
     * @param string $message
     */
    private function sendSmsNotification(string $msisdn, ?string $networkId, string $message): void
    {
        // This would integrate with your SMS sending service
        // For now, we'll just log it
        Log::info('SMS notification queued', [
            'msisdn' => $msisdn,
            'network' => $networkId,
            'message' => $message
        ]);
    }

    /**
     * Calculate total SMS cost based on country-specific rates
     *
     * @param object $user
     * @param array $phoneNumbers
     * @return float
     */
    private function calculateTotalSmsCost($user, array $phoneNumbers): float
    {
        if (empty($phoneNumbers)) {
            return 0;
        }

        $totalCost = 0;
        $defaultRate = $user->common_sms_rate ?? 0.009; // Default rate if not set

        foreach ($phoneNumbers as $phoneNumber) {
            // Extract country code from phone number
            $countryDialCode = $this->extractCountryCode($phoneNumber);
            
            // Get country information
            $country = DB::table('country')
                ->where('dialcode', $countryDialCode)
                ->first();
            
            if ($country) {
                // Get user-specific rate for this country
                $userRate = DB::table('user_cost')
                    ->where('bigid', $user->id)
                    ->where('country_id', $country->id)
                     ->where('status',  1)
                    ->first();
                
                if ($userRate && $userRate->rate > 0) {
                    $ratePerSMS = $userRate->rate;
                    Log::info("Using user-specific rate for country", [
                        'user_id' => $user->id,
                        'country' => $country->name ?? $countryDialCode,
                        'rate' => $ratePerSMS
                    ]);
                } else {
                    // Use default rate if no specific rate is configured
                    $ratePerSMS = $defaultRate;
                    Log::info("Using default rate", [
                        'user_id' => $user->id,
                        'country' => $country->name ?? $countryDialCode,
                        'rate' => $ratePerSMS
                    ]);
                }
            } else {
                // Country not found, use default rate
                $ratePerSMS = $defaultRate;
                Log::warning("Country not found for dial code, using default rate", [
                    'dial_code' => $countryDialCode,
                    'phone' => $phoneNumber,
                    'rate' => $ratePerSMS
                ]);
            }
            
            $totalCost += $ratePerSMS;
        }
        
        return $totalCost;
    }

    /**
     * Extract country code from phone number
     *
     * @param string $phoneNumber
     * @return string
     */
    private function extractCountryCode($phoneNumber): string
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Fetch all country dial codes from the database ordered by length desc (longest first)
        $countries = DB::table('country')
            ->select('dialcode', 'iso_code')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();

        // Loop through each country and check if phone number starts with dialcode
        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country->dialcode;
            }
        }

        // Fallback: default to '44' (UK) if nothing matches
        return '44';
    }

    /**
     * Get current wallet status for a user
     *
     * @param string $userBigId
     * @return array
     */
    public function getWalletStatus(string $userBigId): array
    {
        $user = DB::table('users')
            ->select('smsg_wallet', 'smsg_server1_sent', 'smsg_server2_sent')
            ->where('bigid', $userBigId)
            ->first();

        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }

        $currentWallet = sprintf("%.2f", $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent);

        return [
            'status' => 'ok',
            'balance' => $currentWallet,
            'total_wallet' => $user->smsg_wallet,
            'server1_used' => $user->smsg_server1_sent,
            'server2_used' => $user->smsg_server2_sent,
            'currency' => 'GBP'
        ];
    }
}
