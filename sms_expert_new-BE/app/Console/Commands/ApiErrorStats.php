<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * View API error statistics
 */
class ApiErrorStats extends Command
{
    protected $signature = 'api:error-stats 
                            {--days=7 : Number of days to show stats for}
                            {--severity= : Filter by severity (low, medium, high, critical)}';

    protected $description = 'View API error statistics';

    public function handle()
    {
        $days = (int) $this->option('days');
        $severity = $this->option('severity');
        
        $fromDate = Carbon::now()->subDays($days);
        
        $this->info("API Error Statistics (Last {$days} days)");
        $this->info(str_repeat('=', 50));
        $this->newLine();

        // Total errors
        $query = DB::table('api_error_logs')
            ->where('created_at', '>=', $fromDate);
        
        if ($severity) {
            $query->where('severity', $severity);
        }
        
        $totalErrors = $query->count();
        $this->line("Total Errors: <fg=red>{$totalErrors}</>");
        $this->newLine();

        // By severity
        $this->info('Errors by Severity:');
        $severityStats = DB::table('api_error_logs')
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $fromDate)
            ->groupBy('severity')
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->get();

        $severityData = [];
        foreach ($severityStats as $stat) {
            $color = match($stat->severity) {
                'critical' => 'red',
                'high' => 'yellow',
                'medium' => 'cyan',
                default => 'white',
            };
            $severityData[] = [$stat->severity, "<fg={$color}>{$stat->count}</>"];
        }
        $this->table(['Severity', 'Count'], $severityData);
        $this->newLine();

        // By status code
        $this->info('Errors by Status Code:');
        $statusStats = DB::table('api_error_logs')
            ->select('status_code', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $fromDate)
            ->whereNotNull('status_code')
            ->groupBy('status_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $statusData = [];
        foreach ($statusStats as $stat) {
            $statusData[] = [$stat->status_code, $stat->count];
        }
        $this->table(['Status Code', 'Count'], $statusData);
        $this->newLine();

        // By endpoint
        $this->info('Top 10 Error Endpoints:');
        $endpointStats = DB::table('api_error_logs')
            ->select('method', 'path', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', $fromDate)
            ->groupBy('method', 'path')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $endpointData = [];
        foreach ($endpointStats as $stat) {
            $endpointData[] = [$stat->method, $stat->path, $stat->count];
        }
        $this->table(['Method', 'Path', 'Count'], $endpointData);
        $this->newLine();

        // Recent critical errors
        $this->info('Recent Critical/High Errors (Last 5):');
        $recentErrors = DB::table('api_error_logs')
            ->select('created_at', 'severity', 'method', 'path', 'error_message')
            ->where('created_at', '>=', $fromDate)
            ->whereIn('severity', ['critical', 'high'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentData = [];
        foreach ($recentErrors as $error) {
            $recentData[] = [
                Carbon::parse($error->created_at)->format('Y-m-d H:i'),
                $error->severity,
                $error->method . ' ' . $error->path,
                substr($error->error_message ?? 'N/A', 0, 40) . '...',
            ];
        }
        $this->table(['Time', 'Severity', 'Endpoint', 'Message'], $recentData);

        return 0;
    }
}
