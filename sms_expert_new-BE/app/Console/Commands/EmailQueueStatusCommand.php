<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;

class EmailQueueStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:queue-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of email queue in RabbitMQ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
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
            
            // Check email queue
            $queueName = 'email.notifications';
            
            try {
                list($queue, $messageCount, $consumerCount) = $channel->queue_declare(
                    $queueName,
                    true,   // passive - just check
                    false,
                    false,
                    false
                );
                
                $this->info("\n" . str_repeat('=', 60));
                $this->info("EMAIL QUEUE STATUS");
                $this->info(str_repeat('=', 60));
                
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Queue Name', $queue],
                        ['Messages Waiting', $messageCount],
                        ['Active Consumers', $consumerCount],
                        ['Status', $messageCount > 0 ? 'Messages pending' : 'Queue empty'],
                    ]
                );
                
                if ($messageCount > 0) {
                    $this->warn("\n⚠ There are {$messageCount} messages waiting to be processed.");
                    $this->info("Run 'php artisan rabbitmq:consume-emails' to process them.");
                } else {
                    $this->info("\n✓ Queue is empty. All emails have been processed.");
                }
                
                // Check other queues too
                $this->info("\n" . str_repeat('-', 60));
                $this->info("OTHER QUEUES:");
                $this->info(str_repeat('-', 60));
                
                $otherQueues = [
                    'sms.outbound' => 'SMS Outbound',
                    'sms.dlr' => 'SMS Delivery Reports',
                    'sms.failed' => 'Failed SMS',
                ];
                
                $queueData = [];
                foreach ($otherQueues as $qName => $description) {
                    try {
                        list($q, $count, $consumers) = $channel->queue_declare(
                            $qName,
                            true,
                            false,
                            false,
                            false
                        );
                        $queueData[] = [$description, $qName, $count, $consumers];
                    } catch (\Exception $e) {
                        $queueData[] = [$description, $qName, 'N/A', 'N/A'];
                    }
                }
                
                $this->table(
                    ['Description', 'Queue Name', 'Messages', 'Consumers'],
                    $queueData
                );
                
            } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
                if (strpos($e->getMessage(), 'NOT_FOUND') !== false) {
                    $this->error("Queue '{$queueName}' does not exist.");
                    $this->info("The queue will be created automatically when the first email is sent.");
                } else {
                    throw $e;
                }
            }
            
            $channel->close();
            $connection->close();
            
            $this->info("\n" . str_repeat('=', 60));
            $this->info("RabbitMQ Connection: ✓ Active");
            $this->info("Host: {$host}:{$port}");
            $this->info("VHost: {$vhost}");
            $this->info(str_repeat('=', 60));
            
        } catch (\Exception $e) {
            $this->error("Failed to connect to RabbitMQ: " . $e->getMessage());
            $this->error("Please check your RabbitMQ configuration in .env");
            return 1;
        }
        
        return 0;
    }
}
