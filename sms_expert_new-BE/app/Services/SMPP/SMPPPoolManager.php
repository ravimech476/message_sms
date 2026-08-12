<?php

namespace App\Services\SMPP;

use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;
use Exception;

/**
 * SMPP Connection Pool Manager
 * Manages multiple SMPP connections for load balancing and failover
 */
class SMPPPoolManager
{
    private $connections = [];
    private $hosts = [];
    private $currentHostIndex = 0;
    private $maxConnectionsPerHost = 2;
    private $connectionAttempts = [];

    public function __construct()
    {
        // Load SMPP hosts from configuration
        $hostsString = env('SMPP_HOSTS', 'smpp1.nexmo.com,smpp2.nexmo.com');
        $this->hosts = array_map('trim', explode(',', $hostsString));
        
        SmppLogger::forProvider('pool')->info("SMPP Pool Manager initialized with hosts", ['hosts' => $this->hosts]);
    }

    /**
     * Get an available SMPP connection
     */
    public function getConnection()
    {
        // Try to get an existing connected connection
        foreach ($this->connections as $connection) {
            if ($connection['service']->getStatistics()['connected']) {
                return $connection['service'];
            }
        }
        
        // No active connections, create a new one
        return $this->createConnection();
    }

    /**
     * Create a new SMPP connection
     */
    private function createConnection()
    {
        $attempts = 0;
        $maxAttempts = count($this->hosts);
        
        while ($attempts < $maxAttempts) {
            $host = $this->getNextHost();
            
            try {
                SmppLogger::forProvider('pool')->info("Attempting to connect to SMPP host", ['host' => $host]);
                
                $service = new SMPPService($host, env('SMPP_PORT', 8000));
                $service->connect();
                
                // Store connection
                $connectionId = uniqid('smpp_');
                $this->connections[$connectionId] = [
                    'id' => $connectionId,
                    'host' => $host,
                    'service' => $service,
                    'created_at' => now(),
                    'message_count' => 0
                ];
                
                SmppLogger::forProvider('pool')->info("Successfully connected to SMPP host", [
                    'host' => $host,
                    'connection_id' => $connectionId
                ]);
                
                return $service;
                
            } catch (Exception $e) {
                SmppLogger::forProvider('pool')->warning("Failed to connect to SMPP host", [
                    'host' => $host,
                    'error' => $e->getMessage()
                ]);
                
                // Track failed attempt
                $this->connectionAttempts[$host] = [
                    'last_attempt' => now(),
                    'failure_count' => ($this->connectionAttempts[$host]['failure_count'] ?? 0) + 1
                ];
                
                $attempts++;
            }
        }
        
        throw new Exception("Failed to connect to any SMPP host");
    }

    /**
     * Get next host using round-robin
     */
    private function getNextHost()
    {
        $host = $this->hosts[$this->currentHostIndex];
        
        $this->currentHostIndex++;
        if ($this->currentHostIndex >= count($this->hosts)) {
            $this->currentHostIndex = 0;
        }
        
        // Check if host has too many recent failures
        if (isset($this->connectionAttempts[$host])) {
            $lastAttempt = $this->connectionAttempts[$host]['last_attempt'];
            $failureCount = $this->connectionAttempts[$host]['failure_count'];
            
            // Skip host if it failed recently (within last 5 minutes) and has multiple failures
            if ($failureCount >= 3 && $lastAttempt->diffInMinutes(now()) < 5) {
                return $this->getNextHost(); // Try next host
            }
        }
        
        return $host;
    }

