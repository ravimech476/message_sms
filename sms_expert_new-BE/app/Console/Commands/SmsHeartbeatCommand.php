<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Traits\LogsCronExecution;

class SmsHeartbeatCommand extends Command
{
    use LogsCronExecution;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:heartbeat 
                            {--debug : Enable debug mode}
                            {--hour= : Override current hour (for testing)}
                            {--minute= : Override current minute (for testing)}
                            {--route=l : SMS route to use (l=mBird-Blend, e=CSN-Direct)}
                            {--dry-run : Show what would be done without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'SMS Heartbeat System - Send test SMS, track delivery and manage alerts';

    /**
     * Heartbeat account bigid
     */
    protected string $heartbeatAccountId = '6641b01402fe76dd6656c16bc9c38700';

    /**
     * Network SIM numbers
     */
    protected array $networks = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return $this->executeWithLogging('sms:heartbeat', function () {
            $debug = $this->option('debug');
            $dryRun = $this->option('dry-run');
            $route = $this->option('route') ?? 'l';

            // Get current time or override
            $hour = $this->option('hour') !== null ? (int)$this->option('hour') : (int)now()->format('G');
            $minute = $this->option('minute') !== null ? (int)$this->option('minute') : (int)now()->format('i');

            $this->info("SMS Heartbeat - " . now()->format('Y-m-d H:i:s'));
            $this->info("Hour: {$hour}, Minute: {$minute}, Route: {$route}");

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No SMS will be sent');
            }

            // Initialize network SIM numbers
            $this->initializeNetworks();

            // Check if we should run (original script had hour check >= 99999 which means disabled)
            // For now, we'll make it configurable
            if (!config('heartbeat.enabled', true)) {
                $this->info('Heartbeat is disabled in config');
                return 'Heartbeat disabled';
            }

            // Check hour range (6 AM to 9 PM)
            if ($hour < 6 || $hour > 21) {
                $this->info('Outside operating hours (6 AM - 9 PM)');
                return 'Outside operating hours';
            }

            $actions = [];

            // Monitor at minutes 8, 18, 28, 38, 48, 58
            if (in_array($minute, [8, 18, 28, 38, 48, 58])) {
                $this->monitorHeartbeats($minute, $debug, $dryRun);
                $actions[] = 'monitor';
            }

            // Send at minutes 0, 10, 20, 30, 40, 50
            if (in_array($minute, [0, 10, 20, 30, 40, 50])) {
                $this->sendHeartbeats($minute, $route, $dryRun);
                $actions[] = 'send';
            }

            if (empty($actions)) {
                $this->info("No action at minute {$minute}");
                return "No action at minute {$minute}";
            }

            return 'Actions: ' . implode(', ', $actions);
        });
    }

    /**
     * Initialize network SIM numbers from config or defaults
     */
    protected function initializeNetworks(): void
    {
        $this->networks = [
            'Voda' => config('heartbeat.sims.vodafone', '919003096885'),
            'O2' => config('heartbeat.sims.o2', '919003096885'),
            'EE' => config('heartbeat.sims.ee', '919003096885'),
            'Three' => config('heartbeat.sims.three', '919003096885'),
            'Orange' => config('heartbeat.sims.orange', '919003096885'),
        ];
    }

    /**
     * Monitor heartbeat results
     */
    protected function monitorHeartbeats(int $minute, bool $debug, bool $dryRun): void
    {
        $this->info('--- MONITORING HEARTBEATS ---');

        // Check heartbeats sent 8 minutes ago
        $checkTime = now()->subMinutes(8)->format('YmdHi');

        $successCount = DB::table('heartbeat')
            ->where('smssenttime', 'like', $checkTime . '%')
            ->where('smsstatus', 'ok')
            ->where('dlrstatus', 'ok')
            ->count();

        $this->info("Checking heartbeats from {$checkTime}: {$successCount}/5 successful");

        if ($successCount == 5) {
            $this->info('All heartbeats OK');
            $this->logToFile("Heartbeat (Monitor: OK) - {$checkTime}");
        } else {
            $this->warn('Heartbeat failures detected!');

            // Get detailed status
            $heartbeats = DB::table('heartbeat')
                ->where('smssenttime', 'like', $checkTime . '%')
                ->select(['network', 'smsstatus', 'dlrstatus', 'parts', 'smssenttime', 'smsreceivedtime', 'dlrreceivedtime'])
                ->get();

            $issuesStr = '';
            $clientErrors = 0;
            $clientIssuesTable = [];

            foreach ($heartbeats as $hb) {
                $issuesStr .= "{$hb->network}, {$hb->smsstatus}, {$hb->dlrstatus}, {$hb->parts}, {$hb->smssenttime}, {$hb->smsreceivedtime}, {$hb->dlrreceivedtime}\n";

                $smsStatus = (!empty($hb->smsstatus) && $hb->smsstatus != 'ok') ? $hb->smsstatus : 'ok';
                $dlrStatus = (!empty($hb->dlrstatus) && $hb->dlrstatus != 'ok') ? $hb->dlrstatus : 'ok';

                if ($smsStatus != 'ok') $clientErrors++;
                if ($dlrStatus != 'ok') $clientErrors++;

                $clientIssuesTable[] = [
                    'Network' => $hb->network,
                    'SMS' => $smsStatus,
                    'DLR' => $dlrStatus,
                ];
            }

            $this->table(['Network', 'SMS', 'DLR'], $clientIssuesTable);

            // Send internal alert
            if (!$dryRun) {
                $this->sendInternalAlert($checkTime, $issuesStr, $debug);

                // Send client alert if there are errors
                if ($clientErrors > 0) {
                    $this->sendClientAlert($checkTime, $clientIssuesTable, $debug);
                }
            }

            $this->logToFile("Heartbeat (Monitor: FAIL) - {$checkTime}\n{$issuesStr}");
        }
    }

    /**
     * Send heartbeat SMS messages
     */
    protected function sendHeartbeats(int $minute, string $route, bool $dryRun): void
    {
        $this->info('--- SENDING HEARTBEATS ---');

        // Determine message type based on minute
        $config = $this->getMessageConfig($minute);

        $this->info("Type: {$config['type']}, Parts: {$config['parts']}");

        foreach ($this->networks as $network => $simNumber) {
            // Generate sender ID
            $sender = $config['senderType'] == 'numeric'
                ? '447' . $this->generateRandomString(9, 'nums')
                : $this->generateRandomString(rand(4, 11), 'mixedchars');

            // Generate message
            list($message, $ref) = $this->createMessage($config['parts']);

            $this->line("  {$network}: {$simNumber} (Sender: {$sender})");

            if (!$dryRun) {
                $this->sendAndStore($simNumber, $sender, $message, $route, $network, $ref, $config['parts']);
            }
        }

        $this->info("Sent {$config['parts']}-part messages to " . count($this->networks) . " networks");
    }

    /**
     * Get message configuration based on minute
     */
    protected function getMessageConfig(int $minute): array
    {
        $configs = [
            0 => ['type' => 'Numeric/Single', 'senderType' => 'numeric', 'parts' => 1],
            10 => ['type' => 'Numeric/Double', 'senderType' => 'numeric', 'parts' => 2],
            20 => ['type' => 'Alpha/Single', 'senderType' => 'alpha', 'parts' => 1],
            30 => ['type' => 'Numeric/Single', 'senderType' => 'numeric', 'parts' => 1],
            40 => ['type' => 'Numeric/Long(9)', 'senderType' => 'numeric', 'parts' => 9],
            50 => ['type' => 'Alpha/Single', 'senderType' => 'alpha', 'parts' => 1],
        ];

        return $configs[$minute] ?? $configs[0];
    }

    /**
     * Create a test message with reference
     */
    protected function createMessage(int $parts = 1): array
    {
        $ref = $this->generateRandomString(32, 'charsnums');
        $sentence = '';

        for ($i = 1; $i <= $parts; $i++) {
            $sentence .= "{$ref} £" . rand(10, 99) . " $" . rand(10, 99) . " " . rand(10, 99) . "% _.@\"#&'()*+,/:;<=>?- ";
            $sentence .= $this->generateRandomString(10, 'mixedchars') . " ";
            $sentence .= $this->generateRandomString(10, 'mixedcharsnums') . " ";
            $sentence .= $this->generateRandomString(10, 'nums') . " ";
            $sentence .= $this->generateRandomString(10, 'mixedchars') . " ";
            $sentence .= $this->generateRandomString(10, 'mixedcharsnums') . " ";
            $sentence .= $this->generateRandomString(10, 'nums') . " ";
            $sentence .= $this->generateRandomString(10, 'mixedchars') . " ";
            $sentence .= $this->generateRandomString(10, 'chars');
        }

        return [$sentence, $ref];
    }

    /**
     * Generate random string
     */
    protected function generateRandomString(int $length = 32, string $type = 'all'): string
    {
        $charSets = [
            'chars' => 'abcdefghijklmnopqrstuvwxyz',
            'mixedchars' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'mixedcharsnums' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
            'nums' => '1234567890',
            'charsnums' => 'abcdefghijklmnopqrstuvwxyz1234567890',
        ];

        $chars = $charSets[$type] ?? $charSets['charsnums'];
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $result;
    }

    /**
     * Send SMS and store heartbeat record
     */
    protected function sendAndStore(string $to, string $sender, string $message, string $route, string $network, string $ref, int $parts): void
    {
        try {
            // Send SMS using the system's SMS sending service
            $sendResult = $this->sendSms($to, $sender, $message, $route);

            // Store the heartbeat record
            DB::table('heartbeat')->insert([
                'bigid' => $ref,
                'smsgref' => $sendResult,
                'senderid' => $sender,
                'mobnum' => $to,
                'parts' => $parts,
                'text' => $message,
                'smssenttime' => now()->format('YmdHis'),
                'route' => $route,
                'network' => $network,
            ]);

            $this->logToFile("to:{$to}, senderid:{$sender}, route:{$route}, net:{$network}, ref:{$ref}, sendstat:{$sendResult}");

        } catch (\Exception $e) {
            $this->error("Failed to send to {$network}: " . $e->getMessage());
            Log::error('Heartbeat send failed', [
                'network' => $network,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SMS using the system's SMS service
     */
    protected function sendSms(string $to, string $sender, string $message, string $route): string
    {
        // Use the existing SMS sending infrastructure
        // This should be adapted based on your actual SMS service implementation

        try {
            // Option 1: Use HTTP API
            $response = \Http::asForm()->post(config('services.sms.api_url', 'https://dashboard.smsexpert.co.uk/api/smsg/sms.mes'), [
                'usr' => config('heartbeat.api_user'),
                'pwd' => config('heartbeat.api_password'),
                'from' => $sender,
                'to' => $to,
                'type' => 'text',
                'route' => $route,
                'txt' => $message,
            ]);

            if ($response->successful()) {
                $body = $response->body();
                $lines = explode("\n", $body);
                if (isset($lines[1])) {
                    $parts = explode("|", $lines[1]);
                    return substr($parts[2] ?? '', 0, 32);
                }
            }

            return 'ERROR:' . $response->status();

        } catch (\Exception $e) {
            Log::error('SMS send failed in heartbeat', ['error' => $e->getMessage()]);
            return 'ERROR:' . $e->getMessage();
        }
    }

    /**
     * Send internal alert email
     */
    protected function sendInternalAlert(string $checkTime, string $issues, bool $debug): void
    {
        $subject = "iTagg DirectLite Route Alert (Internal)";
        $content = "Heartbeat (Monitor: FAIL)...\n\nCheck Time: {$checkTime}\n\n{$issues}";

        $recipients = $debug
            ? [config('reports.debug_recipient')]
            : config('heartbeat.internal_alert_recipients', ['anand@nedholdings.com']);

        $this->sendEmail($subject, $content, $recipients);
    }

    /**
     * Send client alert email
     */
    protected function sendClientAlert(string $checkTime, array $issuesTable, bool $debug): void
    {
        $subject = "iTagg DirectLite Route Alert";

        $content = "<b>Heartbeat sent at: {$checkTime}...</b><br><br>";
        $content .= "<table width='90%' border='1' cellpadding='5'>";
        $content .= "<tr><th>Network</th><th>SMS</th><th>DLR</th></tr>";

        foreach ($issuesTable as $row) {
            $content .= "<tr>";
            $content .= "<td><b>{$row['Network']}</b></td>";
            $content .= "<td>{$row['SMS']}</td>";
            $content .= "<td>{$row['DLR']}</td>";
            $content .= "</tr>";
        }

        $content .= "</table>";

        $recipients = $debug
            ? [config('reports.debug_recipient')]
            : config('heartbeat.client_alert_recipients', ['anand@nedholdings.com']);

        $this->sendEmail($subject, $content, $recipients, true);
    }

    /**
     * Send email notification
     */
    protected function sendEmail(string $subject, string $content, array $recipients, bool $isHtml = false): void
    {
        $alertData = [
            'subject' => $subject,
            'title' => $subject,
            'content' => $content,
            'severity' => strpos(strtolower($subject), 'critical') !== false ? 'critical' : 'warning',
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        try {
            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            foreach ($recipients as $recipient) {
                $emailQueueService->queueEmail(
                    'App\\Mail\\SmsHeartbeatAlertMail',
                    trim($recipient),
                    ['alert_data' => $alertData],
                    [],
                    10 // High priority for heartbeat alerts
                );
            }

            $this->info("Alert email queued: {$subject}");
        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
        }
    }

    /**
     * Log to file
     */
    protected function logToFile(string $message): void
    {
        $logPath = storage_path('logs/' . now()->format('Y-m-d') . '/sms-heartbeat.log');
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logPath, now()->format('H:i:s') . " - {$message}\n", FILE_APPEND);
    }
}
