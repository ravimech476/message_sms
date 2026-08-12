<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Exception;

class CheckRabbitMQStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:status 
                            {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check RabbitMQ connection status and server information';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Checking RabbitMQ Status...');
        $this->info('================================');
        
        // Configuration
        $host = env('RABBITMQ_HOST', 'localhost');
        $port = env('RABBITMQ_PORT', 5672);
        $user = env('RABBITMQ_USER', 'guest');
        $vhost = env('RABBITMQ_VHOST', '/');
        $managementPort = 15672;
        
        $this->info("Configuration:");
        $this->info("  Host: {$host}:{$port}");
        $this->info("  User: {$user}");
        $this->info("  VHost: {$vhost}");
        $this->info("");
        
        // Check AMQP Connection
        $this->info("Testing AMQP Connection...");
        try {
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                env('RABBITMQ_PASSWORD', 'guest'),
                $vhost,
                false,  // insist
                'AMQPLAIN',  // login_method
                null,   // login_response
                'en_US', // locale
                5.0,    // connection_timeout
                5.0     // read_write_timeout
            );
            
            $this->info("✅ <fg=green>RabbitMQ is RUNNING</>");
            
            $channel = $connection->channel();
            
            if ($this->option('detailed')) {
                $this->showDetailedInfo($channel);
            } else {
                $this->showBasicInfo($channel);
            }
            
            $channel->close();
            $connection->close();
            
        } catch (Exception $e) {
            $this->error("❌ <fg=red>RabbitMQ is NOT RUNNING</>");
            $this->error("Error: " . $e->getMessage());
            
            $this->info("\n📋 How to start RabbitMQ:");
            
            if (PHP_OS_FAMILY === 'Windows') {
                $this->info("\nOn Windows:");
                $this->info("  1. Open PowerShell as Administrator");
                $this->info("  2. Run: net start RabbitMQ");
                $this->info("\nOr:");
                $this->info("  1. Open Services (services.msc)");
                $this->info("  2. Find 'RabbitMQ' service");
                $this->info("  3. Right-click and select 'Start'");
                
            } elseif (PHP_OS_FAMILY === 'Linux') {
                $this->info("\nOn Linux:");
                $this->info("  sudo systemctl start rabbitmq-server");
                $this->info("\nCheck status:");
                $this->info("  sudo systemctl status rabbitmq-server");
                
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                $this->info("\nOn Mac:");
                $this->info("  brew services start rabbitmq");
                $this->info("\nOr run in foreground:");
                $this->info("  rabbitmq-server");
            }
            
            $this->info("\n🌐 Management Interface:");
            $this->info("  URL: http://{$host}:{$managementPort}");
            $this->info("  Username: guest");
            $this->info("  Password: guest");
            
            return 1;
        }
        
        // Check Management Interface
        $this->info("\n🌐 Management Interface:");
        $managementUrl = "http://{$host}:{$managementPort}";
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $managementUrl . '/api/overview');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . env('RABBITMQ_PASSWORD', 'guest'));
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $this->info("✅ Management interface is accessible");
                $this->info("   URL: {$managementUrl}");
                
                if ($this->option('detailed')) {
                    $data = json_decode($response, true);
                    if ($data) {
                        $this->info("   RabbitMQ Version: " . ($data['rabbitmq_version'] ?? 'Unknown'));
                        $this->info("   Erlang Version: " . ($data['erlang_version'] ?? 'Unknown'));
                    }
                }
            } else {
                $this->warn("⚠️  Management interface not accessible (HTTP {$httpCode})");
                $this->info("   Enable it with: rabbitmq-plugins enable rabbitmq_management");
            }
        } catch (Exception $e) {
            $this->warn("⚠️  Could not check management interface");
        }
        
        return 0;
    }
    
    /**
     * Show basic queue information
     */
    private function showBasicInfo($channel)
    {
        $this->info("\n📊 Queue Status:");
        
        $queues = [
            'sms.outbound' => 'Outbound SMS',
            'sms.priority' => 'Priority SMS',
            'sms.failed' => 'Failed/Retry',
            'sms.dead' => 'Dead Letter',
            'sms.dlr' => 'Delivery Receipts'
        ];
        
        $totalMessages = 0;
        $queueCount = 0;
        
        foreach ($queues as $queueName => $description) {
            try {
                list($queue, $messageCount, $consumerCount) = $channel->queue_declare(
                    $queueName,
                    true,  // passive
                    false, false, false
                );
                
                $status = $messageCount > 0 ? "<fg=yellow>{$messageCount}</>" : "<fg=green>0</>";
                $this->info("   {$description}: {$status} messages");
                
                $totalMessages += $messageCount;
                $queueCount++;
                
            } catch (Exception $e) {
                $this->info("   {$description}: <fg=red>NOT FOUND</>");
            }
        }
        
        $this->info("\n📈 Summary:");
        $this->info("   Total Queues: {$queueCount}/5");
        $this->info("   Total Messages: {$totalMessages}");
    }
    
    /**
     * Show detailed queue information
     */
    private function showDetailedInfo($channel)
    {
        $this->info("\n📊 Detailed Queue Information:");
        
        $headers = ['Queue', 'Messages', 'Consumers', 'Status'];
        $rows = [];
        
        $queues = [
            'sms.outbound',
            'sms.priority',
            'sms.failed',
            'sms.dead',
            'sms.dlr'
        ];
        
        foreach ($queues as $queueName) {
            try {
                list($queue, $messageCount, $consumerCount) = $channel->queue_declare(
                    $queueName,
                    true,  // passive
                    false, false, false
                );
                
                $status = 'Active';
                if ($messageCount > 100) {
                    $status = '⚠️  High volume';
                } elseif ($messageCount > 1000) {
                    $status = '❌ Backlog';
                }
                
                $rows[] = [
                    $queueName,
                    $messageCount,
                    $consumerCount,
                    $status
                ];
                
            } catch (Exception $e) {
                $rows[] = [
                    $queueName,
                    'N/A',
                    'N/A',
                    '❌ Not Found'
                ];
            }
        }
        
        $this->table($headers, $rows);
        
        // Show exchange information
        $this->info("\n📮 Exchange Information:");
        try {
            $channel->exchange_declare('sms.dlx', 'direct', true, false);
            $this->info("   Dead Letter Exchange (sms.dlx): ✅ Active");
        } catch (Exception $e) {
            $this->info("   Dead Letter Exchange (sms.dlx): ❌ Not Found");
        }
    }
}
