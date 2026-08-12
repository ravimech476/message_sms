<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SMPPService;
use Illuminate\Support\Str;

class TestSmppConnection extends Command
{
    protected $signature = 'smpp:test 
                            {--to=+919003096885 : Phone number to send test SMS}
                            {--message=Test SMS from SMPP : Test message}
                            {--debug : Show debug information}';

    protected $description = 'Test SMPP connection and send a test SMS';

    public function handle()
    {
        $to = $this->option('to');
        $message = $this->option('message');
        $debug = $this->option('debug');
        
        $this->info("=== SMPP Connection Test ===");
        $this->info("To: {$to}");
        $this->info("Message: {$message}");
        $this->info("");
        
        try {
            // Enable detailed logging if debug mode
            if ($debug) {
                config(['logging.channels.single.level' => 'debug']);
            }
            
            $this->info("1. Creating SMPP service...");
            $smpp = new SMPPService();
            
            $this->info("2. Connecting to SMPP server...");
            $smpp->connect();
            $this->info("   ✓ Connected successfully");
            
            // Get statistics
            $stats = $smpp->getStatistics();
            $this->info("   Connected: " . ($stats['connected'] ? 'Yes' : 'No'));
            $this->info("   Bound: " . ($stats['bound'] ? 'Yes' : 'No'));
            if (isset($stats['host'])) {
                $this->info("   Host: " . $stats['host']);
            }
            
            $this->info("\n3. Sending test SMS...");
            
            $result = $smpp->sendSMS(
                $to,
                $message,
                'TEST',
                5,
                'test-' . Str::uuid()
            );
            
            if ($result['success']) {
                $this->info("   ✓ SMS sent successfully!");
                $this->info("   Message ID: " . $result['message_id']);
                if (isset($result['host'])) {
                    $this->info("   Sent via: " . $result['host']);
                }
                if (isset($result['response_time_ms'])) {
                    $this->info("   Response time: " . $result['response_time_ms'] . " ms");
                }
            } else {
                $this->error("   ✗ Failed to send SMS");
                if (isset($result['error'])) {
                    $this->error("   Error: " . $result['error']);
                }
            }
            
            $this->info("\n4. Checking for delivery receipts...");
            sleep(2); // Wait a bit for DLRs
            
            $dlrCount = $smpp->processIncomingPdus();
            if ($dlrCount > 0) {
                $this->info("   ✓ Received {$dlrCount} delivery receipts");
            } else {
                $this->info("   No delivery receipts yet");
            }
            
            $this->info("\n5. Disconnecting...");
            $smpp->disconnect();
            $this->info("   ✓ Disconnected");
            
            $this->info("\n✓ Test completed successfully!");
            
        } catch (\Exception $e) {
            $this->error("\n✗ Test failed: " . $e->getMessage());
            
            if ($debug) {
                $this->error("Stack trace:");
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
        
        return 0;
    }
}
