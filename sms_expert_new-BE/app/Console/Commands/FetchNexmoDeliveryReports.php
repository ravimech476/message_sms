<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;
use App\Services\Queue\NexmoDeliveryQueueService;
use App\Services\Queue\RabbitMQService;

class FetchNexmoDeliveryReports extends Command
{
    use LogsCronExecution;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nexmo:fetch-delivery-reports
                            {--lookback-minutes= : Override the lookback window in minutes (e.g. 1440 for a 24h catch-up). Defaults to NEXMO_REPORTS_LOOKBACK_MINUTES.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Nexmo SMS delivery reports every 5 minutes and queue for processing via RabbitMQ';

    /**
     * The Nexmo delivery queue service
     *
     * @var NexmoDeliveryQueueService
     */
    protected $queueService;

    /**
     * Last API error detail (status + body), so the thrown exception can carry
     * the real cause instead of the opaque "Failed to fetch reports".
     *
     * @var string|null
     */
    protected $lastApiError = null;

    /**
     * Set when the fetch could not complete because Vonage rate-limited us (HTTP 429).
     * A 429 is transient — the every-minute schedule retries shortly — so the run
     * exits gracefully WITHOUT raising a "Cron job failed" alert.
     *
     * @var bool
     */
    protected $rateLimited = false;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        return $this->executeWithLogging('nexmo:fetch-delivery-reports', function () {
            $this->info('Starting Nexmo delivery report fetch...');

            // Initialize the queue service
            $rabbitMQService = app(RabbitMQService::class);
            $this->queueService = new NexmoDeliveryQueueService($rabbitMQService);

            // Calculate the lookback window in REAL UTC. The Nexmo Reports API
            // requires both bounds to be in the past and interprets the trailing
            // `Z` as UTC — so the timestamp MUST be UTC. Carbon::now() would return
            // Europe/London (BST = UTC+1 in summer); formatting that with a literal
            // `Z` sends a time 1 hour ahead of real UTC and Vonage rejects it with
            // "date_start must be in the past". Build the window in UTC and end it at
            // now (never the future).
            // Lookback overlaps deliberately so late-arriving DLRs aren't missed. At 1-min
            // cadence a smaller window is plenty (the consumer's idempotency guard dedupes the
            // overlap); shrink via NEXMO_REPORTS_LOOKBACK_MINUTES to cut API/RabbitMQ load.
            // --lookback-minutes wins (used by the daily 24h catch-up job); else env default.
            $lookbackOpt = $this->option('lookback-minutes');
            $lookback = ($lookbackOpt !== null && $lookbackOpt !== '')
                ? max(2, (int) $lookbackOpt)
                : max(2, (int) env('NEXMO_REPORTS_LOOKBACK_MINUTES', 50));
            $this->info("Lookback window: {$lookback} minutes");
            // End the window a small margin BEFORE "now" (default 60s) rather than exactly now.
            // Vonage requires both bounds strictly in the past; ending at the current instant can
            // trip "date_start must be in the past" if the app server's clock is a touch ahead of
            // Vonage's, or the request takes a moment. The next run's overlap covers the gap.
            // If the server clock is badly ahead, fetchNexmoReports() re-anchors to Vonage's own
            // clock (its HTTP Date header) and retries — so it self-corrects regardless of skew.
            $endBuffer = max(0, (int) env('NEXMO_REPORTS_END_BUFFER_SECONDS', 60));

            // Make API call to Nexmo (builds + sends the window, with clock-skew self-correction)
            $response = $this->fetchNexmoReports($lookback, $endBuffer);

            if (!$response) {
                // 429 rate-limit is transient — exit gracefully so it does NOT raise a
                // "Cron job failed" alert. The next every-minute run retries. Vonage's
                // Reports API allows 10 sync req/s; a 1-min cron should rarely hit this,
                // but bursts or shared limits can, and that's not a real failure.
                if ($this->rateLimited) {
                    $msg = 'Nexmo Reports API rate limited (429) — skipping this run, will retry next minute.';
                    $this->warn($msg);
                    Log::warning($msg);
                    return $msg;
                }

                $detail = $this->lastApiError ? (': ' . $this->lastApiError) : '';
                $this->error('Failed to fetch reports from Nexmo API' . $detail);
                throw new \Exception('Failed to fetch reports from Nexmo API' . $detail);
            }

            // Dispatch records to RabbitMQ queue for processing
            $result = $this->dispatchRecordsToQueue($response);

            $this->info('Nexmo delivery report fetch completed successfully');
            return $result;
        });
    }

