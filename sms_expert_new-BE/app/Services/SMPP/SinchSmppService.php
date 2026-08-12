<?php

namespace App\Services\SMPP;

use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Illuminate\Support\Facades\DB;
use App\Services\Queue\RabbitMQService;
use App\Services\UserRouteService;
use App\Services\DeliveryStatusService;
use App\Helpers\GsmCharacterConverter;
use Carbon\Carbon;
use Exception;

/**
 * Sinch SMPP Service
 * Handles SMS sending via Sinch SMPP protocol with wallet deduction and DLR support
 */
class SinchSmppService
{
    private $host;
    private $port;
    private $systemId;
    private $password;
    private $systemType;
    private $sourceAddrTon;
    private $sourceAddrNpi;
    private $destAddrTon;
    private $destAddrNpi;
    private $socket;
    private $sequenceNumber = 1;
    // Multi-bank support — when SINCH_BANKS_ENABLED + a bank key is supplied,
    // these hold the bank-specific seq_id range so concurrent binds under the
    // same system_id don't reuse sequence numbers across each other.
    private $seqIdMin = 1;
    private $seqIdMax = 0x7FFFFFFE;
    private $bankKey = null;
    private $isConnected = false;
    private $isBound = false;
    private $rabbitMQ;
    private $pendingMessages = [];
    private $persistentMode = false;

    // SMPP Command IDs
    const GENERIC_NACK = 0x80000000;
    const BIND_TRANSCEIVER = 0x00000009;
    const BIND_TRANSCEIVER_RESP = 0x80000009;
    const BIND_TRANSMITTER = 0x00000002;
    const BIND_TRANSMITTER_RESP = 0x80000002;
    const BIND_RECEIVER = 0x00000001;
    const BIND_RECEIVER_RESP = 0x80000001;
    const SUBMIT_SM = 0x00000004;
    const SUBMIT_SM_RESP = 0x80000004;
    const DELIVER_SM = 0x00000005;
    const DELIVER_SM_RESP = 0x80000005;
    const UNBIND = 0x00000006;
    const UNBIND_RESP = 0x80000006;
    const ENQUIRE_LINK = 0x00000015;
    const ENQUIRE_LINK_RESP = 0x80000015;

    /**
     * @param string|null $bankKey  Optional bank key from config/sinch_banks.php
     *                              (e.g. 'a', 'b'..'j'). When supplied AND
     *                              config('sinch_banks.enabled') is true, the
     *                              service binds with bank-specific
     *                              credentials, system_type, and partitioned
     *                              seq_id range. Mirrors OLD SYSTEM Sinch
     *                              multi-bind architecture.
     */
    public function __construct(?string $bankKey = null)
    {
        // Default single-bind config (backwards-compatible path).
        $this->host       = env('SINCH_SMPP_HOST', 'eu.smpp.api.sinch.com');
        $this->port       = env('SINCH_SMPP_PORT', 3601);
        $this->systemId   = env('SINCH_SMPP_SYSTEM_ID', '');
        $this->password   = env('SINCH_SMPP_PASSWORD', '');
        $this->systemType = env('SINCH_SMPP_SYSTEM_TYPE', '');

        // Bank-aware overrides — only kick in with multi-bank mode + valid key.
        if ($bankKey !== null && config('sinch_banks.enabled', false)) {
            $bank = config('sinch_banks.banks.' . $bankKey);
            if (!is_array($bank)) {
                throw new \InvalidArgumentException(
                    "Unknown Sinch bank '{$bankKey}'. Add it to config/sinch_banks.php "
                    . "or omit --bank to use the single-bind .env path."
                );
            }

            $this->bankKey    = $bankKey;
            $this->host       = $bank['host']        ?? $this->host;
            $this->port       = (int) ($bank['port'] ?? $this->port);
            $this->systemId   = $bank['system_id']   ?? $this->systemId;
            $this->password   = $bank['password']    ?? $this->password;
            $this->systemType = $bank['system_type'] ?? $this->systemType;

            $range = $bank['seq_id_range'] ?? [1, 0x7FFFFFFE];
            $this->seqIdMin = (int) $range[0];
            $this->seqIdMax = (int) $range[1];
            $this->sequenceNumber = $this->seqIdMin;

            SmppLogger::sinch()->info('SinchSmppService: using bank config', [
                'bank'         => $bankKey,
                'host'         => $this->host,
                'system_id'    => $this->systemId,
                'system_type'  => $this->systemType,
                'seq_id_range' => $range,
            ]);
        }

        $this->sourceAddrTon = 0x05; // Alphanumeric
        $this->sourceAddrNpi = 0x00;
        $this->destAddrTon = 0x01; // International
        $this->destAddrNpi = 0x01; // ISDN

        try {
            $this->rabbitMQ = new RabbitMQService();
        } catch (Exception $e) {
            SmppLogger::sinch()->warning("Sinch SMPP: RabbitMQ not available: " . $e->getMessage());
            $this->rabbitMQ = null;
        }
    }

