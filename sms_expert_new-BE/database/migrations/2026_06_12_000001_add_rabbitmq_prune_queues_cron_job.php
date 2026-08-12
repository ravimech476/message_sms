<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register the RabbitMQ stale-queue prune cron in the admin cron manager
     * (Settings > Monitor > Cron Jobs Management) so it shows up there and can be
     * enabled/disabled and monitored. The `command` MUST be the bare command
     * `rabbitmq:prune-queues` (no args) because routes/console.php gates it with
     * isCronEnabled('rabbitmq:prune-queues').
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        $exists = DB::table('cron_job_settings')
            ->where('command', 'rabbitmq:prune-queues')
            ->exists();

        if (!$exists) {
            DB::table('cron_job_settings')->insert([
                'command'     => 'rabbitmq:prune-queues',
                'name'        => 'Prune Stale RabbitMQ Queues',
                'schedule'    => 'Daily (00:00)',
                'description' => 'Deletes ONLY stale RabbitMQ queues — empty, no consumers, idle >= 180 min — that are NOT in the protected core-queue allowlist. Stops orphan queues from other apps sharing the broker piling up. Runs with --force --idle-minutes=180.',
                'enabled'     => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        DB::table('cron_job_settings')
            ->where('command', 'rabbitmq:prune-queues')
            ->delete();
    }
};
