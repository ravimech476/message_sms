<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Exception;

class SetupRabbitMQQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:setup 
                            {--force : Force recreation of queues}
                            {--purge : Purge existing queues before recreation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup RabbitMQ exchanges and queues';

    private $connection;
    private $channel;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $force = $this->option('force');
        $purge = $this->option('purge');
        
        if ($force) {
            $this->warn('WARNING: Force mode will delete and recreate all queues!');
            if (!$this->confirm('Are you sure you want to continue?')) {
                $this->info('Operation cancelled');
                return 0;
            }
        }
        
        $this->info('Setting up RabbitMQ exchanges and queues...');
        
        try {
            // Connect to RabbitMQ
            $this->connect();
            
            if ($force) {
                $this->deleteExistingQueues();
            }
            
            // Setup exchanges
            $this->setupExchanges();
            
            // Setup queues
            $this->setupQueues($force, $purge);
            
            // Setup bindings
            $this->setupBindings();
            
            // Show final status
            $this->showQueueStatus();
            
            // Disconnect
            $this->disconnect();
            
            $this->info('✓ RabbitMQ setup completed successfully!');
            
            return 0;
        } catch (Exception $e) {
            $this->error('Failed to setup RabbitMQ: ' . $e->getMessage());
            
            // Try to reconnect and provide more details
            $this->tryDiagnose();
            
            return 1;
        }
    }

    /**
     * Connect to RabbitMQ
     */
    private function connect()
    {
        $this->info('Connecting to RabbitMQ...');
        
        try {
            $this->connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'localhost'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                env('RABBITMQ_VHOST', '/')
            );
            
            $this->channel = $this->connection->channel();
            
            $this->info('✓ Connected to RabbitMQ');
        } catch (Exception $e) {
            throw new Exception("Failed to connect to RabbitMQ: " . $e->getMessage());
        }
    }

    /**
     * Delete existing queues if force mode
     */
    private function deleteExistingQueues()
    {
        $this->warn('Deleting existing queues...');
        
        $queues = [
            env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
            'sms.dead',
            env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
            env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
            env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority')
        ];
        
        foreach ($queues as $queue) {
            try {
                // First try to purge the queue
                try {
                    $this->channel->queue_purge($queue);
                    $this->info("  ✓ Purged queue: {$queue}");
                } catch (Exception $e) {
                    // Queue might not exist or might be empty
                }
                
                // Then delete the queue
                $this->channel->queue_delete($queue);
                $this->info("  ✓ Deleted queue: {$queue}");
                
            } catch (Exception $e) {
                // Queue might not exist, that's OK
                $this->info("  - Queue {$queue} does not exist (OK)");
            }
        }
        
        // Also try to delete the exchange
        try {
            $this->channel->exchange_delete('sms.dlx');
            $this->info("  ✓ Deleted exchange: sms.dlx");
        } catch (Exception $e) {
            $this->info("  - Exchange sms.dlx does not exist (OK)");
        }
        
        // Close and reopen channel after deletions
        $this->channel->close();
        $this->channel = $this->connection->channel();
        $this->info('  ✓ Channel reopened');
    }

    /**
     * Setup exchanges
     */
    private function setupExchanges()
    {
        $this->info('Setting up exchanges...');
        
        try {
            // Dead Letter Exchange
            $this->channel->exchange_declare(
                'sms.dlx',
                'direct',
                false,  // passive
                true,   // durable
                false   // auto_delete
            );
            
            $this->info('  ✓ Created exchange: sms.dlx');
        } catch (Exception $e) {
            $this->warn('  Exchange sms.dlx already exists (OK)');
        }
    }

    /**
     * Setup queues
     */
    private function setupQueues($force = false, $purge = false)
    {
        $this->info('Setting up queues...');
        
        // Define queue configurations
        $queues = [
            [
                'name' => env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
                'arguments' => [
                    'x-max-priority' => 10,
                    'x-message-ttl' => 86400000, // 24 hours
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ],
                'description' => 'Main SMS outbound queue'
            ],
            [
                'name' => 'sms.dead',
                'arguments' => [],
                'description' => 'Dead letter queue for failed messages'
            ],
            [
                'name' => env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
                'arguments' => [],
                'description' => 'Delivery receipt queue'
            ],
            [
                'name' => env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
                'arguments' => [
                    'x-message-ttl' => 300000, // 5 minutes
                    'x-dead-letter-exchange' => '',
                    'x-dead-letter-routing-key' => env('RABBITMQ_SMS_QUEUE', 'sms.outbound')
                ],
                'description' => 'Temporary failed queue for retries'
            ],
            [
                'name' => env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority'),
                'arguments' => [
                    'x-max-priority' => 10,
                    'x-dead-letter-exchange' => 'sms.dlx',
                    'x-dead-letter-routing-key' => 'sms.dead'
                ],
                'description' => 'High priority SMS queue'
            ]
        ];
        
        foreach ($queues as $queue) {
            try {
                // Check if channel is still open
                if (!$this->channel) {
                    $this->channel = $this->connection->channel();
                }
                
                // Declare queue
                $this->channel->queue_declare(
                    $queue['name'],
                    false, // passive
                    true,  // durable
                    false, // exclusive
                    false, // auto_delete
                    false, // nowait
                    !empty($queue['arguments']) ? new AMQPTable($queue['arguments']) : null
                );
                
                $this->info("  ✓ Created queue: {$queue['name']}");
                $this->info("    {$queue['description']}");
                
                // Get queue info
                try {
                    list($queueName, $messageCount, $consumerCount) = $this->channel->queue_declare(
                        $queue['name'],
                        true, // passive - just check
                        false, false, false
                    );
                    
                    if ($messageCount > 0) {
                        $this->info("    Messages: {$messageCount}, Consumers: {$consumerCount}");
                        
                        if ($purge && $messageCount > 0) {
                            if ($this->confirm("    Purge {$messageCount} messages from {$queue['name']}?")) {
                                $this->channel->queue_purge($queue['name']);
                                $this->info("    ✓ Purged {$messageCount} messages");
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignore info errors
                }
                
            } catch (Exception $e) {
                $this->error("  ✗ Failed to create queue {$queue['name']}: " . $e->getMessage());
                
                // Try to recreate channel and continue
                try {
                    $this->channel = $this->connection->channel();
                } catch (Exception $channelError) {
                    throw new Exception("Channel connection lost: " . $channelError->getMessage());
                }
            }
        }
    }

    /**
     * Setup bindings
     */
    private function setupBindings()
    {
        $this->info('Setting up bindings...');
        
        try {
            // Check if channel is still open
            if (!$this->channel) {
                $this->channel = $this->connection->channel();
            }
            
            // Bind dead letter queue to dead letter exchange
            $this->channel->queue_bind('sms.dead', 'sms.dlx', 'sms.dead');
            
            $this->info('  ✓ Bound queue sms.dead to exchange sms.dlx with routing key sms.dead');
        } catch (Exception $e) {
            $this->warn('  Binding might already exist: ' . $e->getMessage());
        }
    }

    /**
     * Show queue status
     */
    private function showQueueStatus()
    {
        $this->info("\n=== Final Queue Status ===");
        
        $queues = [
            env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
            'sms.dead',
            env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
            env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
            env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority')
        ];
        
        foreach ($queues as $queue) {
            try {
                list($queueName, $messageCount, $consumerCount) = $this->channel->queue_declare(
                    $queue,
                    true, // passive
                    false, false, false
                );
                
                $this->info("  {$queueName}: {$messageCount} messages, {$consumerCount} consumers");
            } catch (Exception $e) {
                $this->error("  {$queue}: NOT FOUND");
            }
        }
    }

    /**
     * Try to diagnose connection issues
     */
    private function tryDiagnose()
    {
        $this->info("\n=== Diagnostics ===");
        
        // Check environment variables
        $this->info("Configuration:");
        $this->info("  Host: " . env('RABBITMQ_HOST', 'localhost'));
        $this->info("  Port: " . env('RABBITMQ_PORT', 5672));
        $this->info("  User: " . env('RABBITMQ_USER', 'guest'));
        $this->info("  VHost: " . env('RABBITMQ_VHOST', '/'));
        
        // Try basic connection
        try {
            $testConn = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'localhost'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                env('RABBITMQ_VHOST', '/')
            );
            $testConn->close();
            $this->info("\n✓ Basic connection successful");
        } catch (Exception $e) {
            $this->error("\n✗ Cannot connect to RabbitMQ");
            $this->error("  Error: " . $e->getMessage());
            
            $this->info("\nTroubleshooting steps:");
            $this->info("1. Check if RabbitMQ is running:");
            $this->info("   - Windows: Get-Service RabbitMQ");
            $this->info("   - Linux: sudo systemctl status rabbitmq-server");
            $this->info("2. Check management interface: http://localhost:15672");
            $this->info("3. Verify credentials in .env file");
            $this->info("4. Try default credentials: guest/guest");
        }
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
