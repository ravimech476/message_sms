<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ServerSetting;
use App\Models\CampaignFileMigration;
use App\Models\SmsgCampaign;
use App\Services\ServerConnectionService;
use App\Services\Queue\CampaignQueueService;

class ConsumeCampaignFileMigrationCommand extends Command
{
    protected $signature = 'rabbitmq:consume-campaign-file-migration
                            {--timeout=60 : Timeout in seconds}
                            {--max-messages=0 : Max messages to process (0 = unlimited)}';

    protected $description = 'Consume campaign file migration jobs from RabbitMQ queue';

    private $connection;
    private $channel;
    private $queueName = 'campaign.file.migration';
    private $messagesProcessed = 0;
    private $maxMessages = 0;
    private $serverConnectionService;

    public function handle()
    {
        $timeout = (int) $this->option('timeout');
        $this->maxMessages = (int) $this->option('max-messages');
        $this->serverConnectionService = new ServerConnectionService();

        $qlog = \App\Services\Logging\RabbitMQLogService::for($this->queueName);
        $qlog->info('Consumer started', ['timeout' => $timeout]);

        try {
            $this->connectToRabbitMQ();

            list($queue, $messageCount, $consumerCount) = $this->channel->queue_declare(
                $this->queueName,
                true,
                false,
                false,
                false
            );

            $this->info("Connected to RabbitMQ. Queue has {$messageCount} messages.");
            $this->info('Press Ctrl+C to exit');

            $callback = function ($msg) {
                $this->processMessage($msg);
                $this->messagesProcessed++;

                if ($this->maxMessages > 0 && $this->messagesProcessed >= $this->maxMessages) {
                    $this->info("Processed {$this->maxMessages} messages. Exiting...");
                    $this->channel->basic_cancel($msg->delivery_info['consumer_tag']);
                }
            };

            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,
                false,
                false,
                false,
                $callback
            );

            while ($this->channel->is_consuming()) {
                try {
                    $this->channel->wait(null, false, $timeout);
                } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                    $this->info("No messages received for {$timeout} seconds. Still listening...");
                    continue;
                }
            }

