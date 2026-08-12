<?php

namespace App\Services\SMPP;

use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Queue\RabbitMQService;
use Exception;
use Carbon\Carbon;

/**
 * SMPP Service for handling SMS sending via SMPP protocol
 * Enhanced with cluster support, load balancing, and failover
 */
class SMPPService
{
    private $socket;
    private $connected = false;
    private $bound = false;
    private $sequenceNumber = 1;
    private $hosts = []; // Array of available hosts
    private $currentHostIndex = 0;
    private $currentHost;
    private $port;
    private $systemId;
    private $password;
    private $lastActivity;
    private $messagesSent = 0;
    private $messagesFailed = 0;
    private $tpsLimit;
    private $tpsCounter = [];
    private $rabbitMQ;
    private $pendingMessages = []; // Store message info for DLR matching
    private $enableDetailedLogging = true; // Enable detailed logging
    private $connectionAttempts = 0;
    private $maxConnectionAttempts = 3;
    private $loadBalancingMode = 'round-robin'; // round-robin, random, least-used, failover
    private $hostStats = []; // Track host usage and performance

    // SMPP Command IDs
    const BIND_TRANSCEIVER = 0x00000009;
    const BIND_TRANSCEIVER_RESP = 0x80000009;
    const SUBMIT_SM = 0x00000004;
    const SUBMIT_SM_RESP = 0x80000004;
    const DELIVER_SM = 0x00000005;
    const DELIVER_SM_RESP = 0x80000005;
    const ENQUIRE_LINK = 0x00000015;
    const ENQUIRE_LINK_RESP = 0x80000015;
    const UNBIND = 0x00000006;
    const UNBIND_RESP = 0x80000006;
    const GENERIC_NACK = 0x80000000;

    // SMPP Status Codes
    const ESME_ROK = 0x00000000; // No Error
    const ESME_RINVMSGLEN = 0x00000001; // Message Length is invalid
    const ESME_RINVCMDLEN = 0x00000002; // Command Length is invalid
    const ESME_RINVCMDID = 0x00000003; // Invalid Command ID
    const ESME_RINVBNDSTS = 0x00000004; // Incorrect BIND Status for given command
    const ESME_RALYBND = 0x00000005; // ESME Already in Bound State
    const ESME_RINVPRTFLG = 0x00000006; // Invalid Priority Flag
    const ESME_RINVREGDLVFLG = 0x00000007; // Invalid Registered Delivery Flag
    const ESME_RSYSERR = 0x00000008; // System Error
    const ESME_RINVSRCADR = 0x0000000A; // Invalid Source Address
    const ESME_RINVDSTADR = 0x0000000B; // Invalid Dest Addr
    const ESME_RSUBMITFAIL = 0x00000045; // submit_sm or submit_multi failed
    const ESME_RTHROTTLED = 0x00000058; // Throttling error

    // Data Coding
    const DATA_CODING_DEFAULT = 0x00; // SMSC Default Alphabet
    const DATA_CODING_LATIN1 = 0x03; // Latin 1
    const DATA_CODING_UCS2 = 0x08; // UCS2 (UTF-16)

    // ESM Class for DLR
    const ESM_DELIVER_SMSC_RECEIPT = 0x04;

    // Status code messages
    private $statusMessages = [
        0x00000000 => 'No Error',
        0x00000001 => 'Message Length is invalid',
        0x00000002 => 'Command Length is invalid',
        0x00000003 => 'Invalid Command ID',
        0x00000004 => 'Incorrect BIND Status for given command',
        0x00000005 => 'ESME Already in Bound State',
        0x00000006 => 'Invalid Priority Flag',
        0x00000007 => 'Invalid Registered Delivery Flag',
        0x00000008 => 'System Error',
        0x0000000A => 'Invalid Source Address',
        0x0000000B => 'Invalid Destination Address',
        0x00000045 => 'Submit Failed',
        0x00000058 => 'Throttling Error - Exceeded allowed message throughput'
    ];

