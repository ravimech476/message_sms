<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds Sinch-specific pricing columns to the country table
     */
    public function up(): void
    {
        Schema::table('country', function (Blueprint $table) {
            // Sinch pricing columns (similar to Nexmo but manual only)
            $table->decimal('sinch_cost_price_eur', 10, 4)->nullable()->after('cost_price_gbp')->comment('Sinch cost price in EUR');
            $table->decimal('sinch_cost_price_gbp', 10, 4)->nullable()->after('sinch_cost_price_eur')->comment('Sinch cost price in GBP');
            $table->timestamp('sinch_price_updated_at')->nullable()->after('sinch_cost_price_gbp')->comment('Last Sinch price update time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            $table->dropColumn([
                'sinch_cost_price_eur',
                'sinch_cost_price_gbp',
                'sinch_price_updated_at'
            ]);
        });
    }
};
