<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\EmailQueueService;
use App\Services\WalletValidationService;
use App\Services\BulkThroughputService;
use App\Mail\InsufficientFundsAlertMail;
use App\Mail\BulkThroughputWarningMail;
use Illuminate\Support\Facades\Log;

class TestEmailQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-queue 
                            {type=wallet : Type of email to test (wallet|throughput|both)}
                            {--email= : Email address to send to}
                            {--count=1 : Number of test emails to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email queue by sending sample emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $email = $this->option('email') ?: env('MAIL_FROM_ADDRESS', 'test@example.com');
        $count = (int) $this->option('count');
        
        $this->info("Testing email queue...");
        $this->info("Sending to: {$email}");
        $this->info("Type: {$type}");
        $this->info("Count: {$count}");
        
        try {
            $emailQueue = new EmailQueueService();
            $sent = 0;
            
            for ($i = 1; $i <= $count; $i++) {
                if ($type === 'wallet' || $type === 'both') {
                    // Queue insufficient funds email
                    $queued = $emailQueue->queueEmail(
                        InsufficientFundsAlertMail::class,
                        $email,
                        [
                            'username' => 'testuser_' . $i,
                            'contact_name' => 'Test User ' . $i,
                            'login_url' => config('app.url') . '/login'
                        ],
                        [],
                        8  // High priority
                    );
                    
                    if ($queued) {
                        $this->info("✓ Queued wallet alert email #{$i}");
                        $sent++;
                    } else {
                        $this->error("✗ Failed to queue wallet alert email #{$i}");
                    }
                }
                
                if ($type === 'throughput' || $type === 'both') {
                    // Queue throughput warning email
                    $queued = $emailQueue->queueEmail(
                        BulkThroughputWarningMail::class,
                        $email,
                        [
                            'contact_name' => 'Test User ' . $i,
                            'username' => 'testuser_' . $i,
                            'business_name' => 'Test Business ' . $i,
                            'bulk_throughput' => 1000,
                            'messages_sent_today' => 1000
                        ],
                        [],
                        7  // Normal priority
                    );
                    
                    if ($queued) {
                        $this->info("✓ Queued throughput warning email #{$i}");
                        $sent++;
                    } else {
                        $this->error("✗ Failed to queue throughput warning email #{$i}");
                    }
                }
            }
            
            // Get queue statistics
            $stats = $emailQueue->getQueueStats();
            
            $this->info("\n" . str_repeat('=', 50));
            $this->info("Results:");
            $this->info("Emails queued: {$sent}");
            $this->info("Queue status:");
            $this->info("  - Queue name: " . ($stats['queue_name'] ?? 'N/A'));
            $this->info("  - Messages in queue: " . ($stats['messages'] ?? 'N/A'));
            $this->info("  - Active consumers: " . ($stats['consumers'] ?? 'N/A'));
            
            $this->info("\nTo process these emails, run:");
            $this->info("php artisan rabbitmq:consume-emails");
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('Test email queue error', ['error' => $e->getMessage()]);
            return 1;
        }
        
        return 0;
    }
}