    public function __construct($host = null, $port = null)
    {
        // Parse multiple hosts from environment
        $this->parseHosts($host);
        $this->port = $port ?: env('SMPP_PORT', 8000);
        $this->systemId = env('SMPP_SYSTEM_ID');
        $this->password = env('SMPP_PASSWORD');
        $this->tpsLimit = env('SMPP_TPS_LIMIT', 50);
        $this->enableDetailedLogging = env('SMPP_DETAILED_LOGGING', true);
        $this->loadBalancingMode = env('SMPP_LOAD_BALANCING_MODE', 'round-robin');
        $this->maxConnectionAttempts = env('SMPP_MAX_CONNECTION_ATTEMPTS', 3);

        // Initialize host statistics from cache
        $this->loadHostStatistics();

        try {
            $this->rabbitMQ = new RabbitMQService();
        } catch (Exception $e) {
            SmppLogger::forProvider('cluster')->warning("RabbitMQ not available for DLR: " . $e->getMessage());
            $this->rabbitMQ = null;
        }
    }

    /**
     * Parse multiple hosts from configuration
     */
    private function parseHosts($host = null)
    {
        if ($host) {
            $this->hosts = is_array($host) ? $host : [$host];
        } else {
            // Check for multiple hosts in environment
            $hostsEnv = env('SMPP_HOSTS', env('SMPP_HOST', 'smpp1.nexmo.com'));
            
            if (strpos($hostsEnv, ',') !== false) {
                // Multiple hosts provided
                $this->hosts = array_map('trim', explode(',', $hostsEnv));
            } else {
                // Single host
                $this->hosts = [$hostsEnv];
            }
        }

        // Initialize host statistics
        foreach ($this->hosts as $host) {
            if (!isset($this->hostStats[$host])) {
                $this->hostStats[$host] = [
                    'messages_sent' => 0,
                    'messages_failed' => 0,
                    'last_used' => null,
                    'last_error' => null,
                    'response_time_avg' => 0,
                    'is_active' => true,
                    'failed_attempts' => 0,
                    'last_failed' => null
                ];
            }
        }

        SmppLogger::forProvider('cluster')->info("SMPP Cluster initialized with hosts", [
            'hosts' => $this->hosts,
            'load_balancing_mode' => $this->loadBalancingMode
        ]);
    }

    /**
     * Load host statistics from cache
     */
    private function loadHostStatistics()
    {
        $cachedStats = Cache::get('smpp_host_statistics', []);
        
        foreach ($cachedStats as $host => $stats) {
            if (in_array($host, $this->hosts)) {
                $this->hostStats[$host] = array_merge(
                    $this->hostStats[$host] ?? [],
                    $stats
                );
            }
        }
    }

    /**
     * Save host statistics to cache
     */
    private function saveHostStatistics()
    {
        Cache::put('smpp_host_statistics', $this->hostStats, 3600); // Cache for 1 hour
    }

    /**
     * Get next host based on load balancing strategy
     */
    private function getNextHost()
    {
        $availableHosts = $this->getAvailableHosts();
        
        if (empty($availableHosts)) {
            // All hosts are marked as failed, reset them
            $this->resetFailedHosts();
            $availableHosts = $this->hosts;
        }

        switch ($this->loadBalancingMode) {
            case 'random':
                return $this->getRandomHost($availableHosts);
                
            case 'least-used':
                return $this->getLeastUsedHost($availableHosts);
                
            case 'failover':
                return $this->getFailoverHost($availableHosts);
                
            case 'round-robin':
            default:
                return $this->getRoundRobinHost($availableHosts);
        }
    }

    /**
     * Get available hosts (not marked as failed)
     */
    private function getAvailableHosts()
    {
        $available = [];
        $now = Carbon::now();
        
        foreach ($this->hosts as $host) {
            $stats = $this->hostStats[$host];
            
            // Check if host should be retried after failure
            if (!$stats['is_active'] && $stats['last_failed']) {
                $minutesSinceFailed = $now->diffInMinutes(Carbon::parse($stats['last_failed']));
                
                // Retry failed hosts after 5 minutes
                if ($minutesSinceFailed >= 5) {
                    $this->hostStats[$host]['is_active'] = true;
                    $this->hostStats[$host]['failed_attempts'] = 0;
                    SmppLogger::forProvider('cluster')->info("Reactivating SMPP host after cooldown", ['host' => $host]);
                }
            }
            
            if ($this->hostStats[$host]['is_active']) {
                $available[] = $host;
            }
        }
        
        return $available;
    }

    /**
     * Reset all failed hosts
     */
    private function resetFailedHosts()
    {
        foreach ($this->hostStats as $host => &$stats) {
            $stats['is_active'] = true;
            $stats['failed_attempts'] = 0;
        }
        
        SmppLogger::forProvider('cluster')->warning("All SMPP hosts were marked as failed. Resetting all hosts.");
    }