            $this->info("Total messages processed: {$this->messagesProcessed}");

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Campaign file migration consumer error', ['error' => $e->getMessage()]);
            $qlog->error('Consumer fatal error', ['error' => $e->getMessage(), 'error_at' => $e->getFile().':'.$e->getLine()]);
            return 1;
        } finally {
            $this->cleanup();
        }

        return 0;
    }

    private function connectToRabbitMQ()
    {
        $host = env('RABBITMQ_HOST', '127.0.0.1');
        $port = env('RABBITMQ_PORT', 5672);
        $user = env('RABBITMQ_USER', 'guest');
        $password = env('RABBITMQ_PASSWORD', 'guest');
        $vhost = env('RABBITMQ_VHOST', '/');

        $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        $this->channel = $this->connection->channel();

        $this->channel->queue_declare(
            $this->queueName,
            false,
            true,
            false,
            false
        );
    }

    private function processMessage($msg)
    {
        try {
            $data = json_decode($msg->body, true);

            if (!$data) {
                throw new \Exception('Invalid message format');
            }

            $type = $data['type'] ?? '';

            switch ($type) {
                case 'batch_migration':
                    $this->processBatchMigration($data);
                    break;

                case 'file_migration':
                    $this->processFileMigration($data);
                    break;

                default:
                    $this->warn("Unknown message type: {$type}");
            }

            $msg->ack();
            \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                ->info('ACK — campaign message processed', ['type' => $type ?? null]);

        } catch (\Exception $e) {
            $this->error('Failed to process message: ' . $e->getMessage());
            Log::error('Campaign file migration processing error', [
                'error' => $e->getMessage(),
                'message' => $msg->body,
            ]);
            \App\Services\Logging\RabbitMQLogService::for($this->queueName)
                ->error('NACK (requeue=true) — processing exception', [
                    'error'    => $e->getMessage(),
                    'error_at' => $e->getFile().':'.$e->getLine(),
                ]);
            $msg->nack(true);
        }
    }

    private function processBatchMigration(array $data)
    {
        $batchId = $data['batch_id'];
        $direction = $data['direction'];
        $userBigids = $data['user_bigids'];

        $this->info("Processing batch migration: {$batchId}");
        $this->info("Direction: {$direction}, Users: " . count($userBigids));

        // Get server settings
        if ($direction === 'old_to_new') {
            $sourceServer = ServerSetting::getOldServer();
            $destPath = CampaignQueueService::getCampaignDirectory();
        } else {
            $sourceServer = ServerSetting::getNewServer();
            $destPath = null; // Will be set from server settings
        }

        if (!$sourceServer) {
            $this->error("Source server settings not found for direction: {$direction}");
            return;
        }

        // Connect to source server
        if (!$this->serverConnectionService->connect($sourceServer)) {
            $this->error("Failed to connect to source server");
            return;
        }

        // Get all campaign files for these users
        foreach ($userBigids as $userBigid) {
            $this->procesUserCampaignFiles($batchId, $direction, $userBigid, $sourceServer, $destPath);
        }

        $this->serverConnectionService->disconnect();

        // Log completion
        $stats = CampaignFileMigration::getBatchStats($batchId);
        $this->info("Batch {$batchId} completed: " . json_encode($stats));

        Log::info('Campaign file migration batch completed', [
            'batch_id' => $batchId,
            'stats' => $stats,
        ]);
    }

    private function procesUserCampaignFiles(string $batchId, string $direction, string $userBigid, ServerSetting $sourceServer, ?string $destPath)
    {
        $this->info("Processing files for user: {$userBigid}");

        // Get campaign files for this user from database
        $campaigns = SmsgCampaign::where('userref', $userBigid)
            ->whereNotNull('filename')
            ->where('filename', '!=', '')
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info("No campaign files found for user: {$userBigid}");
            return;
        }

        $sourcePath = rtrim($sourceServer->campaign_file_path, '/');

        foreach ($campaigns as $campaign) {
            $filename = $campaign->filename;
            $sourceFilePath = "{$sourcePath}/{$filename}";

            // Check if file exists on source
            if (!$this->serverConnectionService->fileExists($sourceFilePath)) {
                // Create migration record as skipped
                CampaignFileMigration::create([
                    'migration_batch_id' => $batchId,
                    'user_bigid' => $userBigid,
                    'direction' => $direction,
                    'filename' => $filename,
                    'source_path' => $sourceFilePath,
                    'status' => 'skipped',
                    'status_message' => 'Source file does not exist',
                    'completed_at' => now(),
                ]);
                continue;
            }

            // Determine destination path
            if ($direction === 'old_to_new') {
                $destFilePath = CampaignQueueService::getCampaignFilePath($filename);
            } else {
                $destServer = ServerSetting::getOldServer();
                $destFilePath = rtrim($destServer->campaign_file_path ?? '', '/') . '/' . $filename;
            }

            // Create migration record
            $migration = CampaignFileMigration::create([
                'migration_batch_id' => $batchId,
                'user_bigid' => $userBigid,
                'direction' => $direction,
                'filename' => $filename,
                'source_path' => $sourceFilePath,
                'destination_path' => $destFilePath,
                'status' => 'pending',
            ]);

            // Process the file
            $this->migrateFile($migration, $direction);
        }
    }

    private function processFileMigration(array $data)
    {
        $migrationId = $data['migration_id'];
        $direction = $data['direction'];

        $migration = CampaignFileMigration::find($migrationId);

        if (!$migration) {
            $this->error("Migration record not found: {$migrationId}");
            return;
        }

        $this->migrateFile($migration, $direction);
    }

    private function migrateFile(CampaignFileMigration $migration, string $direction)
    {
        $migration->markAsProcessing();

        $this->info("Migrating file: {$migration->filename}");

        try {
            if ($direction === 'old_to_new') {
                $this->migrateOldToNew($migration);
            } else {
                $this->migrateNewToOld($migration);
            }

        } catch (\Exception $e) {
            $migration->markAsFailed($e->getMessage());
            $this->error("Failed to migrate {$migration->filename}: " . $e->getMessage());
        }
    }

    private function migrateOldToNew(CampaignFileMigration $migration)
    {
        $destPath = $migration->destination_path;

        // Check if destination file already exists
        if (file_exists($destPath)) {
            $migration->markAsSkipped('File already exists at destination');
            $this->info("Skipped {$migration->filename}: already exists");
            return;
        }

        // Download file from old server
        $tempFile = sys_get_temp_dir() . '/' . $migration->filename;

        if (!$this->serverConnectionService->downloadFile($migration->source_path, $tempFile)) {
            throw new \Exception('Failed to download file from source server');
        }

        // Ensure destination directory exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Move to destination
        if (!copy($tempFile, $destPath)) {
            unlink($tempFile);
            throw new \Exception('Failed to copy file to destination');
        }

        $fileSize = filesize($destPath);
        unlink($tempFile);

        $migration->markAsCompleted('File migrated successfully', $fileSize);
        $this->info("Migrated {$migration->filename} ({$fileSize} bytes)");
    }

    private function migrateNewToOld(CampaignFileMigration $migration)
    {
        $sourcePath = CampaignQueueService::getCampaignFilePath($migration->filename);

        // Check if source file exists locally
        if (!file_exists($sourcePath)) {
            $migration->markAsSkipped('Source file does not exist locally');
            $this->info("Skipped {$migration->filename}: source not found");
            return;
        }

        // Connect to old server for upload
        $oldServer = ServerSetting::getOldServer();
        if (!$oldServer) {
            throw new \Exception('Old server settings not configured');
        }

        $uploadService = new ServerConnectionService();
        if (!$uploadService->connect($oldServer)) {
            throw new \Exception('Failed to connect to old server');
        }

        // Check if file already exists on old server
        if ($uploadService->fileExists($migration->destination_path)) {
            $uploadService->disconnect();
            $migration->markAsSkipped('File already exists on old server');
            $this->info("Skipped {$migration->filename}: already exists on old server");
            return;
        }

        // Upload file
        if (!$uploadService->uploadFile($sourcePath, $migration->destination_path)) {
            $uploadService->disconnect();
            throw new \Exception('Failed to upload file to old server');
        }

        $fileSize = filesize($sourcePath);
        $uploadService->disconnect();

        $migration->markAsCompleted('File migrated successfully', $fileSize);
        $this->info("Migrated {$migration->filename} to old server ({$fileSize} bytes)");
    }

    private function cleanup()
    {
        try {
            $this->serverConnectionService->disconnect();

            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            Log::error('Error during cleanup: ' . $e->getMessage());
        }
    }
}
