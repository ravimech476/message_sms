<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\Queue\EmailQueueService;
use App\Jobs\SendEmailJob;
use App\Mail\WalletReminderMail;
use App\Services\Queue\PushNotificationQueueService;
use App\Models\UserNotification;
use App\Traits\LogsCronExecution;

class SendWalletRemindersCommand extends Command
{
    use LogsCronExecution;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:send-reminders 
                            {--dry-run : Show what would be sent without actually sending}
                            {--email= : Send test to specific email only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send low wallet balance reminder emails to users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return $this->executeWithLogging('wallet:send-reminders', function () {
            $startTime = now();
            // Create dated log file path
            $logDate = $startTime->format('Y-m-d');
            $logDir = storage_path("logs/{$logDate}");
            $commandName = 'wallet:send-reminders';

            // Create directory if it doesn't exist
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }

            // Sanitize command name for filename
            $logFileName = str_replace([':', ' '], ['_', '_'], $commandName) . '.log';
            $logFilePath = $logDir . '/' . $logFileName;
            $isDryRun = $this->option('dry-run');
            $testEmail = $this->option('email');

            $this->writeToLogFile($logFilePath, 'Starting wallet reminder process...');

            if ($isDryRun) {
                $this->writeToLogFile($logFilePath, 'DRY RUN MODE - No emails will be sent');
            }

            // Get users who need reminders
            $users = $this->getUsersNeedingReminders($testEmail);

            if ($users->isEmpty()) {
                $this->writeToLogFile($logFilePath, 'No users need wallet reminders at this time.');
                return 0;
            }

            $this->writeToLogFile($logFilePath, "Found {$users->count()} users needing reminders");

            $successCount = 0;
            $failCount = 0;
            $reportData = [];

            foreach ($users as $user) {
                try {
                    $firstName = $this->getFirstName($user->contactname, $user->firstname);
                    $businessName = urldecode($user->busname);
                    $walletBalance = sprintf('%01.2f', $user->money);

                    $this->writeToLogFile($logFilePath, "Processing: {$firstName} ({$user->contactemail}) - Balance: £{$walletBalance}");

                    if (!$isDryRun) {
                        // Send the email
                        $sent = $this->sendReminderEmail($user, $firstName, $walletBalance);

                        if ($sent) {
                            // Update last sent date
                            DB::table('userreminder')
                                ->where('usersbigidref', $user->usersbigidref)
                                ->update(['lastsent' => Carbon::now()]);

                            $successCount++;
                            $this->writeToLogFile($logFilePath, "✓ Email sent successfully");

                            // Send push notification to mobile app
                            try {
                                $pushQueue = new PushNotificationQueueService();
                                $pushQueued = $pushQueue->queueWalletLowNotification(
                                    $user->usersbigidref,
                                    $walletBalance,
                                    $user->uname
                                );
                                
                                if ($pushQueued) {
                                    $this->writeToLogFile($logFilePath, "✓ Push notification queued");
                                }
                            } catch (\Exception $pushError) {
                                $this->writeToLogFile($logFilePath, "✗ Push notification failed: " . $pushError->getMessage());
                            }
                        } else {
                            $failCount++;
                            $this->writeToLogFile($logFilePath, "✗ Failed to send");
                        }
                    } else {
                        $this->writeToLogFile($logFilePath, "  [DRY RUN] Would send email and push notification");
                        $successCount++;
                    }

                    // Add to report
                    $reportData[] = [
                        'name' => $firstName,
                        'business' => $businessName,
                        'email' => $user->contactemail,
                        'balance' => $walletBalance,
                        'username' => $user->uname
                    ];

                    // Small delay between emails to avoid overwhelming the server
                    usleep(500000); // 0.5 seconds

                } catch (\Exception $e) {
                    $failCount++;
                    $this->writeToLogFile($logFilePath, "Error processing user {$user->contactemail}: " . $e->getMessage());
                    Log::error('Wallet reminder error', [
                        'user' => $user->contactemail,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Send summary report
            if (!empty($reportData) && !$isDryRun) {
                $this->sendDailyReport($reportData, $logFilePath);
            }

            $this->writeToLogFile($logFilePath, "\n" . str_repeat('=', 50));
            $this->writeToLogFile($logFilePath, "Wallet Reminder Summary:");
            $this->writeToLogFile($logFilePath, "  Successful: {$successCount}");
            $this->writeToLogFile($logFilePath, "  Failed: {$failCount}");
            $this->writeToLogFile($logFilePath, "  Total: " . ($successCount + $failCount));
            $this->writeToLogFile($logFilePath, str_repeat('=', 50));

            return "Success: Sent {$successCount} reminders, Failed: {$failCount}";
        });
    }

    /**
     * Get users who need wallet reminders
     * Only processes users with migration_flag = 'new' (migrated to new system)
     */
    private function getUsersNeedingReminders($testEmail = null)
    {
        $query = DB::table('userreminder')
            ->join('users', 'userreminder.usersbigidref', '=', 'users.bigid')
            ->select(
                DB::raw('(TO_DAYS(CURRENT_DATE) - TO_DAYS(lastsent)) as days_since_last'),
                'userreminder.usersbigidref',
                'users.contactemail',
                'users.contactname',
                DB::raw('(users.smsg_wallet - (users.smsg_server1_sent + users.smsg_server2_sent)) as money'),
                'users.uname',
                'users.firstname',
                'users.busname',
                'userreminder.numonremind',
                'userreminder.reminderperiod'
            )
            ->where('userreminder.reminderon', 'y')
            ->where('users.bit_disabled', 0)
            ->where('users.migration_flag', 'new'); // Only process users migrated to new system

        if ($testEmail) {
            // Test mode - only send to specific email
            $query->where('users.contactemail', $testEmail);
        } else {
            // Normal mode - check wallet balance and last sent conditions
            $query->where(function ($q) {
                $q->whereRaw('(users.smsg_wallet - (users.smsg_server1_sent + users.smsg_server2_sent)) < userreminder.numonremind')
                    ->whereRaw('(TO_DAYS(CURRENT_DATE) - TO_DAYS(lastsent)) >= reminderperiod');
            })->orWhere(function ($q) {
                // Special case for specific test emails if needed
                $q->where('users.contactemail', 'steve@secly.com')
                    ->where('userreminder.reminderon', 'y');
            });
        }

        return $query->get();
    }

    /**
     * Get first name from contact name
     */
    private function getFirstName($contactName = '', $firstName = '')
    {
        if (!empty($firstName)) {
            return $firstName;
        }

        $fullName = trim(strtolower(urldecode($contactName)));

        // Check if name has title
        $titles = ['mr', 'mrs', 'miss', 'dr'];
        $hasTitle = false;
        foreach ($titles as $title) {
            if (strpos($fullName, $title . ' ') === 0) {
                $hasTitle = true;
                break;
            }
        }

        if (strpos($fullName, ' ') !== false && !$hasTitle) {
            $newFirstName = ucfirst(substr($fullName, 0, strpos($fullName, ' ')));
        } else {
            $newFirstName = ucwords($fullName);
        }

        return $newFirstName;
    }

    /**
     * Send reminder email to user
     */
    private function sendReminderEmail($user, $firstName, $walletBalance)
    {
        try {
            $emailData = [
                'contact_name' => $firstName,
                'wallet_balance' => $walletBalance,
                'username' => $user->uname,
                'login_url' => env('CUSTOMER_DOMAIN')
            ];

            // Check if RabbitMQ is available
            $useQueue = env('RABBITMQ_ENABLED', true);

            if ($useQueue) {
                try {
                    // Try to queue via RabbitMQ
                    $emailQueue = new EmailQueueService();
                    $queued = $emailQueue->queueEmail(
                        WalletReminderMail::class,
                        $user->contactemail,
                        $emailData,
                        [],
                        5  // Normal priority
                    );

                    if ($queued) {
                        Log::info('Wallet reminder queued via RabbitMQ', [
                            'email' => $user->contactemail,
                            'balance' => $walletBalance
                        ]);
                        return true;
                    } else {
                        throw new \Exception('Failed to queue email');
                    }
                } catch (\Exception $e) {
                    // Fallback to Laravel queue
                    Log::warning('RabbitMQ unavailable for wallet reminder, using Laravel queue', [
                        'error' => $e->getMessage()
                    ]);

                    SendEmailJob::dispatch(
                        WalletReminderMail::class,
                        $user->contactemail,
                        $emailData
                    )->onConnection('emails')->onQueue('emails');

                    return true;
                }
            } else {
                // Send synchronously if queues are disabled
                \Mail::to($user->contactemail)
                    ->send(new WalletReminderMail($emailData));
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send wallet reminder', [
                'email' => $user->contactemail,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send daily report to administrators
     */
    private function sendDailyReport($reportData, $logFilePath = null)
    {
        try {
            // Build report HTML
            $reportHtml = "The following clients have been alerted by email that their wallets are now low, as shown...<br><br>";
            $reportHtml .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
            $reportHtml .= "<tr><th>Name</th><th>Business</th><th>Email</th><th>Username</th><th>Balance</th></tr>";

            foreach ($reportData as $data) {
                $reportHtml .= "<tr>";
                $reportHtml .= "<td>{$data['name']}</td>";
                $reportHtml .= "<td>{$data['business']}</td>";
                $reportHtml .= "<td>{$data['email']}</td>";
                $reportHtml .= "<td>{$data['username']}</td>";
                $reportHtml .= "<td>£{$data['balance']}</td>";
                $reportHtml .= "</tr>";
            }

            $reportHtml .= "</table>";
            $reportHtml .= "<br><br>Total users notified: " . count($reportData);

            // Send to administrators
            $adminEmails = config('reports.wallet_reminder_admin_recipients');

            foreach ($adminEmails as $adminEmail) {
                try {
                    $emailQueue = new EmailQueueService();
                    $emailQueue->queueEmail(
                        \App\Mail\WalletReminderReportMail::class,
                        $adminEmail,
                        ['report_html' => $reportHtml, 'count' => count($reportData)],
                        [],
                        3  // Lower priority
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to queue admin report, sending directly', [
                        'email' => $adminEmail,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($logFilePath) {
                $this->writeToLogFile($logFilePath, 'Daily report sent to administrators');
            }
            Log::info('Daily wallet reminder report sent to administrators', ['count' => count($reportData)]);
        } catch (\Exception $e) {
            Log::error('Failed to send daily report', ['error' => $e->getMessage()]);
        }
    }
}
