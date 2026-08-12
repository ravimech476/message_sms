<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Http\Controllers\Admin\ReportsController;
use App\Mail\DailyStatsReportMail;
use App\Services\Queue\EmailQueueService;

class DailyStatsReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:daily-stats {--debug : Run in debug mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send daily SMS statistics report to management';

    /**
     * Excluded test/demo account bigids
     */
    protected $excludedBigids = [
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
        $debug = $this->option('debug');
        
        $this->info('Starting Daily Stats Report...');

        try {
            $archiveYm = Carbon::now()->subMonth()->format('ym');
            $statsDoDay = Carbon::yesterday()->format('Ymd');
            $statsDoDay2 = Carbon::yesterday()->format('D jS M');

            // Determine month range
            if (Carbon::now()->day == 1) {
                $wholeMonthYm = Carbon::now()->subMonth()->format('Ym');
                $wholeMonthStr = 'all of ' . Carbon::now()->subMonth()->format('F Y');
            } else {
                $wholeMonthYm = Carbon::now()->format('Ym');
                $wholeMonthStr = Carbon::now()->format('F Y') . ' to date';
            }

            // Generate reports
            $reportsStr = "<b>Reports...</b><br>";

            // Yesterday's invoices
            $yesterdayStart = Carbon::yesterday()->startOfDay()->timestamp;
            $yesterdayEnd = Carbon::yesterday()->endOfDay()->timestamp;
            $reportsStr .= "Invoices (yesterday): <a href='" . ReportsController::generateInvoicesUrl($yesterdayStart, $yesterdayEnd, Carbon::yesterday()->format('Ymd')) . "'>download</a><br>";

            // Month invoices
            if (Carbon::now()->day == 1) {
                $monthStart = Carbon::now()->subMonth()->startOfMonth()->startOfDay()->timestamp;
                $monthEnd = Carbon::now()->subMonth()->endOfMonth()->endOfDay()->timestamp;
                $reportsStr .= "Invoices (all of " . Carbon::now()->subMonth()->format('F Y') . "): <a href='" . ReportsController::generateInvoicesUrl($monthStart, $monthEnd, Carbon::now()->subMonth()->format('Ym')) . "'>download</a><br>";
            } else {
                $monthStart = Carbon::now()->startOfMonth()->startOfDay()->timestamp;
                $monthEnd = Carbon::yesterday()->endOfDay()->timestamp;
                $reportsStr .= "Invoices (" . Carbon::now()->format('F Y') . " prior to today): <a href='" . ReportsController::generateInvoicesUrl($monthStart, $monthEnd, Carbon::now()->format('Ym')) . "'>download</a><br>";
            }

            // Get traffic stats
            $trafficStats = $this->getTrafficStats($statsDoDay, $archiveYm);

            // Get purchases
            $productStr = $this->getPurchases($statsDoDay);

            // Get wallet stats
            $walletStats = $this->getWalletStats();

            // Client wallets report
            $reportsStr .= "Client Wallets: <a href='" . ReportsController::generateClientWalletsUrl(Carbon::now()->format('Ymd')) . "'>download</a><br>";

            // Get purchases as structured data
            $purchasesData = $this->getPurchasesData($statsDoDay);

            // Build reports links
            $reportsLinks = $this->getReportsLinks($statsDoDay, $yesterdayStart, $yesterdayEnd, $monthStart ?? null, $monthEnd ?? null, $wholeMonthStr ?? null);

            // Build report data for template
            $reportData = [
                'subject' => "SMS Expert Daily Stats for {$statsDoDay2}",
                'report_date' => $statsDoDay2,
                'is_debug' => $debug,
                'traffic' => $trafficStats,
                'purchases' => $purchasesData,
                'wallet' => [
                    'total' => $walletStats['wallet'],
                    'client_count' => $walletStats['clientCount'],
                ],
                'reports' => $reportsLinks,
                'generated_at' => now()->format('Y-m-d H:i:s T'),
            ];

            // Send email
            $this->sendReport($reportData, $debug);

            $this->info('Daily Stats Report completed successfully!');
            
        } catch (\Exception $e) {
            $this->error('Error generating daily stats report: ' . $e->getMessage());
            \Log::error('DailyStatsReport Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }

        return 0;
    }

    /**
     * Get traffic statistics
     */
    protected function getTrafficStats($statsDoDay, $archiveYm)
    {
        $mainTableQuery = DB::table('smsg_log')
            ->selectRaw('(SUM(profit) - SUM(hlrlookupcost)) as theprofit')
            ->selectRaw('SUM(numparts) as thevolume')
            ->selectRaw('SUM(costprice) as thecostprice')
            ->selectRaw('SUM(userprice) as theuserprice')
            ->where('timesent', 'like', $statsDoDay . '%')
            ->where('migration_flag', 'new');

        // Check if archive table exists
        $archiveTable = 'smsg_log_' . $archiveYm;
        $archiveExists = DB::select("SHOW TABLES LIKE '{$archiveTable}'");

        if (!empty($archiveExists)) {
            $archiveQuery = DB::table($archiveTable)
                ->selectRaw('(SUM(profit) - SUM(hlrlookupcost)) as theprofit')
                ->selectRaw('SUM(numparts) as thevolume')
                ->selectRaw('SUM(costprice) as thecostprice')
                ->selectRaw('SUM(userprice) as theuserprice')
                ->where('timesent', 'like', $statsDoDay . '%');

            $result = DB::table(DB::raw("({$mainTableQuery->toSql()} UNION {$archiveQuery->toSql()}) as thelogs"))
                ->mergeBindings($mainTableQuery)
                ->mergeBindings($archiveQuery)
                ->selectRaw('SUM(theprofit) as theprofit, SUM(thevolume) as thevolume, SUM(thecostprice) as thecostprice, SUM(theuserprice) as theuserprice')
                ->first();
        } else {
            $result = $mainTableQuery->first();
        }

        $theprofit = $result->theprofit ?? 0;
        $thevolume = $result->thevolume ?? 0;
        $profitpersms = $thevolume > 0 ? ($theprofit / $thevolume) : 0;

        return [
            'profit' => $theprofit,
            'volume' => $thevolume,
            'costprice' => $result->thecostprice ?? 0,
            'userprice' => $result->theuserprice ?? 0,
            'profitpersms' => $profitpersms,
        ];
    }

    /**
     * Get purchases for the day (legacy HTML format)
     */
    protected function getPurchases($statsDoDay)
    {
        $purchases = DB::table('invoices as i')
            ->join('orderitem as o', 'i.id', '=', 'o.invoiceref')
            ->selectRaw("IF(o.productref = 51930, 'VMNs or 60300 keywords', IF(o.productref = 20551, 'SMS', '?')) as theproduct")
            ->selectRaw('SUM(o.nonvatprice) as theuserprice')
            ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(i.paiddate), '%Y%m%d') = ?", [$statsDoDay])
            ->where('i.paiddate', '>', 0)
            ->groupBy('o.productref')
            ->orderBy('theproduct')
            ->get();

        $productStr = "<b>Purchases (cleared+processed. excl VAT)...</b><br>";
        $productStr2 = '';

        foreach ($purchases as $purchase) {
            $productStr2 .= $purchase->theproduct . ": £" . number_format($purchase->theuserprice, 2) . "<br>";
        }

        if (empty($productStr2)) {
            $productStr2 = 'none<br>';
        }

        return $productStr . $productStr2;
    }

    /**
     * Get purchases data as structured array for template
     */
    protected function getPurchasesData($statsDoDay)
    {
        $purchases = DB::table('invoices as i')
            ->join('orderitem as o', 'i.id', '=', 'o.invoiceref')
            ->selectRaw("IF(o.productref = 51930, 'VMNs or 60300 keywords', IF(o.productref = 20551, 'SMS', '?')) as theproduct")
            ->selectRaw('SUM(o.nonvatprice) as theuserprice')
            ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(i.paiddate), '%Y%m%d') = ?", [$statsDoDay])
            ->where('i.paiddate', '>', 0)
            ->groupBy('o.productref')
            ->orderBy('theproduct')
            ->get();

        $result = [];
        foreach ($purchases as $purchase) {
            $result[] = [
                'product' => $purchase->theproduct,
                'amount' => $purchase->theuserprice,
            ];
        }

        return $result;
    }

