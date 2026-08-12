<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use Illuminate\Support\Str;

class TestSmsQueue extends Command
{
    protected $signature = 'sms:test-queue 
                            {--to=+447700900123 : Phone number to send to}
                            {--message=Test message from queue : Message content}
                            {--count=1 : Number of test messages to add}';

    protected $description = 'Add test messages to SMS queue';

    public function handle()
    {
        $to = $this->option('to');
        $message = $this->option('message');
        $count = $this->option('count');
        
        $this->info("Adding {$count} test message(s) to queue...");
        
        try {
            $rabbit = new RabbitMQService();
            
            for ($i = 0; $i < $count; $i++) {
                $queueId = 'test-' . Str::uuid();
                $testMessage = $message . ' (' . ($i + 1) . '/' . $count . ')';
                
                $data = [
                    'queue_id' => $queueId,
                    'mobile_number' => $to,
                    'message' => $testMessage,
                    'from' => 'TEST',
                    'priority' => 5,
                    'metadata' => [
                        'type' => 'test',
                        'created_at' => now()->toIso8601String()
                    ]
                ];
                
                $rabbit->publishToQueue('sms.outbound', $data);
                
                $this->info("✓ Added message {$queueId} to queue");
            }
            
            $this->info("\n✓ Successfully added {$count} message(s) to queue");
            $this->info("Run 'php artisan sms:process-queue' to process them");
            
        } catch (\Exception $e) {
            $this->error("Failed to add test messages: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
