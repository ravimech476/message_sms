<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-queue {--timeout=300 : Maximum time to run in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process email queue from RabbitMQ';

    /**
     * Connection to RabbitMQ
     *
     * @var AMQPStreamConnection
     */
    protected $connection;

    /**
     * Channel for RabbitMQ
     *
     * @var mixed
     */
    protected $channel;

    /**
     * Queue name
     *
     * @var string
     */
    protected $queueName = 'email.notifications';

    /**
     * Exchange name
     *
     * @var string
     */
    protected $exchangeName = 'email.exchange';

    /**
     * Start time
     *
     * @var int
     */
    protected $startTime;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->startTime = time();
        $timeout = $this->option('timeout');

        $this->info('Starting email queue processor...');
        $this->info('Queue: ' . $this->queueName);
        $this->info('Timeout: ' . $timeout . ' seconds');

        $qlog = \App\Services\Logging\RabbitMQLogService::for($this->queueName);
        $qlog->info('Consumer started', ['timeout' => $timeout]);

        try {
            // Initialize RabbitMQ connection
            $this->initializeConnection();

            // Set up the consumer
            $this->setupConsumer();

            // Process messages
            $this->info('Waiting for messages. To exit press CTRL+C');
            
            while ($this->channel->is_consuming()) {
                // Check timeout
                if ((time() - $this->startTime) > $timeout) {
                    $this->info('Timeout reached. Stopping consumer...');
                    break;
                }
                
                // Process messages with a timeout
                $this->channel->wait(null, false, 5); // 5 second timeout for wait
            }

            $this->closeConnection();
            $this->info('Email queue processor stopped.');
            
        } catch (Exception $e) {
            $this->error('Error in email processor: ' . $e->getMessage());
            Log::error('Email processor error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $qlog->error('Consumer fatal error', ['error' => $e->getMessage(), 'error_at' => $e->getFile().':'.$e->getLine()]);

            $this->closeConnection();
            return 1;
        }

        return 0;
    }

    /**
     * Initialize the RabbitMQ connection
     */
    protected function initializeConnection()
    {
        try {
            // Get RabbitMQ configuration from environment
            $host = env('RABBITMQ_HOST', '127.0.0.1');
            $port = env('RABBITMQ_PORT', 5672);
            $user = env('RABBITMQ_USER', 'guest');
            $password = env('RABBITMQ_PASSWORD', 'guest');
            $vhost = env('RABBITMQ_VHOST', '/');
            
            $this->info('Connecting to RabbitMQ at ' . $host . ':' . $port . '...');
            
            // Create connection
            $this->connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost
            );
            
            $this->channel = $this->connection->channel();
            
            // Declare queue (must match the producer)
            try {
                $this->channel->queue_declare(
                    $this->queueName,
                    false,  // passive
                    true,   // durable
                    false,  // exclusive
                    false,  // auto_delete
                    false   // nowait
                );
            } catch (\PhpAmqpLib\Exception\AMQPProtocolChannelException $e) {
                if (strpos($e->getMessage(), 'PRECONDITION_FAILED') !== false) {
                    // Queue exists with different parameters, just use it as is
                    $this->info('Queue already exists, using existing configuration');
                    // Recreate channel after exception
                    $this->channel = $this->connection->channel();
                } else {
                    throw $e;
                }
            }
            
            // Declare exchange
            $this->channel->exchange_declare(
                $this->exchangeName,
                'direct',
                false,  // passive
                true,   // durable
                false   // auto_delete
            );
            
            // Bind queue to exchange
            $this->channel->queue_bind($this->queueName, $this->exchangeName, 'email');
            
            // Set QoS to process one message at a time
            $this->channel->basic_qos(null, 1, null);
            
            $this->info('Connection established successfully.');
            
        } catch (Exception $e) {
            $this->error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Set up the consumer callback
     */
    protected function setupConsumer()
    {
        $callback = function ($msg) {
            try {
                $this->info('Received message: ' . substr($msg->body, 0, 100) . '...');
                
                // Decode the message
                $data = json_decode($msg->body, true);
                
                if (!$data) {
                    throw new Exception('Invalid JSON in message');
                }
                
                // Process the email
                $this->processEmail($data);
                
                // Acknowledge the message
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

                $this->info('Email processed and acknowledged.');
                \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                    ->info('ACK — email processed', ['mailable' => $data['mailable'] ?? null]);

            } catch (Exception $e) {
                $this->error('Failed to process message: ' . $e->getMessage());
                Log::error('Failed to process email message', [
                    'error' => $e->getMessage(),
                    'message_body' => $msg->body
                ]);
                \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                    ->error('NACK (requeue=true) — email processing exception', [
                        'error'    => $e->getMessage(),
                        'error_at' => $e->getFile().':'.$e->getLine(),
                    ]);

                // Reject the message and requeue it
                $msg->delivery_info['channel']->basic_nack(
                    $msg->delivery_info['delivery_tag'],
                    false,
                    true // Requeue
                );
            }
        };
        
        // Register the consumer
        $this->channel->basic_consume(
            $this->queueName,
            '',     // Consumer tag
            false,  // No local
            false,  // No ack - we'll ack manually
            false,  // Exclusive
            false,  // No wait
            $callback
        );
    }

    /**
     * Process an email from the queue
     *
     * @param array $data
     */
    protected function processEmail(array $data)
    {
        $mailableClass = $data['mailable_class'] ?? null;
        $recipient = $data['recipient'] ?? null;
        $emailData = $data['data'] ?? [];
        $ccRecipients = $data['cc_recipients'] ?? [];
        
        if (!$mailableClass || !$recipient) {
            throw new Exception('Missing required email data');
        }
        
        $this->info('Sending email: ' . $mailableClass . ' to ' . $recipient);
        
        // Check if the mailable class exists
        if (!class_exists($mailableClass)) {
            throw new Exception('Mailable class not found: ' . $mailableClass);
        }
        
        // Create the mailable instance based on the class
        $mailable = $this->createMailable($mailableClass, $emailData);
        
        // Send the email
        $mail = Mail::to($recipient);
        
        if (!empty($ccRecipients)) {
            $mail->cc($ccRecipients);
        }
        
        $mail->send($mailable);
        
        $this->info('Email sent successfully to ' . $recipient);
        
        // Log the successful sending
        Log::info('Email sent via RabbitMQ consumer', [
            'mailable' => $mailableClass,
            'recipient' => $recipient,
            'cc' => $ccRecipients
        ]);
    }

    /**
     * Create a mailable instance based on the class name and data
     *
     * @param string $mailableClass
     * @param array $data
     * @return mixed
     */
    protected function createMailable(string $mailableClass, array $data)
    {
        // Handle different mailable classes
        switch ($mailableClass) {
            case 'App\Mail\OrderConfirmationMail':
                return new \App\Mail\OrderConfirmationMail(
                    $data['recipient'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['user_contact_name'] ?? '',
                    $data['invoice_id'] ?? 0
                );
                
            case 'App\Mail\InvoiceMail':
                // Reconstruct the user and invoice objects from arrays
                $user = isset($data['user']) ? (object) $data['user'] : null;
                $invoice = isset($data['invoice']) ? (object) $data['invoice'] : null;
                
                return new \App\Mail\InvoiceMail(
                    $data['recipient'] ?? '',
                    $data['cc_address'] ?? '',
                    $data['invoice_id'] ?? 0,
                    $user,
                    $invoice
                );
                
            case 'App\Mail\BulkThroughputWarningMail':
                return new \App\Mail\BulkThroughputWarningMail(
                    $data
                );
                
            default:
                // For other mailables, try to instantiate with the data array
                return new $mailableClass($data);
        }
    }

    /**
     * Close the RabbitMQ connection
     */
    protected function closeConnection()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
            $this->info('Connection closed.');
        } catch (Exception $e) {
            $this->error('Error closing connection: ' . $e->getMessage());
        }
    }
}
