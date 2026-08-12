<?php

namespace App\Console\Commands;

use App\Services\Queue\SmsQueueService;
use App\Services\SMPP\SMPPPoolManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SmppMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:monitor {--interval=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor SMPP connections and queue status';

    private $shouldStop = false;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'onPcntlSignal']);
            pcntl_signal(SIGINT, [$this, 'onPcntlSignal']);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        
        $this->info("Starting SMPP Monitor");
        $this->info("Refresh Interval: {$interval} seconds");
        $this->info("Press Ctrl+C to stop");
        $this->line("");
        
        $smsQueueService = new SmsQueueService();
        
        while (!$this->shouldStop) {
            // Clear screen (works on Unix/Linux)
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                system('clear');
            }
            
            $this->displayMonitor($smsQueueService);
            
            sleep($interval);
        }
        
        $this->info("\nMonitor stopped");
        
        return Command::SUCCESS;
    }

    /**
     * Display monitor dashboard
     */
    private function displayMonitor($smsQueueService)
    {
        $stats = $smsQueueService->getStatistics();
        
        // Header
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║             SMPP MONITOR - " . Carbon::now()->format('Y-m-d H:i:s') . "              ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->line("");
        
        // Queue Status
        $this->info("📬 QUEUE STATUS");
        $this->info("═══════════════════════════════════════════");
        $this->table(
            ['Queue', 'Messages', 'Consumers'],
            [
                ['SMS Outbound', $stats['queue_stats']['messages'] ?? 0, $stats['queue_stats']['consumers'] ?? 0],
                ['DLR Queue', $stats['dlr_queue_stats']['messages'] ?? 0, $stats['dlr_queue_stats']['consumers'] ?? 0],
                ['Failed Queue', $stats['failed_queue_stats']['messages'] ?? 0, $stats['failed_queue_stats']['consumers'] ?? 0],
            ]
        );
        
        // SMPP Connections
        $this->info("🔌 SMPP CONNECTIONS");
        $this->info("═══════════════════════════════════════════");
        
        if (isset($stats['smpp_pool_stats'])) {
            $this->line("Active Connections: " . $stats['smpp_pool_stats']['active_connections'] . "/" . $stats['smpp_pool_stats']['total_connections']);
            $this->line("Total Messages Sent: " . $stats['smpp_pool_stats']['total_messages_sent']);
            
            if (!empty($stats['smpp_pool_stats']['connections'])) {
                $connectionData = array_map(function($conn) {
                    return [
                        substr($conn['id'], 0, 12),
                        $conn['host'],
                        $conn['connected'] ? '✓ Connected' : '✗ Disconnected',
                        $conn['messages_sent'],
                        $conn['current_tps']
                    ];
                }, $stats['smpp_pool_stats']['connections']);
                
                $this->table(
                    ['ID', 'Host', 'Status', 'Sent', 'TPS'],
                    $connectionData
                );
            }
        }
        
        // Database Statistics
        $this->info("📊 DATABASE STATISTICS");
        $this->info("═══════════════════════════════════════════");
        $this->table(
            ['Status', 'Count'],
            [
                ['Pending', $stats['database_stats']['pending'] ?? 0],
                ['Processing', $stats['database_stats']['processing'] ?? 0],
                ['Sent', $stats['database_stats']['sent'] ?? 0],
                ['Failed', $stats['database_stats']['failed'] ?? 0],
                ['Delivered', $stats['database_stats']['delivered'] ?? 0],
            ]
        );
        
        // Today's Statistics
        if (isset($stats['today_stats']) && $stats['today_stats']) {
            $this->info("📈 TODAY'S STATISTICS");
            $this->info("═══════════════════════════════════════════");
            $this->line("Total Queued: " . ($stats['today_stats']->total_queued ?? 0));
            $this->line("Total Processed: " . ($stats['today_stats']->total_processed ?? 0));
            $this->line("Total Failed: " . ($stats['today_stats']->total_failed ?? 0));
            $this->line("Total Retried: " . ($stats['today_stats']->total_retried ?? 0));
            
            $successRate = 0;
            if ($stats['today_stats']->total_processed > 0) {
                $successRate = round(
                    (($stats['today_stats']->total_processed - $stats['today_stats']->total_failed) / 
                    $stats['today_stats']->total_processed) * 100, 
                    2
                );
            }
            $this->line("Success Rate: {$successRate}%");
        }
        
        // Recent Activity
        $this->info("");
        $this->info("📝 RECENT ACTIVITY");
        $this->info("═══════════════════════════════════════════");
        
        $recentMessages = DB::table('sms_queue')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        if ($recentMessages->count() > 0) {
            $recentData = $recentMessages->map(function($msg) {
                return [
                    substr($msg->queue_id, 0, 20),
                    substr($msg->mobile_number, -4),
                    $msg->status,
                    Carbon::parse($msg->created_at)->diffForHumans()
                ];
            })->toArray();
            
            $this->table(
                ['Queue ID', 'Mobile', 'Status', 'Time'],
                $recentData
            );
        } else {
            $this->line("No recent messages");
        }
    }

    /**
     * Handle pcntl signals (SIGTERM / SIGINT) for graceful shutdown.
     *
     * Renamed from handleSignal() — Symfony's Command base class has its own
     * handleSignal(int, int|false): int|false used by its native signal system,
     * and overriding it with a different signature now triggers a fatal in
     * PHP 8.1+ (declaration-must-be-compatible). We don't use Symfony's signal
     * subscriber here; pcntl_signal() takes any callable, so any name works.
     */
    public function onPcntlSignal(int $signal): void
    {
        $this->info("\nReceived signal {$signal}, stopping monitor...");
        $this->shouldStop = true;
    }
}
