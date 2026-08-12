<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessEmailQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-queue 
                            {--tries=3 : Number of attempts before failing}
                            {--timeout=60 : Timeout for each job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process emails from Laravel database queue (fallback when RabbitMQ is unavailable)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tries = $this->option('tries');
        $timeout = $this->option('timeout');
        
        $this->info('Processing email queue from database...');
        $this->info('Connection: emails');
        $this->info('Queue: emails');
        $this->info("Max tries: {$tries}");
        $this->info("Timeout: {$timeout} seconds");
        $this->info('Press Ctrl+C to stop');
        
        // Process the queue
        Artisan::call('queue:work', [
            'connection' => 'emails',
            '--queue' => 'emails',
            '--tries' => $tries,
            '--timeout' => $timeout,
            '--sleep' => 3,
            '--verbose' => true,
        ], $this->getOutput());
        
        return 0;
    }
}
