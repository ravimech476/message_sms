<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ApiErrorMonitorService;
use App\Services\Queue\EmailQueueService;
use Illuminate\Http\Request;

/**
 * Test API error monitoring
 */
class TestApiErrorMonitor extends Command
{
    protected $signature = 'api:test-error-monitor 
                            {--send-email : Actually send the test email via RabbitMQ}
                            {--direct-mail : Send test email directly (bypass RabbitMQ)}';

    protected $description = 'Test the API error monitoring system';

    public function handle(ApiErrorMonitorService $errorMonitor)
    {
        $this->info('Testing API Error Monitor...');
        $this->newLine();

        // Check configuration
        $this->info('Configuration:');
        $this->line('  Email Enabled: ' . (config('api_monitor.email_enabled') ? 'Yes' : 'No'));
        $this->line('  Email Recipients: ' . implode(', ', config('api_monitor.email_recipients', ['(none configured)'])));
        $this->line('  Max Emails/Hour: ' . config('api_monitor.max_emails_per_hour'));
        $this->line('  Min Severity: ' . config('api_monitor.min_email_severity'));
        $this->line('  Log to Database: ' . (config('api_monitor.log_to_database') ? 'Yes' : 'No'));
        $this->newLine();

        // Check database table
        $this->info('Database Check:');
        try {
            $tableExists = \Schema::hasTable('api_error_logs');
            $this->line('  api_error_logs table: ' . ($tableExists ? '<fg=green>EXISTS</>' : '<fg=red>MISSING</>'));
            
            if ($tableExists) {
                $count = \DB::table('api_error_logs')->count();
                $this->line("  Total logged errors: {$count}");
            }
        } catch (\Exception $e) {
            $this->error('  Database check failed: ' . $e->getMessage());
        }
        $this->newLine();

        // Check RabbitMQ connection
        $this->info('RabbitMQ Check:');
        try {
            $emailQueue = new EmailQueueService();
            $stats = $emailQueue->getQueueStats();
            
            if (isset($stats['error'])) {
                $this->line('  Status: <fg=red>ERROR - ' . $stats['error'] . '</>');
            } else {
                $this->line('  Status: <fg=green>CONNECTED</>');
                $this->line('  Queue: ' . ($stats['queue_name'] ?? 'email.notifications'));
                $this->line('  Messages pending: ' . ($stats['messages'] ?? 0));
                $this->line('  Active consumers: ' . ($stats['consumers'] ?? 0));
            }
        } catch (\Exception $e) {
            $this->line('  Status: <fg=red>FAILED - ' . $e->getMessage() . '</>');
        }
        $this->newLine();

        // Check log file
        $this->info('Log File Check:');
        $logPath = storage_path('logs/api-errors.log');
        $this->line('  Log path: ' . $logPath);
        $this->line('  Exists: ' . (file_exists($logPath) ? '<fg=green>YES</>' : '<fg=yellow>NO (will be created on first error)</>'));
        $this->newLine();

        // Test error logging (without email)
        if ($this->confirm('Do you want to log a test error to the database?')) {
            $this->info('Logging test error...');
            
            try {
                \DB::table('api_error_logs')->insert([
                    'type' => 'test',
                    'severity' => 'low',
                    'method' => 'GET',
                    'path' => '/api/mobile/test',
                    'url' => 'http://test.local/api/mobile/test',
                    'ip_address' => '127.0.0.1',
                    'status_code' => 500,
                    'error_message' => 'This is a test error from api:test-error-monitor command',
                    'created_at' => now(),
                ]);
                
                $this->info('<fg=green>Test error logged successfully!</>');
            } catch (\Exception $e) {
                $this->error('Failed to log test error: ' . $e->getMessage());
            }
        }
        $this->newLine();

        // Test email sending via RabbitMQ
        if ($this->option('send-email')) {
            $this->testEmailViaRabbitMQ();
        } elseif ($this->option('direct-mail')) {
            $this->testEmailDirect();
        } else {
            $this->line('To test email sending via RabbitMQ: php artisan api:test-error-monitor --send-email');
            $this->line('To test email directly (bypass queue): php artisan api:test-error-monitor --direct-mail');
        }

        $this->newLine();
        $this->info('API Error Monitor test complete!');
        
        return 0;
    }

