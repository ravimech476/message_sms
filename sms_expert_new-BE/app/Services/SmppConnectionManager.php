<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SMPP Connection Manager - OLD SYSTEM Compatible
 *
 * Handles server failover, multiple daemon connections, and load balancing.
 * Based on OLD SYSTEM patterns from thisserver_details.inc
 *
 * OLD SYSTEM Features:
 * - arrServers: Primary/secondary server arrays for failover
 * - Multiple credential sets (a-j) for parallel daemon operation
 * - systemType: Identifies daemon for DLR error code mapping
 * - Provider banking: BKnPm format (Block n, Provider m)
 */
class SmppConnectionManager
{
    /**
     * Provider codes for DLR mapping (from OLD SYSTEM)
     * Used in systemType format: smppBKnPm (Block n, Provider m)
     */
    const PROVIDER_MBLOX = 1;
    const PROVIDER_MBIRD = 2;
    const PROVIDER_NEXMO = 3;
    const PROVIDER_ONESIXTYSIM = 4;
    const PROVIDER_ICE = 5;
    const PROVIDER_TEK = 6;
    const PROVIDER_ROUTEM = 7;

    /**
     * Provider names mapping
     */
    protected static $providerNames = [
        self::PROVIDER_MBLOX => 'mblox',
        self::PROVIDER_MBIRD => 'mbird',
        self::PROVIDER_NEXMO => 'nexmo',
        self::PROVIDER_ONESIXTYSIM => 'onesixtysim',
        self::PROVIDER_ICE => 'ice',
        self::PROVIDER_TEK => 'tek',
        self::PROVIDER_ROUTEM => 'routem',
    ];

    /**
     * @var string Current connection/provider
     */
    protected $connection;

    /**
     * @var string Current daemon ID (a, b, c, a0, a3, etc.)
     */
    protected $daemonId;

    /**
     * @var array Connection configuration
     */
    protected $config;

    /**
     * @var SequenceIdManager
     */
    protected $sequenceIdManager;

    /**
     * Create a new SmppConnectionManager instance
     *
     * @param string $connection Connection name (vonage_global, vonage_direct, sinch, etc.)
     * @param string $daemonId Daemon identifier
     */
    public function __construct(string $connection = 'vonage', string $daemonId = 'a')
    {
        $this->connection = $connection;
        $this->daemonId = $daemonId;
        $this->loadConfig();
        $this->initSequenceManager();
    }

    /**
     * Load configuration for the connection
     */
    protected function loadConfig(): void
    {
        // Try connections config first
        $this->config = config("smpp.connections.{$this->connection}");

        // If not found, try direct provider config (sinch, routemobile, etc.)
        if (!$this->config) {
            $this->config = config("smpp.{$this->connection}");
        }

        if (!$this->config) {
            throw new \RuntimeException("SMPP connection '{$this->connection}' not found in config");
        }
    }

    /**
     * Initialize sequence ID manager for this daemon
     */
    protected function initSequenceManager(): void
    {
        $seqIdRange = $this->getSeqIdRange();
        $this->sequenceIdManager = new SequenceIdManager($this->daemonId, $seqIdRange);
    }

    /**
     * Get the servers array for this daemon (with failover support)
     *
     * @return array [primary_server, secondary_server]
     */
    public function getServers(): array
    {
        // Check daemon-specific credentials first
        if (isset($this->config['credentials'][$this->daemonId]['servers'])) {
            return $this->config['credentials'][$this->daemonId]['servers'];
        }

        // Fall back to connection-level servers
        if (isset($this->config['servers'])) {
            if (isset($this->config['servers']['primary'])) {
                return [
                    $this->config['servers']['primary'],
                    $this->config['servers']['secondary'] ?? $this->config['servers']['primary'],
                ];
            }
            return $this->config['servers'];
        }

        // Legacy 'hosts' array
        if (isset($this->config['hosts'])) {
            return $this->config['hosts'];
        }

        throw new \RuntimeException("No servers configured for connection '{$this->connection}'");
    }

    /**
     * Get the primary server
     *
     * @return string
     */
    public function getPrimaryServer(): string
    {
        $servers = $this->getServers();
        return $servers[0] ?? $servers['primary'] ?? '';
    }

    /**
     * Get the secondary server (for failover)
     *
     * @return string
     */
    public function getSecondaryServer(): string
    {
        $servers = $this->getServers();
        return $servers[1] ?? $servers['secondary'] ?? $this->getPrimaryServer();
    }

    /**
     * Get the system type for this daemon
     * Used for DLR error code mapping (OLD SYSTEM: identifies provider)
     *
     * @return string
     */
    public function getSystemType(): string
    {
        // Check daemon-specific credentials first
        if (isset($this->config['credentials'][$this->daemonId]['system_type'])) {
            return $this->config['credentials'][$this->daemonId]['system_type'];
        }

        // Fall back to connection-level system_type
        return $this->config['system_type'] ?? 'smpp';
    }

