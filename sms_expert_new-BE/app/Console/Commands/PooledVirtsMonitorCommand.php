<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;

/**
 * Pooled Dedicated Virtuals Monitoring + Management
 *
 * Based on legacy pooledvirtsmonitor.php
 * - Sweeps funds from master to sub-accounts (runs 2 AM - 11 PM)
 * - Checks pool virtuals availability (runs at 6:00 AM)
 */
class PooledVirtsMonitorCommand extends Command
{
    use LogsCronExecution;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pooledvirts:monitor
                            {--debug : Send emails to debug recipient only}
                            {--dry-run : Show what would be done without making changes}
                            {--check-pool : Force run pool virts check regardless of time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pooled Dedicated Virtuals Monitoring + Management - Sweep funds from master to sub-accounts and check pool virtuals';

    /**
     * Maximum iterations for fund sweeping to prevent infinite loops
     */
    protected int $maxIterations = 1000;

    /**
     * Minimum UK virtuals needed in pool (Nexmo + mBlox + Sinch)
     */
    protected int $ukVirtsNeeded = 5;

    /**
     * Minimum USA virtuals needed in pool (mBird)
     */
    protected int $usaVirtsNeeded = -1; // -1 means disabled

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return $this->executeWithLogging('pooledvirts:monitor', function () {
            $debug = $this->option('debug');
            $dryRun = $this->option('dry-run');
            $forceCheckPool = $this->option('check-pool');

            $hour = (int) now()->format('G');
            $minute = (int) now()->format('i');

            $this->info("Pooled Virtuals Monitor - " . now()->format('Y-m-d H:i:s'));
            $this->info("Hour: {$hour}, Minute: {$minute}");

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No changes will be made');
            }

            $actions = [];

            // Fund sweeping (runs from 2 AM to 11 PM)
            if ($hour >= 2 && $hour <= 23) {
                $sweepCount = $this->sweepFunds($debug, $dryRun);
                $actions[] = "sweep:{$sweepCount}";
            } else {
                $this->info('Outside fund sweeping hours (2 AM - 11 PM)');
            }

            // Pool virtuals check (runs at 6:00 AM or if forced)
            if (($hour == 6 && $minute == 0) || $forceCheckPool) {
                $this->checkMyPoolVirts($debug, $dryRun);
                $actions[] = 'pool-check';
            }

            if (empty($actions)) {
                return 'No actions performed';
            }

            return 'Actions: ' . implode(', ', $actions);
        });
    }

    /**
     * Sweep funds from master accounts to sub-accounts
     */
    protected function sweepFunds(bool $debug, bool $dryRun): int
    {
        $this->info('--- FUND SWEEPING ---');

        $iterations = 0;
        $doMoreRows = true;

        while ($doMoreRows && $iterations < $this->maxIterations) {
            $doMoreRows = false;

            // Get accounts that need topping up
            // Order ensures sub-accounts are done before their master account
            // The LIMIT 1 is necessary to process one at a time
            // Only process users migrated to new system (migration_flag = 'new')
            $account = DB::table('users')
                ->selectRaw("
                    bigid,
                    contactname,
                    uname,
                    masteruname,
                    subusrkey,
                    walletminlevel,
                    ((smsg_wallet - smsg_server1_sent) - smsg_server2_sent) as walletavailable,
                    walletloan,
                    IF(uname = masteruname, '2', '1') as theorder
                ")
                ->where('walletminlevel', '>', 0)
                ->whereRaw('((smsg_wallet - smsg_server1_sent) - smsg_server2_sent) < walletminlevel')
                ->where('migration_flag', 'new') // Only process users migrated to new system
                ->orderBy('theorder', 'asc')
                ->limit(1)
                ->first();

            if (!$account) {
                break;
            }

            $iterations++;
            $doMoreRows = true;

            $contactName = urldecode($account->contactname);
            $walletMinLevelStr = sprintf("%.0f", $account->walletminlevel);
            $walletAvailableStr = sprintf("%.0f", $account->walletavailable);
            $walletLoanStr = sprintf("%.0f", $account->walletloan);

            if ($account->masteruname == $account->uname) {
                // Master account - add loan and alert
                $this->processMasterAccount(
                    $account,
                    $contactName,
                    $walletMinLevelStr,
                    $walletAvailableStr,
                    $walletLoanStr,
                    $iterations,
                    $debug,
                    $dryRun
                );
            } else {
                // Sub-account - sweep from master
                $this->processSubAccount(
                    $account,
                    $contactName,
                    $walletMinLevelStr,
                    $walletAvailableStr,
                    $walletLoanStr,
                    $iterations,
                    $debug,
                    $dryRun
                );
            }
        }

        $this->info("Fund sweeping completed: {$iterations} accounts processed");

        return $iterations;
    }

    /**
     * Process master account - add loan if below minimum
     */
    protected function processMasterAccount(
        object $account,
        string $contactName,
        string $walletMinLevelStr,
        string $walletAvailableStr,
        string $walletLoanStr,
        int $iteration,
        bool $debug,
        bool $dryRun
    ): void {
        $topupAmount = $account->walletminlevel;
        $topupAmountStr = sprintf("%.0f", $topupAmount);

        $this->line("  [{$iteration}] Master account: {$account->uname} ({$contactName})");
        $this->line("      Wallet: {$walletAvailableStr} / Min: {$walletMinLevelStr} / Topup: {$topupAmountStr}");

        if (!$dryRun) {
            DB::table('users')
                ->where('bigid', $account->bigid)
                ->update([
                    'smsg_wallet' => DB::raw("smsg_wallet + {$topupAmount}"),
                    'walletloan' => DB::raw("walletloan + {$topupAmount}"),
                ]);
        }

        $newWalletAvailable = sprintf("%.0f", $account->walletavailable + $topupAmount);
        $newWalletLoan = sprintf("%.0f", $account->walletloan + $topupAmount);

        $subject = "VirtPool Alert({$iteration}:{$account->walletloan}): master account low funds topped up ({$contactName})";
        $content = "<b>master account wallet below sweep level so topped up with new loan...</b><br><br>";
        $content .= "Owner: {$contactName}<br>";
        $content .= "UserName: {$account->uname}<br>";
        $content .= "Wallet Sweep Level:  {$walletMinLevelStr}<br>";
        $content .= "Old Wallet Available:  {$walletAvailableStr}<br>";
        $content .= "Old Wallet Loan:  {$walletLoanStr}<br><br>";
        $content .= "Topup Amount:  {$topupAmountStr}<br>";
        $content .= "New Wallet Available:  {$newWalletAvailable}<br>";
        $content .= "New Wallet Loan:  {$newWalletLoan}<br>";

        if (!$dryRun) {
            $this->sendAlert($subject, $content, $debug);
        }

        Log::info('Pooled Virts: Master account topped up', [
            'account' => $account->uname,
            'topup' => $topupAmount,
            'new_loan' => $account->walletloan + $topupAmount,
        ]);
    }

    /**
     * Process sub-account - sweep funds from master
     */
    protected function processSubAccount(
        object $account,
        string $contactName,
        string $walletMinLevelStr,
        string $walletAvailableStr,
        string $walletLoanStr,
        int $iteration,
        bool $debug,
        bool $dryRun
    ): void {
        $topupAmount = $account->walletminlevel;
        $topupAmountStr = sprintf("%.0f", $topupAmount);

        // Get master account info
        $master = DB::table('users')
            ->selectRaw("
                bigid,
                contactname,
                walletminlevel,
                ((smsg_wallet - smsg_server1_sent) - smsg_server2_sent) as walletavailable,
                walletloan
            ")
            ->where('uname', $account->masteruname)
            ->first();

        if (!$master) {
            $this->error("  Master account not found: {$account->masteruname}");
            return;
        }

        $masterContactName = urldecode($master->contactname);
        $masterWalletMinLevelStr = sprintf("%.0f", $master->walletminlevel);
        $masterWalletAvailableStr = sprintf("%.0f", $master->walletavailable);
        $masterWalletLoanStr = sprintf("%.0f", $master->walletloan);
        $masterReductionAmount = $topupAmount;
        $masterReductionAmountStr = sprintf("%.0f", $masterReductionAmount);

        $this->line("  [{$iteration}] Sub-account: {$account->uname} ({$contactName})");
        $this->line("      Wallet: {$walletAvailableStr} / Min: {$walletMinLevelStr} / Topup: {$topupAmountStr}");
        $this->line("      Master: {$account->masteruname} / Available: {$masterWalletAvailableStr}");

        // Update sub-account
        if (!$dryRun) {
            DB::table('users')
                ->where('bigid', $account->bigid)
                ->update([
                    'smsg_wallet' => DB::raw("smsg_wallet + {$topupAmount}"),
                ]);
        }

        $newSubWalletAvailable = sprintf("%.0f", $account->walletavailable + $topupAmount);

        // Check if master also needs topping up
        if (($master->walletavailable - $masterReductionAmount) < $master->walletminlevel) {
            // Master also needs loan - topup by minlevel*2 to prevent updating every minute
            $masterTopupAmount = ($master->walletminlevel - ($master->walletavailable - $masterReductionAmount)) + $master->walletminlevel;
            $masterTopupAmountStr = sprintf("%.0f", $masterTopupAmount);

            if (!$dryRun) {
                DB::table('users')
                    ->where('uname', $account->masteruname)
                    ->update([
                        'smsg_wallet' => DB::raw("(smsg_wallet - {$masterReductionAmount}) + {$masterTopupAmount}"),
                        'walletloan' => DB::raw("walletloan + {$masterTopupAmount}"),
                    ]);
            }

            $newMasterWalletAvailable = sprintf("%.0f", ($master->walletavailable - $masterReductionAmount) + $masterTopupAmount);
            $newMasterWalletLoan = sprintf("%.0f", $master->walletloan + $masterTopupAmount);

            $subject = "VirtPool Alert({$iteration}:" . sprintf("%.0f", $master->walletloan + $masterTopupAmount) . "): sub+master account low funds topped up ({$contactName})";
            $content = "<b>sub account wallet below sweep level so topped up from master...</b><br><br>";
            $content .= "Owner: {$contactName}<br>";
            $content .= "SubUSRKey: {$account->subusrkey}<br>";
            $content .= "UserName: {$account->uname}<br>";
            $content .= "Wallet Sweep Level:  {$walletMinLevelStr}<br>";
            $content .= "Wallet Loan:  {$walletLoanStr}<br>";
            $content .= "Old Wallet Available:  {$walletAvailableStr}<br><br>";
            $content .= "Topup Amount:  {$topupAmountStr}<br>";
            $content .= "New Wallet Available:  {$newSubWalletAvailable}<br><br><br>";
            $content .= "<b>master account wallet reduced accordingly; but also below sweep level so topped up with new loan...</b><br><br>";
            $content .= "Owner: {$masterContactName}<br>";
            $content .= "UserName: {$account->masteruname}<br>";
            $content .= "Old Wallet Available:  {$masterWalletAvailableStr}<br>";
            $content .= "Old Wallet Loan:  {$masterWalletLoanStr}<br><br>";
            $content .= "Reduction Amount:  {$masterReductionAmountStr}<br>";
            $content .= "Topup Amount:  {$masterTopupAmountStr}<br>";
            $content .= "New Wallet Available:  {$newMasterWalletAvailable}<br>";
            $content .= "New Wallet Loan:  {$newMasterWalletLoan}<br>";

            Log::info('Pooled Virts: Sub+Master account topped up', [
                'sub_account' => $account->uname,
                'master_account' => $account->masteruname,
                'sub_topup' => $topupAmount,
                'master_topup' => $masterTopupAmount,
                'new_master_loan' => $master->walletloan + $masterTopupAmount,
            ]);
        } else {
            // Master doesn't need loan, just reduce wallet
            if (!$dryRun) {
                DB::table('users')
                    ->where('uname', $account->masteruname)
                    ->update([
                        'smsg_wallet' => DB::raw("smsg_wallet - {$masterReductionAmount}"),
                    ]);
            }

            $newMasterWalletAvailable = sprintf("%.0f", $master->walletavailable - $masterReductionAmount);

            $subject = "VirtPool Alert({$iteration}): sub account low funds topped up ({$contactName})";
            $content = "<b>sub account wallet below sweep level so topped up from master...</b><br><br>";
            $content .= "Owner: {$contactName}<br>";
            $content .= "SubUSRKey: {$account->subusrkey}<br>";
            $content .= "UserName: {$account->uname}<br>";
            $content .= "Wallet Sweep Level:  {$walletMinLevelStr}<br>";
            $content .= "Wallet Loan:  {$walletLoanStr}<br>";
            $content .= "Old Wallet Available:  {$walletAvailableStr}<br><br>";
            $content .= "Topup Amount:  {$topupAmountStr}<br>";
            $content .= "New Wallet Available:  {$newSubWalletAvailable}<br><br><br>";
            $content .= "<b>master account wallet reduced accordingly...</b><br><br>";
            $content .= "Owner: {$masterContactName}<br>";
            $content .= "UserName: {$account->masteruname}<br>";
            $content .= "Wallet Loan:  {$masterWalletLoanStr}<br>";
            $content .= "Old Wallet Available:  {$masterWalletAvailableStr}<br><br>";
            $content .= "Reduction Amount:  {$masterReductionAmountStr}<br>";
            $content .= "New Wallet Available:  {$newMasterWalletAvailable}<br>";

            Log::info('Pooled Virts: Sub account topped up from master', [
                'sub_account' => $account->uname,
                'master_account' => $account->masteruname,
                'topup' => $topupAmount,
            ]);
        }

        if (!$dryRun) {
            $this->sendAlert($subject, $content, $debug);
        }
    }

    /**
     * Check pool virtuals status - monitors free UK+USA virts in MyPool
     * Based on legacy checkmypoolvirts() function
     */
    protected function checkMyPoolVirts(bool $debug, bool $dryRun, string $callerStr = ''): void
    {
        $this->info('--- POOL VIRTUALS CHECK ---');

        // Count USA virts (mBird/USA)
        $usaVirts = DB::table('itagg_instance as ii')
            ->join('smsshortcodes as ss', 'ss.id', '=', 'ii.smsshortcodes_id')
            ->where('ii.keyword', '*')
            ->where('ss.shortcode_notes', 'steve/iTagg - unassigned')
            ->where('ss.whichoperator', 'mBird/USA')
            ->count();

        // Count UK virts (mBlox + Nexmo + Sinch)
        $ukVirts = DB::table('itagg_instance as ii')
            ->join('smsshortcodes as ss', 'ss.id', '=', 'ii.smsshortcodes_id')
            ->where('ii.keyword', '*')
            ->where('ss.shortcode_notes', 'steve/iTagg - unassigned')
            ->where(function ($query) {
                $query->where('ss.whichoperator', 'like', 'mBlox%')
                    ->orWhere('ss.whichoperator', 'like', 'Nexmo%')
                    ->orWhere('ss.whichoperator', 'like', 'Sinch%');
            })
            ->count();

        $this->line("  USA virts (mBird): {$usaVirts} (needed: {$this->usaVirtsNeeded})");
        $this->line("  UK virts (Nexmo+mBlox+Sinch): {$ukVirts} (needed: {$this->ukVirtsNeeded})");

        $lowSub = '';
        $usaLowStr = "{$usaVirts} USA virts (mBird)<br><br>";
        $ukLowStr = "{$ukVirts} UK virts (Nexmo+mBlox+Sinch)<br><br>";

        if ($usaVirts <= $this->usaVirtsNeeded) {
            $lowSub .= 'USA+';
        }

        if ($ukVirts <= $this->ukVirtsNeeded) {
            $lowSub .= 'UK+';
        }

        // Remove trailing +
        $lowSub = rtrim($lowSub, '+');

        if ($usaVirts <= $this->usaVirtsNeeded || $ukVirts <= $this->ukVirtsNeeded) {
            $this->warn("  Pool is low on {$lowSub} virts!");

            if (!$dryRun) {
                $subject = "VirtPool{$callerStr} Alert: MyPool is low on {$lowSub} virts";
                $content = "<b>Remaining in Central MyPool...</b><br><br>{$usaLowStr}{$ukLowStr}";
                $this->sendAlert($subject, $content, $debug);
            }

            Log::warning('Pool virtuals low', [
                'usa_virts' => $usaVirts,
                'uk_virts' => $ukVirts,
                'low_pools' => $lowSub,
            ]);
        } else {
            $this->info('  Pool levels are OK');
        }

        $this->info("Pool virtuals check completed");
    }

    /**
     * Send alert email
     */
    protected function sendAlert(string $subject, string $content, bool $debug): void
    {
        $recipients = $debug
            ? [config('reports.debug_recipient', 'anand@nedholdings.com')]
            : config('pooledvirts.alert_recipients', ['anand@nedholdings.com']);

        $alertData = [
            'subject' => $subject,
            'title' => $subject,
            'content' => $content,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        try {
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\PooledVirtsAlertMail',
                    trim($recipient),
                    ['alert_data' => $alertData],
                    [],
                    10 // High priority
                );
            }

            $this->info("Alert queued: {$subject}");
        } catch (\Exception $e) {
            $this->error("Failed to send alert: " . $e->getMessage());
            Log::error('Pooled Virts alert failed', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
