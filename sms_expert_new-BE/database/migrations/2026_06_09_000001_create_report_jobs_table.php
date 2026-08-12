<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Async report generation jobs. A staff member requests a report (type + date
 * range + name + notification email); a RabbitMQ worker generates the file in
 * the background, then emails a download link. The row also drives the per-page
 * "Report History" list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('admin_bigid')->nullable()->index();   // who requested (users.bigid)
            $table->string('admin_name')->nullable();
            $table->string('email');                              // notification recipient
            $table->string('report_type', 30);                   // postpay | daily_sms | money_transfer
            $table->string('report_name');                       // user-editable, defaults to {type}_{from}-{to}
            $table->date('date_from');
            $table->date('date_to');
            $table->text('customer_ids')->nullable();            // JSON array, optional filter
            $table->string('search')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|processing|ready|failed
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();             // relative to storage/app
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};