    /**
     * Allocate the next SMPP sequence_number, wrapping inside the bank's
     * partitioned range (or 1..0x7FFFFFFE in single-bind mode).
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
     * Connect to Sinch SMPP server
     */
    public function connect(): bool
    {
        try {
            // Validate credentials before attempting connection
            if (empty($this->systemId) || empty($this->password)) {
                SmppLogger::sinch()->error("Sinch SMPP: Missing credentials - check SINCH_SMPP_SYSTEM_ID and SINCH_SMPP_PASSWORD in .env");
                return false;
            }

            SmppLogger::sinch()->info("Sinch SMPP: Connecting to {$this->host}:{$this->port}");

            // Port 3601 requires TLS/SSL, port 3600 is plain
            $useTls = ($this->port == 3601 || $this->port == 443);
            $connectionString = $useTls ? "ssl://{$this->host}" : $this->host;

            if ($useTls) {
                SmppLogger::sinch()->info("Sinch SMPP: Using TLS connection");
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $this->socket = @stream_socket_client(
                "{$connectionString}:{$this->port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$this->socket) {
                SmppLogger::sinch()->error("Sinch SMPP: Failed to connect - {$errstr} ({$errno})", [
                    'host' => $this->host,
                    'port' => $this->port,
                    'tls' => $useTls
                ]);
                return false;
            }

            stream_set_timeout($this->socket, 30);
            $this->isConnected = true;

            SmppLogger::sinch()->info("Sinch SMPP: Connected successfully to {$this->host}:{$this->port}", [
                'tls' => $useTls
            ]);

            return true;
        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Connection exception - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bind as transceiver (for send and receive DLR)
     */
    public function bind(): bool
    {
        if (!$this->isConnected) {
            SmppLogger::sinch()->error("Sinch SMPP: Cannot bind - not connected");
            return false;
        }

        // If already bound on this connection, return true
        if ($this->isBound) {
            SmppLogger::sinch()->debug("Sinch SMPP: Already bound on this connection, skipping bind");
            return true;
        }

        $maxRetries = 3;
        $retryDelay = 10; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                SmppLogger::sinch()->info("Sinch SMPP: Attempting bind", [
                    'attempt' => $attempt,
                    'host' => $this->host,
                    'port' => $this->port,
                    'system_id' => $this->systemId,
                    'system_type' => $this->systemType ?: '(empty)'
                ]);

                $pdu = $this->packBindTransceiver();
                $this->sendPdu($pdu);

                $response = $this->readPdu();

                if ($response && $response['command_id'] == self::BIND_TRANSCEIVER_RESP) {
                    if ($response['command_status'] == 0) {
                        $this->isBound = true;
                        SmppLogger::sinch()->info("Sinch SMPP: Bound successfully as transceiver");
                        // Recovery: clear active alerts for the send-path failure subjects.
                        \App\Services\SMPP\SmppErrorAlertService::clear('Sinch SMPP transceiver bind failed');
                        \App\Services\SMPP\SmppErrorAlertService::clear('Sinch SMPP connect failed (send path)');
                        return true;
                    } elseif ($response['command_status'] == 5) {
                        // Status 5 = Already bound - another session exists
                        SmppLogger::sinch()->warning("Sinch SMPP: Already bound (status 5) - another session may be active", [
                            'attempt' => $attempt,
                            'system_id' => $this->systemId,
                            'hint' => 'Check if another sms:process-queue is running, or wait 60 seconds for timeout'
                        ]);

                        if ($attempt < $maxRetries) {
                            SmppLogger::sinch()->info("Sinch SMPP: Waiting {$retryDelay} seconds before retry...");
                            sleep($retryDelay);

                            // Reconnect before retry
                            $this->disconnect();
                            if (!$this->connect()) {
                                SmppLogger::sinch()->error("Sinch SMPP: Reconnection failed");
                                return false;
                            }
                            continue;
                        }
                    } else {
                        $errorMsg = $this->getErrorMessage($response['command_status']);
                        SmppLogger::sinch()->error("Sinch SMPP: Bind failed", [
                            'status_code' => $response['command_status'],
                            'status_hex' => '0x' . sprintf('%08X', $response['command_status']),
                            'error' => $errorMsg
                        ]);
                        return false;
                    }
                } else {
                    SmppLogger::sinch()->error("Sinch SMPP: No valid bind response received", [
                        'response' => $response
                    ]);
                    return false;
                }
            } catch (Exception $e) {
                SmppLogger::sinch()->error("Sinch SMPP: Bind exception - " . $e->getMessage());
                return false;
            }
        }

        SmppLogger::sinch()->error("Sinch SMPP: Bind failed after {$maxRetries} attempts - another session is likely active");
        \App\Services\SMPP\SmppErrorAlertService::notify(
            'Sinch SMPP transceiver bind failed',
            "Sinch SMPP bind_transceiver failed after {$maxRetries} attempts. Likely cause: 2-connection-per-host/system_id limit reached, or credentials rejected. SMS sending via Sinch SMPP will be unavailable.",
            [
                'provider'    => 'sinch',
                'host'        => $this->host,
                'port'        => $this->port,
                'system_id'   => $this->systemId,
                'system_type' => $this->systemType,
                'bank'        => $this->bankKey ?: '(single)',
            ]
        );
        return false;
    }

    /**
     * Bind as receiver (for DLR-only sessions, mirrors OLD SYSTEM).
     *
     * Use this from SinchDlrReceiver instead of bind() — bind_receiver
     * sessions are dedup'd separately from bind_transceiver sessions by the
     * legacy mBlox / Sinch gateway, so you can hold N concurrent receiver
     * binds on the same system_id where you can only hold ONE concurrent
     * transceiver bind. OLD SYSTEM exploits this to run 10 parallel DLR
     * receivers (itagg_daemon_smpp_dlr_multi.php line 296).
     */
    public function bindReceiver(): bool
    {
        if (!$this->isConnected) {
            SmppLogger::sinch()->error("Sinch SMPP: Cannot bind_receiver - not connected");
            return false;
        }

        if ($this->isBound) {
            SmppLogger::sinch()->debug("Sinch SMPP: Already bound on this connection, skipping bind_receiver");
            return true;
        }

        $maxRetries = 3;
        $retryDelay = 10;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                SmppLogger::sinch()->info("Sinch SMPP: Attempting bind_receiver", [
                    'attempt'     => $attempt,
                    'host'        => $this->host,
                    'port'        => $this->port,
                    'system_id'   => $this->systemId,
                    'system_type' => $this->systemType ?: '(empty)',
                    'bank'        => $this->bankKey ?: '(single)',
                ]);

                $pdu = $this->packBindReceiver();
                $this->sendPdu($pdu);

                $response = $this->readPdu();

                if ($response && $response['command_id'] == self::BIND_RECEIVER_RESP) {
                    if ($response['command_status'] == 0) {
                        $this->isBound = true;
                        SmppLogger::sinch()->info("Sinch SMPP: Bound successfully as RECEIVER", [
                            'bank' => $this->bankKey ?: '(single)',
                        ]);
                        // Recovery: clear active alerts for the DLR-receiver failure subjects.
                        \App\Services\SMPP\SmppErrorAlertService::clear('Sinch SMPP DLR receiver bind failed');
                        \App\Services\SMPP\SmppErrorAlertService::clear('Sinch DLR receiver crashed');
                        return true;
                    } elseif ($response['command_status'] == 5) {
                        SmppLogger::sinch()->warning("Sinch SMPP: bind_receiver rejected with status 5 (already bound)", [
                            'attempt'   => $attempt,
                            'system_id' => $this->systemId,
                        ]);

                        if ($attempt < $maxRetries) {
                            SmppLogger::sinch()->info("Sinch SMPP: Waiting {$retryDelay}s before bind_receiver retry...");
                            sleep($retryDelay);
                            $this->disconnect();
                            if (!$this->connect()) {
                                SmppLogger::sinch()->error("Sinch SMPP: Reconnection failed");
                                return false;
                            }
                            continue;
                        }
                    } else {
                        $errorMsg = $this->getErrorMessage($response['command_status']);
                        SmppLogger::sinch()->error("Sinch SMPP: bind_receiver failed", [
                            'status_code' => $response['command_status'],
                            'status_hex'  => '0x' . sprintf('%08X', $response['command_status']),
                            'error'       => $errorMsg,
                        ]);
                        return false;
                    }
                } else {
                    SmppLogger::sinch()->error("Sinch SMPP: No valid bind_receiver response received", [
                        'response' => $response,
                    ]);
                    return false;
                }
            } catch (\Throwable $e) {
                SmppLogger::sinch()->error("Sinch SMPP: bind_receiver exception - " . $e->getMessage());
                return false;
            }
        }

