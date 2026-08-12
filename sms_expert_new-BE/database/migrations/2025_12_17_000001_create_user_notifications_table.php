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
        // Create user notifications table to store notifications for mobile app
        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('user_bigid', 50)->nullable()->index();
                $table->string('title', 255);
                $table->text('message');
                $table->string('type', 50)->default('general'); // wallet, throughput, system, promo, etc.
                $table->string('icon', 50)->nullable(); // Icon name for mobile app
                $table->longText('data')->nullable(); // Additional data as JSON
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->boolean('push_sent')->default(false);
                $table->timestamp('push_sent_at')->nullable();
                $table->string('push_status', 50)->nullable(); // sent, failed, pending
                $table->text('push_error')->nullable();
                $table->timestamps();
                
                // Index for quick lookups
                $table->index(['user_id', 'is_read']);
                $table->index(['user_id', 'created_at']);
                $table->index(['type', 'created_at']);
            });
        }

        // Add push notification queue to RabbitMQ queues list
        // This is handled in RabbitMQService
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
