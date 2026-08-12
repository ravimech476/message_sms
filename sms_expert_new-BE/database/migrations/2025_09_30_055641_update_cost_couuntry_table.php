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
            if (!Schema::hasColumn('country', 'cost_price')) {
                $table->decimal('cost_price', 8, 4)->nullable()->after('can_send_mt_sms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            if (Schema::hasColumn('country', 'cost_price')) {
                $table->dropColumn('cost_price');
            }
        });
    }
};
