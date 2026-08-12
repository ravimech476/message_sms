<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * SMPP Logger Service - Date-wise and Provider-wise Logging
 *
 * Provides structured logging for SMPP operations with:
 * - Date-wise log files (logs/{date}/smpp/{provider}.log)
 * - Provider-specific logs (nexmo, sinch, mblox, routemobile, ice, tek)
 * - DLR-specific logging
 * - Error logging
 *
 * Based on OLD SYSTEM logging patterns
 */
class SmppLogger
{
    /**
     * Supported SMPP providers
     */
    const PROVIDER_NEXMO = 'nexmo';
    const PROVIDER_VONAGE = 'vonage';
    const PROVIDER_SINCH = 'sinch';
    const PROVIDER_MBLOX = 'mblox';
    const PROVIDER_ROUTEMOBILE = 'routemobile';
    const PROVIDER_ICE = 'ice';
    const PROVIDER_TEK = 'tek';

    /**
     * Log types
     */
    const TYPE_SEND = 'SEND';
    const TYPE_DLR = 'DLR';
    const TYPE_INBOUND = 'INBOUND';
    const TYPE_CONNECT = 'CONNECT';
    const TYPE_DISCONNECT = 'DISCONNECT';
    const TYPE_ERROR = 'ERROR';
    const TYPE_BIND = 'BIND';
    const TYPE_UNBIND = 'UNBIND';

    /**
     * @var string Current provider
     */
    protected $provider;

    /**
     * @var Logger[] Cached logger instances
     */
    protected static $loggers = [];

    /**
     * Create a new SmppLogger instance
     *
     * @param string $provider Provider name (nexmo, sinch, etc.)
     */
    public function __construct(string $provider = self::PROVIDER_NEXMO)
    {
        $this->provider = strtolower($provider);
    }

    /**
     * Get logger for a specific provider
     *
     * @param string $provider
     * @return Logger
     */
    protected function getLogger(string $provider): Logger
    {
        $date = date('Y-m-d');
        $cacheKey = "{$date}_{$provider}";

        if (!isset(self::$loggers[$cacheKey])) {
            $logPath = storage_path("logs/{$date}/smpp/{$provider}.log");

            // Ensure directory exists
            $directory = dirname($logPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $logger = new Logger("smpp_{$provider}");

            $handler = new StreamHandler($logPath, Logger::DEBUG);
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                "Y-m-d H:i:s.u",
                true,
                true
            );
            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            self::$loggers[$cacheKey] = $logger;
        }

        return self::$loggers[$cacheKey];
    }

    /**
     * Log an info message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->getLogger($this->provider)->info($message, $this->enrichContext($context));
    }

    /**
     * Log a debug message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->getLogger($this->provider)->debug($message, $this->enrichContext($context));
    }

    /**
     * Log a warning message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->getLogger($this->provider)->warning($message, $this->enrichContext($context));
    }

    /**
     * Log an error message (also logs to errors.log)
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $enrichedContext = $this->enrichContext($context);

        // Log to provider-specific log
        $this->getLogger($this->provider)->error($message, $enrichedContext);

        // Also log to errors.log
        $this->getLogger('errors')->error("[{$this->provider}] {$message}", $enrichedContext);
    }

    /**
     * Log SMS send operation
     *
     * @param string $messageId
     * @param string $mobile
     * @param string $sender
     * @param array $extra
     * @return void
     */
    public function logSend(string $messageId, string $mobile, string $sender, array $extra = []): void
    {
        $this->info(self::TYPE_SEND, array_merge([
            'message_id' => $messageId,
            'mobile' => $this->maskMobile($mobile),
            'sender' => $sender,
        ], $extra));
    }

    /**
     * Log DLR (Delivery Receipt)
     *
     * @param string $messageId
     * @param string $status
     * @param array $extra
     * @return void
     */
    public function logDlr(string $messageId, string $status, array $extra = []): void
    {
        $context = array_merge([
            'message_id' => $messageId,
            'status' => $status,
        ], $extra);

        // Log to provider log
        $this->info(self::TYPE_DLR, $context);

        // Also log to dedicated DLR log
        $this->getLogger('dlr')->info("[{$this->provider}] " . self::TYPE_DLR, $context);
    }

    /**
     * Log inbound SMS
     *
     * @param string $from
     * @param string $to
     * @param string $message
     * @param array $extra
     * @return void
     */
    public function logInbound(string $from, string $to, string $message, array $extra = []): void
    {
        $this->info(self::TYPE_INBOUND, array_merge([
            'from' => $this->maskMobile($from),
            'to' => $to,
            'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
        ], $extra));
    }

