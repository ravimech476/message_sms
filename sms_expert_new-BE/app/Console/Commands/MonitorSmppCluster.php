<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SMPPService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MonitorSmppCluster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:cluster 
                            {action : Action to perform (status|test|reset|switch)}
                            {--host= : Specific host for action}
                            {--mode= : Load balancing mode (round-robin|random|least-used|failover)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and manage SMPP cluster';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->argument('action');
        
        try {
            switch ($action) {
                case 'status':
                    $this->showClusterStatus();
                    break;
                    
                case 'test':
                    $this->testClusterConnections();
                    break;
                    
                case 'reset':
                    $this->resetHostStatistics();
                    break;
                    
                case 'switch':
                    $this->switchLoadBalancingMode();
                    break;
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info("Available actions: status, test, reset, switch");
                    return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }

    /**
     * Show cluster status
     */
    private function showClusterStatus()
    {
        $this->info("\n=== SMPP Cluster Status ===");
        
        // Get configured hosts
        $hostsEnv = env('SMPP_HOSTS', env('SMPP_HOST', 'smpp1.nexmo.com'));
        $hosts = strpos($hostsEnv, ',') !== false 
            ? array_map('trim', explode(',', $hostsEnv))
            : [$hostsEnv];
        
        $this->info("Configured Hosts: " . count($hosts));
        $this->info("Load Balancing Mode: " . env('SMPP_LOAD_BALANCING_MODE', 'round-robin'));
        
        // Get cached statistics
        $hostStats = Cache::get('smpp_host_statistics', []);
        
        // Create SMPP service to get current status
        try {
            $smpp = new SMPPService();
            $stats = $smpp->getStatistics();
            
            if (isset($stats['cluster'])) {
                $this->info("Active Hosts: " . $stats['cluster']['active_hosts'] . "/" . $stats['cluster']['total_hosts']);
                $this->info("Current Host: " . ($stats['current_host'] ?? 'None'));
            }
        } catch (\Exception $e) {
            $this->warn("Could not initialize SMPP service: " . $e->getMessage());
        }
        
        // Display host table
        $this->info("\n Host Statistics:");
        $headers = ['Host', 'Status', 'Sent', 'Failed', 'Success Rate', 'Avg Response', 'Last Used', 'Last Error'];
        $rows = [];
        
        foreach ($hosts as $host) {
            // Get stats from cache
            $hostStat = $hostStats[$host] ?? [
                'is_active' => true,
                'messages_sent' => 0,
                'messages_failed' => 0,
                'response_time_avg' => 0,
                'last_used' => null,
                'last_error' => null
            ];
            
            // Get database status
            $dbStatus = DB::table('smpp_connections')
                ->where('host', $host)
                ->where('port', env('SMPP_PORT', 8000))
                ->first();
            
            $successRate = $hostStat['messages_sent'] > 0 
                ? round(($hostStat['messages_sent'] / ($hostStat['messages_sent'] + $hostStat['messages_failed'])) * 100, 2) . '%'
                : 'N/A';
            
            $status = 'Unknown';
            if ($dbStatus) {
                $status = $dbStatus->status;
            }
            if (!$hostStat['is_active']) {
                $status = 'Failed';
            }
            
            $lastUsed = $hostStat['last_used'] 
                ? Carbon::parse($hostStat['last_used'])->diffForHumans()
                : 'Never';
            
            $lastError = $hostStat['last_error'] 
                ? substr($hostStat['last_error'], 0, 30)
                : 'None';
            
            $rows[] = [
                $host,
                $status,
                $hostStat['messages_sent'],
                $hostStat['messages_failed'],
                $successRate,
                round($hostStat['response_time_avg'], 2) . ' ms',
                $lastUsed,
                $lastError
            ];
        }
        
        $this->table($headers, $rows);
        
        // Show recent activity
        $this->info("\nRecent Activity:");
        
        $recentLogs = DB::table('smpp_logs')
            ->select('host', DB::raw('COUNT(*) as count'), DB::raw('MAX(created_at) as last_activity'))
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->groupBy('host')
            ->get();
        
        if ($recentLogs->isEmpty()) {
            $this->info("No activity in the last hour");
        } else {
            foreach ($recentLogs as $log) {
                $this->info("  {$log->host}: {$log->count} PDUs, Last: " . Carbon::parse($log->last_activity)->diffForHumans());
            }
        }
        
        // Show connection history
        $this->info("\nConnection History (Last 24 Hours):");
        
        $connectionHistory = DB::table('smpp_connections')
            ->whereIn('host', $hosts)
            ->where('updated_at', '>=', Carbon::now()->subDay())
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($connectionHistory as $conn) {
            $status = $conn->status === 'connected' ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$status} {$conn->host} - " . Carbon::parse($conn->updated_at)->format('Y-m-d H:i:s') . " - {$conn->status}");
        }
    }

    /**
     * Test cluster connections
     */
    private function testClusterConnections()
    {
        $this->info("\n=== Testing SMPP Cluster Connections ===");
        
        $host = $this->option('host');
        
        // Get configured hosts
        $hostsEnv = env('SMPP_HOSTS', env('SMPP_HOST', 'smpp1.nexmo.com'));
        $hosts = strpos($hostsEnv, ',') !== false 
            ? array_map('trim', explode(',', $hostsEnv))
            : [$hostsEnv];
        
        if ($host) {
            if (!in_array($host, $hosts)) {
                $this->error("Host '{$host}' is not in configured hosts list");
                return;
            }
            $hosts = [$host];
        }
        
        $port = env('SMPP_PORT', 8000);
        $systemId = env('SMPP_SYSTEM_ID');
        
        foreach ($hosts as $testHost) {
            $this->info("\nTesting: {$testHost}:{$port}");
            
            try {
                // Test TCP connection
                $this->info("  1. Testing TCP connection...");
                $startTime = microtime(true);
                
                $socket = @fsockopen($testHost, $port, $errno, $errstr, 5);
                
                if ($socket) {
                    $tcpTime = round((microtime(true) - $startTime) * 1000, 2);
                    $this->info("     <fg=green>✓</> TCP connection successful ({$tcpTime} ms)");
                    @fclose($socket);
                    
                    // Test SMPP bind
                    $this->info("  2. Testing SMPP bind...");
                    $smpp = new SMPPService($testHost, $port);
                    
                    $bindStart = microtime(true);
                    try {
                        if ($smpp->connect($testHost)) {
                            $bindTime = round((microtime(true) - $bindStart) * 1000, 2);
                            $this->info("     <fg=green>✓</> SMPP bind successful ({$bindTime} ms)");
                            
                            // Get statistics
                            $stats = $smpp->getStatistics();
                            $this->info("     Connected: " . ($stats['connected'] ? 'Yes' : 'No'));
                            $this->info("     Bound: " . ($stats['bound'] ? 'Yes' : 'No'));
                            
                            // Send enquire link
                            $this->info("  3. Testing enquire_link...");
                            if ($smpp->enquireLink()) {
                                $this->info("     <fg=green>✓</> Enquire link successful");
                            } else {
                                $this->warn("     <fg=yellow>⚠</> Enquire link failed");
                            }
                            
                            $smpp->disconnect();
                        } else {
                            $this->error("     <fg=red>✗</> SMPP bind failed");
                        }
                    } catch (\Exception $e) {
                        $this->error("     <fg=red>✗</> SMPP error: " . $e->getMessage());
                    }
                } else {
                    $this->error("     <fg=red>✗</> TCP connection failed: {$errstr} (Error {$errno})");
                    
                    // Test DNS resolution
                    $this->info("  Testing DNS resolution...");
                    $ip = gethostbyname($testHost);
                    if ($ip !== $testHost) {
                        $this->info("     Host resolves to: {$ip}");
                    } else {
                        $this->error("     <fg=red>✗</> DNS resolution failed");
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  <fg=red>✗</> Test failed: " . $e->getMessage());
            }
        }
        
        $this->info("\n=== Test Complete ===");
    }

    /**
     * Reset host statistics
     */
    private function resetHostStatistics()
    {
        $host = $this->option('host');
        
        if ($host) {
            $this->info("Resetting statistics for host: {$host}");
        } else {
            $this->info("Resetting statistics for all hosts");
            
            if (!$this->confirm("Are you sure you want to reset all host statistics?")) {
                $this->info("Operation cancelled");
                return;
            }
        }
        
        // Get current statistics
        $hostStats = Cache::get('smpp_host_statistics', []);
        
        if ($host) {
            // Reset specific host
            if (isset($hostStats[$host])) {
                $hostStats[$host] = [
                    'messages_sent' => 0,
                    'messages_failed' => 0,
                    'last_used' => null,
                    'last_error' => null,
                    'response_time_avg' => 0,
                    'is_active' => true,
                    'failed_attempts' => 0,
                    'last_failed' => null
                ];
                $this->info("✓ Reset statistics for {$host}");
            } else {
                $this->warn("Host {$host} not found in statistics");
            }
        } else {
            // Reset all hosts
            foreach ($hostStats as $h => &$stats) {
                $stats = [
                    'messages_sent' => 0,
                    'messages_failed' => 0,
                    'last_used' => null,
                    'last_error' => null,
                    'response_time_avg' => 0,
                    'is_active' => true,
                    'failed_attempts' => 0,
                    'last_failed' => null
                ];
            }
            $this->info("✓ Reset statistics for all hosts");
        }
        
        // Save updated statistics
        Cache::put('smpp_host_statistics', $hostStats, 3600);
        
        // Also clear database records if requested
        if ($this->confirm("Also clear database connection records?")) {
            if ($host) {
                DB::table('smpp_connections')->where('host', $host)->delete();
                DB::table('smpp_logs')->where('host', $host)->delete();
            } else {
                DB::table('smpp_connections')->truncate();
                DB::table('smpp_logs')->truncate();
            }
            $this->info("✓ Database records cleared");
        }
    }

    /**
     * Switch load balancing mode
     */
    private function switchLoadBalancingMode()
    {
        $mode = $this->option('mode');
        
        if (!$mode) {
            $this->error("Please specify a mode with --mode option");
            $this->info("Available modes: round-robin, random, least-used, failover");
            return;
        }
        
        $validModes = ['round-robin', 'random', 'least-used', 'failover'];
        
        if (!in_array($mode, $validModes)) {
            $this->error("Invalid mode: {$mode}");
            $this->info("Available modes: " . implode(', ', $validModes));
            return;
        }
        
        $this->info("Switching load balancing mode to: {$mode}");
        
        // Update .env file
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);
        
        if (strpos($envContent, 'SMPP_LOAD_BALANCING_MODE=') !== false) {
            $envContent = preg_replace(
                '/SMPP_LOAD_BALANCING_MODE=.*/',
                "SMPP_LOAD_BALANCING_MODE={$mode}",
                $envContent
            );
        } else {
            $envContent .= "\nSMPP_LOAD_BALANCING_MODE={$mode}";
        }
        
        file_put_contents($envFile, $envContent);
        
        $this->info("✓ Load balancing mode updated to: {$mode}");
        $this->warn("Note: You may need to restart your queue workers for changes to take effect");
        
        // Clear cache to force reload
        Cache::forget('smpp_round_robin_index');
        
        // Show mode descriptions
        $this->info("\nMode Description:");
        switch ($mode) {
            case 'round-robin':
                $this->info("  Messages will be distributed evenly across all active hosts in sequence");
                break;
            case 'random':
                $this->info("  Each message will be sent to a randomly selected active host");
                break;
            case 'least-used':
                $this->info("  Messages will be sent to the host with the least number of sent messages");
                break;
            case 'failover':
                $this->info("  All messages will be sent to the first available host; others used only on failure");
                break;
        }
    }
}
