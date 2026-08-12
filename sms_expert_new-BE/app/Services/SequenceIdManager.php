<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sequence ID Manager - OLD SYSTEM Compatible
 *
 * Manages unique message IDs within assigned ranges per daemon.
 * Based on OLD SYSTEM seq_id_range pattern from thisserver_details.inc
 *
 * OLD SYSTEM Pattern:
 * - Each daemon (a-j) has a unique seq_id_range (e.g., 1-2000000, 2000001-4000000)
 * - This prevents message ID collisions when running multiple parallel daemons
 * - seq_id must be high enough so IDs don't repeat before DLR is received
 */
class SequenceIdManager
{
    /**
     * @var string Current daemon identifier (a, b, c, etc.)
     */
    protected $daemonId;

    /**
     * @var int Minimum sequence ID for this daemon
     */
    protected $minSeqId;

    /**
     * @var int Maximum sequence ID for this daemon
     */
    protected $maxSeqId;

    /**
     * @var string Storage driver (redis, database, file)
     */
    protected $storage;

    /**
     * @var string Key prefix for storage
     */
    protected $keyPrefix;

    /**
     * Create a new SequenceIdManager instance
     *
     * @param string $daemonId The daemon identifier (a, b, c, etc.)
     * @param array $seqIdRange The [min, max] range for this daemon
     */
    public function __construct(string $daemonId = 'a', array $seqIdRange = null)
    {
        $this->daemonId = $daemonId;
        $this->storage = config('smpp.sequence_id.storage', 'redis');
        $this->keyPrefix = config('smpp.sequence_id.key_prefix', 'smpp_seq_id_');

        // Set range from provided values or defaults
        if ($seqIdRange) {
            $this->minSeqId = $seqIdRange[0] ?? 1;
            $this->maxSeqId = $seqIdRange[1] ?? 2000000;
        } else {
            $this->minSeqId = 1;
            $this->maxSeqId = 2000000;
        }
    }

    /**
     * Get the next sequence ID for this daemon
     *
     * @return int
     */
    public function getNextSequenceId(): int
    {
        $key = $this->getStorageKey();

        switch ($this->storage) {
            case 'redis':
                return $this->getNextFromRedis($key);
            case 'database':
                return $this->getNextFromDatabase($key);
            case 'file':
                return $this->getNextFromFile($key);
            default:
                return $this->getNextFromRedis($key);
        }
    }

    /**
     * Get next sequence ID from Redis
     *
     * @param string $key
     * @return int
     */
    protected function getNextFromRedis(string $key): int
    {
        try {
            $current = Redis::get($key);

            if ($current === null || $current < $this->minSeqId || $current >= $this->maxSeqId) {
                // Initialize or reset to min
                Redis::set($key, $this->minSeqId);
                $current = $this->minSeqId;
            }

            // Increment and return
            $next = Redis::incr($key);

            // Check if we need to wrap around
            if ($next > $this->maxSeqId) {
                Redis::set($key, $this->minSeqId);
                $next = $this->minSeqId;
                Log::info("SequenceIdManager: Wrapped around for daemon {$this->daemonId}");
            }

            return (int) $next;

        } catch (\Exception $e) {
            Log::error("SequenceIdManager Redis error: " . $e->getMessage());
            // Fallback to time-based ID within range
            return $this->getFallbackId();
        }
    }

    /**
     * Get next sequence ID from database
     *
     * @param string $key
     * @return int
     */
    protected function getNextFromDatabase(string $key): int
    {
        try {
            return DB::transaction(function () use ($key) {
                $record = DB::table('smpp_sequence_ids')
                    ->where('daemon_id', $this->daemonId)
                    ->lockForUpdate()
                    ->first();

                if (!$record) {
                    // Create new record
                    DB::table('smpp_sequence_ids')->insert([
                        'daemon_id' => $this->daemonId,
                        'current_id' => $this->minSeqId,
                        'min_id' => $this->minSeqId,
                        'max_id' => $this->maxSeqId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return $this->minSeqId;
                }

                $nextId = $record->current_id + 1;

                // Check if we need to wrap around
                if ($nextId > $this->maxSeqId) {
                    $nextId = $this->minSeqId;
                    Log::info("SequenceIdManager: Wrapped around for daemon {$this->daemonId}");
                }

                DB::table('smpp_sequence_ids')
                    ->where('daemon_id', $this->daemonId)
                    ->update([
                        'current_id' => $nextId,
                        'updated_at' => now(),
                    ]);

                return $nextId;
            });

        } catch (\Exception $e) {
            Log::error("SequenceIdManager Database error: " . $e->getMessage());
            return $this->getFallbackId();
        }
    }

    /**
     * Get next sequence ID from file
     *
     * @param string $key
     * @return int
     */
    protected function getNextFromFile(string $key): int
    {
        try {
            $filePath = storage_path("app/smpp_seq/{$this->daemonId}.seq");
            $directory = dirname($filePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $handle = fopen($filePath, 'c+');
            if (!$handle) {
                throw new \Exception("Cannot open sequence file");
            }

            // Lock the file
            flock($handle, LOCK_EX);

            $content = fread($handle, 100);
            $current = (int) trim($content);

            if ($current < $this->minSeqId || $current >= $this->maxSeqId) {
                $current = $this->minSeqId;
            }

            $next = $current + 1;
            if ($next > $this->maxSeqId) {
                $next = $this->minSeqId;
                Log::info("SequenceIdManager: Wrapped around for daemon {$this->daemonId}");
            }

            // Write new value
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $next);

            // Unlock and close
            flock($handle, LOCK_UN);
            fclose($handle);

            return $next;

        } catch (\Exception $e) {
            Log::error("SequenceIdManager File error: " . $e->getMessage());
            return $this->getFallbackId();
        }
    }

    /**
     * Get fallback ID based on time (within range)
     *
     * @return int
     */
    protected function getFallbackId(): int
    {
        $range = $this->maxSeqId - $this->minSeqId;
        $offset = microtime(true) * 1000 % $range;
        return (int) ($this->minSeqId + $offset);
    }

    /**
     * Get the storage key for this daemon
     *
     * @return string
     */
    protected function getStorageKey(): string
    {
        return $this->keyPrefix . $this->daemonId;
    }

    /**
     * Set the sequence ID range for this daemon
     *
     * @param int $min
     * @param int $max
     * @return self
     */
    public function setRange(int $min, int $max): self
    {
        $this->minSeqId = $min;
        $this->maxSeqId = $max;
        return $this;
    }

    /**
     * Get the current range
     *
     * @return array
     */
    public function getRange(): array
    {
        return [$this->minSeqId, $this->maxSeqId];
    }

    /**
     * Get daemon ID
     *
     * @return string
     */
    public function getDaemonId(): string
    {
        return $this->daemonId;
    }

    /**
     * Create a SequenceIdManager for a specific provider/daemon configuration
     *
     * @param string $provider Provider name (vonage_global, vonage_direct, sinch, etc.)
     * @param string $daemonId Daemon identifier (a0, b0, a3, etc.)
     * @return self
     */
    public static function forDaemon(string $provider, string $daemonId): self
    {
        $configKey = "smpp.connections.{$provider}.credentials.{$daemonId}.seq_id_range";
        $range = config($configKey);

        if (!$range) {
            // Try sinch config
            $configKey = "smpp.sinch.credentials.{$daemonId}.seq_id_range";
            $range = config($configKey);
        }

        return new self($daemonId, $range);
    }
}