    /**
     * Get report download links as structured array
     */
    protected function getReportsLinks($statsDoDay, $yesterdayStart, $yesterdayEnd, $monthStart, $monthEnd, $wholeMonthStr)
    {
        $links = [];

        // Yesterday's invoices
        $links[] = [
            'name' => 'Invoices (yesterday)',
            'url' => ReportsController::generateInvoicesUrl($yesterdayStart, $yesterdayEnd, Carbon::yesterday()->format('Ymd')),
        ];

        // Month invoices
        if ($monthStart && $monthEnd) {
            // Use subMonth format if it's first day of month, otherwise current month
            $monthFormat = Carbon::now()->day == 1
                ? Carbon::now()->subMonth()->format('Ym')
                : Carbon::now()->format('Ym');

            $links[] = [
                'name' => "Invoices ({$wholeMonthStr})",
                'url' => ReportsController::generateInvoicesUrl($monthStart, $monthEnd, $monthFormat),
            ];
        }

        // Client Wallets
        $links[] = [
            'name' => 'Client Wallets',
            'url' => ReportsController::generateClientWalletsUrl(Carbon::now()->format('Ymd')),
        ];

        return $links;
    }

    /**
     * Get wallet statistics
     * Only includes users with migration_flag = 'new' (migrated to new system)
     */
    protected function getWalletStats()
    {
        // Total wallet exposure
        $walletTotal = DB::table('users')
            ->selectRaw('SUM(smsg_wallet - smsg_server1_sent - smsg_server2_sent) as thewallet')
            ->whereRaw('(smsg_wallet - smsg_server1_sent - smsg_server2_sent) > 0')
            ->whereNotIn('bigid', $this->excludedBigids)
            ->where('migration_flag', 'new') // Only process users migrated to new system
            ->first();

        // Client count
        $clientCount = DB::table('users')
            ->whereRaw('(smsg_wallet - smsg_server1_sent - smsg_server2_sent) > 0')
            ->where(function($query) {
                $query->where('masteruname', '')
                      ->orWhereColumn('masteruname', 'uname');
            })
            ->where('bit_disabled', 0)
            ->whereNotIn('bigid', $this->excludedBigids)
            ->where('migration_flag', 'new') // Only process users migrated to new system
            ->count();

        return [
            'wallet' => $walletTotal->thewallet ?? 0,
            'clientCount' => $clientCount,
        ];
    }

