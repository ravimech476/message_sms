<?php

namespace App\Services\SMPP;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\RabbitMQService;
use Exception;
use Carbon\Carbon;

/**
 * SMPP Service for handling SMS sending via SMPP protocol
 * Enhanced with comprehensive logging and proper field mapping
 */
class SMPPService
{
    private $socket;
    private $connected = false;
    private $bound = false;
    private $sequenceNumber = 1;
    private $host;
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

    // Custom TLV Tags for Nexmo/Vonage
    const TLV_MCC_MNC = 0x1402;           // MCC/MNC information
    const TLV_SUBMISSION_PRICE = 0x1422;  // Price per SMS
    const TLV_REMAINING_BALANCE = 0x1423; // Remaining balance

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
        $this->host = $host ?: env('SMPP_HOST', 'smpp1.nexmo.com');
        $this->port = $port ?: env('SMPP_PORT', 8000);
        $this->systemId = env('SMPP_SYSTEM_ID');
        $this->password = env('SMPP_PASSWORD');
        $this->tpsLimit = env('SMPP_TPS_LIMIT', 50);
        $this->enableDetailedLogging = env('SMPP_DETAILED_LOGGING', true);

        try {
            $this->rabbitMQ = new RabbitMQService();
        } catch (Exception $e) {
            Log::warning("RabbitMQ not available for DLR: " . $e->getMessage());
            $this->rabbitMQ = null;
        }
    }

    /**
     * Format schedule_delivery_time for SMPP protocol
     * Format: YYMMDDhhmmsstnnp (absolute time with timezone)
     * where t = tenths of second (0), nn = timezone offset, p = + or - for timezone
     * 
     * @param string|null $scheduleTime DateTime string or null for immediate
     * @return string Empty string for immediate send, formatted time for scheduled
     */
    private function formatScheduleTimeForSMPP($scheduleTime)
    {
        if (empty($scheduleTime)) {
            // Empty string means immediate delivery
            return '';
        }

        try {
            // Parse the schedule time
            $dt = Carbon::parse($scheduleTime, 'Europe/London');

            // Format: YYMMDDhhmmsstnnp
            // YY = year (2 digits), MM = month, DD = day
            // hh = hour, mm = minute, ss = second
            // t = tenths of second (always 0)
            // nn = timezone offset in quarter hours (00 = UTC)
            // p = + or - for timezone direction

            // Get timezone offset in minutes
            $offsetMinutes = $dt->offsetMinutes;

            // Convert to quarter hours (SMPP uses quarter hour increments)
            $quarterHours = abs($offsetMinutes) / 15;

            // Format the time string
            $formattedTime = $dt->format('ymdHis') . '0'; // Add tenths of second

            // Add timezone in format 'nnp' (quarter hours + direction)
            $formattedTime .= sprintf('%02d', $quarterHours);
            $formattedTime .= ($offsetMinutes >= 0) ? '+' : '-';

            Log::info("Formatted SMPP schedule time", [
                'input' => $scheduleTime,
                'output' => $formattedTime,
                'parsed_dt' => $dt->toDateTimeString(),
                'offset_minutes' => $offsetMinutes,
                'quarter_hours' => $quarterHours
            ]);

            return $formattedTime;
        } catch (Exception $e) {
            Log::error("Failed to format schedule time for SMPP", [
                'error' => $e->getMessage(),
                'schedule_time' => $scheduleTime
            ]);
            // Return empty for immediate delivery on error
            return '';
        }
    }

    /**
     * Format validity_period for SMPP protocol
     * Sets validity to 24 hours from schedule time or current time
     * 
     * @param string|null $scheduleTime
     * @return string Empty string for default validity, formatted time for custom
     */
    private function formatValidityPeriod($scheduleTime)
    {
        try {
            // Set validity to 24 hours from schedule time (or now if immediate)
            if (empty($scheduleTime)) {
                $validityTime = Carbon::now('Europe/London')->addHours(24);
            } else {
                $validityTime = Carbon::parse($scheduleTime, 'Europe/London')->addHours(24);
            }

            // Format same as schedule_delivery_time
            $offsetMinutes = $validityTime->offsetMinutes;
            $quarterHours = abs($offsetMinutes) / 15;

            $formattedTime = $validityTime->format('ymdHis') . '0';
            $formattedTime .= sprintf('%02d', $quarterHours);
            $formattedTime .= ($offsetMinutes >= 0) ? '+' : '-';

            return $formattedTime;
        } catch (Exception $e) {
            Log::warning("Failed to format validity period", [
                'error' => $e->getMessage()
            ]);
            // Return empty for default validity
            return '';
        }
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
            'host' => $this->host,
            'port' => $this->port
        ];

        if (!empty($additionalData)) {
            $logData = array_merge($logData, $additionalData);
        }

        // Log to separate SMPP log file
        Log::channel('single')->info("SMPP {$direction}: {$commandName}", $logData);

        // Also log to database for tracking
        try {
            DB::table('smpp_logs')->insert([
                'direction' => $direction,
                'command' => $commandName,
                'command_id' => sprintf('0x%08X', $commandId),
                'command_status' => $commandStatus,
                'status_message' => $statusMessage,
                'sequence_number' => $sequenceNumber,
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
     * Connect to SMPP server
     */
    public function connect($host = null, $port = null)
    {
        if ($host) $this->host = $host;
        if ($port) $this->port = $port;

        try {
            Log::info("SMPP Connection Attempt", [
                'host' => $this->host,
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
            $this->socket = @fsockopen($this->host, $this->port, $errorCode, $errorString, 30);

            if (!$this->socket) {
                throw new Exception("Failed to connect to SMPP server: $errorString ($errorCode)");
            }

            // Set socket options
            stream_set_blocking($this->socket, true);
            stream_set_timeout($this->socket, 60); // Increased timeout to 60 seconds

            $this->connected = true;
            $this->lastActivity = Carbon::now();

            // Update connection status in database
            $this->updateConnectionStatus('connected');

            Log::info("SMPP Socket Connected Successfully");

            // Bind to SMPP server
            return $this->bind();
        } catch (Exception $e) {
            $this->connected = false;
            $this->bound = false;
            $this->updateConnectionStatus('error', $e->getMessage());
            Log::error("SMPP Connection Failed", [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'port' => $this->port
            ]);
            throw $e;
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
                        $this->updateConnectionStatus('connected');
                        Log::info("SMPP Bind Successful", [
                            'system_id' => $this->systemId,
                            'bind_type' => 'transceiver'
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
            $this->updateConnectionStatus('error', $e->getMessage());
            Log::error("SMPP Bind Failed", [
                'error' => $e->getMessage(),
                'system_id' => $this->systemId
            ]);
            throw $e;
        }
    }

    /**
     * Extract country code from phone number
     */

    /**
     * Send SMS message with DLR request
     * @param string $to Recipient phone number
     * @param string $message SMS message content  
     * @param string $from Sender ID
     * @param int $priority Message priority
     * @param string $queueId Queue ID for tracking
     * @param string $initiator Who initiated the message
     * @param string $referenceId Reference ID (bigid) for DLR matching
     * @param string|null $scheduleDeliveryTime Scheduled delivery time in format 'YYMMDDhhmmss'
     */
    public function sendSMS($to, $message, $from = null, $priority = 5, $queueId = null, $initiator = 'ControlPanel', $referenceId = null, $scheduleDeliveryTime = null)
    {
        // Check if this message was already sent recently (duplicate prevention)
        if ($queueId) {
            $recentSend = DB::table('sms_queue')
                ->where('queue_id', $queueId)
                ->where('status', 'sent')
                ->first();

            if ($recentSend) {
                Log::warning("Message already sent, preventing duplicate", ['queue_id' => $queueId]);
                return [
                    'success' => true,
                    'message_id' => $recentSend->message_id ?? 'already_sent',
                    'to' => $to,
                    'from' => $from,
                    'host' => $this->host,
                    'duplicate' => true
                ];
            }
        }

        if (!$this->connected || !$this->bound) {
            $this->connect();
        }

        // Check TPS limit
        if (!$this->checkTpsLimit()) {
            throw new Exception("TPS limit exceeded. Please try again later.");
        }

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
            $from = $from ?: env('SMPP_DEFAULT_SENDER', 'MYBRANDNAMEsmppservice');

            // Determine data coding based on message content
            $dataCoding = $this->detectDataCoding($message);

            // Format schedule_delivery_time for SMPP (YYMMDDhhmmss with timezone)
            $smppScheduleTime = $this->formatScheduleTimeForSMPP($scheduleDeliveryTime);

            // Format validity_period (24 hours from now or scheduled time)
            $validityPeriod = $this->formatValidityPeriod($scheduleDeliveryTime);

            // Build submit_sm PDU with DLR request
            $body = '';
            $body .= pack('C', 0); // service_type
            $body .= pack('CC', 0x01, 0x01); // source_addr_ton, source_addr_npi
            $body .= pack('a' . (strlen($from) + 1), $from . chr(0));
            $body .= pack('CC', 0x01, 0x01); // dest_addr_ton, dest_addr_npi
            $body .= pack('a' . (strlen($to) + 1), $to . chr(0));
            $body .= pack('CCC', 0x00, 0x00, 0x00); // esm_class, protocol_id, priority_flag

            // Add schedule_delivery_time (null-terminated string)
            $body .= pack('a' . (strlen($smppScheduleTime) + 1), $smppScheduleTime . chr(0));

            // Add validity_period (null-terminated string)  
            $body .= pack('a' . (strlen($validityPeriod) + 1), $validityPeriod . chr(0));

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

            // Add TLV fields to request price, balance, and MCC/MNC from Nexmo
            // These TLV tags tell the SMSC to include this information in the response
            // TLV format: Tag (2 bytes) + Length (2 bytes) + Value (variable)

            // Request Submission Price (0x1422) - empty value to request
            $body .= pack('nn', self::TLV_SUBMISSION_PRICE, 0);

            // Request Remaining Balance (0x1423) - empty value to request
            $body .= pack('nn', self::TLV_REMAINING_BALANCE, 0);

            // Request MCC/MNC (0x1402) - empty value to request
            $body .= pack('nn', self::TLV_MCC_MNC, 0);

            $sequenceNum = $this->sequenceNumber++;
            $pdu = $this->buildPDU(self::SUBMIT_SM, $body, 0, $sequenceNum);

            $sequenceNum = $this->sequenceNumber++;
            $pdu = $this->buildPDU(self::SUBMIT_SM, $body, 0, $sequenceNum);

            // Log submit_sm request
            $this->logPDU('REQUEST', self::SUBMIT_SM, 0, $sequenceNum, $body, [
                'from' => $from,
                'to' => $to,
                'message' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
                'data_coding' => $dataCoding,
                'registered_delivery' => 1
            ]);

            // Store message info for DLR matching
            $this->pendingMessages[$sequenceNum] = [
                'queue_id' => $queueId,
                'reference_id' => $referenceId, // Store reference ID (bigid) for DLR matching
                'mobile' => $to,
                'message' => substr($message, 0, 20),
                'sent_at' => Carbon::now(),
                'country_code' => $countryCode,
                'initiator' => $initiator
            ];

            $this->sendPDU($pdu);

            // Try to read response with timeout - handle out-of-order PDUs
            $maxAttempts = 5;
            $response = null;
            $bufferedPdus = [];

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $pdu = $this->readPDU(true); // Blocking read

                if (!$pdu) {
                    if ($attempt < $maxAttempts) {
                        usleep(500000); // 500ms
                        Log::debug("Retrying to read submit response, attempt {$attempt}");
                        continue;
                    }
                    break;
                }

                // Check if this is the submit_sm_resp we're waiting for
                if ($pdu['command_id'] == self::SUBMIT_SM_RESP && $pdu['sequence_number'] == $sequenceNum) {
                    $response = $pdu;
                    break; // Got the response we need
                }

                // Handle other PDUs that came before our response
                if ($pdu['command_id'] == self::ENQUIRE_LINK) {
                    Log::debug("Received enquire_link while waiting for submit_sm_resp, responding...");
                    $this->handleEnquireLink($pdu);
                    // Continue waiting for submit_sm_resp
                    continue;
                } else if ($pdu['command_id'] == self::DELIVER_SM) {
                    Log::debug("Received deliver_sm while waiting for submit_sm_resp, handling...");
                    $this->handleDeliverSm($pdu);
                    // Continue waiting for submit_sm_resp
                    continue;
                } else {
                    // Buffer unexpected PDU
                    Log::debug("Buffering unexpected PDU while waiting for submit_sm_resp", [
                        'command_id' => dechex($pdu['command_id']),
                        'sequence' => $pdu['sequence_number']
                    ]);
                    $bufferedPdus[] = $pdu;
                }
            }

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
                        // Extract message ID and TLV parameters from response
                        $responseData = $this->parseSubmitSmResponse($response['body']);
                        $messageId = $responseData['message_id'];

                        // Extract TLV data (price, balance, MCC/MNC)
                        $submissionPrice = $responseData['submission_price'] ?? null;
                        $remainingBalance = $responseData['remaining_balance'] ?? null;
                        $mccMnc = $responseData['mcc_mnc'] ?? null;

                        // Log the complete SMPP response with TLV data for Nexmo support
                        Log::info("========== SMPP RESPONSE WITH TLV DATA ==========");
                        Log::info("SMPP Submit Response - Complete TLV Data", [
                            'message_id' => $messageId,
                            'queue_id' => $queueId,
                            'mobile_number' => $to,
                            'submission_price' => $submissionPrice,
                            'remaining_balance' => $remainingBalance,
                            'mcc_mnc' => $mccMnc,
                            'host' => $this->host,
                            'port' => $this->port,
                            'timestamp' => Carbon::now()->toIso8601String(),
                            'raw_tlv_data' => $responseData ?? []
                        ]);
                        Log::info("================================================");

                        // Log the message ID for debugging
                        Log::info("Extracted message ID from SMPP response", [
                            'message_id' => $messageId,
                            'queue_id' => $queueId
                        ]);

                        // Use SMPP message ID as deliveryreceipt1 for tracking
                        // This allows matching DLR responses with the original message
                        $deliveryReceipt1 = $messageId;
                        $supplierMsgRef = mt_rand(1000000000, 9999999999);

                        Log::info("Using SMPP message ID as deliveryreceipt1", [
                            'message_id' => $messageId,
                            'deliveryreceipt1' => $deliveryReceipt1,
                            'queue_id' => $queueId
                        ]);

                        // Update pending messages with message ID for DLR matching
                        if (isset($this->pendingMessages[$response['sequence_number']])) {
                            $pendingMsg = $this->pendingMessages[$response['sequence_number']];

                            // Store message ID mapping for DLR
                            $this->storeMessageIdMapping(
                                $messageId,
                                $pendingMsg['queue_id'],
                                $pendingMsg['reference_id'], // Pass reference ID for DLR matching
                                $to,
                                $pendingMsg['country_code'],
                                $pendingMsg['initiator'],
                                $deliveryReceipt1,
                                $supplierMsgRef
                            );

                            unset($this->pendingMessages[$response['sequence_number']]);
                        }

                        $this->messagesSent++;
                        $this->updateConnectionStatus('connected');

                        Log::info("SMS Sent Successfully", [
                            'message_id' => $messageId,
                            'to' => $to,
                            'from' => $from,
                            'queue_id' => $queueId,
                            'supplier_msg_ref' => $supplierMsgRef,
                            'delivery_receipt1' => $deliveryReceipt1,
                            'country_code' => $countryCode,
                        ]);

                        return [
                            'success' => true,
                            'message_id' => $messageId,
                            'to' => $to,
                            'from' => $from,
                            'host' => $this->host,
                            'supplier_msg_ref' => $supplierMsgRef,
                            'delivery_receipt1' => $deliveryReceipt1
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

            // Log warning but don't throw - SMS might have been sent
            Log::warning("Invalid or no submit response received, but SMS may have been sent");

            // Generate fallback IDs since we didn't get a proper response
            $messageId = uniqid();
            $deliveryReceipt1 = strtoupper(substr(md5($messageId . time()), 0, 16));
            $supplierMsgRef = mt_rand(1000000000, 9999999999);

            // Still mark as sent since the PDU was transmitted
            $this->messagesSent++;

            // Store mapping even without proper response
            if (isset($this->pendingMessages[$sequenceNum])) {
                $pendingMsg = $this->pendingMessages[$sequenceNum];
                $this->storeMessageIdMapping(
                    $messageId,
                    $pendingMsg['queue_id'],
                    $pendingMsg['reference_id'], // Pass reference ID for DLR matching
                    $to,
                    $pendingMsg['country_code'],
                    $pendingMsg['initiator'],
                    $deliveryReceipt1,
                    $supplierMsgRef
                );
                unset($this->pendingMessages[$sequenceNum]);
            }

            Log::info("SMS Sent (with warning)", [
                'message_id' => $messageId,
                'to' => $to,
                'from' => $from,
                'queue_id' => $queueId,
                'warning' => 'No proper response received'
            ]);

            return [
                'success' => true,
                'message_id' => $messageId,
                'to' => $to,
                'from' => $from,
                'host' => $this->host,
                'supplier_msg_ref' => $supplierMsgRef,
                'delivery_receipt1' => $deliveryReceipt1,
                'warning' => 'Response not received properly but SMS likely sent'
            ];
        } catch (Exception $e) {
            $this->messagesFailed++;
            Log::error("Failed to send SMS", [
                'error' => $e->getMessage(),
                'to' => $to ?? 'unknown',
                'from' => $from ?? 'unknown'
            ]);

            // Update smsg_log with failure
            if (isset($to)) {
                $this->updateSmsgLogFailure($to, $e->getMessage(), 'SEND_ERROR', $countryCode ?? '44', $initiator);
            }

            throw $e;
        }
    }
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
                    'countrydialcode' => $countryCode,
                    'initiator' => $initiator,
                    'timesent' => Carbon::now()->format('YmdHis')
                ]);
        } catch (Exception $e) {
            Log::warning("Failed to update smsg_log failure: " . $e->getMessage());
        }
    }

    /**
     * Process incoming PDUs (called periodically)
     */
    public function processIncomingPdus()
    {
        if (!$this->isSocketValid() || !$this->connected || !$this->bound) {
            return 0;
        }

        $processed = 0;
        $maxProcess = 10; // Process max 10 PDUs per call

        try {
            while ($processed < $maxProcess) {
                $pdu = $this->readPDU(false); // Non-blocking read

                if (!$pdu) {
                    break; // No more PDUs to process
                }

                $this->logPDU(
                    'RECEIVED',
                    $pdu['command_id'],
                    $pdu['command_status'],
                    $pdu['sequence_number'],
                    $pdu['body']
                );

                switch ($pdu['command_id']) {
                    case self::DELIVER_SM:
                        $this->handleDeliverSm($pdu);
                        break;
                    case self::ENQUIRE_LINK:
                        $this->handleEnquireLink($pdu);
                        break;
                    default:
                        Log::debug("Received unhandled PDU", ['command_id' => dechex($pdu['command_id'])]);
                }

                $processed++;
            }
        } catch (Exception $e) {
            Log::error("Error processing incoming PDUs: " . $e->getMessage());
        }

        return $processed;
    }

    /**
     * Handle deliver_sm (DLR or incoming SMS)
     */
    private function handleDeliverSm($pdu)
    {
        try {
            $body = $pdu['body'];
            $pos = 0;

            // Parse deliver_sm PDU
            // Skip service_type
            $serviceType = $this->readCString($body, $pos);

            // Source address (sender)
            $sourceTon = ord($body[$pos++]);
            $sourceNpi = ord($body[$pos++]);
            $sourceAddr = $this->readCString($body, $pos);

            // Destination address
            $destTon = ord($body[$pos++]);
            $destNpi = ord($body[$pos++]);
            $destAddr = $this->readCString($body, $pos);

            // ESM class - check if this is a delivery receipt
            $esmClass = ord($body[$pos++]);

            // Skip protocol_id, priority_flag
            $pos += 2;

            // Skip schedule_delivery_time, validity_period
            $this->readCString($body, $pos); // schedule_delivery_time
            $this->readCString($body, $pos); // validity_period

            // Skip registered_delivery, replace_if_present_flag, data_coding, sm_default_msg_id
            $pos += 4;

            // Get message length and content
            $smLength = ord($body[$pos++]);
            $messageContent = substr($body, $pos, $smLength);
            $pos += $smLength;

            // Check if this is a delivery receipt
            if ($esmClass & self::ESM_DELIVER_SMSC_RECEIPT) {
                // This is a DLR
                $dlrData = $this->parseDlrContent($messageContent);
                $dlrData['source'] = $sourceAddr;
                $dlrData['destination'] = $destAddr;

                Log::info("DLR Received", $dlrData);

                // Process the DLR
                $this->processDlr($dlrData);
            } else {
                // This is an incoming SMS (MO)
                Log::info("Incoming SMS (MO) Received", [
                    'from' => $sourceAddr,
                    'to' => $destAddr,
                    'message' => $messageContent,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s')
                ]);

                // Store incoming SMS
                $this->storeIncomingSms($sourceAddr, $destAddr, $messageContent);
            }

            // Send deliver_sm_resp
            $this->sendDeliverSmResp($pdu['sequence_number']);
        } catch (Exception $e) {
            Log::error("Failed to handle deliver_sm", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Still send response to avoid connection issues
            $this->sendDeliverSmResp($pdu['sequence_number'], 0x00000008); // ESME_RSYSERR
        }
    }

    /**
     * Store incoming SMS (MO)
     */
    private function storeIncomingSms($from, $to, $message)
    {
        try {
            // Extract country code from sender
            $countryCode = $this->extractCountryCode($from);

            // Store in database (you may need to create this table)
            DB::table('incoming_sms')->insert([
                'from_number' => $from,
                'to_number' => $to,
                'message' => $message,
                'country_code' => $countryCode,
                'received_at' => Carbon::now(),
                'created_at' => Carbon::now()
            ]);

            Log::info("Incoming SMS stored successfully", [
                'from' => $from,
                'to' => $to
            ]);
        } catch (Exception $e) {
            Log::error("Failed to store incoming SMS: " . $e->getMessage());
        }
    }

    /**
     * Parse DLR content (Vonage format)
     */
    private function parseDlrContent($content)
    {
        $dlr = [
            'raw_dlr' => $content
        ];

        Log::info("Parsing DLR Content", ['raw' => $content]);

        // Parse Vonage DLR format
        // Format: id:IIIIIIII sub:SSS dlvrd:DDD submit date:YYMMDDhhmm done date:YYMMDDhhmm stat:DDDDDDD err:E text:TTTTTTTTTTTTTTTTTTTTT

        if (preg_match('/id:([^\s]+)/', $content, $matches)) {
            $dlr['message_id'] = $matches[1];
        }
        if (preg_match('/sub:(\d+)/', $content, $matches)) {
            $dlr['submit_count'] = $matches[1];
        }
        if (preg_match('/dlvrd:(\d+)/', $content, $matches)) {
            $dlr['delivered_count'] = $matches[1];
        }
        if (preg_match('/submit date:(\d+)/', $content, $matches)) {
            $dlr['submit_date'] = $this->parseSmppDate($matches[1]);
        }
        if (preg_match('/done date:(\d+)/', $content, $matches)) {
            $dlr['done_date'] = $this->parseSmppDate($matches[1]);
        }
        if (preg_match('/stat:([A-Z]+)/', $content, $matches)) {
            $dlr['status'] = $matches[1];
        }
        if (preg_match('/err:(\d+)/', $content, $matches)) {
            $dlr['error_code'] = $matches[1];
        }
        if (preg_match('/[Tt]ext:(.{0,20})/', $content, $matches)) {
            $dlr['text'] = $matches[1];
        }

        // Map status to standard format
        $dlr['status_text'] = $this->mapDlrStatus($dlr['status'] ?? 'UNKNOWN');

        return $dlr;
    }

    /**
     * Process DLR
     */
    private function processDlr($dlrData)
    {
        try {
            Log::info("Processing DLR", $dlrData);

            // Find the original message by message ID
            // $messageInfo = $this->findMessageByMessageId($dlrData['message_id'] ?? '');

            // if ($messageInfo) {
            //     $dlrData['queue_id'] = $messageInfo['queue_id'];
            //     $dlrData['bigid'] = $messageInfo['bigid']; // Include bigid for DLR processing
            //     $dlrData['mobile_number'] = $messageInfo['mobile_number'];
            // } else {
            //     // Try to match by destination number
            $dlrData['mobile_number'] = $dlrData['destination'] ?? '';
            // }

            // Queue DLR for processing
            if ($this->rabbitMQ) {
                $this->rabbitMQ->publishToQueue(
                    env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
                    $dlrData,
                    5
                );
                Log::info("DLR queued for processing", ['message_id' => $dlrData['message_id'] ?? '']);
            } else {
                // Direct database update if RabbitMQ not available
                $this->storeDlrDirectly($dlrData);
            }

            // Update smsg_log table
            $this->updateSmsgLogWithDlr($dlrData);
        } catch (Exception $e) {
            Log::error("Failed to process DLR", [
                'error' => $e->getMessage(),
                'dlr_data' => $dlrData
            ]);
        }
    }

    /**
     * Store DLR directly in database
     */
    private function storeDlrDirectly($dlrData)
    {
        try {
            DB::table('sms_dlr')->insert([
                'message_id' => $dlrData['message_id'] ?? '',
                'queue_id' => $dlrData['queue_id'] ?? '',
                'mobile_number' => $dlrData['mobile_number'] ?? '',
                'status' => $dlrData['status'] ?? 'UNKNOWN',
                'status_text' => $dlrData['status_text'] ?? '',
                'error_code' => $dlrData['error_code'] ?? null,
                'submit_date' => $dlrData['submit_date'] ?? null,
                'done_date' => $dlrData['done_date'] ?? null,
                'raw_dlr' => json_encode($dlrData),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Update SMS queue status
            if (!empty($dlrData['queue_id'])) {
                $status = $this->mapDlrStatusToQueueStatus($dlrData['status'] ?? '');
                DB::table('sms_queue')
                    ->where('queue_id', $dlrData['queue_id'])
                    ->update([
                        'status' => $status,
                        'updated_at' => Carbon::now()
                    ]);
            }
        } catch (Exception $e) {
            Log::error("Failed to store DLR directly: " . $e->getMessage());
        }
    }

    /**
     * Update smsg_log with DLR
     */
    private function updateSmsgLogWithDlr($dlrData)
    {
        try {
            // Ensure data is always an array
            $data = is_array($dlrData) ? $dlrData : json_decode($dlrData, true);

            if (empty($data)) {
                Log::warning("updateSmsgLogWithDlr: Invalid or empty DLR data", [
                    'raw' => $dlrData
                ]);
                return;
            }

            // Log structured DLR data
            Log::info("updateSmsgLogWithDlr ", $data);

            $deliveryStatus = $this->mapDlrStatusForSmsgLog($data['status'] ?? '');
            $deliveryTime = isset($data['done_date'])
                ? Carbon::parse($data['done_date'])->format('YmdHi')
                : Carbon::now()->format('YmdHi');

            // Use bigid for direct update if available
            if (!empty($data['bigid'])) {
                // Direct update using bigid
                $this->updateSmsgLogByBigidnew($data['bigid'], $deliveryStatus, $deliveryTime, $data);
            } else if (!empty($data['message_id'])) {
                // Try to find the message by message_id first
                $existingRecord = null;

                // Check if we have queue_id
                if (!empty($data['queue_id'])) {
                    $existingRecord = DB::table('sms_queue')
                        ->where('queue_id', $data['queue_id'])
                        ->first();
                }

                // If not found by queue_id, try by message_id
                if (!$existingRecord) {
                    $existingRecord = DB::table('sms_queue')
                        ->where('message_id', $data['message_id'])
                        ->first();
                }

                // If still not found, try to find in smsg_log directly
                if (!$existingRecord) {
                    // Extract phone number from source/destination
                    // Note: In DLR, source is usually the recipient number
                    $phoneNumber = $data['source'] ?? '';
                    if (!$phoneNumber || $phoneNumber == 'MYBRANDNAME') {
                        $phoneNumber = $data['destination'] ?? '';
                    }
                    // Clean phone number
                    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

                    // First try to find by message_id in suppliermsgref
                    $smsgLogRecord = DB::table('smsg_log')
                        ->where('mobnum', $data['source'])
                        ->where('sentstatus', 'ok')
                        ->orderBy('id', 'desc')
                        ->first();
                    Log::info("SMSG Log Record by suppliermsgref ", ['record' => $smsgLogRecord]);
                    // If not found and we have a phone number, try by phone
                    if (!$smsgLogRecord && $phoneNumber) {
                        $smsgLogRecord = DB::table('smsg_log')
                            ->where('mobnum', $phoneNumber)
                            ->where('sentstatus', 'ok')
                            // ->whereNull('deliverystatus2')
                            ->orderBy('id', 'desc')
                            ->first();
                    }

                    if ($smsgLogRecord) {
                        // Calculate pricing and all required fields for direct match
                        $updateData = $this->calculateDlrUpdateData($smsgLogRecord, $deliveryStatus, $deliveryTime, $data);
                        $updateData['timesent'] = Carbon::now()->format('YmdHis');
                        // Perform update with all required fields
                        DB::table('smsg_log')
                            ->where('id', $smsgLogRecord->id)
                            ->update($updateData);

                        // Update user wallet if delivered
                        if ($deliveryStatus && isset($updateData['userprice'])) {
                            $user = DB::table('users')
                                ->where('bigid', $smsgLogRecord->userref)
                                ->first();

                            if ($user) {
                                DB::table('users')
                                    ->where('bigid', $user->bigid)
                                    ->increment('smsg_server1_sent', $updateData['userprice']);
                            }
                        }

                        // Handle DLR push callback
                        $this->processDlrPushCallback($smsgLogRecord, $deliveryStatus, $updateData);

                        Log::info("Updated smsg_log with DLR (direct match) - with all fields", [
                            'phone' => $phoneNumber,
                            'status' => $deliveryStatus,
                            'message_id' => $data['message_id'],
                            'updateData' => $updateData
                        ]);
                        return;
                    }

                    Log::warning("Could not find matching record for DLR", [
                        'message_id' => $data['message_id'],
                        'phone' => $phoneNumber
                    ]);
                    return;
                }
                if ($existingRecord) {
                    $metaData = json_decode($existingRecord->metadata, true);
                    $updateData = [
                        'deliverystatus1'    => 'acked',
                        'deliverystatus2'    => $deliveryStatus,
                        'deliverytime1'      => $deliveryTime,
                        'deliverytime2'      => Carbon::now()->format('YmdHis'),
                        'aggregator_dlrcode' => $data['error_code'] ?? 0,
                        'aggregator_dlrmsg'  => $data['status_text'] ?? $data['status'] ?? '',
                        'timesent' => Carbon::now()->format('YmdHis')

                    ];
                    $existingRecordsmsgLogs = DB::table('smsg_log')
                        ->where('bigid',  $metaData['bigid'])
                        ->first();
                    // Copy delivery_receipt1 to delivery_receipt2 if exists
                    if ($existingRecordsmsgLogs && !empty($existingRecordsmsgLogs->deliveryreceipt1)) {
                        $updateData['deliveryreceipt2'] = $existingRecordsmsgLogs->deliveryreceipt1;
                    }

                    $thecountrycodes = $this->extractCountryCode($existingRecordsmsgLogs->mobnum);
                    Log::info("thecountrycodes : " . $thecountrycodes);
                    Log::info("thecountrycodes Mobilenumber: " . $existingRecordsmsgLogs->mobnum);
                    $userBigId = $existingRecordsmsgLogs->userref;
                    $getUserId = DB::table('users')
                        ->where('bigid',  $userBigId)
                        ->first();
                    Log::info("thecountrycodes User id: " . $getUserId->id);
                    $getCostPersms = DB::table('country')
                        ->where('dialcode',  $thecountrycodes)
                        ->first();
                    $getRatePerSMS = DB::table('user_cost')
                        ->where('bigid',  $getUserId->id)
                        ->where('country_id',  $getCostPersms->id)
                        ->where('status',  1)
                        ->first();

                    // Log::info("thecountrycodes getRatePerSMS : " . $getRatePerSMS->rate);
                    // if ($getRatePerSMS) {
                    //     $ratePerSMS = $getRatePerSMS->rate;
                    // } else {
                    //     $ratePerSMS = $getUserId->common_sms_rate; //default rate
                    // }
                    if ($getRatePerSMS) {
                        Log::info("thecountrycodes getRatePerSMS : " . $getRatePerSMS->rate);
                        $ratePerSMS = $getRatePerSMS->rate;
                    } else {
                        Log::warning("No matching rate found in user_cost table for user_id: {$getUserId->id}, country_id: {$getCostPersms->id}. Using default rate.");
                        $ratePerSMS = $getUserId->common_sms_rate; // default rate
                    }

                    Log::info("thecountrycodes ratePerSMS : " . $ratePerSMS);
                    if ($ratePerSMS) {
                        $updateData['userprice'] = $ratePerSMS;
                    } else {
                        $updateData['userprice'] = 0.050; //default rate
                    }

                    Log::info("thecountrycodes getCostPersms : " . $getCostPersms->cost_per_sms);
                    if ($getCostPersms) {
                        $updateData['costprice'] = $getCostPersms->cost_per_sms;
                    } else {
                        $updateData['costprice'] = 0.040; //default cost
                    }
                    Log::info("thecountrycodes updateData : ", $updateData);
                    $updateData['profit'] = $updateData['userprice'] - $updateData['costprice'];

                    $updateData['countrydialcode'] = $thecountrycodes;
                    $updateData['timesent'] = Carbon::now()->format('YmdHis');
                    // Perform update
                    $updated = DB::table('smsg_log')
                        ->where('bigid',  $metaData['bigid'])
                        ->update($updateData);

                    $bigId = $getUserId->bigid;
                    $smsgServer1Sent = $getUserId->smsg_server1_sent + $updateData['userprice'];
                    // Increment user's total_credits_sent
                    DB::table('users')
                        ->where('bigid', $bigId)
                        ->update(['smsg_server1_sent' => $smsgServer1Sent]);


                    $dreceiptUrlDetails = DB::table('useroption')
                        ->select('dreceipt_push_url', 'dreceipt_tries_num', 'dreceipt_retries_wait_mins', 'dlr_daemon_id', 'apitype')
                        ->where('userref',  $bigId)
                        ->first();
                    Log::info("Updated smsg_log with DLR", [
                        'data' => $dreceiptUrlDetails
                    ]);
                    if (
                        $dreceiptUrlDetails &&
                        strlen($dreceiptUrlDetails->dreceipt_push_url) > 10 &&
                        $dreceiptUrlDetails->dreceipt_tries_num > 0 &&
                        intval($dreceiptUrlDetails->dreceipt_retries_wait_mins) >= 0
                    ) {
                        $time = Carbon::now()->format('YmdHis');

                        $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                            <itagg_delivery_receipt>
                                <version>1.1</version>
                                <msisdn>{$existingRecordsmsgLogs->mobnum}</msisdn>
                                <submission_ref>{$updateData['deliveryreceipt2']}</submission_ref>
                                <status>{$deliveryStatus}</status>
                                <reason>4</reason>
                                <gmt_timestamp>{$time}</gmt_timestamp>
                                <retry>0</retry>
                            </itagg_delivery_receipt>";

                        DB::table('delivery_receipt_push_log')->insert([
                            'thismsgreference' => $updateData['deliveryreceipt2'],
                            'msisdn'           => $existingRecordsmsgLogs->mobnum,
                            'smsg_log_bigid'   => $metaData['bigid'],
                            'users_bigid'      => $bigId,
                            'timestamp'        => $time,
                            'status'           => 'new',
                            'message_status'   => $deliveryStatus,
                            'reason'           => '4',
                            'url'              => $dreceiptUrlDetails->dreceipt_push_url,
                            'inserted_time'    => Carbon::now()->format('YmdHis'),
                            'retries_left'     => $dreceiptUrlDetails->dreceipt_tries_num,
                            'wait_minutes'     => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                            'dosendtime'       => Carbon::now()->format('Y-m-d H:i:s'),
                            'xml'              => $itagg_receipt_xml,
                            'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                            'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
                        ]);

                        Log::info("Inserted into delivery_receipt_push_log for DLR push ", [
                            'message_id' => $updateData['deliveryreceipt2'],
                            'url'        => $dreceiptUrlDetails->dreceipt_push_url
                        ]);
                    }


                    Log::info("Updated smsg_log with DLR", [
                        'message_id' => $metaData['smsg_log_id'],
                        'status'     => $deliveryStatus,
                        'updated'    => $updated
                    ]);
                } else {
                    Log::warning("sms_queue not found : ", ['data' => $data]);
                }
            } else {
                Log::warning("DLR data does not contain message_id", ['data' => $data]);
            }
        } catch (Exception $e) {
            Log::warning("Failed to update smsg_log with DLR: " . $e->getMessage(), [
                'raw' => $dlrData
            ]);
        }
    }


    /**
     * Store message ID mapping for DLR matching
     * @param string $messageId SMPP message ID from carrier
     * @param string $queueId Queue ID for tracking
     * @param string $referenceId Reference ID (bigid) for DLR matching
     * @param string $mobile Recipient phone number
     * @param string $countryCode Country code
     * @param string $initiator Who initiated the message
     * @param string $deliveryReceipt1 Internal delivery receipt ID
     * @param string $supplierMsgRef Supplier message reference
     */

    private function updateSmsgLogByBigidnew($bigid, $deliveryStatus, $deliveryTime, $dlrData)
    {
        try {
            // Get the smsg_log record
            $smsgLog = DB::table('smsg_log')
                ->where('id', $dlrData['message_id'])
                ->first();

            if (!$smsgLog) {
                Log::warning("No smsg_log found for bigid: {$bigid}");
                return false;
            }
     

            // Prepare update data
            $updateData = [
                'deliverystatus1'    => 'acked',
                'deliverystatus2'    => $deliveryStatus,
                'deliverytime1'      => $deliveryTime,
                'deliverytime2'      => Carbon::now()->format('YmdHis'),
                'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                'aggregator_dlrmsg'  => $dlrData['status_text'] ?? $dlrData['status'] ?? '',
                'timesent' => Carbon::now()->format('YmdHis')
            ];

            // Copy delivery_receipt1 to delivery_receipt2 if exists
            if (!empty($smsgLog->deliveryreceipt1)) {
                $updateData['deliveryreceipt2'] = $smsgLog->deliveryreceipt1;
            }

            // Extract country code and calculate pricing
            $thecountrycodes = $this->extractCountryCode($smsgLog->mobnum);
            Log::info("thecountrycodes: " . $thecountrycodes);
            Log::info("thecountrycodes Mobile number: " . $smsgLog->mobnum);

            $userBigId = $smsgLog->userref;
            $getUserId = DB::table('users')
                ->where('bigid', $userBigId)
                ->first();

            if (!$getUserId) {
                Log::warning("User not found for bigid", ['bigid' => $userBigId]);
                return false;
            }

            Log::info("thecountrycodes User id: " . $getUserId->id);

            // Get country cost
            $getCostPersms = DB::table('country')
                ->where('dialcode', $thecountrycodes)
                ->first();

            // Get user rate
            $getRatePerSMS = null;
            if ($getCostPersms) {
                $getRatePerSMS = DB::table('user_cost')
                    ->where('bigid', $getUserId->id)
                    ->where('country_id', $getCostPersms->id)
                    ->where('status',  1)
                    ->first();
            }

            // Determine rate per SMS
            if ($getRatePerSMS && isset($getRatePerSMS->rate)) {
                $ratePerSMS = $getRatePerSMS->rate;
                Log::info("thecountrycodes getRatePerSMS: " . $ratePerSMS);
            } else if (isset($getUserId->common_sms_rate)) {
                $ratePerSMS = $getUserId->common_sms_rate;
                Log::info("thecountrycodes using common_sms_rate: " . $ratePerSMS);
            } else {
                $ratePerSMS = 0.050; // default rate
                Log::info("thecountrycodes using default rate: " . $ratePerSMS);
            }

            Log::info("thecountrycodes ratePerSMS: " . $ratePerSMS);
            $updateData['userprice'] = $ratePerSMS;

            // Determine cost price
            if ($getCostPersms && isset($getCostPersms->cost_per_sms)) {
                $updateData['costprice'] = $getCostPersms->cost_per_sms;
                Log::info("thecountrycodes getCostPersms: " . $getCostPersms->cost_per_sms);
            } else {
                $updateData['costprice'] = 0.040; // default cost
                Log::info("thecountrycodes using default cost: 0.040");
            }

            // Calculate profit
            $updateData['profit'] = $updateData['userprice'] - $updateData['costprice'];
            $updateData['countrydialcode'] = $thecountrycodes;
            $updateData['timesent'] = Carbon::now()->format('YmdHis');

            Log::info("thecountrycodes updateData: ", $updateData);

            // Update the smsg_log record
            $updated = DB::table('smsg_log')
                ->where('id', $smsgLog->id)
                ->update($updateData);

            // Increment user's smsg_server1_sent
            $bigIdUser = $getUserId->bigid;
            DB::table('users')
                ->where('bigid', $bigIdUser)
                ->increment('smsg_server1_sent', $updateData['userprice']);

            // Handle DLR push callback
            $dreceiptUrlDetails = DB::table('useroption')
                ->select('dreceipt_push_url', 'dreceipt_tries_num', 'dreceipt_retries_wait_mins', 'dlr_daemon_id', 'apitype')
                ->where('userref', $bigIdUser)
                ->first();

            Log::info("Updated smsg_log with DLR", [
                'data' => $dreceiptUrlDetails
            ]);

            if (
                $dreceiptUrlDetails &&
                !empty($dreceiptUrlDetails->dreceipt_push_url) &&
                strlen($dreceiptUrlDetails->dreceipt_push_url) > 10 &&
                $dreceiptUrlDetails->dreceipt_tries_num > 0 &&
                intval($dreceiptUrlDetails->dreceipt_retries_wait_mins) >= 0
            ) {
                $time = Carbon::now()->format('YmdHis');

                $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                <itagg_delivery_receipt>
                    <version>1.1</version>
                    <msisdn>{$smsgLog->mobnum}</msisdn>
                    <submission_ref>{$updateData['deliveryreceipt2']}</submission_ref>
                    <status>{$deliveryStatus}</status>
                    <reason>4</reason>
                    <gmt_timestamp>{$time}</gmt_timestamp>
                    <retry>0</retry>
                </itagg_delivery_receipt>";

                DB::table('delivery_receipt_push_log')->insert([
                    'thismsgreference' => $updateData['deliveryreceipt2'],
                    'msisdn'           => $smsgLog->mobnum,
                    'smsg_log_bigid'   => $bigid,
                    'users_bigid'      => $bigIdUser,
                    'timestamp'        => $time,
                    'status'           => 'new',
                    'message_status'   => $deliveryStatus,
                    'reason'           => '4',
                    'url'              => $dreceiptUrlDetails->dreceipt_push_url,
                    'inserted_time'    => Carbon::now()->format('YmdHis'),
                    'retries_left'     => $dreceiptUrlDetails->dreceipt_tries_num,
                    'wait_minutes'     => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                    'dosendtime'       => Carbon::now()->format('Y-m-d H:i:s'),
                    'xml'              => $itagg_receipt_xml,
                    'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                    'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
                ]);

                Log::info("Inserted into delivery_receipt_push_log for DLR push", [
                    'message_id' => $updateData['deliveryreceipt2'],
                    'url'        => $dreceiptUrlDetails->dreceipt_push_url
                ]);
            }

            Log::info("Updated smsg_log with DLR by bigid", [
                'bigid' => $bigid,
                'status' => $deliveryStatus,
                'smsg_log_id' => $smsgLog->id,
                'updated' => $updated,
                'updateData' => $updateData
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to update smsg_log by bigid: " . $e->getMessage(), [
                'bigid' => $bigid,
                'dlr_data' => $dlrData,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    private function storeMessageIdMapping($messageId, $queueId, $referenceId, $mobile, $countryCode, $initiator, $deliveryReceipt1, $supplierMsgRef)
    {
        try {
            // Store in cache or database for DLR matching
            if (!empty($queueId)) {
                DB::table('sms_queue')
                    ->where('queue_id', $queueId)
                    ->update([
                        'message_id' => $messageId,
                        'updated_at' => Carbon::now()
                    ]);
                $existingRecord = DB::table('sms_queue')
                    ->where('queue_id', $queueId)
                    ->first();

                $metaData = json_decode($existingRecord->metadata, true);

                // Use reference ID if available, otherwise fallback to metadata bigid
                $bigidToUpdate = $referenceId ?: ($metaData['bigid'] ?? null);

                // Store the SMPP message ID mapping for DLR lookup
                if ($bigidToUpdate) {
                    // Store in a mapping table for quick DLR lookup
                    DB::table('smpp_message_mapping')->insertOrIgnore([
                        'message_id' => $messageId,
                        'bigid' => $bigidToUpdate,
                        'queue_id' => $queueId,
                        'mobile_number' => $mobile,
                        'created_at' => Carbon::now()
                    ]);
                }
                $smsgLog = DB::table('smsg_log')
                    ->where('bigid', $bigidToUpdate)
                    ->first();
                $user = DB::table('users')
                    ->where('bigid', $smsgLog->userref)
                    ->first();
                $country = DB::table('country')
                    ->where('dialcode', $countryCode)
                    ->first();
                $userRate = null;
                $costPrice = 0.040; // Default cost

                // Get user specific rate if country exists
                if ($country) {
                    $userCost = DB::table('user_cost')
                        ->where('bigid', $user->id)
                        ->where('country_id', $country->id)
                        ->where('status',  1)
                        ->first();

                    if ($userCost) {
                        $userRate = $userCost->rate;
                    }

                    // Use country's cost price if available
                    $costPrice = $country->cost_per_sms ?: 0.040;
                }
                $finalRate = $userRate ?: ($user->common_sms_rate ?: 0.050);
                $profit = $finalRate - $costPrice;

                // Also update smsg_log with all required fields
                DB::table('smsg_log')
                    ->where('bigid', $bigidToUpdate)
                    // ->where('sentstatus', 'pending')
                    // ->orderBy('id', 'desc')
                    // ->limit(1)
                    ->update([
                        'suppliermsgref' => $supplierMsgRef, // Store the actual SMPP message ID
                        'deliveryreceipt1' => $messageId,
                        'sentstatus' => 'ok',
                        'sentstatustext' => 'Message sent successfully',
                        // 'timesent' => Carbon::now()->format('YmdHis'),
                        'countrydialcode' => $countryCode,
                        'timesent' => Carbon::now()->format('YmdHis'),
                        // 'initiator' => $initiator,
                        'deliverystatus1' => 'acked',
                        'deliverytime1' => Carbon::now()->format('YmdHi'),
                        'deliveryreceipt2' => $messageId,
                        'userprice' => $finalRate,
                        'costprice' => $costPrice,
                        'profit' => $profit,
                        // 'aggregator_msgid' => $messageId // Also store in aggregator_msgid field if it exists
                    ]);

                Log::info("Message ID mapping stored", [
                    'message_id' => $messageId,
                    'supplier_msg_ref' => $supplierMsgRef,
                    'delivery_receipt1' => $deliveryReceipt1,
                    'mobile' => $mobile,
                    'country_code' => $countryCode,
                    // 'initiator' => $initiator
                ]);
            }
        } catch (Exception $e) {
            Log::warning("Failed to store message ID mapping: " . $e->getMessage());
        }
    }

    /**
     * Find message by message ID
     */
    private function findMessageByMessageId($messageId)
    {
        if (empty($messageId)) {
            return null;
        }

        try {
            // First check the SMPP message mapping table for quick lookup
            $mapping = DB::table('smpp_message_mapping')
                ->where('message_id', $messageId)
                ->first();

            if ($mapping) {
                return [
                    'queue_id' => $mapping->queue_id,
                    'bigid' => $mapping->bigid,
                    'mobile_number' => $mapping->mobile_number
                ];
            }

            // Try to find by message_id in sms_queue
            $message = DB::table('sms_queue')
                ->where('message_id', $messageId)
                ->first();

            if ($message) {
                $metadata = json_decode($message->metadata, true);
                return [
                    'queue_id' => $message->queue_id,
                    'bigid' => $metadata['bigid'] ?? null,
                    'mobile_number' => $message->mobile_number
                ];
            }

            // Try smsg_log table by supplier reference
            $smsgLog = DB::table('smsg_log')
                ->where('suppliermsgref', $messageId)
                ->orWhere('deliveryreceipt1', $messageId)
                ->first();

            if ($smsgLog) {
                return [
                    'queue_id' => null,
                    'bigid' => $smsgLog->bigid,
                    'mobile_number' => $smsgLog->mobnum
                ];
            }
        } catch (Exception $e) {
            Log::warning("Failed to find message by ID: " . $e->getMessage());
        }

        return null;
    }

    // ... [Rest of the methods remain the same] ...

    /**
     * Handle enquire_link request
     */
    private function handleEnquireLink($pdu)
    {
        // Send enquire_link_resp
        $respPdu = $this->buildPDU(self::ENQUIRE_LINK_RESP, '', 0, $pdu['sequence_number']);
        $this->sendPDU($respPdu);
        $this->logPDU('RESPONSE', self::ENQUIRE_LINK_RESP, 0, $pdu['sequence_number']);
        Log::debug("Responded to enquire_link");
    }

    /**
     * Send deliver_sm_resp
     */
    private function sendDeliverSmResp($sequenceNumber, $status = 0)
    {
        if (!$this->isSocketValid()) {
            return;
        }

        try {
            $body = pack('C', 0); // message_id (null)
            $pdu = $this->buildPDU(self::DELIVER_SM_RESP, $body, $status, $sequenceNumber);
            $this->sendPDU($pdu);
            $this->logPDU('RESPONSE', self::DELIVER_SM_RESP, $status, $sequenceNumber);
        } catch (Exception $e) {
            Log::error("Failed to send deliver_sm_resp: " . $e->getMessage());
        }
    }

    /**
     * Read C-style string from buffer
     */
    private function readCString($buffer, &$pos)
    {
        $nullPos = strpos($buffer, chr(0), $pos);
        if ($nullPos === false) {
            $str = substr($buffer, $pos);
            $pos = strlen($buffer);
        } else {
            $str = substr($buffer, $pos, $nullPos - $pos);
            $pos = $nullPos + 1;
        }
        return $str;
    }

    /**
     * Parse SMPP date format
     */
    private function parseSmppDate($dateStr)
    {
        // Format: YYMMDDhhmm or YYMMDDhhmmss
        if (strlen($dateStr) >= 10) {
            $year = '20' . substr($dateStr, 0, 2);
            $month = substr($dateStr, 2, 2);
            $day = substr($dateStr, 4, 2);
            $hour = substr($dateStr, 6, 2);
            $minute = substr($dateStr, 8, 2);
            $second = strlen($dateStr) >= 12 ? substr($dateStr, 10, 2) : '00';

            try {
                return Carbon::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $hour:$minute:$second");
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Map DLR status to internal status
     */
    private function mapDlrStatus($dlrStatus)
    {
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Expired',
            'DELETED' => 'Deleted',
            'UNDELIV' => 'Undeliverable',
            'ACCEPTD' => 'Accepted',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Rejected',
            'SKIPPED' => 'Skipped'
        ];

        return $statusMap[$dlrStatus] ?? 'Unknown';
    }

    /**
     * Map DLR status to queue status
     */
    private function mapDlrStatusToQueueStatus($dlrStatus)
    {
        $statusMap = [
            'DELIVRD' => 'delivered',
            'EXPIRED' => 'failed',
            'DELETED' => 'failed',
            'UNDELIV' => 'failed',
            'ACCEPTD' => 'sent',
            'UNKNOWN' => 'unknown',
            'REJECTD' => 'failed',
            'SKIPPED' => 'failed'
        ];

        return $statusMap[$dlrStatus] ?? 'unknown';
    }

    /**
     * Map DLR status for smsg_log table
     */
    private function mapDlrStatusForSmsgLog($dlrStatus)
    {
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Expired',
            'DELETED' => 'Deleted',
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'Accepted',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Rejected',
            'SKIPPED' => 'Skipped'
        ];

        return $statusMap[$dlrStatus] ?? 'Unknown';
    }

    /**
     * Calculate all required fields for DLR update
     */
    private function calculateDlrUpdateData($smsgLogRecord, $deliveryStatus, $deliveryTime, $dlrData)
    {
        try {
            // Extract country code
            $countryCode = $this->extractCountryCode($smsgLogRecord->mobnum);

            // Get user information
            $user = DB::table('users')
                ->where('bigid', $smsgLogRecord->userref)
                ->first();

            if (!$user) {
                Log::warning("User not found for pricing calculation", [
                    'userref' => $smsgLogRecord->userref
                ]);
                // Return basic update data without pricing
                return [
                    'deliverystatus1' => 'acked',
                    'deliverystatus2' => $deliveryStatus,
                    'deliverytime1' => $deliveryTime,
                    'deliverytime2' => Carbon::now()->format('YmdHis'),
                    'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                    'aggregator_dlrmsg' => $dlrData['status_text'] ?? $dlrData['status'] ?? '',
                    'countrydialcode' => $countryCode,
                    'timesent' => Carbon::now()->format('YmdHis')
                ];
            }

            // Get country cost information
            $country = DB::table('country')
                ->where('dialcode', $countryCode)
                ->first();

            // Initialize pricing variables
            $userRate = null;
            $costPrice = 0.040; // Default cost

            // Get user specific rate if country exists
            if ($country) {
                $userCost = DB::table('user_cost')
                    ->where('bigid', $user->id)
                    ->where('country_id', $country->id)
                    ->where('status',  1)
                    ->first();

                if ($userCost) {
                    $userRate = $userCost->rate;
                }

                // Use country's cost price if available
                $costPrice = $country->cost_per_sms ?: 0.040;
            }

            // Use user's common rate if no specific rate found
            $finalRate = $userRate ?: ($user->common_sms_rate ?: 0.050);
            $profit = $finalRate - $costPrice;

            Log::info("DLR Pricing Calculated", [
                'mobile' => $smsgLogRecord->mobnum,
                'country_code' => $countryCode,
                'user_rate' => $finalRate,
                'cost_price' => $costPrice,
                'profit' => $profit
            ]);

            // Prepare complete update data
            $updateData = [
                'deliverystatus1' => 'acked',
                'deliverystatus2' => $deliveryStatus,
                'deliverytime1' => $deliveryTime,
                'deliverytime2' => Carbon::now()->format('YmdHis'),
                'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                'aggregator_dlrmsg' => $dlrData['status_text'] ?? $dlrData['status'] ?? '',
                'userprice' => $finalRate,
                'costprice' => $costPrice,
                'profit' => $profit,
                'countrydialcode' => $countryCode,
                'timesent' => Carbon::now()->format('YmdHis'),
            ];

            // Copy delivery receipt if exists
            if (!empty($smsgLogRecord->deliveryreceipt1)) {
                $updateData['deliveryreceipt2'] = $smsgLogRecord->deliveryreceipt1;
            }

            return $updateData;
        } catch (Exception $e) {
            Log::error("Error calculating DLR update data: " . $e->getMessage());

            // Return basic update data on error
            return [
                'deliverystatus1' => 'acked',
                'deliverystatus2' => $deliveryStatus,
                'deliverytime1' => $deliveryTime,
                'deliverytime2' => Carbon::now()->format('YmdHis'),
                'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                'aggregator_dlrmsg' => $dlrData['status_text'] ?? $dlrData['status'] ?? '',
                'timesent' => Carbon::now()->format('YmdHis'),
            ];
        }
    }

    /**
     * Process DLR push callback for webhook notifications
     */
    private function processDlrPushCallback($smsgLogRecord, $deliveryStatus, $updateData)
    {
        try {
            $user = DB::table('users')
                ->where('bigid', $smsgLogRecord->userref)
                ->first();

            if (!$user) {
                return;
            }

            $dreceiptUrlDetails = DB::table('useroption')
                ->select('dreceipt_push_url', 'dreceipt_tries_num', 'dreceipt_retries_wait_mins', 'dlr_daemon_id', 'apitype')
                ->where('userref', $user->bigid)
                ->first();

            if (
                !$dreceiptUrlDetails ||
                strlen($dreceiptUrlDetails->dreceipt_push_url) <= 10 ||
                $dreceiptUrlDetails->dreceipt_tries_num <= 0
            ) {
                return;
            }

            $time = Carbon::now()->format('YmdHis');
            $deliveryReceipt = $updateData['deliveryreceipt2'] ?? $smsgLogRecord->deliveryreceipt1 ?? '';

            $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                    <itagg_delivery_receipt>
                        <version>1.1</version>
                        <msisdn>{$smsgLogRecord->mobnum}</msisdn>
                        <submission_ref>{$deliveryReceipt}</submission_ref>
                        <status>{$deliveryStatus}</status>
                        <reason>4</reason>
                        <gmt_timestamp>{$time}</gmt_timestamp>
                        <retry>0</retry>
                    </itagg_delivery_receipt>";

            DB::table('delivery_receipt_push_log')->insert([
                'thismsgreference' => $deliveryReceipt,
                'msisdn' => $smsgLogRecord->mobnum,
                'smsg_log_bigid' => $smsgLogRecord->bigid,
                'users_bigid' => $user->bigid,
                'timestamp' => $time,
                'status' => 'new',
                'message_status' => $deliveryStatus,
                'reason' => '4',
                'url' => $dreceiptUrlDetails->dreceipt_push_url,
                'inserted_time' => Carbon::now()->format('YmdHis'),
                'retries_left' => $dreceiptUrlDetails->dreceipt_tries_num,
                'wait_minutes' => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                'xml' => $itagg_receipt_xml,
                'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
            ]);

            Log::info("DLR push callback queued", [
                'bigid' => $smsgLogRecord->bigid,
                'url' => $dreceiptUrlDetails->dreceipt_push_url
            ]);
        } catch (Exception $e) {
            Log::warning("Failed to queue DLR push callback: " . $e->getMessage());
        }
    }

    /**
     * Update smsg_log directly by bigid
     */
    private function updateSmsgLogByBigid($bigid, $deliveryStatus, $deliveryTime, $dlrData)
    {
        try {
            // Get the smsg_log record - prioritize records without deliverystatus2
            $smsgLog = DB::table('smsg_log')
                ->where('bigid', $bigid)
                ->where('sentstatus', 'ok')
                // ->whereNull('deliverystatus2')
                ->orderBy('id', 'desc')
                ->first();

            // If not found, try to get the most recent one with sentstatus = 'ok'
            if (!$smsgLog) {
                $smsgLog = DB::table('smsg_log')
                    ->where('bigid', $bigid)
                    ->where('sentstatus', 'ok')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$smsgLog) {
                Log::warning("No smsg_log found for bigid: {$bigid}");
                return false;
            }

            // Check if already has delivery status
            if (!empty($smsgLog->deliverystatus2)) {
                Log::warning("smsg_log already has delivery status, possible duplicate DLR", [
                    'bigid' => $bigid,
                    'existing_status' => $smsgLog->deliverystatus2,
                    'new_status' => $deliveryStatus,
                    'smsg_log_id' => $smsgLog->id
                ]);
            }

            // Use the common method to calculate all update data
            $updateData = $this->calculateDlrUpdateData($smsgLog, $deliveryStatus, $deliveryTime, $dlrData);

            // Update the record
            DB::table('smsg_log')
                ->where('id', $smsgLog->id)
                ->update($updateData);

            // Update user wallet if delivered
            if ($deliveryStatus && isset($updateData['userprice'])) {
                $user = DB::table('users')
                    ->where('bigid', $smsgLog->userref)
                    ->first();

                if ($user) {
                    DB::table('users')
                        ->where('bigid', $user->bigid)
                        ->increment('smsg_server1_sent', $updateData['userprice']);
                }
            }

            // Handle DLR push callback
            $this->processDlrPushCallback($smsgLog, $deliveryStatus, $updateData);

            Log::info("Updated smsg_log with DLR by bigid", [
                'bigid' => $bigid,
                'status' => $deliveryStatus,
                'smsg_log_id' => $smsgLog->id,
                'updateData' => $updateData
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to update smsg_log by bigid: " . $e->getMessage(), [
                'bigid' => $bigid,
                'dlr_data' => $dlrData
            ]);
            return false;
        }
    }

    /**
     * Handle DLR push callback
     */
    private function handleDlrPushCallback($smsgLog, $userBigId, $deliveryStatus, $updateData)
    {
        try {
            $dreceiptUrlDetails = DB::table('useroption')
                ->select('dreceipt_push_url', 'dreceipt_tries_num', 'dreceipt_retries_wait_mins', 'dlr_daemon_id', 'apitype')
                ->where('userref', $userBigId)
                ->first();

            if (
                $dreceiptUrlDetails &&
                strlen($dreceiptUrlDetails->dreceipt_push_url) > 10 &&
                $dreceiptUrlDetails->dreceipt_tries_num > 0 &&
                intval($dreceiptUrlDetails->dreceipt_retries_wait_mins) >= 0
            ) {
                $time = Carbon::now()->format('YmdHis');
                $deliveryReceipt = $updateData['deliveryreceipt2'] ?? $smsgLog->deliveryreceipt1 ?? '';

                $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                        <itagg_delivery_receipt>
                            <version>1.1</version>
                            <msisdn>{$smsgLog->mobnum}</msisdn>
                            <submission_ref>{$deliveryReceipt}</submission_ref>
                            <status>{$deliveryStatus}</status>
                            <reason>4</reason>
                            <gmt_timestamp>{$time}</gmt_timestamp>
                            <retry>0</retry>
                        </itagg_delivery_receipt>";

                DB::table('delivery_receipt_push_log')->insert([
                    'thismsgreference' => $deliveryReceipt,
                    'msisdn' => $smsgLog->mobnum,
                    'smsg_log_bigid' => $smsgLog->bigid,
                    'users_bigid' => $userBigId,
                    'timestamp' => $time,
                    'status' => 'new',
                    'message_status' => $deliveryStatus,
                    'reason' => '4',
                    'url' => $dreceiptUrlDetails->dreceipt_push_url,
                    'inserted_time' => Carbon::now()->format('YmdHis'),
                    'retries_left' => $dreceiptUrlDetails->dreceipt_tries_num,
                    'wait_minutes' => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                    'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'xml' => $itagg_receipt_xml,
                    'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                    'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
                ]);

                Log::info("DLR push callback queued", [
                    'bigid' => $smsgLog->bigid,
                    'url' => $dreceiptUrlDetails->dreceipt_push_url
                ]);
            }
        } catch (Exception $e) {
            Log::warning("Failed to queue DLR push callback: " . $e->getMessage());
        }
    }

    /**
     * Keep connection alive
     */
    public function enquireLink()
    {
        if (!$this->isSocketValid() || !$this->connected || !$this->bound) {
            return false;
        }

        try {
            $sequenceNum = $this->sequenceNumber++;
            $pdu = $this->buildPDU(self::ENQUIRE_LINK, '', 0, $sequenceNum);
            $this->logPDU('REQUEST', self::ENQUIRE_LINK, 0, $sequenceNum);
            $this->sendPDU($pdu);

            $response = $this->readPDU(true);

            if ($response && $response['command_id'] == self::ENQUIRE_LINK_RESP) {
                $this->logPDU(
                    'RESPONSE',
                    $response['command_id'],
                    $response['command_status'],
                    $response['sequence_number']
                );
                $this->lastActivity = Carbon::now();
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error("Enquire link failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Disconnect from SMPP server
     */
    public function disconnect()
    {
        try {
            if ($this->isSocketValid() && $this->bound) {
                try {
                    $sequenceNum = $this->sequenceNumber++;
                    $pdu = $this->buildPDU(self::UNBIND, '', 0, $sequenceNum);
                    $this->logPDU('REQUEST', self::UNBIND, 0, $sequenceNum);
                    $this->sendPDU($pdu);
                    // Try to read unbind_resp with short timeout
                    @stream_set_timeout($this->socket, 2);
                    $response = $this->readPDU(true);
                    if ($response) {
                        $this->logPDU(
                            'RESPONSE',
                            $response['command_id'],
                            $response['command_status'],
                            $response['sequence_number']
                        );
                    }
                } catch (Exception $e) {
                    // Ignore unbind errors
                    Log::debug("Unbind error (ignored): " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            // Ignore disconnect errors
        }

        // Close socket
        if ($this->isSocketValid()) {
            @fclose($this->socket);
        }

        $this->socket = null;
        $this->connected = false;
        $this->bound = false;

        $this->updateConnectionStatus('disconnected');

        Log::info("Disconnected from SMPP server");
    }

    /**
     * Build PDU
     */
    private function buildPDU($commandId, $body, $commandStatus = 0, $sequenceNumber = null)
    {
        if ($sequenceNumber === null) {
            $sequenceNumber = $this->sequenceNumber++;
        }

        $header = pack(
            'NNNN',
            16 + strlen($body), // command_length
            $commandId,         // command_id
            $commandStatus,     // command_status
            $sequenceNumber     // sequence_number
        );

        return $header . $body;
    }

    /**
     * Send PDU
     */
    private function sendPDU($pdu)
    {
        if (!$this->isSocketValid()) {
            throw new Exception("Not connected to SMPP server");
        }

        $written = @fwrite($this->socket, $pdu);

        if ($written === false || $written < strlen($pdu)) {
            $this->connected = false;
            $this->bound = false;
            throw new Exception("Failed to send PDU - connection lost");
        }

        $this->lastActivity = Carbon::now();
    }

    /**
     * Read PDU
     */
    private function readPDU($blocking = false)
    {
        if (!$this->isSocketValid()) {
            return null;
        }

        try {
            if ($blocking) {
                @stream_set_blocking($this->socket, true);
                @stream_set_timeout($this->socket, 10); // 10 second timeout for blocking reads
            } else {
                @stream_set_blocking($this->socket, false);
            }

            // Read header (16 bytes)
            $header = @fread($this->socket, 16);

            if (!$header || strlen($header) < 16) {
                return null;
            }

            $headerData = unpack('Nlength/Ncommand_id/Ncommand_status/Nsequence_number', $header);

            // Validate PDU length
            if ($headerData['length'] < 16 || $headerData['length'] > 5000) {
                Log::warning("Invalid PDU length received", ['length' => $headerData['length']]);
                return null;
            }

            // Read body
            $bodyLength = $headerData['length'] - 16;
            $body = '';

            if ($bodyLength > 0) {
                $body = @fread($this->socket, $bodyLength);
                if ($body === false || strlen($body) < $bodyLength) {
                    Log::warning("Incomplete PDU body received", [
                        'expected' => $bodyLength,
                        'received' => $body ? strlen($body) : 0
                    ]);
                    return null;
                }
            }

            $this->lastActivity = Carbon::now();

            return [
                'command_id' => $headerData['command_id'],
                'command_status' => $headerData['command_status'],
                'sequence_number' => $headerData['sequence_number'],
                'body' => $body
            ];
        } catch (Exception $e) {
            Log::error("Error reading PDU: " . $e->getMessage());
            return null;
        } finally {
            // Always reset to non-blocking for normal operations
            if ($this->isSocketValid()) {
                @stream_set_blocking($this->socket, false);
            }
        }
    }

    private function parseSubmitSmResponse($body)
    {
        $result = [
            'message_id' => null,
            'submission_price' => null,
            'remaining_balance' => null,
            'mcc_mnc' => null,
            'raw_tlv' => []
        ];

        if (empty($body)) {
            return $result;
        }

        // Extract message ID (null-terminated string at the beginning)
        $pos = 0;
        $nullPos = strpos($body, chr(0), $pos);

        if ($nullPos !== false) {
            $result['message_id'] = substr($body, $pos, $nullPos - $pos);
            $pos = $nullPos + 1;
        } else {
            $result['message_id'] = $body;
            return $result; // No TLV parameters if no null terminator
        }

        // Parse TLV parameters
        while ($pos < strlen($body) - 3) {
            // Check if we have at least 4 bytes for tag and length
            if ($pos + 4 > strlen($body)) {
                break;
            }

            // Read Tag (2 bytes, big-endian)
            $tagBytes = substr($body, $pos, 2);
            $tag = unpack('n', $tagBytes)[1];
            $pos += 2;

            // Read Length (2 bytes, big-endian)
            $lengthBytes = substr($body, $pos, 2);
            $length = unpack('n', $lengthBytes)[1];
            $pos += 2;

            // Read Value
            $value = null;
            if ($length > 0 && ($pos + $length) <= strlen($body)) {
                $valueBytes = substr($body, $pos, $length);
                $pos += $length;

                // Store raw TLV data
                $result['raw_tlv'][] = [
                    'tag' => sprintf('0x%04X', $tag),
                    'tag_decimal' => $tag,
                    'length' => $length,
                    'value_hex' => bin2hex($valueBytes),
                    'value_raw' => $valueBytes
                ];

                // Parse specific TLV tags
                switch ($tag) {
                    case self::TLV_SUBMISSION_PRICE: // 0x1422
                        $value = trim($valueBytes);
                        $result['submission_price'] = $value;
                        Log::info("SMPP TLV - Submission Price", [
                            'value' => $value,
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    case self::TLV_REMAINING_BALANCE: // 0x1423
                        $value = trim($valueBytes);
                        $result['remaining_balance'] = $value;
                        Log::info("SMPP TLV - Remaining Balance", [
                            'value' => $value,
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    case self::TLV_MCC_MNC: // 0x1402
                        $value = trim($valueBytes);
                        $result['mcc_mnc'] = $value;
                        Log::info("SMPP TLV - MCC/MNC", [
                            'value' => $value,
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    default:
                        Log::debug("SMPP TLV - Unknown Tag", [
                            'tag' => sprintf('0x%04X', $tag),
                            'length' => $length,
                            'value_hex' => bin2hex($valueBytes)
                        ]);
                }
            }
        }

        return $result;
    }
    /**
     * Extract message ID from submit_sm_resp body
     */
    private function extractMessageId($body)
    {
        if (empty($body)) {
            return null;
        }

        // Message ID is a null-terminated string
        $nullPos = strpos($body, chr(0));

        if ($nullPos === false) {
            return substr($body, 0);
        }

        return substr($body, 0, $nullPos);
    }

    /**
     * Detect data coding based on message content
     */
    private function detectDataCoding($message)
    {
        // Check if message contains non-ASCII characters
        if (preg_match('/[^\x00-\x7F]/', $message)) {
            // Check if it's valid UTF-8
            if (mb_check_encoding($message, 'UTF-8')) {
                return self::DATA_CODING_UCS2; // Use UCS2 for Unicode
            }
            return self::DATA_CODING_LATIN1; // Use Latin1 for extended ASCII
        }

        return self::DATA_CODING_DEFAULT; // Use default for ASCII
    }

    /**
     * Check TPS limit
     */
    private function checkTpsLimit()
    {
        $now = time();

        // Remove old entries (older than 1 second)
        $this->tpsCounter = array_filter($this->tpsCounter, function ($time) use ($now) {
            return $time > ($now - 1);
        });

        // Check if we're within limit
        if (count($this->tpsCounter) >= $this->tpsLimit) {
            return false;
        }

        // Add current request
        $this->tpsCounter[] = $now;

        return true;
    }

    /**
     * Update connection status in database
     */
    private function updateConnectionStatus($status, $error = null)
    {
        try {
            DB::table('smpp_connections')->updateOrInsert(
                [
                    'host' => $this->host,
                    'port' => $this->port,
                ],
                [
                    'status' => $status,
                    'system_id' => $this->systemId,
                    'messages_sent' => $this->messagesSent,
                    'messages_failed' => $this->messagesFailed,
                    'current_tps' => count($this->tpsCounter),
                    'last_activity' => Carbon::now(),
                    'connected_at' => $status === 'connected' ? Carbon::now() : null,
                    'disconnected_at' => $status === 'disconnected' ? Carbon::now() : null,
                    'error_message' => $error,
                    'updated_at' => Carbon::now()
                ]
            );
        } catch (Exception $e) {
            Log::warning("Failed to update connection status: " . $e->getMessage());
        }
    }

    /**
     * Get connection statistics
     */
    public function getStatistics()
    {
        return [
            'connected' => $this->connected,
            'bound' => $this->bound,
            'host' => $this->host,
            'port' => $this->port,
            'messages_sent' => $this->messagesSent,
            'messages_failed' => $this->messagesFailed,
            'current_tps' => count($this->tpsCounter),
            'tps_limit' => $this->tpsLimit,
            'last_activity' => $this->lastActivity ? $this->lastActivity->toDateTimeString() : null,
            'pending_messages' => count($this->pendingMessages),
            'socket_valid' => $this->isSocketValid()
        ];
    }

    public function __destruct()
    {
        // Safely disconnect on destruction
        try {
            $this->disconnect();
        } catch (Exception $e) {
            // Ignore destructor errors
        }
    }
    /**
     * Read incoming messages from SMPP connection
     * @param int $timeout Timeout in seconds (0 = non-blocking)
     * @return array Array of PDUs (DELIVER_SM messages)
     */
    public function readIncomingMessages($timeout = 0)
    {
        if (!$this->connected || !$this->bound) {
            throw new \Exception("Not connected to SMPP server");
        }

        $messages = [];

        try {
            // Set socket to non-blocking mode for continuous reading
            $originalBlocking = stream_get_meta_data($this->socket)['blocked'] ?? true;
            stream_set_blocking($this->socket, false);
            if ($timeout > 0) {
                stream_set_timeout($this->socket, $timeout);
            }

            // Try to read any available PDUs
            $maxReads = 10; // Limit to prevent infinite loop
            $reads = 0;

            while ($reads < $maxReads) {
                $pdu = $this->readPDU(false); // Non-blocking read

                if (!$pdu) {
                    break; // No more PDUs available
                }

                $reads++;
                $commandId = $pdu['command_id'];

                // Process based on command type
                switch ($commandId) {
                    case self::DELIVER_SM:
                        // Parse DELIVER_SM and add to messages array
                        $deliverSm = $this->parseDeliverSm($pdu);
                        $messages[] = $deliverSm;

                        // Send DELIVER_SM_RESP
                        $this->sendDeliverSmResp($pdu['sequence_number']);
                        break;

                    case self::ENQUIRE_LINK:
                        // Respond to ENQUIRE_LINK
                        $this->handleEnquireLink($pdu);
                        break;

                    case self::UNBIND:
                        // Handle UNBIND request from server
                        $this->sendUnbindResp($pdu['sequence_number']);
                        $this->connected = false;
                        $this->bound = false;
                        Log::warning("Received UNBIND from server");
                        break;

                    default:
                        // Log unknown command
                        Log::debug("Received SMPP command", [
                            'command_id' => sprintf('0x%08X', $commandId),
                            'sequence_number' => $pdu['sequence_number']
                        ]);
                }
            }

            // Restore original blocking mode
            stream_set_blocking($this->socket, $originalBlocking);
        } catch (\Exception $e) {
            if ($this->isSocketValid()) {
                stream_set_blocking($this->socket, true);
            }
            Log::error("Error reading incoming messages: " . $e->getMessage());
            throw $e;
        }

        return $messages;
    }
    private function parseDeliverSm($pdu)
    {
        $body = $pdu['body'];
        $pos = 0;

        // Service type (C-Octet String)
        $serviceType = $this->readCString($body, $pos);

        // Source address TON (1 octet)
        $sourceAddrTon = ord($body[$pos++]);

        // Source address NPI (1 octet)
        $sourceAddrNpi = ord($body[$pos++]);

        // Source address (C-Octet String, max 21)
        $sourceAddr = $this->readCString($body, $pos);

        // Destination address TON (1 octet)
        $destAddrTon = ord($body[$pos++]);

        // Destination address NPI (1 octet)
        $destAddrNpi = ord($body[$pos++]);

        // Destination address (C-Octet String, max 21)
        $destinationAddr = $this->readCString($body, $pos);

        // ESM class (1 octet)
        $esmClass = ord($body[$pos++]);

        // Protocol ID (1 octet)
        $protocolId = ord($body[$pos++]);

        // Priority flag (1 octet)
        $priorityFlag = ord($body[$pos++]);

        // Schedule delivery time (C-Octet String)
        $scheduleDeliveryTime = $this->readCString($body, $pos);

        // Validity period (C-Octet String)
        $validityPeriod = $this->readCString($body, $pos);

        // Registered delivery (1 octet)
        $registeredDelivery = ord($body[$pos++]);

        // Replace if present flag (1 octet)
        $replaceIfPresent = ord($body[$pos++]);

        // Data coding (1 octet)
        $dataCoding = ord($body[$pos++]);

        // SM default msg ID (1 octet)
        $smDefaultMsgId = ord($body[$pos++]);

        // SM length (1 octet)
        $smLength = ord($body[$pos++]);

        // Short message (Octet String, max 254)
        $shortMessage = '';
        if ($smLength > 0) {
            $shortMessage = substr($body, $pos, $smLength);
            $pos += $smLength;
        }

        // Decode message based on data coding
        $decodedMessage = $this->decodeIncomingMessage($shortMessage, $dataCoding);

        // Parse optional parameters if any
        $optionalParams = [];
        if ($pos < strlen($body)) {
            $optionalParams = $this->parseOptionalParameters(substr($body, $pos));
        }

        // Check for message_payload in optional parameters (for long messages)
        if (isset($optionalParams['message_payload'])) {
            $decodedMessage = $this->decodeIncomingMessage($optionalParams['message_payload'], $dataCoding);
        }

        return [
            'message_id' => uniqid('MO-'),
            'sequence_number' => $pdu['sequence_number'],
            'service_type' => $serviceType,
            'source_addr_ton' => $sourceAddrTon,
            'source_addr_npi' => $sourceAddrNpi,
            'source_addr' => $sourceAddr,
            'dest_addr_ton' => $destAddrTon,
            'dest_addr_npi' => $destAddrNpi,
            'destination_addr' => $destinationAddr,
            'esm_class' => $esmClass,
            'protocol_id' => $protocolId,
            'priority_flag' => $priorityFlag,
            'schedule_delivery_time' => $scheduleDeliveryTime,
            'validity_period' => $validityPeriod,
            'registered_delivery' => $registeredDelivery,
            'replace_if_present' => $replaceIfPresent,
            'data_coding' => $dataCoding,
            'sm_default_msg_id' => $smDefaultMsgId,
            'sm_length' => $smLength,
            'short_message' => $decodedMessage,
            'raw_message' => bin2hex($shortMessage),
            'optional_parameters' => $optionalParams
        ];
    }

    private function parseOptionalParameters($data)
    {
        $params = [];
        $pos = 0;

        while ($pos < strlen($data) - 4) {
            // Tag (2 bytes)
            $tag = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;

            // Length (2 bytes)
            $length = unpack('n', substr($data, $pos, 2))[1];
            $pos += 2;

            // Value
            $value = substr($data, $pos, $length);
            $pos += $length;

            // Store parameter
            $params[$this->getTlvName($tag)] = $value;
        }

        return $params;
    }
    private function getTlvName($tag)
    {
        $tlvNames = [
            0x0005 => 'dest_addr_subunit',
            0x0006 => 'dest_network_type',
            0x0007 => 'dest_bearer_type',
            0x0008 => 'dest_telematics_id',
            0x000D => 'source_addr_subunit',
            0x000E => 'source_network_type',
            0x000F => 'source_bearer_type',
            0x0010 => 'source_telematics_id',
            0x0017 => 'qos_time_to_live',
            0x0019 => 'payload_type',
            0x001D => 'additional_status_info_text',
            0x001E => 'receipted_message_id',
            0x0030 => 'ms_msg_wait_facilities',
            0x0201 => 'privacy_indicator',
            0x0202 => 'source_subaddress',
            0x0203 => 'dest_subaddress',
            0x0204 => 'user_message_reference',
            0x0205 => 'user_response_code',
            0x020A => 'source_port',
            0x020B => 'destination_port',
            0x020C => 'sar_msg_ref_num',
            0x020D => 'language_indicator',
            0x020E => 'sar_total_segments',
            0x020F => 'sar_segment_seqnum',
            0x0210 => 'sc_interface_version',
            0x0302 => 'callback_num_pres_ind',
            0x0303 => 'callback_num_atag',
            0x0304 => 'number_of_messages',
            0x0381 => 'callback_num',
            0x0420 => 'dpf_result',
            0x0421 => 'set_dpf',
            0x0422 => 'ms_availability_status',
            0x0423 => 'network_error_code',
            0x0424 => 'message_payload',
            0x0425 => 'delivery_failure_reason',
            0x0426 => 'more_messages_to_send',
            0x0427 => 'message_state',
            0x0428 => 'congestion_state',
            0x0501 => 'ussd_service_op',
            0x1201 => 'display_time',
            0x1203 => 'sms_signal',
            0x1204 => 'ms_validity',
            0x130C => 'alert_on_message_delivery',
            0x1380 => 'its_reply_type',
            0x1383 => 'its_session_info',
        ];

        return $tlvNames[$tag] ?? sprintf('unknown_0x%04X', $tag);
    }

    /**
     * Decode incoming message based on data coding
     */
    // private function decodeIncomingMessage($message, $dataCoding)
    // {
    //     try {
    //         switch ($dataCoding) {
    //             case 0x00: // SMSC Default (GSM 7-bit)
    //                 // Usually already decoded, but may need GSM 7-bit decoding
    //                 return $this->gsmDecode($message);

    //             case 0x01: // ASCII
    //                 return $message;

    //             case 0x03: // Latin 1 (ISO-8859-1)
    //                 return mb_convert_encoding($message, 'UTF-8', 'ISO-8859-1');

    //             case 0x08: // UCS-2 (UTF-16 Big Endian)
    //                 return mb_convert_encoding($message, 'UTF-8', 'UTF-16BE');

    //             default:
    //                 return $message;
    //         }
    //     } catch (\Exception $e) {
    //         Log::warning("Failed to decode message: " . $e->getMessage());
    //         return $message;
    //     }
    // }
    private function gsmDecode($data)
    {
        // If data is already readable ASCII, return as is
        if (mb_check_encoding($data, 'ASCII')) {
            return $data;
        }

        // Basic GSM 7-bit character set
        $gsm7bit = [
            "@",
            "£",
            "$",
            "¥",
            "è",
            "é",
            "ù",
            "ì",
            "ò",
            "Ç",
            "\n",
            "Ø",
            "ø",
            "\r",
            "Å",
            "å",
            "Δ",
            "_",
            "Φ",
            "Γ",
            "Λ",
            "Ω",
            "Π",
            "Ψ",
            "Σ",
            "Θ",
            "Ξ",
            "\x1B",
            "Æ",
            "æ",
            "ß",
            "É",
            " ",
            "!",
            "\"",
            "#",
            "¤",
            "%",
            "&",
            "'",
            "(",
            ")",
            "*",
            "+",
            ",",
            "-",
            ".",
            "/",
            "0",
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9",
            ":",
            ";",
            "<",
            "=",
            ">",
            "?",
            "¡",
            "A",
            "B",
            "C",
            "D",
            "E",
            "F",
            "G",
            "H",
            "I",
            "J",
            "K",
            "L",
            "M",
            "N",
            "O",
            "P",
            "Q",
            "R",
            "S",
            "T",
            "U",
            "V",
            "W",
            "X",
            "Y",
            "Z",
            "Ä",
            "Ö",
            "Ñ",
            "Ü",
            "§",
            "¿",
            "a",
            "b",
            "c",
            "d",
            "e",
            "f",
            "g",
            "h",
            "i",
            "j",
            "k",
            "l",
            "m",
            "n",
            "o",
            "p",
            "q",
            "r",
            "s",
            "t",
            "u",
            "v",
            "w",
            "x",
            "y",
            "z",
            "ä",
            "ö",
            "ñ",
            "ü",
            "à"
        ];

        $decoded = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $byte = ord($data[$i]);
            if ($byte < 128) {
                $decoded .= $gsm7bit[$byte] ?? chr($byte);
            }
        }

        return $decoded;
    }
    private function sendEnquireLinkResp($sequenceNumber)
    {
        $pdu = pack(
            'NNNN',
            16,                           // command_length
            self::ENQUIRE_LINK_RESP,     // command_id
            self::ESME_ROK,              // command_status
            $sequenceNumber              // sequence_number
        );

        $this->sendPDU($pdu);

        return true;
    }
    private function sendUnbindResp($sequenceNumber)
    {
        $pdu = pack(
            'NNNN',
            16,                      // command_length
            self::UNBIND_RESP,       // command_id
            self::ESME_ROK,          // command_status
            $sequenceNumber          // sequence_number
        );

        $this->sendPDU($pdu);

        return true;
    }

    /**
     * Decode incoming message based on data coding
     * @param string $message Binary message data
     * @param int $dataCoding Data coding scheme
     * @return string Decoded message
     */
    private function decodeIncomingMessage($message, $dataCoding)
    {
        try {
            switch ($dataCoding) {
                case self::DATA_CODING_DEFAULT:
                case 0: // SMSC Default - GSM 7-bit
                    // For GSM 7-bit, just return as-is since it's usually ASCII-compatible
                    return $message;

                case self::DATA_CODING_LATIN1:
                case 3: // Latin-1
                    return mb_convert_encoding($message, 'UTF-8', 'ISO-8859-1');

                case self::DATA_CODING_UCS2:
                case 8: // UCS2/UTF-16
                    return mb_convert_encoding($message, 'UTF-8', 'UCS-2BE');

                case 4: // Binary
                    return bin2hex($message);

                default:
                    // Try UTF-8 first
                    if (mb_check_encoding($message, 'UTF-8')) {
                        return $message;
                    }
                    // Fallback to returning as-is
                    return $message;
            }
        } catch (\Exception $e) {
            Log::warning("Failed to decode message with data_coding {$dataCoding}: " . $e->getMessage());
            // Return as-is on decode failure
            return $message;
        }
    }
}
