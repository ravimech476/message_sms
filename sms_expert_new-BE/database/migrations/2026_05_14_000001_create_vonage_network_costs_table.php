<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates vonage_network_costs table for network-wise (carrier-specific) SMS costs
     */
    public function up(): void
    {
        Schema::create('vonage_network_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('country_id')->index()->comment('Reference to country table');
            $table->string('country_code', 5)->index()->comment('ISO 2-letter country code (GB, US, etc.)');
            $table->string('country_name', 100)->comment('Country name for display');
            $table->string('network_code', 20)->nullable()->comment('MCC+MNC network code (e.g., 23410 for O2 UK)');
            $table->string('network_name', 100)->comment('Mobile network/carrier name (O2, Vodafone, etc.)');
            $table->decimal('cost_price_gbp', 10, 6)->default(0)->comment('Cost price in GBP');
            $table->decimal('cost_price_eur', 10, 6)->default(0)->comment('Cost price in EUR');
            $table->decimal('cost_price_usd', 10, 6)->nullable()->comment('Cost price in USD (optional)');
            $table->boolean('is_active')->default(true)->comment('Whether this network cost is active');
            $table->timestamps();

            // Ensure unique combination of country and network
            $table->unique(['country_code', 'network_code'], 'unique_country_network');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vonage_network_costs');
    }
};
