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
            $table->timestamp('exchange_rate_updated_at')->nullable()->after('exchange_rate_eur_to_gbp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            $table->dropColumn('exchange_rate_updated_at');
        });
    }
};
