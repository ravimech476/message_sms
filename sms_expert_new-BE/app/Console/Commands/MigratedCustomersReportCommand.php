<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Mail\MigratedCustomersReportMail;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;

/**
 * Daily migrated-customers summary email.
 *
 *   php artisan migration:daily-report
 *
 * Scheduled at 05:00 Europe/London (see routes/console.php). Recipients come from
 * MIGRATION_REPORT_EMAIL in .env (comma-separated for multiple).
 */
class MigratedCustomersReportCommand extends Command
{
    // Same OLD SYSTEM customer-listing query the admin UI uses, so counts match.
    use LegacyCustomerList;

    protected $signature = 'migration:daily-report';
    protected $description = 'Email a daily migrated-customers summary (total migrated, yesterday migrated, pending) to MIGRATION_REPORT_EMAIL.';

    public function handle(): int
    {
        $tz        = 'Europe/London';
        $yesterday = Carbon::now($tz)->subDay()->toDateString(); // Y-m-d

        // Use the SAME customer set as the admin UI (legacy OLD SYSTEM customer
        // listing, via the shared LegacyCustomerList trait) so the counts match
        // what staff see on the customer pages — NOT a raw users count, which also
        // includes admin / test / disabled rows.
        //
        // migration_flag enum('old','new'): 'new' = migrated, 'old' = pending.
        $customers = $this->getLegacyCustomers();
        $bigids    = $customers->pluck('bigid')->filter()->values()->all();

        $totalCustomers = $customers->count();
        $totalMigrated  = $customers->where('migration_flag', 'new')->count();
        $pendingMigrate = $customers->where('migration_flag', 'old')->count();

        // "Yesterday" needs migrated_at (not in the legacy SELECT), so scope a
        // migrated_at query to the same customer set by bigid.
        $yesterdayMigrated = !empty($bigids)
            ? DB::table('users')
                ->whereIn('bigid', $bigids)
                ->where('migration_flag', 'new')
                ->whereDate('migrated_at', $yesterday)
                ->count()
            : 0;

        $stats = [
            'date'               => Carbon::now($tz)->subDay()->format('D jS M Y'),
            'total_customers'    => $totalCustomers,
            'total_migrated'     => $totalMigrated,
            'yesterday_migrated' => $yesterdayMigrated,
            'pending_migrate'    => $pendingMigrate,
            'migrated_percent'   => $totalCustomers > 0 ? round($totalMigrated / $totalCustomers * 100, 1) : 0,
        ];

        $this->info("Total customers: {$totalCustomers} | Migrated: {$totalMigrated} | Yesterday: {$yesterdayMigrated} | Pending: {$pendingMigrate}");

        // Read from config (survives config:cache) which maps MIGRATION_REPORT_EMAIL.
        $raw = (string) config('mail.migration_report_email', '');
        $recipients = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if (empty($recipients)) {
            $this->warn('MIGRATION_REPORT_EMAIL is not set — skipping email (counts logged).');
            Log::warning('migration:daily-report — MIGRATION_REPORT_EMAIL not configured; email skipped.', $stats);
            return self::SUCCESS;
        }

        try {
            Mail::to($recipients)->send(new MigratedCustomersReportMail($stats));
            $this->info('Report emailed to: ' . implode(', ', $recipients));
        } catch (\Throwable $e) {
            $this->error('Email failed: ' . $e->getMessage());
            Log::error('migration:daily-report email failed: ' . $e->getMessage(), $stats);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