    /**
     * Generate client wallets CSV report
     * Only includes users with migration_flag = 'new' (migrated to new system)
     */
    protected function generateClientWalletsReport()
    {
        $dateFile = Carbon::now()->format('Ymd');
        $uniqueId = md5(uniqid(rand(), true));
        $fileName = "{$dateFile}_client_wallets_{$uniqueId}.csv";
        $filePath = "reports/{$fileName}";

        $clients = DB::table('users')
            ->selectRaw("IF(masteruname = '' OR masteruname = uname, uname, masteruname) as theusr")
            ->selectRaw('SUM(smsg_wallet - smsg_server1_sent - smsg_server2_sent) as thewallet')
            ->whereRaw('(smsg_wallet - smsg_server1_sent - smsg_server2_sent) > 0')
            ->whereNotIn('bigid', $this->excludedBigids)
            ->where('migration_flag', 'new') // Only process users migrated to new system
            ->groupBy('theusr')
            ->havingRaw('thewallet > 0')
            ->orderBy('thewallet', 'desc')
            ->get();

        $csvContent = "Customer,Wallet,User ID\n";

        foreach ($clients as $client) {
            $userDetails = DB::table('users')
                ->select('contactname', 'busname')
                ->where('uname', $client->theusr)
                ->first();

            $wallet = '£' . number_format($client->thewallet, 2);
            $contactname = trim(urldecode($userDetails->contactname ?? ''));
            $busname = trim(urldecode($userDetails->busname ?? ''));

            if (empty($busname) && empty($contactname)) {
                $customer = 'No customer details';
            } elseif (empty($busname)) {
                $customer = $contactname;
            } else {
                $customer = $busname;
            }

            $csvContent .= '"' . str_replace('"', '""', $customer) . '","' . $wallet . '","' . $client->theusr . "\"\n";
        }

        Storage::disk('public')->put($filePath, $csvContent);

        return url('storage/' . $filePath);
    }