    /**
     * Get random host
     */
    private function getRandomHost($availableHosts)
    {
        return $availableHosts[array_rand($availableHosts)];
    }

    /**
     * Get least used host
     */
    private function getLeastUsedHost($availableHosts)
    {
        $leastUsed = null;
        $minMessages = PHP_INT_MAX;
        
        foreach ($availableHosts as $host) {
            $messageCount = $this->hostStats[$host]['messages_sent'];
            if ($messageCount < $minMessages) {
                $minMessages = $messageCount;
                $leastUsed = $host;
            }
        }
        
        return $leastUsed ?: $availableHosts[0];
    }

    /**
     * Get failover host (always use first available)
     */
    private function getFailoverHost($availableHosts)
    {
        return $availableHosts[0];
    }

    /**
     * Get round-robin host
     */
    private function getRoundRobinHost($availableHosts)
    {
        // Get the next host in rotation among available hosts
        $currentIndex = Cache::get('smpp_round_robin_index', 0);
        $host = $availableHosts[$currentIndex % count($availableHosts)];
        
        // Update index for next call
        Cache::put('smpp_round_robin_index', ($currentIndex + 1) % count($availableHosts), 3600);
        
        return $host;
    }

    /**
     * Mark host as failed
     */
    private function markHostAsFailed($host, $error)
    {
        $this->hostStats[$host]['failed_attempts']++;
        $this->hostStats[$host]['last_error'] = $error;
        $this->hostStats[$host]['last_failed'] = Carbon::now()->toIso8601String();
        
        // Mark as inactive after 3 failed attempts
        if ($this->hostStats[$host]['failed_attempts'] >= 3) {
            $this->hostStats[$host]['is_active'] = false;
            SmppLogger::forProvider('cluster')->error("SMPP host marked as failed after multiple attempts", [
                'host' => $host,
                'failed_attempts' => $this->hostStats[$host]['failed_attempts'],
                'error' => $error
            ]);
        }
        
        $this->saveHostStatistics();
    }

    /**
     * Mark host as successful
     */
    private function markHostAsSuccessful($host, $responseTime = null)
    {
        $this->hostStats[$host]['messages_sent']++;
        $this->hostStats[$host]['last_used'] = Carbon::now()->toIso8601String();
        $this->hostStats[$host]['failed_attempts'] = 0; // Reset failed attempts on success
        
        // Update average response time
        if ($responseTime !== null) {
            $avgTime = $this->hostStats[$host]['response_time_avg'];
            $count = $this->hostStats[$host]['messages_sent'];
            $this->hostStats[$host]['response_time_avg'] = (($avgTime * ($count - 1)) + $responseTime) / $count;
        }
        
        $this->saveHostStatistics();
    }

    /**
     * Log SMPP PDU for debugging
     */
    private function logPDU($direction, $commandId, $commandStatus, $sequenceNumber, $body = '', $additionalData = [])
    {
        if (!$this->enableDetailedLogging) {
            return;
        }

        $commandName = $this->getCommandName($commandId);
        $statusMessage = $this->statusMessages[$commandStatus] ?? 'Unknown Status';

        $logData = [
            'direction' => $direction,
            'command' => $commandName,
            'command_id' => sprintf('0x%08X', $commandId),
            'status' => sprintf('0x%08X', $commandStatus),
            'status_message' => $statusMessage,
            'sequence' => $sequenceNumber,
            'timestamp' => Carbon::now()->format('Y-m-d H:i:s.u'),
            'host' => $this->currentHost,
            'port' => $this->port
        ];

        if (!empty($additionalData)) {
            $logData = array_merge($logData, $additionalData);
        }

        // Log to separate SMPP log file
        SmppLogger::forProvider('cluster')->info("SMPP {$direction}: {$commandName}", $logData);

        // Also log to database for tracking
        try {
            DB::table('smpp_logs')->insert([
                'direction' => $direction,
                'command' => $commandName,
                'command_id' => sprintf('0x%08X', $commandId),
                'command_status' => $commandStatus,
                'status_message' => $statusMessage,
                'sequence_number' => $sequenceNumber,
                'host' => $this->currentHost,
                'data' => json_encode($logData),
                'created_at' => Carbon::now()
            ]);
        } catch (Exception $e) {
            // Ignore logging errors
        }
    }

