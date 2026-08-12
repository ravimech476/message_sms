<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\CampaignQueueService;
use Illuminate\Support\Facades\Log;

class CampaignQueueConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:consume 
                            {--single : Process only one campaign and exit}
                            {--timeout=0 : Timeout in seconds (0 = no timeout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume and process campaigns from RabbitMQ queue';

    /**
     * @var CampaignQueueService
     */
    protected $campaignQueueService;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Campaign Queue Consumer...');
        $this->info('Press Ctrl+C to stop.');
        
        Log::info('Campaign Queue Consumer started', [
            'single_mode' => $this->option('single'),
            'timeout' => $this->option('timeout')
        ]);

        try {
            $this->campaignQueueService = new CampaignQueueService();
            
            if ($this->option('single')) {
                $this->info('Running in single-campaign mode...');
                $this->processSingleCampaign();
            } else {
                $this->info('Running in continuous mode...');
                $this->startContinuousConsumer();
            }
            
        } catch (\Exception $e) {
            $this->error('Consumer error: ' . $e->getMessage());
            Log::error('Campaign Queue Consumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Process a single campaign and exit
     */
    protected function processSingleCampaign()
    {
        $this->info('Waiting for a campaign to process...');
        
        // This will process one campaign and return
        $stats = $this->campaignQueueService->getQueueStats();
        
        if (($stats['messages'] ?? 0) > 0) {
            $this->info("Found {$stats['messages']} campaign(s) in queue");
            $this->campaignQueueService->startConsumer();
        } else {
            $this->info('No campaigns in queue');
        }
    }

    /**
     * Start continuous consumer
     */
    protected function startContinuousConsumer()
    {
        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }

        $timeout = (int) $this->option('timeout');
        $startTime = time();

        while (true) {
            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                $this->info('Timeout reached. Shutting down...');
                break;
            }

            // Process signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                // Get queue stats
                $stats = $this->campaignQueueService->getQueueStats();
                $messageCount = $stats['messages'] ?? 0;

                if ($messageCount > 0) {
                    $this->info("Processing {$messageCount} campaign(s) in queue...");
                    $this->campaignQueueService->startConsumer();
                } else {
                    // No messages, wait before checking again
                    sleep(5);
                }
            } catch (\Exception $e) {
                $this->error('Error processing campaign: ' . $e->getMessage());
                Log::error('Campaign consumer loop error', [
                    'error' => $e->getMessage()
                ]);
                
                // Wait before retrying
                sleep(10);
            }

            // Touch heartbeat file
            $this->touchHeartbeat();
        }
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown($signal)
    {
        $this->info("\nReceived shutdown signal ({$signal}). Gracefully shutting down...");
        Log::info('Campaign Queue Consumer shutdown', ['signal' => $signal]);
        exit(0);
    }

    /**
     * Touch heartbeat file for monitoring
     */
    protected function touchHeartbeat()
    {
        $heartbeatFile = storage_path('logs/campaign_consumer_heartbeat');
        touch($heartbeatFile);
    }
}
