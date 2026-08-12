<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;

class ResetEmailQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:reset-queue 
                            {--force : Force delete and recreate the queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset or recreate the email queue in RabbitMQ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        
        if ($force) {
            $this->warn('⚠️  This will delete the email queue and all pending messages!');
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        try {
            $host = env('RABBITMQ_HOST', '127.0.0.1');
            $port = env('RABBITMQ_PORT', 5672);
            $user = env('RABBITMQ_USER', 'guest');
            $password = env('RABBITMQ_PASSWORD', 'guest');
            $vhost = env('RABBITMQ_VHOST', '/');
            
            $this->info('Connecting to RabbitMQ...');
            
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost
            );
            
            $channel = $connection->channel();
            $queueName = 'email.notifications';
            $exchangeName = 'email.exchange';
            
            if ($force) {
                // Delete the queue
                try {
                    $this->info('Deleting existing queue...');
                    $channel->queue_delete($queueName);
                    $this->info('✓ Queue deleted successfully');
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'NOT_FOUND') !== false) {
                        $this->info('Queue does not exist, nothing to delete');
                    } else {
                        throw $e;
                    }
                }
            }
            
            // Create/declare the queue with proper settings
            $this->info('Creating queue with proper settings...');
            
            try {
                // Declare exchange first
                $channel->exchange_declare(
                    $exchangeName,
                    'direct',
                    false,  // passive
                    true,   // durable
                    false   // auto_delete
                );
                $this->info('✓ Exchange declared: ' . $exchangeName);
                
                // Declare queue with basic settings (no TTL or priority to avoid conflicts)
                $channel->queue_declare(
                    $queueName,
                    false,  // passive
                    true,   // durable
                    false,  // exclusive
                    false,  // auto_delete
                    false   // nowait
                );
                $this->info('✓ Queue declared: ' . $queueName);
                
                // Bind queue to exchange
                $channel->queue_bind($queueName, $exchangeName, 'email');
                $this->info('✓ Queue bound to exchange');
                
                // Check queue status
                list($queue, $messageCount, $consumerCount) = $channel->queue_declare(
                    $queueName,
                    true,   // passive - just check
                    false,
                    false,
                    false
                );
                
                $this->info("\n" . str_repeat('=', 50));
                $this->info('Queue Status:');
                $this->info('  Queue: ' . $queue);
                $this->info('  Messages: ' . $messageCount);
                $this->info('  Consumers: ' . $consumerCount);
                $this->info(str_repeat('=', 50));
                
            } catch (\Exception $e) {
                $this->error('Failed to create queue: ' . $e->getMessage());
                throw $e;
            }
            
            $channel->close();
            $connection->close();
            
            $this->info("\n✅ Email queue is ready!");
            $this->info('You can now:');
            $this->info('  1. Send test emails: php artisan email:test-queue wallet');
            $this->info('  2. Consume emails: php artisan rabbitmq:consume-emails');
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