    /**
     * Test email via RabbitMQ queue
     */
    protected function testEmailViaRabbitMQ(): void
    {
        $recipients = config('api_monitor.email_recipients', []);
        
        if (empty($recipients)) {
            $this->warn('No email recipients configured!');
            $this->line('Add recipients to .env: API_MONITOR_EMAIL_RECIPIENTS=email@example.com');
            return;
        }

        if (!$this->confirm('Queue a test error notification email to RabbitMQ for: ' . implode(', ', $recipients) . '?')) {
            return;
        }
        
        $this->info('Queuing test email to RabbitMQ...');
        
        try {
            $testErrorData = $this->getTestErrorData();

            $emailQueue = new EmailQueueService();
            
            foreach ($recipients as $recipient) {
                $recipient = trim($recipient);
                if (empty($recipient)) continue;
                
                $result = $emailQueue->queueEmail(
                    \App\Mail\ApiErrorNotification::class,
                    $recipient,
                    ['errorData' => $testErrorData],
                    [],
                    10
                );
                
                if ($result) {
                    $this->info("<fg=green>✓ Email queued for: {$recipient}</>");
                } else {
                    $this->error("✗ Failed to queue email for: {$recipient}");
                }
            }
            
            $this->newLine();
            $this->info('Test email(s) queued to RabbitMQ!');
            $this->line('Run the consumer to process: php artisan rabbitmq:consume-emails');
            
        } catch (\Exception $e) {
            $this->error('Failed to queue test email: ' . $e->getMessage());
        }
    }

    /**
     * Test email directly (bypass RabbitMQ)
     */
    protected function testEmailDirect(): void
    {
        $recipients = config('api_monitor.email_recipients', []);
        
        if (empty($recipients)) {
            $this->warn('No email recipients configured!');
            $this->line('Add recipients to .env: API_MONITOR_EMAIL_RECIPIENTS=email@example.com');
            return;
        }

        if (!$this->confirm('Send a test error notification email directly to: ' . implode(', ', $recipients) . '?')) {
            return;
        }
        
        $this->info('Sending test email directly...');
        
        try {
            $testErrorData = $this->getTestErrorData();

            \Mail::to($recipients)->send(new \App\Mail\ApiErrorNotification($testErrorData));
            
            $this->info('<fg=green>Test email sent directly!</>');
            
        } catch (\Exception $e) {
            $this->error('Failed to send test email: ' . $e->getMessage());
        }
    }

    /**
     * Get test error data
     */
    protected function getTestErrorData(): array
    {
        return [
            'type' => 'test',
            'timestamp' => now()->toIso8601String(),
            'severity' => 'high',
            'request' => [
                'method' => 'POST',
                'url' => 'http://test.local/api/mobile/sms/send',
                'path' => '/api/mobile/sms/send',
                'ip' => '127.0.0.1',
                'user_agent' => 'Test Agent / Postman',
                'user_id' => 123,
                'user_bigid' => 'test_bigid_12345',
                'body' => ['to' => '447912345678', 'message' => 'Test message'],
            ],
            'exception' => [
                'class' => 'TestException',
                'message' => 'This is a TEST error notification from the api:test-error-monitor command. No action required.',
                'code' => 500,
                'file' => '/app/Http/Controllers/Api/Mobile/SmsController.php',
                'line' => 123,
                'trace' => [
                    ['file' => '/app/Http/Controllers/Api/Mobile/SmsController.php', 'line' => 123, 'function' => 'App\\Http\\Controllers\\Api\\Mobile\\SmsController->send'],
                    ['file' => '/vendor/laravel/framework/src/Illuminate/Routing/Controller.php', 'line' => 54, 'function' => 'Illuminate\\Routing\\Controller->callAction'],
                ],
            ],
            'response' => [
                'status_code' => 500,
            ],
            'environment' => [
                'app_env' => config('app.env'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ];
    }
}
