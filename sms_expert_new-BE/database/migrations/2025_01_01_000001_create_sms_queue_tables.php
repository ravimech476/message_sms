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
        // SMS Queue table for managing queued messages
        Schema::create('sms_queue', function (Blueprint $table) {
            $table->id();
            $table->string('queue_id', 64)->unique();
            $table->string('user_ref', 64)->index();
            $table->string('mobile_number', 20);
            $table->text('message');
            $table->string('sender_id', 20)->default('MYBRANDNAME');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'retry'])->default('pending')->index();
            $table->integer('retry_count')->default(0);
            $table->integer('priority')->default(5); // 1-10, 1 being highest
            $table->string('smpp_host')->nullable();
            $table->string('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->decimal('cost', 10, 6)->default(0);
            $table->longText('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'priority', 'created_at']);
            $table->index(['user_ref', 'status']);
        });

        // DLR (Delivery Receipt) table
        Schema::create('sms_dlr', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->unique();
            $table->string('queue_id', 64)->index();
            $table->string('mobile_number', 20);
            $table->enum('status', [
                'DELIVRD', 'EXPIRED', 'DELETED', 'UNDELIV', 
                'ACCEPTD', 'UNKNOWN', 'REJECTD', 'SKIPPED'
            ])->index();
            $table->string('status_text')->nullable();
            $table->integer('error_code')->nullable();
            $table->timestamp('submit_date')->nullable();
            $table->timestamp('done_date')->nullable();
            $table->string('network_code')->nullable();
            $table->decimal('charge', 10, 6)->default(0);
            $table->longText('raw_dlr')->nullable();
            $table->timestamps();
            
            $table->index(['created_at']);
        });

        // SMPP Connection Status table
        Schema::create('smpp_connections', function (Blueprint $table) {
            $table->id();
            $table->string('host', 100);
            $table->integer('port');
            $table->enum('status', ['connected', 'disconnected', 'error', 'binding'])->default('disconnected');
            $table->string('system_id', 50);
            $table->integer('messages_sent')->default(0);
            $table->integer('messages_failed')->default(0);
            $table->integer('current_tps')->default(0);
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->text('error_message')->nullable();
            $table->longText('statistics')->nullable();
            $table->timestamps();
            
            $table->unique(['host', 'port']);
            $table->index('status');
        });

        // Queue Statistics table
        Schema::create('queue_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->integer('hour')->default(0);
            $table->string('queue_name', 50);
            $table->integer('messages_queued')->default(0);
            $table->integer('messages_processed')->default(0);
            $table->integer('messages_failed')->default(0);
            $table->integer('messages_retried')->default(0);
            $table->integer('queue_depth')->default(0);
            $table->float('avg_processing_time')->default(0);
            $table->float('success_rate')->default(0);
            $table->timestamps();
            
            $table->unique(['date', 'hour', 'queue_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_statistics');
        Schema::dropIfExists('smpp_connections');
        Schema::dropIfExists('sms_dlr');
        Schema::dropIfExists('sms_queue');
    }
};
