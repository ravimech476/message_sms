<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;

class UrlForwardDaemonCommand extends Command
{
    use LogsCronExecution;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'urlforward:process 
                            {--daemon=default : The daemon name to process}
                            {--debug : Enable debug mode}
                            {--dry-run : Show what would be done without making changes}
                            {--limit=100 : Maximum rows to process per run}
                            {--continuous : Run continuously instead of single batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'URL Forward Daemon - Process incoming SMS URL forwarding requests';

    /**
     * Touch file path
     */
    protected string $touchFile;

    /**
     * Daemon name
     */
    protected string $daemonName;

    /**
     * Debug mode
     */
    protected bool $debug = false;

    /**
     * Dry run mode
     */
    protected bool $dryRun = false;

    /**
     * White label client IPs
     */
    protected array $whitelabelClientIps = [];

    /**
     * Default outgoing IP for white label
     */
    protected string $defaultWhitelabelIp;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->daemonName = $this->option('daemon');
        $this->debug = $this->option('debug');
        $this->dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $continuous = $this->option('continuous');

        $this->touchFile = storage_path("app/urlforward-{$this->daemonName}.touch");

        // Load white label configuration
        $this->loadWhitelabelConfig();

        $this->info("URL Forward Daemon [{$this->daemonName}] - " . now()->format('Y-m-d H:i:s'));

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Check for existing process
        if (!$this->acquireLock()) {
            $this->error('Another process is already running for this daemon');
            return 1;
        }

        try {
            if ($continuous) {
                $this->runContinuous($limit);
            } else {
                $this->processBatch($limit);
            }
        } finally {
            $this->releaseLock();
        }

        return 0;
    }

    /**
     * Run continuously until stopped
     */
    protected function runContinuous(int $limit): void
    {
        $this->info('Running in continuous mode (Ctrl+C to stop)');

        while (true) {
            $this->updateTouchFile();
            $processed = $this->processBatch($limit);
            $this->updateTouchFile();

            if ($processed == 0) {
                sleep(1); // Wait 1 second if no rows to process
            }
        }
    }

    /**
     * Process a batch of URL forward requests
     * Only processes forwards for users migrated to new system (migration_flag = 'new')
     */
    protected function processBatch(int $limit): int
    {
        $nowTime = now()->format('Y-m-d H:i:s');

        // Get pending URL forward requests
        // Only process forwards for users migrated to new system
        $forwards = DB::table('url_forward as uf')
            ->join('itagg_instance as ii', 'uf.itagg_instance_id', '=', 'ii.id')
            ->join('users as u', 'ii.users_bigid', '=', 'u.bigid')
            ->select([
                'uf.id',
                'uf.url',
                'uf.retries_left',
                'uf.parameter_string',
                'uf.itagg_forwardinglog_id',
                'uf.itagg_incominglog_id',
                'uf.wait_minutes',
                'uf.itagg_instance_id',
                'uf.timeout_seconds'
            ])
            ->whereIn('uf.status', ['new', 'doing'])
            ->where('uf.retries_left', '>', 0)
            ->where('uf.dosendtime', '<=', $nowTime)
            ->where('uf.daemonname', $this->daemonName)
            ->where('u.migration_flag', 'new') // Only process users migrated to new system
            ->limit($limit)
            ->get();

        if ($forwards->isEmpty()) {
            $this->line("No pending forwards for daemon [{$this->daemonName}]");
            return 0;
        }

        $this->info("Found {$forwards->count()} pending forwards");

        $processed = 0;
        foreach ($forwards as $forward) {
            $this->updateTouchFile();
            $this->processForward($forward);
            $processed++;
        }

        return $processed;
    }

    /**
     * Process a single URL forward request
     */
    protected function processForward(object $forward): void
    {
        $this->line("Processing forward ID: {$forward->id}");

        // Mark as 'doing'
        if (!$this->dryRun) {
            $affected = DB::table('url_forward')
                ->where('id', $forward->id)
                ->update(['status' => 'doing']);

            if ($affected < 1) {
                $this->warn("  Row already being processed by another instance");
                return;
            }
        }

        $queryString = $forward->parameter_string;
        $waitTime = $forward->wait_minutes * 60;
        $curlTimeout = $forward->timeout_seconds ?? 15;

        $this->line("  URL: {$forward->url}");
        $this->line("  Timeout: {$curlTimeout}s");

        // Determine outgoing IP for white label
        $interfaceIp = $this->getOutgoingIp($forward->url);
        $sendMethod = 'post';

        // Start timing
        $startTime = time();

        try {
            // Check if this is a GET request (tpointcloudplatform)
            if (stripos($forward->url, 'tpointcloudplatform') !== false) {
                $sendMethod = 'get';
                $response = $this->makeGetRequest($forward->url, $queryString, $curlTimeout, $interfaceIp);
            } else {
                $response = $this->makePostRequest($forward->url, $queryString, $curlTimeout, $interfaceIp);
            }

            $endTime = time();
            $responseSpeed = $endTime - $startTime;

            $this->updateTouchFile();

            if ($response['success']) {
                $this->handleSuccess($forward, $response, $responseSpeed, $sendMethod);
            } else {
                $this->handleFailure($forward, $response, $responseSpeed, $waitTime, $curlTimeout, $queryString);
            }

        } catch (\Exception $e) {
            $endTime = time();
            $responseSpeed = $endTime - $startTime;

            $response = [
                'success' => false,
                'errno' => 0,
                'error' => $e->getMessage(),
                'result' => '',
                'http_status' => '0',
                'curl_info' => [],
            ];

            $this->handleFailure($forward, $response, $responseSpeed, $waitTime, $curlTimeout, $queryString);
        }
    }

