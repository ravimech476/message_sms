<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Mail\DeliveryReceiptFailureMail;
use App\Services\Queue\EmailQueueService;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;

class DeliveryReceiptPushCommand extends Command
{
    use LogsCronExecution;

    protected $signature = 'delivery-receipt:push {daemonname=default}';
    protected $description = 'Process delivery receipt push notifications';

    private $daemonName;
    private $touchFile;
    private $curlTimeout = 10;
    private $batchSize = 1000;

    public function handle()
    {
        return $this->executeWithLogging('delivery-receipt:push', function () {
            $startTime = now();
            
            // Create dated log file path
            $logDate = $startTime->format('Y-m-d');
            $logDir = storage_path("logs/{$logDate}");
            $commandName = 'delivery-receipt:push';
            
            // Create directory if it doesn't exist
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Sanitize command name for filename
            $logFileName = str_replace([':', ' '], ['_', '_'], $commandName) . '.log';
            $logFilePath = $logDir . '/' . $logFileName;
            
            $this->daemonName = $this->argument('daemonname');
            
            $this->writeToLogFile($logFilePath, "=== Starting Delivery Receipt Push: {$this->daemonName} ===");
            
            $processed = $this->processDeliveryReceipts($logFilePath);
            
            $this->writeToLogFile($logFilePath, "=== Process Completed | Total processed: {$processed} ===");
            
            return "Success: Processed {$processed} delivery receipts";
        });
    }