    /**
     * Fetch reports from Nexmo API
     *
     * @param string $dateStart
     * @param string $dateEnd
     * @return array|null
     */
    protected function fetchNexmoReports($lookback, $endBuffer)
    {
        try {
            // Get API credentials. Read via config() (which falls back to env at
            // config-build time) rather than raw env() — once `php artisan config:cache`
            // runs in production, env() returns NULL outside config files, which would
            // send EMPTY Basic-Auth credentials and Vonage answers 401 → the opaque
            // "Failed to fetch reports". config() works cached or not. For Vonage the
            // SMPP system_id IS the API key and the SMPP password IS the API secret.
            $apiKey = config('smpp.connections.vonage.system_id') ?: env('SMPP_SYSTEM_ID');
            $apiSecret = config('smpp.connections.vonage.password') ?: env('SMPP_PASSWORD');

            if (empty($apiKey) || empty($apiSecret)) {
                $this->lastApiError = 'Nexmo API credentials missing (smpp.connections.vonage.system_id / .password '
                    . 'are empty). If config is cached, run `php artisan config:clear` after setting '
                    . 'SMPP_SYSTEM_ID / SMPP_PASSWORD in .env.';
                $this->error($this->lastApiError);
                Log::error('Nexmo Reports API: missing credentials');
                return null;
            }

            // Build API URL
            $url = 'https://api.nexmo.com/v2/reports/records';

            // Rate-limit / transient-error handling + clock-skew self-correction.
            // Vonage Reports API allows 10 synchronous req/s on /v2/reports/records; exceeding
            // it returns HTTP 429. A 1-min cron is far under that, but we still handle 429 / 5xx
            // / network blips defensively.
            //
            // The 422 "date_start must be in the past" error means THIS server's clock is ahead
            // of Vonage's. We self-correct, in order:
            //   1) Re-anchor the window to Vonage's OWN clock via the HTTP `Date` response header
            //      (authoritative — fixes any skew in one shot). But some 422 responses omit that
            //      header, so this can't be the only mechanism.
            //   2) Fallback: progressively push the ENTIRE window further into the past
            //      ($shiftStep seconds per retry) until Vonage accepts it. Needs no header and
            //      corrects any positive skew given enough attempts. DLRs aren't real-time, so an
            //      older window is harmless — the 1-min cron overlaps and the consumer dedupes.
            $maxAttempts = max(1, (int) env('NEXMO_REPORTS_MAX_ATTEMPTS', 6));
            $shiftStep   = max(60, (int) env('NEXMO_REPORTS_SKEW_SHIFT_SECONDS', 300)); // +5 min/retry
            $reanchorNow = null;   // Vonage-clock anchor once known; null = use local clock
            $reanchored  = false;  // Date-header re-anchor only attempted once
            $extraShift  = 0;      // cumulative backward shift (seconds) for the fallback

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                // Rebuild the window each attempt from the chosen anchor, pushed back by any
                // accumulated skew shift.
                $anchor = ($reanchorNow ?? Carbon::now('UTC'))->copy()->subSeconds($extraShift);
                [$dateStart, $dateEnd] = $this->buildWindow($anchor, $lookback, $endBuffer);

                $queryParams = [
                    'product' => 'SMS',
                    'direction' => 'outbound',
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'include_message' => 'true',
                    'account_id' => $apiKey,
                ];

                $this->info("Fetching reports from {$dateStart} to {$dateEnd}");
                $this->info("Calling Nexmo API (attempt {$attempt}/{$maxAttempts})...");

                $response = Http::withBasicAuth($apiKey, $apiSecret)
                    ->timeout(30)
                    ->get($url, $queryParams);

                if ($response->successful()) {
                    $data = $response->json();
                    $allRecords = $data['records'] ?? [];
                    $pageCount = 1;

                    // PAGINATION: the Reports API returns results in pages, with the next page
                    // URL in _links.next.href (it carries all the original query params incl.
                    // account_id + the window). Follow it until exhausted so a 24h catch-up
                    // doesn't silently drop everything past page 1. Each page is fetched with
                    // its own 429/5xx retry (fetchPageWithRetries). Capped to avoid runaways.
                    $maxPages = max(1, (int) env('NEXMO_REPORTS_MAX_PAGES', 200));
                    $nextUrl  = $data['_links']['next']['href'] ?? null;

                    while ($nextUrl && $pageCount < $maxPages) {
                        // Vonage's _links.next.href carries the pagination cursor but DROPS the
                        // required date_start window param, so following it verbatim 422s with
                        // "Missing required parameter: date_start". Re-inject the original window
                        // params on every page (cursor from the href still advances).
                        [$pageUrl, $pageParams] = $this->reinjectWindow($nextUrl, $queryParams);
                        $pageData = $this->fetchPageWithRetries($pageUrl, $pageParams, $apiKey, $apiSecret, $maxAttempts);
                        if ($pageData === null) {
                            // A page failed after its retries (429/5xx/4xx). Don't lose what we
                            // already have — queue the fetched records; the next run / daily
                            // catch-up picks up the rest. lastApiError/rateLimited already set.
                            $this->warn("Pagination stopped at page {$pageCount} due to API error — processing " . count($allRecords) . " record(s) fetched so far.");
                            $this->warn("  \u{21b3} Reason: " . ($this->lastApiError ?: 'unknown'));
                            $this->warn("  \u{21b3} Failed next-page URL: " . $nextUrl);
                            Log::warning('Nexmo Reports API: pagination stopped early', [
                                'pages_fetched' => $pageCount,
                                'records_so_far' => count($allRecords),
                                'error' => $this->lastApiError,
                                'failed_next_url' => $nextUrl,
                            ]);
                            break;
                        }
                        $allRecords = array_merge($allRecords, $pageData['records'] ?? []);
                        $nextUrl = $pageData['_links']['next']['href'] ?? null;
                        $pageCount++;
                    }

                    if ($nextUrl && $pageCount >= $maxPages) {
                        $this->warn("Reached max page cap ({$maxPages}); remaining pages will be fetched next run.");
                        Log::warning('Nexmo Reports API: hit max page cap', ['max_pages' => $maxPages]);
                    }

                    $total = count($allRecords);
                    $this->info("API Response: {$total} record(s) across {$pageCount} page(s)");
                    return ['records' => $allRecords, 'items_count' => $total];
                }

                $status = $response->status();
                $body = $response->body();

                // 422 "...must be in the past" = server clock ahead of Vonage. Self-correct.
                if ($status === 422 && stripos($body, 'must be in the past') !== false) {
                    // Fast path: anchor to Vonage's own clock from the Date header (once).
                    if (!$reanchored) {
                        $vonageDateHeader = $response->header('Date');
                        if ($vonageDateHeader) {
                            try {
                                $reanchorNow = Carbon::parse($vonageDateHeader)->setTimezone('UTC');
                                $reanchored = true;
                                $extraShift = 0; // anchor is authoritative; reset the fallback shift
                                Log::warning('Nexmo Reports API: re-anchoring window to Vonage Date header', [
                                    'vonage_now'   => $reanchorNow->toIso8601String(),
                                    'server_now'   => Carbon::now('UTC')->toIso8601String(),
                                    'skew_seconds' => Carbon::now('UTC')->diffInSeconds($reanchorNow, false),
                                ]);
                                $this->warn("Clock skew — re-anchoring to Vonage time ({$reanchorNow->toIso8601String()}) and retrying.");
                                continue;
                            } catch (\Throwable $e) {
                                // fall through to the progressive shift below
                            }
                        }
                    }

                    // Fallback: push the window further into the past and retry.
                    if ($attempt < $maxAttempts) {
                        $extraShift += $shiftStep;
                        Log::warning('Nexmo Reports API: window not in the past — shifting back and retrying', [
                            'attempt' => $attempt, 'extra_shift_seconds' => $extraShift,
                        ]);
                        $this->warn("Window not in the past — shifting back {$extraShift}s and retrying.");
                        continue;
                    }

                    $this->lastApiError = "HTTP 422 'must be in the past' unresolved after {$maxAttempts} attempts "
                        . "(server clock likely far ahead of Vonage — check NTP/time sync). Last window {$dateStart}..{$dateEnd}.";
                    $this->error($this->lastApiError);
                    Log::error('Nexmo API Error', ['status' => $status, 'body' => $body]);
                    return null;
                }

                // 429 = rate limited. Respect Retry-After (seconds) if Vonage sent one.
                if ($status === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                    $wait = $retryAfter > 0 ? $retryAfter : min(30, (int) pow(2, $attempt));
                    Log::warning('Nexmo Reports API rate limited (429)', [
                        'retry_after' => $retryAfter, 'wait_seconds' => $wait, 'attempt' => $attempt,
                    ]);
                    $this->warn("Rate limited (429). Backing off {$wait}s…");
                    if ($attempt < $maxAttempts) { sleep($wait); continue; }
                    $this->lastApiError = "HTTP 429 rate limited after {$maxAttempts} attempts";
                    $this->rateLimited = true; // transient — let handle() skip gracefully (no failure alert)
                    return null;
                }

                // 5xx = transient server error — retry with backoff.
                if ($status >= 500) {
                    $wait = min(30, (int) pow(2, $attempt));
                    Log::warning('Nexmo Reports API server error', ['status' => $status, 'attempt' => $attempt]);
                    $this->warn("Server error {$status}. Retrying in {$wait}s…");
                    if ($attempt < $maxAttempts) { sleep($wait); continue; }
                    $this->lastApiError = "HTTP {$status} server error after {$maxAttempts} attempts";
                    return null;
                }

                // Other 4xx = permanent (bad params/auth). Do not retry.
                $this->lastApiError = 'HTTP ' . $status . ' - ' . substr($response->body(), 0, 500);
                $this->error('API Error: ' . $status . ' - ' . $response->body());
                Log::error('Nexmo API Error', ['status' => $status, 'body' => $response->body()]);
                return null;
            }

            $this->lastApiError = $this->lastApiError ?: "Exhausted {$maxAttempts} attempts without a successful response";
            return null;

        } catch (\Exception $e) {
            $this->lastApiError = 'Exception: ' . $e->getMessage();
            $this->error('Exception during API call: ' . $e->getMessage());
            Log::error('Nexmo API Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch ONE Reports API page (a pagination `next` href) with retry handling for
     * 429 (rate limit — honours Retry-After) and 5xx / network errors (transient).
     * The 422 clock-skew re-anchor is NOT applied here: it only concerns the windowed
     * FIRST request; pagination hrefs already carry a valid past window. Returns the
     * decoded page (array), or null on unrecoverable failure (sets lastApiError, and
     * rateLimited on 429 so handle() can skip gracefully).
     */
    private function fetchPageWithRetries(string $url, array $queryParams, string $apiKey, string $apiSecret, int $maxAttempts): ?array
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withBasicAuth($apiKey, $apiSecret)->timeout(30)->get($url, $queryParams);
            } catch (\Throwable $e) {
                Log::warning('Nexmo Reports API pagination request exception', ['error' => $e->getMessage(), 'attempt' => $attempt]);
                if ($attempt < $maxAttempts) { sleep(min(30, (int) pow(2, $attempt))); continue; }
                $this->lastApiError = 'Pagination request exception: ' . $e->getMessage();
                return null;
            }

            if ($response->successful()) {
                return $response->json();
            }

            $status = $response->status();

            // 429 = rate limited. Respect Retry-After; back off and retry.
            if ($status === 429) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                $wait = $retryAfter > 0 ? $retryAfter : min(30, (int) pow(2, $attempt));
                Log::warning('Nexmo Reports API rate limited (429) during pagination', ['wait_seconds' => $wait, 'attempt' => $attempt]);
                $this->warn("Pagination rate limited (429). Backing off {$wait}s…");
                if ($attempt < $maxAttempts) { sleep($wait); continue; }
                $this->lastApiError = "HTTP 429 rate limited during pagination after {$maxAttempts} attempts";
                $this->rateLimited = true;
                return null;
            }

