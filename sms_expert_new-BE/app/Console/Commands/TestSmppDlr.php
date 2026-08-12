<?php

namespace App\Console\Commands;

use App\Services\SMPP\SMPPService;
use App\Services\Queue\SmsQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestSmppDlr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:test-dlr {mobile} {--message=Test DLR from SMS Expert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMPP DLR by sending a test SMS and waiting for delivery receipt';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mobile = $this->argument('mobile');
        $message = $this->option('message');
        
        $this->info("========================================");
        $this->info("SMPP DLR Test");
        $this->info("========================================");
        $this->info("Mobile: {$mobile}");
        $this->info("Message: {$message}");
        $this->info("");
        
        try {
            // Step 1: Send SMS via SMPP
            $this->info("Step 1: Sending SMS via SMPP...");
            
            $smppService = new SMPPService();
            $smppService->connect();
            
            $queueId = 'test_dlr_' . uniqid();
            $result = $smppService->sendSMS($mobile, $message, 'SMSEXPERT', 5, $queueId);
            
            if ($result['success']) {
                $this->info("✓ SMS sent successfully");
                $this->info("  Message ID: " . $result['message_id']);
                $this->info("  Queue ID: {$queueId}");
                $this->info("");
                
                // Step 2: Wait for DLR
                $this->info("Step 2: Waiting for DLR (max 60 seconds)...");
                
                $startTime = time();
                $dlrReceived = false;
                $attempts = 0;
                
                while ((time() - $startTime) < 60 && !$dlrReceived) {
                    $attempts++;
                    
                    // Process incoming PDUs
                    $processed = $smppService->processIncomingPdus();
                    
                    if ($processed > 0) {
                        $this->info("  Processed {$processed} PDU(s)");
                    }
                    
                    // Check database for DLR
                    $dlr = DB::table('sms_dlr')
                        ->where('message_id', $result['message_id'])
                        ->orWhere('queue_id', $queueId)
                        ->first();
                    
                    if ($dlr) {
                        $dlrReceived = true;
                        $this->info("");
                        $this->info("✓ DLR Received!");
                        $this->info("  Status: " . $dlr->status);
                        $this->info("  Status Text: " . $dlr->status_text);
                        $this->info("  Submit Date: " . $dlr->submit_date);
                        $this->info("  Done Date: " . $dlr->done_date);
                        $this->info("  Error Code: " . ($dlr->error_code ?? 'None'));
                        
                        // Check smsg_log update
                        $smsgLog = DB::table('smsg_log')
                            ->where('suppliermsgref', $result['message_id'])
                            ->first();
                        
                        if ($smsgLog) {
                            $this->info("");
                            $this->info("✓ smsg_log Updated:");
                            $this->info("  Delivery Status: " . $smsgLog->deliverystatus2);
                            $this->info("  DLR Code: " . $smsgLog->aggregator_dlrcode);
                            $this->info("  DLR Message: " . $smsgLog->aggregator_dlrmsg);
                        }
                    } else {
                        // Show progress
                        if ($attempts % 10 == 0) {
                            $elapsed = time() - $startTime;
                            $this->info("  Waiting... ({$elapsed} seconds)");
                        }
                    }
                    
                    sleep(1);
                }
                
                if (!$dlrReceived) {
                    $this->warn("⚠ DLR not received within 60 seconds");
                    $this->info("This could mean:");
                    $this->info("  - The message is still being delivered");
                    $this->info("  - The carrier has not sent the DLR yet");
                    $this->info("  - There may be an issue with DLR reception");
                    $this->info("");
                    $this->info("You can check later with:");
                    $this->info("  SELECT * FROM sms_dlr WHERE message_id = '{$result['message_id']}';");
                }
                
            } else {
                $this->error("Failed to send SMS");
            }
            
            $smppService->disconnect();
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
        
        // Step 3: Show statistics
        $this->info("");
        $this->info("========================================");
        $this->info("DLR Statistics (Last 24 Hours)");
        $this->info("========================================");
        
        $stats = DB::table('sms_dlr')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'DELIVRD' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN ('EXPIRED', 'DELETED', 'UNDELIV', 'REJECTD') THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_hour
            ", [Carbon::now()->subHour()])
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->first();
        
        if ($stats && $stats->total > 0) {
            $this->info("Total DLRs: " . $stats->total);
            $this->info("Delivered: " . $stats->delivered);
            $this->info("Failed: " . $stats->failed);
            $this->info("Last Hour: " . $stats->last_hour);
            
            $deliveryRate = round(($stats->delivered / $stats->total) * 100, 2);
            $this->info("Delivery Rate: {$deliveryRate}%");
        } else {
            $this->info("No DLRs found in the last 24 hours");
        }
        
        return Command::SUCCESS;
    }
}
