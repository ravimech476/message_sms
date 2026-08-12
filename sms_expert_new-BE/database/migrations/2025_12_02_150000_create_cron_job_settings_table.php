<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cron_job_settings', function (Blueprint $table) {
            $table->id();
            $table->string('command')->unique();
            $table->string('name');
            $table->string('schedule');
            $table->boolean('enabled')->default(false);
            $table->text('description')->nullable();
            $table->timestamp('last_toggled_at')->nullable();
            $table->string('toggled_by')->nullable();
            $table->timestamps();
        });

        // Insert default cron jobs
        $cronJobs = [
            ['command' => 'sms:process-scheduled', 'name' => 'Process Scheduled SMS', 'schedule' => 'Every minute', 'enabled' => false],
            ['command' => 'emails:send-schedule', 'name' => 'Send Scheduled Emails', 'schedule' => 'Every minute', 'enabled' => false],
            ['command' => 'sms:update-pricing', 'name' => 'SMS Update Pricing', 'schedule' => 'Daily (00:05)', 'enabled' => false],
            ['command' => 'wallet:send-reminders', 'name' => 'Wallet Send Reminders', 'schedule' => 'Daily (06:15)', 'enabled' => false],
            ['command' => 'delivery-receipt:push', 'name' => 'Delivery Receipt Push', 'schedule' => 'Every 5 minutes', 'enabled' => false],
            ['command' => 'virtualnumbers:sync', 'name' => 'Virtual Numbers Sync', 'schedule' => 'Daily (02:30)', 'enabled' => false],
            ['command' => 'nexmo:fetch-delivery-reports', 'name' => 'Fetch Nexmo Delivery Reports', 'schedule' => 'Every minute', 'enabled' => true],
            ['command' => 'nexmo:fetch-delivery-reports-daily', 'name' => 'Daily DLR Catch-up (24h)', 'schedule' => 'Daily (02:00 UK)', 'enabled' => true],
            ['command' => 'db:tidy', 'name' => 'Database Tidy', 'schedule' => 'Daily (02:30)', 'enabled' => false],
            ['command' => 'sms:xml-gateway', 'name' => 'XML to SMS Gateway', 'schedule' => 'Every minute', 'enabled' => false],
        ];

        foreach ($cronJobs as $job) {
            \DB::table('cron_job_settings')->insert(array_merge($job, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_job_settings');
    }
};
