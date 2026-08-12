<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Features Table - OLD SYSTEM Compatible
 *
 * Stores customer-specific feature flags and settings.
 * Based on hardcoded conditions from OLD SYSTEM smssend.inc, cp2_sendsms.inc, sms.mes
 *
 * Features:
 * - utf8_decode: UTF-8 decode for message length checking
 * - priority_queue: High priority daemon for test campaigns
 * - route_override: Allow special route handling
 * - debug_mode: Show debug information
 * - test_mode: Skip actual SMS sending (insert to buffer only)
 * - route_fix: Auto-change route (e.g., 8 -> 7)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_features', function (Blueprint $table) {
            $table->id();
            $table->string('user_bigid', 32)->index()->comment('Customer bigid from users table');
            $table->string('username', 50)->nullable()->index()->comment('Username (alternative to bigid)');
            $table->string('master_username', 50)->nullable()->index()->comment('Master account username (applies to all sub-accounts)');

            // Feature flags
            $table->boolean('utf8_decode')->default(false)->comment('UTF-8 decode for message length checking');
            $table->boolean('priority_queue')->default(false)->comment('High priority daemon for campaigns');
            $table->integer('priority_daemon_id')->nullable()->comment('Specific daemon ID for priority (e.g., 100)');
            $table->string('priority_route', 10)->nullable()->comment('Route that triggers priority (e.g., p)');

            $table->boolean('route_override')->default(false)->comment('Allow special route handling');
            $table->boolean('debug_mode')->default(false)->comment('Show debug information');
            $table->boolean('test_mode')->default(false)->comment('Skip actual SMS sending');

            $table->boolean('route_fix_enabled')->default(false)->comment('Auto-change route');
            $table->string('route_fix_from', 10)->nullable()->comment('Original route to change from');
            $table->string('route_fix_to', 10)->nullable()->comment('New route to change to');
            $table->boolean('route_fix_notify')->default(false)->comment('Send email notification on route fix');
            $table->string('route_fix_notify_email', 255)->nullable()->comment('Email for route fix notifications');

            $table->text('notes')->nullable()->comment('Admin notes about this customer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_bigid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_features');
    }
};
