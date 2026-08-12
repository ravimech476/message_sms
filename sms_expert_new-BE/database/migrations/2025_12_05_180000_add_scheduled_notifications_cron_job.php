<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the scheduled notifications cron job
        DB::table('cron_job_settings')->insertOrIgnore([
            'command' => 'notifications:process-scheduled',
            'name' => 'Process Scheduled Notifications',
            'schedule' => 'Every minute',
            'description' => 'Processes scheduled notifications that are due to be sent (web and email)',
            'enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('cron_job_settings')
            ->where('command', 'notifications:process-scheduled')
            ->delete();
    }
};
