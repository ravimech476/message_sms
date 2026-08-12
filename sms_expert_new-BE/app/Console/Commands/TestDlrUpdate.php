<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestDlrUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:test-dlr-update 
                            {bigid : The bigid of the message to update}
                            {status=DELIVRD : DLR status (DELIVRD, EXPIRED, UNDELIV, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger a DLR update for testing';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $bigid = $this->argument('bigid');
        $status = $this->argument('status');
        
        $this->info("Testing DLR update for bigid: {$bigid}");
        $this->info("Status: {$status}");
        
        // Find the message
        $message = DB::table('smsg_log')
            ->where('bigid', $bigid)
            ->first();
            
        if (!$message) {
            $this->error("Message not found with bigid: {$bigid}");
            return 1;
        }
        
        $this->table(
            ['Field', 'Current Value'],
            [
                ['ID', $message->id],
                ['Mobile', $message->mobnum],
                ['Status', $message->sentstatus],
                ['Delivery Status', $message->deliverystatus2 ?? 'NULL'],
                ['User Price', $message->userprice ?? 'NULL'],
                ['Cost Price', $message->costprice ?? 'NULL'],
                ['Profit', $message->profit ?? 'NULL'],
            ]
        );
        
        if ($this->confirm('Do you want to update this message with DLR?')) {
            // Simulate DLR data
            $dlrData = [
                'message_id' => $message->suppliermsgref ?: uniqid(),
                'status' => $status,
                'error_code' => '000',
                'done_date' => Carbon::now(),
                'source' => $message->mobnum,
                'bigid' => $bigid
            ];
            
            // Use the SMPP service to process the DLR
            $smppService = new \App\Services\SMPP\SMPPService();
            
            // Call the update method via reflection (since it's private)
            $reflection = new \ReflectionClass($smppService);
            $method = $reflection->getMethod('updateSmsgLogWithDlr');
            $method->setAccessible(true);
            
            $method->invoke($smppService, $dlrData);
            
            // Fetch updated record
            $updated = DB::table('smsg_log')
                ->where('bigid', $bigid)
                ->first();
                
            $this->info("\nMessage updated successfully!");
            $this->table(
                ['Field', 'New Value'],
                [
                    ['Delivery Status', $updated->deliverystatus2 ?? 'NULL'],
                    ['User Price', $updated->userprice ?? 'NULL'],
                    ['Cost Price', $updated->costprice ?? 'NULL'],
                    ['Profit', $updated->profit ?? 'NULL'],
                    ['Country Code', $updated->countrydialcode ?? 'NULL'],
                ]
            );
            
            // Check if wallet was updated
            if ($updated->deliverystatus2 == 'Delivered') {
                $user = DB::table('users')
                    ->where('bigid', $message->userref)
                    ->first();
                    
                if ($user) {
                    $this->info("\nWallet updated: +{$updated->userprice} for user {$user->uname}");
                }
            }
        }
        
        return 0;
    }
}
