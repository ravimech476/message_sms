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
        // Create admin_notifications table
        if (!Schema::hasTable('admin_notifications')) {
            Schema::create('admin_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('message');
                $table->enum('type', ['info', 'warning', 'success', 'danger', 'announcement'])->default('info');
                $table->enum('target_type', ['all', 'specific'])->default('all');
                $table->enum('delivery_method', ['web', 'email', 'both'])->default('web');
                $table->boolean('requires_acknowledgment')->default(false);
                $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled'])->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->unsignedInteger('total_recipients')->default(0);
                $table->unsignedInteger('read_count')->default(0);
                $table->unsignedInteger('acknowledged_count')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                
                $table->index('status');
                $table->index('scheduled_at');
                $table->index('created_by');
            });
        }

        // Create notification_recipients table
        if (!Schema::hasTable('notification_recipients')) {
            Schema::create('notification_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('notification_id');
                $table->unsignedBigInteger('user_id');
                $table->string('user_bigid')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('is_acknowledged')->default(false);
                $table->boolean('email_sent')->default(false);
                $table->boolean('web_delivered')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamps();
                
                $table->foreign('notification_id')->references('id')->on('admin_notifications')->onDelete('cascade');
                $table->index(['notification_id', 'user_id']);
                $table->index(['user_id', 'is_read']);
                $table->index(['user_id', 'is_acknowledged']);
                $table->index('user_bigid');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('admin_notifications');
    }
};
