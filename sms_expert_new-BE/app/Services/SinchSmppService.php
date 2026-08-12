<?php

namespace App\Services;

use SmppClient;
use SmppAddress;
use SocketTransport;
use SMPP;
use Illuminate\Support\Facades\Log;

/**
 * Sinch SMPP Service - OLD SYSTEM Compatible
 *
 * Implements SMPP sending with features from OLD SYSTEM:
 * - Server failover (primary/secondary)
 * - systemType for daemon identification
 * - seq_id_range for unique message IDs
 * - Provider-specific logging (logs/{date}/smpp/sinch.log)
 */
class SinchSmppService
{
    /**
     * @var SmppClient
     */
    protected $client;

    /**
     * @var SocketTransport
     */
    protected $transport;

    /**
     * @var SmppConnectionManager
     */
    protected $connectionManager;

    /**
     * @var string Current daemon ID
     */
    protected $daemonId;

    /**
     * @var SmppLogger
     */
    protected $logger;

    /**
     * Create a new SinchSmppService instance
     *
     * @param string $daemonId Daemon identifier (default 'a')
     */
    public function __construct(string $daemonId = 'a')
    {
        $this->daemonId = $daemonId;
        $this->logger = SmppLogger::sinch();
    }

    /**
     * Connect to SMPP server with failover support
     *
     * @return SmppClient
     */
    public function connect()
    {
        // Get primary and secondary servers from config
        $primaryHost = config('sinch_smpp.servers.primary', config('sinch_smpp.host'));
        $secondaryHost = config('sinch_smpp.servers.secondary', $primaryHost);
        $port = config('sinch_smpp.port');
        $recvTimeout = config('sinch_smpp.connection.recv_timeout', 10000);

        $servers = [$primaryHost, $secondaryHost];
        $lastException = null;

        foreach ($servers as $host) {
            try {
                $this->logger->logConnect($host, $port, ['daemon_id' => $this->daemonId]);

                $this->transport = new SocketTransport([$host], $port);
                $this->transport->setRecvTimeout($recvTimeout);
                $this->transport->open();

                $this->client = new SmppClient($this->transport);

                // Bind with systemType for daemon identification (OLD SYSTEM pattern)
                $systemId = config('sinch_smpp.system_id');
                $systemType = config('sinch_smpp.system_type', 'smppProd1');

                $this->client->bindTransceiver(
                    $systemId,
                    config('sinch_smpp.password'),
                    $systemType
                );

                $this->logger->logBind($systemId, $systemType, 'transceiver', [
                    'host' => $host,
                    'port' => $port,
                    'daemon_id' => $this->daemonId,
                ]);

                return $this->client;

            } catch (\Exception $e) {
                $this->logger->logError("Connection failed to {$host}:{$port}", null, [
                    'error' => $e->getMessage(),
                    'daemon_id' => $this->daemonId,
                ]);
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new \RuntimeException("Failed to connect to any Sinch SMPP server");
    }

    /**
     * Connect using SmppConnectionManager (for multi-daemon support)
     *
     * @param string $provider Provider name
     * @param string $daemonId Daemon identifier
     * @return SmppClient
     */
    public function connectWithManager(string $provider = 'sinch', string $daemonId = 'a')
    {
        $this->daemonId = $daemonId;
        $this->connectionManager = SmppConnectionManager::forDaemon($provider, $daemonId);

        $info = $this->connectionManager->getConnectionInfo();
        Log::info("SinchSmppService: Using connection manager", $info);

        return $this->connectionManager->connectWithFailover(function ($host, $port) use ($info) {
            $recvTimeout = config('sinch_smpp.connection.recv_timeout', 10000);

            $this->transport = new SocketTransport([$host], $port);
            $this->transport->setRecvTimeout($recvTimeout);
            $this->transport->open();

            $this->client = new SmppClient($this->transport);

            $this->client->bindTransceiver(
                $info['system_id'],
                $this->connectionManager->getPassword(),
                $info['system_type']
            );

            return $this->client;
        });
    }

    /**
     * Send an SMS
     *
     * @param string $mobile Mobile number
     * @param string $message Message content
     * @return mixed Message ID from SMPP server
     */
    public function sendSms($mobile, $message)
    {
        $client = $this->connect();

        $from = new SmppAddress(
            config('sinch_smpp.sender'),
            SMPP::TON_ALPHANUMERIC
        );

        $to = new SmppAddress(
            $mobile,
            SMPP::TON_INTERNATIONAL,
            SMPP::NPI_E164
        );

        $messageId = $client->sendSMS(
            $from,
            $to,
            $message,
            null,
            SMPP::ESM_CLASS_DEFAULT,
            null,
            null,
            null,
            null,
            null,
            null,
            SMPP::REGISTERED_DELIVERY_SMSC_RECEIPT_REQUESTED
        );

        $client->close();

        return $messageId;
    }

    /**
     * Send an SMS with sequence ID tracking (OLD SYSTEM pattern)
     *
     * @param string $mobile Mobile number
     * @param string $message Message content
     * @param string|null $sender Sender ID (optional)
     * @return array ['message_id' => string, 'sequence_id' => int]
     */
    public function sendSmsWithSequence($mobile, $message, $sender = null)
    {
        // Get sequence ID from manager
        $sequenceId = null;
        if ($this->connectionManager) {
            $sequenceId = $this->connectionManager->getNextSequenceId();
        } else {
            // Use standalone sequence manager
            $seqRange = [
                config('sinch_smpp.seq_id_range.min', 1),
                config('sinch_smpp.seq_id_range.max', 2000000),
            ];
            $seqManager = new SequenceIdManager($this->daemonId, $seqRange);
            $sequenceId = $seqManager->getNextSequenceId();
        }

        $client = $this->client ?? $this->connect();

        $senderName = $sender ?? config('sinch_smpp.sender');
        $senderAddress = new SmppAddress(
            $senderName,
            SMPP::TON_ALPHANUMERIC
        );

        $to = new SmppAddress(
            $mobile,
            SMPP::TON_INTERNATIONAL,
            SMPP::NPI_E164
        );

        try {
            $messageId = $client->sendSMS(
                $senderAddress,
                $to,
                $message,
                null,
                SMPP::ESM_CLASS_DEFAULT,
                null,
                null,
                null,
                null,
                null,
                null,
                SMPP::REGISTERED_DELIVERY_SMSC_RECEIPT_REQUESTED
            );

            // Log successful send
            $this->logger->logSend($messageId, $mobile, $senderName, [
                'sequence_id' => $sequenceId,
                'daemon_id' => $this->daemonId,
                'system_type' => config('sinch_smpp.system_type', 'smppProd1'),
                'message_length' => strlen($message),
            ]);

            return [
                'message_id' => $messageId,
                'sequence_id' => $sequenceId,
                'daemon_id' => $this->daemonId,
                'system_type' => config('sinch_smpp.system_type', 'smppProd1'),
            ];

        } catch (\Exception $e) {
            $this->logger->logError("Failed to send SMS", null, [
                'mobile' => substr($mobile, 0, 8) . '****',
                'sender' => $senderName,
                'error' => $e->getMessage(),
                'daemon_id' => $this->daemonId,
            ]);
            throw $e;
        }
    }

    /**
     * Get the system type for this daemon
     *
     * @return string
     */
    public function getSystemType(): string
    {
        if ($this->connectionManager) {
            return $this->connectionManager->getSystemType();
        }
        return config('sinch_smpp.system_type', 'smppProd1');
    }

    /**
     * Get the current daemon ID
     *
     * @return string
     */
    public function getDaemonId(): string
    {
        return $this->daemonId;
    }

    /**
     * Close the connection
     */
    public function disconnect()
    {
        if ($this->client) {
            try {
                $this->client->close();
                $this->logger->logDisconnect('Normal disconnect', ['daemon_id' => $this->daemonId]);
            } catch (\Exception $e) {
                $this->logger->warning("Error closing connection: " . $e->getMessage(), [
                    'daemon_id' => $this->daemonId,
                ]);
            }
        }
    }

    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}