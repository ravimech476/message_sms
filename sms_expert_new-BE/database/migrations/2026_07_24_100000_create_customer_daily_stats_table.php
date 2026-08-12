<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-customer daily SMS stat rollup for the customer dashboard (/dashboard).
 *
 * smsg_log only retains recent data (older rows are archived/tidied — see the OLD
 * dbtidy.php), and scanning it live per dashboard load is expensive. This table stores
 * one pre-aggregated row per (customer, day) so the dashboard reads a tiny indexed table
 * instead. Populated by `customer:build-daily-stats` (nightly cron + 01:00 catch-up) and
 * pruned to the last 365 days.
 *
 * Card definitions (client spec), all COUNT(*) of smsg_log rows for that user/day:
 *   sent_count      = all rows
 *   delivered_count = deliverystatus2 NOT IN ('pending','')
 *   pending_count   = deliverystatus2 IN ('pending','')
 *   blocklist_count = sentstatus = 'fail'   (the card renamed from "Failed" to "Block List")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_daily_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('users_bigid', 32);
            $table->date('stat_date');
            $table->unsignedBigInteger('sent_count')->default(0);
            $table->unsignedBigInteger('delivered_count')->default(0);
            $table->unsignedBigInteger('pending_count')->default(0);
            $table->unsignedBigInteger('blocklist_count')->default(0);
            $table->timestamps();

            // One row per customer per day; also the upsert key.
            $table->unique(['users_bigid', 'stat_date'], 'uniq_customer_day');
            // Range scans on the dashboard + retention delete by date.
            $table->index('stat_date', 'idx_stat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_daily_stats');
    }
};
