<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use Illuminate\Support\Facades\Log;
use Exception;

class ManageSmsQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:queue-manage 
                            {action : Action to perform: purge, count, peek}
                            {--queue=sms.outbound : Queue name}
                            {--count=10 : Number of messages to peek}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage SMS queues - purge, count, or peek at messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $queueName = $this->option('queue');
        
        try {
            $rabbitMQ = new RabbitMQService();
            
            switch ($action) {
                case 'count':
                    $stats = $rabbitMQ->getQueueStats($queueName);
                    $this->info("Queue: {$queueName}");
                    $this->info("Messages: " . ($stats['messages'] ?? 0));
                    $this->info("Consumers: " . ($stats['consumers'] ?? 0));
                    break;
                    
                case 'purge':
                    if (!$this->confirm("Are you sure you want to purge ALL messages from {$queueName}?")) {
                        $this->info("Cancelled.");
                        return 0;
                    }
                    
                    $purged = $rabbitMQ->purgeQueue($queueName);
                    $this->info("Purged {$purged} messages from {$queueName}");
                    break;
                    
                case 'peek':
                    $count = (int) $this->option('count');
                    $this->info("Peeking at first {$count} messages in {$queueName}...\n");
                    
                    $messages = $rabbitMQ->peekMessages($queueName, $count);
                    
                    if (empty($messages)) {
                        $this->info("Queue is empty.");
                    } else {
                        foreach ($messages as $i => $msg) {
                            $this->line("--- Message " . ($i + 1) . " ---");
                            $this->line("Queue ID: " . ($msg['queue_id'] ?? 'N/A'));
                            $this->line("Mobile: " . ($msg['mobile_number'] ?? 'EMPTY'));
                            $this->line("Message: " . substr($msg['message'] ?? 'EMPTY', 0, 50) . '...');
                            $this->line("Sender: " . ($msg['sender_id'] ?? 'N/A'));
                            $this->line("");
                        }
                    }
                    break;
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info("Available actions: count, purge, peek");
                    return 1;
            }
            
        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