    /**
     * Parse provider code from system type
     * OLD SYSTEM format: smppBKnPm (Block n, Provider m)
     *
     * @param string|null $systemType
     * @return int|null Provider code or null
     */
    public function parseProviderFromSystemType(?string $systemType = null): ?int
    {
        $systemType = $systemType ?? $this->getSystemType();

        // Match smppBKnPm format
        if (preg_match('/smppBK(\d+)P(\d+)/', $systemType, $matches)) {
            return (int) $matches[2];
        }

        // Match smppProdN format (mBlox/Sinch)
        if (preg_match('/smppProd(\d+)/', $systemType)) {
            return self::PROVIDER_MBLOX;
        }

        // Check specific provider names
        $systemTypeLower = strtolower($systemType);
        foreach (self::$providerNames as $code => $name) {
            if (strpos($systemTypeLower, $name) !== false) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get provider name from code
     *
     * @param int $code
     * @return string
     */
    public static function getProviderName(int $code): string
    {
        return self::$providerNames[$code] ?? 'unknown';
    }

    /**
     * Get the sequence ID range for this daemon
     *
     * @return array [min, max]
     */
    public function getSeqIdRange(): array
    {
        // Check daemon-specific credentials first
        if (isset($this->config['credentials'][$this->daemonId]['seq_id_range'])) {
            return $this->config['credentials'][$this->daemonId]['seq_id_range'];
        }

        // Fall back to connection-level seq_id_range
        if (isset($this->config['seq_id_range'])) {
            return [
                $this->config['seq_id_range']['min'] ?? 1,
                $this->config['seq_id_range']['max'] ?? 2000000,
            ];
        }

        // Default range
        return [1, 2000000];
    }

    /**
     * Get the next sequence ID
     *
     * @return int
     */
    public function getNextSequenceId(): int
    {
        return $this->sequenceIdManager->getNextSequenceId();
    }

    /**
     * Get the port for this connection
     *
     * @return int
     */
    public function getPort(): int
    {
        return (int) ($this->config['port'] ?? 8000);
    }

    /**
     * Get the system ID (username) for this connection
     *
     * @return string
     */
    public function getSystemId(): string
    {
        return $this->config['system_id'] ?? '';
    }

    /**
     * Get the password for this connection
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->config['password'] ?? '';
    }

    /**
     * Get the profile for this connection (mBlox specific)
     *
     * @return string
     */
    public function getProfile(): string
    {
        return $this->config['profile'] ?? '';
    }

    /**
     * Get the throttle/TPS limit for this connection
     *
     * @return int
     */
    public function getThrottleLimit(): int
    {
        // Check connection-specific throttle
        $throttleKey = "smpp.throttle.{$this->connection}";
        $limit = config($throttleKey);

        if ($limit) {
            return (int) $limit;
        }

        // Fall back to config throttle
        if (isset($this->config['throttle']['tps_limit'])) {
            return (int) $this->config['throttle']['tps_limit'];
        }

        // Fall back to settings
        return (int) config('smpp.settings.tps_limit', 50);
    }

    /**
     * Get bind type for this connection
     *
     * @return string
     */
    public function getBindType(): string
    {
        return $this->config['bind_type'] ?? 'transceiver';
    }

    /**
     * Get connection timeout
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return (int) ($this->config['connection']['timeout']
            ?? config('smpp.settings.timeout', 30));
    }

    /**
     * Get all daemon IDs available for this connection
     *
     * @return array
     */
    public function getAvailableDaemons(): array
    {
        if (isset($this->config['credentials'])) {
            return array_keys($this->config['credentials']);
        }

        return [$this->daemonId];
    }

    /**
     * Try to connect with failover
     * Attempts primary server first, then secondary on failure
     *
     * @param callable $connectCallback Function that takes (host, port) and returns connection
     * @return mixed Connection result
     * @throws \Exception If all connection attempts fail
     */
    public function connectWithFailover(callable $connectCallback)
    {
        $servers = $this->getServers();
        $port = $this->getPort();
        $lastException = null;

        foreach ($servers as $server) {
            try {
                Log::info("SmppConnectionManager: Attempting connection to {$server}:{$port}");
                $result = $connectCallback($server, $port);
                Log::info("SmppConnectionManager: Connected to {$server}:{$port}");
                return $result;
            } catch (\Exception $e) {
                Log::warning("SmppConnectionManager: Failed to connect to {$server}:{$port}: " . $e->getMessage());
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new \RuntimeException("Failed to connect to any SMPP server");
    }

    /**
     * Get connection info for logging/debugging
     *
     * @return array
     */
    public function getConnectionInfo(): array
    {
        return [
            'connection' => $this->connection,
            'daemon_id' => $this->daemonId,
            'servers' => $this->getServers(),
            'port' => $this->getPort(),
            'system_type' => $this->getSystemType(),
            'system_id' => $this->getSystemId(),
            'seq_id_range' => $this->getSeqIdRange(),
            'throttle_limit' => $this->getThrottleLimit(),
            'bind_type' => $this->getBindType(),
        ];
    }

    /**
     * Create a manager for a specific provider/daemon
     *
     * @param string $provider
     * @param string $daemonId
     * @return self
     */
    public static function forDaemon(string $provider, string $daemonId): self
    {
        return new self($provider, $daemonId);
    }

    /**
     * Get all available connections
     *
     * @return array
     */
    public static function getAvailableConnections(): array
    {
        $connections = array_keys(config('smpp.connections', []));

        // Add direct provider configs
        foreach (['sinch', 'routemobile', 'ice', 'tek'] as $provider) {
            if (config("smpp.{$provider}")) {
                $connections[] = $provider;
            }
        }

        return array_unique($connections);
    }
}
