<?php

namespace App\Services;

use App\Models\ReportJob;
use App\Exports\PostPayFilteredExport;
use App\Exports\DailySmsFilteredExport;
use App\Exports\MoneyTransferredFilteredExport;
use App\Exports\SalesReportFilteredExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

/**
 * Generates the Excel file for a queued ReportJob by reusing the same
 * *FilteredExport classes the synchronous "Export" buttons use, and stores it
 * under storage/app/reports/. Marks the job ready on success.
 */
class ReportGenerationService
{
    public function generate(ReportJob $job): void
    {
        $from = optional($job->date_from)->format('Y-m-d') ?: (string) $job->date_from;
        $to   = optional($job->date_to)->format('Y-m-d') ?: (string) $job->date_to;
        $customerIds = $job->customer_ids_array;

        $export = $this->makeExport($job, $from, $to, $customerIds);

        // Safe, unique filename derived from the (user-editable) report name.
        $safe = Str::slug($job->report_name ?: $job->report_type) ?: 'report';
        $fileName = $safe . '_' . $job->id . '.xlsx';
        $relativePath = 'reports/' . $fileName;

        // Stored on the 'local' disk => storage/app/reports/...
        Excel::store($export, $relativePath, 'local');

        $job->update([
            'file_name'     => $fileName,
            'file_path'     => $relativePath,
            'status'        => ReportJob::STATUS_READY,
            'completed_at'  => now('Europe/London'),
            'error_message' => null,
        ]);
    }

    private function makeExport(ReportJob $job, string $from, string $to, array $customerIds)
    {
        switch ($job->report_type) {
            case 'postpay':
                return new PostPayFilteredExport($from, $to, $customerIds);
            case 'daily_sms':
                return new DailySmsFilteredExport($from, $to, $customerIds, (string) $job->search);
            case 'money_transfer':
                return new MoneyTransferredFilteredExport($from, $to, $customerIds);
            case 'monthly_sales':
                // The Sales Report carries its status + payment-method filters in the
                // job's `search` field as JSON: {"statuses":[...],"methods":[...]}.
                // Falls back to a plain comma list of statuses for older jobs.
                $filters  = json_decode((string) $job->search, true);
                $statuses = is_array($filters['statuses'] ?? null)
                    ? $filters['statuses']
                    : array_values(array_filter(array_map('trim', explode(',', (string) $job->search))));
                $methods  = is_array($filters['methods'] ?? null) ? $filters['methods'] : [];
                return new SalesReportFilteredExport($from, $to, $customerIds, $statuses, $methods);
            default:
                throw new \InvalidArgumentException("Unknown report type: {$job->report_type}");
        }
    }
}
