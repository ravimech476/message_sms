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
        // Check if admin_notifications table exists
        if (!Schema::hasTable('admin_notifications')) {
            // Create the table from scratch
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
        } else {
            // Table exists, add missing columns
            Schema::table('admin_notifications', function (Blueprint $table) {
                // Get existing columns
                $columns = Schema::getColumnListing('admin_notifications');
                
                if (!in_array('title', $columns)) {
                    $table->string('title')->nullable()->after('id');
                }
                if (!in_array('message', $columns)) {
                    $table->text('message')->nullable()->after('title');
                }
                if (!in_array('type', $columns)) {
                    $table->string('type')->default('info')->after('message');
                }
                if (!in_array('target_type', $columns)) {
                    $table->string('target_type')->default('all')->after('type');
                }
                if (!in_array('delivery_method', $columns)) {
                    $table->string('delivery_method')->default('web')->after('target_type');
                }
                if (!in_array('requires_acknowledgment', $columns)) {
                    $table->boolean('requires_acknowledgment')->default(false)->after('delivery_method');
                }
                if (!in_array('status', $columns)) {
                    $table->string('status')->default('draft')->after('requires_acknowledgment');
                }
                if (!in_array('scheduled_at', $columns)) {
                    $table->timestamp('scheduled_at')->nullable()->after('status');
                }
                if (!in_array('sent_at', $columns)) {
                    $table->timestamp('sent_at')->nullable()->after('scheduled_at');
                }
                if (!in_array('total_recipients', $columns)) {
                    $table->unsignedInteger('total_recipients')->default(0)->after('sent_at');
                }
                if (!in_array('read_count', $columns)) {
                    $table->unsignedInteger('read_count')->default(0)->after('total_recipients');
                }
                if (!in_array('acknowledged_count', $columns)) {
                    $table->unsignedInteger('acknowledged_count')->default(0)->after('read_count');
                }
                if (!in_array('created_by', $columns)) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('acknowledged_count');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop table on rollback, just remove added columns if needed
    }
};
