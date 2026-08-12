<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable all cron jobs by default.
     *
     * The original cron-seeding migrations ran with enabled = true, so existing
     * installs already have every cron job switched on. This migration turns them
     * all off so cron jobs are disabled by default and must be explicitly enabled
     * by an admin.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cron_job_settings')) {
            return;
        }

        DB::table('cron_job_settings')->update([
            'enabled' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * No reverse: we intentionally do not re-enable cron jobs on rollback,
     * since enabling them is an explicit admin decision.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
