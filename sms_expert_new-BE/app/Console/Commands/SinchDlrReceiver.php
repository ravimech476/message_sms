<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SMPP\SinchSmppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\SmppLogger;

/**
 * Sinch (mBlox legacy) SMPP DLR receiver — mirrors SmppDlrReceiver but uses
 * SinchSmppService and config/sinch_banks.php.
 *
 * Architecture per bank: each --bank=<key> instance opens one transceiver
 * bind under the same Sinch system_id but with a partitioned seq_id range so
 * DLRs route back to the bind that originally sent the submit_sm. Run 10
 * instances (a..j) via supervisor for parity with the OLD SYSTEM 10-bind
 * Sinch setup.
 *
 * REQUIREMENT: Sinch must have provisioned multi-bind / concurrent-session
 * mode on the account. Without that, every bank past the first will be
 * rejected with ESME_RALYBND.
 */
class SinchDlrReceiver extends Command
{
    protected $signature = 'sinch:dlr-receiver
                            {--bank= : Bank key from config/sinch_banks.php (a..j). Required when SINCH_BANKS_ENABLED=true. Omit for single-bind .env mode.}
                            {--timeout=0 : Timeout in seconds (0 for infinite)}
                            {--reconnect-delay=5 : Delay in seconds before reconnecting}
                            {--max-reconnect-attempts=0 : Maximum reconnection attempts (0 for unlimited)}
                            {--enquire-interval=30 : Enquire link interval in seconds}
                            {--enquire-max-failures=3 : Max enquire link failures before reconnect}';

    protected $description = 'Continuously receive and process DLRs from Sinch SMPP (transceiver bind, one per bank)';

    private ?SinchSmppService $sinchService = null;
    private bool $isRunning = true;
    private ?Carbon $lastActivity = null;
    private ?Carbon $lastEnquireLink = null;
    private int $reconnectDelay = 5;
    private int $timeout = 0;
    private int $enquireInterval = 30;
    private int $processedCount = 0;
    private int $reconnectAttempts = 0;
    private int $maxReconnectAttempts = 0;
    private int $consecutiveErrors = 0;
    private int $maxConsecutiveErrors = 10;
    private int $enquireLinkFailures = 0;
    private int $maxEnquireLinkFailures = 3;
    private ?string $bankKey = null;

