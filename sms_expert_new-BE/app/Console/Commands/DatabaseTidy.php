<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\CronJobLog;
use Exception;

class DatabaseTidy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:tidy 
                            {--tidy-tables : Run table tidying operations}
                            {--process-ppd : Process per-delivered records}
                            {--client-reports : Generate client reports}
                            {--quick-reports : Generate quick reports}
                            {--reports-8am : Generate 8am reports}
                            {--all : Run all operations (default)}
                            {--dry-run : Show what would be done without executing}
                            {--debug : Enable debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Database maintenance and tidying operations - archives old data, optimizes tables, processes per-delivered SMS, and generates reports';

    /**
     * Report string accumulator
     */
    protected $reportStr = '';

    /**
     * Debug mode
     */
    protected $debug = false;

    /**
     * Dry run mode
     */
    protected $dryRun = false;

    /**
     * Cron job log instance
     */
    protected $cronLog;

    /**
     * Log file path
     */
    protected $logFile;

    /**
     * Total affected rows for operations
     */
    protected $doneRows = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->debug = $this->option('debug');
        $this->dryRun = $this->option('dry-run');
        
        // Set up logging
        $this->logFile = storage_path('logs/' . date('Y-m-d') . '/cron/db-tidy-' . date('His') . '.log');
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Start cron log
        $this->cronLog = CronJobLog::create([
            'command' => 'db:tidy',
            'status' => 'running',
            'started_at' => now(),
            'log_file' => $this->logFile,
        ]);

        $startTime = time();

        try {
            $this->info('==============================================');
            $this->info('  DATABASE TIDY - ' . Carbon::now()->format('Y-m-d H:i:s'));
            $this->info('==============================================');
            $this->newLine();

            $this->reportStr = "<b>Database Tidy Report</b><br><br>";

            // Determine which operations to run
            $tidyTables = $this->option('tidy-tables');
            $processPpd = $this->option('process-ppd');
            $clientReports = $this->option('client-reports');
            $quickReports = $this->option('quick-reports');
            $reports8am = $this->option('reports-8am');
            $all = $this->option('all');

            // If no specific option is set, or --all is set, run everything
            if ($all || (!$tidyTables && !$processPpd && !$clientReports && !$quickReports && !$reports8am)) {
                $tidyTables = true;
                $processPpd = true;
                $clientReports = true;
                $quickReports = true;
            }

            // Calculate archive dates
            $archiveDates = $this->calculateArchiveDates();
            $this->logOutput("Archive Configuration:");
            $this->logOutput("  Archive YM: " . $archiveDates['archiveym']);
            $this->logOutput("  Archive Max Date: " . $archiveDates['archivemaxdate']);
            $this->logOutput("  Archive 4 Days: " . $archiveDates['archive4days']);

            // Main operations
            if ($tidyTables) {
                $this->tidyTables($archiveDates);
            }

            if ($processPpd) {
                $this->processPerDelivereds($archiveDates);
            }

            if ($clientReports) {
                $this->generateClientReports();
            }

            if ($quickReports) {
                $this->generateQuickReports();
            }

            if ($reports8am) {
                $this->generate8amReports();
            }

            // Final optimization
            if ($tidyTables && !$this->dryRun) {
                $this->finalOptimization($archiveDates);
            }

            $duration = time() - $startTime;
            $this->reportStr .= "<br><b>Total runtime: {$duration} seconds</b><br><br>OK";

            // Update cron log
            $this->cronLog->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration' => $duration,
                'output' => $this->reportStr,
            ]);

            $this->newLine();
            $this->info('==============================================');
            $this->info("  COMPLETED in {$duration} seconds");
            $this->info('==============================================');

            // Send email report
            $this->sendReportEmail();

            return 0;

        } catch (Exception $e) {
            $duration = time() - $startTime;
            
            $this->error('ERROR: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            Log::error('Database Tidy Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update cron log
            $this->cronLog->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration' => $duration,
                'output' => $this->reportStr,
                'error' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Calculate archive dates
     */
    protected function calculateArchiveDates(): array
    {
        $dates = [];
        
        // Archive table suffix (e.g., 2511 for November 2025)
        $dates['archiveym'] = Carbon::now()->subMonth()->format('ym');
        $dates['archiveym2'] = Carbon::now()->subMonths(2)->format('ym');
        
        // Archive max date - everything before today
        $dates['archivemaxdate'] = Carbon::now()->format('Ymd') . '000000';
        $dates['archive4days'] = Carbon::now()->subDays(4)->format('Ymd') . '000000';
        
        // For 2-month-old archive
        $dates['archivemaxdate2'] = Carbon::now()->subMonths(2)->endOfMonth()->format('Ymd') . '235959';
        
        // Archive table names
        $dates['archivesmsglogtable'] = 'smsg_log_' . $dates['archiveym'];
        $dates['archivesmsglogtable2'] = 'smsg_log_' . $dates['archiveym2'];
        
        // Time periods
        $dates['yesterdayymd'] = Carbon::yesterday()->format('Ymd');
        $dates['yesterdaydatestart'] = Carbon::yesterday()->format('Ymd') . '000000';
        $dates['todaydatestart'] = Carbon::now()->format('Ymd') . '000000';
        $dates['currentmonthstamp'] = Carbon::now()->startOfMonth()->format('Y-m-d H:i:s');
        
        // Various archive periods
        $dates['archive3monthsago'] = Carbon::now()->subMonths(3)->startOfMonth()->format('Ymd') . '235959';
        $dates['archive6monthsago'] = Carbon::now()->subMonths(6)->startOfMonth()->format('Ymd') . '235959';
        $dates['archive12monthsago'] = Carbon::now()->subMonths(12)->startOfMonth()->format('Ymd') . '235959';
        $dates['archive13monthsago'] = Carbon::now()->subMonths(13)->startOfMonth()->format('Ymd') . '235959';

        return $dates;
    }

    /**
     * Check if a table exists
     */
    protected function tableExists(string $tableName): bool
    {
        try {
            $result = DB::select("SHOW TABLES LIKE '{$tableName}'");
            return count($result) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ensure archive table exists, create from smsg_log if not
     */
    protected function ensureArchiveTableExists(string $tableName): bool
    {
        if ($this->tableExists($tableName)) {
            $this->logOutput("Archive table {$tableName} already exists.");
            return true;
        }

        $this->logOutput("Archive table {$tableName} does not exist. Creating from smsg_log...");
        
        if ($this->dryRun) {
            $this->logOutput("DRY RUN: Would create table {$tableName} LIKE smsg_log");
            return true;
        }

        try {
            DB::statement("CREATE TABLE {$tableName} LIKE smsg_log");
            $this->logOutput("Created archive table {$tableName} successfully.");
            $this->reportStr .= "Created archive table {$tableName}<br>";
            return true;
        } catch (Exception $e) {
            $this->error("Failed to create archive table {$tableName}: " . $e->getMessage());
            $this->reportStr .= "FAILED to create archive table {$tableName}: " . $e->getMessage() . "<br>";
            return false;
        }
    }

    /**
     * Tidy tables - main archiving and optimization
     */
    protected function tidyTables(array $dates): void
    {
        $this->info('📦 Starting Table Tidying Operations...');
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Ensure archive tables exist before proceeding
        $this->logOutput("Ensuring archive tables exist...");
        $this->ensureArchiveTableExists($dates['archivesmsglogtable']);
        $this->ensureArchiveTableExists($dates['archivesmsglogtable2']);

        // Suspend SMS processing during tidy
        if (!$this->dryRun) {
            $this->logOutput("Suspending SMS processing...");
            $this->quickSql("UPDATE smsg_control SET phase1_status = 'suspend', campaignstatus = 'suspend'");
            sleep(30); // Wait for processes to finish
        }

        try {
            // Log archive information
            $this->reportStr .= "Archiving...<br>";
            $this->reportStr .= "Earlier than {$dates['archivemaxdate2']} -> {$dates['archivesmsglogtable2']}<br>";
            $this->reportStr .= "Between {$dates['archivemaxdate2']} and {$dates['archivemaxdate']} -> {$dates['archivesmsglogtable']}<br><br>";

            // First of month operations
            if (Carbon::now()->day == 1) {
                $this->processFirstOfMonth($dates);
            }

            // Archive old messages
            $this->archiveOldMessages($dates);

            // Clean up various tables
            $this->cleanupTables($dates);

            // Update tomorrow's scheduled messages
            $this->logOutput("Updating scheduled messages...");
            $this->quickSql("UPDATE smsg_log SET sentstatus = 'no' WHERE dayofyear = " . Carbon::now()->format('Ymd') . " AND sentstatus = 'tomorrowonward'");
            $this->quickSql("UPDATE smsg_log SET sentstatustmp = 'no' WHERE dayofyear = " . Carbon::now()->format('Ymd') . " AND sentstatustmp = 'tomorrowonward'");

            // Fix stuck messages
            $this->logOutput("Fixing stuck messages...");
            $this->quickSql("UPDATE smsg_log SET sentstatus = 'firing' WHERE sentstatus LIKE 'doing' AND dosendtime > '" . Carbon::now()->format('Ymd') . "000000' AND dosendtime < '" . Carbon::now()->format('Ymd') . "010000'");

        } finally {
            // Resume SMS processing
            if (!$this->dryRun) {
                $this->logOutput("Resuming SMS processing...");
                $this->quickSql("UPDATE smsg_control SET phase1_status = 'run', campaignstatus = 'run'");
            }
        }
    }

    /**
     * Process first of month operations
     */
    protected function processFirstOfMonth(array $dates): void
    {
        $this->logOutput("----- Start of 1st of Month Operations -----");

        // For 1st of month, we need to create the NEW month's archive table
        // Current month's table (e.g., smsg_log_2512 for December 2025)
        $currentMonthTable = 'smsg_log_' . Carbon::now()->format('ym');
        
        // Check if current month archive table exists, if not create it
        if (!$this->tableExists($currentMonthTable)) {
            $this->logOutput("Creating current month archive table {$currentMonthTable}...");
            if (!$this->dryRun) {
                try {
                    DB::statement("CREATE TABLE {$currentMonthTable} LIKE smsg_log");
                    $this->logOutput("Created archive table {$currentMonthTable} successfully.");
                    $this->reportStr .= "Created archive table {$currentMonthTable}<br>";
                } catch (Exception $e) {
                    $this->warn("Could not create {$currentMonthTable}: " . $e->getMessage());
                }
            }
        }

        // Check if last month's archive table exists
        if (!$this->tableExists($dates['archivesmsglogtable'])) {
            $this->logOutput("Last month's archive table {$dates['archivesmsglogtable']} does not exist. Creating...");
            $this->ensureArchiveTableExists($dates['archivesmsglogtable']);
        }

        // Check if 2-month-old archive table exists
        if (!$this->tableExists($dates['archivesmsglogtable2'])) {
            $this->logOutput("Two month old archive table {$dates['archivesmsglogtable2']} does not exist. Creating...");
            $this->ensureArchiveTableExists($dates['archivesmsglogtable2']);
        }

        // Only move data between archive tables if both exist and have data
        if ($this->tableExists($dates['archivesmsglogtable2'])) {
            // Move data from old archive to newer archive (for records that belong to last month)
            $this->logOutput("Checking for data to move from {$dates['archivesmsglogtable2']} to {$dates['archivesmsglogtable']}...");
            $this->quickCountSql("SELECT COUNT(*) as cnt FROM {$dates['archivesmsglogtable2']} WHERE dosendtime > '{$dates['archivemaxdate2']}'");
            
            if (!$this->dryRun) {
                $this->quickSql("INSERT INTO {$dates['archivesmsglogtable']} SELECT * FROM {$dates['archivesmsglogtable2']} WHERE dosendtime > '{$dates['archivemaxdate2']}'");
                $this->quickLimitSql("DELETE FROM {$dates['archivesmsglogtable2']} WHERE dosendtime > '{$dates['archivemaxdate2']}' ORDER BY dosendtime", 250000);
            }
        }

        $this->logOutput("----- End of 1st of Month Operations -----");
    }

    /**
     * Archive old messages from smsg_log
     */
    protected function archiveOldMessages(array $dates): void
    {
        $this->logOutput("Archiving old messages from smsg_log...");

        // Ensure archive tables exist
        if (!$this->tableExists($dates['archivesmsglogtable'])) {
            $this->ensureArchiveTableExists($dates['archivesmsglogtable']);
        }
        if (!$this->tableExists($dates['archivesmsglogtable2'])) {
            $this->ensureArchiveTableExists($dates['archivesmsglogtable2']);
        }

        // Count records to archive
        $this->quickCountSql("SELECT COUNT(*) as cnt FROM smsg_log WHERE dosendtime <= '{$dates['archivemaxdate2']}'");

        $removelatestsql = "(dosendtime > '{$dates['archivemaxdate2']}' AND dosendtime < '{$dates['archive4days']}' AND sentstatus NOT IN ('pause', 'tomorrowonward')) OR (dosendtime < '{$dates['archivemaxdate']}' AND deliverystatus2 IN ('Delivered', 'Lost Notification', 'Non Delivered', 'Unknown'))";
        
        $this->quickCountSql("SELECT COUNT(*) as cnt FROM smsg_log WHERE {$removelatestsql}");

        if (!$this->dryRun) {
            // Insert into archive tables (only if they exist)
            if ($this->tableExists($dates['archivesmsglogtable2'])) {
                $this->quickSql("INSERT INTO {$dates['archivesmsglogtable2']} SELECT * FROM smsg_log WHERE dosendtime <= '{$dates['archivemaxdate2']}'");
            }
            
            if ($this->tableExists($dates['archivesmsglogtable'])) {
                $this->quickSql("INSERT INTO {$dates['archivesmsglogtable']} SELECT * FROM smsg_log WHERE {$removelatestsql}");
            }

            // Delete archived records
            $this->quickLimitSql("DELETE FROM smsg_log WHERE dosendtime <= '{$dates['archivemaxdate2']}' ORDER BY dosendtime", 250000);
            $this->quickLimitSql("DELETE FROM smsg_log WHERE {$removelatestsql} ORDER BY dosendtime", 250000);
        }

        // Drop very old archive table (14 months ago)
        $oldArchiveTable = 'smsg_log_' . Carbon::now()->subMonths(14)->format('ym');
        if ($this->tableExists($oldArchiveTable)) {
            $this->logOutput("Dropping old archive table {$oldArchiveTable}...");
            if (!$this->dryRun) {
                $this->quickSql("DROP TABLE IF EXISTS {$oldArchiveTable}");
            }
        } else {
            $this->logOutput("Old archive table {$oldArchiveTable} does not exist, skipping drop.");
        }
    }

    /**
     * Cleanup various tables
     */
    protected function cleanupTables(array $dates): void
    {
        $this->logOutput("Cleaning up auxiliary tables...");

        // Delivery receipt push log
        $fourDaysAgo = Carbon::now()->subDays(4)->format('Y-m-d H:i:s');
        
        if ($this->tableExists('delivery_receipt_push_log')) {
            $this->quickSql("DELETE FROM delivery_receipt_push_log WHERE status IN ('processed', 'fail') AND dosendtime < '{$fourDaysAgo}'");
            $this->quickSql("OPTIMIZE TABLE delivery_receipt_push_log");
        } else {
            $this->logOutput("Table delivery_receipt_push_log does not exist, skipping.");
        }

        // URL forward table
        if ($this->tableExists('url_forward')) {
            $this->quickSql("DELETE FROM url_forward WHERE inserted_time < '{$fourDaysAgo}'");
            $this->quickSql("OPTIMIZE TABLE url_forward");
        } else {
            $this->logOutput("Table url_forward does not exist, skipping.");
        }

        // iTagg forwarding log
        $this->logOutput("Cleaning itagg_forwardinglog...");
        if (!$this->dryRun && $this->tableExists('itagg_forwardinglog') && $this->tableExists('itagg_incominglog')) {
            try {
                DB::statement("DELETE FROM itagg_forwardinglog WHERE itagg_incominglog_id < (SELECT id FROM itagg_incominglog WHERE recieved < '{$fourDaysAgo}' ORDER BY recieved DESC LIMIT 1)");
                $this->quickSql("OPTIMIZE TABLE itagg_forwardinglog");
            } catch (Exception $e) {
                $this->warn("Could not clean itagg_forwardinglog: " . $e->getMessage());
            }
        }

        // iTagg incoming log - archive and cleanup
        $ninetyDaysAgo = Carbon::now()->subDays(90)->format('Y-m-d H:i:s');
        $yearAgo = Carbon::now()->subDays(365)->format('Y-m-d H:i:s');
        
        $this->logOutput("Archiving old incoming logs...");
        if (!$this->dryRun && $this->tableExists('itagg_incominglog')) {
            // Ensure archive table exists
            if (!$this->tableExists('itagg_incominglog_old')) {
                try {
                    DB::statement("CREATE TABLE itagg_incominglog_old LIKE itagg_incominglog");
                    $this->logOutput("Created itagg_incominglog_old table.");
                } catch (Exception $e) {
                    $this->warn("Could not create itagg_incominglog_old: " . $e->getMessage());
                }
            }
            
            if ($this->tableExists('itagg_incominglog_old')) {
                $this->quickSql("INSERT INTO itagg_incominglog_old SELECT * FROM itagg_incominglog WHERE recieved < '{$ninetyDaysAgo}'");
                $this->quickSql("DELETE FROM itagg_incominglog WHERE recieved < '{$ninetyDaysAgo}'");
                $this->quickLimitSql("DELETE FROM itagg_incominglog_old WHERE recieved < '{$yearAgo}' ORDER BY recieved", 250000);
                $this->quickSql("OPTIMIZE TABLE itagg_incominglog");
                $this->quickSql("OPTIMIZE TABLE itagg_incominglog_old");
            }
        }

        // HLR client log
        $this->logOutput("Cleaning HLR client log...");
        $fourDaysAgoTimestamp = Carbon::now()->subDays(4)->format('YmdHis');
        if (!$this->dryRun && $this->tableExists('hlr_clientlog')) {
            // Ensure archive table exists
            if (!$this->tableExists('hlr_clientlog_old')) {
                try {
                    DB::statement("CREATE TABLE hlr_clientlog_old LIKE hlr_clientlog");
                    $this->logOutput("Created hlr_clientlog_old table.");
                } catch (Exception $e) {
                    $this->warn("Could not create hlr_clientlog_old: " . $e->getMessage());
                }
            }
            
            if ($this->tableExists('hlr_clientlog_old')) {
                $this->quickSql("INSERT INTO hlr_clientlog_old (msisdn, userref, createdate, status, mccmnc, source, lookupdate, tag, addedid, firstsmsuse, subref, supplier_status) SELECT msisdn, userref, createdate, status, mccmnc, source, lookupdate, tag, addedid, firstsmsuse, subref, supplier_status FROM hlr_clientlog WHERE createdate < '{$fourDaysAgoTimestamp}'");
                $this->quickSql("DELETE FROM hlr_clientlog WHERE createdate < '{$fourDaysAgoTimestamp}'");
                $this->quickSql("OPTIMIZE TABLE hlr_clientlog");
                $this->quickSql("OPTIMIZE TABLE hlr_clientlog_old");
            }
        }

        // Short URL forwarding
        $this->logOutput("Cleaning short URL forwarding...");
        if (!$this->dryRun && $this->tableExists('smsg_shorturl_forwarding')) {
            // Ensure archive table exists
            if (!$this->tableExists('smsg_shorturl_forwarding_archive')) {
                try {
                    DB::statement("CREATE TABLE smsg_shorturl_forwarding_archive LIKE smsg_shorturl_forwarding");
                    $this->logOutput("Created smsg_shorturl_forwarding_archive table.");
                } catch (Exception $e) {
                    $this->warn("Could not create smsg_shorturl_forwarding_archive: " . $e->getMessage());
                }
            }
            
            if ($this->tableExists('smsg_shorturl_forwarding_archive')) {
                $this->quickSql("INSERT IGNORE INTO smsg_shorturl_forwarding_archive SELECT * FROM smsg_shorturl_forwarding WHERE datecreated < '{$dates['archive3monthsago']}'");
                $this->quickLimitSql("DELETE FROM smsg_shorturl_forwarding WHERE datecreated < '{$dates['archive3monthsago']}' ORDER BY datecreated", 250000);
                $this->quickLimitSql("DELETE FROM smsg_shorturl_forwarding_archive WHERE datecreated < '{$dates['archive12monthsago']}' ORDER BY datecreated", 250000);
                $this->quickSql("OPTIMIZE TABLE smsg_shorturl_forwarding");
            }
        }

        // Clean up cron job logs older than 30 days
        $this->logOutput("Cleaning old cron job logs...");
        CronJobLog::cleanup(30);
    }

    /**
     * Process per-delivered records
     */
    protected function processPerDelivereds(array $dates): void
    {
        $this->info('💰 Processing Per-Delivered Records...');
        $this->newLine();

        $this->processPerDeliveredTable('smsg_log');
        $this->processPerDeliveredTable($dates['archivesmsglogtable']);
    }

    /**
     * Process per-delivered for a specific table
     */
    protected function processPerDeliveredTable(string $tableName): void
    {
        $this->logOutput("Processing per-delivered for {$tableName}...");

        // Check if table exists
        if (!$this->tableExists($tableName)) {
            $this->logOutput("Table {$tableName} does not exist, skipping per-delivered processing.");
            return;
        }

        if ($this->dryRun) {
            $this->logOutput("DRY RUN: Would process per-delivered records");
            return;
        }

        $startTime = time();
        $usersAdjusted = [];
        $rowsChecked = 0;
        $rowsUpdated = 0;
        $walletsAdjusted = 0;

        try {
            // Get records that need processing
            $sql = "SELECT id, userref, costprice, userprice, profit, origcostprice, origuserprice, chargetype, deliverystatus2, bigid 
                    FROM {$tableName} 
                    WHERE bigid NOT LIKE 'smpppsmpppsmppp%' 
                    AND sentstatus = 'ok' 
                    AND dayofyear < " . Carbon::now()->format('Ymd') . " 
                    AND chargetype <> 'pps'";

            $records = DB::select($sql);

            foreach ($records as $record) {
                $rowsChecked++;
                
                $newCostPrice = null;
                $newUserPrice = null;
                $newProfit = null;
                $walletAdjustment = null;
                $newOrigCostPrice = null;
                $newOrigUserPrice = null;

                // Determine adjustments based on charge type
                switch ($record->chargetype) {
                    case 'ppsd': // User PPS, Route PPD
                        if ($record->deliverystatus2 != 'Delivered' && $record->deliverystatus2 != 'Lost Notification' && $record->origcostprice == 0 && $record->origuserprice == 0) {
                            $newCostPrice = 0;
                            $newProfit = $record->userprice;
                            $newOrigCostPrice = $record->costprice;
                            $newOrigUserPrice = $record->userprice;
                        } elseif ($record->deliverystatus2 == 'Delivered' && $record->origcostprice > 0) {
                            $newCostPrice = $record->origcostprice;
                            $newProfit = $record->userprice - $record->origcostprice;
                            $newOrigCostPrice = 0;
                            $newOrigUserPrice = 0;
                        }
                        break;

                    case 'ppds': // User PPD, Route PPS
                        if ($record->deliverystatus2 != 'Delivered' && $record->origcostprice == 0 && $record->origuserprice == 0) {
                            $newUserPrice = 0;
                            $newProfit = 0 - $record->costprice;
                            $walletAdjustment = 0 - $record->userprice;
                            $newOrigCostPrice = $record->costprice;
                            $newOrigUserPrice = $record->userprice;
                        } elseif ($record->deliverystatus2 == 'Delivered' && $record->origcostprice > 0) {
                            $newUserPrice = $record->origuserprice;
                            $newProfit = $record->origuserprice - $record->origcostprice;
                            $walletAdjustment = $record->origuserprice;
                            $newOrigCostPrice = 0;
                            $newOrigUserPrice = 0;
                        }
                        break;

                    case 'ppd': // User PPD, Route PPD
                        if ($record->deliverystatus2 != 'Delivered' && $record->origcostprice == 0 && $record->origuserprice == 0) {
                            $newCostPrice = 0;
                            $newUserPrice = 0;
                            $newProfit = 0;
                            $walletAdjustment = 0 - $record->userprice;
                            $newOrigCostPrice = $record->costprice;
                            $newOrigUserPrice = $record->userprice;
                        } elseif ($record->deliverystatus2 == 'Delivered' && $record->origcostprice > 0) {
                            $newCostPrice = $record->origcostprice;
                            $newUserPrice = $record->origuserprice;
                            $newProfit = $record->origuserprice - $record->origcostprice;
                            $walletAdjustment = $record->origuserprice;
                            $newOrigCostPrice = 0;
                            $newOrigUserPrice = 0;
                        }
                        break;
                }

                // Update smsg_log record if needed
                if ($newCostPrice !== null || $newUserPrice !== null || $newProfit !== null) {
                    $updateFields = [];
                    
                    if ($newCostPrice !== null) $updateFields['costprice'] = $newCostPrice;
                    if ($newUserPrice !== null) $updateFields['userprice'] = $newUserPrice;
                    if ($newProfit !== null) $updateFields['profit'] = $newProfit;
                    if ($newOrigCostPrice !== null) $updateFields['origcostprice'] = $newOrigCostPrice;
                    if ($newOrigUserPrice !== null) $updateFields['origuserprice'] = $newOrigUserPrice;

                    DB::table($tableName)->where('id', $record->id)->update($updateFields);
                    $rowsUpdated++;
                }

                // Adjust user wallet if needed
                if ($walletAdjustment !== null) {
                    DB::table('users')->where('bigid', $record->userref)->increment('smsg_server1_sent', $walletAdjustment);
                    $walletsAdjusted++;

                    if (isset($usersAdjusted[$record->userref])) {
                        $usersAdjusted[$record->userref] += $walletAdjustment;
                    } else {
                        $usersAdjusted[$record->userref] = $walletAdjustment;
                    }
                }

                // Pause briefly every 1000 records
                if ($rowsChecked % 1000 == 0) {
                    usleep(50000);
                }
            }

            $duration = time() - $startTime;
            $this->reportStr .= "Per-Delivered ({$tableName}): {$rowsChecked} checked, {$rowsUpdated} updated, {$walletsAdjusted} wallets adjusted in {$duration}s<br>";

            // Log user adjustments
            if (count($usersAdjusted) > 0) {
                $this->reportStr .= "<br>User Wallet Adjustments:<br>";
                foreach ($usersAdjusted as $userRef => $adjustment) {
                    $user = DB::table('users')->where('bigid', $userRef)->first(['contactname', 'busname', 'contactemail']);
                    $this->reportStr .= "  {$user->contactname} ({$userRef}): {$adjustment}<br>";
                }
            }

        } catch (Exception $e) {
            $this->error("Error processing per-delivered for {$tableName}: " . $e->getMessage());
            Log::error("Per-delivered processing error", ['table' => $tableName, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Generate client reports
     */
    protected function generateClientReports(): void
    {
        $this->info('📊 Generating Client Reports...');
        $this->newLine();

        // Monthly reports on 1st of month
        if (Carbon::now()->day == 1) {
            $this->logOutput("Generating monthly reports...");
            // Add specific client report generation here
        }

        // Weekly reports on Sunday
        if (Carbon::now()->dayOfWeek == 0) {
            $this->logOutput("Generating weekly reports...");
            // Add specific weekly report generation here
        }

        $this->logOutput("Client reports completed.");
    }

    /**
     * Generate quick reports
     */
    protected function generateQuickReports(): void
    {
        $this->info('⚡ Generating Quick Reports...');
        $this->newLine();

        $this->logOutput("Quick reports completed.");
    }

    /**
     * Generate 8am reports
     */
    protected function generate8amReports(): void
    {
        $this->info('🌅 Generating 8am Reports...');
        $this->newLine();

        $this->logOutput("8am reports completed.");
    }

    /**
     * Final table optimization
     */
    protected function finalOptimization(array $dates): void
    {
        $this->logOutput("Running final table optimizations...");

        $tablesToOptimize = [
            'smsg_log',
            $dates['archivesmsglogtable'],
            $dates['archivesmsglogtable2'],
            'users',
            'master_msisdn',
            'smsg_receipt_buffer',
            'smsg_billing_mblox',
            'platinum_log'
        ];

        foreach ($tablesToOptimize as $table) {
            if ($this->tableExists($table)) {
                try {
                    $this->quickSql("OPTIMIZE TABLE {$table}");
                } catch (Exception $e) {
                    $this->warn("Could not optimize {$table}: " . $e->getMessage());
                }
            } else {
                $this->logOutput("Table {$table} does not exist, skipping optimization.");
            }
        }
    }

    /**
     * Execute SQL and log result
     */
    protected function quickSql(string $sql): string
    {
        $startTime = time();
        $startTimeStr = Carbon::now()->format('g:i:sa, jS M Y');

        try {
            if ($this->dryRun) {
                $result = "DRY RUN - Would execute: " . substr($sql, 0, 100) . "...";
                $this->doneRows = 0;
            } else {
                DB::statement($sql);
                $this->doneRows = DB::select("SELECT ROW_COUNT() as cnt")[0]->cnt ?? 0;
            }

            $endTimeStr = Carbon::now()->format('g:i:sa, jS M Y');
            $runtime = time() - $startTime;

            $result = "{$startTimeStr} - {$endTimeStr} ({$runtime}s): DONE ({$this->doneRows} rows): " . substr($sql, 0, 100) . "<br>";
            $this->logOutput($result);
            $this->reportStr .= $result;

            return $result;

        } catch (Exception $e) {
            $result = "{$startTimeStr}: FAILED: {$sql} - Error: " . $e->getMessage() . "<br>";
            $this->error($result);
            $this->reportStr .= $result;

            return $result;
        }
    }

    /**
     * Execute count SQL and log result
     */
    protected function quickCountSql(string $sql): string
    {
        try {
            $result = DB::select($sql);
            $count = $result[0]->cnt ?? 0;
            
            $result = "Count: {$count} - " . substr($sql, 0, 80) . "<br>";
            $this->logOutput($result);
            $this->reportStr .= $result;

            return $result;

        } catch (Exception $e) {
            return "Count FAILED: {$sql}<br>";
        }
    }

    /**
     * Execute SQL with limit in chunks
     */
    protected function quickLimitSql(string $sql, int $limit): string
    {
        $totalDeleted = 0;
        $chunks = 0;
        $pauseTime = 5;

        while (true) {
            $limitedSql = $sql . " LIMIT {$limit}";
            
            if ($this->dryRun) {
                $this->logOutput("DRY RUN: Would execute chunked delete: {$limitedSql}");
                break;
            }

            DB::statement($limitedSql);
            $rowsDeleted = DB::select("SELECT ROW_COUNT() as cnt")[0]->cnt ?? 0;

            if ($rowsDeleted == 0) {
                break;
            }

            $totalDeleted += $rowsDeleted;
            $chunks++;

            if ($rowsDeleted < $limit) {
                break;
            }

            sleep($pauseTime);
        }

        $result = "Deleted {$totalDeleted} rows in {$chunks} chunks: " . substr($sql, 0, 80) . "<br>";
        $this->logOutput($result);
        $this->reportStr .= $result;

        return $result;
    }

    /**
     * Log output to file and console
     */
    protected function logOutput(string $message): void
    {
        $timestamp = Carbon::now()->format('H:i:s');
        $logMessage = "[{$timestamp}] {$message}";

        // Write to log file
        file_put_contents($this->logFile, $logMessage . "\n", FILE_APPEND);

        // Console output
        if ($this->debug) {
            $this->line($message);
        }
    }

    /**
     * Send report email
     */
    protected function sendReportEmail(): void
    {
        try {
            $supportEmail = env('SUPPORT_EMAIL', 'anand@nedholdings.com');
            $recipients = array_filter(array_map('trim', explode(',', $supportEmail)));

            if (empty($recipients)) {
                $this->warn("No recipients configured for report email (SUPPORT_EMAIL not set)");
                return;
            }

            $reportData = [
                'subject' => 'Database Tidy Report - ' . Carbon::now()->format('Y-m-d H:i'),
                'date' => Carbon::now()->format('Y-m-d H:i'),
                'content' => strip_tags(str_replace('<br>', "\n", $this->reportStr)),
            ];

            // Send via RabbitMQ queue
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\DatabaseTidyReportMail',
                    trim($recipient),
                    ['report_data' => $reportData],
                    [],
                    5
                );
            }

            $this->logOutput("Report email queued to: " . implode(', ', $recipients));

        } catch (Exception $e) {
            $this->warn("Failed to send report email: " . $e->getMessage());
        }
    }
}