        SmppLogger::sinch()->error("Sinch SMPP: bind_receiver failed after {$maxRetries} attempts");
        \App\Services\SMPP\SmppErrorAlertService::notify(
            'Sinch SMPP DLR receiver bind failed',
            "Sinch DLR receiver could not establish a bind_receiver session after {$maxRetries} attempts. Inbound DLR via SMPP is degraded; webhook DLR path still active.",
            [
                'provider'    => 'sinch',
                'host'        => $this->host,
                'port'        => $this->port,
                'system_id'   => $this->systemId,
                'system_type' => $this->systemType,
                'bank'        => $this->bankKey ?: '(single)',
            ]
        );
        return false;
    }

    /**
     * Send SMS via SMPP with full tracking
     *
     * @param string $to Destination phone number
     * @param string $message SMS message
     * @param string $from Sender ID
     * @param int $priority Message priority
     * @param string|null $queueId Queue ID for tracking
     * @param string $initiator Who initiated (ControlPanel, API, etc.)
     * @param string|null $referenceId Reference ID (bigid)
     * @param string|null $scheduleDeliveryTime Scheduled delivery time
     * @return array Result with success status
     */
    public function sendSMS(
        string $to,
        string $message,
        string $from,
        int $priority = 5,
        ?string $queueId = null,
        string $initiator = 'ControlPanel',
        ?string $referenceId = null,
        ?string $scheduleDeliveryTime = null,
        $smsgLogId = null   // exact smsg_log row -> disambiguates the same number repeated in one batch
    ): array {
        try {
            // Connect if not connected
            if (!$this->isConnected) {
                if (!$this->connect()) {
                    \App\Services\SMPP\SmppErrorAlertService::notify(
                        'Sinch SMPP connect failed (send path)',
                        'Outbound SMS attempt could not open a Sinch SMPP socket. Sends are failing fast — likely network/DNS issue or Sinch endpoint outage.',
                        [
                            'provider' => 'sinch',
                            'to'       => $to,
                            'host'     => $this->host,
                            'port'     => $this->port,
                        ]
                    );
                    return [
                        'success' => false,
                        'error' => 'Failed to connect to Sinch SMPP'
                    ];
                }
            }

            // Bind if not bound
            if (!$this->isBound) {
                if (!$this->bind()) {
                    return [
                        'success' => false,
                        'error' => 'Failed to bind to Sinch SMPP'
                    ];
                }
            }

            // Extract and format phone number
            $originalTo = $to;
            $to = $this->formatPhoneNumber($to);
            $countryCode = $this->extractCountryCode($to);

            // Determine encoding
            $encoding = $this->detectEncoding($message);
            $dataCoding = ($encoding === 'ucs2') ? 0x08 : 0x00;

            // Format schedule time if provided
            $smppScheduleTime = $this->formatScheduleTimeForSMPP($scheduleDeliveryTime);

            // Check if this is a long message that needs concatenation
            $isLongMessage = $this->isLongMessage($message, $dataCoding);

            if ($isLongMessage) {
                // Handle long message as concatenated SMS
                return $this->sendConcatenatedSMS(
                    $to,
                    $message,
                    $from,
                    $dataCoding,
                    $smppScheduleTime,
                    $queueId,
                    $referenceId,
                    $countryCode,
                    $initiator
                );
            }

            // Single message - encode per detected scheme. Full GSM 03.38 (£ -> 0x01, € -> 1B 65,
            // accents, …) for GSM; UTF-16BE for UCS2. OLD SYSTEM parity (utf8_to_gsm0338).
            $encodedMessage = ($encoding === 'ucs2')
                ? $this->encodeUcs2($message)
                : GsmCharacterConverter::encodeToGsm7bit($message)['bytes'];

            // Build submit_sm PDU
            $pdu = $this->packSubmitSm($from, $to, $encodedMessage, $dataCoding, $smppScheduleTime);

            $sequenceNum = $this->sequenceNumber;

            // Store pending message info
            $this->pendingMessages[$sequenceNum] = [
                'queue_id' => $queueId,
                'reference_id' => $referenceId,
                'mobile' => $to,
                'country_code' => $countryCode,
                'initiator' => $initiator,
                'smsg_log_id' => $smsgLogId,   // exact row for the message_id update (dup-number safe)
                'sent_at' => Carbon::now()
            ];

            $this->sendPdu($pdu);

            // Read response
            // Read PDUs until we get OUR submit_sm_resp. Sinch frequently interleaves
            // an enquire_link or a deliver_sm (a DLR for an earlier message) BEFORE the
            // submit_sm_resp. Reading only the first PDU wrongly reported "No valid
            // response from Sinch SMPP" even though the message was accepted/delivered.
            $response = null;
            for ($i = 0; $i < 10; $i++) {
                $pdu = $this->readPdu();
                if (!$pdu) {
                    break; // timeout / connection closed
                }
                if ($pdu['command_id'] == self::SUBMIT_SM_RESP) {
                    $response = $pdu;
                    break;
                }
                if ($pdu['command_id'] == self::ENQUIRE_LINK) {
                    $this->sendEnquireLinkResp($pdu['sequence_number']);
                    continue;
                }
                if ($pdu['command_id'] == self::DELIVER_SM) {
                    // Ack it so Sinch doesn't retransmit; the dedicated
                    // sinch:dlr-receiver processes DLR content on its own bind.
                    $this->sendDeliverSmResp($pdu['sequence_number']);
                    continue;
                }
                // any other PDU — ignore and keep reading toward submit_sm_resp
            }

            if ($response && $response['command_id'] == self::SUBMIT_SM_RESP) {
                if ($response['command_status'] == 0) {
                    $messageId = $response['message_id'] ?? '';

                    SmppLogger::sinch()->info("Sinch SMPP: SMS sent successfully", [
                        'to' => $to,
                        'from' => $from,
                        'message_id' => $messageId,
                        'queue_id' => $queueId
                    ]);

                    // Store message mapping and handle wallet deduction
                    $this->storeMessageIdMapping(
                        $messageId,
                        $queueId,
                        $referenceId,
                        $to,
                        $countryCode,
                        $initiator,
                        $smsgLogId
                    );

                    return [
                        'success' => true,
                        'message_id' => $messageId,
                        'provider' => 'sinch_smpp',
                        'host' => $this->host,
                        'to' => $to,
                        'from' => $from
                    ];
                } else {
                    $errorMsg = $this->getErrorMessage($response['command_status']);
                    SmppLogger::sinch()->error("Sinch SMPP: Submit failed", [
                        'status' => $response['command_status'],
                        'error' => $errorMsg,
                        'to' => $to
                    ]);

                    return [
                        'success' => false,
                        'error' => $errorMsg,
                        'status_code' => $response['command_status']
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'No valid response from Sinch SMPP'
            ];

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Send exception - " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if message is too long for a single SMS
     */
    private function isLongMessage(string $message, int $dataCoding): bool
    {
        if ($dataCoding === 0x08) { // UCS2
            return mb_strlen($message, 'UTF-8') > 70;
        }
        return strlen($message) > 160;
    }

    /**
     * Split long message into parts for concatenated SMS
     */
    private function splitLongMessage(string $message, int $dataCoding): array
    {
        $parts = [];

        if ($dataCoding === 0x08) { // UCS2
            // OLD SYSTEM parity (smppclient-csn.class.php: str_split($message,132) on the UTF-16BE
            // bytes): split by 132 OCTETS, NOT by character count. A 4-byte non-BMP / emoji code
            // point made a char-based part exceed 132 bytes, overflowing the 140-octet SMS, so the
            // SMSC rejected it with ESME_RINVMSGLEN. Byte-splitting can never overflow. Parts are
            // raw UTF-16BE bytes; the send loop below no longer re-encodes them.
            $utf16 = mb_convert_encoding($message, 'UTF-16BE', 'UTF-8');
            $parts = str_split($utf16, 132);
        } else { // GSM 7-bit - 153 chars per part
            $maxCharsPerPart = 153;
            $totalLength = strlen($message);

            for ($i = 0; $i < $totalLength; $i += $maxCharsPerPart) {
                $parts[] = substr($message, $i, $maxCharsPerPart);
            }
        }

        return $parts;
    }

    /**
     * Generate UDH (User Data Header) for concatenated SMS
     */
    private function generateUDH(int $referenceNumber, int $totalParts, int $partNumber): string
    {
        // UDH format for concatenated SMS:
        // 05 = UDH length (5 bytes follow)
        // 00 = Information Element Identifier (concatenated SMS)
        // 03 = Length of information element (3 bytes)
        // XX = Reference number (0-255)
        // XX = Total parts
        // XX = Part number (1-based)
        return pack('CCCCCC', 0x05, 0x00, 0x03, $referenceNumber & 0xFF, $totalParts, $partNumber);
    }

    /**
     * Get unique concatenation reference number
     */
    private function getConcatReferenceNumber(): int
    {
        return mt_rand(1, 255);
    }

    /**
     * Send concatenated SMS for long messages
     */
    private function sendConcatenatedSMS(
        string $to,
        string $message,
        string $from,
        int $dataCoding,
        string $smppScheduleTime,
        ?string $queueId,
        ?string $referenceId,
        string $countryCode,
        string $initiator
    ): array {
        // Split the message into parts
        $parts = $this->splitLongMessage($message, $dataCoding);
        $totalParts = count($parts);
        $concatRef = $this->getConcatReferenceNumber();

        SmppLogger::sinch()->info("Sinch SMPP: Sending concatenated SMS", [
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
            $body = pack('x'); // service_type null
            $body .= pack('CC', $this->sourceAddrTon, $this->sourceAddrNpi);
            $body .= pack('a' . (strlen($from) + 1), $from);
            $body .= pack('CC', $this->destAddrTon, $this->destAddrNpi);
            $body .= pack('a' . (strlen($to) + 1), $to);

            // ESM class 0x40 = UDHI bit set (indicates UDH present in message)
            $body .= pack('CCC', 0x40, 0x00, 0x00); // esm_class with UDHI, protocol_id, priority_flag

            // schedule_delivery_time
            $body .= pack('a' . (strlen($smppScheduleTime) + 1), $smppScheduleTime);

            // validity_period (null for default)
            $body .= pack('x');

            // Request delivery receipt only for last part
            $registeredDelivery = ($partNum === $totalParts) ? 0x01 : 0x00;
            $body .= pack('C', $registeredDelivery);

            $body .= pack('C', 0x00); // replace_if_present_flag
            $body .= pack('CC', $dataCoding, 0x00); // data_coding, sm_default_msg_id

            // Prepare message content with UDH
            if ($dataCoding === 0x08) { // UCS2
                // $partMessage is ALREADY UTF-16BE bytes (splitLongMessage octet-split it, OLD-style).
                // Do NOT re-encode — send the raw bytes as-is.
                $fullMessage = $udh . $partMessage;
            } else {
                // Full GSM 03.38 default alphabet (£ -> 0x01, € -> 1B 65, accents, …) — OLD parity.
                $fullMessage = $udh . GsmCharacterConverter::encodeToGsm7bit($partMessage)['bytes'];
            }

            // Add message length and content
            $body .= pack('C', strlen($fullMessage)); // sm_length (includes UDH)
            $body .= $fullMessage; // UDH + message content

            $pdu = $this->packPdu(self::SUBMIT_SM, $body);

            SmppLogger::sinch()->debug("Sinch SMPP: Sending part {$partNum}/{$totalParts}", [
                'concat_ref' => $concatRef,
                'registered_delivery' => $registeredDelivery
            ]);

            $this->sendPdu($pdu);

            // Read response — skip interleaved enquire_link / deliver_sm before the
            // submit_sm_resp (same fix as sendSMS; otherwise parts read the wrong PDU).
            $response = null;
            for ($r = 0; $r < 10; $r++) {
                $p = $this->readPdu();
                if (!$p) {
                    break;
                }
                if ($p['command_id'] == self::SUBMIT_SM_RESP) {
                    $response = $p;
                    break;
                }
                if ($p['command_id'] == self::ENQUIRE_LINK) {
                    $this->sendEnquireLinkResp($p['sequence_number']);
                    continue;
                }
                if ($p['command_id'] == self::DELIVER_SM) {
                    $this->sendDeliverSmResp($p['sequence_number']);
                    continue;
                }
            }

            if ($response && $response['command_id'] == self::SUBMIT_SM_RESP && $response['command_status'] == 0) {
                $messageId = $response['message_id'] ?? '';
                $messageIds[] = $messageId;
                $results[] = [
                    'part' => $partNum,
                    'success' => true,
                    'message_id' => $messageId
                ];

                SmppLogger::sinch()->info("Sinch SMPP: Concatenated SMS part sent successfully", [
                    'part' => "{$partNum}/{$totalParts}",
                    'message_id' => $messageId,
                    'concat_ref' => $concatRef
                ]);
            } else {
                $errorCode = $response['command_status'] ?? 'unknown';
                $errorMsg = $this->getErrorMessage($errorCode);
                SmppLogger::sinch()->error("Sinch SMPP: Failed to send concatenated SMS part", [
                    'part' => "{$partNum}/{$totalParts}",
                    'error_code' => $errorCode,
                    'error' => $errorMsg,
                    'concat_ref' => $concatRef
                ]);

                return [
                    'success' => false,
                    'error' => "Failed to send part {$partNum}/{$totalParts}: {$errorMsg}",
                    'error_code' => $errorCode,
                    'parts_sent' => $partNum - 1,
                    'total_parts' => $totalParts
                ];
            }

            // Small delay between parts
            if ($partNum < $totalParts) {
                usleep(50000); // 50ms delay
            }
        }

        // All parts sent successfully - store message mapping
        $lastMessageId = end($messageIds);
        $supplierMsgRef = mt_rand(1000000000, 9999999999);

        if (!empty($referenceId) && !empty($lastMessageId)) {
            $this->storeMessageIdMapping(
                $lastMessageId,
                $queueId,
                $referenceId,
                $to,
                $countryCode,
                $initiator,
                $smsgLogId
            );

            SmppLogger::sinch()->info("Sinch SMPP: Concatenated SMS mapping stored", [
                'reference_id' => $referenceId,
                'last_message_id' => $lastMessageId,
                'total_parts' => $totalParts,
                'all_message_ids' => $messageIds
            ]);
        }

        return [
            'success' => true,
            'message_id' => implode(',', $messageIds),
            'message_ids' => $messageIds,
            'provider' => 'sinch_smpp',
            'host' => $this->host,
            'to' => $to,
            'from' => $from,
            'total_parts' => $totalParts,
            'concat_ref' => $concatRef,
            'parts' => $results
        ];
    }

    /**
     * Store message ID mapping and handle wallet deduction
     * NOTE: sms_queue table has been removed - only uses smsg_log and smpp_message_mapping
     */
    private function storeMessageIdMapping(
        string $messageId,
        ?string $queueId,
        ?string $referenceId,
        string $mobile,
        string $countryCode,
        string $initiator,
        $smsgLogId = null
    ): void {
        try {
            if (empty($referenceId)) {
                SmppLogger::sinch()->warning("Sinch SMPP: No reference_id for wallet deduction");
                return;
            }

            // Insert into message mapping for DLR
            DB::table('smpp_message_mapping')->insertOrIgnore([
                'message_id' => $messageId,
                'bigid' => $referenceId,
                'queue_id' => $queueId,
                'mobile_number' => $mobile,
                'provider' => 'sinch',
                'created_at' => Carbon::now()
            ]);

            // Get smsg_log and user info - ONLY NEW SYSTEM records.
            // Prefer the EXACT row id (bigid+mobnum is NOT unique when the same number is
            // repeated in one batch — both rows would otherwise get the same message_id and
            // only the first would get its DLR). Fall back to bigid+mobnum for legacy callers.
            $smsgLogQuery = DB::table('smsg_log');
            if (!empty($smsgLogId)) {
                $smsgLogQuery->where('id', $smsgLogId);
            } else {
                $smsgLogQuery->where('bigid', $referenceId)
                    ->where('mobnum', $mobile)
                    ->where('migration_flag', 'new');  // Only NEW system records
            }
            $smsgLog = $smsgLogQuery->first();
            if (!$smsgLog) {
                SmppLogger::sinch()->warning("Sinch SMPP: No smsg_log found", ['smsg_log_id' => $smsgLogId, 'bigid' => $referenceId, 'mobnum' => $mobile]);
                return;
            }

            $user = DB::table('users')->where('bigid', $smsgLog->userref)->first();
            if (!$user) {
                SmppLogger::sinch()->warning("Sinch SMPP: User not found", ['userref' => $smsgLog->userref]);
                return;
            }

            $numParts = isset($smsgLog->numparts) && $smsgLog->numparts > 0
                ? (int)$smsgLog->numparts
                : 1;

            // Get pricing from smsg_userroute + country cost table
            $userRouteService = app(UserRouteService::class);

            // Use Sinch route (3002 for UK)
            $routenum = ($smsgLog->requested_route && $smsgLog->requested_route > 0) ? $smsgLog->requested_route : (($countryCode === '44') ? 3002 : 8002);

            // Get pricing using smsg_userroute + country.sinch_cost_price_gbp
            $pricing = $userRouteService->getPricingForPhoneNumber(
                $smsgLog->userref,
                $smsgLog->mobnum,
                $routenum,
                7,      // numbits
                'alpha', // origtype
                'sinch'  // operator for Sinch cost lookup
            );

            $userRate = $pricing['userprice'];
            $costPrice = $pricing['costprice'];

            SmppLogger::sinch()->info("Sinch SMPP Pricing from smsg_userroute (OLD SYSTEM)", [
                'userref' => $smsgLog->userref,
                'mobile' => $smsgLog->mobnum,
                'routenum' => $routenum,
                'userprice' => $userRate,
                'costprice' => $costPrice
            ]);

            // Calculate totals
            $totalUserPrice = round($userRate * $numParts, 4);
            $totalCostPrice = round($costPrice * $numParts, 4);
            $totalProfit = round($totalUserPrice - $totalCostPrice, 4);

            // Update the EXACT row matched above ($smsgLog->id). id is unique; bigid+mobnum is
            // NOT when the same number repeats in a batch, which would update both rows.
            DB::table('smsg_log')
                ->where('id', $smsgLog->id)
                ->update([
                    'suppliermsgref' => $messageId,
                    // Keep onesixty_suppliermsgref == deliveryreceipt1 (indexed match key), so a
                    // Sinch DLR can also be matched by the indexed column, not a full scan.
                    'onesixty_suppliermsgref' => $messageId,
                    'deliveryreceipt1' => $messageId,
                    'sentstatus' => 'ok',
                    'sentstatustext' => 'Sent via Sinch SMPP',
                    'suppliername' => 'Sinch SMPP',
                    'countrydialcode' => $countryCode,
                    'timesent' => Carbon::now()->format('YmdHis'),
                    'deliverystatus1' => 'acked',
                    'deliverytime1' => Carbon::now()->format('YmdHi'),
                    'userprice' => $totalUserPrice,
                    'costprice' => $totalCostPrice,
                    'profit' => $totalProfit,
                ]);

            // Wallet deduction - increment smsg_server1_sent (usage tracking)
            if ($totalUserPrice > 0) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->where('bigid', $user->bigid)
                    ->increment('smsg_server1_sent', $totalUserPrice);

                SmppLogger::sinch()->info("Sinch SMPP: Wallet deducted", [
                    'user_id' => $user->id,
                    'bigid' => $referenceId,
                    'amount' => $totalUserPrice,
                    'numparts' => $numParts,
                    'country_code' => $countryCode
                ]);
            }

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Failed to store message mapping - " . $e->getMessage());
        }
    }

    /**
     * Process Delivery Receipt from Sinch
     */
    public function processDeliveryReceipt(array $dlrData): bool
    {
        try {
            $messageId = $dlrData['message_id'] ?? '';
            $status = $dlrData['status'] ?? '';

            if (empty($messageId)) {
                SmppLogger::sinch()->warning("Sinch SMPP DLR: No message_id in DLR data");
                return false;
            }

            // Find the message mapping
            $mapping = DB::table('smpp_message_mapping')
                ->where('message_id', $messageId)
                ->where('provider', 'sinch')
                ->first();

            if (!$mapping) {
                SmppLogger::sinch()->warning("Sinch SMPP DLR: No mapping found", ['message_id' => $messageId]);
                return false;
            }

            // Map Sinch status to internal status
            $deliveryStatus = $this->mapDeliveryStatus($status);

            // Convert to OLD SYSTEM format for database storage (capital letters)
            $oldSystemStatus = $this->mapToOldSystemStatus($deliveryStatus);

            // Update smsg_log
            $updateData = [
                'deliverystatus2' => $oldSystemStatus,  // Use OLD SYSTEM format: 'Delivered', 'Non Delivered', etc.
                'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
                'aggregator_dlrmsg' => $status,
            ];

            if ($deliveryStatus === 'delivered') {
                $updateData['sentstatus'] = 'ok';
                $updateData['sentstatustext'] = 'Delivered via Sinch';
            } elseif (in_array($deliveryStatus, ['failed', 'rejected', 'expired'])) {
                $updateData['sentstatus'] = 'fail';
                $updateData['sentstatustext'] = 'Delivery failed: ' . $status;
            }

            DB::table('smsg_log')
                ->where('bigid', $mapping->bigid)
                ->where('migration_flag', 'new')  // Only NEW system records
                ->update($updateData);

            // Handle DLR push callback
            $this->handleDlrPushCallback($mapping->bigid, $deliveryStatus, $dlrData);

            SmppLogger::sinch()->info("Sinch SMPP DLR: Processed successfully", [
                'message_id' => $messageId,
                'bigid' => $mapping->bigid,
                'status' => $deliveryStatus
            ]);

            return true;

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP DLR: Processing failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle DLR push callback to customer URL
     */
    private function handleDlrPushCallback(string $bigid, string $deliveryStatus, array $dlrData): void
    {
        try {
            $smsgLog = DB::table('smsg_log')->where('bigid', $bigid)->first();
            if (!$smsgLog) {
                return;
            }

            $dreceiptUrlDetails = app(\App\Services\TableCache::class)->useroption($smsgLog->userref); // cached per account (Phase 2)

            // OLD SYSTEM parity (daemon_dreceipt_inbound_buffer.php:16,268,295,321): push URL is the
            // PER-MESSAGE smsg_log.dreceipt_url, NOT useroption.dreceipt_push_url. Retry/daemon from useroption.
            $dreceiptUrl = $smsgLog->dreceipt_url ?? '';

            if (
                $dreceiptUrlDetails &&
                strlen($dreceiptUrl) > 10 &&
                $dreceiptUrlDetails->dreceipt_tries_num > 0
            ) {
                $time = Carbon::now()->format('YmdHis');
                $deliveryReceiptRef = $dlrData['message_id'] ?? $smsgLog->deliveryreceipt1 ?? '';

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
                    'msisdn' => $smsgLog->mobnum,
                    'smsg_log_bigid' => $bigid,
                    'users_bigid' => $smsgLog->userref,
                    'timestamp' => $time,
                    'status' => 'new',
                    'message_status' => $deliveryStatus,
                    'reason' => '4',
                    'url' => $dreceiptUrl,
                    'inserted_time' => $time,
                    'retries_left' => $dreceiptUrlDetails->dreceipt_tries_num,
                    'wait_minutes' => $dreceiptUrlDetails->dreceipt_retries_wait_mins,
                    'dosendtime' => Carbon::now()->format('Y-m-d H:i:s'),
                    'xml' => $itagg_receipt_xml,
                    'dlr_daemon_id' => $dreceiptUrlDetails->dlr_daemon_id ?? 'default',
                    'apitype' => $dreceiptUrlDetails->apitype ?? 'w'
                ]);

                SmppLogger::sinch()->info("Sinch SMPP: DLR push callback queued", [
                    'bigid' => $bigid,
                    'url' => $dreceiptUrl
                ]);
            }
        } catch (Exception $e) {
            SmppLogger::sinch()->warning("Sinch SMPP: DLR push callback failed - " . $e->getMessage());
        }
    }

    /**
     * Map Sinch delivery status to internal status
     */
    private function mapDeliveryStatus(string $status): string
    {
        $statusMap = [
            'DELIVRD' => 'delivered',
            'DELIVERED' => 'delivered',
            'ACCEPTD' => 'accepted',
            'ACCEPTED' => 'accepted',
            'UNDELIV' => 'failed',
            'FAILED' => 'failed',
            'REJECTD' => 'rejected',
            'REJECTED' => 'rejected',
            'EXPIRED' => 'expired',
            'UNKNOWN' => 'unknown',
        ];

        return $statusMap[strtoupper($status)] ?? 'unknown';
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);

        if (substr($number, 0, 2) === '44' || substr($number, 0, 2) === '91') {
            return $number;
        }

        if (substr($number, 0, 1) === '0') {
            return '44' . substr($number, 1);
        }

        return $number;
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);

        // Common country codes
        $countryCodes = ['1', '7', '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41',
                         '43', '44', '45', '46', '47', '48', '49', '51', '52', '53', '54', '55', '56',
                         '57', '58', '60', '61', '62', '63', '64', '65', '66', '81', '82', '84', '86',
                         '90', '91', '92', '93', '94', '95', '98', '212', '213', '216', '218', '220',
                         '221', '222', '223', '224', '225', '226', '227', '228', '229', '230', '231',
                         '232', '233', '234', '235', '236', '237', '238', '239', '240', '241', '242',
                         '243', '244', '245', '246', '247', '248', '249', '250', '251', '252', '253',
                         '254', '255', '256', '257', '258', '260', '261', '262', '263', '264', '265',
                         '266', '267', '268', '269', '290', '291', '297', '298', '299', '350', '351',
                         '352', '353', '354', '355', '356', '357', '358', '359', '370', '371', '372',
                         '373', '374', '375', '376', '377', '378', '380', '381', '382', '385', '386',
                         '387', '389', '420', '421', '423', '500', '501', '502', '503', '504', '505',
                         '506', '507', '508', '509', '590', '591', '592', '593', '594', '595', '596',
                         '597', '598', '599', '670', '672', '673', '674', '675', '676', '677', '678',
                         '679', '680', '681', '682', '683', '685', '686', '687', '688', '689', '690',
                         '691', '692', '850', '852', '853', '855', '856', '880', '886', '960', '961',
                         '962', '963', '964', '965', '966', '967', '968', '970', '971', '972', '973',
                         '974', '975', '976', '977', '992', '993', '994', '995', '996', '998'];

        // Sort by length descending
        usort($countryCodes, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($countryCodes as $code) {
            if (substr($number, 0, strlen($code)) === $code) {
                return $code;
            }
        }

        return '44'; // Default to UK
    }

    /**
     * Detect if message needs UCS2 encoding
     */
    private function detectEncoding(string $message): string
    {
        // GSM whenever fully representable in GSM 03.38 (£, €, accents included) — OLD parity;
        // UCS2 only for genuine non-GSM content. Uses the shared GSM encoder so £ (UTF-8 or
        // Latin-1) is recognised correctly and never forces UCS2.
        return GsmCharacterConverter::encodeToGsm7bit($message)['fully_encodable'] ? 'gsm' : 'ucs2';
    }

    /**
     * Encode message as UCS2
     */
    private function encodeUcs2(string $message): string
    {
        return mb_convert_encoding($message, 'UCS-2BE', 'UTF-8');
    }

    /**
     * Format schedule_delivery_time for SMPP
     */
    private function formatScheduleTimeForSMPP(?string $scheduleTime): string
    {
        if (empty($scheduleTime)) {
            return '';
        }

        try {
            $dt = Carbon::parse($scheduleTime, 'Europe/London');
            $offsetMinutes = $dt->offsetMinutes;
            $quarterHours = abs($offsetMinutes) / 15;

            $formattedTime = $dt->format('ymdHis') . '0';
            $formattedTime .= sprintf('%02d', $quarterHours);
            $formattedTime .= ($offsetMinutes >= 0) ? '+' : '-';

            return $formattedTime;
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Pack bind transceiver PDU
     * Matches working Nexmo SMPP implementation
     */
    private function packBindTransceiver(): string
    {
        // Use explicit null termination like Nexmo (C-octet strings must be null-terminated)
        $systemType = $this->systemType ?: 'smpp'; // Default to 'smpp' if not set

        $body = pack('a' . (strlen($this->systemId) + 1), $this->systemId . chr(0));
        $body .= pack('a' . (strlen($this->password) + 1), $this->password . chr(0));
        $body .= pack('a' . (strlen($systemType) + 1), $systemType . chr(0));
        $body .= pack('CCC', 0x34, 0x00, 0x00); // interface_version (3.4), addr_ton, addr_npi
        $body .= pack('a1', chr(0)); // address_range null

        return $this->packPdu(self::BIND_TRANSCEIVER, $body);
    }

    /**
     * Pack bind_receiver PDU — same wire format as bind_transceiver, only the
     * command_id differs. Used by SinchDlrReceiver because OLD SYSTEM
     * (itagg_daemon_smpp_dlr_multi.php) uses bind_receiver and Sinch's
     * legacy-mode gateways allow N concurrent receivers per system_id, where
     * they reject the second concurrent transceiver with ESME_RALYBND.
     */
    private function packBindReceiver(): string
    {
        $systemType = $this->systemType ?: 'smpp';

        $body = pack('a' . (strlen($this->systemId) + 1), $this->systemId . chr(0));
        $body .= pack('a' . (strlen($this->password) + 1), $this->password . chr(0));
        $body .= pack('a' . (strlen($systemType) + 1), $systemType . chr(0));
        $body .= pack('CCC', 0x34, 0x00, 0x00);
        $body .= pack('a1', chr(0));

        return $this->packPdu(self::BIND_RECEIVER, $body);
    }

    /**
     * Pack submit_sm PDU for single (non-concatenated) messages
     * Note: Long messages are handled separately by sendConcatenatedSMS()
     */
    /**
     * Convert an ASCII message to the GSM 03.38 default alphabet (unpacked) for
     * data_coding = 0x00. Several ASCII bytes occupy different positions in the GSM
     * 7-bit alphabet; sending raw ASCII makes strict carriers (e.g. India) mis-render
     * them — most visibly '_' (0x5F) becoming '§' (GSM 0x5F). Shared-position chars
     * (A-Z, a-z, 0-9, space, . , / : ? & = …) pass through unchanged. Mirrors the OLD
     * SYSTEM gsmencoder utf8_to_gsm0338(). Only for data_coding 0x00 (pure-ASCII).
     */
    private function encodeGsm7bitDefault(string $text): string
    {
        static $map = [
            '@'  => "\x00",
            '$'  => "\x02",
            '_'  => "\x11",
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

    private function packSubmitSm(string $from, string $to, string $message, int $dataCoding, string $scheduleTime = ''): string
    {
        $body = pack('x'); // service_type null
        $body .= pack('CC', $this->sourceAddrTon, $this->sourceAddrNpi);
        $body .= pack('a' . (strlen($from) + 1), $from);
        $body .= pack('CC', $this->destAddrTon, $this->destAddrNpi);
        $body .= pack('a' . (strlen($to) + 1), $to);
        $body .= pack('CCC', 0x00, 0x00, 0x00); // esm_class, protocol_id, priority_flag

        // schedule_delivery_time
        $body .= pack('a' . (strlen($scheduleTime) + 1), $scheduleTime);

        // validity_period (null for default)
        $body .= pack('x');

        // registered_delivery (1 = delivery receipt requested)
        $body .= pack('C', 0x01);

        $body .= pack('C', 0x00); // replace_if_present_flag
        $body .= pack('CC', $dataCoding, 0x00); // data_coding, sm_default_msg_id

        // Single message - add message content directly (max 160 chars GSM / 140 bytes)
        // Long messages are handled by sendConcatenatedSMS() with UDH
        $body .= pack('C', strlen($message)); // sm_length
        $body .= $message; // short_message

        return $this->packPdu(self::SUBMIT_SM, $body);
    }

    /**
     * Pack PDU with header
     */
    private function packPdu(int $commandId, string $body): string
    {
        $sequenceNumber = $this->nextSequenceNumber();
        $commandLength = 16 + strlen($body);
        $commandStatus = 0;

        $header = pack('NNNN', $commandLength, $commandId, $commandStatus, $sequenceNumber);

        return $header . $body;
    }

    /**
     * Send PDU to socket
     */
    private function sendPdu(string $pdu): void
    {
        if (!$this->socket) {
            throw new Exception("Socket not connected");
        }

        $written = fwrite($this->socket, $pdu);

        if ($written === false || $written !== strlen($pdu)) {
            throw new Exception("Failed to send PDU");
        }
    }

    /**
     * Read PDU from socket
     */
    private function readPdu(): ?array
    {
        if (!$this->socket) {
            return null;
        }

        // Read header (16 bytes)
        $header = fread($this->socket, 16);

        if (strlen($header) < 16) {
            return null;
        }

        $unpacked = unpack('Ncommand_length/Ncommand_id/Ncommand_status/Nsequence_number', $header);

        $result = [
            'command_length' => $unpacked['command_length'],
            'command_id' => $unpacked['command_id'],
            'command_status' => $unpacked['command_status'],
            'sequence_number' => $unpacked['sequence_number']
        ];

        // Read body if present
        $bodyLength = $result['command_length'] - 16;
        if ($bodyLength > 0) {
            $body = fread($this->socket, $bodyLength);

            // Extract message_id for submit_sm_resp
            if ($result['command_id'] == self::SUBMIT_SM_RESP && $result['command_status'] == 0) {
                $messageId = rtrim($body, "\0");
                $result['message_id'] = $messageId;
            }
        }

        return $result;
    }

    /**
     * Get error message from status code
     */
    private function getErrorMessage(int $status): string
    {
        $errors = [
            0x00000001 => 'Invalid message length',
            0x00000002 => 'Invalid command length',
            0x00000003 => 'Invalid command ID',
            0x00000004 => 'Incorrect bind status',
            0x00000005 => 'Already bound',
            0x00000008 => 'System error',
            0x0000000A => 'Invalid source address',
            0x0000000B => 'Invalid destination address',
            0x0000000D => 'Bind failed',
            0x0000000E => 'Invalid password',
            0x0000000F => 'Invalid system ID',
            0x00000045 => 'Submit failed',
            0x00000058 => 'Throttling error',
        ];

        return $errors[$status] ?? "Unknown error (0x" . dechex($status) . ")";
    }

    /**
     * Handle deliver_sm PDU (DLR or inbound SMS)
     */
    public function handleDeliverSm(array $pdu, string $body): void
    {
        try {
            // Parse deliver_sm body
            $dlrData = $this->parseDeliverSm($body);

            if (!$dlrData) {
                SmppLogger::sinch()->warning("Sinch SMPP: Could not parse deliver_sm");
                return;
            }

            SmppLogger::sinch()->info("Sinch SMPP DLR received", $dlrData);

            // Check if this is a DLR (delivery receipt) or MO (mobile originated)
            $esmClass = $dlrData['esm_class'] ?? 0;

            if ($esmClass & 0x04) {
                // This is a DLR
                $this->processDlr($dlrData);
            } else {
                // This is an inbound SMS (MO) — process it (was previously only logged).
                SmppLogger::sinch()->info("Sinch SMPP: Inbound SMS received", $dlrData);
                $this->processInboundSms($dlrData);
            }

            // Send deliver_sm_resp
            $this->sendDeliverSmResp($pdu['sequence_number']);

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Failed to handle deliver_sm - " . $e->getMessage());
        }
    }

    /**
     * Parse deliver_sm PDU body
     */
    private function parseDeliverSm(string $body): ?array
    {
        try {
            $offset = 0;

            // service_type (null-terminated)
            $serviceType = $this->readCString($body, $offset);

            // source_addr_ton, source_addr_npi
            $sourceAddrTon = ord($body[$offset++]);
            $sourceAddrNpi = ord($body[$offset++]);

            // source_addr (null-terminated)
            $sourceAddr = $this->readCString($body, $offset);

            // dest_addr_ton, dest_addr_npi
            $destAddrTon = ord($body[$offset++]);
            $destAddrNpi = ord($body[$offset++]);

            // destination_addr (null-terminated)
            $destAddr = $this->readCString($body, $offset);

            // esm_class, protocol_id, priority_flag
            $esmClass = ord($body[$offset++]);
            $protocolId = ord($body[$offset++]);
            $priorityFlag = ord($body[$offset++]);

            // schedule_delivery_time (null-terminated)
            $scheduleTime = $this->readCString($body, $offset);

            // validity_period (null-terminated)
            $validityPeriod = $this->readCString($body, $offset);

            // registered_delivery, replace_if_present_flag, data_coding, sm_default_msg_id
            $registeredDelivery = ord($body[$offset++]);
            $replaceIfPresent = ord($body[$offset++]);
            $dataCoding = ord($body[$offset++]);
            $smDefaultMsgId = ord($body[$offset++]);

            // sm_length
            $smLength = ord($body[$offset++]);

            // short_message
            $shortMessage = substr($body, $offset, $smLength);
            $offset += $smLength;

            // Parse DLR content from short message
            $dlrInfo = $this->parseDlrContent($shortMessage);

            return array_merge([
                'service_type' => $serviceType,
                'source_addr' => $sourceAddr,
                'dest_addr' => $destAddr,
                'esm_class' => $esmClass,
                'data_coding' => $dataCoding,
                'short_message' => $shortMessage,
            ], $dlrInfo);

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Failed to parse deliver_sm - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Read null-terminated string from body
     */
    private function readCString(string $body, int &$offset): string
    {
        $str = '';
        while ($offset < strlen($body) && $body[$offset] !== "\0") {
            $str .= $body[$offset++];
        }
        $offset++; // Skip null terminator
        return $str;
    }

    /**
     * Parse DLR content (standard format)
     * Format: id:xxx sub:001 dlvrd:001 submit date:YYMMDDHHMM done date:YYMMDDHHMM stat:DELIVRD err:000 text:...
     */
    private function parseDlrContent(string $content): array
    {
        $result = [
            'message_id' => '',
            'status' => '',
            'error_code' => '',
            'submit_date' => '',
            'done_date' => '',
        ];

        // Parse id:
        if (preg_match('/id:([^\s]+)/', $content, $matches)) {
            $result['message_id'] = trim($matches[1]);
        }

        // Parse stat:
        if (preg_match('/stat:([^\s]+)/', $content, $matches)) {
            $result['status'] = trim($matches[1]);
        }

        // Parse err:
        if (preg_match('/err:([^\s]+)/', $content, $matches)) {
            $result['error_code'] = trim($matches[1]);
        }

        // Parse submit date:
        if (preg_match('/submit date:([^\s]+)/', $content, $matches)) {
            $result['submit_date'] = trim($matches[1]);
        }

        // Parse done date:
        if (preg_match('/done date:([^\s]+)/', $content, $matches)) {
            $result['done_date'] = trim($matches[1]);
        }

        return $result;
    }

    /**
     * Process DLR and update database (uses DeliveryStatusService for OLD SYSTEM compatible processing)
     */
    private function processDlr(array $dlrData): void
    {
        try {
            $messageId = $dlrData['message_id'] ?? '';
            $status = $dlrData['status'] ?? '';
            $mobileNumber = $dlrData['source'] ?? $dlrData['mobile_number'] ?? '';

            if (empty($messageId)) {
                SmppLogger::sinch()->warning("Sinch SMPP DLR: No message_id");
                return;
            }

            // Map SMPP status to OLD SYSTEM error code
            $errorCode = $this->mapSinchStatusToErrorCode($status);

            // Prepare DLR payload for DeliveryStatusService
            $dlrPayload = [
                'message_id' => $messageId,
                'mobile_number' => $mobileNumber,
                'status' => $status,
                'error_code' => $errorCode,
                'done_date' => Carbon::now('Europe/London')->format('YmdHis'),
                'provider' => 'sinch',
                'aggregator_code' => $dlrData['error_code'] ?? $errorCode,
                'aggregator_msg' => $dlrData['status_text'] ?? $status,
                'retry' => $dlrData['retry'] ?? '0',
                'raw_data' => $dlrData,
            ];

            // Use DeliveryStatusService for OLD SYSTEM compatible processing
            // This handles: smsg_log update, wallet refund, DLR push callback, master_msisdn update
            $deliveryStatusService = app(DeliveryStatusService::class);
            $result = $deliveryStatusService->processDeliveryReceipt($dlrPayload);

            if ($result) {
                SmppLogger::sinch()->info("Sinch SMPP DLR: Processed via DeliveryStatusService (OLD SYSTEM)", [
                    'message_id' => $messageId,
                    'status' => $status,
                    'error_code' => $errorCode
                ]);
            } else {
                // Fallback to legacy processing if DeliveryStatusService couldn't find the record
                $this->processDlrLegacy($dlrData);
            }

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP DLR: Processing failed - " . $e->getMessage());
        }
    }

    /**
     * Map Sinch SMPP status to OLD SYSTEM error/reason code
     */
    private function mapSinchStatusToErrorCode(string $status): int
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
     * Legacy DLR processing (fallback method)
     */
    private function processDlrLegacy(array $dlrData): void
    {
        $messageId = $dlrData['message_id'] ?? '';
        $status = $dlrData['status'] ?? '';

        // Map status
        $deliveryStatus = $this->mapDeliveryStatus($status);

        // Find the message mapping
        $mapping = DB::table('smpp_message_mapping')
            ->where('message_id', $messageId)
            ->where('provider', 'sinch')
            ->first();

        if (!$mapping) {
            // Try to find by suppliermsgref in smsg_log - ONLY NEW SYSTEM records
            $smsgLog = DB::table('smsg_log')
                ->where('suppliermsgref', $messageId)
                ->where('migration_flag', 'new')  // Only NEW system records
                ->first();

            if (!$smsgLog) {
                SmppLogger::sinch()->warning("Sinch SMPP DLR: No mapping found (legacy) or OLD system record", ['message_id' => $messageId]);
                return;
            }

            $bigid = $smsgLog->bigid;
        } else {
            $bigid = $mapping->bigid;
        }

        // Map to OLD SYSTEM delivery status
        $oldSystemStatus = $this->mapToOldSystemStatus($deliveryStatus);
        $errorCode = $this->mapSinchStatusToErrorCode($status);

        // Get upstream error message
        $deliveryStatusService = app(DeliveryStatusService::class);
        $upstreamErrorMessage = $deliveryStatusService->getUpstreamErrorMessage($errorCode);

        // Update smsg_log with OLD SYSTEM compatible fields
        $updateData = [
            'deliverystatus1' => 'acked',
            'deliverystatus2' => $oldSystemStatus,
            'deliverytime2' => Carbon::now('Europe/London')->format('YmdHi'),  // OLD SYSTEM parity: deliverytime2 stored in GMT/UTC; display converts +1h BST
            'upstream_errormessage' => $upstreamErrorMessage,
            'delivery_reason' => $errorCode,
            'aggregator_dlrcode' => $errorCode,
            'aggregator_dlrmsg' => $status,
        ];

        if ($deliveryStatus === 'delivered') {
            $updateData['sentstatus'] = 'ok';
            $updateData['sentstatustext'] = 'Delivered via Sinch SMPP';
        } elseif (in_array($deliveryStatus, ['failed', 'rejected', 'expired'])) {
            $updateData['sentstatus'] = 'fail';
            $updateData['sentstatustext'] = 'Delivery failed: ' . $status;
        }

        DB::table('smsg_log')
            ->where('bigid', $bigid)
            ->where('migration_flag', 'new')  // Only NEW system records
            ->update($updateData);

        SmppLogger::sinch()->info("Sinch SMPP DLR: Updated via legacy method (OLD SYSTEM format)", [
            'message_id' => $messageId,
            'bigid' => $bigid,
            'status' => $oldSystemStatus
        ]);
    }

    /**
     * Map delivery status to OLD SYSTEM format
     */
    private function mapToOldSystemStatus(string $status): string
    {
        $statusMap = [
            'delivered' => 'Delivered',
            'expired' => 'Non Delivered',
            'deleted' => 'Non Delivered',
            'failed' => 'Non Delivered',
            'rejected' => 'Non Delivered',
            'accepted' => 'acked',
            'unknown' => 'Unknown',
            'buffered' => 'buffered smsc',
        ];

        return $statusMap[strtolower($status)] ?? 'Unknown';
    }

    /**
     * Send deliver_sm_resp
     */
    private function sendDeliverSmResp(int $sequenceNumber): void
    {
        try {
            $body = pack('x'); // message_id null
            $commandLength = 16 + strlen($body);

            $header = pack('NNNN', $commandLength, self::DELIVER_SM_RESP, 0, $sequenceNumber);
            $pdu = $header . $body;

            $this->sendPdu($pdu);

            SmppLogger::sinch()->debug("Sinch SMPP: deliver_sm_resp sent");
        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Failed to send deliver_sm_resp - " . $e->getMessage());
        }
    }

    /**
     * Send enquire_link to keep connection alive
     */
    public function enquireLink(): bool
    {
        if (!$this->isConnected || !$this->isBound) {
            return false;
        }

        try {
            $pdu = $this->packPdu(self::ENQUIRE_LINK, '');
            $this->sendPdu($pdu);

            $response = $this->readPdu();

            if ($response && $response['command_id'] == self::ENQUIRE_LINK_RESP) {
                SmppLogger::sinch()->debug("Sinch SMPP: enquire_link successful");
                return true;
            }

            return false;
        } catch (Exception $e) {
            SmppLogger::sinch()->warning("Sinch SMPP: enquire_link failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Listen for incoming PDUs (DLRs)
     */
    public function listenForDlr(int $timeout = 5): void
    {
        if (!$this->socket) {
            return;
        }

        stream_set_timeout($this->socket, $timeout);

        $pdu = $this->readPdu();

        if ($pdu) {
            if ($pdu['command_id'] == self::DELIVER_SM) {
                // Read body and handle
                $bodyLength = $pdu['command_length'] - 16;
                if ($bodyLength > 0) {
                    // Body was already read in readPdu, need to re-read
                    // For now, log that we received a deliver_sm
                    SmppLogger::sinch()->info("Sinch SMPP: Received deliver_sm", $pdu);
                }
            } elseif ($pdu['command_id'] == self::ENQUIRE_LINK) {
                // Respond to enquire_link from server
                $this->sendEnquireLinkResp($pdu['sequence_number']);
            }
        }
    }

    /**
     * Send enquire_link_resp
     */
    private function sendEnquireLinkResp(int $sequenceNumber): void
    {
        try {
            $commandLength = 16;
            $header = pack('NNNN', $commandLength, self::ENQUIRE_LINK_RESP, 0, $sequenceNumber);

            $this->sendPdu($header);

            SmppLogger::sinch()->debug("Sinch SMPP: enquire_link_resp sent");
        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Failed to send enquire_link_resp - " . $e->getMessage());
        }
    }

    /**
     * Disconnect from SMPP server
     */
    public function disconnect(): void
    {
        // Use is_resource() — after a remote-side close (bind failure, network
        // drop) the $socket variable is still truthy but no longer a valid
        // stream, and fclose() on it throws TypeError in PHP 8.
        $hasLiveSocket = is_resource($this->socket);

        if ($this->isBound && $hasLiveSocket) {
            try {
                $pdu = $this->packPdu(self::UNBIND, '');
                $this->sendPdu($pdu);
                $this->readPdu();
            } catch (\Throwable $e) {
                SmppLogger::sinch()->warning("Sinch SMPP: Unbind warning - " . $e->getMessage());
            }
        }

        if ($hasLiveSocket) {
            try {
                fclose($this->socket);
            } catch (\Throwable $e) {
                // Stream may have died between is_resource check and fclose
                SmppLogger::sinch()->debug("Sinch SMPP: fclose warning - " . $e->getMessage());
            }
        }

        $this->socket = null;
        $this->isConnected = false;
        $this->isBound = false;
    }

    /**
     * Check if ready
     */
    public function isReady(): bool
    {
        return $this->isConnected && $this->isBound;
    }

    /**
     * Destructor - only disconnect if not in persistent mode
     */
    public function __destruct()
    {
        if (!$this->persistentMode) {
            $this->disconnect();
        }
    }

    /**
     * Enable persistent mode (connection won't auto-close)
     */
    public function setPersistentMode(bool $enabled): void
    {
        $this->persistentMode = $enabled;
    }

    /**
     * Check for and process incoming DLRs (non-blocking)
     * Call this periodically to receive delivery receipts
     *
     * @param int $timeout Timeout in seconds for checking (0 = non-blocking)
     * @return int Number of DLRs processed
     */
    public function checkForDlr(int $timeout = 0): int
    {
        if (!$this->socket || !$this->isBound) {
            SmppLogger::sinch()->debug("Sinch SMPP checkForDlr: Not connected/bound");
            return 0;
        }

        $dlrCount = 0;
        $startTime = time();

        do {
            // Non-blocking check for incoming data
            $read = [$this->socket];
            $write = null;
            $except = null;

            $changed = @stream_select($read, $write, $except, 0, 500000); // 500ms timeout

            if ($changed === false) {
                // Error - check if socket is still valid
                SmppLogger::sinch()->warning("Sinch SMPP checkForDlr: stream_select error");
                break;
            }

            if ($changed === 0) {
                // No data available
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    break;
                }
                continue;
            }

            // Data available, read PDU
            SmppLogger::sinch()->info("Sinch SMPP: Data available on socket, reading PDU...");

            stream_set_blocking($this->socket, true);
            stream_set_timeout($this->socket, 5);

            $header = @fread($this->socket, 16);

            if (strlen($header) < 16) {
                SmppLogger::sinch()->warning("Sinch SMPP: Incomplete header received", ['length' => strlen($header)]);
                stream_set_blocking($this->socket, false);
                break;
            }

            $unpacked = unpack('Ncommand_length/Ncommand_id/Ncommand_status/Nsequence_number', $header);

            $pdu = [
                'command_length' => $unpacked['command_length'],
                'command_id' => $unpacked['command_id'],
                'command_status' => $unpacked['command_status'],
                'sequence_number' => $unpacked['sequence_number'],
                'body' => ''
            ];

            SmppLogger::sinch()->info("Sinch SMPP: PDU received", [
                'command_id' => sprintf('0x%08X', $pdu['command_id']),
                'command_length' => $pdu['command_length'],
                'command_status' => $pdu['command_status']
            ]);

            // Read body
            $bodyLength = $pdu['command_length'] - 16;
            if ($bodyLength > 0) {
                $pdu['body'] = @fread($this->socket, $bodyLength);
                SmppLogger::sinch()->debug("Sinch SMPP: PDU body read", ['body_length' => strlen($pdu['body'])]);
            }

            stream_set_blocking($this->socket, false);

            // Handle the PDU
            switch ($pdu['command_id']) {
                case self::DELIVER_SM:
                    SmppLogger::sinch()->info("Sinch SMPP: DELIVER_SM received (DLR or Inbound)");
                    $this->handleDeliverSmFromQueue($pdu);
                    $dlrCount++;
                    break;

                case self::ENQUIRE_LINK:
                    SmppLogger::sinch()->debug("Sinch SMPP: ENQUIRE_LINK from server, sending response");
                    $this->sendEnquireLinkResp($pdu['sequence_number']);
                    break;

                case self::ENQUIRE_LINK_RESP:
                    SmppLogger::sinch()->debug("Sinch SMPP: ENQUIRE_LINK_RESP received");
                    break;

                case self::SUBMIT_SM_RESP:
                    SmppLogger::sinch()->debug("Sinch SMPP: SUBMIT_SM_RESP received (late response)", [
                        'status' => $pdu['command_status']
                    ]);
                    break;

                default:
                    SmppLogger::sinch()->info("Sinch SMPP: Unknown PDU received", [
                        'command_id' => sprintf('0x%08X', $pdu['command_id']),
                        'command_status' => $pdu['command_status']
                    ]);
            }

            // Continue checking if we have time left
        } while ($timeout > 0 && (time() - $startTime) < $timeout);

        return $dlrCount;
    }

    /**
     * Handle deliver_sm from queue processor
     */
    private function handleDeliverSmFromQueue(array $pdu): void
    {
        try {
            $body = $pdu['body'] ?? '';
            $dlrData = $this->parseDeliverSm($body);

            if (!$dlrData) {
                SmppLogger::sinch()->warning("Sinch SMPP: Could not parse deliver_sm", [
                    'body_length' => strlen($body),
                    'body_hex' => bin2hex(substr($body, 0, 50))
                ]);
                $this->sendDeliverSmResp($pdu['sequence_number']);
                return;
            }

            // Check if this is a DLR or MO (Inbound)
            // esm_class bit 2 (0x04) = Delivery Receipt
            // esm_class = 0 = Normal message (Inbound MO)
            $esmClass = $dlrData['esm_class'] ?? 0;

            SmppLogger::sinch()->info("Sinch SMPP DELIVER_SM parsed", [
                'esm_class' => $esmClass,
                'esm_class_hex' => sprintf('0x%02X', $esmClass),
                'is_dlr' => ($esmClass & 0x04) ? 'yes' : 'no',
                'source' => $dlrData['source_addr'] ?? '',
                'dest' => $dlrData['dest_addr'] ?? '',
                'message_preview' => substr($dlrData['short_message'] ?? '', 0, 50)
            ]);

            if ($esmClass & 0x04) {
                // This is a DLR (Delivery Receipt)
                SmppLogger::sinch()->info("Sinch SMPP: Processing as DLR (Delivery Receipt)", [
                    'message_id' => $dlrData['message_id'] ?? '',
                    'status' => $dlrData['status'] ?? ''
                ]);
                $this->processDlr($dlrData);
            } else {
                // This is an inbound SMS (MO - Mobile Originated)
                SmppLogger::sinch()->info("Sinch SMPP: Processing as Inbound SMS (MO)", [
                    'from' => $dlrData['source_addr'] ?? '',
                    'to' => $dlrData['dest_addr'] ?? '',
                    'message' => $dlrData['short_message'] ?? ''
                ]);
                $this->processInboundSms($dlrData);
            }

            // Send deliver_sm_resp
            $this->sendDeliverSmResp($pdu['sequence_number']);

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP: Failed to handle deliver_sm - " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Process inbound SMS - Queue to RabbitMQ for processing by InboundSmsProcessor
     */
    private function processInboundSms(array $data): void
    {
        $from = $data['source_addr'] ?? '';
        $to = $data['dest_addr'] ?? '';
        $message = $data['short_message'] ?? '';

        // Clean phone numbers (remove + prefix if present)
        $from = ltrim($from, '+');
        $to = ltrim($to, '+');

        SmppLogger::sinch()->info("Sinch SMPP Inbound SMS received", [
            'from' => $from,
            'to' => $to,
            'message' => $message
        ]);

        try {
            // Build inbound data in same format as HTTP webhook
            $requestId = uniqid('smpp_inbound_', true);

            $inboundData = [
                'source' => $from,
                'dest' => $to,
                'message' => $message,
                'network' => '99',
                'operator_message_id' => $requestId,
                'operator_id' => '',
                'provider' => 'sinch_smpp',
                'request_id' => $requestId,
                'received_at' => Carbon::now()->toIso8601String(),
                'ip_address' => 'smpp',
                'user_agent' => 'Sinch SMPP',
                'raw_request' => $data,
            ];

            // Process the inbound MO DIRECTLY in this receiver — exactly how DLRs are
            // handled (processDlr) — so it always lands in itagg_incominglog and does
            // NOT depend on a separate sms.inbound queue consumer being up. That was
            // the bug: inbound was only published to the sms.inbound queue, so if that
            // consumer wasn't running, the MO message was silently lost (while DLRs,
            // processed inline, kept working).
            $this->processInboundSmsDirect($inboundData);

            SmppLogger::sinch()->info("Sinch SMPP Inbound SMS processed", [
                'request_id' => $requestId,
                'from' => $from,
                'to' => $to,
            ]);

        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP Inbound SMS processing failed: " . $e->getMessage());
        }
    }

    /**
     * Process inbound SMS directly (fallback when RabbitMQ unavailable)
     */
    private function processInboundSmsDirect(array $inboundData): void
    {
        try {
            // Use the InboundSmsProcessor service if available
            $processor = app(\App\Services\InboundSmsProcessor::class);
            $processor->process($inboundData);

            SmppLogger::sinch()->info("Sinch SMPP Inbound SMS processed directly", [
                'request_id' => $inboundData['request_id'] ?? null,
                'from' => $inboundData['source'] ?? null
            ]);
        } catch (Exception $e) {
            SmppLogger::sinch()->error("Sinch SMPP Inbound SMS direct processing failed: " . $e->getMessage());
        }
    }

    /**
     * Send enquire_link to keep connection alive
     * Call this periodically (every 30 seconds)
     *
     * @return bool True if successful
     */
    public function sendEnquireLink(): bool
    {
        if (!$this->isConnected || !$this->isBound || !$this->socket) {
            return false;
        }

        try {
            $pdu = $this->packPdu(self::ENQUIRE_LINK, '');
            $this->sendPdu($pdu);

            // Wait for response (with short timeout)
            stream_set_blocking($this->socket, true);
            stream_set_timeout($this->socket, 5);

            $response = $this->readPdu();

            stream_set_blocking($this->socket, false);

            if ($response && $response['command_id'] == self::ENQUIRE_LINK_RESP) {
                SmppLogger::sinch()->debug("Sinch SMPP: enquire_link successful");
                return true;
            }

            return false;
        } catch (Exception $e) {
            SmppLogger::sinch()->warning("Sinch SMPP: enquire_link failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get socket for external monitoring
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Check if still connected and bound
     */
    public function isStillConnected(): bool
    {
        if (!$this->socket || !$this->isConnected || !$this->isBound) {
            return false;
        }

        // Check if socket is still valid
        $meta = @stream_get_meta_data($this->socket);
        if ($meta && $meta['eof']) {
            $this->isConnected = false;
            $this->isBound = false;
            return false;
        }

        return true;
    }

    /**
     * Reconnect if disconnected
     *
     * @return bool True if connected/reconnected successfully
     */
    public function ensureConnected(): bool
    {
        if ($this->isStillConnected()) {
            return true;
        }

        SmppLogger::sinch()->info("Sinch SMPP: Reconnecting...");

        // Close existing socket if any
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }

        $this->isConnected = false;
        $this->isBound = false;

        // Reconnect
        if ($this->connect() && $this->bind()) {
            SmppLogger::sinch()->info("Sinch SMPP: Reconnected successfully");
            return true;
        }

        SmppLogger::sinch()->error("Sinch SMPP: Failed to reconnect");
        return false;
    }
}
