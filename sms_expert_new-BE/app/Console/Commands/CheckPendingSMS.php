<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;



class CheckPendingSMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:check-pending';
    

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update status of pending SMS messages';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $pending = DB::table('smsg_log')->where('sentstatus', 'pending')
        ->where('migration_flag', 'new')->get();

        foreach ($pending as $sms) {
            // You could call SMS API to get status if needed
            $this->info("Pending SMS: {$sms->mobnum}");
        }
    }
}
