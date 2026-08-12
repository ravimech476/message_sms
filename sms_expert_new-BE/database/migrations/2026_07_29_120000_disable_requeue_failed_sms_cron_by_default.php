<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * Register the nightly "Requeue Failed SMS" cron (sms:requeue-failed) as DISABLED by default.
 *
 * CronJobSetting::isEnabled() treats a MISSING settings row as ENABLED, so to make this cron
 * off-by-default we must explicitly insert a row with enabled = 0. Admins turn it on from the
 * process-monitor tab when they're ready to use it. Idempotent: create-if-missing, and force
 * the initial state to disabled.
 */
return new class extends Migration
{
    private string $command = 'sms:requeue-failed';

    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')->where('command', $this->command)->exists();

        if ($exists) {
            // A row already exists (e.g. auto-synced as enabled): force it disabled for the initial
            // rollout so the cron is off by default.
            DB::table('cron_job_settings')->where('command', $this->command)->update([
                'enabled'    => 0,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('cron_job_settings')->insert([
                'command'    => $this->command,
                'name'       => 'Requeue Failed SMS',
                'schedule'   => 'Daily (23:00)',
                'enabled'    => 0, // DISABLED by default
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Bust the isEnabled() cache so the scheduler sees the disabled state immediately.
        Cache::forget('cron_enabled_' . md5($this->command));
        Cache::forget('cron_settings_all');
    }

    public function down(): void
    {
        if (Schema::hasTable('cron_job_settings')) {
            DB::table('cron_job_settings')->where('command', $this->command)->delete();
            Cache::forget('cron_enabled_' . md5($this->command));
            Cache::forget('cron_settings_all');
        }
    }
};
