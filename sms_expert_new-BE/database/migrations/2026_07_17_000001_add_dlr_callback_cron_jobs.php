<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Register the three dlr-callback:* scheduled jobs in cron_job_settings so they appear in the
 * admin "Cron Jobs Management" screen and can be toggled. They are defined in
 * routes/console.php (gated by isCronEnabled('dlr-callback:*')) but were never seeded into
 * this table, so the UI didn't list them. Idempotent — skips any that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $crons = [
            [
                'command'     => 'dlr-callback:sweep-stuck',
                'name'        => 'DLR Callback Sweeper',
                'schedule'    => 'Every 5 minutes',
                'enabled'     => true,
                'description' => 'Releases dlr.callback.push rows stuck at status=doing for >10 min '
                    . '(backstop for a consumer killed mid-claim). Mirrors OLD SYSTEM push recovery.',
            ],
            [
                'command'     => 'dlr-callback:watchdog',
                'name'        => 'DLR Callback Watchdog',
                'schedule'    => 'Every minute',
                'enabled'     => true,
                'description' => 'Reads the dlr-callback:consume heartbeat touchfile and alerts if the '
                    . 'consumer is frozen (>120s stale). Mirrors OLD SYSTEM dreceipt push monitor.',
            ],
            [
                'command'     => 'dlr-callback:restart',
                'name'        => 'DLR Callback Restart',
                'schedule'    => 'Daily (02:25 & 03:30)',
                'enabled'     => true,
                'description' => 'Periodically restarts the dlr-callback-consume worker via supervisorctl '
                    . '(Linux only). Mirrors OLD SYSTEM twice-daily pkill; supervisor auto-restarts it.',
            ],
        ];

        foreach ($crons as $c) {
            $exists = DB::table('cron_job_settings')->where('command', $c['command'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('cron_job_settings')->insert(array_merge($c, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('cron_job_settings')
            ->whereIn('command', [
                'dlr-callback:sweep-stuck',
                'dlr-callback:watchdog',
                'dlr-callback:restart',
            ])
            ->delete();
    }
};