    /**
     * Send SMS using connection pool with queue_id, initiator, and reference_id for DLR
     * @param string $to Recipient phone number
     * @param string $message SMS message content
     * @param string $from Sender ID
     * @param int $priority Message priority
     * @param string $queueId Queue ID for tracking
     * @param string $initiator Who initiated the message
     * @param string $referenceId Reference ID (bigid) for DLR matching
     */
    public function sendSMS($to, $message, $from = null, $priority = 5, $queueId = null, $initiator = 'ControlPanel', $referenceId = null, $scheduleDeliveryTime = null, $smsgLogId = null)
    {
        // PERSISTENT MODE: instead of an ephemeral connect/submit/disconnect (whose
        // DLR Vonage can't deliver back, because the session is gone), publish the
        // submit to the outbound queue. A long-lived transceiver daemon
        // (smpp:dlr-receiver --send, one per bank) drains it, submits on its OWN
        // bound session, and receives the DLR on that SAME session. See config/smpp.php.
        if (config('smpp.persistent_send')) {
            $published = $this->publishOutbound([
                'to'                    => $to,
                'message'               => $message,
                'from'                  => $from,
                'priority'              => $priority,
                'queue_id'              => $queueId,
                'initiator'             => $initiator,
                'reference_id'          => $referenceId,
                'schedule_delivery_time' => $scheduleDeliveryTime,
                'smsg_log_id'           => $smsgLogId,
                'provider'              => 'nexmo',
            ], $priority);

            if ($published) {
                // The real message_id / deliveryreceipt1 are written by the transceiver
                // when it actually submits (storeMessageIdMapping, scoped by smsg_log_id).
                return [
                    'success'    => true,
                    'queued'     => true,
                    'message_id' => '',
                    'to'         => $to,
                    'from'       => $from,
                ];
            }

            // If publishing failed, fall through to direct send so the SMS still goes.
            SmppLogger::forProvider('pool')->warning('persistent_send: publish failed, falling back to direct send', [
                'smsg_log_id' => $smsgLogId, 'to' => $to,
            ]);
        }

        $maxRetries = 3;
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                $connection = $this->getConnection();
                $result = $connection->sendSMS($to, $message, $from, $priority, $queueId, $initiator, $referenceId, $scheduleDeliveryTime, $smsgLogId);
                
                // Update connection statistics
                foreach ($this->connections as &$conn) {
                    if ($conn['service'] === $connection) {
                        $conn['message_count']++;
                        $conn['last_used'] = now();
                        break;
                    }
                }
                
                return $result;
                
            } catch (Exception $e) {
                SmppLogger::forProvider('pool')->warning("Failed to send SMS, attempt " . ($attempts + 1), [
                    'error' => $e->getMessage()
                ]);
                
                // Mark connection as failed and try with another
                $this->handleFailedConnection($e->getMessage());
                
                $attempts++;
                
                if ($attempts >= $maxRetries) {
                    throw new Exception("Failed to send SMS after {$maxRetries} attempts: " . $e->getMessage());
                }
                
                // Wait before retry
                sleep(1);
            }
        }
    }

    /**
     * Publish an outbound submit to the SMPP outbound queue for the persistent
     * transceiver daemons to pick up and submit on a long-lived bound session.
     */
    private function publishOutbound(array $payload, int $priority): bool
    {
        try {
            $queue = config('smpp.outbound_queue', 'sms.outbound');
            $rabbit = new \App\Services\Queue\RabbitMQService();
            return (bool) $rabbit->publishToQueue($queue, $payload, $priority);
        } catch (\Throwable $e) {
            SmppLogger::forProvider('pool')->warning('publishOutbound failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle failed connection
     */
    private function handleFailedConnection($error)
    {
        // Remove failed connections
        foreach ($this->connections as $id => $connection) {
            if (!$connection['service']->getStatistics()['connected']) {
                try {
                    $connection['service']->disconnect();
                } catch (Exception $e) {
                    // Ignore disconnect errors
                }
                unset($this->connections[$id]);
            }
        }
    }

    /**
     * Keep connections alive
     */
    public function keepAlive()
    {
        foreach ($this->connections as $id => $connection) {
            try {
                if (!$connection['service']->enquireLink()) {
                    SmppLogger::forProvider('pool')->warning("Enquire link failed for connection", ['id' => $id]);
                    unset($this->connections[$id]);
                }
            } catch (Exception $e) {
                SmppLogger::forProvider('pool')->error("Error during keep-alive", [
                    'connection_id' => $id,
                    'error' => $e->getMessage()
                ]);
                unset($this->connections[$id]);
            }
        }
    }

    /**
     * Get pool statistics
     */
    public function getStatistics()
    {
        $stats = [
            'total_connections' => count($this->connections),
            'active_connections' => 0,
            'total_messages_sent' => 0,
            'connections' => []
        ];
        
        foreach ($this->connections as $connection) {
            $connStats = $connection['service']->getStatistics();
            
            if ($connStats['connected']) {
                $stats['active_connections']++;
            }
            
            $stats['total_messages_sent'] += $connection['message_count'];
            
            $stats['connections'][] = [
                'id' => $connection['id'],
                'host' => $connection['host'],
                'connected' => $connStats['connected'],
                'messages_sent' => $connStats['messages_sent'],
                'messages_failed' => $connStats['messages_failed'],
                'current_tps' => $connStats['current_tps'],
                'created_at' => $connection['created_at']->toDateTimeString(),
                'last_used' => isset($connection['last_used']) ? $connection['last_used']->toDateTimeString() : null
            ];
        }
        
        return $stats;
    }

    /**
     * Disconnect all connections
     */
    public function disconnectAll()
    {
        foreach ($this->connections as $connection) {
            try {
                $connection['service']->disconnect();
            } catch (Exception $e) {
                SmppLogger::forProvider('pool')->warning("Error disconnecting", ['error' => $e->getMessage()]);
            }
        }
        
        $this->connections = [];
        SmppLogger::forProvider('pool')->info("All SMPP connections disconnected");
    }

    public function __destruct()
    {
        $this->disconnectAll();
    }
}
