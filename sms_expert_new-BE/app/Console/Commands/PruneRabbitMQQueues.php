<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Delete STALE RabbitMQ queues — ones that are empty AND have no consumers AND
 * have been idle for a while — so unused/orphaned queues don't pile up.
 *
 * Safety layers (a queue is only deleted when ALL hold):
 *   1. NOT in the protected core-queue allowlist (the always-on worker queues).
 *   2. NOT an internal queue (amq.* / starts with the RabbitMQ reserved prefix).
 *   3. messages == 0            (no data would be lost).
 *   4. consumers == 0           (no worker is attached).
 *   5. idle for >= --idle-minutes (a momentarily-empty queue isn't pruned).
 *   6. dry-run by default       (must pass --force to actually delete).
 *   7. the DELETE itself uses if-empty=true & if-unused=true, so RabbitMQ refuses
 *      the delete if the queue became non-empty/used between check and delete.
 *
 * Uses the RabbitMQ Management HTTP API (port 15672) because AMQP cannot enumerate
 * queues. The management plugin must be enabled: `rabbitmq-plugins enable rabbitmq_management`.
 */
class PruneRabbitMQQueues extends Command
{
    protected $signature = 'rabbitmq:prune-queues
                            {--force : Actually delete (default is a dry-run preview)}
                            {--idle-minutes=60 : Only prune queues idle for at least this many minutes}
                            {--vhost= : Override the vhost (default RABBITMQ_VHOST)}';

    protected $description = 'Delete stale RabbitMQ queues (empty + no consumers + idle). Dry-run by default.';

    public function handle(): int
    {
        $host    = env('RABBITMQ_HOST', '127.0.0.1');
        $mgmtPort = (int) env('RABBITMQ_MANAGEMENT_PORT', 15672);
        $user    = env('RABBITMQ_USER', 'guest');
        $pass    = env('RABBITMQ_PASSWORD', 'guest');
        $vhost   = $this->option('vhost') ?: env('RABBITMQ_VHOST', '/');
        $idleMin = max(0, (int) $this->option('idle-minutes'));
        $force   = (bool) $this->option('force');

        $base = "http://{$host}:{$mgmtPort}";
        $vhostEnc = rawurlencode($vhost);

        $this->info("RabbitMQ prune — {$base}  vhost='{$vhost}'  idle>={$idleMin}m  " . ($force ? 'FORCE (will delete)' : 'DRY-RUN'));

        $protected = $this->protectedQueues();

        // 1. List all queues with their message/consumer/idle state.
        try {
            $resp = Http::withBasicAuth($user, $pass)->timeout(15)->get("{$base}/api/queues/{$vhostEnc}");
        } catch (\Throwable $e) {
            $this->error("Cannot reach RabbitMQ Management API at {$base}: {$e->getMessage()}");
            $this->line('Enable it with: rabbitmq-plugins enable rabbitmq_management');
            return self::FAILURE;
        }

        if (!$resp->successful()) {
            $this->error("Management API error {$resp->status()}: " . $resp->body());
            return self::FAILURE;
        }

        $queues = $resp->json();
        if (!is_array($queues)) {
            $this->error('Unexpected response from Management API.');
            return self::FAILURE;
        }

        $now = Carbon::now('UTC');
        $candidates = [];
        $skipped = 0;

        foreach ($queues as $q) {
            $name      = $q['name'] ?? '';
            $messages  = (int) ($q['messages'] ?? 0);
            $consumers = (int) ($q['consumers'] ?? 0);
            $idleSince = $q['idle_since'] ?? null;

            if ($name === '') { continue; }

            // Layer 1 & 2: never touch protected or internal queues.
            if (in_array($name, $protected, true) || str_starts_with($name, 'amq.')) {
                $skipped++;
                continue;
            }

            // Layer 3 & 4: must be empty and unused.
            if ($messages > 0 || $consumers > 0) {
                $skipped++;
                continue;
            }

            // Layer 5: must have been idle long enough.
            $idleMinutes = null;
            if ($idleSince) {
                try {
                    // RabbitMQ reports idle_since in the broker's local time, no tz — compare loosely.
                    $idleMinutes = Carbon::parse($idleSince)->diffInMinutes($now);
                } catch (\Throwable $e) {
                    $idleMinutes = null;
                }
            }
            if ($idleMin > 0 && ($idleMinutes === null || $idleMinutes < $idleMin)) {
                $skipped++;
                continue;
            }

            $candidates[] = [
                'name' => $name,
                'idle_minutes' => $idleMinutes === null ? null : (int) round($idleMinutes),
            ];
        }

        if (empty($candidates)) {
            $this->info("No stale queues to prune. ({$skipped} skipped: protected / in-use / not-empty / not-idle)");
            return self::SUCCESS;
        }

        $this->line('');
        $this->info(count($candidates) . " stale queue(s) found ({$skipped} skipped):");
        foreach ($candidates as $c) {
            $idle = $c['idle_minutes'] === null ? 'unknown' : "{$c['idle_minutes']}m";
            $this->line("  • {$c['name']}  (empty, 0 consumers, idle {$idle})");
        }

        if (!$force) {
            $this->line('');
            $this->warn('DRY-RUN — nothing deleted. Re-run with --force to delete the queues above.');
            return self::SUCCESS;
        }

        // 6 & 7: delete with if-empty / if-unused so RabbitMQ guards the final step.
        $deleted = 0; $failed = 0;
        foreach ($candidates as $c) {
            $name = $c['name'];
            try {
                $del = Http::withBasicAuth($user, $pass)->timeout(15)
                    ->delete("{$base}/api/queues/{$vhostEnc}/" . rawurlencode($name) . '?if-empty=true&if-unused=true');

                if ($del->successful()) {
                    $deleted++;
                    $this->line("  ✓ deleted {$name}");
                    Log::info('rabbitmq:prune-queues deleted stale queue', ['queue' => $name]);
                } else {
                    $failed++;
                    // 400 = became non-empty/used between check and delete — safely skipped by RabbitMQ.
                    $this->warn("  ✗ kept {$name} (RabbitMQ refused: {$del->status()} — not empty/unused anymore)");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ✗ error deleting {$name}: {$e->getMessage()}");
            }
        }

        $this->line('');
        $this->info("Done. Deleted {$deleted}, kept {$failed}, skipped {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * The always-on / core worker queues that must NEVER be pruned, even when
     * momentarily empty with no consumer (e.g. a worker restarting). Resolves the
     * env-overridable names plus the hardcoded literals used across the app.
     */
    private function protectedQueues(): array
    {
        $env = [
            env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
            env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority'),
            env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
            env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound'),
            env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
            env('RABBITMQ_CAMPAIGN_QUEUE', 'campaign.process'),
            env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push'),
            env('RABBITMQ_NEXMO_DELIVERY_QUEUE', 'nexmo.delivery.reports'),
            env('RABBITMQ_PUSH_QUEUE', 'push.notifications'),
            env('RABBITMQ_REPORTS_QUEUE', 'nexmo.delivery.reports'),
            env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr'),
            env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound'),
        ];

        // Every queue this project legitimately uses (declared/published/consumed
        // somewhere in app/). Kept explicit so the prune only ever removes orphan
        // queues from OTHER apps sharing the broker.
        $literals = [
            // SMS (Vonage/Nexmo)
            'sms.outbound', 'sms.priority', 'sms.dlr', 'sms.inbound', 'sms.failed',
            'sms.dead', 'sms.dlx',
            // SMS (Sinch / mBlox)
            'sms.sinch.outbound', 'sms.sinch.priority', 'sms.sinch.dlr', 'sms.sinch.failed',
            // DLR + delivery
            'dlr.callback.push', 'nexmo.delivery.reports', 'nexmo.webhook.update',
            // Campaign
            'campaign.process', 'campaign.file.migration',
            // Webhooks
            'webhook.dlr', 'webhook.inbound',
            // Email
            'emails', 'email.queue', 'email.notifications',
            // Notifications / push
            'notifications', 'notifications.send', 'push.notifications',
            // Reports
            'reports', 'reports.generate',
        ];

        return array_values(array_unique(array_filter(array_merge($env, $literals))));
    }
}
