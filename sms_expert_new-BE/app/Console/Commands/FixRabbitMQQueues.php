<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Exception;

class FixRabbitMQQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:fix 
                            {--queue= : Specific queue to fix}
                            {--all : Fix all problematic queues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix problematic RabbitMQ queues by deleting and recreating them';

    private $connection;
    private $channel;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $specificQueue = $this->option('queue');
        $fixAll = $this->option('all');
        
        if (!$specificQueue && !$fixAll) {
            $this->error('Please specify --queue=<name> or --all');
            return 1;
        }
        
        $this->info('Fixing RabbitMQ queues...');
        
        try {
            // Connect to RabbitMQ
            $this->connect();
            
            if ($fixAll) {
                $this->fixProblematicQueues();
            } else {
                $this->fixQueue($specificQueue);
            }
            
            // Show final status
            $this->showQueueStatus();
            
            // Disconnect
            $this->disconnect();
            
            $this->info('✓ Queue fix completed successfully!');
            
            return 0;
        } catch (Exception $e) {
            $this->error('Failed to fix queues: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Connect to RabbitMQ
     */
    private function connect()
    {
        $this->info('Connecting to RabbitMQ...');
        
        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'localhost'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            env('RABBITMQ_VHOST', '/')
        );
        
        $this->channel = $this->connection->channel();
        
        $this->info('✓ Connected to RabbitMQ');
    }

    /**
     * Fix all problematic queues
     */
    private function fixProblematicQueues()
    {
        $problematicQueues = [
            'sms.failed',
            'sms.priority'
        ];
        
        foreach ($problematicQueues as $queue) {
            $this->fixQueue($queue);
        }
    }

    /**
     * Fix a specific queue
     */
    private function fixQueue($queueName)
    {
        $this->info("\nFixing queue: {$queueName}");
        
        // First, check if queue exists and get message count
        $messageCount = 0;
        $messages = [];
        
        try {
            list($name, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $queueName,
                true, // passive - just check
                false, false, false
            );
            
            $this->info("  Current queue has {$messageCount} messages");
            
            if ($messageCount > 0) {
                // Ask if user wants to save messages
                if ($this->confirm("  Do you want to save these messages before deleting the queue?")) {
                    $this->info("  Retrieving messages...");
                    
                    // Get all messages without acknowledging them
                    for ($i = 0; $i < min($messageCount, 100); $i++) {
                        $msg = $this->channel->basic_get($queueName, false);
                        if ($msg) {
                            $messages[] = $msg->body;
                            // Don't acknowledge, so messages stay in queue
                            $this->channel->basic_nack($msg->delivery_info['delivery_tag'], false, true);
                        }
                    }
                    
                    $this->info("  Saved " . count($messages) . " messages");
                    
                    if ($messageCount > 100) {
                        $this->warn("  Note: Only first 100 messages were saved");
                    }
                }
            }
        } catch (Exception $e) {
            $this->warn("  Queue doesn't exist or error checking: " . $e->getMessage());
        }
        
        // Delete the queue
        try {
            $this->channel->queue_delete($queueName);
            $this->info("  ✓ Deleted queue: {$queueName}");
        } catch (Exception $e) {
            $this->error("  Failed to delete queue: " . $e->getMessage());
            
            // Try to force delete by closing and reopening channel
            $this->channel->close();
            $this->channel = $this->connection->channel();
            
            try {
                $this->channel->queue_delete($queueName);
                $this->info("  ✓ Deleted queue after reconnect: {$queueName}");
            } catch (Exception $e2) {
                $this->error("  Still failed to delete: " . $e2->getMessage());
                return;
            }
        }
        
        // Ensure channel is open
        if (!$this->channel) {
            $this->channel = $this->connection->channel();
        }
        
        // Define queue configurations
        $queueConfigs = [
            'sms.failed' => [
                'arguments' => [
                    'x-message-ttl' => 300000, // 5 minutes
                    'x-dead-letter-exchange' => '',
                    'x-dead-letter-routing-key' => env('RABBITMQ_SMS_QUEUE', 'sms.outbound')
                ],
                'description' => 'Temporary failed queue for retries'
            ],
            'sms.priority' => [
                'arguments' => [
                    'x-max-priority' => 10,
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ],
                'description' => 'High priority SMS queue'
            ],
            'sms.outbound' => [
                'arguments' => [
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ],
                'description' => 'Main SMS outbound queue'
            ],
            'sms.dead' => [
                'arguments' => [],
                'description' => 'Dead letter queue for failed messages'
            ],
            'sms.dlr' => [
                'arguments' => [],
                'description' => 'Delivery receipt queue'
            ]
        ];
        
        // Get configuration for this queue
        if (!isset($queueConfigs[$queueName])) {
            $this->error("  Unknown queue configuration for: {$queueName}");
            return;
        }
        
        $config = $queueConfigs[$queueName];
        
        // Recreate the queue with correct configuration
        try {
            // Ensure exchange exists if needed
            if (isset($config['arguments']['x-dead-letter-exchange']) && 
                $config['arguments']['x-dead-letter-exchange'] === 'sms.dlx') {
                try {
                    $this->channel->exchange_declare(
                        'sms.dlx',
                        'direct',
                        false,  // passive
                        true,   // durable
                        false   // auto_delete
                    );
                } catch (Exception $e) {
                    // Exchange might already exist
                }
            }
            
            // Create the queue
            $this->channel->queue_declare(
                $queueName,
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                !empty($config['arguments']) ? new AMQPTable($config['arguments']) : null
            );
            
            $this->info("  ✓ Recreated queue: {$queueName}");
            $this->info("    {$config['description']}");
            
            // If this is the dead queue, bind it to the exchange
            if ($queueName === 'sms.dead') {
                try {
                    $this->channel->queue_bind('sms.dead', 'sms.dlx', 'sms.dead');
                    $this->info("  ✓ Bound queue to dead letter exchange");
                } catch (Exception $e) {
                    // Binding might already exist
                }
            }
            
        } catch (Exception $e) {
            $this->error("  Failed to recreate queue: " . $e->getMessage());
            return;
        }
        
        // Re-publish saved messages if any
        if (!empty($messages)) {
            $this->info("  Re-publishing saved messages...");
            
            foreach ($messages as $messageBody) {
                try {
                    $msg = new \PhpAmqpLib\Message\AMQPMessage($messageBody, [
                        'delivery_mode' => 2 // persistent
                    ]);
                    $this->channel->basic_publish($msg, '', $queueName);
                } catch (Exception $e) {
                    $this->warn("  Failed to republish message: " . $e->getMessage());
                }
            }
            
            $this->info("  ✓ Re-published " . count($messages) . " messages");
        }
    }

    /**
     * Show queue status
     */
    private function showQueueStatus()
    {
        $this->info("\n=== Final Queue Status ===");
        
        $queues = [
            env('RABBITMQ_SMS_QUEUE', 'sms.outbound') => 'Outbound Queue',
            'sms.dead' => 'Dead Letter Queue',
            env('RABBITMQ_DLR_QUEUE', 'sms.dlr') => 'DLR Queue',
            env('RABBITMQ_FAILED_QUEUE', 'sms.failed') => 'Failed/Retry Queue',
            env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority') => 'Priority Queue'
        ];
        
        $headers = ['Queue', 'Messages', 'Consumers', 'Status'];
        $rows = [];
        
        foreach ($queues as $queue => $description) {
            try {
                list($queueName, $messageCount, $consumerCount) = $this->channel->queue_declare(
                    $queue,
                    true, // passive
                    false, false, false
                );
                
                $status = '✓ OK';
                if ($messageCount > 100) {
                    $status = '⚠ Many messages';
                }
                
                $rows[] = [
                    $queueName,
                    $messageCount,
                    $consumerCount,
                    $status
                ];
                
            } catch (Exception $e) {
                $rows[] = [
                    $queue,
                    'N/A',
                    'N/A',
                    '✗ NOT FOUND'
                ];
            }
        }
        
        $this->table($headers, $rows);
    }

    /**
     * Disconnect from RabbitMQ
     */
    private function disconnect()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
        
        $this->info("\n✓ Disconnected from RabbitMQ");
    }
}