    /**
     * Get command name from ID
     */
    private function getCommandName($commandId)
    {
        $commands = [
            self::BIND_TRANSCEIVER => 'bind_transceiver',
            self::BIND_TRANSCEIVER_RESP => 'bind_transceiver_resp',
            self::SUBMIT_SM => 'submit_sm',
            self::SUBMIT_SM_RESP => 'submit_sm_resp',
            self::DELIVER_SM => 'deliver_sm',
            self::DELIVER_SM_RESP => 'deliver_sm_resp',
            self::ENQUIRE_LINK => 'enquire_link',
            self::ENQUIRE_LINK_RESP => 'enquire_link_resp',
            self::UNBIND => 'unbind',
            self::UNBIND_RESP => 'unbind_resp',
            self::GENERIC_NACK => 'generic_nack'
        ];

        return $commands[$commandId] ?? sprintf('unknown_0x%08X', $commandId);
    }

    /**
     * Check if socket is valid
     */
    private function isSocketValid()
    {
        return $this->socket && is_resource($this->socket) && get_resource_type($this->socket) === 'stream';
    }

    /**
     * Connect to SMPP server with cluster support
     */
    public function connect($host = null, $port = null)
    {
        if ($port) $this->port = $port;
        
        // If specific host provided, use it
        if ($host) {
            return $this->connectToHost($host);
        }
        
        // Try connecting to hosts based on load balancing strategy
        $attempts = 0;
        $maxAttempts = count($this->hosts) * 2; // Try each host twice if needed
        
        while ($attempts < $maxAttempts) {
            $selectedHost = $this->getNextHost();
            
            SmppLogger::forProvider('cluster')->info("Attempting SMPP connection", [
                'host' => $selectedHost,
                'attempt' => $attempts + 1,
                'max_attempts' => $maxAttempts,
                'load_balancing_mode' => $this->loadBalancingMode
            ]);
            
            if ($this->connectToHost($selectedHost)) {
                return true;
            }
            
            $attempts++;
            
            // Brief delay before trying next host
            if ($attempts < $maxAttempts) {
                usleep(500000); // 500ms delay
            }
        }
        
        throw new Exception("Failed to connect to any SMPP host after {$attempts} attempts");
    }

    /**
     * Connect to specific host
     */
    private function connectToHost($host)
    {
        $this->currentHost = $host;
        $startTime = microtime(true);
        
        try {
            SmppLogger::forProvider('cluster')->info("SMPP Connection Attempt", [
                'host' => $host,
                'port' => $this->port,
                'system_id' => $this->systemId
            ]);

            // Close any existing connection
            if ($this->isSocketValid()) {
                @fclose($this->socket);
            }

            $this->connected = false;
            $this->bound = false;

            // Create socket connection with timeout
            $errorCode = 0;
            $errorString = '';
            $this->socket = @fsockopen($host, $this->port, $errorCode, $errorString, 10); // 10 second timeout

            if (!$this->socket) {
                throw new Exception("Failed to connect to SMPP server: $errorString ($errorCode)");
            }

            // Set socket options
            stream_set_blocking($this->socket, true);
            stream_set_timeout($this->socket, 30);

            $this->connected = true;
            $this->lastActivity = Carbon::now();

            // Update connection status in database
            $this->updateConnectionStatus('connected', $host);

            SmppLogger::forProvider('cluster')->info("SMPP Socket Connected Successfully", ['host' => $host]);

            // Bind to SMPP server
            if ($this->bind()) {
                $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
                $this->markHostAsSuccessful($host, $responseTime);
                
                SmppLogger::forProvider('cluster')->info("Successfully connected and bound to SMPP host", [
                    'host' => $host,
                    'response_time_ms' => round($responseTime, 2)
                ]);
                
                return true;
            } else {
                throw new Exception("Failed to bind to SMPP server");
            }
            
        } catch (Exception $e) {
            $this->connected = false;
            $this->bound = false;
            
            $this->markHostAsFailed($host, $e->getMessage());
            $this->updateConnectionStatus('error', $host, $e->getMessage());
            
            SmppLogger::forProvider('cluster')->error("SMPP Connection Failed", [
                'error' => $e->getMessage(),
                'host' => $host,
                'port' => $this->port
            ]);
            
            return false;
        }
    }

