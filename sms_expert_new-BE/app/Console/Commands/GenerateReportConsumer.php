<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Queue\RabbitMQService;
use App\Services\ReportGenerationService;
use App\Models\ReportJob;
use App\Mail\ReportReadyMail;
use App\Http\Controllers\Admin\ReportsController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Long-running worker that generates admin reports queued on the
 * RABBITMQ_REPORTS_QUEUE. The DB row (report_jobs) owns the state; on success
 * it emails the requester a download link.
 *
 * Run: php artisan reports:consume   (add to supervisor for production)
 */
class GenerateReportConsumer extends Command
{
    protected $signature = 'reports:consume {--prefetch=1}';
    protected $description = 'Generate admin reports queued for background processing (RabbitMQ consumer)';

    public function handle()
    {
        $rabbit  = new RabbitMQService();
        $service = new ReportGenerationService();
        $queue   = env('RABBITMQ_REPORTS_QUEUE', 'reports.generate');

        $this->info("[reports:consume] listening on {$queue} ...");

        $rabbit->consumeFromQueue($queue, function ($data) use ($service) {
            $jobId = (int) ($data['report_job_id'] ?? 0);
            if ($jobId <= 0) {
                Log::warning('reports:consume — message missing report_job_id', (array) $data);
                return true; // ack — bad payload, nothing to retry
            }

            $job = ReportJob::find($jobId);
            if (!$job) {
                Log::warning("reports:consume — ReportJob {$jobId} not found");
                return true;
            }

            // Idempotent on redelivery — don't regenerate a finished report.
            if (in_array($job->status, [ReportJob::STATUS_READY, ReportJob::STATUS_FAILED], true)) {
                return true;
            }

            $job->update(['status' => ReportJob::STATUS_PROCESSING]);

            try {
                $service->generate($job); // sets status=ready + file_path

                // Notify the requester (non-fatal — the report is already saved).
                try {
                    $url = ReportsController::downloadUrl($job);
                    Mail::to($job->email)->send(new ReportReadyMail($job, $url));
                } catch (\Throwable $mailEx) {
                    Log::warning("reports:consume — notify email failed for job {$jobId}: " . $mailEx->getMessage());
                }

                $this->info("[reports:consume] report #{$jobId} ready ({$job->file_name})");
            } catch (\Throwable $e) {
                $job->update([
                    'status'        => ReportJob::STATUS_FAILED,
                    'error_message' => substr($e->getMessage(), 0, 1000),
                    'completed_at'  => now('Europe/London'),
                ]);
                Log::error("reports:consume — report #{$jobId} failed: " . $e->getMessage());
                $this->error("[reports:consume] report #{$jobId} FAILED: " . $e->getMessage());
            }

            return true; // ack — DB row owns the state
        }, (int) $this->option('prefetch'));

        return Command::SUCCESS;
    }
}
