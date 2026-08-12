<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CampaignReportDaemon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:report 
                            {--days=14 : Number of days to look back for campaigns}
                            {--campaign= : Process specific campaign ID only}
                            {--user= : Process specific user only}
                            {--dlr-only : Only process DLR stats}
                            {--clicks-only : Only process click stats}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DLR and click statistics for SMS campaigns';

    private $processedCount = 0;
    private $errorCount = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $specificCampaign = $this->option('campaign');
        $specificUser = $this->option('user');
        $dlrOnly = $this->option('dlr-only');
        $clicksOnly = $this->option('clicks-only');

        $this->info("Campaign Report Generator Started");
        $this->info("Looking back: {$days} days");
        
        $startTime = microtime(true);

        try {
            if (!$clicksOnly) {
                $this->info("\n=== Processing DLR Stats ===");
                $this->processDlrStats($days, $specificCampaign, $specificUser);
            }

            if (!$dlrOnly) {
                $this->info("\n=== Processing Click Stats ===");
                $this->processClickStats($days, $specificCampaign, $specificUser);
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Campaign Report Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        
        $this->info("\n=== Summary ===");
        $this->info("Campaigns processed: {$this->processedCount}");
        $this->info("Errors: {$this->errorCount}");
        $this->info("Time elapsed: {$elapsed} seconds");

        return 0;
    }

    /**
     * Process DLR statistics for campaigns
     */
    private function processDlrStats($days, $specificCampaign = null, $specificUser = null)
    {
        $cutoffDate = Carbon::now()->subDays($days)->format('Ymd000000');
        
        // Build query for campaigns
        $query = DB::table('smsg_campaigns')
            ->select('userref', 'campaignid')
            ->where('datetime', '>', $cutoffDate);

        if ($specificCampaign) {
            $query->where('campaignid', $specificCampaign);
        }
        if ($specificUser) {
            $query->where('userref', $specificUser);
        }

        $campaigns = $query->get();
        
        $this->info("Found " . count($campaigns) . " campaigns to process for DLR stats");

        $bar = $this->output->createProgressBar(count($campaigns));
        $bar->start();

        foreach ($campaigns as $campaign) {
            try {
                $this->updateDlrStats($campaign->userref, $campaign->campaignid);
                $this->processedCount++;
            } catch (\Exception $e) {
                $this->errorCount++;
                Log::warning("DLR Stats Error for campaign {$campaign->campaignid}", [
                    'error' => $e->getMessage()
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Update DLR stats for a specific campaign
     */
    private function updateDlrStats($userref, $campaignref)
    {
        // Get current month table
        $currentTable = 'smsg_log';
        
        // Get previous month table (partitioned)
        $prevMonthTable = 'smsg_log_' . Carbon::now()->subMonth()->format('ym');
        
        // Check if previous month table exists
        $prevTableExists = $this->tableExists($prevMonthTable);

        // Build the union query for DLR stats
        $sql = "
            SELECT
                thedlr,
                SUM(cnt) as thecnt
            FROM (
                (SELECT 
                    CASE 
                        WHEN sentstatus = 'fail' AND LOCATE('blacklist', sentstatustext) > 0 
                        THEN 'Not Sent/Blacklisted' 
                        ELSE deliverystatus2 
                    END as thedlr, 
                    COUNT(*) as cnt 
                FROM {$currentTable} 
                WHERE campaignref = ? AND userref = ?  AND migration_flag = 'new'
                GROUP BY thedlr)
        ";

        $params = [$campaignref, $userref];

        if ($prevTableExists) {
            $sql .= "
                UNION ALL
                (SELECT 
                    CASE 
                        WHEN sentstatus = 'fail' AND LOCATE('blacklist', sentstatustext) > 0 
                        THEN 'Not Sent/Blacklisted' 
                        ELSE deliverystatus2 
                    END as thedlr, 
                    COUNT(*) as cnt 
                FROM {$prevMonthTable} 
                WHERE campaignref = ? AND userref = ?  AND migration_flag = 'new'
                GROUP BY thedlr)
            ";
            $params = array_merge($params, [$campaignref, $userref]);
        }

        $sql .= ") as thelogs GROUP BY thedlr";

        $results = DB::select($sql, $params);

        // Initialize counters
        $stats = [
            'Delivered' => 0,
            'Non Delivered' => 0,
            'Unknown' => 0,
            'Lost Notifications' => 0,
            'Not Sent/Blacklisted' => 0,
            'Blank' => 0,
            'Other' => 0,
        ];

        // Process results
        foreach ($results as $row) {
            $dlr = $row->thedlr ?? '';
            $count = (int) $row->thecnt;

            switch ($dlr) {
                case 'Delivered':
                    $stats['Delivered'] = $count;
                    break;
                case 'Non Delivered':
                    $stats['Non Delivered'] = $count;
                    break;
                case 'Unknown':
                    $stats['Unknown'] = $count;
                    break;
                case 'Lost Notifications':
                    $stats['Lost Notifications'] = $count;
                    break;
                case 'Not Sent/Blacklisted':
                    $stats['Not Sent/Blacklisted'] = $count;
                    break;
                case '':
                    $stats['Blank'] = $count;
                    break;
                default:
                    $stats['Other'] += $count;
                    break;
            }
        }

        // Format stats string
        $statsString = sprintf(
            "Delivered:%s, Non Delivered:%s, Unknown:%s, Lost Notification:%s, Not Sent/Blacklisted:%s, Blank:%s",
            number_format($stats['Delivered']),
            number_format($stats['Non Delivered']),
            number_format($stats['Unknown']),
            number_format($stats['Lost Notifications']),
            number_format($stats['Not Sent/Blacklisted']),
            number_format($stats['Blank'])
        );

        if ($stats['Other'] > 0) {
            $statsString .= ", Other:" . number_format($stats['Other']);
        }

        // Update campaign record
        DB::table('smsg_campaigns')
            ->where('campaignid', $campaignref)
            ->where('userref', $userref)
            ->update([
                'dlrstatsdate' => Carbon::now()->format('YmdHis'),
                'dlrstats' => $statsString
            ]);
    }

    /**
     * Process click statistics for campaigns
     */
    private function processClickStats($days, $specificCampaign = null, $specificUser = null)
    {
        $cutoffDate = Carbon::now()->subDays($days)->format('Ymd000000');
        
        // Build query for campaigns
        $query = DB::table('smsg_campaigns')
            ->select('userref', 'campaignid')
            ->where('datetime', '>', $cutoffDate);

        if ($specificCampaign) {
            $query->where('campaignid', $specificCampaign);
        }
        if ($specificUser) {
            $query->where('userref', $specificUser);
        }

        $campaigns = $query->get();
        
        $this->info("Found " . count($campaigns) . " campaigns to process for click stats");

        $bar = $this->output->createProgressBar(count($campaigns));
        $bar->start();

        foreach ($campaigns as $campaign) {
            try {
                $this->updateClickStats($campaign->userref, $campaign->campaignid);
            } catch (\Exception $e) {
                $this->errorCount++;
                Log::warning("Click Stats Error for campaign {$campaign->campaignid}", [
                    'error' => $e->getMessage()
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Update click stats for a specific campaign
     */
    private function updateClickStats($userref, $campaignref)
    {
        // Get total rows (URLs generated)
        $totalRows = DB::table('smsg_shorturl_forwarding')
            ->where('campaignref', $campaignref)
            ->where('userref', $userref)
            ->count();

        if ($totalRows == 0) {
            // No short URLs for this campaign, skip
            return;
        }

        // Get click stats
        $clickStats = DB::table('smsg_shorturl_forwarding')
            ->where('campaignref', $campaignref)
            ->where('userref', $userref)
            ->where('numclicks', '>', 0)
            ->selectRaw('SUM(numclicks) as totalclicks, COUNT(*) as totaluniqueclicks')
            ->first();

        $totalClicks = (int) ($clickStats->totalclicks ?? 0);
        $totalUniqueClicks = (int) ($clickStats->totaluniqueclicks ?? 0);
        
        // Calculate percentage
        $percentageClicked = $totalRows > 0 
            ? number_format(($totalUniqueClicks / ($totalRows / 100)), 2) 
            : '0.00';

        // Format stats string
        $statsString = sprintf(
            "Total Clicks:%s, Total Unique Clicks:%s, Percentage of Links Clicked:%s%%",
            number_format($totalClicks),
            number_format($totalUniqueClicks),
            $percentageClicked
        );

        // Update campaign record
        DB::table('smsg_campaigns')
            ->where('campaignid', $campaignref)
            ->where('userref', $userref)
            ->update([
                'clickstatsdate' => Carbon::now()->format('YmdHis'),
                'clickstats' => $statsString
            ]);
    }

    /**
     * Check if a table exists
     */
    private function tableExists($tableName)
    {
        try {
            $result = DB::select("SHOW TABLES LIKE ?", [$tableName]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