    /**
     * Bind to SMPP server as transceiver (can send and receive)
     */
    private function bind()
    {
        try {
            if (!$this->isSocketValid()) {
                throw new Exception("Socket not connected");
            }

            $body = pack('a' . (strlen($this->systemId) + 1), $this->systemId . chr(0));
            $body .= pack('a' . (strlen($this->password) + 1), $this->password . chr(0));
            $body .= pack('a' . (strlen('smpp') + 1), 'smpp' . chr(0));
            $body .= pack('CCC', 0x34, 0x00, 0x00); // interface_version, addr_ton, addr_npi
            $body .= pack('a1', chr(0)); // address_range

            $sequenceNum = $this->sequenceNumber++;
            $pdu = $this->buildPDU(self::BIND_TRANSCEIVER, $body, 0, $sequenceNum);

            $this->logPDU('REQUEST', self::BIND_TRANSCEIVER, 0, $sequenceNum, $body, [
                'system_id' => $this->systemId,
                'system_type' => 'smpp',
                'interface_version' => '0x34'
            ]);

            $this->sendPDU($pdu);

            $response = $this->readPDU(true); // Blocking read for bind response

            if ($response) {
                $this->logPDU(
                    'RESPONSE',
                    $response['command_id'],
                    $response['command_status'],
                    $response['sequence_number'],
                    $response['body']
                );

                if ($response['command_id'] == self::BIND_TRANSCEIVER_RESP) {
                    if ($response['command_status'] == self::ESME_ROK) {
                        $this->bound = true;
                        $this->updateConnectionStatus('connected', $this->currentHost);
                        SmppLogger::forProvider('cluster')->info("SMPP Bind Successful", [
                            'system_id' => $this->systemId,
                            'bind_type' => 'transceiver',
                            'host' => $this->currentHost
                        ]);
                        return true;
                    } else {
                        $errorMsg = $this->statusMessages[$response['command_status']] ?? 'Unknown error';
                        throw new Exception("Bind failed: {$errorMsg} (0x" . sprintf('%08X', $response['command_status']) . ")");
                    }
                }
            }

            throw new Exception("Invalid bind response");
        } catch (Exception $e) {
            $this->bound = false;
            $this->updateConnectionStatus('error', $this->currentHost, $e->getMessage());
            SmppLogger::forProvider('cluster')->error("SMPP Bind Failed", [
                'error' => $e->getMessage(),
                'system_id' => $this->systemId,
                'host' => $this->currentHost
            ]);
            throw $e;
        }
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode($phoneNumber)
    {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Fetch all country dial codes from the database ordered by length desc (longest first)
        $countries = DB::table('country')
            ->select('dialcode', 'iso_code')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();

        // Loop through each country and check if phone number starts with dialcode
        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country->dialcode; // or $country->iso_code if you want country code
            }
        }