            // 5xx = transient server error — retry with backoff.
            if ($status >= 500) {
                $wait = min(30, (int) pow(2, $attempt));
                Log::warning('Nexmo Reports API server error during pagination', ['status' => $status, 'attempt' => $attempt]);
                $this->warn("Pagination server error {$status}. Retrying in {$wait}s…");
                if ($attempt < $maxAttempts) { sleep($wait); continue; }
                $this->lastApiError = "HTTP {$status} server error during pagination after {$maxAttempts} attempts";
                return null;
            }

            // Other 4xx = permanent (bad cursor/auth). Do not retry.
            $this->lastApiError = 'Pagination HTTP ' . $status . ' - ' . substr($response->body(), 0, 300);
            Log::error('Nexmo API pagination error', ['status' => $status, 'body' => $response->body()]);
            return null;
        }

        return null;
    }

    /**
     * Vonage's Reports API `_links.next.href` carries the pagination cursor but DROPS the
     * required `date_start` (and sometimes `date_end`) window param — so following it verbatim
     * fails with 422 "Missing required parameter: date_start". Rebuild the page request as the
     * href's base path + the ORIGINAL window params merged with the href's own cursor params,
     * so the window survives while the cursor advances. Returns [baseUrl, mergedParams].
     */
    private function reinjectWindow(string $nextUrl, array $originalParams): array
    {
        $parsed = parse_url($nextUrl);
        parse_str($parsed['query'] ?? '', $cursorParams);

        // Cursor/page params from the href win on shared keys; date_start/date_end (absent from
        // the href) are preserved from the original request.
        $merged = array_merge($originalParams, $cursorParams);

        $base = ($parsed['scheme'] ?? 'https') . '://'
            . ($parsed['host'] ?? 'api.nexmo.com')
            . ($parsed['path'] ?? '/v2/reports/records');

        return [$base, $merged];
    }

    /**
     * Build the [date_start, date_end] window (UTC, ...Z) from a given "now".
     * Ends $endBuffer seconds before $nowUtc and spans $lookback minutes back.
     * $nowUtc is either the local clock or Vonage's clock (on re-anchor).
     */
    private function buildWindow(\Carbon\Carbon $nowUtc, int $lookback, int $endBuffer): array
    {
        $end = $nowUtc->copy()->subSeconds($endBuffer);
        return [
            $end->copy()->subMinutes($lookback)->format('Y-m-d\TH:i:s\Z'),
            $end->copy()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Dispatch records to RabbitMQ queue for processing
     *
     * @param array $response
     * @return string
     */
    protected function dispatchRecordsToQueue($response)
    {
        if (!isset($response['records']) || empty($response['records'])) {
            $this->info('No records to process');
            return 'No records to process';
        }

        $records = $response['records'];
        $totalRecords = count($records);

        // Queue all records using the batch method
        $result = $this->queueService->queueBatchDeliveryReports($records);

        $message = sprintf(
            "Dispatch complete. Total: %d, Queued: %d, Skipped: %d",
            $totalRecords,
            $result['queued'],
            $result['failed']
        );
        
        $this->info($message);
        
        Log::info('Nexmo delivery reports dispatched to RabbitMQ', [
            'total' => $totalRecords,
            'queued' => $result['queued'],
            'failed' => $result['failed']
        ]);
        
        return $message;
    }
}