    /**
     * Log connection event
     *
     * @param string $host
     * @param int $port
     * @param array $extra
     * @return void
     */
    public function logConnect(string $host, int $port, array $extra = []): void
    {
        $this->info(self::TYPE_CONNECT, array_merge([
            'host' => $host,
            'port' => $port,
        ], $extra));
    }

    /**
     * Log disconnection event
     *
     * @param string $reason
     * @param array $extra
     * @return void
     */
    public function logDisconnect(string $reason = '', array $extra = []): void
    {
        $this->info(self::TYPE_DISCONNECT, array_merge([
            'reason' => $reason,
        ], $extra));
    }

    /**
     * Log bind event
     *
     * @param string $systemId
     * @param string $systemType
     * @param string $bindType
     * @param array $extra
     * @return void
     */
    public function logBind(string $systemId, string $systemType, string $bindType = 'transceiver', array $extra = []): void
    {
        $this->info(self::TYPE_BIND, array_merge([
            'system_id' => $systemId,
            'system_type' => $systemType,
            'bind_type' => $bindType,
        ], $extra));
    }

    /**
     * Log unbind event
     *
     * @param string $reason
     * @param array $extra
     * @return void
     */
    public function logUnbind(string $reason = '', array $extra = []): void
    {
        $this->info(self::TYPE_UNBIND, array_merge([
            'reason' => $reason,
        ], $extra));
    }

    /**
     * Log SMPP error
     *
     * @param string $errorMessage
     * @param int|null $errorCode
     * @param array $extra
     * @return void
     */
    public function logError(string $errorMessage, ?int $errorCode = null, array $extra = []): void
    {
        $this->error(self::TYPE_ERROR, array_merge([
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
        ], $extra));
    }

    /**
     * Log submit_sm (message submission)
     *
     * @param array $pdu PDU data
     * @param array $extra
     * @return void
     */
    public function logSubmitSm(array $pdu, array $extra = []): void
    {
        $this->debug('SUBMIT_SM', array_merge([
            'sequence_number' => $pdu['sequence_number'] ?? null,
            'source_addr' => $pdu['source_addr'] ?? null,
            'dest_addr' => isset($pdu['dest_addr']) ? $this->maskMobile($pdu['dest_addr']) : null,
            'short_message' => isset($pdu['short_message']) ? substr($pdu['short_message'], 0, 30) . '...' : null,
        ], $extra));
    }

    /**
     * Log submit_sm_resp (response to submission)
     *
     * @param string $messageId
     * @param int $commandStatus
     * @param array $extra
     * @return void
     */
    public function logSubmitSmResp(string $messageId, int $commandStatus, array $extra = []): void
    {
        $level = $commandStatus === 0 ? 'debug' : 'warning';

        $this->$level('SUBMIT_SM_RESP', array_merge([
            'message_id' => $messageId,
            'command_status' => $commandStatus,
            'status_text' => $this->getCommandStatusText($commandStatus),
        ], $extra));
    }

    /**
     * Log deliver_sm (delivery receipt or MO)
     *
     * @param array $pdu PDU data
     * @param array $extra
     * @return void
     */
    public function logDeliverSm(array $pdu, array $extra = []): void
    {
        $this->debug('DELIVER_SM', array_merge([
            'sequence_number' => $pdu['sequence_number'] ?? null,
            'source_addr' => $pdu['source_addr'] ?? null,
            'dest_addr' => $pdu['dest_addr'] ?? null,
            'esm_class' => $pdu['esm_class'] ?? null,
        ], $extra));
    }

    /**
     * Enrich context with common data
     *
     * @param array $context
     * @return array
     */
    protected function enrichContext(array $context): array
    {
        return array_merge([
            'provider' => $this->provider,
            'timestamp' => now()->format('Y-m-d H:i:s.u'),
        ], $context);
    }

    /**
     * Mask mobile number for privacy
     *
     * @param string $mobile
     * @return string
     */
    protected function maskMobile(string $mobile): string
    {
        if (strlen($mobile) <= 6) {
            return $mobile;
        }
        return substr($mobile, 0, -4) . '****';
    }

