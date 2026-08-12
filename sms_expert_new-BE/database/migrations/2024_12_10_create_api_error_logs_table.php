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
        Schema::create('api_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->default('exception'); // exception, response_error
            $table->string('severity', 20)->default('high'); // low, medium, high, critical
            $table->string('method', 10); // GET, POST, PUT, DELETE, etc.
            $table->string('path', 500);
            $table->text('url');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_bigid', 50)->nullable();
            $table->integer('status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('exception_class', 255)->nullable();
            $table->string('exception_file', 500)->nullable();
            $table->integer('exception_line')->nullable();
            $table->longText('request_data')->nullable();
            $table->longText('response_data')->nullable();
            $table->longText('trace')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for faster queries
            $table->index('severity');
            $table->index('status_code');
            $table->index('user_id');
            $table->index('user_bigid');
            $table->index('created_at');
            $table->index(['severity', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_error_logs');
    }
};