        // Fallback: default to '44' (UK) if nothing matches
        return '44';
    }

    /**
     * Send SMS message with DLR request and automatic failover
     */
    public function sendSMS($to, $message, $from = null, $priority = 5, $queueId = null, $initiator = 'ControlPanel')
    {
        $lastError = null;
        $attempts = 0;
        $maxRetries = 3;
        
        while ($attempts < $maxRetries) {
            try {
                // Ensure connection
                if (!$this->connected || !$this->bound) {
                    $this->connect();
                }
                
                // Try to send SMS
                return $this->sendSMSInternal($to, $message, $from, $priority, $queueId, $initiator);
                
            } catch (Exception $e) {
                $lastError = $e;
                $attempts++;
                
                SmppLogger::forProvider('cluster')->warning("SMS send attempt failed", [
                    'attempt' => $attempts,
                    'max_retries' => $maxRetries,
                    'host' => $this->currentHost,
                    'error' => $e->getMessage()
                ]);
                
                // Disconnect from current host
                $this->disconnect();
                
                // If not the last attempt, try reconnecting (possibly to different host)
                if ($attempts < $maxRetries) {
                    usleep(500000); // 500ms delay before retry
                    
                    // Force connection to different host on retry
                    if ($this->loadBalancingMode === 'failover' || $attempts > 1) {
                        $this->markHostAsFailed($this->currentHost, $e->getMessage());
                    }
                }
            }
        }
        
        // All retries exhausted
        throw $lastError ?: new Exception("Failed to send SMS after {$attempts} attempts");
    }

    /**
     * Internal SMS sending method
     */
    private function sendSMSInternal($to, $message, $from = null, $priority = 5, $queueId = null, $initiator = 'ControlPanel')
    {
        // Check TPS limit
        if (!$this->checkTpsLimit()) {
            throw new Exception("TPS limit exceeded. Please try again later.");
        }

        $startTime = microtime(true);

        try {
            // Clean phone number
            $originalTo = $to;
            $to = preg_replace('/[^0-9]/', '', $to);

            // Extract country code before formatting
            $countryCode = $this->extractCountryCode($to);

            // Format UK numbers
            if (substr($to, 0, 1) === '0') {
                $to = '44' . substr($to, 1);
                $countryCode = '44';
            }

            // Set sender ID
            $from = $from ?: env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME');

            // Determine data coding based on message content
            $dataCoding = $this->detectDataCoding($message);

            // Build submit_sm PDU with DLR request
            $body = '';
            $body .= pack('C', 0); // service_type
            // Source TON/NPI by sender type — mirrors OLD SYSTEM smsg_2send_csn_smpp_fire.inc:
            //   letters         -> Alphanumeric (5)/NPI 0   (e.g. "MYBRANDNAME")
            //   numeric  <6      -> NetworkSpecific (3)/NPI 0 (shortcode)
            //   numeric  >=6     -> International (1)/E.164 (1)
            // Wrong TON makes Vonage reject with REJECTD/err:099 on strict destinations.
            $srcDigits = ltrim($from, '+');
            if (!ctype_digit($srcDigits))   { $srcTon = 0x05; $srcNpi = 0x00; } // alphanumeric
            elseif (strlen($srcDigits) < 6) { $srcTon = 0x03; $srcNpi = 0x00; } // shortcode
            else                            { $srcTon = 0x01; $srcNpi = 0x01; } // international
            $body .= pack('CC', $srcTon, $srcNpi); // source_addr_ton, source_addr_npi
            $body .= pack('a' . (strlen($from) + 1), $from . chr(0));
            $body .= pack('CC', 0x01, 0x01); // dest_addr_ton, dest_addr_npi
            $body .= pack('a' . (strlen($to) + 1), $to . chr(0));
            $body .= pack('CCC', 0x00, 0x00, 0x00); // esm_class, protocol_id, priority_flag
            $body .= pack('a1a1', chr(0), chr(0)); // schedule_delivery_time, validity_period

            // Request delivery receipt (important for DLR)
            $body .= pack('C', 0x01); // registered_delivery = 1 (request DLR)

            $body .= pack('C', 0x00); // replace_if_present_flag
            $body .= pack('CC', $dataCoding, 0x00); // data_coding, sm_default_msg_id

            // Handle long messages
            if (strlen($message) > 160) {
                // Use message payload TLV for long messages
                $body .= pack('C', 0); // sm_length = 0
                $body .= pack('nna*', 0x0424, strlen($message), $message); // message_payload TLV
            } else {
                $body .= pack('C', strlen($message)); // sm_length
                $body .= $message; // short_message
            }

            $sequenceNum = $this->sequenceNumber++;
            $pdu = $this->buildPDU(self::SUBMIT_SM, $body, 0, $sequenceNum);

            // Log submit_sm request
            $this->logPDU('REQUEST', self::SUBMIT_SM, 0, $sequenceNum, $body, [
                'from' => $from,
                'to' => $to,
                'message' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
                'data_coding' => $dataCoding,
                'registered_delivery' => 1,
                'host' => $this->currentHost
            ]);

            // Store message info for DLR matching
            $this->pendingMessages[$sequenceNum] = [
                'queue_id' => $queueId,
                'mobile' => $to,
                'message' => substr($message, 0, 20),
                'sent_at' => Carbon::now(),
                'country_code' => $countryCode,
                'initiator' => $initiator,
                'host' => $this->currentHost
            ];

            $this->sendPDU($pdu);

            $response = $this->readPDU(true); // Blocking read for submit response

            if ($response) {
                $this->logPDU(
                    'RESPONSE',
                    $response['command_id'],
                    $response['command_status'],
                    $response['sequence_number'],
                    $response['body']
                );

                if ($response['command_id'] == self::SUBMIT_SM_RESP) {
                    if ($response['command_status'] == self::ESME_ROK) {
                        // Extract message ID from response
                        $messageId = $this->extractMessageId($response['body']);

                        // Use SMPP message ID as deliveryreceipt1 for tracking
                        // This allows matching DLR responses with the original message
                        $deliveryReceipt1 = $messageId;
                        $supplierMsgRef = mt_rand(1000000000, 9999999999);
                        
                        SmppLogger::forProvider('cluster')->info("Using SMPP message ID as deliveryreceipt1", [
                            'message_id' => $messageId,
                            'deliveryreceipt1' => $deliveryReceipt1,
                            'queue_id' => $queueId,
                            'host' => $this->currentHost
                        ]);

                        // Update pending messages with message ID for DLR matching
                        if (isset($this->pendingMessages[$response['sequence_number']])) {
                            $pendingMsg = $this->pendingMessages[$response['sequence_number']];

                            // Store message ID mapping for DLR
                            $this->storeMessageIdMapping(
                                $messageId,
                                $pendingMsg['queue_id'],
                                $to,
                                $pendingMsg['country_code'],
                                $pendingMsg['initiator'],
                                $deliveryReceipt1,
                                $supplierMsgRef
                            );

                            unset($this->pendingMessages[$response['sequence_number']]);
                        }

                        $this->messagesSent++;
                        $this->updateConnectionStatus('connected', $this->currentHost);

                        // Calculate response time and update host stats
                        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
                        $this->markHostAsSuccessful($this->currentHost, $responseTime);

                        SmppLogger::forProvider('cluster')->info("SMS Sent Successfully", [
                            'message_id' => $messageId,
                            'to' => $to,
                            'from' => $from,
                            'queue_id' => $queueId,
                            'supplier_msg_ref' => $supplierMsgRef,
                            'delivery_receipt1' => $deliveryReceipt1,
                            'country_code' => $countryCode,
                            'initiator' => $initiator,
                            'host' => $this->currentHost,
                            'response_time_ms' => round($responseTime, 2)
                        ]);

                        return [
                            'success' => true,
                            'message_id' => $messageId,
                            'to' => $to,
                            'from' => $from,
                            'host' => $this->currentHost,
                            'supplier_msg_ref' => $supplierMsgRef,
                            'delivery_receipt1' => $deliveryReceipt1,
                            'response_time_ms' => round($responseTime, 2)
                        ];
                    } else {
                        $this->messagesFailed++;
                        $errorMsg = $this->statusMessages[$response['command_status']] ?? 'Unknown error';
                        $errorCode = sprintf('0x%08X', $response['command_status']);

                        // Update smsg_log with failure reason
                        $this->updateSmsgLogFailure($to, $errorMsg, $errorCode, $countryCode, $initiator);

                        throw new Exception("Submit failed: {$errorMsg} ({$errorCode})");
                    }
                }
            }

            throw new Exception("Invalid submit response");
        } catch (Exception $e) {
            $this->messagesFailed++;
            
            // Update host statistics for failure
            $this->hostStats[$this->currentHost]['messages_failed']++;
            $this->saveHostStatistics();
            
            SmppLogger::forProvider('cluster')->error("Failed to send SMS", [
                'error' => $e->getMessage(),
                'to' => $to ?? 'unknown',
                'from' => $from ?? 'unknown',
                'host' => $this->currentHost
            ]);

            // Update smsg_log with failure
            if (isset($to)) {
                $this->updateSmsgLogFailure($to, $e->getMessage(), 'SEND_ERROR', $countryCode ?? '44', $initiator);
            }

            throw $e;
        }
    }

    /**
     * Update smsg_log for failed SMS
     */
    private function updateSmsgLogFailure($mobile, $errorMessage, $errorCode, $countryCode, $initiator)
    {
        try {
            DB::table('smsg_log')
                ->where('mobnum', $mobile)
                ->where('sentstatus', 'pending')
                ->orderBy('id', 'desc')
                ->limit(1)
                ->update([
                    'sentstatus' => 'fail',
                    'sentstatustext' => $errorMessage,
                    'aggregator_dlrcode' => $errorCode,
                    'aggregator_dlrmsg' => $errorMessage,
                    'initiator' => $initiator,
                    'timesent' => Carbon::now()->format('YmdHis')
                ]);
        } catch (Exception $e) {
            SmppLogger::forProvider('cluster')->warning("Failed to update smsg_log failure: " . $e->getMessage());
        }
    }

    // ... [Rest of the methods remain the same - copy from original file] ...
    // Including: processIncomingPdus, handleDeliverSm, storeIncomingSms, parseDlrContent,
    // processDlr, storeDlrDirectly, updateSmsgLogWithDlr, storeMessageIdMapping,
    // findMessageByMessageId, handleEnquireLink, sendDeliverSmResp, readCString,
    // parseSmppDate, mapDlrStatus, mapDlrStatusToQueueStatus, mapDlrStatusForSmsgLog,
    // enquireLink, disconnect, buildPDU, sendPDU, readPDU, extractMessageId,
    // detectDataCoding, checkTpsLimit

    /**
     * Update connection status in database with host information
     */
    private function updateConnectionStatus($status, $host = null, $error = null)
    {
        try {
            $host = $host ?: $this->currentHost;
            
            DB::table('smpp_connections')->updateOrInsert(
                [
                    'host' => $host,
                    'port' => $this->port,
                ],
                [
                    'status' => $status,
                    'system_id' => $this->systemId,
                    'messages_sent' => $this->hostStats[$host]['messages_sent'] ?? 0,
                    'messages_failed' => $this->hostStats[$host]['messages_failed'] ?? 0,
                    'current_tps' => count($this->tpsCounter),
                    'last_activity' => Carbon::now(),
                    'connected_at' => $status === 'connected' ? Carbon::now() : null,
                    'disconnected_at' => $status === 'disconnected' ? Carbon::now() : null,
                    'error_message' => $error,
                    'updated_at' => Carbon::now()
                ]
            );
        } catch (Exception $e) {
            SmppLogger::forProvider('cluster')->warning("Failed to update connection status: " . $e->getMessage());
        }
    }

    /**
     * Get cluster statistics
     */
    public function getClusterStatistics()
    {
        $stats = [
            'load_balancing_mode' => $this->loadBalancingMode,
            'total_hosts' => count($this->hosts),
            'active_hosts' => 0,
            'current_host' => $this->currentHost,
            'hosts' => []
        ];
        
        foreach ($this->hosts as $host) {
            $hostStat = $this->hostStats[$host];
            
            if ($hostStat['is_active']) {
                $stats['active_hosts']++;
            }
            
            // Get connection status from database
            $dbStatus = DB::table('smpp_connections')
                ->where('host', $host)
                ->where('port', $this->port)
                ->first();
            
            $stats['hosts'][$host] = [
                'is_active' => $hostStat['is_active'],
                'messages_sent' => $hostStat['messages_sent'],
                'messages_failed' => $hostStat['messages_failed'],
                'success_rate' => $hostStat['messages_sent'] > 0 
                    ? round(($hostStat['messages_sent'] / ($hostStat['messages_sent'] + $hostStat['messages_failed'])) * 100, 2) 
                    : 0,
                'avg_response_time_ms' => round($hostStat['response_time_avg'], 2),
                'last_used' => $hostStat['last_used'],
                'last_error' => $hostStat['last_error'],
                'failed_attempts' => $hostStat['failed_attempts'],
                'connection_status' => $dbStatus->status ?? 'unknown',
                'is_current' => ($host === $this->currentHost)
            ];
        }
        
        return $stats;
    }

    /**
     * Get connection statistics
     */
    public function getStatistics()
    {
        $clusterStats = $this->getClusterStatistics();
        
        return [
            'connected' => $this->connected,
            'bound' => $this->bound,
            'current_host' => $this->currentHost,
            'port' => $this->port,
            'messages_sent' => $this->messagesSent,
            'messages_failed' => $this->messagesFailed,
            'current_tps' => count($this->tpsCounter),
            'tps_limit' => $this->tpsLimit,
            'last_activity' => $this->lastActivity ? $this->lastActivity->toDateTimeString() : null,
            'pending_messages' => count($this->pendingMessages),
            'socket_valid' => $this->isSocketValid(),
            'cluster' => $clusterStats
        ];
    }

    // Copy remaining methods from the original file...
    // (All the DLR processing, PDU handling, and utility methods remain the same)
    // Just ensure they use $this->currentHost where needed for logging

    public function __destruct()
    {
        // Save statistics before destruction
        $this->saveHostStatistics();
        
        // Safely disconnect on destruction
        try {
            $this->disconnect();
        } catch (Exception $e) {
            // Ignore destructor errors
        }
    }
}
