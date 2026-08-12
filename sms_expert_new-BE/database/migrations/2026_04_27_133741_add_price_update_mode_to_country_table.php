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
            $table->enum('price_update_mode', ['api', 'manual'])->default('api')->after('exchange_rate_eur_to_gbp')
                ->comment('api = fetch from Nexmo API, manual = admin sets price manually');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            $table->dropColumn('price_update_mode');
        });
    }
};
