<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\Queue\SmsQueueService;
use App\Services\Queue\RabbitMQService;
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use App\Services\XmlGatewayCustomerService;
use Carbon\Carbon;
use Exception;
use Webklex\IMAP\Facades\Client;

class XmlToSmsGateway extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:xml-gateway 
                            {--file= : Path to XML file to process}
                            {--stdin : Read XML from stdin (for piped email)}
                            {--xml= : Direct XML string input}
                            {--inbox : Read emails from IMAP inbox}
                            {--folder=INBOX : IMAP folder to read from}
                            {--limit=50 : Maximum emails to process per run}
                            {--delete : Delete emails after processing}
                            {--daemon : Run continuously as daemon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process XML to SMS gateway requests - converts XML email/input to SMS via SMPP';

    /**
     * @var SmsQueueService
     */
    protected $smsQueueService;

    /**
     * @var BulkThroughputService
     */
    protected $bulkThroughputService;

    /**
     * @var WalletValidationService
     */
    protected $walletValidationService;

    /**
     * @var XmlGatewayCustomerService
     */
    protected $customerService;

    /**
     * @var string
     */
    protected $fromEmail = '';

    /**
     * @var string
     */
    protected $logFile;

    /**
     * @var bool - Whether to skip confirmation email for this request
     */
    protected $skipConfirmation = false;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();
        $this->customerService = new XmlGatewayCustomerService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Initialize log file with date-wise folder structure
        try {
            $dateFolder = storage_path('logs/' . date('Y-m-d'));
            if (!is_dir($dateFolder)) {
                @mkdir($dateFolder, 0755, true);
            }
            $this->logFile = $dateFolder . '/xml-to-sms-gateway.log';
        } catch (Exception $e) {
            // Fallback to main logs folder
            $this->logFile = storage_path('logs/xml-to-sms-gateway.log');
        }

        $this->writeLog("========== XML to SMS Gateway Started: " . date('Y-m-d H:i:s') . " ==========");

        try {
            // Initialize SMPP Queue Service
            $this->smsQueueService = new SmsQueueService();
        } catch (Exception $e) {
            $this->logError('Failed to initialize SMPP Queue Service: ' . $e->getMessage());
            return 1;
        }

        // Check if reading from inbox
        if ($this->option('inbox')) {
            return $this->processInbox();
        }

        // Get XML input from various sources (file, stdin, xml option)
        $rawInput = $this->getInputData();
        
        if (empty($rawInput)) {
            $this->logError('No input data received');
            return 1;
        }

        return $this->processEmailContent($rawInput);
    }

    /**
     * Process emails from IMAP inbox
     */
    protected function processInbox(): int
    {
        $this->writeLog("Reading emails from IMAP inbox...");

        $isDaemon = $this->option('daemon');
        $limit = (int) $this->option('limit');
        $folder = $this->option('folder');
        $deleteAfterProcess = $this->option('delete');

        do {
            try {
                // Connect to IMAP
                $client = $this->connectToImap();
                
                if (!$client) {
                    $this->logError('Failed to connect to IMAP server');
                    if ($isDaemon) {
                        sleep(60); // Wait 1 minute before retry
                        continue;
                    }
                    return 1;
                }

                // Get folder
                $imapFolder = $client->getFolder($folder);
                
                if (!$imapFolder) {
                    $this->logError("Folder '{$folder}' not found");
                    $client->disconnect();
                    return 1;
                }

                // Get unread/unseen messages
                $messages = $imapFolder->messages()
                    ->unseen()
                    ->limit($limit)
                    ->get();

                $messageCount = $messages->count();
                $this->writeLog("Found {$messageCount} unread message(s) in {$folder}");

                if ($messageCount === 0) {
                    $client->disconnect();
                    
                    if ($isDaemon) {
                        $this->writeLog("No messages to process, waiting 30 seconds...");
                        sleep(30);
                        continue;
                    }
                    return 0;
                }

                $processed = 0;
                $failed = 0;

                foreach ($messages as $message) {
                    try {
                        $this->writeLog("Processing email: " . $message->getSubject());
                        
                        // Get email content
                        $emailContent = $this->extractEmailContent($message);
                        
                        if (!empty($emailContent)) {
                            $result = $this->processEmailContent($emailContent);
                            
                            if ($result === 0) {
                                $processed++;
                                
                                // Mark as read
                                $message->setFlag('Seen');
                                
                                // Delete if option set
                                if ($deleteAfterProcess) {
                                    $message->delete();
                                } else {
                                    // Move to processed folder
                                    $this->moveToProcessedFolder($client, $message);
                                }
                            } else {
                                $failed++;
                                // Move to failed folder
                                $this->moveToFailedFolder($client, $message);
                            }
                        } else {
                            $this->writeLog("Empty email content, skipping");
                            $message->setFlag('Seen');
                            $failed++;
                        }
                        
                    } catch (Exception $e) {
                        $this->writeLog("Error processing email: " . $e->getMessage());
                        $failed++;
                        $message->setFlag('Seen');
                    }
                }

                $this->writeLog("Batch complete - Processed: {$processed}, Failed: {$failed}");

                $client->disconnect();

                if ($isDaemon) {
                    $this->writeLog("Daemon mode: waiting 10 seconds before next check...");
                    sleep(10);
                }

            } catch (Exception $e) {
                $this->logError('IMAP Error: ' . $e->getMessage());
                
                if ($isDaemon) {
                    sleep(60); // Wait 1 minute before retry on error
                    continue;
                }
                return 1;
            }

        } while ($isDaemon);

        return 0;
    }

    /**
     * Connect to IMAP server
     */
    protected function connectToImap()
    {
        try {
            // Try using Laravel IMAP package if available
            if (class_exists('Webklex\IMAP\Facades\Client')) {
                $client = Client::account('default');
                $client->connect();
                return $client;
            }

            // Fallback to native PHP IMAP
            return $this->connectNativeImap();

        } catch (Exception $e) {
            $this->writeLog("IMAP connection error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Connect using native PHP IMAP extension
     */
    protected function connectNativeImap()
    {
        $host = env('IMAP_HOST', 'imap.gmail.com');
        $port = env('IMAP_PORT', 993);
        $encryption = env('IMAP_ENCRYPTION', 'ssl');
        $username = env('IMAP_USERNAME');
        $password = env('IMAP_PASSWORD');
        $validateCert = env('IMAP_VALIDATE_CERT', true);

        if (empty($username) || empty($password)) {
            $this->writeLog("IMAP credentials not configured");
            return null;
        }

        $mailbox = "{" . $host . ":" . $port . "/imap/" . $encryption;
        if (!$validateCert) {
            $mailbox .= "/novalidate-cert";
        }
        $mailbox .= "}INBOX";

        $this->writeLog("Connecting to IMAP: {$host}:{$port}");

        $connection = @imap_open($mailbox, $username, $password);

        if (!$connection) {
            $error = imap_last_error();
            $this->writeLog("IMAP connection failed: " . $error);
            return null;
        }

        $this->writeLog("IMAP connected successfully");

        // Return a wrapper object for native IMAP
        return new NativeImapClient($connection, $mailbox);
    }

    /**
     * Extract email content from message object
     */
    protected function extractEmailContent($message): string
    {
        $content = '';

        try {
            // For Laravel IMAP package
            if (method_exists($message, 'getTextBody')) {
                $content = $message->getTextBody();
                
                if (empty($content)) {
                    $content = $message->getHTMLBody();
                }

                // Get sender email
                $from = $message->getFrom();
                if ($from && count($from) > 0) {
                    $this->fromEmail = $from[0]->mail ?? '';
                }
            }
            // For native IMAP
            elseif (is_array($message)) {
                $content = $message['body'] ?? '';
                $this->fromEmail = $message['from'] ?? '';
            }

            // Build full email format with headers
            $fullEmail = "From: {$this->fromEmail}\n";
            
            if (method_exists($message, 'getSubject')) {
                $fullEmail .= "Subject: " . $message->getSubject() . "\n";
            }
            
            $fullEmail .= "\n" . $content;

            return $fullEmail;

        } catch (Exception $e) {
            $this->writeLog("Error extracting email content: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Move message to processed folder
     */
    protected function moveToProcessedFolder($client, $message): void
    {
        try {
            $processedFolder = env('IMAP_PROCESSED_FOLDER', 'Processed');
            
            // Try to create folder if it doesn't exist
            if (method_exists($client, 'getFolder')) {
                $folder = $client->getFolder($processedFolder);
                if (!$folder) {
                    $client->createFolder($processedFolder);
                }
                $message->move($processedFolder);
            }
        } catch (Exception $e) {
            $this->writeLog("Could not move to processed folder: " . $e->getMessage());
        }
    }

    /**
     * Move message to failed folder
     */
    protected function moveToFailedFolder($client, $message): void
    {
        try {
            $failedFolder = env('IMAP_FAILED_FOLDER', 'Failed');
            
            if (method_exists($client, 'getFolder')) {
                $folder = $client->getFolder($failedFolder);
                if (!$folder) {
                    $client->createFolder($failedFolder);
                }
                $message->move($failedFolder);
            }
        } catch (Exception $e) {
            $this->writeLog("Could not move to failed folder: " . $e->getMessage());
        }
    }

    /**
     * Process single email content
     */
    protected function processEmailContent(string $rawInput): int
    {
        $this->writeLog("Raw input received, length: " . strlen($rawInput));

        // Parse email headers if present (for piped email input)
        $parsedData = $this->parseEmailInput($rawInput);
        
        if (empty($parsedData['body'])) {
            $this->writeLog("No body content found, exiting");
            return 0;
        }

        // Extract and parse XML
        $xmlData = $this->parseXmlBody($parsedData['body']);
        
        if (!$xmlData) {
            $this->logError('Failed to parse XML body');
            return 1;
        }

        // Validate user credentials
        $user = $this->validateUser($xmlData['username'], $xmlData['password']);
        
        if (!$user) {
            $this->logError('Invalid username/password for user: ' . $xmlData['username']);
            return 1;
        }

        $this->writeLog("User validated: {$xmlData['username']} (bigid: {$user->bigid})");

        // Check if confirmation emails should be skipped for this user/email
        $this->skipConfirmation = $this->shouldSkipConfirmation($this->fromEmail, $xmlData['username']);
        if ($this->skipConfirmation) {
            $this->writeLog("Confirmation emails will be skipped for user: {$xmlData['username']}");
        }

        // Get wallet balance
        $walletBalance = $this->getWalletBalance($user->bigid);
        $this->writeLog("Current wallet balance: £" . number_format($walletBalance, 2));

        // Parse phone numbers
        $phoneNumbers = $this->parsePhoneNumbers($xmlData['numbers']);
        $messageCount = count($phoneNumbers);

        if ($messageCount === 0) {
            $this->logError('No valid phone numbers found');
            return 1;
        }

        $this->writeLog("Processing {$messageCount} phone number(s): " . implode(', ', $phoneNumbers));

        // Check blacklisted numbers
        $blacklistCheck = $this->checkBlacklist($user->bigid, $phoneNumbers);
        $phoneNumbers = $blacklistCheck['allowed'];
        
        if (count($phoneNumbers) === 0) {
            $this->logError('All numbers are blacklisted: ' . implode(', ', $blacklistCheck['blocked']));
            return 1;
        }

        if (count($blacklistCheck['blocked']) > 0) {
            $this->writeLog("Blocked numbers (blacklisted): " . implode(', ', $blacklistCheck['blocked']));
        }

        // Check bulk throughput limit
        $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($user->bigid);
        
        if (!$throughputCheck['allowed']) {
            $this->logError("Throughput limit exceeded for user {$user->bigid}. Limit: " . ($throughputCheck['limit'] ?? 0));
            return 1;
        }

        // Determine route and calculate cost
        $route = $this->determineRoute($xmlData, $user);
        $costPerMessage = $this->getMessageCost($user->bigid, $route, $xmlData['senderid']);
        $smsParts = $this->calculateSmsParts($xmlData['message']);
        $totalCost = $costPerMessage * $messageCount * $smsParts;

        $this->writeLog("Route: {$route}, Cost per message: £{$costPerMessage}, SMS Parts: {$smsParts}, Total cost: £{$totalCost}");

        // Check wallet balance
        if ($walletBalance < $totalCost) {
            $shortage = $totalCost - $walletBalance;
            $this->logError("Insufficient wallet balance. Required: £{$totalCost}, Available: £{$walletBalance}, Shortage: £{$shortage}");
            return 1;
        }

        // Validate sender ID
        if (!$this->validateSenderId($xmlData['senderid'])) {
            $this->logError("Invalid sender ID: {$xmlData['senderid']} (max 11 characters for alphanumeric)");
            return 1;
        }

        // Check shortcode/sender ID access restrictions (OLD SYSTEM compatibility)
        if (!$this->validateShortcodeAccess($xmlData['senderid'], $user->bigid)) {
            $this->logError("You are not allowed to use this sender ID/shortcode: {$xmlData['senderid']}");
            return 1;
        }

        // Process and send SMS
        $results = $this->processSmsMessages(
            $user,
            $phoneNumbers,
            $xmlData['message'],
            $xmlData['senderid'],
            $route,
            $costPerMessage
        );

        // Log results
        $this->writeLog("SMS Processing Complete:");
        $this->writeLog("  - Success: {$results['success']}");
        $this->writeLog("  - Failed: {$results['failed']}");
        $this->writeLog("========== XML to SMS Gateway Completed: " . date('Y-m-d H:i:s') . " ==========\n");

        if ($results['success'] > 0) {
            // Send confirmation email (unless skipped for this user)
            $this->sendConfirmationEmail($results['success'], $this->fromEmail);

            $this->info("Successfully queued {$results['success']} SMS message(s)");
            return 0;
        }

        $this->error("Failed to send SMS messages");
        return 1;
    }

    /**
     * Get input data from various sources
     */
    protected function getInputData(): string
    {
        // Option 1: From file
        if ($filePath = $this->option('file')) {
            if (file_exists($filePath)) {
                $this->writeLog("Reading from file: {$filePath}");
                return file_get_contents($filePath);
            }
            $this->writeLog("File not found: {$filePath}");
            return '';
        }

        // Option 2: Direct XML string
        if ($xmlString = $this->option('xml')) {
            $this->writeLog("Reading from --xml option");
            return $xmlString;
        }

        // Option 3: From stdin (for piped email)
        if ($this->option('stdin')) {
            $this->writeLog("Reading from stdin");
            $input = '';
            $stdin = fopen('php://stdin', 'r');
            if ($stdin) {
                stream_set_blocking($stdin, false);
                while (($line = fgets($stdin)) !== false) {
                    $input .= $line;
                }
                fclose($stdin);
            }
            return $input;
        }

        return '';
    }

    /**
     * Parse email input to extract headers and body
     */
    protected function parseEmailInput(string $rawInput): array
    {
        $result = [
            'from' => '',
            'to' => '',
            'subject' => '',
            'body' => ''
        ];

        $lines = explode("\n", $rawInput);
        $inBody = false;
        $body = '';

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            // Parse From header
            if (!$result['from'] && preg_match('/^From:\s*(.+)$/i', $line, $matches)) {
                if (preg_match('/([A-Za-z0-9_\-\.]+@[A-Za-z0-9_\-\.]+)/', $matches[1], $emailMatch)) {
                    $result['from'] = $emailMatch[1];
                    $this->fromEmail = $result['from'];
                }
                continue;
            }

            // Parse To header
            if (!$result['to'] && preg_match('/^To:\s*(.+)$/i', $line, $matches)) {
                $result['to'] = trim($matches[1]);
                continue;
            }

            // Parse Subject header
            if (!$result['subject'] && preg_match('/^Subject:\s*(.+)$/i', $line, $matches)) {
                $result['subject'] = trim($matches[1]);
                continue;
            }

            // Detect start of body (empty line after headers)
            if (!$inBody && trim($line) === '') {
                $inBody = true;
                continue;
            }

            // Collect body content
            if ($inBody) {
                $body .= $line . "\n";
                
                // Check for end of SOAP envelope
                if (preg_match('/<\/SOAP:Envelope>/i', $line)) {
                    break;
                }
            }
        }

        // If no email headers found, treat entire input as body (direct XML)
        if (empty($result['from']) && empty($result['to'])) {
            $result['body'] = $rawInput;
        } else {
            $result['body'] = $body;
        }

        $this->writeLog("Parsed email - From: {$result['from']}, To: {$result['to']}");

        return $result;
    }

    /**
     * Parse XML body to extract SMS parameters
     */
    protected function parseXmlBody(string $body): ?array
    {
        // Clean up XML - strip whitespace between tags
        $body = preg_replace("#>\s+<#", "><", $body);

        // Extract preservelinebreaks setting
        $preserveLinebreaks = 'y';
        if (preg_match('#<preservelinebreaks>(.*?)</preservelinebreaks>#i', $body, $matches)) {
            $preserveLinebreaks = $matches[1];
            $body = preg_replace('#<preservelinebreaks>.*?</preservelinebreaks>#i', '', $body);
        }

        // Extract text content before XML parsing (to handle special characters)
        $message = '';
        if (preg_match('#<text>(.*?)</text>#is', $body, $matches)) {
            $message = trim($matches[1]);
            $body = preg_replace('#<text>.*?</text>#is', '', $body);
            
            if ($preserveLinebreaks === 'n') {
                $message = preg_replace("#[\r\n]#s", "", $message);
            }
        }

        if (empty($message)) {
            $this->writeLog("No <text> element found in XML");
            return null;
        }

        // Extract label/sender ID
        $senderid = '';
        if (preg_match('#<label>(.*?)</label>#i', $body, $matches)) {
            $senderid = $matches[1];
            $body = preg_replace('#<label>.*?</label>#i', '', $body);
        }

        // Parse remaining XML using regex (more reliable for malformed XML)
        $result = [
            'username' => $this->extractXmlValue($body, 'username'),
            'password' => $this->extractXmlValue($body, 'password'),
            'senderid' => $senderid ?: $this->extractXmlValue($body, 'label') ?: env('SMPP_DEFAULT_SENDER', 'SMSEXPERT'),
            'message' => $message,
            'numbers' => $this->extractXmlValue($body, 'number'),
            'route' => $this->extractXmlValue($body, 'smsroute') ?: '7002',
            'ispremium' => $this->extractXmlValue($body, 'ispremium') === 'y',
            'tariff' => $this->extractXmlValue($body, 'tariff') ?: '0'
        ];

        $this->writeLog("Parsed XML - Username: {$result['username']}, Sender: {$result['senderid']}, Numbers: {$result['numbers']}");

        return $result;
    }

    /**
     * Extract value from XML using regex
     */
    protected function extractXmlValue(string $xml, string $tag): string
    {
        if (preg_match("#<{$tag}>(.*?)</{$tag}>#is", $xml, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Validate user credentials
     */
    protected function validateUser(string $username, string $password): ?object
    {
        if (empty($username) || empty($password)) {
            return null;
        }

        $user = DB::table('users')
            ->where('uname', $username)
            ->where('pword', $password)
            ->where('status', 'active')
            ->first();

        return $user;
    }

    /**
     * Get user's wallet balance
     */
    protected function getWalletBalance(string $bigid): float
    {
        $user = DB::table('users')
            ->select(DB::raw('(smsg_wallet - smsg_server1_sent - smsg_server2_sent) as balance'))
            ->where('bigid', $bigid)
            ->first();

        return $user ? (float) $user->balance : 0;
    }

    /**
     * Parse phone numbers from comma-separated string
     */
    protected function parsePhoneNumbers(string $numberString): array
    {
        $numbers = array_map('trim', explode(',', $numberString));
        $formatted = [];

        foreach ($numbers as $number) {
            $number = preg_replace('/[^0-9]/', '', $number);
            
            if (empty($number)) {
                continue;
            }

            // Format UK numbers (07xxx -> 44xxx)
            if (substr($number, 0, 2) === '07') {
                $number = '44' . substr($number, 1);
            }

            $formatted[] = $number;
        }

        return $formatted;
    }

    /**
     * Check blacklisted numbers
     */
    protected function checkBlacklist(string $userref, array $phoneNumbers): array
    {
        $blocked = [];
        $allowed = [];

        foreach ($phoneNumbers as $phone) {
            $isBlacklisted = DB::table('itagg_outbound_blacklist')
                ->where('users_bigid', $userref)
                ->where('msisdn', $phone)
                ->exists();

            if ($isBlacklisted) {
                $blocked[] = $phone;
            } else {
                $allowed[] = $phone;
            }
        }

        return [
            'allowed' => $allowed,
            'blocked' => $blocked
        ];
    }

    /**
     * Determine SMS route based on XML data and user settings
     *
     * OLD SYSTEM compatibility:
     * - arunestates: Default route 7002 when not specified
     * - mark: Default route 7002 when not specified
     * - Database-driven customer features for other overrides
     */
    protected function determineRoute(array $xmlData, object $user): string
    {
        $route = $xmlData['route'];

        // Check for customer-specific route override from database
        $customerRoute = $this->customerService->getDefaultRoute($user->bigid, $user->uname);

        if ($customerRoute && (empty($route) || $route === '0')) {
            $this->writeLog("Using customer-specific default route: {$customerRoute} for user: {$user->uname}");
            return $customerRoute;
        }

        // Default to route 7002 if not specified or invalid
        if (empty($route) || $route === '0' || $route === '7') {
            $route = '7002';
        }

        return $route;
    }

    /**
     * Check if user is allowed to use a specific shortcode/sender ID
     *
     * OLD SYSTEM compatibility:
     * - Shortcode 82958: Only bigid 'dcd735888fac7d724773f574e7d68191' (SpiralArm)
     * - Shortcode 82466: Only bigid '4eea19bc689a0631f19a1ed6f4c7279f'
     */
    protected function validateShortcodeAccess(string $senderid, string $bigid): bool
    {
        // Check database for shortcode restrictions
        $restriction = $this->customerService->getShortcodeRestriction($senderid);

        if ($restriction) {
            if ($restriction['restricted'] && $restriction['allowed_bigid'] !== $bigid) {
                $this->writeLog("Shortcode {$senderid} is restricted to bigid: {$restriction['allowed_bigid']}");
                return false;
            }
        }

        return true;
    }

    /**
     * Check if confirmation email should be skipped for this user/email
     *
     * OLD SYSTEM compatibility:
     * - Hardys and Hansons emails: Skip confirmation
     * - arunestates: Skip confirmation
     */
    protected function shouldSkipConfirmation(string $fromEmail, string $username): bool
    {
        // Check database for skip confirmation setting
        if ($this->customerService->shouldSkipConfirmation($username, $fromEmail)) {
            return true;
        }

        return false;
    }

    /**
     * Send confirmation email after successful SMS submission
     */
    protected function sendConfirmationEmail(int $successCount, string $fromEmail): void
    {
        if ($this->skipConfirmation || empty($fromEmail)) {
            return;
        }

        try {
            $notificationData = [
                'type' => 'success',
                'subject' => 'XML-SMS Gateway Confirmation - OK',
                'messages_sent' => $successCount,
                'message' => 'Your SMS submission was successful.',
                'from_email' => $fromEmail,
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\XmlGatewayNotificationMail',
                $fromEmail,
                ['notification_data' => $notificationData],
                [],
                5
            );

            $this->writeLog("Confirmation email queued to: {$fromEmail}");
        } catch (Exception $e) {
            $this->writeLog("Failed to send confirmation email: " . $e->getMessage());
        }
    }

    /**
     * Get message cost for user and route
     */
    protected function getMessageCost(string $bigid, string $route, string $senderid): float
    {
        // Determine originator type
        $origtype = 'alpha';
        if (preg_match( '/^[0-9]+$/', $senderid)) {
            $origtype = strlen($senderid) < 6 ? 'shortcode' : 'msisdn';
        }

        // Query user route pricing
        $pricing = DB::table('smsg_userroute as ur')
            ->join('smsg_route as r', 'ur.routenum', '=', 'r.routenum')
            ->where('r.countrydialcode', '44')
            ->where(function($query) {
                $query->where('ur.countrydialcode', '44')
                      ->orWhere('ur.countrydialcode', 'all');
            })
            ->where('ur.numbits', 7)
            ->where('ur.origtype', $origtype)
            ->where(function($query) use ($bigid) {
                $query->where('ur.userref', $bigid)
                      ->orWhere('ur.userref', '11111111111111111111111111111111');
            })
            ->where('ur.routenum', $route)
            ->where('r.routestatus', 'live')
            ->orderBy('ur.priority')
            ->orderBy('r.costprice')
            ->select('ur.userprice')
            ->first();

        if ($pricing) {
            return (float) $pricing->userprice;
        }

        // Fallback to default price
        return 0.04; // Default 4p per SMS
    }

    /**
     * Validate sender ID format and length
     */
    protected function validateSenderId(string $senderid): bool
    {
        if (empty($senderid)) {
            return false;
        }

        // Alpha sender ID max 11 characters
        if (!preg_match('/^[0-9]+$/', $senderid) && strlen($senderid) > 11) {
            return false;
        }

        return true;
    }

    /**
     * Extract country code from phone number
     */
    protected function extractCountryCode(string $phoneNumber): ?object
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        $countries = DB::table('country')
            ->select('dialcode', 'iso_code', 'id')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();

        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country;
            }
        }

        // Default to UK
        return DB::table('country')->where('dialcode', '44')->first();
    }

    /**
     * Calculate SMS parts based on message length
     */
    protected function calculateSmsParts(string $message): int
    {
        $length = mb_strlen($message, 'UTF-8');

        if ($length <= 160) {
            return 1;
        }

        return (int) ceil($length / 153);
    }

    /**
     * Determine SMPP provider based on originator (sender ID)
     * Checks smsshortcodes.whichoperator field to determine if Sinch or Nexmo
     * Same logic as SmsSendingService::determineProviderFromOriginator()
     *
     * @param string $from The originator/sender ID
     * @return string 'sinch' or 'nexmo'
     */
    protected function determineProviderFromOriginator(string $from): string
    {
        // If alphanumeric sender ID, default to nexmo
        if (!preg_match('/^[0-9]+$/', $from)) {
            return 'nexmo';
        }

        // Format UK mobile numbers
        if (substr($from, 0, 2) === '07') {
            $from = '447' . substr($from, 2);
        }

        // Check smsshortcodes table for this number
        $shortcode = DB::table('smsshortcodes')
            ->where('number', $from)
            ->first();

        if ($shortcode && !empty($shortcode->whichoperator)) {
            $operator = strtolower($shortcode->whichoperator);

            // Check for Sinch/mBlox indicators
            if (str_contains($operator, 'sinch') || str_contains($operator, 'mblox')) {
                $this->writeLog("Provider determined from shortcode: sinch (operator: {$shortcode->whichoperator})");
                return 'sinch';
            }
        }

        // Default to nexmo
        return 'nexmo';
    }

    /**
     * Process and send SMS messages via SMPP (same as SMSController@sendMessage)
     */
    protected function processSmsMessages(
        object $user,
        array $phoneNumbers,
        string $message,
        string $senderid,
        string $route,
        float $costPerMessage
    ): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'queue_ids' => []
        ];

        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $smsParts = $this->calculateSmsParts($message);
        $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

        // Determine SMPP provider based on sender ID (same as SmsSendingService)
        $provider = $this->determineProviderFromOriginator($senderid);
        $supplierName = $provider === 'sinch' ? 'Sinch SMPP' : 'Vonage SMPP';
        $this->writeLog("Using SMPP provider: {$provider} for sender ID: {$senderid}");

        foreach ($phoneNumbers as $phoneNumber) {
            try {
                $countryInfo = $this->extractCountryCode($phoneNumber);
                $countryCode = $countryInfo ? $countryInfo->dialcode : '44';

                // Calculate dosendtimeint as Unix timestamp (like old system)
                $dosendtimeint = mktime(
                    (int) substr($datenow, 8, 2),
                    (int) substr($datenow, 10, 2),
                    (int) substr($datenow, 12, 2),
                    (int) substr($datenow, 4, 2),
                    (int) substr($datenow, 6, 2),
                    (int) substr($datenow, 0, 4)
                );

                // Calculate daemon priority (like old system)
                $userDaemonPriority = $user->daemonpriority ?? 100;
                $baseDaemonId = ($userDaemonPriority == 0 || $userDaemonPriority == 100) && count($phoneNumbers) > 500 ? 200 : $userDaemonPriority;
                $daemonId = $baseDaemonId + mt_rand(0, 39);

                // Insert into smsg_log for record keeping (same as SMSController)
                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => 'sms',
                    'initiator' => 'XmlGateway',
                    'bigid' => $bigid,
                    'mobnum' => $phoneNumber,
                    'numparts' => $smsParts,
                    'text' => $message,
                    'originator' => $senderid,
                    'numbits' => 7,
                    'timesubmitted' => $datenow,
                    'userref' => $user->bigid,
                    'affiliateref' => '0',
                    'dosendtime' => $datenow,
                    'dosendtimeint' => $dosendtimeint,
                    'dayofyear' => substr($datenow, 0, 8),
                    'timesent' => '00000000000000',
                    'sentstatus' => 'pending',
                    'sentstatustmp' => 'pending',
                    'sentstatustext' => $useSmppQueue ? 'Queued for SMPP delivery' : 'Pending',
                    'suppliermsgref' => '',
                    'smsgdaemonid' => $daemonId,
                    'sendpriority' => $baseDaemonId,
                    'costprice' => 0.000000,
                    'userprice' => $costPerMessage,
                    'aggregator_dlrcode' => 0,
                    'aggregator_dlrmsg' => $useSmppQueue ? 'Queued' : 'Non Delivered',
                    'campaignref' => '',
                    'binaryflags' => '',
                    'profit' => 0.000000,
                    'countrydialcode' => $countryCode,
                    'suppliername' => $useSmppQueue ? $supplierName : '',
                    'supplierrouteref' => '',
                    'requested_route' => $route,
                    'requested_routetag' => '',
                    'deliverystatus2' => '',  // Empty initially, will be set by DLR
                    'migration_flag' => 'new',
                ]);

                // Queue SMS via SMPP
                if ($useSmppQueue) {
                    $queueParams = [
                        'user_ref' => $user->bigid,
                        'mobile_number' => $phoneNumber,
                        'message' => $message,
                        'sender_id' => $senderid,
                        'priority' => 5,
                        'reference_id' => $bigid,
                        'provider' => $provider,
                        'metadata' => [
                            'smsg_log_id' => $smsgLogId,
                            'bigid' => $bigid,
                            'source' => 'xml_gateway',
                            'route' => $route,
                            'provider' => $provider,
                            'scheduled' => false
                        ]
                    ];

                    $queueResult = $this->smsQueueService->queueSms($queueParams);

                    if ($queueResult['success']) {
                        $results['success']++;
                        $results['queue_ids'][] = $queueResult['queue_id'];

                        $this->writeLog("SMS queued successfully - Mobile: {$phoneNumber}, Queue ID: {$queueResult['queue_id']}");

                        Log::info('XML Gateway: SMS queued via SMPP', [
                            'queue_id' => $queueResult['queue_id'],
                            'mobile' => $phoneNumber,
                            'bigid' => $bigid,
                            'user' => $user->uname
                        ]);
                    } else {
                        $results['failed']++;
                        $errorMsg = $queueResult['error'] ?? 'Unknown error';

                        $this->writeLog("SMS queue failed - Mobile: {$phoneNumber}, Error: {$errorMsg}");

                        // Update smsg_log with failure
                        DB::table('smsg_log')
                            ->where('id', $smsgLogId)
                            ->where('migration_flag', 'new')
                            ->update([
                                'sentstatus' => 'fail',
                                'sentstatustext' => 'Queue failed: ' . $errorMsg,
                                'deliverystatus2' => 'Non Delivered'  // OLD SYSTEM format
                            ]);

                        Log::error('XML Gateway: Failed to queue SMS', [
                            'mobile' => $phoneNumber,
                            'error' => $errorMsg
                        ]);
                    }
                } else {
                    // SMPP not available - just log
                    $results['success']++;
                    $this->writeLog("SMS logged (SMPP not available) - Mobile: {$phoneNumber}");
                }

            } catch (Exception $e) {
                $results['failed']++;
                $this->writeLog("Exception processing SMS to {$phoneNumber}: " . $e->getMessage());
                
                Log::error('XML Gateway: Exception processing SMS', [
                    'mobile' => $phoneNumber,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Write to log file with proper file locking
     */
    protected function writeLog(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        // Try to write with file locking and retry logic
        $maxRetries = 3;
        $retryDelay = 100000; // 100ms in microseconds
        
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $handle = @fopen($this->logFile, 'a');
                if ($handle) {
                    if (flock($handle, LOCK_EX | LOCK_NB)) {
                        fwrite($handle, $logMessage);
                        flock($handle, LOCK_UN);
                        fclose($handle);
                        break;
                    } else {
                        fclose($handle);
                        usleep($retryDelay);
                    }
                } else {
                    // If file cannot be opened, use Laravel's Log as fallback
                    Log::info('XML Gateway: ' . $message);
                    break;
                }
            } catch (Exception $e) {
                // Fallback to Laravel Log on any error
                Log::info('XML Gateway: ' . $message);
                break;
            }
        }
        
        // Also output to console if available
        if ($this->output) {
            $this->line($message);
        }
    }

    /**
     * Log error and optionally send email notification
     */
    protected function logError(string $message): void
    {
        // Always try to write to log file first
        try {
            $this->writeLog("ERROR: {$message}");
        } catch (Exception $e) {
            // Ignore write errors, continue with Laravel Log
        }
        
        // Always log to Laravel's logging system
        Log::error('XML to SMS Gateway Error', [
            'message' => $message,
            'from_email' => $this->fromEmail
        ]);

        // Send error notification email if from email is available
        if (!empty($this->fromEmail)) {
            try {
                $notificationData = [
                    'type' => 'error',
                    'subject' => 'XML-SMS Gateway Warning/Error',
                    'message' => $message,
                    'from_email' => $this->fromEmail,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];

                $emailQueueService = new \App\Services\Queue\EmailQueueService();
                $emailQueueService->queueEmail(
                    'App\\Mail\\XmlGatewayNotificationMail',
                    $this->fromEmail,
                    ['notification_data' => $notificationData],
                    [],
                    10 // High priority for errors
                );
            } catch (Exception $e) {
                Log::error('Failed to send error email: ' . $e->getMessage());
            }
        }

        // Output to console if available
        if ($this->output) {
            $this->error($message);
        }
    }
}

/**
 * Native IMAP Client wrapper for PHP IMAP extension
 */
class NativeImapClient
{
    protected $connection;
    protected $mailbox;

    public function __construct($connection, string $mailbox)
    {
        $this->connection = $connection;
        $this->mailbox = $mailbox;
    }

    public function getFolder(string $folder)
    {
        return new NativeImapFolder($this->connection, $this->mailbox, $folder);
    }

    public function createFolder(string $folder): bool
    {
        $fullPath = str_replace('INBOX', $folder, $this->mailbox);
        return imap_createmailbox($this->connection, $fullPath);
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
        }
    }
}

/**
 * Native IMAP Folder wrapper
 */
class NativeImapFolder
{
    protected $connection;
    protected $mailbox;
    protected $folder;

    public function __construct($connection, string $mailbox, string $folder)
    {
        $this->connection = $connection;
        $this->mailbox = $mailbox;
        $this->folder = $folder;
    }

    public function messages()
    {
        return new NativeImapQuery($this->connection);
    }
}

/**
 * Native IMAP Query builder
 */
class NativeImapQuery
{
    protected $connection;
    protected $criteria = 'UNSEEN';
    protected $limit = 50;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function unseen()
    {
        $this->criteria = 'UNSEEN';
        return $this;
    }

    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function get(): array
    {
        $messages = [];
        $emails = imap_search($this->connection, $this->criteria);

        if (!$emails) {
            return $messages;
        }

        $count = 0;
        foreach ($emails as $emailNumber) {
            if ($count >= $this->limit) {
                break;
            }

            $header = imap_headerinfo($this->connection, $emailNumber);
            $body = imap_fetchbody($this->connection, $emailNumber, 1);

            $messages[] = new NativeImapMessage($this->connection, $emailNumber, [
                'from' => $header->fromaddress ?? '',
                'subject' => $header->subject ?? '',
                'body' => $body,
                'header' => $header
            ]);

            $count++;
        }

        return $messages;
    }

    public function count(): int
    {
        $emails = imap_search($this->connection, $this->criteria);
        return $emails ? count($emails) : 0;
    }
}

/**
 * Native IMAP Message wrapper
 */
class NativeImapMessage
{
    protected $connection;
    protected $messageNumber;
    protected $data;

    public function __construct($connection, int $messageNumber, array $data)
    {
        $this->connection = $connection;
        $this->messageNumber = $messageNumber;
        $this->data = $data;
    }

    public function getSubject(): string
    {
        return $this->data['subject'] ?? '';
    }

    public function getFrom(): array
    {
        $from = $this->data['from'] ?? '';
        if (preg_match('/([A-Za-z0-9_\-\.]+@[A-Za-z0-9_\-\.]+)/', $from, $matches)) {
            return [(object)['mail' => $matches[1]]];
        }
        return [];
    }

    public function getTextBody(): string
    {
        return $this->data['body'] ?? '';
    }

    public function getHTMLBody(): string
    {
        return $this->data['body'] ?? '';
    }

    public function setFlag(string $flag): void
    {
        if ($flag === 'Seen') {
            imap_setflag_full($this->connection, (string)$this->messageNumber, '\\Seen');
        }
    }

    public function delete(): void
    {
        imap_delete($this->connection, $this->messageNumber);
        imap_expunge($this->connection);
    }

    public function move(string $folder): void
    {
        imap_mail_move($this->connection, (string)$this->messageNumber, $folder);
        imap_expunge($this->connection);
    }
}
