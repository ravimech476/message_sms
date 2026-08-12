<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Mail\ExceptionNotificationMail;
use App\Services\Queue\EmailQueueService;
use Illuminate\Support\Facades\Mail;

class TestErrorEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:error-email
                            {--email= : Specific email to send test to}
                            {--direct : Send directly without queue}
                            {--throw : Throw actual exception to test Handler}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test error email notification system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Error Email Notification System');
        $this->info('========================================');

        // Check configuration
        $enabled = config('exception.email_enabled', false);
        $recipients = config('exception.email_recipients', []);

        $this->info('');
        $this->info('Current Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['EXCEPTION_EMAIL_ENABLED', $enabled ? 'Yes' : 'No'],
                ['EXCEPTION_EMAIL_RECIPIENTS', implode(', ', $recipients) ?: '(none)'],
                ['EXCEPTION_EMAIL_ONLY_PRODUCTION', config('exception.email_only_production') ? 'Yes' : 'No'],
                ['EXCEPTION_EMAIL_THROTTLE', config('exception.email_throttle', 5) . ' per minute'],
                ['Current Environment', app()->environment()],
            ]
        );

        if (!$enabled) {
            $this->error('Error emails are DISABLED. Set EXCEPTION_EMAIL_ENABLED=true in .env');
            return 1;
        }

        if (empty($recipients) && !$this->option('email')) {
            $this->error('No recipients configured. Set EXCEPTION_EMAIL_RECIPIENTS in .env or use --email option');
            return 1;
        }

        // Option 1: Throw actual exception to test Handler
        if ($this->option('throw')) {
            $this->warn('');
            $this->warn('Throwing test exception to test Handler.php...');
            $this->warn('Check your email inbox for the error notification.');

            throw new \Exception('Test exception from TestErrorEmailCommand - verifying error email notifications');
        }

        // Option 2: Send test email directly
        $targetEmail = $this->option('email') ?: $recipients[0];

        $this->info('');
        $this->info("Sending test error email to: {$targetEmail}");

        // Build test exception data
        $exceptionData = [
            'exception_class' => 'App\\Exceptions\\TestException',
            'exception_message' => 'This is a TEST error email to verify the notification system is working correctly.',
            'exception_code' => 500,
            'file' => '/app/Console/Commands/TestErrorEmailCommand.php',
            'line' => 75,
            'trace' => [
                [
                    'file' => '/app/Console/Commands/TestErrorEmailCommand.php',
                    'line' => 75,
                    'function' => 'App\\Console\\Commands\\TestErrorEmailCommand->handle()',
                ],
                [
                    'file' => '/vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php',
                    'line' => 36,
                    'function' => 'Illuminate\\Container\\BoundMethod::call()',
                ],
            ],
            'url' => 'php artisan test:error-email',
            'method' => 'CLI',
            'ip' => gethostbyname(gethostname()) ?: '127.0.0.1',
            'user_agent' => 'Artisan CLI',
            'referer' => null,
            'user_id' => null,
            'user_email' => null,
            'user_name' => null,
            'input' => ['command' => 'test:error-email'],
            'headers' => [],
            'environment' => app()->environment(),
            'app_name' => config('app.name', 'SMS Expert'),
            'app_url' => config('app.url'),
            'timestamp' => now()->format('Y-m-d H:i:s T'),
            'server' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => 'CLI',
                'memory_usage' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
            'request_id' => 'test_' . uniqid(),
        ];

        try {
            if ($this->option('direct')) {
                // Send directly without queue
                $this->info('Sending directly (without queue)...');
                Mail::to($targetEmail)->send(new ExceptionNotificationMail($exceptionData));
                $this->info('Email sent directly!');
            } else {
                // Send via RabbitMQ queue
                $this->info('Queueing via RabbitMQ...');
                $emailQueueService = new EmailQueueService();
                $emailQueueService->queueEmail(
                    'App\\Mail\\ExceptionNotificationMail',
                    $targetEmail,
                    ['exception_data' => $exceptionData],
                    [],
                    10
                );
                $this->info('Email queued successfully!');
                $this->warn('Make sure the email queue consumer is running: php artisan rabbitmq:consume-emails');
            }

            $this->info('');
            $this->info('✓ Test completed! Check your inbox at: ' . $targetEmail);

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to send test email: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
