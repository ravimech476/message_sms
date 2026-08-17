<?php

namespace App\Services\Smpp;

use Exception;
use GsmEncoder;

/**
 * SMPP send service (Phase 2) — ported from sms_expert's RAW-SOCKET approach.
 *
 * Uses fsockopen + manually-built SMPP 3.4 PDUs (bind_transmitter, submit_sm),
 * NOT vanilla php-smpp's SmppClient (whose socket_select/isOpen handling is
 * incompatible with Vonage — bind succeeds then the submit response reads false).
 * php-smpp's GsmEncoder is still reused for GSM 03.38 encoding.
 *
 * Bank-aware: pass a bank key from config/smpp_banks.php, or use the single-bind config.
 *
 * @author Anand Karthik
 */
class SmppService
{
    // SMPP command IDs
    const BIND_RECEIVER         = 0x00000001;
    const BIND_RECEIVER_RESP    = 0x80000001;
    const BIND_TRANSMITTER      = 0x00000002;
    const BIND_TRANSMITTER_RESP = 0x80000002;
    const SUBMIT_SM             = 0x00000004;
    const SUBMIT_SM_RESP        = 0x80000004;
    const DELIVER_SM            = 0x00000005;
    const DELIVER_SM_RESP       = 0x80000005;
    const UNBIND                = 0x00000006;
    const ENQUIRE_LINK          = 0x00000015;
    const ENQUIRE_LINK_RESP     = 0x80000015;
    const ESME_ROK              = 0x00000000;
    const ESM_DELIVER_SMSC_RECEIPT = 0x04;

    private $socket;
    private bool $bound = false;
    private int $sequenceNumber = 0;

    private string $host;
    private int $port;
    private $systemId;
    private $password;
    private string $systemType;
    private ?string $bankKey;
    private int $seqMin = 1;
    private int $seqMax = 0x7FFFFFFF;
    private ?string $lastSubmissionPrice = null;
    private ?int $concatRefSlotBase = null;   // per-worker slice of the 0-255 concat-reference space
    private int $concatRefCounter = 0;

    public bool $debug = false;

    public function __construct(?string $bankKey = null)
    {
        $this->bankKey = $bankKey;

        if ($bankKey && config('smpp_banks.enabled')) {
            $bank = config("smpp_banks.banks.$bankKey");
            if (!$bank) {
                throw new Exception("SMPP bank '$bankKey' not found in config/smpp_banks.php");
            }
            $this->host = $bank['host'];
            $this->port = (int) $bank['port'];
            $this->systemId = $bank['system_id'];
            $this->password = $bank['password'];
            $this->systemType = (string) $bank['system_type'];
            [$this->seqMin, $this->seqMax] = $bank['seq_id_range'] ?? [1, 0x7FFFFFFF];
            $this->sequenceNumber = $this->seqMin - 1;
        } else {
            $this->host = config('smpp.host');
            $this->port = (int) config('smpp.port');
            $this->systemId = config('smpp.system_id');
            $this->password = config('smpp.password');
            $this->systemType = (string) config('smpp.system_type');
        }
    }

    /** fsockopen + bind_transmitter (send). */
    public function connect(): bool
    {
        $this->openSocket();
        return $this->bind(false);
    }

    /** fsockopen + bind_receiver (DLR/MO). */
    public function connectReceiver(): bool
    {
        $this->openSocket();
        return $this->bind(true);
    }

