<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SmppClient;
use SocketTransport;

/**
 * Phase 0 smoke test: open ONE SMPP bind to Vonage using the configured account.
 * Proves the sockets extension, network egress, php-smpp, and the credentials all work
 * before the full send/DLR pipeline is built.
 *
 *   docker exec silicon-sms-php php artisan smpp:test-bind
 *   docker exec silicon-sms-php php artisan smpp:test-bind --bank=a0
 */
class SmppTestBind extends Command
{
    protected $signature = 'smpp:test-bind {--bank= : Use a bank from config/smpp_banks.php instead of the single-bind config} {--host= : Override host}';

    protected $description = 'Smoke test: open one SMPP bind (bind_transmitter) to Vonage';

    public function handle()
    {
        // Resolve connection settings — from a bank, or the single-bind config
        $bankKey = $this->option('bank');
        if ($bankKey) {
            $bank = config("smpp_banks.banks.$bankKey");
            if (!$bank) {
                $this->error("Bank '$bankKey' not found in config/smpp_banks.php");
                return 1;
            }
            $host = $bank['host'];
            $port = (int) $bank['port'];
            $sysId = $bank['system_id'];
            $pass = $bank['password'];
            $sysType = $bank['system_type'];
            $label = "bank $bankKey";
        } else {
            $host = config('smpp.host');
            $port = (int) config('smpp.port');
            $sysId = config('smpp.system_id');
            $pass = config('smpp.password');
            $sysType = config('smpp.system_type');
            $label = 'single-bind';
        }

        if ($this->option('host')) {
            $host = $this->option('host');
        }

        if (empty($sysId) || empty($pass)) {
            $this->error('SMPP_SYSTEM_ID / SMPP_PASSWORD are empty in .env.');
            $this->line('Copy them from sms_expert\'s .env first (see the copy command), then re-run.');
            return 1;
        }

        $this->info("[$label] connecting to {$host}:{$port} (system_type={$sysType}) ...");

        try {
            SmppClient::$system_type = (string) $sysType;

            $transport = new SocketTransport([$host], $port);
            $transport->setRecvTimeout(10000);
            $transport->setSendTimeout(10000);
            $transport->open();
            $this->line('  TCP connected — sending bind_transmitter ...');

            $client = new SmppClient($transport);
            $client->bindTransmitter($sysId, $pass);

            $this->info("  ✅ BIND SUCCESS — Vonage accepted the bind as system_id={$sysId}");
            $client->close();
            return 0;
        } catch (\Throwable $e) {
            $this->error('  ❌ BIND FAILED: ' . $e->getMessage());
            $this->line('  (ESME_RBINDFAIL/RINVSYSID/RINVPASWD = wrong credentials; RALYBND = concurrent-bind limit.)');
            return 1;
        }
    }
}
