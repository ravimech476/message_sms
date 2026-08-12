<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SMPPService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;

class SmppDiagnostic extends Command
{
    protected $signature = 'smpp:diagnostic 
                            {--duration=60 : How many seconds to run the diagnostic}';

    protected $description = 'Diagnostic tool to test SMPP inbound message reception';

    public function handle()
    {
        $duration = (int)$this->option('duration');
        
        $this->info("=======================================================");
        $this->info("SMPP Inbound Diagnostic Tool");
        $this->info("=======================================================");
        $this->info("Duration: {$duration} seconds");
        $this->info("");
        
        // Step 1: Check configuration
        $this->info("Step 1: Checking SMPP Configuration...");
        $host = env('SMPP_HOST');
        $port = env('SMPP_PORT');
        $systemId = env('SMPP_SYSTEM_ID');
        $password = env('SMPP_PASSWORD') ? '****' : 'NOT SET';
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['SMPP_HOST', $host],
                ['SMPP_PORT', $port],
                ['SMPP_SYSTEM_ID', $systemId],
                ['SMPP_PASSWORD', $password],
            ]
        );
        
        if (!$host || !$port || !$systemId) {
            $this->error("✗ SMPP configuration is incomplete!");
            return 1;
        }
        $this->info("✓ Configuration OK");
        $this->info("");
        
        // Step 2: Test connection
        $this->info("Step 2: Testing SMPP Connection...");
        try {
            $smpp = new SMPPService($host, $port);
            $smpp->connect();
            $this->info("✓ Connected successfully!");
            
            $stats = $smpp->getStatistics();
            $this->table(
                ['Stat', 'Value'],
                [
                    ['Connected', $stats['connected'] ? 'YES' : 'NO'],
                    ['Bound', $stats['bound'] ? 'YES' : 'NO'],
                    ['Socket Valid', $stats['socket_valid'] ? 'YES' : 'NO'],
                ]
            );
        } catch (Exception $e) {
            $this->error("✗ Connection failed: " . $e->getMessage());
            return 1;
        }
        $this->info("");
        
        // Step 3: Listen for messages
        $this->info("Step 3: Listening for inbound messages...");
        $this->info("Duration: {$duration} seconds");
        $this->info("Send an SMS TO your shortcode now!");
        $this->info("");
        
        $startTime = time();
        $messagesReceived = 0;
        $dlrsReceived = 0;
        $enquireLinks = 0;
        $lastEnquireLink = time();
        
        while ((time() - $startTime) < $duration) {
            try {
                // Read messages
                $pdus = $smpp->readIncomingMessages(0);
                
                if (!empty($pdus)) {
                    foreach ($pdus as $pdu) {
                        $messagesReceived++;
                        
                        // Check if DLR
                        $isDlr = isset($pdu['esm_class']) && ($pdu['esm_class'] & 0x04) === 0x04;
                        
                        if ($isDlr) {
                            $dlrsReceived++;
                            $this->warn("✓ DLR Received:");
                        } else {
                            $this->info("✓ INBOUND SMS Received:");
                        }
                        
                        $this->line("   From: " . ($pdu['source_addr'] ?? 'unknown'));
                        $this->line("   To: " . ($pdu['destination_addr'] ?? 'unknown'));
                        $this->line("   Message: " . ($pdu['short_message'] ?? 'N/A'));
                        $this->line("   ESM Class: " . (isset($pdu['esm_class']) ? sprintf('0x%02X', $pdu['esm_class']) : 'N/A'));
                        $this->line("   Data Coding: " . ($pdu['data_coding'] ?? 'N/A'));
                        $this->line("   Sequence: " . ($pdu['sequence_number'] ?? 'N/A'));
                        $this->line("");
                        
                        // Log to file
                        SmppLogger::vonage()->info("SMPP Diagnostic - Message Received", $pdu);
                    }
                }
                
                // Send enquire_link every 30 seconds
                if ((time() - $lastEnquireLink) >= 30) {
                    if ($smpp->enquireLink()) {
                        $enquireLinks++;
                        $this->line("→ Enquire link sent ({$enquireLinks})");
                        $lastEnquireLink = time();
                    }
                }
                
                // Show progress
                $elapsed = time() - $startTime;
                $remaining = $duration - $elapsed;
                if ($elapsed % 10 == 0 && $elapsed > 0) {
                    $this->line("Time remaining: {$remaining}s | Messages: {$messagesReceived} | DLRs: {$dlrsReceived}");
                }
                
                usleep(100000); // 100ms
                
            } catch (Exception $e) {
                $this->error("Error during listening: " . $e->getMessage());
                SmppLogger::vonage()->error("SMPP Diagnostic Error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                break;
            }
        }
        
        // Summary
        $this->info("");
        $this->info("=======================================================");
        $this->info("Diagnostic Summary");
        $this->info("=======================================================");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total PDUs Received', $messagesReceived],
                ['Inbound SMS (MO)', $messagesReceived - $dlrsReceived],
                ['Delivery Receipts', $dlrsReceived],
                ['Enquire Links Sent', $enquireLinks],
                ['Duration', "{$duration} seconds"],
            ]
        );
        
        // Disconnect
        try {
            $smpp->disconnect();
            $this->info("✓ Disconnected successfully");
        } catch (Exception $e) {
            $this->warn("Disconnect warning: " . $e->getMessage());
        }
        
        // Diagnosis
        $this->info("");
        if ($messagesReceived == 0) {
            $this->error("⚠ No messages received during test!");
            $this->info("");
            $this->info("Possible reasons:");
            $this->info("1. SMPP binding mode - Must be TRANSCEIVER (not TRANSMITTER)");
            $this->info("2. Shortcode not configured for inbound");
            $this->info("3. No SMS sent during test period");
            $this->info("4. SMPP provider blocking inbound messages");
            $this->info("5. Network/firewall issues");
            $this->info("");
            $this->info("Recommendations:");
            $this->info("• Contact your SMPP provider to verify:");
            $this->info("  - Account is configured as TRANSCEIVER");
            $this->info("  - Shortcode/long number supports inbound SMS");
            $this->info("  - No IP restrictions");
            $this->info("• Check firewall allows incoming SMPP connections");
            $this->info("• Review logs: storage/logs/laravel.log");
        } else if ($dlrsReceived > 0 && ($messagesReceived - $dlrsReceived) == 0) {
            $this->warn("⚠ Only DLRs received, no inbound SMS!");
            $this->info("This suggests:");
            $this->info("• Outbound SMS is working (DLRs received)");
            $this->info("• Inbound SMS might not be configured");
            $this->info("• Contact SMPP provider about MO message support");
        } else {
            $this->info("✓ Diagnostic completed successfully!");
            $this->info("Inbound SMS reception is working!");
        }
        
        return 0;
    }
}
