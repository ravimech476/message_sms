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
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type', 20)->index(); // 'customer' or 'admin'
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_ref', 64)->nullable(); // bigid for customers, username for admins
            $table->string('action', 100)->index(); // e.g., 'login', 'send_sms', 'view_page', 'update_profile'
            $table->string('page_url', 500)->nullable();
            $table->string('page_name', 200)->nullable();
            $table->string('http_method', 10)->nullable(); // GET, POST, PUT, DELETE
            $table->text('description')->nullable();
            $table->longText('request_data')->nullable(); // Request parameters (sanitized)
            $table->longText('response_data')->nullable(); // Response summary
            $table->text('queries_executed')->nullable(); // SQL queries executed during the request
            $table->integer('query_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->integer('response_status')->nullable(); // HTTP status code
            $table->integer('execution_time_ms')->nullable(); // Execution time in milliseconds
            $table->text('error_message')->nullable(); // For failed actions
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['user_type', 'user_id', 'created_at']);
            $table->index(['user_type', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
