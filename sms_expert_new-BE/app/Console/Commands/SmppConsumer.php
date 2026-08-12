<?php

namespace App\Console\Commands;

use App\Services\Queue\RabbitMQService;
use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;

class SmppConsumer extends Command
{
    protected $signature = 'smpp:consume 
                            {--queue=sms.outbound : Queue name to consume from}
                            {--workers=1 : Number of workers}
                            {--sleep=3 : Sleep time when queue is empty}';
    
    protected $description = 'Consume SMS messages from RabbitMQ and send via SMPP';
    
    private $smsQueueService;
    private $rabbitMQ;
    private $shouldStop = false;
    
    public function __construct()
    {
        parent::__construct();
        $this->smsQueueService = new SmsQueueService();
        $this->rabbitMQ = new RabbitMQService();
    }
    
    public function handle()
    {
        $queue = $this->option('queue');
        $workers = (int) $this->option('workers');
        
        $this->info("Starting SMPP consumer for queue: {$queue} with {$workers} workers");
        
        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
        
        try {
            // Start consuming messages
            $this->rabbitMQ->consumeFromQueue(
                $queue,
                function ($data) {
                    return $this->smsQueueService->processSms($data);
                },
                $workers
            );
        } catch (\Exception $e) {
            $this->error("Consumer error: " . $e->getMessage());
            SmppLogger::vonage()->error("SMPP Consumer error", ['error' => $e->getMessage()]);
            return 1;
        }
        
        return 0;
    }
    
    public function shutdown()
    {
        $this->shouldStop = true;
        $this->info("Shutting down consumer gracefully...");
    }
}
