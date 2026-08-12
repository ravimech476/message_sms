<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportJob;
use App\Services\ReportGenerationService;
use App\Mail\ReportReadyMail;
use App\Http\Controllers\Admin\ReportsController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Generate any pending report_jobs DIRECTLY, without RabbitMQ. Use this to test
 * the pipeline (generation + email) when the reports:consume worker isn't
 * running, or schedule it as a safety net so reports never get stuck 'pending'.
 *
 *   php artisan reports:run-pending
 */
class RunPendingReports extends Command
{
    protected $signature = 'reports:run-pending {--limit=25} {--include-stuck : also retry jobs stuck on processing}';
    protected $description = 'Generate pending report_jobs directly (no RabbitMQ worker required)';

    public function handle()
    {
        $service = new ReportGenerationService();

        $statuses = [ReportJob::STATUS_PENDING];
        if ($this->option('include-stuck')) {
            $statuses[] = ReportJob::STATUS_PROCESSING;
        }

        $jobs = ReportJob::whereIn('status', $statuses)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('No pending reports to generate.');
            return Command::SUCCESS;
        }

        $this->info("Generating {$jobs->count()} report(s)...");

        foreach ($jobs as $job) {
            $this->line("  #{$job->id}  {$job->report_name}");
            $job->update(['status' => ReportJob::STATUS_PROCESSING]);

            try {
                $service->generate($job); // sets status=ready + file_path

                try {
                    Mail::to($job->email)->send(new ReportReadyMail($job, ReportsController::downloadUrl($job)));
                    $this->info("    -> ready, emailed {$job->email}");
                } catch (\Throwable $mailEx) {
                    Log::warning("reports:run-pending — email failed for job {$job->id}: " . $mailEx->getMessage());
                    $this->warn("    -> ready, but EMAIL FAILED: " . $mailEx->getMessage());
                }
            } catch (\Throwable $e) {
                $job->update([
                    'status'        => ReportJob::STATUS_FAILED,
                    'error_message' => substr($e->getMessage(), 0, 1000),
                    'completed_at'  => now('Europe/London'),
                ]);
                Log::error("reports:run-pending — job {$job->id} failed: " . $e->getMessage());
                $this->error("    -> FAILED: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