    /**
     * Make a POST request
     */
    protected function makePostRequest(string $url, string $queryString, int $timeout, ?string $interfaceIp): array
    {
        parse_str($queryString, $params);

        try {
            $httpClient = Http::timeout($timeout)
                ->withOptions([
                    'verify' => false, // SSL verification disabled as in original
                ]);

            // Add interface IP if specified
            if ($interfaceIp) {
                $httpClient->withOptions(['curl' => [CURLOPT_INTERFACE => $interfaceIp]]);
            }

            $response = $httpClient->asForm()->post($url, $params);

            return [
                'success' => $response->successful() || $response->redirect(),
                'errno' => 0,
                'error' => '',
                'result' => addslashes($response->body()),
                'http_status' => (string) $response->status(),
                'curl_info' => [],
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'errno' => CURLE_OPERATION_TIMEDOUT,
                'error' => 'Connection timeout: ' . $e->getMessage(),
                'result' => '',
                'http_status' => '0',
                'curl_info' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errno' => 0,
                'error' => $e->getMessage(),
                'result' => '',
                'http_status' => '0',
                'curl_info' => [],
            ];
        }
    }

    /**
     * Make a GET request
     */
    protected function makeGetRequest(string $url, string $queryString, int $timeout, ?string $interfaceIp): array
    {
        $fullUrl = $url . '?' . $queryString;

        try {
            $httpClient = Http::timeout($timeout)
                ->withOptions([
                    'verify' => false,
                ]);

            if ($interfaceIp) {
                $httpClient->withOptions(['curl' => [CURLOPT_INTERFACE => $interfaceIp]]);
            }

            $response = $httpClient->get($fullUrl);

            return [
                'success' => $response->successful() || $response->redirect(),
                'errno' => 0,
                'error' => '',
                'result' => addslashes($response->body()),
                'http_status' => (string) $response->status(),
                'curl_info' => [],
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'success' => false,
                'errno' => CURLE_OPERATION_TIMEDOUT,
                'error' => 'Connection timeout: ' . $e->getMessage(),
                'result' => '',
                'http_status' => '0',
                'curl_info' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errno' => 0,
                'error' => $e->getMessage(),
                'result' => '',
                'http_status' => '0',
                'curl_info' => [],
            ];
        }
    }

    /**
     * Handle successful forward
     */
    protected function handleSuccess(object $forward, array $response, int $responseSpeed, string $sendMethod): void
    {
        $nowTime = now()->format('Y-m-d H:i:s');
        $urlTime = now()->format('YmdHis');

        $this->info("  SUCCESS - HTTP {$response['http_status']} ({$responseSpeed}s)");

        if ($this->dryRun) {
            return;
        }

        // Update url_forward
        DB::table('url_forward')
            ->where('id', $forward->id)
            ->update([
                'status' => 'processed',
                'server_response' => $response['result'],
                'last_connection_time' => $nowTime,
                'last_server_response_speed' => $responseSpeed,
                'http_status_code' => $response['http_status'],
                'sendmethod' => $sendMethod,
            ]);

        // Update itagg_forwardinglog
        if ($forward->itagg_forwardinglog_id) {
            DB::table('itagg_forwardinglog')
                ->where('id', $forward->itagg_forwardinglog_id)
                ->update([
                    'forwarded_url' => $forward->url,
                    'url_response' => $response['result'],
                    'forwarded_url_timestamp' => $urlTime,
                    'last_server_response_speed' => $responseSpeed,
                    'http_status_code' => $response['http_status'],
                ]);
        }

        // Log success
        $this->logToDb($forward->id, "Successful forwarding event. Retries left = " . ($forward->retries_left - 1), 'url_forward');

        if ($forward->itagg_forwardinglog_id) {
            $this->logToDb($forward->itagg_forwardinglog_id, "Successful URL forwarding event ($urlTime).", 'itagg_forwardinglog');
        }
    }

