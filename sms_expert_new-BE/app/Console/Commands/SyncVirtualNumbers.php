<?php

namespace App\Console\Commands;

use App\Jobs\SyncVirtualNumbersJob;
use Illuminate\Console\Command;
use App\Traits\LogsCronExecution;

class SyncVirtualNumbers extends Command
{
    use LogsCronExecution;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'virtualnumbers:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync virtual numbers from Nexmo API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return $this->executeWithLogging('virtualnumbers:sync', function () {
             $startTime = now();
            // Create dated log file path
            $logDate = $startTime->format('Y-m-d');
            $logDir = storage_path("logs/{$logDate}");
            $commandName = 'virtualnumbers:sync';

            // Create directory if it doesn't exist
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }

            // Sanitize command name for filename
            $logFileName = str_replace([':', ' '], ['_', '_'], $commandName) . '.log';
            $logFilePath = $logDir . '/' . $logFileName;
             $this->writeToLogFile($logFilePath,'Starting virtual numbers sync...');

            SyncVirtualNumbersJob::dispatch();
         $this->writeToLogFile($logFilePath,'Sync job dispatched successfully!');
            
            return 'Success: Virtual numbers sync job dispatched';
        });
    }
}
