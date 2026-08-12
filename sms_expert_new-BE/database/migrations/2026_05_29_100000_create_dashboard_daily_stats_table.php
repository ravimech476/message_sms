<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pre-computed per-day dashboard aggregates.
     *
     * The admin dashboard used to run heavy live UNION queries across every
     * smsg_log% table on each page load. The `dashboard:build-stats` cron now
     * populates this table once per day, and the dashboard reads only from here.
     *
     * Two metric "bases" are stored so the existing dashboard numbers are
     * reproduced exactly:
     *   - acked_*  : rows where deliverystatus1 = 'acked'   (used by the summary cards)
     *   - ok_*     : rows where sentstatus = 'ok'           (used by the daily/monthly charts)
     */
    public function up(): void
    {
        if (Schema::hasTable('dashboard_daily_stats')) {
            return;
        }

        Schema::create('dashboard_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date');

            // 'acked' basis - summary cards
            $table->unsignedBigInteger('acked_sent_count')->default(0);
            $table->unsignedBigInteger('acked_delivered_count')->default(0);
            $table->decimal('acked_profit', 18, 4)->default(0);
            $table->decimal('acked_costprice', 18, 4)->default(0);
            $table->decimal('acked_userprice', 18, 4)->default(0);

            // 'sentstatus = ok' basis - charts and monthly bar
            $table->unsignedBigInteger('ok_sent_count')->default(0);
            $table->unsignedBigInteger('ok_row_count')->default(0);
            $table->unsignedBigInteger('ok_delivered_count')->default(0);
            $table->decimal('ok_profit', 18, 4)->default(0);
            $table->decimal('ok_costprice', 18, 4)->default(0);
            $table->decimal('ok_userprice', 18, 4)->default(0);

            $table->timestamps();

            $table->unique('stat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_daily_stats');
    }
};