    /**
     * Handle failed forward
     */
    protected function handleFailure(object $forward, array $response, int $responseSpeed, int $waitTime, int $curlTimeout, string $queryString): void
    {
        $nowTime = now()->format('Y-m-d H:i:s');
        $urlTime = now()->format('YmdHis');
        $newDosendTime = now()->addSeconds($waitTime)->format('Y-m-d H:i:s');

        $this->error("  FAILED - Error: {$response['errno']}: {$response['error']} (HTTP {$response['http_status']})");

        // Determine new status
        $newStatus = $forward->retries_left > 1 ? 'new' : 'forced_suspension';

        if ($this->dryRun) {
            $this->line("  Would set status to: {$newStatus}, retry at: {$newDosendTime}");
            return;
        }

        // Get user info for email
        $userInfo = $this->getUserInfo($forward->itagg_incominglog_id);

        // Send timeout email to internal team
        if ($response['errno'] == CURLE_OPERATION_TIMEDOUT) {
            $this->sendTimeoutAlert($forward, $response, $userInfo, $curlTimeout, $queryString);
        }

        // Send client notification for specific clients
        if ($userInfo && in_array($userInfo->bigid, config('urlforward.notify_clients', []))) {
            $this->sendClientAlert($forward, $response, $userInfo, $curlTimeout, $queryString);
        }

        // Update url_forward
        DB::table('url_forward')
            ->where('id', $forward->id)
            ->where('url', $forward->url)
            ->update([
                'status' => $newStatus,
                'server_response' => $response['result'],
                'last_connection_time' => $nowTime,
                'retries_left' => DB::raw('retries_left - 1'),
                'dosendtime' => $newDosendTime,
                'last_server_response_speed' => $responseSpeed,
                'http_status_code' => $response['http_status'],
            ]);

        // Log failure
        $logMessage = "Failed forwarding event: Error code: {$response['errno']} ({$response['error']}) http status = {$response['http_status']}. ";
        $logMessage .= "Retries left = " . ($forward->retries_left - 1) . ", Response timeout limit was {$curlTimeout} seconds. ";
        $logMessage .= "Setting new status = {$newStatus}";

        $this->logToDb($forward->id, $logMessage, 'url_forward');

        if ($forward->itagg_forwardinglog_id) {
            $forwardLogMessage = "FAILED URL forwarding event ($urlTime). Error code: {$response['errno']} ({$response['error']}) ";
            $forwardLogMessage .= "http status = {$response['http_status']}. Retries left: " . ($forward->retries_left - 1) . ". ";
            $forwardLogMessage .= "Response timeout limit was {$curlTimeout} seconds.";

            $this->logToDb($forward->itagg_forwardinglog_id, $forwardLogMessage, 'itagg_forwardinglog');
        }

        Log::warning('URL Forward failed', [
            'daemon' => $this->daemonName,
            'forward_id' => $forward->id,
            'url' => $forward->url,
            'error' => $response['error'],
            'http_status' => $response['http_status'],
            'retries_left' => $forward->retries_left - 1,
        ]);
    }

    /**
     * Get user info for notifications
     */
    protected function getUserInfo(int $incomingLogId): ?object
    {
        return DB::table('users')
            ->join('itagg_incominglog', 'users.bigid', '=', 'itagg_incominglog.user_bigid')
            ->where('itagg_incominglog.id', $incomingLogId)
            ->select(['users.contactname', 'users.contactemail', 'users.bigid', 'users.busname'])
            ->first();
    }

    /**
     * Send timeout alert to internal team
     */
    protected function sendTimeoutAlert(object $forward, array $response, ?object $userInfo, int $curlTimeout, string $queryString): void
    {
        $recipients = config('urlforward.timeout_alert_recipients', ['anand@nedholdings.com']);
        $contactName = $userInfo ? urldecode($userInfo->contactname) : 'Unknown';
        $contactEmail = $userInfo->contactemail ?? 'Unknown';
        $busName = $userInfo ? urldecode($userInfo->busname) : 'Unknown';

        $subject = "URL Forward Timeout - Instance ID: {$forward->itagg_instance_id}";

        $content = "<b>Incoming SMS forwarding TIMEOUT to:</b> {$forward->url}<br><br>";
        $content .= "<b>Querystring:</b> {$queryString}<br><br>";
        $content .= "<b>Response:</b> {$response['errno']}, {$response['error']}<br><br>";
        $content .= "<b>Result:</b> {$response['result']}<br><br>";
        $content .= "<b>Retries left:</b> " . ($forward->retries_left - 1) . "<br><br>";
        $content .= "<b>Daemon name:</b> {$this->daemonName}, timeout value was {$curlTimeout} seconds<br><br>";
        $content .= "<b>Contact:</b> {$contactEmail}, {$contactName}, {$busName}<br><br>";
        $content .= "<b>itagg_instance.id:</b> {$forward->itagg_instance_id}<br><br>";
        $content .= "<b>itagg_incominglog.id:</b> {$forward->itagg_incominglog_id}<br><br>";
        $content .= "<b>url_forward.id:</b> {$forward->id}<br>";

        $this->sendEmail($subject, $content, $recipients);
    }

