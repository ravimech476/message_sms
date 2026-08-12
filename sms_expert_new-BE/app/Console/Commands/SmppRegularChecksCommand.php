<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;

class SmppRegularChecksCommand extends Command
{
    use LogsCronExecution;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:regular-checks 
                            {--debug : Send emails to debug recipient only}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check CSN SMPP wallet balances, update local client wallets and add special ITAGGSMPPLOG records to smsg_log';

    /**
     * Exchange rates
     */
    protected float $fxEurGbp = 0.849;
    protected float $fxAudGbp = 0.599;
    protected float $fxToGbp;

    /**
     * Date variables
     */
    protected string $date1;
    protected string $date2;
    protected string $date3;
    protected string $theCountryCode = '44';

    /**
     * State tracking
     */
    protected bool $isNewDay = false;
    protected array $updatesToRun = [];
    protected float $gbpTotalSentToDate2Local = 0;

    /**
     * Test accounts to exclude from production operations
     */
    protected array $testAccountBigIds = [
        'q43786f4ae53946dfa8aa3def2fbd53e',
        '6641b01402fe76dd6656c16bc9c38700',
        '65f050e205dff82f529eae1c6c133bb9',
        '73419c0c137c96c84a4490545e731838',
        'v9vex6kfd8d424b6978je2er53c65dfb',
        'a33b52c6e9gd72f94fe6dbb6ccfdc57c',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return $this->executeWithLogging('smpp:regular-checks', function () {
            $debug = $this->option('debug');
            $dryRun = $this->option('dry-run');

            $this->info('Starting SMPP Regular Checks...');
            $this->info('Run Time: ' . now()->format('Y-m-d H:i:s'));

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be made');
            }

            // Initialize exchange rate
            $this->fxToGbp = $this->fxEurGbp;

            // Check if it's a new day
            $this->checkNewDay($dryRun);

            // Reset local tracking
            $this->gbpTotalSentToDate2Local = 0;
            $this->updatesToRun = [];

            // Get SMPP accounts to process
            $smppAccounts = $this->getSmppAccounts();

            if ($smppAccounts->isEmpty()) {
                $this->info('No SMPP accounts to process.');
                return 'No SMPP accounts to process';
            }

            $this->info("Found {$smppAccounts->count()} SMPP account(s) to process");

            $processedCount = 0;
            $errorCount = 0;

            foreach ($smppAccounts as $index => $account) {
                try {
                    $this->processSmppAccount(
                        $index + 1,
                        $account,
                        $dryRun,
                        $debug
                    );
                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Error processing account {$account->smpp_username}: " . $e->getMessage());
                    SmppLogger::vonage()->error('SMPP Regular Checks error', [
                        'account' => $account->smpp_username,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Execute all pending updates
            if (!$dryRun) {
                $this->executeUpdates();
            }

            $summary = "Processed: {$processedCount}, Errors: {$errorCount}";
            $this->info($summary);

            return $summary;
        });
    }

    /**
     * Check if it's a new day and update control table
     */
    protected function checkNewDay(bool $dryRun): void
    {
        $control = DB::table('smsg_control')->first();
        $todaysDate = now()->format('Ymd');

        if (!$control || empty($control->todaysdate) || $todaysDate > $control->todaysdate) {
            $this->isNewDay = true;
            $this->info('NEW DAY detected - will roll over daily stats');

            if (!$dryRun) {
                DB::table('smsg_control')->update(['todaysdate' => $todaysDate]);
            }
        } else {
            $this->isNewDay = false;
            $this->info('Continuation of day - updating running stats');
        }
    }

    /**
     * Get SMPP accounts to process
     */
    protected function getSmppAccounts()
    {
        // This should be configured in a database table or config
        // For now, returning the accounts from the legacy script
        return collect([
            (object)[
                'client_smpp_number' => 1,
                'smpp_username' => 'mmmmmmm1',
                'smpp_password' => 'mmmmmmm1',
                'route_charge_type' => 'ppd',
                'eur_csn_price' => 0.0165 / $this->fxEurGbp,
                'client_charge_type' => 'ppd',
                'gbp_client_price' => 0.017,
                'client_route_num' => '7002',
                'client_name' => 'iTagg-mBloxTier1',
                'client_user_ref' => 'a33b52c6e9gd72f94fe6dbb6ccfdc57c',
                'low_dlr_level' => 70,
                'provider_name' => 'mblox',
            ],
        ]);
    }

    /**
     * Process a single SMPP account
     */
    protected function processSmppAccount(
        int $accountNumber,
        object $account,
        bool $dryRun,
        bool $debug
    ): void {
        $this->info("Processing account: {$account->client_name} ({$account->smpp_username})");

        $clientSmppNum = "smppsent{$accountNumber}";

        // Get current local wallet info
        $user = DB::table('users')
            ->where('bigid', $account->client_user_ref)
            ->select([
                'smsg_wallet',
                'smsg_server1_sent',
                'smsg_server2_sent',
                'currentsmppdate',
                'lastsmppdateroll',
                'smppsent1',
                'smppsent2',
                'smppsent3',
                'smppsent4',
                'smppsent5',
            ])
            ->first();

        if (!$user) {
            $this->warn("User not found: {$account->client_user_ref}");
            return;
        }

        $gbpTotalBoughtToDate = $user->smsg_wallet;
        $gbpTotalSentToDate1 = $user->smsg_server1_sent;
        $gbpTotalSentToDate2 = $user->smsg_server2_sent;
        $currentSmppDate = $user->currentsmppdate;

        if (empty($currentSmppDate) || $currentSmppDate == '0') {
            $currentSmppDate = now()->format('Ymd') . '000100';
        }

        // Get stats based on provider
        $stats = $this->getProviderStats($account, $dryRun, $debug);

        $sentToday = $stats['sent'];
        $deliveredToday = $stats['delivered'];
        $failedToday = $stats['failed'];
        $pendingToday = $stats['pending'];

        // Calculate delivery percentage
        $sentDelPercent = $sentToday > 0 ? sprintf("%.0f", $deliveredToday / ($sentToday / 100)) : 0;
        $sentDelPercentStr = $sentDelPercent . "%";

        // Calculate client spend
        if ($account->client_charge_type == 'pps') {
            $gbpClientTotalSpentToday = $sentToday * $account->gbp_client_price;
        } else {
            $gbpClientTotalSpentToday = $deliveredToday * $account->gbp_client_price;
        }

        // Track total for multi-account users
        $isFirstForUser = ($accountNumber == 1);
        if ($isFirstForUser) {
            $newSmsgServer2Sent = $gbpClientTotalSpentToday;
            $this->gbpTotalSentToDate2Local = $gbpClientTotalSpentToday;
        } else {
            $newSmsgServer2Sent = $this->gbpTotalSentToDate2Local + $gbpClientTotalSpentToday;
            $this->gbpTotalSentToDate2Local += $gbpClientTotalSpentToday;
        }

        $alertStr = '';

        if ($this->isNewDay) {
            // Report final figures for yesterday
            $this->sendYesterdaySummaryEmail($account, $user, $clientSmppNum, $debug);

            $currentSmppDate = now()->format('Ymd') . '000100';
            $alertStr = ':NEWDAY';
            $gbpClientWalletRemaining = $gbpTotalBoughtToDate - ($gbpTotalSentToDate1 + $gbpTotalSentToDate2);

            $sql = "UPDATE users SET 
                    smsg_server1_sent = smsg_server1_sent + smsg_server2_sent, 
                    smsg_server2_sent = 0, 
                    currentsmppdate = '{$currentSmppDate}', 
                    lastsmppdateroll = " . time() . ", 
                    smppsent1 = 0, smppsent2 = 0, smppsent3 = 0, smppsent4 = 0, smppsent5 = 0 
                    WHERE bigid = '{$account->client_user_ref}'";

            $this->updatesToRun[$accountNumber] = $sql;
        } else {
            // Continuation of day
            $gbpClientWalletRemaining = $gbpTotalBoughtToDate - ($gbpTotalSentToDate1 + $newSmsgServer2Sent);

            $sql = "UPDATE users SET 
                    smsg_server2_sent = {$newSmsgServer2Sent}, 
                    currentsmppdate = '{$currentSmppDate}', 
                    {$clientSmppNum} = {$sentToday} 
                    WHERE bigid = '{$account->client_user_ref}'";

            $this->updatesToRun[$accountNumber] = $sql;
        }

        // Set date variables for smsg_log
        $this->date1 = substr($currentSmppDate, 0, 8);
        $this->date2 = substr($currentSmppDate, 0, 14);
        $this->date3 = substr($currentSmppDate, 0, 12);

        // Update smsg_log
        if (!$dryRun) {
            $this->updateSmsgLog(
                substr($account->smpp_username, 0, 8),
                $account->route_charge_type,
                $account->eur_csn_price,
                $account->client_charge_type,
                $account->gbp_client_price,
                $account->client_route_num,
                $account->client_user_ref,
                $sentToday,
                $deliveredToday,
                $failedToday,
                $pendingToday
            );
        }

        // Log status
        $this->table(
            ['Metric', 'Value'],
            [
                ['Wallet Remaining', '£' . number_format($gbpClientWalletRemaining, 2)],
                ['Sent Today', $sentToday],
                ['Delivered Today', $deliveredToday],
                ['Failed Today', $failedToday],
                ['Pending Today', $pendingToday],
                ['Delivery Rate', $sentDelPercentStr],
            ]
        );

        // Send status email at specific times
        $this->sendStatusEmail($account, $alertStr, $sentToday, $sentDelPercentStr, $gbpClientWalletRemaining, $debug);

        // Check for low DLR alert
        $this->checkLowDlrAlert($account, $sentToday, $sentDelPercent, $debug);
    }

    /**
     * Get stats from provider API
     */
    protected function getProviderStats(object $account, bool $dryRun, bool $debug): array
    {
        if ($account->provider_name == 'mbird') {
            return $this->getMessageBirdStats($account, $dryRun, $debug);
        } else {
            // mblox - just ensure we create "zero" records
            // Stats are retrieved manually once per day for previous day
            $this->updatePreviousDay('mblox', $account, $dryRun);

            return [
                'sent' => 0,
                'delivered' => 0,
                'failed' => 0,
                'pending' => 0,
            ];
        }
    }

    /**
     * Get MessageBird stats
     */
    protected function getMessageBirdStats(object $account, bool $dryRun, bool $debug): array
    {
        $apiUrl = 'https://api.mobiletulip.com/api/partners/itagg/mt-volume';

        // Get today's stats
        $startDate = now()->format('Ymd') . '000000';
        $endDate = now()->format('Ymd') . '235959';

        try {
            $response = Http::get($apiUrl, [
                'smppusername' => $account->smpp_username,
                'smpppassword' => $account->smpp_password,
                'startdate' => $startDate,
                'enddate' => $endDate,
            ]);

            if ($response->successful()) {
                $data = trim($response->body());
                // Format: "TOTAL|DELIVERED|PENDING|FAILED"
                $parts = explode('|', $data);

                if (count($parts) >= 4) {
                    $stats = [
                        'sent' => (int)$parts[0],
                        'delivered' => (int)$parts[1],
                        'pending' => (int)$parts[2],
                        'failed' => (int)$parts[3],
                    ];

                    // Get previous days stats at specific times (5, 11, 17, 23 hours at minute 0)
                    $hour = (int)now()->format('G');
                    $minute = (int)now()->format('i');

                    if (in_array($hour, [5, 11, 17, 23]) && $minute == 0) {
                        $this->fetchAndStorePreviousDaysStats($account, $apiUrl, $dryRun);
                        $this->updatePreviousDay('mbird', $account, $dryRun);
                    }

                    return $stats;
                }
            }

            $this->warn("Failed to get MessageBird stats: " . $response->body());
        } catch (\Exception $e) {
            $this->error("MessageBird API error: " . $e->getMessage());
        }

        return ['sent' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0];
    }

    /**
     * Fetch and store previous days stats from MessageBird
     */
    protected function fetchAndStorePreviousDaysStats(object $account, string $apiUrl, bool $dryRun): void
    {
        for ($daysAgo = 1; $daysAgo <= 4; $daysAgo++) {
            $oldDate = now()->subDays($daysAgo)->format('Ymd');

            try {
                $response = Http::get($apiUrl, [
                    'smppusername' => $account->smpp_username,
                    'smpppassword' => $account->smpp_password,
                    'startdate' => $oldDate . '000000',
                    'enddate' => $oldDate . '235959',
                ]);

                if ($response->successful()) {
                    $parts = explode('|', trim($response->body()));

                    if (count($parts) >= 4 && !$dryRun) {
                        DB::table('smsg_smpp_stats')->insert([
                            'status' => 'new',
                            'providername' => 'mbird',
                            'provideruname' => $account->smpp_username,
                            'datesent' => $oldDate,
                            'delivered' => (int)$parts[1],
                            'failed' => (int)$parts[3],
                            'unknown' => 0,
                            'pending' => (int)$parts[2],
                            'changeddate' => now(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $this->warn("Failed to fetch stats for {$oldDate}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update previous day stats
     */
    protected function updatePreviousDay(string $providerName, object $account, bool $dryRun): void
    {
        $archiveTable = 'smsg_log_' . now()->subMonth()->format('ym');
        $today = now()->format('Ymd');

        $pendingStats = DB::table('smsg_smpp_stats')
            ->where('status', 'new')
            ->where('datesent', '<', $today)
            ->where('providername', $providerName)
            ->orderBy('changeddate', 'asc')
            ->get();

        foreach ($pendingStats as $stat) {
            $failed = $stat->failed + $stat->unknown;
            $delivered = $stat->delivered;
            $pending = $stat->pending;

            // Calculate costs
            if ($account->route_charge_type == 'pps') {
                $deliveredCostPrice = $delivered * ($account->eur_csn_price * $this->fxToGbp);
                $failedCostPrice = $failed * ($account->eur_csn_price * $this->fxToGbp);
                $pendingCostPrice = $pending * ($account->eur_csn_price * $this->fxToGbp);
            } else {
                $deliveredCostPrice = $delivered * ($account->eur_csn_price * $this->fxToGbp);
                $failedCostPrice = 0;
                $pendingCostPrice = 0;
            }

            // Calculate user prices
            if ($account->client_charge_type == 'pps') {
                $deliveredUserPrice = $delivered * $account->gbp_client_price;
                $failedUserPrice = $failed * $account->gbp_client_price;
                $pendingUserPrice = $pending * $account->gbp_client_price;
            } else {
                $deliveredUserPrice = $delivered * $account->gbp_client_price;
                $failedUserPrice = 0;
                $pendingUserPrice = 0;
            }

            $totalUserPrice = $deliveredUserPrice + $failedUserPrice + $pendingUserPrice;

            // Get userref from smsg_log
            $bigIdPrefix = 'smpppsmpppsmppp' . substr($stat->provideruname, 0, 8) . $stat->datesent;

            $existingLog = DB::table('smsg_log')
                ->where('bigid', 'like', $bigIdPrefix . '%')
                ->first();

            if (!$existingLog) {
                $this->warn("No smsg_log found for {$bigIdPrefix}");
                continue;
            }

            // Get old user prices for wallet adjustment
            $totalOldUserPrice = 0;

            foreach (['d', 'f', 'p'] as $suffix) {
                $table = $suffix == 'p' ? 'smsg_log' : $archiveTable;
                $oldLog = DB::table($table)
                    ->where('bigid', $bigIdPrefix . $suffix)
                    ->first();

                if ($oldLog) {
                    $totalOldUserPrice += $oldLog->userprice ?? 0;
                }
            }

            if (!$dryRun) {
                // Update delivered record
                DB::table($archiveTable)
                    ->where('bigid', $bigIdPrefix . 'd')
                    ->update([
                        'numparts' => $delivered,
                        'costprice' => $deliveredCostPrice,
                        'userprice' => $deliveredUserPrice,
                        'profit' => $deliveredUserPrice - $deliveredCostPrice,
                    ]);

                // Update failed record
                DB::table($archiveTable)
                    ->where('bigid', $bigIdPrefix . 'f')
                    ->update([
                        'numparts' => $failed,
                        'costprice' => $failedCostPrice,
                        'userprice' => $failedUserPrice,
                        'profit' => $failedUserPrice - $failedCostPrice,
                    ]);

                // Update pending record
                DB::table('smsg_log')
                    ->where('bigid', $bigIdPrefix . 'p')
                    ->update([
                        'numparts' => $pending,
                        'costprice' => $pendingCostPrice,
                        'userprice' => $pendingUserPrice,
                        'profit' => $pendingUserPrice - $pendingCostPrice,
                    ]);

                // Update user wallet
                DB::table('users')
                    ->where('bigid', $existingLog->userref)
                    ->update([
                        'smsg_server1_sent' => DB::raw("(smsg_server1_sent - {$totalOldUserPrice}) + {$totalUserPrice}"),
                    ]);

                // Mark stat as done
                DB::table('smsg_smpp_stats')
                    ->where('id', $stat->id)
                    ->update(['status' => 'done']);
            }

            $this->info("Updated previous day stats for {$stat->datesent}");
        }
    }

    /**
     * Update smsg_log with today's stats
     */
    protected function updateSmsgLog(
        string $csnSmppUsr,
        string $routeChargeType,
        float $eurCsnPrice,
        string $clientChargeType,
        float $gbpClientPrice,
        string $clientRouteNum,
        string $clientUserRef,
        int $sentToday,
        int $deliveredToday,
        int $failedToday,
        int $pendingToday
    ): void {
        $bigIdPrefix = 'smpppsmpppsmppp' . $csnSmppUsr . $this->date1;

        // Check if records exist for today
        $existingCount = DB::table('smsg_log')
            ->where('bigid', 'like', $bigIdPrefix . '%')
            ->where('countrydialcode', $this->theCountryCode)
            ->count();

        $isNewDay = ($existingCount < 3);

        // Get old values if not new day
        $oldDelivered = ['numparts' => 0, 'costprice' => 0, 'userprice' => 0];
        $oldFailed = ['numparts' => 0, 'costprice' => 0, 'userprice' => 0];
        $oldPending = ['numparts' => 0, 'costprice' => 0, 'userprice' => 0];

        if (!$isNewDay) {
            $oldDelivered = (array)DB::table('smsg_log')
                ->where('bigid', $bigIdPrefix . 'd')
                ->where('countrydialcode', $this->theCountryCode)
                ->first(['numparts', 'costprice', 'userprice']);

            $oldFailed = (array)DB::table('smsg_log')
                ->where('bigid', $bigIdPrefix . 'f')
                ->where('countrydialcode', $this->theCountryCode)
                ->first(['numparts', 'costprice', 'userprice']);

            $oldPending = (array)DB::table('smsg_log')
                ->where('bigid', $bigIdPrefix . 'p')
                ->where('countrydialcode', $this->theCountryCode)
                ->first(['numparts', 'costprice', 'userprice']);
        }

        // Calculate new cost prices
        if ($routeChargeType == 'pps') {
            $newDeliveredCostPrice = ($oldDelivered['costprice'] ?? 0) + (($deliveredToday - ($oldDelivered['numparts'] ?? 0)) * ($eurCsnPrice * $this->fxToGbp));
            $newFailedCostPrice = ($oldFailed['costprice'] ?? 0) + (($failedToday - ($oldFailed['numparts'] ?? 0)) * ($eurCsnPrice * $this->fxToGbp));
            $newPendingCostPrice = ($oldPending['costprice'] ?? 0) + (($pendingToday - ($oldPending['numparts'] ?? 0)) * ($eurCsnPrice * $this->fxToGbp));
        } else {
            $newDeliveredCostPrice = ($oldDelivered['costprice'] ?? 0) + (($deliveredToday - ($oldDelivered['numparts'] ?? 0)) * ($eurCsnPrice * $this->fxToGbp));
            $newFailedCostPrice = $oldFailed['costprice'] ?? 0;
            $newPendingCostPrice = $oldPending['costprice'] ?? 0;
        }

        // Get route info
        $routeInfo = $this->getRouteInfo($clientRouteNum);

        // Calculate new user prices
        if ($clientChargeType == 'pps') {
            $newDeliveredUserPrice = ($oldDelivered['userprice'] ?? 0) + (($deliveredToday - ($oldDelivered['numparts'] ?? 0)) * $gbpClientPrice);
            $newFailedUserPrice = ($oldFailed['userprice'] ?? 0) + (($failedToday - ($oldFailed['numparts'] ?? 0)) * $gbpClientPrice);
            $newPendingUserPrice = ($oldPending['userprice'] ?? 0) + (($pendingToday - ($oldPending['numparts'] ?? 0)) * $gbpClientPrice);
        } else {
            $newDeliveredUserPrice = ($oldDelivered['userprice'] ?? 0) + (($deliveredToday - ($oldDelivered['numparts'] ?? 0)) * $gbpClientPrice);
            $newFailedUserPrice = $oldFailed['userprice'] ?? 0;
            $newPendingUserPrice = $oldPending['userprice'] ?? 0;
        }

        // Insert/update records
        $this->doSmsgLogSql($isNewDay, 'd', 'Delivered', $deliveredToday, $newDeliveredCostPrice, $newDeliveredUserPrice, $newDeliveredUserPrice - $newDeliveredCostPrice, $clientUserRef, $clientChargeType, $routeInfo, $csnSmppUsr);
        $this->doSmsgLogSql($isNewDay, 'f', 'Non Delivered', $failedToday, $newFailedCostPrice, $newFailedUserPrice, $newFailedUserPrice - $newFailedCostPrice, $clientUserRef, $clientChargeType, $routeInfo, $csnSmppUsr);
        $this->doSmsgLogSql($isNewDay, 'p', '', $pendingToday, $newPendingCostPrice, $newPendingUserPrice, $newPendingUserPrice - $newPendingCostPrice, $clientUserRef, $clientChargeType, $routeInfo, $csnSmppUsr);
    }

    /**
     * Get route info based on route number
     */
    protected function getRouteInfo(string $clientRouteNum): array
    {
        $routes = [
            '7029' => ['requested_route' => '7029', 'suppliername' => 'csn0', 'supplierrouteref' => 0],
            '8002' => ['requested_route' => '8002', 'suppliername' => 'csn1', 'supplierrouteref' => 4001],
            '8029' => ['requested_route' => '8029', 'suppliername' => 'csn2', 'supplierrouteref' => 6001],
            '3029' => ['requested_route' => '3029', 'suppliername' => 'csn3', 'supplierrouteref' => 7001],
        ];

        return $routes[$clientRouteNum] ?? ['requested_route' => '7002', 'suppliername' => 'mblox', 'supplierrouteref' => 5001];
    }

    /**
     * Insert or update smsg_log record
     */
    protected function doSmsgLogSql(
        bool $newDay,
        string $dlrStatShort,
        string $dlrStat,
        int $numParts,
        float $costPrice,
        float $userPrice,
        float $profit,
        string $clientUserRef,
        string $clientChargeType,
        array $routeInfo,
        string $csnSmppUsr
    ): void {
        $bigId = 'smpppsmpppsmppp' . $csnSmppUsr . $this->date1 . $dlrStatShort;
        $doSendTimeInt = strtotime($this->date2);

        $data = [
            'userref' => $clientUserRef,
            'mobnum' => '447999999999',
            'countrydialcode' => $this->theCountryCode,
            'text' => 'iTAGGSMPPLOG',
            'originator' => 'iTAGGSMPPLOG',
            'chargetype' => $clientChargeType,
            'requested_route' => $routeInfo['requested_route'],
            'suppliername' => $routeInfo['suppliername'],
            'supplierrouteref' => $routeInfo['supplierrouteref'],
            'dayofyear' => $this->date1,
            'timesubmitted' => $this->date2,
            'dosendtime' => $this->date2,
            'dosendtimeint' => $doSendTimeInt,
            'timesent' => $this->date2,
            'deliverytime1' => $this->date3,
            'deliverytime2' => $this->date3,
            'deliverystatus2' => $dlrStat,
            'numparts' => $numParts,
            'costprice' => $costPrice,
            'userprice' => $userPrice,
            'profit' => $profit,
            'deliverystatus1' => 'acked',
            'deliveryreceipt1' => '',
            'deliveryreceipt2' => '',
            'numbits' => 7,
            'affiliateref' => 0,
            'initiator' => 'ExternalAPI',
            'incominglog_ref' => 0,
            'SItype' => null,
            'userdefined' => '',
            'sentstatus' => 'ok',
            'sentstatustext' => '',
            'sentstatustmp' => 'no',
            'submission_retries' => 0,
            'upstream_errormessage' => null,
            'onesixty_suppliermsgref' => '',
            'suppliermsgref' => 0,
            'delivery_reason' => null,
            'dreceipt_url' => '',
            'ofcomnetid' => 0,
            'netid' => 0,
            'hlrstatus' => '',
            'hlrbatchid' => null,
            'aggregator_dlrmsg' => '',
            'smsgdaemonid' => 4000,
            'sendpriority' => 0,
        ];

        if ($newDay) {
            $data['bigid'] = $bigId;
            DB::table('smsg_log')->insert($data);
        } else {
            DB::table('smsg_log')
                ->where('bigid', $bigId)
                ->where('countrydialcode', $this->theCountryCode)
                ->update($data);
        }
    }

    /**
     * Execute all pending updates
     */
    protected function executeUpdates(): void
    {
        foreach ($this->updatesToRun as $index => $sql) {
            try {
                DB::statement($sql);
                $this->info("Executed update #{$index}");
            } catch (\Exception $e) {
                $this->error("Failed to execute update #{$index}: " . $e->getMessage());
            }
        }
    }

    /**
     * Send yesterday summary email
     */
    protected function sendYesterdaySummaryEmail(object $account, object $user, string $clientSmppNum, bool $debug): void
    {
        $subject = "SMPPStats:YESTERDAY:{$account->client_name}/{$account->smpp_username} " . ($user->$clientSmppNum ?? 0);
        $content = "Do sanity check to ensure users sent volume of " . ($user->$clientSmppNum ?? 0) . " matches smsg_log sent volume for yesterday.";

        $this->sendEmail($subject, $content, $debug);
    }

    /**
     * Send status email
     */
    protected function sendStatusEmail(object $account, string $alertStr, int $sentToday, string $sentDelPercentStr, float $gbpClientWalletRemaining, bool $debug): void
    {
        $hour = (int)now()->format('G');
        $minute = (int)now()->format('i');

        // Send email at 6 AM or on new day
        $shouldSend = (($sentToday >= 0 || $alertStr != '') && $hour == 6 && $minute == 0) || ($alertStr == ':NEWDAY');

        if (!$shouldSend) {
            return;
        }

        $subject = "SMPPStats{$alertStr}:{$account->client_name}/{$account->smpp_username} {$sentToday}/{$sentDelPercentStr}";
        $content = "Wallet: £" . number_format($gbpClientWalletRemaining, 2) . "\n\n";
        $content .= "Today:\n";
        $content .= "- Sent: {$sentToday}\n";
        $content .= "- Delivery Rate: {$sentDelPercentStr}\n";

        $this->sendEmail($subject, $content, $debug);
    }

    /**
     * Check for low DLR alert
     */
    protected function checkLowDlrAlert(object $account, int $sentToday, int $sentDelPercent, bool $debug): void
    {
        $hour = (int)now()->format('G');
        $minute = (int)now()->format('i');

        if ($sentToday > 0 && $sentDelPercent < $account->low_dlr_level && in_array($hour, [6, 12, 18, 21]) && $minute == 0) {
            $message = "LOW DLR%:{$account->client_name}/{$account->smpp_username} {$sentToday}/{$sentDelPercent}%";
            $this->warn($message);

            // Optionally send SMS alert
            // $this->sendSmsAlert($message, 'SMPPLOWDLR');
        }
    }

    /**
     * Send email notification
     */
    protected function sendEmail(string $subject, string $content, bool $debug): void
    {
        $recipients = $debug
            ? [config('reports.debug_recipient')]
            : config('reports.smpp_checks_recipients', ['anand@nedholdings.com']);

        $reportData = [
            'subject' => $subject,
            'content' => $content,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'environment' => config('app.env'),
        ];

        try {
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\SmppChecksReportMail',
                    trim($recipient),
                    ['report_data' => $reportData],
                    [],
                    5
                );
            }

            $this->info("Email queued: {$subject}");
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
        }
    }
}
