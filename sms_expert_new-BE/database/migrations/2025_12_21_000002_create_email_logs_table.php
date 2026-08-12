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
        if (Schema::hasTable('email_logs')) {
            return;
        }

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to', 255);
            $table->string('subject', 500);
            $table->text('message');
            $table->string('sent_at', 20)->nullable()->comment('Format: YmdHis for scheduled time');
            $table->enum('status', ['scheduled', 'sent', 'failed'])->default('scheduled');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            // Indexes for performance
            $table->index('status');
            $table->index('sent_at');
            $table->index(['status', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
