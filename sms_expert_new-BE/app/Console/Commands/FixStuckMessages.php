<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixStuckMessages extends Command
{
    protected $signature = 'sms:fix-stuck 
                            {--queue-id= : Specific queue ID to fix}
                            {--mark-sent : Mark stuck messages as sent}
                            {--clear : Clear stuck messages from queue}';

    protected $description = 'Fix stuck messages that were sent but not acknowledged';

    public function handle()
    {
        $queueId = $this->option('queue-id');
        $markSent = $this->option('mark-sent');
        $clear = $this->option('clear');
        
        $this->info("=== Fix Stuck Messages ===");
        
        // Find stuck messages
        if ($queueId) {
            $stuckMessages = DB::table('sms_queue')
                ->where('queue_id', $queueId)
                ->get();
        } else {
            // Messages that are processing for more than 5 minutes
            $stuckMessages = DB::table('sms_queue')
                ->where('status', 'processing')
                ->where('processed_at', '<', Carbon::now()->subMinutes(5))
                ->get();
            
            if ($stuckMessages->isEmpty()) {
                // Also check queued messages that have been retried many times
                $stuckMessages = DB::table('sms_queue')
                    ->where('status', 'queued')
                    ->where('retry_count', '>', 2)
                    ->get();
            }
        }
        
        if ($stuckMessages->isEmpty()) {
            $this->info("No stuck messages found.");
            
            // Show recent queue activity
            $recent = DB::table('sms_queue')
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get(['queue_id', 'status', 'mobile_number', 'retry_count', 'updated_at']);
            
            if ($recent->isNotEmpty()) {
                $this->info("\nRecent messages:");
                $headers = ['Queue ID', 'Status', 'Mobile', 'Retries', 'Updated'];
                $rows = [];
                foreach ($recent as $msg) {
                    $rows[] = [
                        substr($msg->queue_id, 0, 20) . '...',
                        $msg->status,
                        substr($msg->mobile_number, -4),
                        $msg->retry_count,
                        Carbon::parse($msg->updated_at)->diffForHumans()
                    ];
                }
                $this->table($headers, $rows);
            }
            
            return 0;
        }
        
        $this->info("Found " . count($stuckMessages) . " stuck message(s)");
        
        foreach ($stuckMessages as $message) {
            $this->info("\nMessage: " . $message->queue_id);
            $this->info("  Status: " . $message->status);
            $this->info("  Mobile: " . $message->mobile_number);
            $this->info("  Retries: " . $message->retry_count);
            
            // Check if SMS was actually sent (check smsg_log)
            $smsgLog = DB::table('smsg_log')
                ->where('mobnum', $message->mobile_number)
                ->where('timesent', '>=', Carbon::parse($message->created_at)->format('YmdHis'))
                ->first();
            
            if ($smsgLog) {
                $this->info("  <fg=green>✓ SMS was sent (found in smsg_log)</>");
                $this->info("    Sent status: " . $smsgLog->sentstatus);
                
                if ($markSent || $this->confirm("Mark this message as sent?")) {
                    DB::table('sms_queue')
                        ->where('queue_id', $message->queue_id)
                        ->update([
                            'status' => 'sent',
                            'sent_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ]);
                    
                    $this->info("  ✓ Marked as sent");
                }
            } else {
                $this->warn("  ⚠ No record in smsg_log");
                
                if ($clear || $this->confirm("Clear this message from queue?")) {
                    DB::table('sms_queue')
                        ->where('queue_id', $message->queue_id)
                        ->update([
                            'status' => 'cleared',
                            'error_message' => 'Manually cleared - stuck message',
                            'updated_at' => Carbon::now()
                        ]);
                    
                    $this->info("  ✓ Cleared from queue");
                }
            }
        }
        
        // Clear from RabbitMQ as well
        if ($markSent || $clear) {
            $this->info("\nClearing RabbitMQ queue...");
            $this->call('sms:queue', ['action' => 'clear', '--force' => true]);
        }
        
        $this->info("\n✓ Stuck messages processed");
        
        return 0;
    }
}
