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
        Schema::table('country', function (Blueprint $table) {
            // Add new columns for EUR, GBP, and exchange rate (4 decimal points)
            $table->decimal('cost_price_eur', 10, 4)->nullable()->after('cost_per_sms')->comment('SMS cost in EUR from Nexmo');
            $table->decimal('cost_price_gbp', 10, 4)->nullable()->after('cost_price_eur')->comment('SMS cost in GBP (converted from EUR)');
            $table->decimal('exchange_rate_eur_to_gbp', 10, 4)->nullable()->after('cost_price_gbp')->comment('EUR to GBP exchange rate used for conversion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            $table->dropColumn(['cost_price_eur', 'cost_price_gbp', 'exchange_rate_eur_to_gbp']);
        });
    }
};
