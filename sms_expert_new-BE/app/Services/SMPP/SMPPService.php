<?php

namespace App\Services\SMPP;

use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Queue\RabbitMQService;
use App\Services\UserRouteService;
use App\Services\DeliveryStatusService;
use App\Helpers\GsmCharacterConverter;
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
    // Multi-bank support: when SMPP_BANKS_ENABLED + a bank key is supplied,
    // these hold the bank-specific system_type and seq_id range. When unused
    // they fall back to env defaults so the single-bind code path is unchanged.
    private $systemType = 'smpp';
    private $seqIdMin = 1;
    private $seqIdMax = 0x7FFFFFFE; // SMPP seq_id is u32; reserve top bit
    private $bankKey = null;
    private $lastActivity;
    private $messagesSent = 0;
    private $messagesFailed = 0;
    private $tpsLimit;
    private $tpsCounter = [];
    private $rabbitMQ;
    private $pendingMessages = []; // Store message info for DLR matching
    private $deferredDeliverSm = []; // deliver_sm (DLR/MO) PDUs buffered during a submit_sm_resp wait; drained after the send so DLR work never blocks the wait
    private $pendingSubmitResponses = []; // out-of-order submit_sm_resp PDUs keyed by sequence number
    private $enableDetailedLogging = true; // Enable detailed logging
    private $concatRefSlotBase = null;     // OLD-parity: this worker's base offset in the 0-255 concat reference space (computed once)
    private $concatRefCounter = 0;         // OLD-parity: rotates the concat reference within this worker's slice

    // Bind mode: 'transceiver' (default — send + receive DLR on ONE socket) or
    // 'transmitter' (send-only). A transmitter bind does NOT receive deliver_sm, so
    // submit_sm_resp is never starved by the DLR flood — the send worker uses this and
    // lets the dedicated smpp:dlr-receiver own DLR reception on a separate bind
    // (the old-system model). Default stays 'transceiver' so nothing changes until
    // the worker explicitly opts in via setBindMode('transmitter').
    private $bindMode = 'transceiver';

    // SMPP Command IDs
    const BIND_TRANSCEIVER = 0x00000009;
    const BIND_TRANSCEIVER_RESP = 0x80000009;
    const BIND_TRANSMITTER = 0x00000002;
    const BIND_TRANSMITTER_RESP = 0x80000002;
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
    const TLV_PRICING_REQUEST = 0x1421;   // Request pricing info (send with value 0x31 to enable)
    const TLV_MCC_MNC = 0x1402;           // MCC/MNC information (returned by Vonage)
    const TLV_SUBMISSION_PRICE = 0x1422;  // Price per SMS (returned by Vonage)
    const TLV_REMAINING_BALANCE = 0x1423; // Remaining balance (returned by Vonage)

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
    

    /**
     * @param string|null $host     Override SMPP host (single-bind mode)
     * @param int|null    $port     Override SMPP port (single-bind mode)
     * @param string|null $bankKey  Bank key from config/smpp_banks.php
     *                              (e.g. 'a0', 'b0'...). When supplied AND
     *                              config('smpp_banks.enabled') is true, the
     *                              service binds with bank-specific
     *                              credentials, system_type, and a partitioned
     *                              seq_id range. Mirrors the OLD SYSTEM
     *                              multi-bind architecture.
     */
    public function __construct($host = null, $port = null, ?string $bankKey = null)
    {
        // Default single-bind config from env (backwards-compatible path).
        $this->host        = $host ?: env('SMPP_HOST', 'smpp1.nexmo.com');
        $this->port        = $port ?: env('SMPP_PORT', 8000);
        $this->systemId    = env('SMPP_SYSTEM_ID');
        $this->password    = env('SMPP_PASSWORD');
        $this->systemType  = env('SMPP_TYPE', 'smpp');
        $this->tpsLimit    = env('SMPP_TPS_LIMIT', 50);
        $this->enableDetailedLogging = env('SMPP_DETAILED_LOGGING', true);

        // Bank-aware overrides — only kick in when multi-bank mode is on AND a
        // valid bank key was supplied. Anything else keeps env-driven behaviour.
        if ($bankKey !== null && config('smpp_banks.enabled', false)) {
            $bank = config('smpp_banks.banks.' . $bankKey);
            if (!is_array($bank)) {
                throw new \InvalidArgumentException(
                    "Unknown SMPP bank '{$bankKey}'. Add it to config/smpp_banks.php "
                    . "or omit --bank to use the single-bind .env path."
                );
            }

            $this->bankKey    = $bankKey;
            $this->host       = $bank['host']        ?? $this->host;
            $this->port       = (int) ($bank['port'] ?? $this->port);
            $this->systemId   = $bank['system_id']   ?? $this->systemId;
            $this->password   = $bank['password']    ?? $this->password;
            $this->systemType = $bank['system_type'] ?? $this->systemType;

            // Partitioned sequence_number range — start at the bottom of the
            // bank's slice and wrap inside it. Two binds with overlapping
            // ranges would lose DLR routing, so this is load-bearing.
            $range = $bank['seq_id_range'] ?? [1, 0x7FFFFFFE];
            $this->seqIdMin = (int) $range[0];
            $this->seqIdMax = (int) $range[1];
            $this->sequenceNumber = $this->seqIdMin;

            SmppLogger::vonage()->info('SMPPService: using bank config', [
                'bank'         => $bankKey,
                'host'         => $this->host,
                'system_id'    => $this->systemId,
                'system_type'  => $this->systemType,
                'seq_id_range' => $range,
            ]);
        }

        try {
            $this->rabbitMQ = new RabbitMQService();
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("RabbitMQ not available for DLR: " . $e->getMessage());
            $this->rabbitMQ = null;
        }
    }

    /**
     * Allocate the next SMPP sequence_number, wrapping inside the bank's
     * partitioned range (or 1..0x7FFFFFFE for single-bind mode).
     *
     * The wrap is what lets multiple binds on the same system_id coexist
     * without DLR routing ambiguity — see config/smpp_banks.php for the
     * range layout.
     */
    private function nextSequenceNumber(): int
    {
        $seq = $this->sequenceNumber++;
        if ($this->sequenceNumber > $this->seqIdMax) {
            $this->sequenceNumber = $this->seqIdMin;
        }
        return $seq;
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

            SmppLogger::vonage()->info("Formatted SMPP schedule time", [
                'input' => $scheduleTime,
                'output' => $formattedTime,
                'parsed_dt' => $dt->toDateTimeString(),
                'offset_minutes' => $offsetMinutes,
                'quarter_hours' => $quarterHours
            ]);

            return $formattedTime;
        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to format schedule time for SMPP", [
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
        // OLD SYSTEM PARITY (smppclient-csn.class.php): the old system ALWAYS sent an EMPTY
        // validity_period, letting the SMSC apply its own default retry window (typically 48-72h).
        // The new system previously forced now+24h here, which made messages to temporarily
        // unreachable handsets EXPIRE after 24h (→ Non-Delivered/Expired) where the old route would
        // still be retrying. Returning empty restores the old behaviour. (To cap validity instead,
        // set a value here — e.g. now+48h — but empty = SMSC default = old behaviour.)
        return '';
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
        SmppLogger::vonage()->info("SMPP {$direction}: {$commandName}", $logData);

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
            self::BIND_TRANSMITTER => 'bind_transmitter',
            self::BIND_TRANSMITTER_RESP => 'bind_transmitter_resp',
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
    /**
     * Set the bind mode BEFORE connect()/bind(). 'transmitter' = send-only: a
     * transmitter bind does NOT receive deliver_sm, so submit_sm_resp is never starved
     * by DLR traffic (the fix for the send-worker starvation). 'transceiver' = default
     * (send + receive on one socket). DLR reception in transmitter mode is owned by the
     * separate smpp:dlr-receiver (bind_receiver) — the old-system send/receive split.
     */
    public function setBindMode(string $mode): void
    {
        $this->bindMode = ($mode === 'transmitter') ? 'transmitter' : 'transceiver';
    }

    public function connect($host = null, $port = null)
    {
        if ($host) $this->host = $host;
        if ($port) $this->port = $port;

        try {
            SmppLogger::vonage()->info("SMPP Connection Attempt", [
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

            SmppLogger::vonage()->info("SMPP Socket Connected Successfully");

            // Bind to SMPP server
            return $this->bind();
        } catch (Exception $e) {
            $this->connected = false;
            $this->bound = false;
            $this->updateConnectionStatus('error', $e->getMessage());
            SmppLogger::vonage()->error("SMPP Connection Failed", [
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

            // Pick the bind command from the mode. Transmitter = send-only (no deliver_sm
            // on this socket, so submit_sm_resp is never starved); transceiver = default.
            $bindCommand     = ($this->bindMode === 'transmitter') ? self::BIND_TRANSMITTER : self::BIND_TRANSCEIVER;
            $bindRespCommand = ($this->bindMode === 'transmitter') ? self::BIND_TRANSMITTER_RESP : self::BIND_TRANSCEIVER_RESP;

            // Bind PDU body: system_id + password + system_type (null-terminated)
            // system_type was previously hardcoded to 'smpp' here — that ignored
            // SMPP_TYPE / per-bank system_type and routed every bind down
            // Vonage's generic path with default DLR priority. Now read from
            // $this->systemType so SMPP_TYPE=smppBK1P3 actually takes effect.
            $body = pack('a' . (strlen($this->systemId) + 1), $this->systemId . chr(0));
            $body .= pack('a' . (strlen($this->password) + 1), $this->password . chr(0));
            $body .= pack('a' . (strlen($this->systemType) + 1), $this->systemType . chr(0));
            $body .= pack('CCC', 0x34, 0x00, 0x00); // interface_version, addr_ton, addr_npi
            $body .= pack('a1', chr(0)); // address_range

            $sequenceNum = $this->nextSequenceNumber();
            $pdu = $this->buildPDU($bindCommand, $body, 0, $sequenceNum);

            $this->logPDU('REQUEST', $bindCommand, 0, $sequenceNum, $body, [
                'system_id'         => $this->systemId,
                'system_type'       => $this->systemType,
                'bank'              => $this->bankKey,
                'bind_mode'         => $this->bindMode,
                'interface_version' => '0x34',
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

                if ($response['command_id'] == $bindRespCommand) {
                    if ($response['command_status'] == self::ESME_ROK) {
                        $this->bound = true;
                        $this->updateConnectionStatus('connected');
                        SmppLogger::vonage()->info("SMPP Bind Successful", [
                            'system_id' => $this->systemId,
                            'bind_type' => $this->bindMode
                        ]);
                        // Recovery: clear active alert so a future failure can email again.
                        \App\Services\SMPP\SmppErrorAlertService::clear('Vonage SMPP bind failed');
                        \App\Services\SMPP\SmppErrorAlertService::clear('Vonage SMPP DLR receiver cannot connect');
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
            SmppLogger::vonage()->error("SMPP Bind Failed", [
                'error' => $e->getMessage(),
                'system_id' => $this->systemId
            ]);
            \App\Services\SMPP\SmppErrorAlertService::notify(
                'Vonage SMPP bind failed',
                'Vonage SMPP transceiver bind failed. SMS sending will be unavailable until this is resolved.',
                [
                    'provider'   => 'vonage',
                    'host'       => $this->host ?? '(unknown)',
                    'port'       => $this->port ?? '(unknown)',
                    'system_id'  => $this->systemId,
                    'error'      => $e->getMessage(),
                ]
            );
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
    public function sendSMS($to, $message, $from = null, $priority = 5, $queueId = null, $initiator = 'ControlPanel', $referenceId = null, $scheduleDeliveryTime = null, $smsgLogId = null)
    {
        // Duplicate-send guard.
        //
        // When a unique smsg_log row id is supplied (queue/direct path), scope the
        // guard to THAT row only — a single `to=NUM,NUM,NUM` request creates several
        // smsg_log rows that all share the same bigid AND mobnum, and each must be
        // sent independently. Keying on bigid+mobnum here would let row 1's send
        // suppress rows 2..N (the "only the first number is sent" bug). The id-scoped
        // check still protects against a genuine re-delivery of the same message.
        //
        // Fallback (no smsg_log id — legacy/direct callers): keep the original
        // bigid+mobnum guard so those paths behave exactly as before.
        if ($smsgLogId) {
            // Only a row that was ACTUALLY submitted to Vonage (deliveryreceipt1 set)
            // counts as a duplicate. A persistent-mode row can be sentstatus='ok' while
            // still only QUEUED (no deliveryreceipt1 yet) — that must still be submittable.
            $recentSend = DB::table('smsg_log')
                ->where('id', $smsgLogId)
                ->where('sentstatus', 'ok')
                ->whereNotNull('deliveryreceipt1')
                ->where('deliveryreceipt1', '<>', '')
                ->first();

            if ($recentSend) {
                SmppLogger::vonage()->warning("smsg_log row already sent, preventing duplicate", [
                    'smsg_log_id' => $smsgLogId,
                ]);
                return [
                    'success' => true,
                    'message_id' => $recentSend->suppliermsgref ?? 'already_sent',
                    'to' => $to,
                    'from' => $from,
                    'host' => $this->host,
                    'duplicate' => true,
                ];
            }
        } elseif ($referenceId) {
            $normalisedTo = preg_replace('/[^0-9]/', '', (string) $to);

            $recentSend = DB::table('smsg_log')
                ->where('bigid', $referenceId)
                ->where('mobnum', $normalisedTo)
                ->where('sentstatus', 'ok')
                ->whereNotNull('deliveryreceipt1')
                ->where('deliveryreceipt1', '<>', '')
                ->where('migration_flag', 'new')
                ->first();

            if ($recentSend) {
                SmppLogger::vonage()->warning("Message already sent to this recipient, preventing duplicate", [
                    'bigid'  => $referenceId,
                    'mobnum' => $normalisedTo,
                ]);
                return [
                    'success' => true,
                    'message_id' => $recentSend->suppliermsgref ?? 'already_sent',
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

            // Convert Unicode characters to GSM equivalents to prevent expensive UCS2 encoding
            $conversionResult = $this->convertMessageToGsm($message);
            $message = $conversionResult['message'];

            // Log conversion details if any characters were converted
            if (!empty($conversionResult['converted'])) {
                SmppLogger::vonage()->info("GSM Character Conversion Applied", [
                    'original_length' => $conversionResult['original_length'],
                    'converted_length' => $conversionResult['converted_length'],
                    'characters_converted' => count($conversionResult['converted']),
                    'conversions' => $conversionResult['converted'],
                    'original_encoding' => $conversionResult['original_encoding'],
                    'final_encoding' => $conversionResult['converted_encoding'],
                    'cost_savings' => $conversionResult['cost_savings'],
                    'to' => $to
                ]);
            }

            // Determine data coding based on message content (after conversion)
            $dataCoding = $this->detectDataCoding($message);

            // Format schedule_delivery_time for SMPP (YYMMDDhhmmss with timezone)
            $smppScheduleTime = $this->formatScheduleTimeForSMPP($scheduleDeliveryTime);

            // Format validity_period (24 hours from now or scheduled time)
            $validityPeriod = $this->formatValidityPeriod($scheduleDeliveryTime);

            // Check if this is a long message that needs to be split
            $isLongMessage = $this->isLongMessage($message, $dataCoding);

            if ($isLongMessage) {
                // Handle long message as concatenated SMS
                return $this->sendConcatenatedSMS(
                    $to,
                    $message,
                    $from,
                    $dataCoding,
                    $smppScheduleTime,
                    $validityPeriod,
                    $queueId,
                    $referenceId,
                    $countryCode,
                    $initiator,
                    $smsgLogId
                );
            }

            // Build submit_sm PDU for single message
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
            else                            { $srcTon = 0x01; $srcNpi = 0x00; } // international — OLD parity: numeric sender NPI=0 (Unknown), not E.164
            $body .= pack('CC', $srcTon, $srcNpi); // source_addr_ton, source_addr_npi
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

            // Single message - encode per data_coding.
            // GSM 7-bit: full GSM 03.38 default alphabet (£ -> 0x01, € -> 1B 65, accents, etc.),
            // OLD SYSTEM parity. UCS2: UTF-16BE (was previously sent as raw UTF-8 — wrong).
            if ($dataCoding === self::DATA_CODING_DEFAULT) {
                $shortMessage = GsmCharacterConverter::encodeToGsm7bit($message)['bytes'];
            } elseif ($dataCoding === self::DATA_CODING_UCS2) {
                $shortMessage = mb_convert_encoding($message, 'UTF-16BE', 'UTF-8');
            } else {
                $shortMessage = $message;
            }
            $body .= pack('C', strlen($shortMessage)); // sm_length
            $body .= $shortMessage; // short_message

            // Add TLV field to request price, balance, and MCC/MNC from Nexmo/Vonage
            // Per Vonage docs: Include TLV 0x1421 with value 0x31 ("1" as ASCII)
            // This tells Vonage to return 0x1422 (price), 0x1423 (balance), 0x1402 (MCC/MNC)
            // TLV format: Tag (2 bytes) + Length (2 bytes) + Value (variable)

            // Request Pricing Information (0x1421) - value must be 0x31 ("1" as ASCII byte)
            $body .= pack('nnC', self::TLV_PRICING_REQUEST, 1, 0x31);

            $sequenceNum = $this->nextSequenceNumber();
            $pdu = $this->buildPDU(self::SUBMIT_SM, $body, 0, $sequenceNum);

            $sequenceNum = $this->nextSequenceNumber();
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
                'smsg_log_id' => $smsgLogId,    // Unique row id — disambiguates repeated numbers in one batch
                'mobile' => $to,
                'message' => substr($message, 0, 20),
                'sent_at' => Carbon::now(),
                'country_code' => $countryCode,
                'initiator' => $initiator
            ];

            $this->applySendRateLimit(); // proactive per-second throttle (stay under TPS)
            $this->sendPDU($pdu);

            // Try to read response with timeout - handle out-of-order PDUs
            $maxAttempts = 15; // Increased attempts to handle multiple enquire_link PDUs
            $maxTimeSeconds = 30; // Maximum time to wait for response
            $startWaitTime = microtime(true);
            $response = null;
            $bufferedPdus = [];
            $enquireLinkCount = 0;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                // Check if we've exceeded max wait time
                if ((microtime(true) - $startWaitTime) > $maxTimeSeconds) {
                    SmppLogger::vonage()->warning("Exceeded max wait time for submit_sm_resp", [
                        'wait_time' => round(microtime(true) - $startWaitTime, 2),
                        'attempts' => $attempt,
                        'enquire_links_handled' => $enquireLinkCount
                    ]);
                    break;
                }

                $pdu = $this->readPDU(true); // Blocking read

                if (!$pdu) {
                    // No PDU received, wait a bit and retry
                    if ($attempt < $maxAttempts) {
                        usleep(200000); // 200ms between retries
                        SmppLogger::vonage()->debug("No PDU received, retrying...", ['attempt' => $attempt]);
                        continue;
                    }
                    break;
                }

                // Log received PDU for debugging
                SmppLogger::vonage()->debug("PDU received while waiting for submit_sm_resp", [
                    'command_id' => sprintf('0x%08X', $pdu['command_id']),
                    'command_name' => $this->getCommandName($pdu['command_id']),
                    'sequence' => $pdu['sequence_number'],
                    'expected_sequence' => $sequenceNum
                ]);

                // Check if this is the submit_sm_resp we're waiting for
                if ($pdu['command_id'] == self::SUBMIT_SM_RESP) {
                    if ($pdu['sequence_number'] == $sequenceNum) {
                        $response = $pdu;
                        SmppLogger::vonage()->info("Received expected submit_sm_resp", [
                            'sequence' => $sequenceNum,
                            'attempts' => $attempt,
                            'enquire_links_handled' => $enquireLinkCount,
                            'wait_time_ms' => round((microtime(true) - $startWaitTime) * 1000, 2)
                        ]);
                        break; // Got the response we need
                    } else {
                        // Got a submit_sm_resp but for a different sequence - buffer it
                        SmppLogger::vonage()->debug("Received submit_sm_resp for different sequence", [
                            'received_sequence' => $pdu['sequence_number'],
                            'expected_sequence' => $sequenceNum
                        ]);
                        $bufferedPdus[] = $pdu;
                    }
                } else if ($pdu['command_id'] == self::ENQUIRE_LINK) {
                    // Handle enquire_link immediately and continue waiting
                    $enquireLinkCount++;
                    $this->handleEnquireLink($pdu);
                    SmppLogger::vonage()->debug("Handled enquire_link, continuing to wait for submit_sm_resp", [
                        'enquire_link_count' => $enquireLinkCount
                    ]);
                    // Don't increment attempt counter for enquire_link - it's not a failed attempt
                    $attempt--;
                    continue;
                } else if ($pdu['command_id'] == self::DELIVER_SM) {
                    // Handle deliver_sm and continue waiting
                    SmppLogger::vonage()->debug("Received deliver_sm while waiting for submit_sm_resp, handling...");
                    $this->handleDeliverSm($pdu);
                    // Don't increment attempt counter
                    $attempt--;
                    continue;
                } else if ($pdu['command_id'] == self::ENQUIRE_LINK_RESP) {
                    // This is a response to our own enquire_link, ignore
                    SmppLogger::vonage()->debug("Received enquire_link_resp, continuing to wait");
                    $attempt--;
                    continue;
                } else {
                    // Buffer unexpected PDU
                    SmppLogger::vonage()->debug("Buffering unexpected PDU while waiting for submit_sm_resp", [
                        'command_id' => sprintf('0x%08X', $pdu['command_id']),
                        'command_name' => $this->getCommandName($pdu['command_id']),
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
                        SmppLogger::vonage()->info("========== SMPP RESPONSE WITH TLV DATA ==========");
                        SmppLogger::vonage()->info("SMPP Submit Response - Complete TLV Data", [
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
                        SmppLogger::vonage()->info("================================================");

                        // Log the message ID for debugging
                        SmppLogger::vonage()->info("Extracted message ID from SMPP response", [
                            'message_id' => $messageId,
                            'queue_id' => $queueId
                        ]);

                        // Use SMPP message ID as deliveryreceipt1 for tracking
                        // This allows matching DLR responses with the original message
                        $deliveryReceipt1 = $messageId;
                        $supplierMsgRef = mt_rand(1000000000, 9999999999);

                        SmppLogger::vonage()->info("Using SMPP message ID as deliveryreceipt1", [
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
                                $supplierMsgRef,
                                $submissionPrice, // Pass Vonage submission price for cost calculation
                                $pendingMsg['smsg_log_id'] ?? null
                            );

                            unset($this->pendingMessages[$response['sequence_number']]);
                        }

                        $this->messagesSent++;
                        $this->updateConnectionStatus('connected');

                        SmppLogger::vonage()->info("SMS Sent Successfully", [
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
            SmppLogger::vonage()->warning("No submit_sm_resp received within timeout", [
                'queue_id' => $queueId,
                'to' => $to,
                'from' => $from,
                'sequence_number' => $sequenceNum,
                'enquire_links_handled' => $enquireLinkCount ?? 0,
                'buffered_pdus' => count($bufferedPdus ?? []),
                'note' => 'SMS was submitted but response not received - may still be delivered'
            ]);

            // Do NOT fabricate a message_id here. Vonage assigns its OWN id and returns it
            // only on the DLR; a synthetic uniqid() written to deliveryreceipt1 /
            // onesixty_suppliermsgref can NEVER match that real DLR id, permanently orphaning
            // the delivery status ("No smsg_log record found" — the root cause of DLRs not
            // updating). Leave the match columns EMPTY so the API-poll reconciliation
            // (nexmo:process-delivery-queue) can backfill the real id + status later. The SMS
            // is still counted as sent (the PDU was transmitted) and still priced below.
            $messageId = '';
            $deliveryReceipt1 = '';
            $supplierMsgRef = '';

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
                    $supplierMsgRef,
                    null, // No submission price available in fallback case
                    $pendingMsg['smsg_log_id'] ?? null
                );
                unset($this->pendingMessages[$sequenceNum]);
            }

            SmppLogger::vonage()->info("SMS Sent (fallback - no response received)", [
                'message_id' => $messageId,
                'to' => $to,
                'from' => $from,
                'queue_id' => $queueId,
                'sequence_number' => $sequenceNum,
                'enquire_links_handled' => $enquireLinkCount ?? 0,
                'wait_time_ms' => isset($startWaitTime) ? round((microtime(true) - $startWaitTime) * 1000, 2) : 0,
                'status' => 'submitted_without_confirmation'
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
            SmppLogger::vonage()->error("Failed to send SMS", [
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
                ->where('migration_flag', 'new')
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
            SmppLogger::vonage()->warning("Failed to update smsg_log failure: " . $e->getMessage());
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
                        SmppLogger::vonage()->debug("Received unhandled PDU", ['command_id' => dechex($pdu['command_id'])]);
                }

                $processed++;
            }
        } catch (Exception $e) {
            SmppLogger::vonage()->error("Error processing incoming PDUs: " . $e->getMessage());
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

                // AUTHORITATIVE message id = the receipted_message_id TLV (0x001E) that
                // follows the short_message in the PDU. Vonage's real MT id is a 32-hex
                // UUID (matches smsg_log.deliveryreceipt1 exactly); the legacy receipt-TEXT
                // "id:" field can be truncated/reformatted, which is why DLR matching
                // succeeded only intermittently when we relied on the text id alone.
                // Prefer the TLV when present; keep the text id as a fallback.
                $receiptedId = $this->extractReceiptedMessageId($body, $pos);
                if (!empty($receiptedId)) {
                    $dlrData['text_message_id'] = $dlrData['message_id'] ?? null;
                    $dlrData['message_id'] = $receiptedId;
                    $dlrData['receipted_message_id'] = $receiptedId;
                    SmppLogger::vonage()->info("DLR: using receipted_message_id TLV for matching", [
                        'receipted_message_id' => $receiptedId,
                        'text_id'              => $dlrData['text_message_id'],
                    ]);
                }

                SmppLogger::vonage()->info("DLR Received", $dlrData);

                // Process the DLR
                $this->processDlr($dlrData);
            } else {
                // This is an incoming SMS (MO)
                SmppLogger::vonage()->info("Incoming SMS (MO) Received", [
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
            SmppLogger::vonage()->error("Failed to handle deliver_sm", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Still send response to avoid connection issues
            $this->sendDeliverSmResp($pdu['sequence_number'], 0x00000008); // ESME_RSYSERR
        }
    }

    /**
     * Scan the optional TLV parameters that follow the short_message in a deliver_sm
     * PDU body and return the receipted_message_id (TLV 0x001E) value, if present.
     *
     * This is the message id the SMSC (Vonage) allocated at submit time — the same
     * value returned in submit_sm_resp and stored in smsg_log.deliveryreceipt1 — so
     * it correlates the DLR reliably. The receipt-TEXT "id:" field is the legacy
     * channel and is not dependable for 32-hex UUID ids, which is the root cause of
     * "DLR sometimes updates, sometimes not".
     *
     * @param string $body deliver_sm PDU body
     * @param int    $pos  byte offset positioned just AFTER the short_message
     */
    private function extractReceiptedMessageId(string $body, int $pos): ?string
    {
        $len = strlen($body);

        while ($pos + 4 <= $len) {
            $tag = unpack('n', substr($body, $pos, 2))[1];
            $pos += 2;
            $tlvLen = unpack('n', substr($body, $pos, 2))[1];
            $pos += 2;

            if ($tlvLen < 0 || $pos + $tlvLen > $len) {
                break; // malformed / truncated TLV — stop scanning
            }

            $value = substr($body, $pos, $tlvLen);
            $pos += $tlvLen;

            if ($tag === 0x001E) { // receipted_message_id
                // C-octet string: strip a trailing NUL, then trim whitespace.
                return trim(rtrim($value, "\0"));
            }
        }

        return null;
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

            SmppLogger::vonage()->info("Incoming SMS stored successfully", [
                'from' => $from,
                'to' => $to
            ]);
        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to store incoming SMS: " . $e->getMessage());
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

        SmppLogger::vonage()->info("Parsing DLR Content", ['raw' => $content]);

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
     * Process DLR (uses DeliveryStatusService for OLD SYSTEM compatible processing)
     */
    private function processDlr($dlrData)
    {
        try {
            SmppLogger::vonage()->info("Processing DLR (OLD SYSTEM logic)", $dlrData);

            // In a DLR, the source/destination are swapped:
            // - source = the phone number that received the SMS (recipient)
            // - destination = your sender ID
            // So we use 'source' for the mobile number
            $dlrData['mobile_number'] = $dlrData['source'] ?? '';

            // Map SMPP status to the canonical iTAGG reason code (1-42), matching the
            // OLD SYSTEM daemon ($reply[5]). This reason code — NOT the raw provider
            // err: value — drives delivery_reason, upstream_errormessage, statnum and
            // master_msisdn (e.g. DELIVRD -> 4 -> "Delivered to mobile device").
            // The raw Vonage err: code is preserved separately in aggregator_dlrcode.
            $status = $dlrData['status'] ?? 'UNKNOWN';
            $reasonCode = $this->mapSmppStatusToOldSystemErrorCode($status);
            $providerErr = $dlrData['error_code'] ?? $reasonCode; // raw err: (e.g. "000")

            // Prepare DLR payload for DeliveryStatusService
            $dlrPayload = [
                'message_id' => $dlrData['message_id'] ?? '',
                'mobile_number' => $dlrData['mobile_number'],
                'status' => $status,
                'error_code' => $reasonCode,            // canonical reason code -> delivery_reason / upstream_errormessage
                'done_date' => $dlrData['done_date'] ?? Carbon::now('Europe/London')->format('YmdHis'),
                'provider' => 'nexmo',
                'aggregator_code' => $providerErr,      // raw provider err -> aggregator_dlrcode
                'aggregator_msg' => $dlrData['status_text'] ?? $status,
                'retry' => '0',
                'raw_data' => $dlrData,
            ];

            // OLD-SYSTEM table path (DLR_USE_BUFFER=true): don't do the DB match/update on the
            // SMPP receive session — just buffer the DLR into smsg_receipt_buffer_new (a fast insert)
            // and let the dlr:process-buffer daemon match + update smsg_log. This is exactly
            // daemon_dreceipt_inbound_buffer.php: it keeps the receive session from being blocked
            // by DB work (no send starvation) and needs NO RabbitMQ.
            if (filter_var(env('DLR_USE_BUFFER', false), FILTER_VALIDATE_BOOLEAN)) {
                DB::table('smsg_receipt_buffer_new')->insert([
                    'XMLDATA'     => json_encode($dlrPayload),
                    'status'      => 'new',
                    'processtime' => Carbon::now()->format('YmdHi'),
                ]);
                SmppLogger::vonage()->debug("DLR buffered to smsg_receipt_buffer_new", [
                    'message_id' => $dlrPayload['message_id'],
                ]);
                return;
            }

            // Default (inline): use DeliveryStatusService for OLD SYSTEM compatible processing.
            // This handles: smsg_log update, DLR push callback, master_msisdn update.
            // NOTE: Wallet is NOT refunded on DLR failure (per OLD SYSTEM behavior)
            $deliveryStatusService = app(DeliveryStatusService::class);
            $result = $deliveryStatusService->processDeliveryReceipt($dlrPayload);

            if (!$result) {
                // Fallback: If DeliveryStatusService couldn't find the record, try legacy lookup
                SmppLogger::vonage()->info("DeliveryStatusService couldn't process DLR, trying legacy method", [
                    'message_id' => $dlrData['message_id'] ?? ''
                ]);
                $this->updateSmsgLogWithDlr($dlrData);
            }

            // Queue DLR for additional processing if RabbitMQ available
            if ($this->rabbitMQ) {
                $this->rabbitMQ->publishToQueue(
                    env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
                    $dlrData,
                    5
                );
                SmppLogger::vonage()->info("DLR also queued for additional processing", ['message_id' => $dlrData['message_id'] ?? '']);
            }

            // Store in sms_dlr table for tracking
            $this->storeDlrDirectly($dlrData);

        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to process DLR", [
                'error' => $e->getMessage(),
                'dlr_data' => $dlrData
            ]);
        }
    }

    /**
     * Map SMPP status to OLD SYSTEM error/reason code
     */
    private function mapSmppStatusToOldSystemErrorCode(string $status): int
    {
        $statusMap = [
            'DELIVRD' => 4,   // Delivered to mobile device
            'EXPIRED' => 8,   // Message expired
            'DELETED' => 20,  // Permanent Operator error
            'UNDELIV' => 5,   // Failed, no further info
            'ACCEPTD' => 3,   // Delivered to network (acked)
            'ACKED'   => 3,   // Delivered to network
            'UNKNOWN' => 6,   // Final status unknown
            'REJECTD' => 27,  // Barred by User
            'BUFFERED' => 2,  // Buffered at SMSC
        ];

        return $statusMap[strtoupper($status)] ?? 6;
    }

    /**
     * Store DLR directly in database
     */
    private function storeDlrDirectly($dlrData)
    {
        try {
            // insertOrIgnore (not insert): SMSCs retransmit the same DLR when a
            // deliver_sm_resp ack is slow, so the same message_id arrives more than once.
            // sms_dlr.message_id is UNIQUE — a plain insert threw 1062 on every repeat and
            // spammed the error log. The status update to smsg_log is handled separately
            // (updateSmsgLogWithDlr), so ignoring the duplicate row loses nothing.
            DB::table('sms_dlr')->insertOrIgnore([
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

            // NOTE: sms_queue table removed - DLR status tracked via smsg_log only
            // smsg_log is updated by updateSmsgLogWithDlr method
        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to store DLR directly: " . $e->getMessage());
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
                SmppLogger::vonage()->warning("updateSmsgLogWithDlr: Invalid or empty DLR data", [
                    'raw' => $dlrData
                ]);
                return;
            }

            // Log structured DLR data
            SmppLogger::vonage()->info("updateSmsgLogWithDlr ", $data);

            $deliveryStatus = $this->mapDlrStatusForSmsgLog($data['status'] ?? '');
            $deliveryTime = isset($data['done_date'])
                ? Carbon::parse($data['done_date'])->format('YmdHi')
                : Carbon::now()->format('YmdHi');

            // First, find the smsg_log record using message_id
            $messageId = $data['message_id'] ?? null;

            if (empty($messageId)) {
                SmppLogger::vonage()->warning("No message_id in DLR data", ['dlr_data' => $data]);
                return;
            }

            // Match by the INDEXED onesixty_suppliermsgref column ONLY. It is varchar(36) AND
            // indexed, and the send stores the exact Vonage hex message_id there. This is a
            // single sub-ms seek. The old fallbacks (deliveryreceipt1, hex-convert,
            // smpp_message_mapping) were un-indexed full scans of ~2M rows (13-17s each per the
            // slow query log) and are removed. Legacy rows are handled by dlr:backfill-msgref.
            $smsgLog = DB::table('smsg_log')
                ->where('onesixty_suppliermsgref', $messageId)
                ->first();

            if (!$smsgLog) {
                SmppLogger::vonage()->warning("No smsg_log record found for message_id", [
                    'message_id' => $messageId,
                    'dlr_data' => $data
                ]);
                return;
            }

            // Now we have the bigid from the found record
            $bigid = $smsgLog->id;
            $data['message_id'] = $bigid; // Set the ID for updateSmsgLogByBigidnew

            SmppLogger::vonage()->info("Found smsg_log record for DLR", [
                'message_id' => $messageId,
                'smsg_log_id' => $bigid,
                'mobile' => $smsgLog->mobnum
            ]);

            return $this->updateSmsgLogByBigidnew($bigid, $deliveryStatus, $deliveryTime, $data);
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("Failed to update smsg_log with DLR: " . $e->getMessage(), [
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
                ->where('migration_flag', 'new')
                ->first();

            if (!$smsgLog) {
                SmppLogger::vonage()->warning("No smsg_log found for bigid: {$bigid}");
                return false;
            }


            // Canonical iTAGG reason code (1-42) from the SMPP stat, matching OLD SYSTEM
            // $reply[5]. Drives delivery_reason + upstream_errormessage; raw provider err:
            // stays in aggregator_dlrcode.
            $reasonCode = $this->mapSmppStatusToOldSystemErrorCode($dlrData['status'] ?? 'UNKNOWN');
            $upstreamErrorMessage = app(DeliveryStatusService::class)->getUpstreamErrorMessage($reasonCode);

            // Prepare update data
            $updateData = [
                'deliverystatus1'      => 'acked',
                'deliverystatus2'      => $deliveryStatus,
                'deliverytime1'        => $deliveryTime,
                'deliverytime2'        => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
                'delivery_reason'      => $reasonCode,
                'upstream_errormessage' => $upstreamErrorMessage,
                'aggregator_dlrcode'   => $dlrData['error_code'] ?? 0,
                'aggregator_dlrmsg'    => $dlrData['status_text'] ?? $dlrData['status'] ?? '',
                'timesent' => Carbon::now()->format('YmdHis')
            ];

            // Copy delivery_receipt1 to delivery_receipt2 if exists
            if (!empty($smsgLog->deliveryreceipt1)) {
                $updateData['deliveryreceipt2'] = $smsgLog->deliveryreceipt1;
            }

            // Extract country code
            $thecountrycodes = $this->extractCountryCode($smsgLog->mobnum);
            SmppLogger::vonage()->info("DLR Processing - Country code: " . $thecountrycodes);
            SmppLogger::vonage()->info("DLR Processing - Mobile number: " . $smsgLog->mobnum);

            $userBigId = $smsgLog->userref;
            $getUserId = DB::table('users')
                ->where('bigid', $userBigId)
                ->first();

            if (!$getUserId) {
                SmppLogger::vonage()->warning("User not found for bigid", ['bigid' => $userBigId]);
                return false;
            }

            SmppLogger::vonage()->info("DLR Processing - User id: " . $getUserId->id);

            // CHECK: If pricing was already set from SMPP TLV during send, preserve it
            // Only recalculate if costprice is null, 0, or not set
            $existingCostPrice = $smsgLog->costprice ?? 0;
            $existingUserPrice = $smsgLog->userprice ?? 0;
            $existingProfit = $smsgLog->profit ?? 0;

            if ($existingCostPrice > 0 && $existingUserPrice > 0) {
                // Pricing was already set from SMPP TLV response during send - preserve it
                SmppLogger::vonage()->info("DLR Processing - Preserving existing SMPP TLV pricing (GBP)", [
                    'existing_costprice_gbp' => $existingCostPrice,
                    'existing_userprice_gbp' => $existingUserPrice,
                    'existing_profit_gbp' => $existingProfit,
                    'mobile' => $smsgLog->mobnum
                ]);

                $updateData['costprice'] = $existingCostPrice;
                $updateData['userprice'] = $existingUserPrice;
                $updateData['profit'] = $existingProfit;
            } else {
                // Pricing not set - calculate now using smsg_userroute + country cost table
                SmppLogger::vonage()->info("DLR Processing - No existing pricing, calculating from country cost table");

                $userRouteService = app(UserRouteService::class);

                // Determine route number
                $routenum = ($smsgLog->requested_route && $smsgLog->requested_route > 0) ? $smsgLog->requested_route : (($thecountrycodes === '44') ? 7002 : 8002);

                // Determine operator from suppliername for cost lookup
                $suppliername = $smsgLog->suppliername ?? '';
                $isSinch = (stripos($suppliername, 'sinch') !== false || stripos($suppliername, 'mblox') !== false);
                $costOperator = $isSinch ? 'sinch' : 'vonage';

                // Get pricing using smsg_userroute + country cost table (returns per-part prices)
                $pricing = $userRouteService->getPricingForPhoneNumber(
                    $smsgLog->userref,
                    $smsgLog->mobnum,
                    $routenum,
                    7,      // numbits
                    'alpha', // origtype
                    $costOperator // operator for country cost lookup
                );

                // Get numparts from smsg_log - important for multi-part SMS
                $numParts = $smsgLog->numparts ?: 1;

                // Per-part prices from smsg_userroute
                $perPartUserRate = $pricing['userprice'];
                $perPartCostPrice = $pricing['costprice'];

                // Calculate TOTAL prices by multiplying by numparts
                $userRate = $perPartUserRate * $numParts;
                $costPrice = $perPartCostPrice * $numParts;

                SmppLogger::vonage()->info("DLR Processing - Pricing from smsg_userroute (multiplied by numparts)", [
                    'userref' => $smsgLog->userref,
                    'routenum' => $routenum,
                    'numparts' => $numParts,
                    'per_part_userprice' => $perPartUserRate,
                    'per_part_costprice' => $perPartCostPrice,
                    'total_userprice' => $userRate,
                    'total_costprice' => $costPrice
                ]);

                $updateData['costprice'] = round($costPrice, 8);
                $updateData['userprice'] = round($userRate, 8);
                $updateData['profit'] = round($userRate - $costPrice, 8);
            }

            SmppLogger::vonage()->info("DLR Processing - Final pricing", [
                'costprice' => $updateData['costprice'],
                'userprice' => $updateData['userprice'],
                'profit' => $updateData['profit'],
                'country_code' => $thecountrycodes
            ]);
            $updateData['countrydialcode'] = $thecountrycodes;
            $updateData['timesent'] = Carbon::now()->format('YmdHis');

            SmppLogger::vonage()->info("thecountrycodes updateData: ", $updateData);

            // Update the smsg_log record
            $updated = DB::table('smsg_log')
                ->where('id', $smsgLog->id)
                ->where('migration_flag', 'new')
                ->update($updateData);

            // NOTE: User wallet is already debited when SMS is sent (in storeMessageIdMapping)
            // We do NOT debit again on DLR to avoid double-charging
            // The increment here is ONLY for tracking/statistics purposes if needed
            $bigIdUser = $getUserId->bigid;

            // Log for audit purposes - wallet was already debited on send
            SmppLogger::vonage()->info("DLR Processing - Wallet already debited on send, no additional debit", [
                'user_bigid' => $bigIdUser,
                'userprice' => $updateData['userprice'],
                'message_id' => $dlrData['message_id'] ?? '',
                'delivery_status' => $deliveryStatus
            ]);

            // Handle DLR push callback — useroption cached per account (Phase 2).
            $dreceiptUrlDetails = app(\App\Services\TableCache::class)->useroption($bigIdUser);

            SmppLogger::vonage()->info("Updated smsg_log with DLR", [
                'data' => $dreceiptUrlDetails
            ]);

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url, NOT useroption.dreceipt_push_url. Retry/daemon from useroption.
            $dreceiptUrl = $smsgLog->dreceipt_url ?? '';

            if (
                $dreceiptUrlDetails &&
                strlen($dreceiptUrl) > 10 &&
                $dreceiptUrlDetails->dreceipt_tries_num > 0 &&
                intval($dreceiptUrlDetails->dreceipt_retries_wait_mins) >= 0
            ) {
                $time = Carbon::now()->format('YmdHis');

                // Use deliveryreceipt2 if set, otherwise fallback to deliveryreceipt1 or message_id
                $deliveryReceiptRef = $updateData['deliveryreceipt2'] ?? $smsgLog->deliveryreceipt1 ?? $dlrData['message_id'] ?? '';

                $itagg_receipt_xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
                <itagg_delivery_receipt>
                    <version>1.1</version>
                    <msisdn>{$smsgLog->mobnum}</msisdn>
                    <submission_ref>{$deliveryReceiptRef}</submission_ref>
                    <status>{$deliveryStatus}</status>
                    <reason>4</reason>
                    <gmt_timestamp>{$time}</gmt_timestamp>
                    <retry>0</retry>
                </itagg_delivery_receipt>";

                DB::table('delivery_receipt_push_log')->insert([
                    'thismsgreference' => $deliveryReceiptRef,
                    'msisdn'           => $smsgLog->mobnum,
                    'smsg_log_bigid'   => $bigid,
                    'users_bigid'      => $bigIdUser,
                    'timestamp'        => $time,
                    'status'           => 'new',
                    'message_status'   => $deliveryStatus,
                    'reason'           => '4',
                    'url'              => $dreceiptUrl,
                    'inserted_time'    => Carbon::now()->format('YmdHis'),
                    'retries_left'     => $dreceiptUrlDetails->dreceipt_tries_num,
                    'wait_minutes'     => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                    'dosendtime'       => Carbon::now()->format('Y-m-d H:i:s'),
                    'xml'              => $itagg_receipt_xml,
                    'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                    'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
                ]);

                SmppLogger::vonage()->info("Inserted into delivery_receipt_push_log for DLR push", [
                    'message_id' => $deliveryReceiptRef,
                    'url'        => $dreceiptUrl
                ]);
            }

            SmppLogger::vonage()->info("Updated smsg_log with DLR by bigid", [
                'bigid' => $bigid,
                'status' => $deliveryStatus,
                'smsg_log_id' => $smsgLog->id,
                'updated' => $updated,
                'updateData' => $updateData
            ]);

            return true;
        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to update smsg_log by bigid: " . $e->getMessage(), [
                'bigid' => $bigid,
                'dlr_data' => $dlrData,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
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
     * @param string|null $submissionPrice Vonage/SMPP submission price from TLV (in EUR)
     */

    private function storeMessageIdMapping(
        $messageId,
        $queueId,
        $referenceId,
        $mobile,
        $countryCode,
        $initiator,
        $deliveryReceipt1,
        $supplierMsgRef,
        $submissionPrice = null,
        $smsgLogId = null
    ) {
        try {
            // NOTE: sms_queue table removed - using referenceId (bigid) directly
            $bigidToUpdate = $referenceId;

            if (empty($bigidToUpdate)) {
                SmppLogger::vonage()->warning("storeMessageIdMapping: No reference_id provided for wallet deduction");
                return;
            }

            // Convert hex message_id to decimal for Vonage DLR matching
            // Vonage returns message_id in hex in submit_sm_resp, but decimal in DLR
            // Note: For long UUIDs (32+ chars), use GMP for big number conversion
            $messageIdDecimal = $messageId;
            if (!empty($messageId) && ctype_xdigit($messageId)) {
                // Check if we have GMP extension for large numbers
                if (strlen($messageId) > 15 && function_exists('gmp_strval')) {
                    // Use GMP for large hex strings (UUIDs)
                    $messageIdDecimal = gmp_strval(gmp_init($messageId, 16), 10);
                    SmppLogger::vonage()->info("Converted large hex message_id to decimal using GMP", [
                        'hex' => $messageId,
                        'decimal' => $messageIdDecimal
                    ]);
                } elseif (strlen($messageId) <= 15) {
                    // Small enough for regular hexdec
                    $messageIdDecimal = (string)hexdec($messageId);
                    SmppLogger::vonage()->info("Converted hex message_id to decimal for DLR matching", [
                        'hex' => $messageId,
                        'decimal' => $messageIdDecimal
                    ]);
                } else {
                    // No GMP, store hex as-is (DLR matching will try hex directly)
                    SmppLogger::vonage()->info("Large hex message_id stored as-is (no GMP)", [
                        'hex' => $messageId
                    ]);
                }
            }

            // Store mapping for DLR
            // Note: We store the original hex message_id here
            // The decimal conversion is handled during DLR lookup
            // Skip when there is no real message_id (submit_sm_resp timeout) — a blank id
            // would only create a junk mapping row that can never match a DLR.
            if (!empty($messageId)) {
                DB::table('smpp_message_mapping')->insertOrIgnore([
                    'message_id' => $messageId,
                    'bigid' => $bigidToUpdate,
                    'queue_id' => $queueId,
                    'mobile_number' => $mobile,
                    'created_at' => Carbon::now()
                ]);
            }

            // Scope to the SPECIFIC recipient row. bigid+mobnum is NOT unique when the
            // same number is repeated in one request, so prefer the exact smsg_log.id
            // (threaded through from the queue) — each submit_sm keeps its OWN message_id /
            // deliveryreceipt so its DLR can match later. Fall back to bigid+mobnum only
            // when no id is available (legacy/direct callers).
            if ($smsgLogId) {
                $smsgLog = DB::table('smsg_log')->where('id', $smsgLogId)->first();
                if (!$smsgLog) {
                    SmppLogger::vonage()->warning("storeMessageIdMapping: No smsg_log found for id", ['smsg_log_id' => $smsgLogId]);
                    return;
                }
            } else {
            $smsgLog = DB::table('smsg_log')->where('bigid', $bigidToUpdate)
            ->where('mobnum', $mobile)
            ->where('migration_flag', 'new')->first();

            if (!$smsgLog) {
                SmppLogger::vonage()->warning("storeMessageIdMapping: No smsg_log found for bigid+mobnum", ['bigid' => $bigidToUpdate, 'mobnum' => $mobile]);
                return;
            }
            }

            // Continue with wallet deduction logic using smsgLog...
                $user = DB::table('users')->where('bigid', $smsgLog->userref)->first();
                $country = DB::table('country')->where('dialcode', $countryCode)->first();

                $numParts = isset($smsgLog->numparts) && $smsgLog->numparts > 0
                    ? (int)$smsgLog->numparts
                    : 1;

                // =====================
                // Get pricing from smsg_userroute + country cost table
                // =====================
                $userRouteService = app(UserRouteService::class);

                // Determine route number (use stored requested_route or default to 7002 for UK)
                $routenum = ($smsgLog->requested_route && $smsgLog->requested_route > 0) ? $smsgLog->requested_route : (($countryCode === '44') ? 7002 : 8002);

                // Get pricing using smsg_userroute + country.cost_price_gbp (Vonage)
                $pricing = $userRouteService->getPricingForPhoneNumber(
                    $smsgLog->userref,
                    $mobile,
                    $routenum,
                    7,      // numbits
                    'alpha', // origtype
                    'vonage' // operator for Vonage cost lookup
                );

                $userRate = $pricing['userprice'];
                $costPrice = $pricing['costprice'];

                SmppLogger::vonage()->info("SMPP Pricing from smsg_userroute (OLD SYSTEM)", [
                    'userref' => $smsgLog->userref,
                    'mobile' => $mobile,
                    'routenum' => $routenum,
                    'userprice' => $userRate,
                    'costprice' => $costPrice
                ]);

                // =====================
                // PER PART CALCULATION
                // =====================
                $perPartUserRate = round($userRate, 4);
                $perPartCostPrice = round($costPrice, 4);
                $perPartProfit = round($perPartUserRate - $perPartCostPrice, 4);

                // =====================
                // TOTAL CALCULATION
                // =====================
                $totalUserPrice = round($perPartUserRate * $numParts, 4);
                $totalCostPrice = round($perPartCostPrice * $numParts, 4);
                $totalProfit = round($perPartProfit * $numParts, 4);

                // =====================
                // UPDATE SMS LOG
                // =====================
                // Store decimal message_id in onesixty_suppliermsgref for Vonage DLR matching.
                // Vonage DLR returns message_id in decimal format, not hex. Target the EXACT
                // row (smsg_log.id) when known so repeated numbers in one batch each keep their
                // own message_id; fall back to bigid+mobnum for legacy/direct callers.
                $rowUpdate = DB::table('smsg_log');
                if ($smsgLogId) {
                    $rowUpdate->where('id', $smsgLogId);
                } else {
                    $rowUpdate->where('bigid', $bigidToUpdate)
                        ->where('mobnum', $mobile)
                        ->where('migration_flag', 'new');
                }
                $rowUpdate
                    ->update([
                        'suppliermsgref' => $supplierMsgRef,
                        // Store the SAME hex message_id in onesixty_suppliermsgref as in
                        // deliveryreceipt1. onesixty_suppliermsgref is varchar(36) AND indexed,
                        // so the DLR matcher can look up by it as an INDEXED seek (sub-ms) —
                        // instead of a full-table scan on the un-indexed deliveryreceipt1.
                        // Vonage's DLR returns this exact hex id (receipted_message_id TLV).
                        'onesixty_suppliermsgref' => $messageId, // hex — indexed match key
                        'deliveryreceipt1' => $messageId,        // hex (same value)
                        'sentstatus' => 'ok',
                        // OLD SYSTEM parity: successful sends leave sentstatustext EMPTY (OLD
                        // "sentstatus='ok'" updates never write a success message into it; only
                        // failures/blacklist/disabled populate it). The sent-log shows this field
                        // only when non-empty, so blank keeps successful rows clean.
                        'sentstatustext' => '',
                        'countrydialcode' => $countryCode,
                        'timesent' => Carbon::now()->format('YmdHis'),
                        'deliverystatus1' => 'acked',
                        'deliverytime1' => Carbon::now()->format('YmdHi'),
                        'userprice' => $totalUserPrice,
                        'costprice' => $totalCostPrice,
                        'profit' => $totalProfit,
                    ]);

                // =====================
                // WALLET DEDUCTION
                // =====================
                if ($user && $totalUserPrice > 0) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->where('bigid', $user->bigid)
                        ->increment('smsg_server1_sent', $totalUserPrice);
                }

                SmppLogger::vonage()->info("SMS pricing stored (4 decimals, no round-up)", [
                    'bigid' => $bigidToUpdate,
                    'per_part_user_price' => $perPartUserRate,
                    'total_user_price' => $totalUserPrice,
                    'numparts' => $numParts
                ]);
            
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("Failed to store message ID mapping: " . $e->getMessage());
        }
    }


    // old code 2 digit
    // private function storeMessageIdMapping(
    //     $messageId,
    //     $queueId,
    //     $referenceId,
    //     $mobile,
    //     $countryCode,
    //     $initiator,
    //     $deliveryReceipt1,
    //     $supplierMsgRef,
    //     $submissionPrice = null
    // ) {
    //     try {

    //         /* =========================
    //        QUEUE + BIGID RESOLUTION
    //     ========================== */
    //         if (empty($queueId)) {
    //             return;
    //         }

    //         DB::table('sms_queue')
    //             ->where('queue_id', $queueId)
    //             ->update([
    //                 'message_id' => $messageId,
    //                 'updated_at' => Carbon::now()
    //             ]);

    //         $queue = DB::table('sms_queue')->where('queue_id', $queueId)->first();
    //         if (!$queue) {
    //             return;
    //         }

    //         $metaData = json_decode($queue->metadata, true);
    //         $bigid = $referenceId ?: ($metaData['bigid'] ?? null);
    //         if (!$bigid) {
    //             return;
    //         }

    //         DB::table('smpp_message_mapping')->insertOrIgnore([
    //             'message_id' => $messageId,
    //             'bigid' => $bigid,
    //             'queue_id' => $queueId,
    //             'mobile_number' => $mobile,
    //             'created_at' => Carbon::now()
    //         ]);

    //         $smsgLog = DB::table('smsg_log')->where('bigid', $bigid)->first();
    //         if (!$smsgLog) {
    //             return;
    //         }

    //         $user = DB::table('users')->where('bigid', $smsgLog->userref)->first();
    //         if (!$user) {
    //             return;
    //         }

    //         $country = DB::table('country')->where('dialcode', $countryCode)->first();
    //         $numParts = ($smsgLog->numparts > 0) ? (int)$smsgLog->numparts : 1;

    //         /* =========================
    //        STEP 1: RAW COST PRICE
    //     ========================== */
    //         $rawCostPrice = 0.0400;
    //         $exchangeRateUsed = 1.0;

    //         if ($submissionPrice !== null && is_numeric($submissionPrice) && $submissionPrice > 0) {

    //             $exchangeRate = ($country && $country->exchange_rate_eur_to_gbp > 0)
    //                 ? (float)$country->exchange_rate_eur_to_gbp
    //                 : 0.85;

    //             $exchangeRateUsed = $exchangeRate;
    //             $rawCostPrice = (float)$submissionPrice * $exchangeRate;
    //         } elseif ($country) {

    //             $rawCostPrice = $country->cost_price_gbp
    //                 ?: $country->cost_per_sms
    //                 ?: 0.0400;
    //         }

    //         /* =========================
    //        STEP 2: 🔐 LOCK COST PRICE
    //        (ROUND UP TO 2 DECIMALS)
    //     ========================== */
    //         $lockedCostPrice = ceil($rawCostPrice * 100) / 100;   // 0.065 → 0.07

    //         /* =========================
    //        STEP 3: USER RATE (LOCKED)
    //     ========================== */
    //         $userMargin = DB::table('user_margin')
    //             ->where('user_id', $user->id)
    //             ->where('is_active', 1)
    //             ->first();

    //         if ($userMargin && $userMargin->margin_percentage > 0) {

    //             $marginAmount = round(
    //                 $lockedCostPrice * ($userMargin->margin_percentage / 100),
    //                 2
    //             );

    //             $lockedUserRate = round(
    //                 $lockedCostPrice + $marginAmount,
    //                 2
    //             );
    //         } else {

    //             $lockedUserRate = $user->common_sms_rate
    //                 ? round((float)$user->common_sms_rate, 2)
    //                 : round($lockedCostPrice * 1.25, 2);
    //         }

    //         /* =========================
    //        STEP 4: TOTALS (LOCKED)
    //     ========================== */
    //         $totalUserPrice = round($lockedUserRate * $numParts, 2);
    //         $totalCostPrice = round($lockedCostPrice * $numParts, 2);
    //         $totalProfit    = round($totalUserPrice - $totalCostPrice, 2);

    //         /* =========================
    //        STEP 5: UPDATE SMSG_LOG
    //     ========================== */
    //         DB::table('smsg_log')
    //             ->where('bigid', $bigid)
    //             ->update([
    //                 'suppliermsgref'  => $supplierMsgRef,
    //                 'deliveryreceipt1' => $messageId,
    //                 'sentstatus'      => 'ok',
    //                 'sentstatustext'  => 'Message sent successfully',
    //                 'countrydialcode' => $countryCode,
    //                 'timesent'        => Carbon::now()->format('YmdHis'),
    //                 'deliverystatus1' => 'acked',
    //                 'deliverytime1'   => Carbon::now()->format('YmdHi'),

    //                 // 🔐 LOCKED VALUES ONLY
    //                 'userprice' => $totalUserPrice,   // ✅ 0.14
    //                 'costprice' => $totalCostPrice,   // ✅ 0.07
    //                 'profit'    => $totalProfit,
    //             ]);

    //         /* =========================
    //        STEP 6: WALLET DEDUCTION
    //        (ONLY LOCKED VALUE)
    //     ========================== */
    //         if ($totalUserPrice > 0) {

    //             DB::table('users')
    //                 ->where('id', $user->id)
    //                 ->where('bigid', $user->bigid)
    //                 ->increment('smsg_server1_sent', $totalUserPrice);
    //         }

    //         /* =========================
    //        LOG (OPTIONAL)
    //     ========================== */
    //         SmppLogger::vonage()->info('SMS Pricing Locked & Debited', [
    //             'bigid' => $bigid,
    //             'raw_cost_price' => $rawCostPrice,
    //             'locked_cost_price' => $lockedCostPrice,
    //             'locked_user_rate' => $lockedUserRate,
    //             'numparts' => $numParts,
    //             'wallet_minus' => $totalUserPrice,
    //         ]);
    //     } catch (\Exception $e) {
    //         SmppLogger::vonage()->error('storeMessageIdMapping failed', [
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }


    // old code 8 decimal
    // private function storeMessageIdMapping($messageId, $queueId, $referenceId, $mobile, $countryCode, $initiator, $deliveryReceipt1, $supplierMsgRef, $submissionPrice = null)
    // {
    //     try {
    //         // Store in cache or database for DLR matching
    //         if (!empty($queueId)) {
    //             DB::table('sms_queue')
    //                 ->where('queue_id', $queueId)
    //                 ->update([
    //                     'message_id' => $messageId,
    //                     'updated_at' => Carbon::now()
    //                 ]);
    //             $existingRecord = DB::table('sms_queue')
    //                 ->where('queue_id', $queueId)
    //                 ->first();

    //             $metaData = json_decode($existingRecord->metadata, true);

    //             // Use reference ID if available, otherwise fallback to metadata bigid
    //             $bigidToUpdate = $referenceId ?: ($metaData['bigid'] ?? null);

    //             // Store the SMPP message ID mapping for DLR lookup
    //             if ($bigidToUpdate) {
    //                 // Store in a mapping table for quick DLR lookup
    //                 DB::table('smpp_message_mapping')->insertOrIgnore([
    //                     'message_id' => $messageId,
    //                     'bigid' => $bigidToUpdate,
    //                     'queue_id' => $queueId,
    //                     'mobile_number' => $mobile,
    //                     'created_at' => Carbon::now()
    //                 ]);
    //             }
    //             $smsgLog = DB::table('smsg_log')
    //                 ->where('bigid', $bigidToUpdate)
    //                 ->first();
    //             $user = DB::table('users')
    //                 ->where('bigid', $smsgLog->userref)
    //                 ->first();
    //             $country = DB::table('country')
    //                 ->where('dialcode', $countryCode)
    //                 ->first();

    //             SmppLogger::vonage()->info("Country Table Details", [
    //                 'country' => $country
    //             ]);

    //             // Get numparts from smsg_log (number of SMS parts for long messages)
    //             // Default to 1 if not set
    //             $numParts = isset($smsgLog->numparts) && $smsgLog->numparts > 0 ? (int)$smsgLog->numparts : 1;

    //             SmppLogger::vonage()->info("SMS Parts calculation", [
    //                 'bigid' => $bigidToUpdate,
    //                 'numparts' => $numParts,
    //                 'mobile' => $mobile
    //             ]);

    //             // Default values (8 decimal places for precision)
    //             $costPrice = 0.0400;
    //             $userRate = 0.0500;
    //             $marginPercentage = 0;
    //             $exchangeRateUsed = 1.0;
    //             $originalEurPrice = null;

    //             // PRIORITY: Use SMPP TLV Submission Price if available (convert EUR to GBP)
    //             if ($submissionPrice !== null && is_numeric($submissionPrice) && floatval($submissionPrice) > 0) {
    //                 $originalEurPrice = floatval($submissionPrice);

    //                 // Get exchange rate from country table (EUR to GBP)
    //                 $exchangeRate = 1.0; // Default 1:1 if not found
    //                 if ($country && isset($country->exchange_rate_eur_to_gbp) && $country->exchange_rate_eur_to_gbp > 0) {
    //                     $exchangeRate = floatval($country->exchange_rate_eur_to_gbp);
    //                     SmppLogger::vonage()->info("ExchangeRate 1", [
    //                         'exchangeRate' =>  $exchangeRate
    //                     ]);
    //                 } else {
    //                     // Fallback: Try to get exchange rate from any country record or use default
    //                     $defaultExchangeRate = DB::table('country')
    //                         ->whereNotNull('exchange_rate_eur_to_gbp')
    //                         ->where('exchange_rate_eur_to_gbp', '>', 0)
    //                         ->first();
    //                     if ($defaultExchangeRate) {
    //                         $exchangeRate = floatval($defaultExchangeRate->exchange_rate_eur_to_gbp);
    //                         SmppLogger::vonage()->info("ExchangeRate 2", [
    //                         'exchangeRate' =>  $exchangeRate
    //                     ]);
    //                     } else {
    //                         // Use a reasonable default EUR to GBP rate (approximately 0.85)
    //                         $exchangeRate = 0.85;
    //                     }
    //                 }
    //                 $exchangeRateUsed = $exchangeRate;

    //                 // Convert EUR to GBP: GBP = EUR × exchange_rate_eur_to_gbp
    //                 $costPrice = round($originalEurPrice * $exchangeRate, 8);

    //                 SmppLogger::vonage()->info("Using SMPP TLV Submission Price - EUR to GBP conversion", [
    //                     'submission_price_eur' => $originalEurPrice,
    //                     'exchange_rate_eur_to_gbp' => $exchangeRate,
    //                     'cost_price_gbp' => $costPrice,
    //                     'country_code' => $countryCode,
    //                     'mobile' => $mobile
    //                 ]);
    //             } elseif ($country) {
    //                 // Fallback: Use GBP cost price from country table
    //                 $costPrice = $country->cost_price_gbp ?: $country->cost_per_sms ?: 0.0400;

    //                 SmppLogger::vonage()->info("Using country cost price (no SMPP TLV price)", [
    //                     'country_code' => $countryCode,
    //                     'cost_price_gbp' => $costPrice
    //                 ]);
    //             }

    //             // Get user's margin percentage to calculate user price
    //             $userMargin = DB::table('user_margin')
    //                 ->where('user_id', $user->id)
    //                 ->where('is_active', 1)
    //                 ->first();

    //             if ($userMargin && $userMargin->margin_percentage > 0) {
    //                 $marginPercentage = $userMargin->margin_percentage;
    //                 // Calculate user rate using margin: cost + (cost × margin%)
    //                 $marginAmount = $costPrice * ($marginPercentage / 100);
    //                 $userRate = round($costPrice + $marginAmount, 8);

    //                 SmppLogger::vonage()->info("User price calculated with margin", [
    //                     'cost_price_gbp' => $costPrice,
    //                     'margin_percentage' => $marginPercentage,
    //                     'margin_amount' => round($marginAmount, 8),
    //                     'user_rate_gbp' => $userRate
    //                 ]);
    //             } else {
    //                 // Fallback to user's common rate or default markup
    //                 $userRate = $user->common_sms_rate ?: round($costPrice * 1.25, 8); // 25% default markup

    //                 SmppLogger::vonage()->info("User price using common rate (no margin set)", [
    //                     'cost_price_gbp' => $costPrice,
    //                     'user_rate_gbp' => $userRate
    //                 ]);
    //             }

    //             // Calculate per-part pricing (8 decimal places for precision)
    //             $perPartUserRate = round($userRate, 8);
    //             $perPartCostPrice = round($costPrice, 8);
    //             $perPartProfit = round($perPartUserRate - $perPartCostPrice, 8);

    //             // Calculate total pricing based on number of parts
    //             // For messages > 160 chars: numparts=2, > 306 chars: numparts=3, etc.
    //             $totalUserPrice = round($perPartUserRate * $numParts, 8);
    //             $totalCostPrice = round($perPartCostPrice * $numParts, 8);
    //             $totalProfit = round($perPartProfit * $numParts, 8);

    //             SmppLogger::vonage()->info("Final SMS pricing calculated (all values in GBP)", [
    //                 'message_id' => $messageId,
    //                 'mobile' => $mobile,
    //                 'smpp_submission_price_eur' => $originalEurPrice,
    //                 'exchange_rate_eur_to_gbp' => $exchangeRateUsed,
    //                 'per_part_cost_price_gbp' => $perPartCostPrice,
    //                 'per_part_user_price_gbp' => $perPartUserRate,
    //                 'per_part_profit_gbp' => $perPartProfit,
    //                 'numparts' => $numParts,
    //                 'total_cost_price_gbp' => $totalCostPrice,
    //                 'total_user_price_gbp' => $totalUserPrice,
    //                 'total_profit_gbp' => $totalProfit,
    //                 'margin_percentage' => $marginPercentage ?? 0
    //             ]);

    //             // Also update smsg_log with all required fields
    //             // Store total prices (per-part price × numparts)
    //             DB::table('smsg_log')
    //                 ->where('bigid', $bigidToUpdate)
    //                 ->update([
    //                     'suppliermsgref' => $supplierMsgRef, // Store the actual SMPP message ID
    //                     'deliveryreceipt1' => $messageId, // Store message ID in deliveryreceipt1 only
    //                     'sentstatus' => 'ok',
    //                     'sentstatustext' => 'Message sent successfully',
    //                     'countrydialcode' => $countryCode,
    //                     'timesent' => Carbon::now()->format('YmdHis'),
    //                     'deliverystatus1' => 'acked',
    //                     'deliverytime1' => Carbon::now()->format('YmdHi'),
    //                     // DO NOT set deliveryreceipt2 here - it will be set when DLR arrives
    //                     // Store total prices (multiplied by numparts)
    //                     'userprice' => $totalUserPrice,
    //                     'costprice' => $totalCostPrice,
    //                     'profit' => $totalProfit,
    //                 ]);

    //             // Deduct total cost from user's wallet immediately when SMS is sent
    //             // Total = per-part price × numparts
    //             if ($user && $totalUserPrice > 0) {
    //                 // DB::table('users')
    //                 //     ->where('id', $user->id)
    //                 //     ->where('bigid', $user->bigid)
    //                 //     ->update([
    //                 //         'smsg_server1_sent' => DB::raw('smsg_server1_sent - ' . $totalUserPrice),
    //                 //         'smsg_wallet' => DB::raw('smsg_wallet - ' . $totalUserPrice),
    //                 //     ]);
    //                 DB::table('users')
    //                     ->where('id', $user->id)
    //                     ->where('bigid', $user->bigid)
    //                     ->increment('smsg_server1_sent', $totalUserPrice);

    //                 SmppLogger::vonage()->info("User wallet debited on SMS send (total for all parts)", [
    //                     'user_id' => $user->id,
    //                     'user_bigid' => $user->bigid,
    //                     'per_part_price' => $perPartUserRate,
    //                     'numparts' => $numParts,
    //                     'total_amount_debited' => $totalUserPrice,
    //                     'message_id' => $messageId,
    //                     'mobile' => $mobile
    //                 ]);
    //             }

    //             SmppLogger::vonage()->info("Message ID mapping stored", [
    //                 'message_id' => $messageId,
    //                 'supplier_msg_ref' => $supplierMsgRef,
    //                 'delivery_receipt1' => $deliveryReceipt1,
    //                 'mobile' => $mobile,
    //                 'country_code' => $countryCode,
    //                 // 'initiator' => $initiator
    //             ]);
    //         }
    //     } catch (Exception $e) {
    //         SmppLogger::vonage()->warning("Failed to store message ID mapping: " . $e->getMessage());
    //     }
    // }

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

            // NOTE: sms_queue table removed - using smsg_log and smpp_message_mapping only
            // Match by the INDEXED onesixty_suppliermsgref (holds the exact hex message_id) —
            // a single sub-ms seek instead of a full scan on un-indexed deliveryreceipt1.
            $smsgLog = DB::table('smsg_log')
                ->where('onesixty_suppliermsgref', $messageId)
                ->first();

            if ($smsgLog) {
                return [
                    'queue_id' => null,
                    'bigid' => $smsgLog->bigid,
                    'mobile_number' => $smsgLog->mobnum
                ];
            }
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("Failed to find message by ID: " . $e->getMessage());
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
        SmppLogger::vonage()->debug("Responded to enquire_link");
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
            SmppLogger::vonage()->error("Failed to send deliver_sm_resp: " . $e->getMessage());
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
                // Vonage SMPP DLR "submit date"/"done date" are in GMT/UTC. Parse them AS UTC —
                // NOT the app default (Europe/London). Without the explicit 'UTC' the value was
                // read as London and later setTimezone('UTC') subtracted the BST hour, storing
                // deliverytime2 one hour early in summer (e.g. done date 13:13 -> 12:13). This
                // also restores OLD SYSTEM parity (OLD stores the raw GMT done_date verbatim).
                return Carbon::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $hour:$minute:$second", 'UTC');
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
    /**
     * Map DLR status for smsg_log (OLD SYSTEM compatible)
     */
    private function mapDlrStatusForSmsgLog($dlrStatus)
    {
        // OLD SYSTEM status mapping from getXmlStatusForReasonCode
        $statusMap = [
            'DELIVRD' => 'Delivered',
            'EXPIRED' => 'Non Delivered',  // OLD SYSTEM: expired = Non Delivered
            'DELETED' => 'Non Delivered',  // OLD SYSTEM: deleted = Non Delivered
            'UNDELIV' => 'Non Delivered',
            'ACCEPTD' => 'acked',          // OLD SYSTEM: accepted = acked
            'ACKED'   => 'acked',
            'UNKNOWN' => 'Unknown',
            'REJECTD' => 'Non Delivered',  // OLD SYSTEM: rejected = Non Delivered
            'BUFFERED' => 'buffered smsc',
            'SKIPPED' => 'Non Delivered'
        ];

        return $statusMap[strtoupper($dlrStatus)] ?? 'Unknown';
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
                SmppLogger::vonage()->warning("User not found for pricing calculation", [
                    'userref' => $smsgLogRecord->userref
                ]);
                // Return basic update data without pricing
                return [
                    'deliverystatus1' => 'acked',
                    'deliverystatus2' => $deliveryStatus,
                    'deliverytime1' => $deliveryTime,
                    'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
                    'aggregator_dlrcode' => $dlrData['error_code'] ?? 0,
                    'aggregator_dlrmsg' => $dlrData['status_text'] ?? $dlrData['status'] ?? '',
                    'countrydialcode' => $countryCode,
                    'timesent' => Carbon::now()->format('YmdHis')
                ];
            }

            // Get pricing from smsg_userroute + country cost table
            $userRouteService = app(UserRouteService::class);

            // Determine route number
            $routenum = ($smsgLogRecord->requested_route && $smsgLogRecord->requested_route > 0) ? $smsgLogRecord->requested_route : (($countryCode === '44') ? 7002 : 8002);

            // Determine operator from suppliername for cost lookup
            $suppliername = $smsgLogRecord->suppliername ?? '';
            $isSinch = (stripos($suppliername, 'sinch') !== false || stripos($suppliername, 'mblox') !== false);
            $costOperator = $isSinch ? 'sinch' : 'vonage';

            // Get pricing using smsg_userroute + country cost table
            $pricing = $userRouteService->getPricingForPhoneNumber(
                $smsgLogRecord->userref,
                $smsgLogRecord->mobnum,
                $routenum,
                7,      // numbits
                'alpha', // origtype
                $costOperator // operator for country cost lookup
            );

            // Check if userprice was already set correctly by storeMessageIdMapping
            // If already set and > 0, preserve it (it's already the TOTAL including all parts)
            // Only calculate if not set (e.g., for old records or edge cases)
            $existingUserPrice = (float)($smsgLogRecord->userprice ?? 0);
            $existingCostPrice = (float)($smsgLogRecord->costprice ?? 0);
            $existingProfit = (float)($smsgLogRecord->profit ?? 0);

            if ($existingUserPrice > 0) {
                // Preserve existing pricing (already calculated as total in storeMessageIdMapping)
                $finalRate = $existingUserPrice;
                $costPrice = $existingCostPrice;
                $profit = $existingProfit;

                SmppLogger::vonage()->info("DLR: Preserving existing pricing (already set by storeMessageIdMapping)", [
                    'mobile' => $smsgLogRecord->mobnum,
                    'userprice' => $finalRate,
                    'costprice' => $costPrice,
                    'numparts' => $smsgLogRecord->numparts ?? 1
                ]);
            } else {
                // Calculate pricing for records that don't have it set
                $numParts = isset($smsgLogRecord->numparts) && $smsgLogRecord->numparts > 0
                    ? (int)$smsgLogRecord->numparts
                    : 1;

                $perPartRate = round($pricing['userprice'], 4);
                $perPartCost = round($pricing['costprice'], 4);

                // Multiply by numParts to get TOTAL
                $finalRate = round($perPartRate * $numParts, 4);
                $costPrice = round($perPartCost * $numParts, 4);
                $profit = round($finalRate - $costPrice, 4);

                SmppLogger::vonage()->info("DLR: Calculating pricing (was not set)", [
                    'mobile' => $smsgLogRecord->mobnum,
                    'country_code' => $countryCode,
                    'routenum' => $routenum,
                    'per_part_rate' => $perPartRate,
                    'numparts' => $numParts,
                    'total_userprice' => $finalRate,
                    'total_costprice' => $costPrice
                ]);
            }

            // Prepare complete update data
            $updateData = [
                'deliverystatus1' => 'acked',
                'deliverystatus2' => $deliveryStatus,
                'deliverytime1' => $deliveryTime,
                'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
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
            SmppLogger::vonage()->error("Error calculating DLR update data: " . $e->getMessage());

            // Return basic update data on error
            return [
                'deliverystatus1' => 'acked',
                'deliverystatus2' => $deliveryStatus,
                'deliverytime1' => $deliveryTime,
                'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
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

            // useroption cached per account (Phase 2).
            $dreceiptUrlDetails = app(\App\Services\TableCache::class)->useroption($user->bigid);

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url, NOT useroption.dreceipt_push_url. Retry/daemon from useroption.
            $dreceiptUrl = $smsgLogRecord->dreceipt_url ?? '';

            if (
                !$dreceiptUrlDetails ||
                strlen($dreceiptUrl) <= 10 ||
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
                'url' => $dreceiptUrl,
                'inserted_time' => Carbon::now()->format('YmdHis'),
                'retries_left' => $dreceiptUrlDetails->dreceipt_tries_num,
                'wait_minutes' => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                'xml' => $itagg_receipt_xml,
                'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
            ]);

            SmppLogger::vonage()->info("DLR push callback queued", [
                'bigid' => $smsgLogRecord->bigid,
                'url' => $dreceiptUrl
            ]);
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("Failed to queue DLR push callback: " . $e->getMessage());
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
                ->where('migration_flag', 'new')
                // ->whereNull('deliverystatus2')
                ->orderBy('id', 'desc')
                ->first();

            // If not found, try to get the most recent one with sentstatus = 'ok'
            if (!$smsgLog) {
                $smsgLog = DB::table('smsg_log')
                    ->where('bigid', $bigid)
                    ->where('sentstatus', 'ok')
                    ->where('migration_flag', 'new')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$smsgLog) {
                SmppLogger::vonage()->warning("No smsg_log found for bigid: {$bigid}");
                return false;
            }

            // Check if already has delivery status
            if (!empty($smsgLog->deliverystatus2)) {
                SmppLogger::vonage()->warning("smsg_log already has delivery status, possible duplicate DLR", [
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
                ->where('migration_flag', 'new')
                ->update($updateData);

            // NOTE: Wallet deduction is done in storeMessageIdMapping() when SMS is sent
            // Do NOT deduct again here during DLR processing to avoid double charging

            // Handle DLR push callback
            $this->processDlrPushCallback($smsgLog, $deliveryStatus, $updateData);

            SmppLogger::vonage()->info("Updated smsg_log with DLR by bigid", [
                'bigid' => $bigid,
                'status' => $deliveryStatus,
                'smsg_log_id' => $smsgLog->id,
                'updateData' => $updateData
            ]);

            return true;
        } catch (Exception $e) {
            SmppLogger::vonage()->error("Failed to update smsg_log by bigid: " . $e->getMessage(), [
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
            // useroption cached per account (Phase 2).
            $dreceiptUrlDetails = app(\App\Services\TableCache::class)->useroption($userBigId);

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url, NOT useroption.dreceipt_push_url. Retry/daemon from useroption.
            $dreceiptUrl = $smsgLog->dreceipt_url ?? '';

            if (
                $dreceiptUrlDetails &&
                strlen($dreceiptUrl) > 10 &&
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
                    'url' => $dreceiptUrl,
                    'inserted_time' => Carbon::now()->format('YmdHis'),
                    'retries_left' => $dreceiptUrlDetails->dreceipt_tries_num,
                    'wait_minutes' => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                    'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'xml' => $itagg_receipt_xml,
                    'dlr_daemon_id'    => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                    'apitype'          => $dreceiptUrlDetails->apitype ?? 'w'
                ]);

                SmppLogger::vonage()->info("DLR push callback queued", [
                    'bigid' => $smsgLog->bigid,
                    'url' => $dreceiptUrl
                ]);
            }
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("Failed to queue DLR push callback: " . $e->getMessage());
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
            $sequenceNum = $this->nextSequenceNumber();
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
            SmppLogger::vonage()->error("Enquire link failed: " . $e->getMessage());
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
                    $sequenceNum = $this->nextSequenceNumber();
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
                    SmppLogger::vonage()->debug("Unbind error (ignored): " . $e->getMessage());
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

        SmppLogger::vonage()->info("Disconnected from SMPP server");
    }

    /**
     * Build PDU
     */
    private function buildPDU($commandId, $body, $commandStatus = 0, $sequenceNumber = null)
    {
        if ($sequenceNumber === null) {
            $sequenceNumber = $this->nextSequenceNumber();
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
                SmppLogger::vonage()->warning("Invalid PDU length received", ['length' => $headerData['length']]);
                return null;
            }

            // Read body
            $bodyLength = $headerData['length'] - 16;
            $body = '';

            if ($bodyLength > 0) {
                $body = @fread($this->socket, $bodyLength);
                if ($body === false || strlen($body) < $bodyLength) {
                    SmppLogger::vonage()->warning("Incomplete PDU body received", [
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
            SmppLogger::vonage()->error("Error reading PDU: " . $e->getMessage());
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
                    case self::TLV_PRICING_REQUEST: // 0x1421 (request flag - might be echoed back)
                        SmppLogger::vonage()->debug("SMPP TLV - Pricing Request Flag received", [
                            'value' => ord($valueBytes),
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    case self::TLV_SUBMISSION_PRICE: // 0x1422
                        $value = trim($valueBytes);
                        $result['submission_price'] = $value;
                        SmppLogger::vonage()->info("SMPP TLV - Submission Price", [
                            'value' => $value,
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    case self::TLV_REMAINING_BALANCE: // 0x1423
                        $value = trim($valueBytes);
                        $result['remaining_balance'] = $value;
                        SmppLogger::vonage()->info("SMPP TLV - Remaining Balance", [
                            'value' => $value,
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    case self::TLV_MCC_MNC: // 0x1402
                        $value = trim($valueBytes);
                        $result['mcc_mnc'] = $value;
                        SmppLogger::vonage()->info("SMPP TLV - MCC/MNC", [
                            'value' => $value,
                            'hex' => bin2hex($valueBytes)
                        ]);
                        break;

                    default:
                        SmppLogger::vonage()->debug("SMPP TLV - Unknown Tag", [
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
     * Convert Unicode characters to GSM equivalents
     * This prevents messages from being sent as UCS2 encoding which
     * reduces the character limit from 160 to 70 characters per SMS.
     * 
     * @param string $message The original message
     * @return array Conversion result with message and details
     */
    private function convertMessageToGsm($message)
    {
        try {
            return GsmCharacterConverter::convert($message, false);
        } catch (Exception $e) {
            SmppLogger::vonage()->warning("GSM conversion failed, using original message: " . $e->getMessage());
            return [
                'message' => $message,
                'original' => $message,
                'converted' => [],
                'removed' => [],
                'remaining_non_gsm' => [],
                'is_gsm_safe' => false,
                'original_encoding' => 'Unknown',
                'converted_encoding' => 'Unknown',
                'original_length' => mb_strlen($message),
                'converted_length' => mb_strlen($message),
                'gsm_parts' => 1,
                'unicode_parts' => 1,
                'cost_savings' => [
                    'original_parts' => 1,
                    'converted_parts' => 1,
                    'parts_saved' => 0,
                    'percentage_saved' => 0,
                    'encoding_changed' => false
                ]
            ];
        }
    }

    /**
     * Detect data coding based on message content
     */
    private function detectDataCoding($message)
    {
        // OLD SYSTEM parity (GsmEncoder::utf8_to_gsm0338): a message is sent as GSM 7-bit
        // whenever it is representable in the GSM 03.38 alphabet — that INCLUDES £, €, $, and
        // accented/Greek letters. Only genuinely non-GSM content (Chinese, emoji, Arabic, …)
        // uses UCS2. The previous logic flipped to UCS2 on ANY non-ASCII byte, so a "£184" text
        // shipped as UTF-16 and rendered as garbage ("Chinese-looking") on the handset.
        $gsm = GsmCharacterConverter::encodeToGsm7bit($message);
        return $gsm['fully_encodable'] ? self::DATA_CODING_DEFAULT : self::DATA_CODING_UCS2;
    }

    /**
     * Convert an ASCII message to the GSM 03.38 default alphabet (unpacked) for
     * data_coding = 0x00. Several ASCII bytes occupy DIFFERENT positions in the GSM
     * 7-bit alphabet, so sending raw ASCII makes strict carriers (e.g. India) mis-render
     * them — most visibly ASCII '_' (0x5F), whose byte lands on GSM position 0x5F = '§'.
     * Characters that share the same position (A-Z, a-z, 0-9, space, . , / : ? & = ! …)
     * pass through unchanged, so this is a no-op for typical text and only corrects the
     * handful of differing chars. Mirrors the OLD SYSTEM gsmencoder utf8_to_gsm0338().
     * Only call this when data_coding === DATA_CODING_DEFAULT (pure-ASCII messages).
     */
    private function encodeGsm7bitDefault($text)
    {
        static $map = [
            '@'  => "\x00",
            '$'  => "\x02",
            '_'  => "\x11",
            // Extended characters use the 0x1B escape prefix in GSM 03.38.
            '^'  => "\x1B\x14",
            '{'  => "\x1B\x28",
            '}'  => "\x1B\x29",
            '\\' => "\x1B\x2F",
            '['  => "\x1B\x3C",
            '~'  => "\x1B\x3D",
            ']'  => "\x1B\x3E",
            '|'  => "\x1B\x40",
        ];

        return strtr($text, $map);
    }

    /**
     * Check if message needs to be split into multiple parts
     *
     * @param string $message The message text
     * @param int $dataCoding The data coding scheme
     * @return bool True if message needs splitting
     */
    private function isLongMessage($message, $dataCoding)
    {
        if ($dataCoding === self::DATA_CODING_UCS2) {
            // UCS2: 70 chars for single, 67 per part for concatenated
            return mb_strlen($message, 'UTF-8') > 70;
        }
        // GSM 7-bit: 160 chars for single, 153 per part for concatenated
        return strlen($message) > 160;
    }

    /**
     * Split a long message into parts for concatenated SMS
     *
     * @param string $message The message text
     * @param int $dataCoding The data coding scheme
     * @return array Array of message parts
     */
    private function splitLongMessage($message, $dataCoding)
    {
        $parts = [];

        if ($dataCoding === self::DATA_CODING_UCS2) {
            // OLD SYSTEM parity — smppclient-csn.class.php::splitMessageString does, for UCS2,
            // exactly: str_split($message, 132) where $message is the ALREADY-UTF-16BE-encoded byte
            // string. i.e. it splits by 132 OCTETS (bytes), NOT by character count.
            //
            // The previous NEW code split by 66 CHARACTERS. That only equals 132 bytes for 2-byte
            // (BMP) code units. A 4-byte NON-BMP / EMOJI code point (a UTF-16 surrogate pair) made a
            // 66-char part exceed 132 bytes, overflowing the 140-octet SMS, so Vonage rejected that
            // part with ESME_RINVMSGLEN (command_status 1) — the "part 4/7 failed" incident. A
            // byte-based split can NEVER overflow the octet limit, so it matches OLD and never fails.
            //
            // We encode the whole message to UTF-16BE up front and return the raw byte parts.
            // sendConcatenatedSMS() sends these AS-IS (its UCS2 branch no longer re-encodes).
            $utf16 = mb_convert_encoding($message, 'UTF-16BE', 'UTF-8');
            return str_split($utf16, 132);
        }

        // GSM 7-bit: split by SEPTET count — max 153 septets per concatenated part.
        //
        // GSM 03.38 EXTENDED characters ( ^ { } \ [ ~ ] | ) are encoded by
        // encodeGsm7bitDefault() as a 2-septet escape sequence (0x1B + char), so each costs
        // TWO septets. The old code split on raw character length (153), so a part full of
        // extended chars expanded PAST the 140-octet SMS limit once encoded and the SMSC
        // rejected that part with ESME_RINVMSGLEN (command_status 1) — exactly the "part 3/6
        // failed" error. We count septets, never exceed 153 per part, and never split an
        // extended char across parts. Mirrors the OLD SYSTEM SmppClient::splitMessageString.
        $maxSeptets = 153;
        $extended   = "^{}\\[~]|"; // each of these = 2 septets in GSM 03.38
        $length     = strlen($message);
        $current    = '';
        $septets    = 0;

        for ($i = 0; $i < $length; $i++) {
            $ch   = $message[$i];
            $cost = (strpos($extended, $ch) !== false) ? 2 : 1;

            // Start a new part BEFORE this char if it would overflow the current one.
            if ($septets + $cost > $maxSeptets) {
                $parts[]  = $current;
                $current  = '';
                $septets  = 0;
            }

            $current .= $ch;
            $septets += $cost;
        }

        if ($current !== '') {
            $parts[] = $current;
        }
        if (empty($parts)) {
            $parts[] = '';
        }

        return $parts;
    }

    /**
     * Generate UDH (User Data Header) for concatenated SMS
     *
     * @param int $referenceNumber Unique reference number for this message set (0-255)
     * @param int $totalParts Total number of parts
     * @param int $partNumber Current part number (1-based)
     * @return string Binary UDH
     */
    private function generateUDH($referenceNumber, $totalParts, $partNumber)
    {
        // UDH for 8-bit reference number (6 bytes)
        // 05 - UDH Length (5 bytes follow)
        // 00 - Information Element Identifier (concatenated SMS, 8-bit reference)
        // 03 - Information Element Data Length (3 bytes)
        // XX - Reference Number (0-255)
        // XX - Total Parts
        // XX - Part Number
        return pack('CCCCCC',
            0x05,                    // UDH Length
            0x00,                    // IEI: Concatenated SMS
            0x03,                    // IEI Data Length
            $referenceNumber & 0xFF, // Reference Number (8-bit)
            $totalParts,             // Total Parts
            $partNumber              // Current Part Number
        );
    }

    /**
     * Get a unique reference number for concatenated SMS
     *
     * @return int Reference number (0-255)
     */
    private function getConcatReferenceNumber()
    {
        // OLD SYSTEM PARITY (smppclient-csn.class.php::getCsmsReference, scp20160621 fix):
        // partition the 8-bit (0-255) concat reference space PER WORKER so parallel senders can
        // never pick the SAME reference for the SAME MSISDN at the same time — which would make the
        // handset mis-reassemble two different long messages into one. The old system gave each
        // daemon a 6-value slice; we do the same, keyed off this connection's bank identity, and
        // rotate within the slice on each concatenated message via a dedicated counter.
        $slotSize = 6;
        $slots    = intdiv(256, $slotSize); // 42 non-overlapping slices (0..251)

        if ($this->concatRefSlotBase === null) {
            // Stable per-worker slice. bankKey identifies the pooled connection; fall back to the
            // worker's sequence-range start if no bank is set (single-connection mode).
            $workerIndex = crc32((string) ($this->bankKey ?? $this->seqIdMin)) % $slots;
            $this->concatRefSlotBase = $workerIndex * $slotSize;
        }

        $ref = $this->concatRefSlotBase + ($this->concatRefCounter % $slotSize);
        $this->concatRefCounter++;
        return $ref;
    }

    /**
     * Send a long message as concatenated SMS (multiple parts with UDH)
     *
     * @param string $to Destination phone number
     * @param string $message Full message text
     * @param string $from Sender ID
     * @param int $dataCoding Data coding scheme
     * @param string $smppScheduleTime Scheduled delivery time
     * @param string $validityPeriod Validity period
     * @param string|null $queueId Queue ID
     * @param string|null $referenceId Reference ID (bigid)
     * @param string $countryCode Country code
     * @param string $initiator Initiator
     * @return array Result with message IDs for all parts
     */
    private function sendConcatenatedSMS($to, $message, $from, $dataCoding, $smppScheduleTime, $validityPeriod, $queueId, $referenceId, $countryCode, $initiator, $smsgLogId = null)
    {
        // Split the message into parts
        $parts = $this->splitLongMessage($message, $dataCoding);
        $totalParts = count($parts);
        $concatRef = $this->getConcatReferenceNumber();

        SmppLogger::vonage()->info("Sending concatenated SMS", [
            'to' => $to,
            'total_parts' => $totalParts,
            'concat_ref' => $concatRef,
            'message_length' => strlen($message),
            'data_coding' => $dataCoding
        ]);

        $messageIds = [];
        $results = [];

        for ($partNum = 1; $partNum <= $totalParts; $partNum++) {
            $partMessage = $parts[$partNum - 1];

            // Generate UDH for this part
            $udh = $this->generateUDH($concatRef, $totalParts, $partNum);

            // Build submit_sm PDU with UDH
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
            else                            { $srcTon = 0x01; $srcNpi = 0x00; } // international — OLD parity: numeric sender NPI=0 (Unknown), not E.164
            $body .= pack('CC', $srcTon, $srcNpi); // source_addr_ton, source_addr_npi
            $body .= pack('a' . (strlen($from) + 1), $from . chr(0));
            $body .= pack('CC', 0x01, 0x01); // dest_addr_ton, dest_addr_npi
            $body .= pack('a' . (strlen($to) + 1), $to . chr(0));

            // ESM class 0x40 = UDHI bit set (indicates UDH present in message)
            $body .= pack('CCC', 0x40, 0x00, 0x00); // esm_class with UDHI, protocol_id, priority_flag

            // Add schedule_delivery_time (null-terminated string)
            $body .= pack('a' . (strlen($smppScheduleTime) + 1), $smppScheduleTime . chr(0));

            // Add validity_period (null-terminated string)
            $body .= pack('a' . (strlen($validityPeriod) + 1), $validityPeriod . chr(0));

            // Request delivery receipt only for last part
            $registeredDelivery = ($partNum === $totalParts) ? 0x01 : 0x00;
            $body .= pack('C', $registeredDelivery);

            $body .= pack('C', 0x00); // replace_if_present_flag
            $body .= pack('CC', $dataCoding, 0x00); // data_coding, sm_default_msg_id

            // Prepare message content with UDH
            if ($dataCoding === self::DATA_CODING_UCS2) {
                // $partMessage is ALREADY UTF-16BE bytes — splitLongMessage() encoded the whole
                // message and octet-split it (OLD-style, str_split by 132). Do NOT re-encode here or
                // it would double-encode. Send the raw UTF-16BE bytes as-is.
                $fullMessage = $udh . $partMessage;
            } elseif ($dataCoding === self::DATA_CODING_DEFAULT) {
                // Full GSM 03.38 default alphabet (£ -> 0x01, € -> 1B 65, accents, …) — OLD parity.
                $fullMessage = $udh . GsmCharacterConverter::encodeToGsm7bit($partMessage)['bytes'];
            } else {
                // Latin-1 (0x03) and any other 8-bit coding: send bytes as-is
                $fullMessage = $udh . $partMessage;
            }

            // Add message length and content
            $body .= pack('C', strlen($fullMessage)); // sm_length (includes UDH)
            $body .= $fullMessage; // UDH + message content

            // Add TLV for pricing request
            if (defined('self::TLV_PRICING_REQUEST')) {
                $body .= pack('nnC', self::TLV_PRICING_REQUEST, 1, 0x31);
            }

            // Send this part with THROTTLE (ESME_RTHROTTLED / error 88) backoff+retry.
            // Vonage throttles bursts; on an 88 we wait (exp backoff) and re-submit the SAME
            // part rather than failing the whole message — so the sender self-paces under the
            // account's rate limit instead of dropping messages that then stay 'pending'.
            $throttleMax     = (int) env('SMPP_THROTTLE_MAX_RETRIES', 5);
            $throttleAttempt = 0;
            $response        = null;

            while (true) {
                $sequenceNum = $this->nextSequenceNumber();
                $pdu = $this->buildPDU(self::SUBMIT_SM, $body, 0, $sequenceNum);

                // Log submit_sm request
                $this->logPDU('REQUEST', self::SUBMIT_SM, 0, $sequenceNum, $body, [
                    'from' => $from,
                    'to' => $to,
                    'part' => "{$partNum}/{$totalParts}",
                    'concat_ref' => $concatRef,
                    'data_coding' => $dataCoding,
                    'registered_delivery' => $registeredDelivery
                ]);

                // Store message info for DLR matching (only for last part)
                if ($partNum === $totalParts) {
                    $this->pendingMessages[$sequenceNum] = [
                        'queue_id' => $queueId,
                        'reference_id' => $referenceId,
                        'smsg_log_id' => $smsgLogId,
                        'mobile' => $to,
                        'message' => substr($message, 0, 20),
                        'sent_at' => Carbon::now(),
                        'country_code' => $countryCode,
                        'initiator' => $initiator,
                        'total_parts' => $totalParts,
                        'concat_ref' => $concatRef
                    ];
                }

                $this->applySendRateLimit(); // proactive per-second throttle (stay under TPS)
                $this->sendPDU($pdu);

                // Wait for response
                $response = $this->waitForSubmitSmResponse($sequenceNum);

                if ($response && $response['command_status'] === 0) {
                    break; // part accepted
                }

                $errorCode = $response['command_status'] ?? 'unknown';

                // Throttled (88): back off and retry THIS part (exponential, capped at 3s).
                if ($errorCode === self::ESME_RTHROTTLED && $throttleAttempt < $throttleMax) {
                    $throttleAttempt++;
                    $backoffMs = (int) min(3000, 200 * pow(2, $throttleAttempt));
                    SmppLogger::vonage()->warning("Throttled (88) — backing off then retrying part", [
                        'part' => "{$partNum}/{$totalParts}",
                        'attempt' => $throttleAttempt,
                        'backoff_ms' => $backoffMs,
                        'concat_ref' => $concatRef
                    ]);
                    unset($this->pendingMessages[$sequenceNum]); // drop stale pending for the rejected seq
                    usleep($backoffMs * 1000);
                    continue; // re-submit the same part with a fresh sequence
                }

                // Permanent failure (or throttle retries exhausted): fail the whole message.
                SmppLogger::vonage()->error("Failed to send concatenated SMS part", [
                    'part' => "{$partNum}/{$totalParts}",
                    'error_code' => $errorCode,
                    'throttle_retries' => $throttleAttempt,
                    'concat_ref' => $concatRef
                ]);
                $this->drainDeferredDeliverSm(); // process any DLRs buffered during the wait
                return [
                    'success' => false,
                    'error' => "Failed to send part {$partNum}/{$totalParts}",
                    'error_code' => $errorCode,
                    'parts_sent' => $partNum - 1,
                    'total_parts' => $totalParts
                ];
            }

            $messageId = $response['message_id'] ?? null;
            $messageIds[] = $messageId;
            $results[] = [
                'part' => $partNum,
                'success' => true,
                'message_id' => $messageId,
                'sequence' => $sequenceNum
            ];

            SmppLogger::vonage()->info("Concatenated SMS part sent successfully", [
                'part' => "{$partNum}/{$totalParts}",
                'message_id' => $messageId,
                'concat_ref' => $concatRef
            ]);

            // Small delay between parts to avoid overwhelming the SMSC
            if ($partNum < $totalParts) {
                usleep(50000); // 50ms delay between parts
            }
        }

        // All parts sent successfully
        $this->messagesSent++;

        // Store message mapping for DLR tracking (use last message ID)
        $lastMessageId = end($messageIds);
        $supplierMsgRef = mt_rand(1000000000, 9999999999);

        // Call storeMessageIdMapping to update smsg_log with status and store DLR mapping
        // Note: Pricing is already stored by SMSController, storeMessageIdMapping will recalculate
        // but should arrive at the same total since numparts is correct
        if (!empty($referenceId) && !empty($lastMessageId)) {
            $this->storeMessageIdMapping(
                $lastMessageId,
                $queueId,
                $referenceId,
                $to,
                $countryCode,
                $initiator,
                $lastMessageId, // Use as deliveryreceipt1
                $supplierMsgRef,
                null, // No submission price from TLV for concatenated
                $smsgLogId
            );

            SmppLogger::vonage()->info("Concatenated SMS mapping stored", [
                'reference_id' => $referenceId,
                'last_message_id' => $lastMessageId,
                'total_parts' => $totalParts,
                'all_message_ids' => $messageIds
            ]);
        }

        // Now that the smsg_log mapping is stored, process any DLRs that were buffered
        // while waiting for the submit responses (they can now match this message).
        $this->drainDeferredDeliverSm();

        return [
            'success' => true,
            'message_id' => implode(',', $messageIds),
            'message_ids' => $messageIds,
            'to' => $to,
            'from' => $from,
            'host' => $this->host,
            'total_parts' => $totalParts,
            'concat_ref' => $concatRef,
            'parts' => $results,
            'supplier_msg_ref' => $supplierMsgRef,
            'delivery_receipt1' => $lastMessageId
        ];
    }

    /**
     * PROACTIVE per-second send rate limiter — mirrors the OLD SYSTEM
     * registerMessageSendWithThrottle() (smsg_2send_csn_smpp_fire.inc / mblox_fire.inc).
     *
     * Keeps submit_sm throughput UNDER the account's provisioned TPS so Vonage never
     * returns ESME_RTHROTTLED (88) in the first place. A shared per-second counter in
     * the cache (Redis) is incremented before each submit; if the cap for the current
     * second is reached, we sleep until the next second and retry — exactly like the old
     * memcache 'unixtime|count' logic, but shared across all binds/consumers so the whole
     * account stays under one TPS ceiling.
     *
     * Cap = SMPP_TPS_LIMIT (the existing per-account TPS env, default 50). Because the
     * counter lives in the shared cache (Redis), this is an ACCOUNT-WIDE cap across every
     * bind/consumer — unlike the in-memory checkTpsLimit() which only limits one instance.
     * Set to 0 to disable.
     */
    private function applySendRateLimit(): void
    {
        $max = (int) ($this->tpsLimit ?? 0);
        if ($max <= 0) {
            return; // limiter disabled
        }

        $provider = defined('static::PROVIDER_NAME') ? static::PROVIDER_NAME : 'vonage';

        // Cap total waiting so a cache hiccup can never hang a send indefinitely.
        for ($guard = 0; $guard < 50; $guard++) {
            $second = time();
            $key = "smpp_rl:{$provider}:{$second}";

            try {
                // Atomic per-second counter (Redis INCR). Cache::add sets the TTL once when
                // the key is first created; INCR then counts without touching the TTL.
                Cache::add($key, 0, 3);
                $count = Cache::increment($key);
            } catch (\Throwable $e) {
                return; // cache unavailable — fail OPEN (never block real sends)
            }

            if (!is_numeric($count) || (int) $count <= $max) {
                return; // within this second's budget — proceed with the send
            }

            // Over the cap for this second — sleep until the next second, then retry.
            $fraction = microtime(true) - $second;               // how far into the current second
            $sleepUs  = (int) ((1.0 - $fraction) * 1_000_000);
            usleep(max(1000, min($sleepUs, 1_000_000)));         // clamp 1ms..1s
        }
    }

    /**
     * Wait for submit_sm response with timeout
     *
     * @param int $expectedSequence Expected sequence number
     * @return array|null Response PDU or null
     */
    private function waitForSubmitSmResponse($expectedSequence)
    {
        // Silence-based timeout: a busy connection (e.g. a DLR flood) is still healthy,
        // so we only give up after a stretch of genuine silence. A hard cap bounds the
        // total wait. deliver_sm PDUs are BUFFERED (not processed) here so their slow DB
        // work never starves this wait — they're drained after the send completes.
        $maxTotalSeconds   = 45;   // hard cap on the whole wait
        $maxSilenceSeconds = 15;   // give up only after this long with NO pdu at all
        $startTime    = microtime(true);
        $lastActivity = microtime(true);
        $attempt      = 0;

        // If a previous part already read this response out of order, use it.
        if (isset($this->pendingSubmitResponses[$expectedSequence])) {
            $pdu = $this->pendingSubmitResponses[$expectedSequence];
            unset($this->pendingSubmitResponses[$expectedSequence]);
            $parsed = $this->parseSubmitSmResponse($pdu['body'] ?? '');
            $pdu['message_id'] = $parsed['message_id'] ?? null;
            return $pdu;
        }

        while (true) {
            $now = microtime(true);
            if (($now - $startTime) > $maxTotalSeconds || ($now - $lastActivity) > $maxSilenceSeconds) {
                SmppLogger::vonage()->warning("Timeout waiting for submit_sm_resp", [
                    'expected_sequence' => $expectedSequence,
                    'attempts' => $attempt,
                    'elapsed_ms' => round(($now - $startTime) * 1000, 2),
                    'buffered_deliver_sm' => count($this->deferredDeliverSm),
                ]);
                return null;
            }

            $attempt++;
            $pdu = $this->readPDU(true);

            if (!$pdu) {
                usleep(50000); // 50ms; the silence timer decides when to give up
                continue;
            }

            // Any PDU means the connection is alive and making progress.
            $lastActivity = microtime(true);

            // enquire_link: cheap, answer inline.
            if ($pdu['command_id'] === self::ENQUIRE_LINK) {
                $this->sendEnquireLinkResp($pdu['sequence_number']);
                continue;
            }

            // deliver_sm (DLR / MO): BUFFER it — do NOT process inline. Processing does
            // slow DB work that would starve this wait and cause false "Failed to send
            // part" timeouts. The buffered PDUs are drained (ACKed + processed) after the
            // send finishes. Mirrors the OLD SYSTEM's pdu_queue / separate DLR daemon.
            if ($pdu['command_id'] === self::DELIVER_SM) {
                $this->deferredDeliverSm[] = $pdu;
                continue;
            }

            // Our submit_sm_resp?
            if ($pdu['command_id'] === self::SUBMIT_SM_RESP) {
                if ($pdu['sequence_number'] === $expectedSequence) {
                    $parsedResponse = $this->parseSubmitSmResponse($pdu['body'] ?? '');
                    $pdu['message_id'] = $parsedResponse['message_id'] ?? null;

                    SmppLogger::vonage()->debug("Parsed submit_sm_resp", [
                        'sequence' => $expectedSequence,
                        'message_id' => $pdu['message_id'],
                        'command_status' => $pdu['command_status']
                    ]);

                    return $pdu;
                }

                // A submit_sm_resp for a different sequence — keep it in case a later
                // part is waiting for it, instead of discarding it.
                $this->pendingSubmitResponses[$pdu['sequence_number']] = $pdu;
                SmppLogger::vonage()->debug("Buffered submit_sm_resp for different sequence", [
                    'expected' => $expectedSequence,
                    'received' => $pdu['sequence_number']
                ]);
            }
        }
    }

    /**
     * Process deliver_sm (DLR / MO) PDUs that were buffered while waiting for a
     * submit_sm_resp. Called after a send completes so DLR handling (which sends the
     * deliver_sm_resp ACK and does the DB work) never blocks the submit wait.
     */
    private function drainDeferredDeliverSm()
    {
        if (empty($this->deferredDeliverSm)) {
            return;
        }

        $pdus = $this->deferredDeliverSm;
        $this->deferredDeliverSm = [];

        SmppLogger::vonage()->debug("Draining buffered deliver_sm PDUs", [
            'count' => count($pdus),
        ]);

        foreach ($pdus as $pdu) {
            try {
                $this->handleDeliverSm($pdu); // ACKs + processes the DLR/MO
            } catch (\Throwable $e) {
                SmppLogger::vonage()->error("Failed processing buffered deliver_sm", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
            SmppLogger::vonage()->warning("Failed to update connection status: " . $e->getMessage());
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
                        SmppLogger::vonage()->warning("Received UNBIND from server");
                        break;

                    default:
                        // Log unknown command
                        SmppLogger::vonage()->debug("Received SMPP command", [
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
            SmppLogger::vonage()->error("Error reading incoming messages: " . $e->getMessage());
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
    //         SmppLogger::vonage()->warning("Failed to decode message: " . $e->getMessage());
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
            SmppLogger::vonage()->warning("Failed to decode message with data_coding {$dataCoding}: " . $e->getMessage());
            // Return as-is on decode failure
            return $message;
        }
    }
}
