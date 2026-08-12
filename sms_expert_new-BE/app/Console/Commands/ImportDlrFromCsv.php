<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\NexmoDeliveryQueueService;
use App\Services\Queue\RabbitMQService;

/**
 * Import a Vonage/Nexmo delivery-report CSV export and update DLR status exactly
 * like the live nexmo:fetch-delivery-reports command — match each row to smsg_log
 * by message_id (onesixty_suppliermsgref), update deliverystatus2, and fire the
 * DLR push callback. Sources rows from a CSV instead of the Reports API.
 *
 * CSV columns (as exported by Vonage): message_id, to, status, error_code,
 * error_code_description, total_price. NOTE: the `to` column is often mangled by
 * Excel into scientific notation (e.g. 4.47883E+11) and is NOT used for matching —
 * message_id is the (indexed) match key.
 *
 * Default mode QUEUES each row to RabbitMQ (nexmo.delivery.reports), identical to the
 * Nexmo report, so the running DLR consumer does the status-update + callback.
 * Use --sync to process each row inline (no consumer needed).
 *
 *   php artisan dlr:import-csv                          # public/report_SMS.csv, queued
 *   php artisan dlr:import-csv path/to/file.csv         # custom file
 *   php artisan dlr:import-csv --sync                   # update + callback inline
 *   php artisan dlr:import-csv --limit=100 --sync       # test on the first 100 rows
 */
class ImportDlrFromCsv extends Command
{
    protected $signature = 'dlr:import-csv
                            {file? : Path to the CSV (defaults to public/report_SMS.csv)}
                            {--sync : Process each row inline (update + callback) instead of queuing}
                            {--chunk=500 : Rows per queue batch (queue mode)}
                            {--limit=0 : Only process the first N data rows (0 = all)}';

    protected $description = 'Import a Vonage/Nexmo DLR CSV export and update statuses + fire callbacks (same flow as nexmo:fetch-delivery-reports)';

    public function handle(): int
    {
        $file  = $this->argument('file') ?: public_path('report_SMS.csv');
        $sync  = (bool) $this->option('sync');
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = (int) $this->option('limit');

        if (!is_file($file)) {
            $this->error("CSV not found: {$file}");
            return self::FAILURE;
        }

        $fh = fopen($file, 'r');
        if ($fh === false) {
            $this->error("Cannot open CSV: {$file}");
            return self::FAILURE;
        }

        // Header → column-index map.
        $header = fgetcsv($fh);
        if ($header === false) {
            $this->error('CSV is empty.');
            fclose($fh);
            return self::FAILURE;
        }
        $map = array_flip(array_map(fn($h) => strtolower(trim($h)), $header));
        foreach (['message_id', 'status'] as $req) {
            if (!isset($map[$req])) {
                $this->error("CSV is missing the required '{$req}' column. Found: " . implode(', ', $header));
                fclose($fh);
                return self::FAILURE;
            }
        }

        $queueService = new NexmoDeliveryQueueService(app(RabbitMQService::class));

        $this->info(($sync ? 'SYNC' : 'QUEUE') . " mode — importing DLRs from: {$file}");

        $read = 0; $queued = 0; $skipped = 0; $processed = 0; $noMatch = 0;
        $batch = [];

        $col = fn(array $row, string $name) => isset($map[$name]) ? trim((string) ($row[$map[$name]] ?? '')) : '';

        while (($row = fgetcsv($fh)) !== false) {
            if ($limit > 0 && $read >= $limit) {
                break;
            }

            $messageId = $col($row, 'message_id');
            if ($messageId === '' || strtolower($messageId) === 'message_id') {
                continue; // blank line or a repeated header
            }

            $record = [
                'message_id'             => $messageId,
                'status'                 => $col($row, 'status'),
                'error_code'             => $col($row, 'error_code'),
                'error_code_description' => $col($row, 'error_code_description'),
                'total_price'            => $col($row, 'total_price') ?: 0,
                'to'                     => '', // CSV `to` is Excel-corrupted; message_id is the match key
            ];
            $read++;

            if ($sync) {
                // processDeliveryReport() always returns true (it "acks" the record), so we
                // pre-check the indexed match column to report real matches vs not-found.
                $matched = DB::table('smsg_log')->where('onesixty_suppliermsgref', $messageId)->exists();

                // Matches by onesixty_suppliermsgref, updates deliverystatus2 and fires the
                // DLR push callback — identical to the live DLR consumer.
                $queueService->processDeliveryReport($record);

                if ($matched) {
                    $processed++;
                } else {
                    $noMatch++;
                }
                if ($read % 1000 === 0) {
                    $this->info("  processed {$read} rows (updated {$processed}, not-found {$noMatch})…");
                }
            } else {
                $batch[] = $record;
                if (count($batch) >= $chunk) {
                    $r = $queueService->queueBatchDeliveryReports($batch);
                    $queued  += $r['queued'];
                    $skipped += $r['failed'];
                    $batch = [];
                    $this->info("  queued {$queued} / read {$read}…");
                }
            }
        }

        if (!$sync && !empty($batch)) {
            $r = $queueService->queueBatchDeliveryReports($batch);
            $queued  += $r['queued'];
            $skipped += $r['failed'];
        }

        fclose($fh);

        $this->newLine();
        if ($sync) {
            $this->info("Done (sync). Read: {$read}, Matched & updated: {$processed}, Not found in smsg_log: {$noMatch}");
        } else {
            $this->info("Done (queued). Read: {$read}, Queued: {$queued}, Skipped: {$skipped}");
            $this->line("The DLR consumer (nexmo.delivery.reports) will update each deliverystatus2 and fire the callback — same as nexmo:fetch-delivery-reports.");
        }

        return self::SUCCESS;
    }
}