    /**
     * Send client alert
     */
    protected function sendClientAlert(object $forward, array $response, object $userInfo, int $curlTimeout, string $queryString): void
    {
        if (empty($userInfo->contactemail)) {
            return;
        }

        $contactName = urldecode($userInfo->contactname);
        $busName = urldecode($userInfo->busname);

        $subject = "SMS Expert incoming SMS forwarding failed";

        $content = "<b>Incoming SMS forwarding failed to:</b> {$forward->url}<br><br>";
        $content .= "<b>Querystring:</b> {$queryString}<br><br>";
        $content .= "<b>Response:</b> {$response['errno']}, {$response['error']}<br><br>";
        $content .= "<b>Result:</b> {$response['result']}<br><br>";
        $content .= "<b>Retries left:</b> " . ($forward->retries_left - 1) . "<br><br>";
        $content .= "<b>Daemon name:</b> {$this->daemonName}, timeout value was {$curlTimeout} seconds<br><br>";
        $content .= "<b>Contact:</b> {$userInfo->contactemail}, {$contactName}, {$busName}<br><br>";
        $content .= "<b>itagg_instance.id:</b> {$forward->itagg_instance_id}<br><br>";
        $content .= "<b>itagg_incominglog.id:</b> {$forward->itagg_incominglog_id}<br><br>";
        $content .= "<b>url_forward.id:</b> {$forward->id}<br>";

        $this->sendEmail($subject, $content, [$userInfo->contactemail]);
    }

    /**
     * Send email
     */
    protected function sendEmail(string $subject, string $content, array $recipients): void
    {
        if ($this->debug) {
            $recipients = [config('reports.debug_recipient')];
        }

        $alertData = [
            'subject' => $subject,
            'title' => $subject,
            'content' => $content,
            'type' => strpos(strtolower($subject), 'error') !== false ? 'error' : 'warning',
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        try {
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\UrlForwardAlertMail',
                    trim($recipient),
                    ['alert_data' => $alertData],
                    [],
                    10
                );
            }
        } catch (\Exception $e) {
            Log::error('URL Forward email failed', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log to database comment log
     */
    protected function logToDb(int $recordId, string $message, string $tableName): void
    {
        try {
            DB::table('db_comment_log')->insert([
                'record_id' => $recordId,
                'table_name' => $tableName,
                'comment' => $message,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail if table doesn't exist
            Log::debug('Could not log to db_comment_log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get outgoing IP for white label clients
     */
    protected function getOutgoingIp(string $url): ?string
    {
        if (empty($url)) {
            return $this->defaultWhitelabelIp ?? null;
        }

        // Check if this URL belongs to a white label client
        $userRef = DB::table('useroption')
            ->join('itagg_instance', 'useroption.userref', '=', 'itagg_instance.users_bigid')
            ->where('useroption.apitype', 'w')
            ->where('itagg_instance.forwarding_url', $url)
            ->value('useroption.userref');

        if ($userRef && isset($this->whitelabelClientIps[$userRef])) {
            $this->line("  Using white label IP: {$this->whitelabelClientIps[$userRef]}");
            return $this->whitelabelClientIps[$userRef];
        }

        return null;
    }

    /**
     * Load white label configuration
     */
    protected function loadWhitelabelConfig(): void
    {
        $this->defaultWhitelabelIp = config('urlforward.default_whitelabel_ip', '');
        $this->whitelabelClientIps = config('urlforward.whitelabel_client_ips', []);
    }

    /**
     * Acquire process lock
     */
    protected function acquireLock(): bool
    {
        $pid = getmypid();

        if (file_exists($this->touchFile)) {
            $lines = file($this->touchFile);
            if (!empty($lines[0])) {
                $filePid = trim($lines[0]);

                // Check if process is still running
                if ($filePid != $pid && posix_kill((int)$filePid, 0)) {
                    return false; // Process still running
                }
            }
        }

        // Create/update touch file with our PID
        $this->updateTouchFile();
        return true;
    }

    /**
     * Release process lock
     */
    protected function releaseLock(): void
    {
        if (file_exists($this->touchFile)) {
            @unlink($this->touchFile);
        }
    }

    /**
     * Update touch file
     */
    protected function updateTouchFile(): void
    {
        $pid = getmypid();
        $now = time();

        $dir = dirname($this->touchFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->touchFile, "{$pid}\n{$now}");
        @chmod($this->touchFile, 0777);
    }
}
