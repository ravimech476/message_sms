<?php

namespace App\Services;

use App\Models\ServerSetting;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

class ServerConnectionService
{
    protected ?SFTP $connection = null;
    protected ?ServerSetting $serverSetting = null;

    /**
     * Connect to server via SFTP
     */
    public function connect(ServerSetting $serverSetting): bool
    {
        $this->serverSetting = $serverSetting;

        try {
            $this->connection = new SFTP($serverSetting->host, $serverSetting->port);

            $password = $serverSetting->getDecryptedPassword();

            if (!$this->connection->login($serverSetting->username, $password)) {
                throw new \Exception('SFTP login failed');
            }

            return true;

        } catch (\Exception $e) {
            Log::error('SFTP Connection Error', [
                'server_type' => $serverSetting->server_type,
                'host' => $serverSetting->host,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Test connection to server
     */
    public function testConnection(ServerSetting $serverSetting): array
    {
        try {
            $sftp = new SFTP($serverSetting->host, $serverSetting->port, 10); // 10 second timeout

            $password = $serverSetting->getDecryptedPassword();

            if (!$sftp->login($serverSetting->username, $password)) {
                $serverSetting->updateTestStatus(false, 'Authentication failed - check username/password');
                return [
                    'success' => false,
                    'message' => 'Authentication failed - check username/password',
                ];
            }

            // Try to access the campaign file path
            $path = $serverSetting->campaign_file_path;
            if (!empty($path)) {
                $stat = $sftp->stat($path);
                if ($stat === false) {
                    $serverSetting->updateTestStatus(false, "Connected but path '{$path}' does not exist");
                    return [
                        'success' => false,
                        'message' => "Connected but path '{$path}' does not exist",
                    ];
                }
            }

            $serverSetting->updateTestStatus(true, 'Connection successful');
            return [
                'success' => true,
                'message' => 'Connection successful',
                'server_info' => [
                    'pwd' => $sftp->pwd(),
                    'path_exists' => !empty($path),
                ],
            ];

        } catch (\Exception $e) {
            $message = 'Connection failed: ' . $e->getMessage();
            $serverSetting->updateTestStatus(false, $message);
            return [
                'success' => false,
                'message' => $message,
            ];
        }
    }

    /**
     * List files in directory
     */
    public function listFiles(string $path, string $pattern = '*'): array
    {
        if (!$this->connection) {
            return [];
        }

        try {
            $files = $this->connection->nlist($path);
            if ($files === false) {
                return [];
            }

            // Filter out . and ..
            $files = array_filter($files, fn($f) => $f !== '.' && $f !== '..');

            // Apply pattern filter if specified
            if ($pattern !== '*') {
                $files = array_filter($files, function ($file) use ($pattern) {
                    return fnmatch($pattern, $file);
                });
            }

            return array_values($files);

        } catch (\Exception $e) {
            Log::error('SFTP List Files Error', ['path' => $path, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $path): bool
    {
        if (!$this->connection) {
            return false;
        }

        try {
            return $this->connection->stat($path) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file content
     */
    public function getFile(string $remotePath): ?string
    {
        if (!$this->connection) {
            return null;
        }

        try {
            return $this->connection->get($remotePath);
        } catch (\Exception $e) {
            Log::error('SFTP Get File Error', ['path' => $remotePath, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Download file to local path
     */
    public function downloadFile(string $remotePath, string $localPath): bool
    {
        if (!$this->connection) {
            return false;
        }

        try {
            return $this->connection->get($remotePath, $localPath);
        } catch (\Exception $e) {
            Log::error('SFTP Download File Error', [
                'remote' => $remotePath,
                'local' => $localPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Upload file to remote server
     */
    public function uploadFile(string $localPath, string $remotePath): bool
    {
        if (!$this->connection) {
            return false;
        }

        try {
            return $this->connection->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);
        } catch (\Exception $e) {
            Log::error('SFTP Upload File Error', [
                'local' => $localPath,
                'remote' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get file size
     */
    public function getFileSize(string $path): ?int
    {
        if (!$this->connection) {
            return null;
        }

        try {
            $stat = $this->connection->stat($path);
            return $stat ? ($stat['size'] ?? null) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Disconnect from server
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    /**
     * Get connection
     */
    public function getConnection(): ?SFTP
    {
        return $this->connection;
    }
}
