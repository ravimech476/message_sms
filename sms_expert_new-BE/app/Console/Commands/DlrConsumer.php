<?php

namespace App\Console\Commands;

use App\Services\Queue\RabbitMQService;
use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DlrConsumer extends Command
{
    protected $signature = 'dlr:consume 
                            {--queue=sms.dlr : DLR queue name}
                            {--workers=1 : Number of workers}';
    
    protected $description = 'Consume delivery receipts from RabbitMQ';
    
    private $smsQueueService;
    private $rabbitMQ;
    
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
        
        $this->info("Starting DLR consumer for queue: {$queue}");
        
        try {
            // Start consuming DLR messages
            $this->rabbitMQ->consumeFromQueue(
                $queue,
                function ($data) {
                    return $this->smsQueueService->processDlr($data);
                },
                $workers
            );
        } catch (\Exception $e) {
            $this->error("DLR Consumer error: " . $e->getMessage());
            Log::error("DLR Consumer error", ['error' => $e->getMessage()]);
            return 1;
        }
        
        return 0;
    }
}
