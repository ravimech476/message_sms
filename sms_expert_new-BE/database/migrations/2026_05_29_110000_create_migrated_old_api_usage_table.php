<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks migrated customers (users.migration_flag = 'new') who are still sending
     * SMS through the OLD API. The old system stamps smsg_log.migration_flag = 'old',
     * so a migrated user with recent 'old' rows is still on the old API.
     *
     * Populated daily by `alert:old-api-usage`. The customer dashboard reads this table
     * and shows a "still using old API" banner once per day.
     */
    public function up(): void
    {
        if (Schema::hasTable('migrated_old_api_usage')) {
            return;
        }

        Schema::create('migrated_old_api_usage', function (Blueprint $table) {
            $table->id();
            $table->string('user_bigid', 64);
            $table->dateTime('last_old_api_used_at')->nullable();
            $table->unsignedBigInteger('old_api_count')->default(0);
            $table->date('last_alert_shown_date')->nullable();
            $table->timestamps();

            $table->unique('user_bigid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migrated_old_api_usage');
    }
};
