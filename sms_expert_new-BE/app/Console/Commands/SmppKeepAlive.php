<?php

namespace App\Console\Commands;

use App\Services\SMPP\SMPPPoolManager;
use Illuminate\Console\Command;

class SmppKeepAlive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:keepalive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send keep-alive signals to SMPP connections';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Starting SMPP Keep-Alive service...");
        
        $smppPool = new SMPPPoolManager();
        
        while (true) {
            try {
                $smppPool->keepAlive();
                $stats = $smppPool->getStatistics();
                
                $this->info("Keep-alive sent. Active connections: {$stats['active_connections']}/{$stats['total_connections']}");
                
                // Wait for 30 seconds before next keep-alive
                sleep(30);
                
            } catch (\Exception $e) {
                $this->error("Keep-alive error: " . $e->getMessage());
                sleep(5); // Wait a bit before retry
            }
        }
        
        return 0;
    }
}
