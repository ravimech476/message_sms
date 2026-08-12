<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\SMPP\SMPPService;
use App\Services\UserRouteService;
use Exception;

class FetchPendingDlrs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:fetch-pending-dlrs 
                            {--days=1 : Number of days to look back for pending messages}
                            {--limit=1000 : Maximum number of records to process}
                            {--dry-run : Run without making actual updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch pending DLRs for SMS messages that have not received delivery receipts';

    private $smppService;
    private $processedCount = 0;
    private $updatedCount = 0;
    private $failedCount = 0;
    /** Set when Vonage returns HTTP 429 — stop hammering the per-message API this run. */
    private $rateLimited = false;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = $this->option('days');
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info("Starting Pending DLR Fetcher...");
        $this->info("Looking back {$days} days for pending messages");
        $this->info("Processing limit: {$limit} records");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No actual updates will be made");
        }

        try {
            // Initialize SMPP Service for DLR calculations
            $this->smppService = new SMPPService();

            // Find pending messages
            $pendingMessages = $this->findPendingMessages($days, $limit);
            
            if ($pendingMessages->isEmpty()) {
                $this->info("No pending messages found.");
                return 0;
            }

            $this->info("Found {$pendingMessages->count()} pending messages to process");
            
            // Process each pending message
            $bar = $this->output->createProgressBar($pendingMessages->count());
            $bar->start();

            foreach ($pendingMessages as $message) {
                $this->processPendingMessage($message, $dryRun);
                $bar->advance();

                // If Vonage rate-limited us (429), stop hammering the per-message API for
                // the rest of this run — the remaining messages stay pending and are picked
                // up next run. Prevents a 429 storm that would get us throttled harder.
                if ($this->rateLimited) {
                    $this->line('');
                    $this->warn('Nexmo rate limited (429) — stopping this run early; remaining pending messages will be retried next run.');
                    Log::warning('FetchPendingDlrs: stopped early due to Nexmo 429 rate limit');
                    break;
                }
            }

            $bar->finish();
            $this->line('');

            // Display summary
            $this->displaySummary();

            // Log the run
            $this->logCronRun();

            return 0;

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("FetchPendingDlrs Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Find pending messages that need DLR updates
     */
    private function findPendingMessages($days, $limit)
    {
        $cutoffDate = Carbon::now()->subDays($days)->format('YmdHis');
        
        return DB::table('smsg_log')
            ->where('sentstatus', 'ok')
            ->where('migration_flag', 'new')
            ->whereNull('deliverystatus2')
            ->where('timesent', '>=', $cutoffDate)
            ->whereNotNull('suppliermsgref')
            ->orderBy('timesent', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Process a single pending message
     */
    private function processPendingMessage($message, $dryRun)
    {
        $this->processedCount++;

        try {
            // Check if we can get status from external source (if API available)
            $dlrStatus = $this->fetchDlrFromProvider($message);
            
            if (!$dlrStatus) {
                // If no DLR from provider, mark as expired if older than 24 hours
                $dlrStatus = $this->determineDefaultStatus($message);
            }

            if ($dryRun) {
                $this->info("\n[DRY RUN] Would update message ID {$message->id} with status: {$dlrStatus['status']}");
                return;
            }

            // Update the message with DLR status
            $this->updateMessageWithDlr($message, $dlrStatus);
            $this->updatedCount++;

        } catch (Exception $e) {
            $this->failedCount++;
            Log::error("Failed to process pending message", [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Try to fetch DLR from provider (Nexmo/Vonage)
     */
    private function fetchDlrFromProvider($message)
    {
        // Check if we have Nexmo API credentials
        $apiKey = env('NEXMO_API_KEY');
        $apiSecret = env('NEXMO_API_SECRET');
        
        if (!$apiKey || !$apiSecret) {
            return null;
        }

        try {
            // Try to get message status from Nexmo API
            $url = "https://api.nexmo.com/v1/messages/" . $message->suppliermsgref;
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "$apiKey:$apiSecret");
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // Capture Retry-After if present (used for backoff on 429).
            $retryAfter = (int) curl_getinfo($ch, CURLINFO_RETRY_AFTER);
            curl_close($ch);

            if ($httpCode == 200) {
                $data = json_decode($response, true);

                // Map Nexmo status to our status
                return $this->mapNexmoStatus($data);
            }

            // 429 = rate limited. Flag it so the batch loop stops this run (avoid a 429
            // storm), back off briefly honouring Retry-After when Vonage sends it.
            if ($httpCode == 429) {
                $wait = $retryAfter > 0 ? min($retryAfter, 30) : 2;
                Log::warning('FetchPendingDlrs: Nexmo API rate limited (429)', [
                    'message_id'  => $message->suppliermsgref,
                    'retry_after' => $retryAfter,
                    'wait'        => $wait,
                ]);
                $this->rateLimited = true;
                sleep($wait);
                return null;
            }

        } catch (Exception $e) {
            Log::warning("Failed to fetch DLR from provider", [
                'message_id' => $message->suppliermsgref,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * Map Nexmo API status to internal status
     */
    private function mapNexmoStatus($nexmoData)
    {
        $statusMap = [
            'delivered' => 'DELIVRD',
            'expired' => 'EXPIRED',
            'failed' => 'UNDELIV',
            'rejected' => 'REJECTD',
            'accepted' => 'ACCEPTD',
            'buffered' => 'ACCEPTD',
            'unknown' => 'UNKNOWN'
        ];
        
        $nexmoStatus = $nexmoData['status'] ?? 'unknown';
        $internalStatus = $statusMap[$nexmoStatus] ?? 'UNKNOWN';
        
        return [
            'status' => $internalStatus,
            'error_code' => $nexmoData['error_code'] ?? '000',
            'done_date' => isset($nexmoData['date_finalized']) 
                ? Carbon::parse($nexmoData['date_finalized']) 
                : Carbon::now()
        ];
    }

    /**
     * Determine default status based on message age
     */
    private function determineDefaultStatus($message)
    {
        $messageTime = Carbon::createFromFormat('YmdHis', $message->timesent);
        $hoursOld = $messageTime->diffInHours(Carbon::now());
        
        // If message is older than 24 hours and no DLR, mark as expired
        if ($hoursOld > 24) {
            return [
                'status' => 'EXPIRED',
                'error_code' => '999',
                'done_date' => Carbon::now()
            ];
        }
        
        // If less than 24 hours, mark as unknown
        return [
            'status' => 'UNKNOWN',
            'error_code' => '000',
            'done_date' => Carbon::now()
        ];
    }

    /**
     * Update message with DLR information
     */
    private function updateMessageWithDlr($message, $dlrStatus)
    {
        // Prepare DLR data
        $dlrData = [
            'message_id' => $message->suppliermsgref,
            'status' => $dlrStatus['status'],
            'error_code' => $dlrStatus['error_code'],
            'done_date' => $dlrStatus['done_date'],
            'source' => $message->mobnum,
            'bigid' => $message->bigid
        ];

        // Map status for smsg_log
        $deliveryStatus = $this->mapDlrStatusForSmsgLog($dlrStatus['status']);
        
        // Calculate pricing and all fields
        $updateData = $this->calculateDlrUpdateData($message, $deliveryStatus, $dlrData);
        
        // Update smsg_log
        DB::table('smsg_log')
            ->where('id', $message->id)
            ->update($updateData);
        
        // Update user wallet if delivered
        if ($deliveryStatus == 'Delivered' && isset($updateData['userprice'])) {
            $this->updateUserWallet($message, $updateData['userprice']);
        }
        
        // Log the update
        Log::info("Updated pending DLR via cron", [
            'smsg_log_id' => $message->id,
            'bigid' => $message->bigid,
            'status' => $deliveryStatus,
            'updateData' => $updateData
        ]);
    }

    /**
     * Calculate DLR update data with pricing
     * Uses OLD SYSTEM pricing logic via smsg_userroute table
     */
    private function calculateDlrUpdateData($message, $deliveryStatus, $dlrData)
    {
        // Extract country code
        $countryCode = $this->extractCountryCode($message->mobnum);

        // Get user information
        $user = DB::table('users')
            ->where('bigid', $message->userref)
            ->first();

        if (!$user) {
            Log::warning("User not found for pricing calculation", [
                'userref' => $message->userref
            ]);

            // Return basic update without pricing
            return [
                'deliverystatus1' => 'acked',
                'deliverystatus2' => $deliveryStatus,
                'deliverytime1' => Carbon::now('Europe/London')->format('YmdHi'),
                'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
                'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                'aggregator_dlrmsg' => 'Updated via cron - ' . $deliveryStatus,
                'countrydialcode' => $countryCode
            ];
        }

        // Use OLD SYSTEM pricing logic via smsg_userroute table
        $userRouteService = app(UserRouteService::class);

        // Get route number from message or default to 7002
        $routenum = $message->routenum ?? $message->requested_route ?? 7002;

        // Get pricing using old system logic (smsg_userroute table)
        $pricing = $userRouteService->getPricingForPhoneNumber(
            $message->userref,
            $message->mobnum,
            $routenum,
            $message->numbits ?? 7,
            'alpha'
        );

        // Build update data with old system pricing
        $updateData = [
            'deliverystatus1' => 'acked',
            'deliverystatus2' => $deliveryStatus,
            'deliverytime1' => Carbon::now('Europe/London')->format('YmdHi'),
            'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
            'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
            'aggregator_dlrmsg' => 'Updated via cron - ' . $deliveryStatus,
            'userprice' => $pricing['userprice'],
            'costprice' => $pricing['costprice'],
            'profit' => $pricing['profit'],
            'countrydialcode' => $countryCode
        ];

        // Copy delivery receipt if exists
        if (!empty($message->deliveryreceipt1)) {
            $updateData['deliveryreceipt2'] = $message->deliveryreceipt1;
        }

        Log::debug("DLR pricing calculated using old system logic (cron)", [
            'userref' => $message->userref,
            'mobnum' => $message->mobnum,
            'routenum' => $routenum,
            'userprice' => $pricing['userprice'],
            'costprice' => $pricing['costprice'],
            'profit' => $pricing['profit']
        ]);

        return $updateData;
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        $countries = DB::table('country')
            ->select('dialcode', 'iso_code')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();
        
        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country->dialcode;
            }
        }
        
        return '44'; // Default to UK
    }

    /**
     * Map DLR status for smsg_log - OLD SYSTEM format
     * Uses capital letters: 'Delivered', 'Non Delivered', etc.
     */
    private function mapDlrStatusForSmsgLog($dlrStatus)
    {
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Non Delivered',  // OLD SYSTEM: expired = Non Delivered
            'DELETED' => 'Non Delivered',  // OLD SYSTEM: deleted = Non Delivered
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'acked',          // OLD SYSTEM: accepted = acked
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Non Delivered',  // OLD SYSTEM: rejected = Non Delivered
            'SKIPPED' => 'Non Delivered'   // OLD SYSTEM: skipped = Non Delivered
        ];

        return $statusMap[$dlrStatus] ?? 'Unknown';
    }

    /**
     * Update user wallet
     */
    private function updateUserWallet($message, $amount)
    {
        try {
            DB::table('users')
                ->where('bigid', $message->userref)
                ->where('migration_flag', 'new')
                ->increment('smsg_server1_sent', $amount);
                
            Log::info("Updated user wallet via cron", [
                'user_bigid' => $message->userref,
                'amount' => $amount
            ]);
        } catch (Exception $e) {
            Log::error("Failed to update user wallet", [
                'user_bigid' => $message->userref,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display summary
     */
    private function displaySummary()
    {
        $this->info("\n=== Summary ===");
        $this->info("Processed: {$this->processedCount}");
        $this->info("Updated: {$this->updatedCount}");
        $this->info("Failed: {$this->failedCount}");
    }

    /**
     * Log cron run
     */
    private function logCronRun()
    {
        try {
            DB::table('cron_logs')->insert([
                'cron_name' => 'fetch_pending_dlrs',
                'processed_count' => $this->processedCount,
                'updated_count' => $this->updatedCount,
                'failed_count' => $this->failedCount,
                'run_date' => Carbon::now(),
                'created_at' => Carbon::now()
            ]);
        } catch (Exception $e) {
            // Table might not exist, just log it
            Log::info("Cron run completed", [
                'cron' => 'fetch_pending_dlrs',
                'processed' => $this->processedCount,
                'updated' => $this->updatedCount,
                'failed' => $this->failedCount
            ]);
        }
    }
}