    private function openSocket(): void
    {
        if (empty($this->systemId) || empty($this->password)) {
            throw new Exception('SMPP credentials (system_id/password) are not configured.');
        }
        $errno = 0;
        $errstr = '';
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new Exception("Failed to connect to {$this->host}:{$this->port} — $errstr ($errno)");
        }
        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, 60);
        $this->log("connected to {$this->host}:{$this->port}");
    }

    private function bind(bool $receiver): bool
    {
        $command = $receiver ? self::BIND_RECEIVER : self::BIND_TRANSMITTER;
        $respCmd = $receiver ? self::BIND_RECEIVER_RESP : self::BIND_TRANSMITTER_RESP;

        // bind body: system_id, password, system_type (null-terminated),
        // interface_version (0x34), addr_ton, addr_npi, address_range.
        $body  = $this->systemId . "\x00";
        $body .= $this->password . "\x00";
        $body .= $this->systemType . "\x00";
        $body .= pack('CCC', 0x34, 0x00, 0x00);
        $body .= "\x00";

        $this->sendPDU($this->buildPDU($command, $body, 0, $this->nextSequenceNumber()));

        $resp = $this->readPDU(true);
        if (!$resp) {
            throw new Exception('No bind response from SMPP server');
        }
        if ($resp['command_id'] !== $respCmd) {
            throw new Exception('Unexpected bind response command 0x' . sprintf('%08X', $resp['command_id']));
        }
        if ($resp['command_status'] !== self::ESME_ROK) {
            throw new Exception('Bind rejected — status 0x' . sprintf('%08X', $resp['command_status']));
        }

        $this->bound = true;
        $this->log(($receiver ? 'bind_receiver' : 'bind_transmitter') . ' OK');
        \App\Services\Logging\ComponentLogger::smpp()->info(
            ($receiver ? 'bind_receiver' : 'bind_transmitter') . ' OK',
            ['host' => $this->host, 'system_id' => $this->systemId, 'bank' => $this->bankKey]
        );
        return true;
    }

    /**
     * Receiver loop: read deliver_sm DLRs and hand each parsed DLR array to $onDlr.
     * Responds to deliver_sm and enquire_link; sends periodic enquire_link keepalives.
     * Runs for up to $maxSeconds (0 = forever).
     */
    public function listenForDlr(callable $onDlr, int $maxSeconds = 0): void
    {
        $start = time();
        $lastEnquire = time();

        while ($this->isSocketValid()) {
            if ($maxSeconds > 0 && (time() - $start) >= $maxSeconds) {
                break;
            }
            // keepalive every 25s
            if (time() - $lastEnquire >= 25) {
                $this->sendPDU($this->buildPDU(self::ENQUIRE_LINK, '', 0, $this->nextSequenceNumber()));
                $lastEnquire = time();
            }

            $pdu = $this->readPDU(false); // non-blocking
            if (!$pdu) {
                usleep(200000); // 200ms
                continue;
            }

            switch ($pdu['command_id']) {
                case self::DELIVER_SM:
                    $dlr = $this->parseDeliverSm($pdu['body']);
                    // respond first so Vonage never blocks
                    $this->sendPDU($this->buildPDU(self::DELIVER_SM_RESP, "\x00", 0, $pdu['sequence_number']));
                    if ($dlr && !empty($dlr['is_dlr'])) {
                        $onDlr($dlr);
                    }
                    break;
                case self::ENQUIRE_LINK:
                    $this->sendPDU($this->buildPDU(self::ENQUIRE_LINK_RESP, '', 0, $pdu['sequence_number']));
                    break;
                case self::ENQUIRE_LINK_RESP:
                default:
                    break; // ignore
            }
        }
    }

    /** Parse a deliver_sm body → ['is_dlr'=>bool, 'message_id'=>.., 'status'=>.., 'done_date'=>.., 'err'=>..]. */
    private function parseDeliverSm(string $body): ?array
    {
        try {
            $pos = 0;
            $this->readCString($body, $pos);                 // service_type
            $pos += 2; $this->readCString($body, $pos);      // source ton/npi + addr
            $pos += 2; $this->readCString($body, $pos);      // dest ton/npi + addr
            $esmClass = ord($body[$pos++]);
            $pos += 2;                                        // protocol_id, priority
            $this->readCString($body, $pos);                 // schedule_delivery_time
            $this->readCString($body, $pos);                 // validity_period
            $pos += 4;                                        // reg_delivery, replace, data_coding, sm_default
            $smLength = ord($body[$pos++]);
            $shortMessage = substr($body, $pos, $smLength);
            $pos += $smLength;

            if (!($esmClass & self::ESM_DELIVER_SMSC_RECEIPT)) {
                return ['is_dlr' => false]; // inbound MO, not a DLR
            }

            $dlr = $this->parseDlrText($shortMessage);
            $dlr['is_dlr'] = true;

            // Authoritative id = receipted_message_id TLV (0x001E), if present.
            $tlvId = $this->extractReceiptedMessageId($body, $pos);
            if (!empty($tlvId)) {
                $dlr['message_id'] = $tlvId;
            }
            return $dlr;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** DLR text: "id:X sub:1 dlvrd:1 submit date:.. done date:YYMMDDHHMM stat:DELIVRD err:000 text:..". */
    private function parseDlrText(string $text): array
    {
        $out = ['message_id' => null, 'status' => null, 'done_date' => null, 'err' => null];
        if (preg_match('/\bid:(\S+)/i', $text, $m)) $out['message_id'] = $m[1];
        if (preg_match('/\bstat:(\w+)/i', $text, $m)) $out['status'] = strtoupper($m[1]);
        if (preg_match('/\bdone date:(\d+)/i', $text, $m)) $out['done_date'] = $m[1];
        if (preg_match('/\berr:(\w+)/i', $text, $m)) $out['err'] = $m[1];
        return $out;
    }

    private function extractReceiptedMessageId(string $body, int $pos): ?string
    {
        $len = strlen($body);
        while ($pos + 4 <= $len) {
            $tag = unpack('n', substr($body, $pos, 2))[1]; $pos += 2;
            $tlvLen = unpack('n', substr($body, $pos, 2))[1]; $pos += 2;
            if ($tlvLen < 0 || $pos + $tlvLen > $len) break;
            $value = substr($body, $pos, $tlvLen); $pos += $tlvLen;
            if ($tag === 0x001E) {
                return rtrim($value, "\x00");
            }
        }
        return null;
    }

    private function readCString(string $body, int &$pos): string
    {
        $nullPos = strpos($body, "\x00", $pos);
        if ($nullPos === false) {
            $s = substr($body, $pos);
            $pos = strlen($body);
            return $s;
        }
        $s = substr($body, $pos, $nullPos - $pos);
        $pos = $nullPos + 1;
        return $s;
    }

    /**
     * Send one SMS via submit_sm. Returns the provider message-id (from submit_sm_resp).
     */
    public function sendSms(string $to, string $message, string $from): string
    {
        if (!$this->bound) {
            throw new Exception('Not bound to SMPP server');
        }

        $to = ltrim(preg_replace('/\s+/', '', $to), '+');
        [$shortMessage, $dataCoding, $srcTon] = $this->encode($message, $from);

        // Too long for one SMS → split into concatenated parts (UDH). GSM single =
        // 160 septets (=bytes, unpacked); UCS2 single = 140 octets (70 chars).
        $singleLimit = ($dataCoding === 0x08) ? 140 : 160;
        if (strlen($shortMessage) > $singleLimit) {
            return $this->sendConcatenated($to, $shortMessage, $dataCoding, $srcTon, $from);
        }

        // submit_sm body (SMPP 3.4)
        $body  = "\x00";                                  // service_type (empty)
        $body .= pack('C', $srcTon);                      // source_addr_ton
        $body .= pack('C', 0x00);                         // source_addr_npi (0 = unknown, matches sms_expert)
        $body .= $from . "\x00";                          // source_addr
        $body .= pack('C', 0x01);                         // dest_addr_ton (international)
        $body .= pack('C', 0x01);                         // dest_addr_npi (E.164)
        $body .= $to . "\x00";                            // destination_addr
        $body .= pack('C', 0x00);                         // esm_class
        $body .= pack('C', 0x00);                         // protocol_id
        $body .= pack('C', 0x00);                         // priority_flag
        $body .= "\x00";                                  // schedule_delivery_time (empty)
        $body .= "\x00";                                  // validity_period (empty → SMSC default)
        $body .= pack('C', 0x01);                         // registered_delivery = 1 (request DLR)
        $body .= pack('C', 0x00);                         // replace_if_present_flag
        $body .= pack('C', $dataCoding);                  // data_coding
        $body .= pack('C', 0x00);                         // sm_default_msg_id
        $body .= pack('C', strlen($shortMessage));        // sm_length
        $body .= $shortMessage;                           // short_message
        // Ask Vonage to return pricing: TLV 0x1421=0x31 → submit_sm_resp carries the
        // per-SMS price in TLV 0x1422 (and balance 0x1423). Same as sms_expert.
        $body .= pack('nnC', 0x1421, 1, 0x31);

        $seq = $this->nextSequenceNumber();
        $this->sendPDU($this->buildPDU(self::SUBMIT_SM, $body, 0, $seq));

        $resp = $this->readPDU(true);
        if (!$resp) {
            throw new Exception('No submit_sm response');
        }
        if ($resp['command_id'] !== self::SUBMIT_SM_RESP) {
            throw new Exception('Unexpected submit response command 0x' . sprintf('%08X', $resp['command_id']));
        }
        if ($resp['command_status'] !== self::ESME_ROK) {
            throw new Exception('submit_sm rejected — status 0x' . sprintf('%08X', $resp['command_status']));
        }

        $this->lastSubmissionPrice = $this->parseSubmissionPrice($resp['body']);
        $messageId = $this->parseMessageId($resp['body']);
        \App\Services\Logging\ComponentLogger::smpp()->info('SUBMIT_SM ok', [
            'to' => $to, 'from' => $from, 'id' => $messageId,
            'dc' => $dataCoding, 'parts' => 1, 'price' => $this->lastSubmissionPrice,
        ]);
        return $messageId;
    }

    /** Vonage per-SMS price (TLV 0x1422) from the last submit_sm_resp, or null. */
    public function getLastPrice(): ?string
    {
        return $this->lastSubmissionPrice;
    }

    /**
     * Send a long message as concatenated SMS (multiple submit_sm with a UDH so the
     * handset reassembles them). Returns the LAST part's message-id (used for DLR
     * matching — same as sms_expert); lastSubmissionPrice = sum of the parts' prices.
     */
    private function sendConcatenated(string $to, string $encoded, int $dataCoding, int $srcTon, string $from): string
    {
        $parts = $this->splitEncoded($encoded, $dataCoding);
        $total = count($parts);
        $ref   = $this->getConcatRef();

        $lastId   = '';
        $priceSum = 0.0;
        $anyPrice = false;

        foreach ($parts as $i => $part) {
            $partNum = $i + 1;
            $payload = $this->generateUDH($ref, $total, $partNum) . $part;

            $body  = "\x00";                              // service_type
            $body .= pack('C', $srcTon);                  // source_addr_ton
            $body .= pack('C', 0x00);                     // source_addr_npi
            $body .= $from . "\x00";                      // source_addr
            $body .= pack('C', 0x01);                     // dest_addr_ton
            $body .= pack('C', 0x01);                     // dest_addr_npi
            $body .= $to . "\x00";                        // destination_addr
            $body .= pack('C', 0x40);                     // esm_class = UDHI (UDH present)
            $body .= pack('C', 0x00);                     // protocol_id
            $body .= pack('C', 0x00);                     // priority_flag
            $body .= "\x00";                              // schedule_delivery_time
            $body .= "\x00";                              // validity_period
            $body .= pack('C', 0x01);                     // registered_delivery = 1
            $body .= pack('C', 0x00);                     // replace_if_present
            $body .= pack('C', $dataCoding);              // data_coding
            $body .= pack('C', 0x00);                     // sm_default_msg_id
            $body .= pack('C', strlen($payload));         // sm_length (UDH + content)
            $body .= $payload;                            // short_message
            $body .= pack('nnC', 0x1421, 1, 0x31);        // request Vonage pricing

            $this->sendPDU($this->buildPDU(self::SUBMIT_SM, $body, 0, $this->nextSequenceNumber()));

            $resp = $this->readPDU(true);
            if (!$resp || $resp['command_id'] !== self::SUBMIT_SM_RESP) {
                throw new Exception("No submit_sm response for part {$partNum}/{$total}");
            }
            if ($resp['command_status'] !== self::ESME_ROK) {
                throw new Exception("Part {$partNum}/{$total} rejected — status 0x" . sprintf('%08X', $resp['command_status']));
            }

            $lastId = $this->parseMessageId($resp['body']); // keep the last part's id (DLR match)
            $price = $this->parseSubmissionPrice($resp['body']);
            if ($price !== null) {
                $priceSum += (float) $price;
                $anyPrice = true;
            }
        }

        $this->lastSubmissionPrice = $anyPrice ? number_format($priceSum, 5, '.', '') : null;
        \App\Services\Logging\ComponentLogger::smpp()->info('SUBMIT_SM ok (concatenated)', [
            'to' => $to, 'from' => $from, 'id' => $lastId,
            'dc' => $dataCoding, 'parts' => $total, 'price' => $this->lastSubmissionPrice,
        ]);
        return $lastId;
    }

    /**
     * Split an already-encoded message into concatenated-SMS parts.
     *   UCS2 : 132 octets/part (byte split — never overflows, emoji-safe).
     *   GSM  : 153 septets/part (unpacked = 1 byte/septet); never end a part on a
     *          lone 0x1B escape (would split an extended char across parts).
     */
    private function splitEncoded(string $encoded, int $dataCoding): array
    {
        if ($dataCoding === 0x08) {
            return str_split($encoded, 132);
        }

        $parts = [];
        $len = strlen($encoded);
        $pos = 0;
        while ($pos < $len) {
            $take = min(153, $len - $pos);
            if ($take === 153 && $encoded[$pos + $take - 1] === "\x1B") {
                $take--; // push the escape (and its following char) into the next part
            }
            $parts[] = substr($encoded, $pos, $take);
            $pos += $take;
        }
        return $parts ?: [''];
    }

    /** 6-byte UDH for concatenated SMS: 05 00 03 <ref> <total> <part>. */
    private function generateUDH(int $ref, int $total, int $part): string
    {
        return pack('CCCCCC', 0x05, 0x00, 0x03, $ref & 0xFF, $total, $part);
    }

    /**
     * Unique concat reference (0-255), partitioned per worker (6-value slice keyed
     * off bankKey) so parallel senders never collide on the same phone. Mirrors
     * sms_expert's getConcatReferenceNumber.
     */
    private function getConcatRef(): int
    {
        $slotSize = 6;
        $slots = intdiv(256, $slotSize);
        if ($this->concatRefSlotBase === null) {
            $workerIndex = crc32((string) ($this->bankKey ?? $this->seqMin)) % $slots;
            $this->concatRefSlotBase = $workerIndex * $slotSize;
        }
        $ref = $this->concatRefSlotBase + ($this->concatRefCounter % $slotSize);
        $this->concatRefCounter++;
        return $ref;
    }

    public function close(): void
    {
        try {
            if ($this->isSocketValid()) {
                if ($this->bound) {
                    $this->sendPDU($this->buildPDU(self::UNBIND, '', 0, $this->nextSequenceNumber()));
                    $this->readPDU(true);
                }
                @fclose($this->socket);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $this->bound = false;
    }

    public function bankKey(): ?string
    {
        return $this->bankKey;
    }

    // ------------------------------------------------------------------ PDU I/O

    private function buildPDU($commandId, $body, $commandStatus = 0, $sequenceNumber = null): string
    {
        if ($sequenceNumber === null) {
            $sequenceNumber = $this->nextSequenceNumber();
        }
        $header = pack('NNNN', 16 + strlen($body), $commandId, $commandStatus, $sequenceNumber);
        return $header . $body;
    }

    private function sendPDU($pdu): void
    {
        if (!$this->isSocketValid()) {
            throw new Exception('Not connected to SMPP server');
        }
        $written = @fwrite($this->socket, $pdu);
        if ($written === false || $written < strlen($pdu)) {
            $this->bound = false;
            throw new Exception('Failed to send PDU — connection lost');
        }
    }

    private function readPDU($blocking = false): ?array
    {
        if (!$this->isSocketValid()) {
            return null;
        }

        // Non-blocking poll: if no data is waiting, return immediately.
        // Once bytes are present we commit to a full blocking read of the whole
        // PDU so a partial header/body can never desync the stream.
        if (!$blocking) {
            $read = [$this->socket];
            $write = $except = null;
            $ready = @stream_select($read, $write, $except, 0, 0);
            if ($ready === false || $ready === 0) {
                return null;
            }
        }

        $header = $this->readExact(16, 15);
        if ($header === null) {
            return null;
        }
        $h = unpack('Nlength/Ncommand_id/Ncommand_status/Nsequence_number', $header);
        if ($h['length'] < 16 || $h['length'] > 5000) {
            return null;
        }
        $body = '';
        $bodyLen = $h['length'] - 16;
        if ($bodyLen > 0) {
            $body = $this->readExact($bodyLen, 15);
            if ($body === null) {
                return null;
            }
        }
        return [
            'command_id'      => $h['command_id'],
            'command_status'  => $h['command_status'],
            'sequence_number' => $h['sequence_number'],
            'body'            => $body,
        ];
    }

    /** Blocking read of exactly $len bytes (looping over short reads). Null on EOF/timeout. */
    private function readExact(int $len, int $timeoutSec): ?string
    {
        @stream_set_blocking($this->socket, true);
        @stream_set_timeout($this->socket, $timeoutSec);

        $buf = '';
        while (strlen($buf) < $len) {
            $chunk = @fread($this->socket, $len - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = @stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out']) || !empty($meta['eof'])) {
                    return null;
                }
                return null;
            }
            $buf .= $chunk;
        }
        return $buf;
    }

    /** message_id = null-terminated string at the start of the submit_sm_resp body. */
    private function parseMessageId(string $body): string
    {
        if ($body === '') {
            return '';
        }
        $nullPos = strpos($body, "\x00");
        return $nullPos !== false ? substr($body, 0, $nullPos) : $body;
    }

    /**
     * Extract the Vonage per-SMS price from a submit_sm_resp body: skip the
     * message-id C-string, then scan TLVs for 0x1422 (price, an ASCII string).
     */
    private function parseSubmissionPrice(string $body): ?string
    {
        $nullPos = strpos($body, "\x00");
        if ($nullPos === false) {
            return null;
        }
        $pos = $nullPos + 1;
        $len = strlen($body);
        while ($pos + 4 <= $len) {
            $tag = unpack('n', substr($body, $pos, 2))[1]; $pos += 2;
            $tlvLen = unpack('n', substr($body, $pos, 2))[1]; $pos += 2;
            if ($tlvLen < 0 || $pos + $tlvLen > $len) break;
            $value = substr($body, $pos, $tlvLen); $pos += $tlvLen;
            if ($tag === 0x1422) {
                $price = trim($value);
                return $price === '' ? null : $price;
            }
        }
        return null;
    }

    private function nextSequenceNumber(): int
    {
        $this->sequenceNumber++;
        if ($this->sequenceNumber > $this->seqMax) {
            $this->sequenceNumber = $this->seqMin;
        }
        return $this->sequenceNumber;
    }

    private function isSocketValid(): bool
    {
        return is_resource($this->socket);
    }

    private function log(string $msg): void
    {
        if ($this->debug) {
            fwrite(STDERR, "[smpp] $msg\n");
        }
    }

    /**
     * ASCII → GSM 03.38 (data_coding 0); anything else → UCS2 (data_coding 8).
     * Returns [shortMessage, dataCoding, sourceTon].
     */
    private function encode(string $message, string $from): array
    {
        $srcTon = ctype_digit(ltrim($from, '+')) ? 0x01 : 0x05; // international : alphanumeric
        if (mb_check_encoding($message, 'ASCII')) {
            return [GsmEncoder::utf8_to_gsm0338($message), 0x00, $srcTon];
        }
        // UTF-16BE (not UCS-2BE) so emoji / non-BMP characters encode as surrogate pairs.
        return [mb_convert_encoding($message, 'UTF-16BE', 'UTF-8'), 0x08, $srcTon];
    }

    public static function messageIdToDecimal(string $messageId): string
    {
        $messageId = trim($messageId);
        if ($messageId === '' || !ctype_xdigit($messageId) || strlen($messageId) <= 15) {
            return $messageId;
        }
        $dec = '0';
        for ($i = 0, $len = strlen($messageId); $i < $len; $i++) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($messageId[$i]));
        }
        return $dec;
    }
}
