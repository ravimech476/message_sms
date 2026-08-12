<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SinchSmppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * Process Scheduled Sinch SMS from smsg_log table
 *
 * NOTE: sms_queue table has been REMOVED from this project.
 * This command now processes scheduled SMS from smsg_log table
 * where suppliername='Sinch SMPP' and deliverystatus2='tomorrowonward'
 */
class ProcessSinchSmsQueue extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sinch:process-queue
                            {--limit=100 : Maximum messages to process per run}
                            {--daemon : Run continuously as daemon}';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled Sinch SMS messages from smsg_log table';

    private $sinchSmpp;
    private $running = true;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Sinch SMS Queue Processor...');
        $this->info('NOTE: Using smsg_log table for scheduled SMS (sms_queue removed)');

        try {
            $this->sinchSmpp = new SinchSmppService();
        } catch (Exception $e) {
            $this->error('Failed to initialize Sinch SMPP service: ' . $e->getMessage());
            return 1;
        }

        $limit = $this->option('limit');
        $daemon = $this->option('daemon');

        if ($daemon) {
            $this->runAsDaemon();
        } else {
            $this->processMessages($limit);
        }

        return 0;
    }

    /**
     * Run as daemon (continuous processing)
     */
    private function runAsDaemon()
    {
        $this->info('Running in daemon mode. Press Ctrl+C to stop.');

        // Handle shutdown signals
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->running = false;
                $this->info('Received shutdown signal...');
            });
            pcntl_signal(SIGTERM, function () {
                $this->running = false;
                $this->info('Received termination signal...');
            });
        }

        while ($this->running) {
            try {
                // Process pending scheduled Sinch messages from smsg_log table
                $processed = $this->processPendingMessages(10);

                if ($processed === 0) {
                    // No messages, sleep a bit
                    sleep(1);
                }

                // Handle signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

            } catch (Exception $e) {
                Log::error('Sinch Queue Daemon Error: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());

                // Wait before retry
                sleep(5);
            }
        }

        $this->info('Daemon stopped.');
    }

    /**
     * Process a limited number of messages
     */
    private function processMessages(int $limit)
    {
        $this->info("Processing up to {$limit} scheduled Sinch messages...");

        $totalProcessed = $this->processPendingMessages($limit);

        $this->info("Completed: {$totalProcessed} processed.");
    }

    /**
     * Process pending messages from smsg_log table
     * Looks for scheduled SMS where it's time to send
     */
    private function processPendingMessages(int $limit): int
    {
        $processed = 0;
        $now = Carbon::now('Europe/London');

        // Get scheduled Sinch messages that are due to be sent
        // These are identified by suppliername='Sinch SMPP' and deliverystatus2='tomorrowonward'
        // and dosendtime <= now
        $messages = DB::table('smsg_log')
            ->where(function ($query) {
                $query->where('suppliername', 'Sinch SMPP')
                    ->orWhere('aggregator_dlrmsg', 'like', 'Scheduled - %mblox%')
                    ->orWhere('aggregator_dlrmsg', 'like', 'Scheduled - %sinch%');
            })
            ->where('deliverystatus2', 'tomorrowonward')
            ->where('sentstatus', 'pending')
            ->where('dosendtime', '<=', $now->format('YmdHis'))
            ->orderBy('dosendtime', 'asc')
            ->limit($limit)
            ->get();

        foreach ($messages as $message) {
            try {
                $bigid = $message->bigid;
                $mobile = $message->mobnum;

                $this->line("Processing: {$bigid} -> {$mobile}");

                // Mark as processing
                DB::table('smsg_log')
                    ->where('id', $message->id)
                    ->update([
                        'sentstatus' => 'sending',
                        'sentstatustext' => 'Sending via Sinch SMPP'
                    ]);

                // Send via Sinch SMPP
                $result = $this->sinchSmpp->sendSMS(
                    $mobile,
                    $message->text,
                    $message->originator,
                    5, // priority
                    null, // queueId
                    'ControlPanel',
                    $bigid, // referenceId for wallet deduction
                    null // scheduleDeliveryTime - sending now
                );

                if ($result['success']) {
                    // Update smsg_log with success
                    DB::table('smsg_log')
                        ->where('id', $message->id)
                        ->update([
                            'sentstatus' => 'ok',
                            'sentstatustext' => 'Sent via Sinch SMPP',
                            'suppliermsgref' => $result['message_id'] ?? '',
                            'timesent' => Carbon::now('Europe/London')->format('YmdHis'),
                            'deliverystatus2' => 'acked',  // OLD SYSTEM: sent to network, awaiting DLR // Now waiting for DLR
                        ]);

                    $this->info("  ✓ Sent successfully: " . ($result['message_id'] ?? ''));
                    $processed++;
                } else {
                    // Update smsg_log with failure
                    DB::table('smsg_log')
                        ->where('id', $message->id)
                        ->update([
                            'sentstatus' => 'fail',
                            'sentstatustext' => 'Sinch SMPP failed: ' . ($result['error'] ?? 'Unknown error'),
                            'deliverystatus2' => 'Non Delivered',
                        ]);

                    $this->warn("  ✗ Failed: " . ($result['error'] ?? 'Unknown error'));
                }

            } catch (Exception $e) {
                $this->error("  ✗ Exception: " . $e->getMessage());
                Log::error('Sinch Queue Process Error', [
                    'bigid' => $message->bigid ?? 'unknown',
                    'error' => $e->getMessage()
                ]);

                // Update smsg_log with error
                DB::table('smsg_log')
                    ->where('id', $message->id)
                    ->update([
                        'sentstatus' => 'fail',
                        'sentstatustext' => 'Exception: ' . $e->getMessage(),
                        'deliverystatus2' => 'Non Delivered',
                    ]);
            }
        }

        return $processed;
    }
}