    public function handle(): int
    {
        $this->timeout                = (int) $this->option('timeout');
        $this->reconnectDelay         = (int) $this->option('reconnect-delay');
        $this->maxReconnectAttempts   = (int) $this->option('max-reconnect-attempts');
        $this->enquireInterval        = (int) $this->option('enquire-interval');
        $this->maxEnquireLinkFailures = (int) $this->option('enquire-max-failures');
        $this->bankKey                = $this->option('bank') ?: null;

        $label = $this->bankKey ? "bank={$this->bankKey}" : 'single-bind';
        $this->info("Sinch DLR Receiver starting ({$label})...");

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'onPcntlSignal']);
            pcntl_signal(SIGINT, [$this, 'onPcntlSignal']);
        }

        // Top-level reconnect loop — keeps trying until killed
        while ($this->isRunning) {
            try {
                if (!$this->connectAndBind()) {
                    $this->waitBeforeReconnect();
                    continue;
                }

                $this->processLoop();
            } catch (\Throwable $e) {
                $this->error('Sinch DLR loop error: ' . $e->getMessage());
                SmppLogger::sinch()->error('Sinch DLR Receiver loop error', [
                    'bank'  => $this->bankKey,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                \App\Services\SMPP\SmppErrorAlertService::notify(
                    'Sinch DLR receiver crashed',
                    'Sinch DLR receiver hit an unexpected exception in its top-level loop. The worker will auto-reconnect but if this repeats the DLR receive path is degraded.',
                    [
                        'provider' => 'sinch',
                        'bank'     => $this->bankKey ?: '(single)',
                        'error'    => $e->getMessage(),
                    ]
                );
                $this->disconnect();
                $this->waitBeforeReconnect();
            }
        }

        $this->disconnect();
        $this->info("Sinch DLR Receiver stopped. Processed: {$this->processedCount}");
        return 0;
    }

    private function connectAndBind(): bool
    {
        try {
            $this->reconnectAttempts++;

            if ($this->maxReconnectAttempts > 0 && $this->reconnectAttempts > $this->maxReconnectAttempts) {
                $this->error("Max reconnect attempts ({$this->maxReconnectAttempts}) reached. Exiting.");
                $this->isRunning = false;
                return false;
            }

            $this->disconnect();

            $this->sinchService = new SinchSmppService($this->bankKey);

            // SinchSmppService splits connect() (open TCP/TLS) and bind()
            // (send bind_transceiver) — unlike SmppService which does both in
            // connect(). Both calls are required before checkForDlr() will
            // do anything; without bind() the service's $isBound flag stays
            // false and every checkForDlr() tick logs "Not connected/bound".
            if (!$this->sinchService->connect()) {
                $this->warn("Sinch SMPP connect failed (attempt {$this->reconnectAttempts}).");
                $this->sinchService = null;
                return false;
            }

            // Use bind_receiver, not bind_transceiver — mirrors OLD SYSTEM
            // (itagg_daemon_smpp_dlr_multi.php:296). Receiver-mode sessions
            // are dedup'd separately from transceiver sessions by Sinch's
            // gateway, so 10 banks can hold concurrent receiver binds where
            // they couldn't hold concurrent transceiver binds (status 5).
            if (!$this->sinchService->bindReceiver()) {
                $this->warn("Sinch SMPP bind_receiver failed (attempt {$this->reconnectAttempts}). Likely causes: bad SINCH_SMPP_SYSTEM_ID/PASSWORD, or this bank's system_type is already bound elsewhere.");
                try {
                    $this->sinchService->disconnect();
                } catch (\Throwable $e) {
                    // ignore — socket may already be dead
                }
                $this->sinchService = null;
                return false;
            }

            $this->lastActivity    = Carbon::now();
            $this->lastEnquireLink = Carbon::now();
            $this->enquireLinkFailures = 0;
            $this->reconnectAttempts   = 0;

            $bankInfo = $this->bankKey ? " bank={$this->bankKey}" : '';
            $this->info("✓ Sinch SMPP connected and bound{$bankInfo}.");
            return true;
        } catch (Exception $e) {
            $this->error('Sinch connect exception: ' . $e->getMessage());
            SmppLogger::sinch()->error('Sinch DLR connect error', [
                'bank'    => $this->bankKey,
                'attempt' => $this->reconnectAttempts,
                'error'   => $e->getMessage(),
            ]);
            $this->sinchService = null;
            return false;
        }
    }

    private function processLoop(): void
    {
        while ($this->isRunning && $this->sinchService) {
            try {
                // checkForDlr() is the public entry point on SinchSmppService.
                // It does a non-blocking stream_select on the bind socket, reads
                // any available PDUs, dispatches deliver_sm to the queue
                // handler, responds to enquire_link from server, and returns
                // the count of DLRs processed in this tick.
                $dlrCount = $this->sinchService->checkForDlr(1);

                if ($dlrCount > 0) {
                    $this->processedCount += $dlrCount;
                    $this->lastActivity        = Carbon::now();
                    $this->consecutiveErrors   = 0;
                    $this->enquireLinkFailures = 0;
                }

                // Periodic enquire_link (client-initiated keepalive)
                if (Carbon::now()->diffInSeconds($this->lastEnquireLink) >= $this->enquireInterval) {
                    $this->sendEnquireLink();
                }

                // Timeout-based exit
                if ($this->timeout > 0 && Carbon::now()->diffInSeconds($this->lastActivity) >= $this->timeout) {
                    $this->info("Idle timeout ({$this->timeout}s) reached. Exiting loop.");
                    $this->isRunning = false;
                }

                // Don't busy-spin when checkForDlr returns 0
                if ($dlrCount === 0) {
                    usleep(200_000); // 200ms breath
                }
            } catch (\Throwable $e) {
                $this->consecutiveErrors++;
                $this->warn("Sinch read error ({$this->consecutiveErrors}/{$this->maxConsecutiveErrors}): " . $e->getMessage());

                SmppLogger::sinch()->warning('Sinch DLR read error', [
                    'bank'  => $this->bankKey,
                    'error' => $e->getMessage(),
                    'count' => $this->consecutiveErrors,
                ]);

                if ($this->consecutiveErrors >= $this->maxConsecutiveErrors) {
                    $this->error('Too many consecutive errors — reconnecting.');
                    return; // exit processLoop → outer loop reconnects
                }

                usleep(500_000); // 0.5s breath
            }
        }
    }

    private function sendEnquireLink(): void
    {
        try {
            if (!$this->sinchService) {
                return;
            }
            $ok = $this->sinchService->enquireLink();
            $this->lastEnquireLink = Carbon::now();

            if ($ok) {
                $this->enquireLinkFailures = 0;
            } else {
                $this->enquireLinkFailures++;
                $this->warn("enquire_link returned false ({$this->enquireLinkFailures}/{$this->maxEnquireLinkFailures})");
            }

            if ($this->enquireLinkFailures >= $this->maxEnquireLinkFailures) {
                $this->error('enquire_link failed too many times — forcing reconnect.');
                $this->disconnect();
            }
        } catch (Exception $e) {
            $this->enquireLinkFailures++;
            $this->warn("enquire_link threw ({$this->enquireLinkFailures}/{$this->maxEnquireLinkFailures}): " . $e->getMessage());

            if ($this->enquireLinkFailures >= $this->maxEnquireLinkFailures) {
                $this->error('enquire_link failed too many times — forcing reconnect.');
                $this->disconnect();
            }
        }
    }

    private function disconnect(): void
    {
        if ($this->sinchService) {
            try {
                $this->sinchService->disconnect();
            } catch (\Throwable $e) {
                // ignore — destructor-style cleanup; socket may already be dead
            }
            $this->sinchService = null;
        }
    }

    private function waitBeforeReconnect(): void
    {
        for ($i = 0; $i < $this->reconnectDelay && $this->isRunning; $i++) {
            sleep(1);
        }
    }

    /**
     * Handle pcntl signals (SIGTERM / SIGINT) for graceful shutdown.
     * Renamed from handleSignal() to avoid colliding with Symfony Command's
     * native handleSignal(int, int|false): int|false — PHP 8.1+ fatals on
     * signature mismatch. We use pcntl_signal() which accepts any callable.
     */
    public function onPcntlSignal(int $signal): void
    {
        $this->info("\nReceived signal {$signal}, stopping Sinch DLR receiver...");
        $this->isRunning = false;
    }
}