    /**
     * Generate daily invoices CSV report
     */
    protected function getDailyInvoices($dateStart, $dateEnd, $dateFile)
    {
        $uniqueId = md5(uniqid(rand(), true));
        $fileName = "{$dateFile}_sms_invoices_{$uniqueId}.csv";
        $filePath = "reports/{$fileName}";

        $invoices = DB::table('invoices as i')
            ->join('orderitem as o', 'i.id', '=', 'o.invoiceref')
            ->join('users as u', 'o.userref', '=', 'u.bigid')
            ->selectRaw("i.id")
            ->selectRaw("IF(o.productref = 51930, 'VMNs, Keywords or Other', IF(o.productref = 20551, 'SMS Messages', 'Unknown Product')) as theproduct")
            ->selectRaw("IF(o.status IN ('order', 'invoice'), 'created', 'cancelled') as thestatus")
            ->selectRaw("IF(o.status IN ('order', 'invoice'), DATE_FORMAT(FROM_UNIXTIME(i.invoicedate), '%d/%m/%Y'), DATE_FORMAT(FROM_UNIXTIME(o.finalstatedate), '%d/%m/%Y')) as theday")
            ->select('o.nonvatprice', 'o.vatrate', 'o.fullprice', 'u.contactname', 'u.busname', 'u.bigid')
            ->where(function($query) use ($dateStart, $dateEnd) {
                $query->where(function($q) use ($dateStart, $dateEnd) {
                    $q->whereIn('o.status', ['order', 'invoice'])
                      ->whereBetween('i.invoicedate', [$dateStart, $dateEnd]);
                })->orWhere(function($q) use ($dateStart, $dateEnd) {
                    $q->where('o.status', 'orderdeleted')
                      ->whereBetween('o.finalstatedate', [$dateStart, $dateEnd]);
                });
            })
            ->orderBy('i.id')
            ->orderByRaw("IF(o.status IN ('order', 'invoice'), 'created', 'cancelled') DESC")
            ->get();

        $headers = ['InvoiceNo', 'Customer', 'InvoiceDate', 'DueDate', 'Terms', 'Location', 'Memo', 'Item(Product/Service)', 'ItemDescription', 'ItemQuantity', 'ItemRate', 'ItemAmount', 'ItemTaxCode', 'ItemTaxAmount', 'Service Date', 'Status'];
        $csvContent = implode(',', $headers) . "\n";

        foreach ($invoices as $invoice) {
            $vatAmount = '£' . number_format(($invoice->nonvatprice / 100) * $invoice->vatrate, 2);
            $nonVatPrice = '£' . number_format($invoice->nonvatprice, 2);
            $vatRate = number_format($invoice->vatrate, 2) . '%';

            $contactname = trim(urldecode($invoice->contactname ?? ''));
            $busname = trim(urldecode($invoice->busname ?? ''));

            if (empty($busname) && empty($contactname)) {
                $customer = 'No customer details';
            } elseif (empty($busname)) {
                $customer = $contactname;
            } else {
                $customer = $busname;
            }

            $row = [
                $invoice->id,
                $customer,
                $invoice->theday,
                $invoice->theday,
                'Due on receipt',
                '',
                '',
                $invoice->theproduct,
                '',
                '1',
                $nonVatPrice,
                $nonVatPrice,
                $vatRate,
                $vatAmount,
                $invoice->theday,
                $invoice->thestatus,
            ];

            $csvContent .= '"' . implode('","', array_map(function($val) {
                return str_replace('"', '""', $val);
            }, $row)) . "\"\n";
        }

        Storage::disk('public')->put($filePath, $csvContent);

        return url('storage/' . $filePath);
    }

    /**
     * Build email content
     */
    protected function buildEmailContent($statsDoDay2, $trafficStats, $productStr, $walletStats, $reportsStr)
    {
        return "SMS Expert Daily Stats for {$statsDoDay2}<br><br>
            <b>Traffic</b><font style='vertical-align:super; font-size:8pt;'>1</font><b>...</b><br>
            Volume Sent: " . number_format($trafficStats['volume']) . "<br>
            Client Cost: £" . number_format($trafficStats['userprice'], 2) . "<br>
            Our Cost: £" . number_format($trafficStats['costprice'], 2) . "<br>
            Profit: £" . number_format($trafficStats['profit'], 0) . "<br>
            Average SMS Profit (per sent): £" . sprintf("%.4f", $trafficStats['profitpersms']) . "<br><br>
            {$productStr}<br>
            <b>Other Stats</b><font style='vertical-align:super; font-size:8pt;'>2</font><b>...</b><br>
            Number of Clients (enabled with +ve wallets): " . $walletStats['clientCount'] . "<br>
            Total Wallet Exposure: £" . number_format($walletStats['wallet'], 0) . "<br><br>
            {$reportsStr}
            <br><br><br><br><br>
            <center>
                <font style='font-size:8pt;'>1 includes main internal/test account messages sent at cost</font><br>
                <font style='font-size:8pt;'>2 main internal/test accounts excluded, but other newer ones may have been included</font><br>
            </center><br>";
    }

    /**
     * Send the report email using template and RabbitMQ queue
     */
    protected function sendReport(array $reportData, $debug = false)
    {
        if ($debug) {
            $recipients = [config('reports.debug_recipient')];
        } else {
            $recipients = config('reports.daily_stats_recipients');
        }

        try {
            // Send via RabbitMQ queue
            $emailQueueService = new EmailQueueService();

            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\DailyStatsReportMail',
                    trim($recipient),
                    ['report_data' => $reportData],
                    [],
                    5 // Medium priority
                );
            }

            $this->info('Email queued to: ' . implode(', ', $recipients));

        } catch (\Exception $e) {
            // Fallback to direct send if queue fails
            $this->warn('Queue failed, sending directly: ' . $e->getMessage());

            foreach ($recipients as $recipient) {
                Mail::to(trim($recipient))->send(new DailyStatsReportMail($reportData));
            }

            $this->info('Email sent directly to: ' . implode(', ', $recipients));
        }
    }
}
