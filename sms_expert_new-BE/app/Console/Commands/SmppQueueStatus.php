<?php

namespace App\Console\Commands;

use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;

class SmppQueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:queue-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show SMS queue statistics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("SMS Queue Statistics");
        $this->info("====================");
        
        try {
            $smsQueueService = new SmsQueueService();
            $stats = $smsQueueService->getStatistics();
            
            // Show RabbitMQ queue statistics
            $this->info("");
            $this->info("RabbitMQ Queues:");
            
            $headers = ['Queue Name', 'Messages', 'Consumers', 'Status'];
            $rows = [];
            
            foreach ($stats['queues'] as $queueName => $queueStats) {
                $status = isset($queueStats['error']) ? '✗ Error' : '✓ Active';
                $rows[] = [
                    $queueName,
                    $queueStats['messages'] ?? 0,
                    $queueStats['consumers'] ?? 0,
                    $status
                ];
            }
            
            $this->table($headers, $rows);
            
            // Show database statistics
            $this->info("");
            $this->info("Database Queue Status:");
            
            $headers = ['Status', 'Count'];
            $rows = [];
            
            foreach ($stats['database'] as $status => $count) {
                $rows[] = [ucfirst($status), $count];
            }
            
            $this->table($headers, $rows);
            
            // Show today's statistics
            $this->info("");
            $this->info("Today's Statistics:");
            $this->info("Queued: " . $stats['today']['queued']);
            $this->info("Processed: " . $stats['today']['processed']);
            $this->info("Failed: " . $stats['today']['failed']);
            $this->info("Retried: " . $stats['today']['retried']);
            
            // Calculate success rate
            $total = $stats['today']['processed'] + $stats['today']['failed'];
            if ($total > 0) {
                $successRate = round(($stats['today']['processed'] / $total) * 100, 2);
                $this->info("Success Rate: {$successRate}%");
            }
            
            // Show SMPP pool statistics
            $this->info("");
            $this->info("SMPP Pool Statistics:");
            $this->info("Total Connections: " . $stats['smpp']['total_connections']);
            $this->info("Active Connections: " . $stats['smpp']['active_connections']);
            
        } catch (\Exception $e) {
            $this->error("Failed to get queue status: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
