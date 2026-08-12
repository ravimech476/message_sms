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
        Schema::table('useroption', function (Blueprint $table) {
            $table->boolean('customer_tour_completed')->default(false);
            $table->boolean('campaign_tour_completed')->default(false);
            $table->timestamp('customer_tour_completed_at')->nullable();
            $table->timestamp('campaign_tour_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('useroption', function (Blueprint $table) {
            $table->dropColumn([
                'customer_tour_completed',
                'campaign_tour_completed',
                'customer_tour_completed_at',
                'campaign_tour_completed_at'
            ]);
        });
    }
};