    /**
     * Get SMPP command status text
     *
     * @param int $status
     * @return string
     */
    protected function getCommandStatusText(int $status): string
    {
        $statuses = [
            0x00000000 => 'ESME_ROK',
            0x00000001 => 'ESME_RINVMSGLEN',
            0x00000002 => 'ESME_RINVCMDLEN',
            0x00000003 => 'ESME_RINVCMDID',
            0x00000004 => 'ESME_RINVBNDSTS',
            0x00000005 => 'ESME_RALYBND',
            0x00000006 => 'ESME_RINVPRTFLG',
            0x00000007 => 'ESME_RINVREGDLVFLG',
            0x00000008 => 'ESME_RSYSERR',
            0x0000000A => 'ESME_RINVSRCADR',
            0x0000000B => 'ESME_RINVDSTADR',
            0x0000000C => 'ESME_RINVMSGID',
            0x0000000D => 'ESME_RBINDFAIL',
            0x0000000E => 'ESME_RINVPASWD',
            0x0000000F => 'ESME_RINVSYSID',
            0x00000011 => 'ESME_RCANCELFAIL',
            0x00000013 => 'ESME_RREPLACEFAIL',
            0x00000014 => 'ESME_RMSGQFUL',
            0x00000015 => 'ESME_RINVSERTYP',
            0x00000033 => 'ESME_RINVNUMDESTS',
            0x00000034 => 'ESME_RINVDLNAME',
            0x00000040 => 'ESME_RINVDESTFLAG',
            0x00000042 => 'ESME_RINVSUBREP',
            0x00000043 => 'ESME_RINVESMCLASS',
            0x00000044 => 'ESME_RCNTSUBDL',
            0x00000045 => 'ESME_RSUBMITFAIL',
            0x00000048 => 'ESME_RINVSRCTON',
            0x00000049 => 'ESME_RINVSRCNPI',
            0x00000050 => 'ESME_RINVDSTTON',
            0x00000051 => 'ESME_RINVDSTNPI',
            0x00000053 => 'ESME_RINVSYSTYP',
            0x00000054 => 'ESME_RINVREPFLAG',
            0x00000055 => 'ESME_RINVNUMMSGS',
            0x00000058 => 'ESME_RTHROTTLED',
            0x00000061 => 'ESME_RINVSCHED',
            0x00000062 => 'ESME_RINVEXPIRY',
            0x00000063 => 'ESME_RINVDFTMSGID',
            0x00000064 => 'ESME_RX_T_APPN',
            0x00000065 => 'ESME_RX_P_APPN',
            0x00000066 => 'ESME_RX_R_APPN',
            0x00000067 => 'ESME_RQUERYFAIL',
            0x000000C0 => 'ESME_RINVOPTPARSTREAM',
            0x000000C1 => 'ESME_ROPTPARNOTALLWD',
            0x000000C2 => 'ESME_RINVPARLEN',
            0x000000C3 => 'ESME_RMISSINGOPTPARAM',
            0x000000C4 => 'ESME_RINVOPTPARAMVAL',
            0x000000FE => 'ESME_RDELIVERYFAILURE',
            0x000000FF => 'ESME_RUNKNOWNERR',
        ];

        return $statuses[$status] ?? "UNKNOWN_STATUS_{$status}";
    }

    /**
     * Create a logger for a specific provider
     *
     * @param string $provider
     * @return self
     */
    public static function forProvider(string $provider): self
    {
        return new self($provider);
    }

    /**
     * Create a Nexmo logger
     *
     * @return self
     */
    public static function nexmo(): self
    {
        return new self(self::PROVIDER_NEXMO);
    }

    /**
     * Create a Vonage logger (alias for Nexmo)
     *
     * @return self
     */
    public static function vonage(): self
    {
        return new self(self::PROVIDER_VONAGE);
    }

    /**
     * Create a Sinch logger
     *
     * @return self
     */
    public static function sinch(): self
    {
        return new self(self::PROVIDER_SINCH);
    }

    /**
     * Create a mBlox logger
     *
     * @return self
     */
    public static function mblox(): self
    {
        return new self(self::PROVIDER_MBLOX);
    }

    /**
     * Create a Route Mobile logger
     *
     * @return self
     */
    public static function routemobile(): self
    {
        return new self(self::PROVIDER_ROUTEMOBILE);
    }

    /**
     * Create an ICE logger
     *
     * @return self
     */
    public static function ice(): self
    {
        return new self(self::PROVIDER_ICE);
    }

    /**
     * Create a TEK logger
     *
     * @return self
     */
    public static function tek(): self
    {
        return new self(self::PROVIDER_TEK);
    }

    /**
     * Clear cached loggers (useful for long-running processes at midnight)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$loggers = [];
    }
}
