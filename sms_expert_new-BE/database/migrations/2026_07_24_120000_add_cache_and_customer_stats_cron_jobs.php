<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the two crons added this session into cron_job_settings so they appear (and can be
 * toggled) on Admin → Settings → Monitor, and so isCronEnabled() lets them run:
 *
 *   cache:rebuild-tables         — nightly rebuild of the no-TTL TableCache reference caches
 *                                  (country / smsg_route / ofcom / useroption). Runs 00:10.
 *   customer:build-daily-stats   — per-customer daily rollup feeding the /dashboard cards.
 *                                  Runs 00:05 (build yesterday) + 01:00 (catch-up + prune).
 *
 * Idempotent (insert-if-missing) — the monitor list reads command names WITHOUT arguments, which
 * is exactly how isCronEnabled() checks them, so a single row per base command is correct.
 */
return new class extends Migration
{
    private array $crons = [
        [
            'command'  => 'cache:rebuild-tables',
            'name'     => 'Reference Cache Rebuild',
            'schedule' => 'Daily (00:10)',
            'enabled'  => 1,
        ],
        [
            'command'  => 'customer:build-daily-stats',
            'name'     => 'Customer Daily Stats (Dashboard)',
            'schedule' => 'Daily (00:05) + catch-up (01:00)',
            'enabled'  => 1,
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        foreach ($this->crons as $cron) {
            $exists = DB::table('cron_job_settings')->where('command', $cron['command'])->exists();
            if (!$exists) {
                DB::table('cron_job_settings')->insert($cron);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_job_settings')) {
            DB::table('cron_job_settings')
                ->whereIn('command', array_column($this->crons, 'command'))
                ->delete();
        }
    }
};