    /**
     * Fetch pending receipts and process them
     */
    private function processDeliveryReceipts($logFilePath)
    {
        $receipts = $this->fetchPendingReceipts($logFilePath);
        
        if ($receipts->isEmpty()) {
            $this->writeToLogFile($logFilePath, "No pending delivery receipts to process.");
            return 0;
        }

        $count = $receipts->count();
        $this->writeToLogFile($logFilePath, "Processing {$count} receipts...");

        foreach ($receipts as $receipt) {
            try {
                $this->processReceipt($receipt, $logFilePath);
                usleep(50000); // 50ms delay between requests
            } catch (\Exception $e) {
                $this->writeToLogFile($logFilePath, "✗ Exception processing receipt ID {$receipt->id}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Fetch pending receipts from database
     * Only processes receipts for users migrated to new system (migration_flag = 'new')
     */
    private function fetchPendingReceipts($logFilePath)
    {
        $query = DB::table('delivery_receipt_push_log as d')
            ->join('users as u', 'd.users_bigid', '=', 'u.bigid')
            ->select('d.*')
            ->where('d.status', 'new')
            ->where('d.retries_left', '>', 0)
            ->where('d.dosendtime', '<=', Carbon::now())
            ->where('u.migration_flag', 'new') // Only process users migrated to new system
            ->limit($this->batchSize);

        $sql = vsprintf(
            str_replace('?', '%s', $query->toSql()),
            collect($query->getBindings())->map(fn($b) => is_numeric($b) ? $b : "'{$b}'")->toArray()
        );

        $this->writeToLogFile($logFilePath, "Executing SQL: {$sql}");

        return $query->get();
    }

    /**
     * Process individual receipt
     */
    private function processReceipt($receipt, $logFilePath)
    {
        // Mark as processing
        DB::table('delivery_receipt_push_log')
            ->where('id', $receipt->id)
            ->update(['status' => 'doing']);

        $this->writeToLogFile($logFilePath, "→ Processing ID {$receipt->id} | URL: {$receipt->url} | API Type: {$receipt->apitype}");

        $startTime = microtime(true);
        
        // Prepare the data based on API type
        $postData = $this->preparePostData($receipt, $logFilePath);
        
        // Send the request
        $response = $this->sendCurlRequest($receipt->url, $postData, $receipt, $logFilePath);
        
        $responseTime = round(microtime(true) - $startTime, 3);

        if ($response['success']) {
            $this->handleSuccessfulDelivery($receipt, $response, $responseTime, $logFilePath);
        } else {
            $this->handleFailedDelivery($receipt, $response, $responseTime, $logFilePath);
        }
    }

    /**
     * Prepare POST data based on API type
     */
    private function preparePostData($receipt, $logFilePath): array
    {
        if ($receipt->apitype === 'w') {
            // White-label API format
            return $this->prepareWhiteLabelData($receipt);
        } else {
            // XML API format
            return ['xml' => $receipt->xml];
        }
    }

    /**
     * Prepare white-label API data format
     */
    private function prepareWhiteLabelData($receipt): array
    {
        $xmlData = $this->parseDeliveryReceiptXml($receipt->xml);
        
        $dlrstatus = strtolower($xmlData['status'] ?? 'unknown');
        $dlrstatus = str_replace(['non delivered', 'lost notification'], ['not delivered', 'unknown'], $dlrstatus);
        
        $validStatuses = ['delivered', 'not delivered', 'unknown', 'buffered'];
        if (!in_array($dlrstatus, $validStatuses)) {
            $dlrstatus = 'unknown';
        }

        return [
            'msisdn' => $xmlData['msisdn'] ?? '',
            'subid' => $xmlData['submission_ref'] ?? '',
            'dlrstatus' => $dlrstatus,
            'dlrcode' => $xmlData['reason'] ?? '0'
        ];
    }

    /**
     * Parse XML delivery receipt
     */
    private function parseDeliveryReceiptXml($xml): array
    {
        try {
            // Remove excessive whitespace and newlines
            $xml = preg_replace('/>\s+</', '><', trim($xml));
            
            $xmlObj = simplexml_load_string($xml);
            if ($xmlObj === false) {
                return [];
            }

            return [
                'msisdn' => (string) $xmlObj->msisdn,
                'submission_ref' => (string) $xmlObj->submission_ref,
                'status' => (string) $xmlObj->status,
                'reason' => (string) $xmlObj->reason,
                'gmt_timestamp' => (string) ($xmlObj->gmt_timestamp ?? ''),
                'retry' => (string) ($xmlObj->retry ?? '0'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Send CURL request to delivery receipt URL
     */
    private function sendCurlRequest($url, $postData, $receipt, $logFilePath): array
    {
        $ch = curl_init();
        
        // Determine if we're sending XML or form data
        $isXmlRequest = isset($postData['xml']);
        
        if ($isXmlRequest) {
            // Send as raw XML
            $xmlData = $postData['xml'];
            
            // Clean and format XML
            $xmlData = preg_replace('/>\s+</', '><', trim($xmlData));
            
            $this->writeToLogFile($logFilePath, "Sending XML data (length: " . strlen($xmlData) . " bytes)");
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $xmlData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->curlTimeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/xml; charset=ISO-8859-1',
                    'Content-Length: ' . strlen($xmlData),
                    'Accept: */*',
                ],
            ]);
        } else {
            // Send as form data (for white-label API)
            $formData = http_build_query($postData);
            $this->writeToLogFile($logFilePath, "Sending form data: {$formData}");
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $formData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->curlTimeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: */*',
                ],
            ]);
        }

        // Optional: Bind to specific interface IP (disabled by default to prevent bind errors)
        // Uncomment only if you have valid IPs configured
        /*
        if ($receipt->apitype === 'w') {
            $ip = $this->getWhiteLabelInterfaceIp($receipt->users_bigid);
            if ($ip && $this->isValidIpAddress($ip)) {
                $this->writeToLogFile($logFilePath, "Binding to interface IP: {$ip}");
                curl_setopt($ch, CURLOPT_INTERFACE, $ip);
            }
        }
        */

        // Execute request
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        curl_close($ch);

        // Log response details
        if ($errno != CURLE_OK) {
            $this->writeToLogFile($logFilePath, "CURL Error [{$errno}]: {$error}");
        } else {
            $responsePreview = substr(str_replace(["\n", "\r"], '', $result), 0, 200);
            $this->writeToLogFile($logFilePath, "HTTP {$httpCode} | Time: {$totalTime}s | Response: {$responsePreview}");
        }

        return [
            'success' => ($errno == CURLE_OK && $httpCode >= 200 && $httpCode < 300),
            'result' => $result,
            'errno' => $errno,
            'error' => $error,
            'http_code' => $httpCode,
        ];
    }

    /**
     * Validate if IP address exists on the server
     */
    private function isValidIpAddress($ip): bool
    {
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        // Check if IP exists on server (Linux only)
        if (PHP_OS_FAMILY === 'Linux') {
            $command = "ip addr show | grep -q " . escapeshellarg($ip);
            exec($command, $output, $returnCode);
            return $returnCode === 0;
        }
        
        return false;
    }

    /**
     * Get white-label interface IP for user
     */
    private function getWhiteLabelInterfaceIp($userId): ?string
    {
        // Disabled by default to prevent bind errors
        // Uncomment and configure if you need this feature
        return null;
        
        /*
        $map = config('delivery_receipt.white_label_ips', []);
        return $map[$userId] ?? config('delivery_receipt.white_label_default_ip');
        */
    }

    /**
     * Handle successful delivery
     */
    private function handleSuccessfulDelivery($receipt, $response, $responseTime, $logFilePath)
    {
        DB::table('delivery_receipt_push_log')
            ->where('id', $receipt->id)
            ->update([
                'status' => 'processed',
                'server_response' => substr($response['result'] ?? '', 0, 1000),
                'last_connection_time' => now(),
                'last_server_response_speed' => $responseTime
            ]);

        $this->writeToLogFile($logFilePath, "✓ Success ID {$receipt->id} | {$receipt->url} | {$responseTime}s");
    }

    /**
     * Handle failed delivery
     */
    private function handleFailedDelivery($receipt, $response, $responseTime, $logFilePath)
    {
        $retriesLeft = $receipt->retries_left - 1;
        $newStatus = $retriesLeft > 0 ? 'new' : 'fail';
        $nextAttempt = now()->addMinutes($receipt->wait_minutes ?? 5);

        $errorMessage = $response['error'] ?: "HTTP {$response['http_code']}";
        $serverResponse = $response['result'] ?: $errorMessage;

        DB::table('delivery_receipt_push_log')
            ->where('id', $receipt->id)
            ->update([
                'status' => $newStatus,
                'server_response' => substr($serverResponse, 0, 1000),
                'last_connection_time' => now(),
                'retries_left' => $retriesLeft,
                'dosendtime' => $nextAttempt,
                'last_server_response_speed' => $responseTime
            ]);

        $this->writeToLogFile($logFilePath, "✗ Failed ID {$receipt->id} | {$errorMessage} | Retries left: {$retriesLeft} | Next attempt: {$nextAttempt}");

        // Send notification only if no retries left or critical error
        if ($retriesLeft <= 0) {
            $this->sendFailureNotification($receipt, $response, $retriesLeft, $logFilePath);
        }
    }

    /**
     * Send failure notification email
     */
    private function sendFailureNotification($receipt, $response, $retriesLeft, $logFilePath)
    {
        if (!$this->shouldSendNotification($receipt->users_bigid)) {
            return;
        }

        $recipient = $this->getNotificationRecipient($receipt->users_bigid);
        if (!$recipient) {
            $this->writeToLogFile($logFilePath, "⚠ No recipient found for user ID {$receipt->users_bigid}");
            return;
        }

        try {
            // Send failure notification via RabbitMQ queue
            $emailQueueService = new EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\DeliveryReceiptFailureMail',
                $recipient['email'],
                [
                    'url' => $receipt->url,
                    'xml' => $receipt->xml,
                    'error' => $response['error'],
                    'errno' => $response['errno'],
                    'result' => $response['result'],
                    'http_code' => $response['http_code'] ?? 0,
                    'retries_left' => $retriesLeft,
                    'daemon_name' => $this->daemonName,
                    'receipt_id' => $receipt->id,
                    'contact_email' => $recipient['email'],
                    'contact_name' => $recipient['name']
                ],
                config('delivery_receipt.cc_emails', [])
            );

            $this->writeToLogFile($logFilePath, "📧 Notification queued to {$recipient['email']} for ID {$receipt->id}");
        } catch (\Exception $e) {
            $this->writeToLogFile($logFilePath, "⚠ Failed to queue email: {$e->getMessage()}");
        }
    }

    /**
     * Check if notification should be sent for this user
     */
    private function shouldSendNotification($userId): bool
    {
        $excluded = config('delivery_receipt.excluded_notification_users', []);
        return !in_array($userId, $excluded);
    }

    /**
     * Get notification recipient for user
     */
    private function getNotificationRecipient($userId): ?array
    {
        // Check for special recipient
        $special = config('delivery_receipt.special_recipients', []);
        if (isset($special[$userId])) {
            return $special[$userId];
        }

        // Get from database
        $user = DB::table('users')
            ->where('bigid', $userId)
            ->first(['contactname', 'contactemail']);

        if ($user && $user->contactemail) {
            return [
                'email' => $user->contactemail,
                'name' => $user->contactname ?? 'User'
            ];
        }

        // Return default recipient
        return config('delivery_receipt.default_recipient');
    }
}