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
            if (!Schema::hasColumn('country', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('can_send_mt_sms');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('country', function (Blueprint $table) {
            if (Schema::hasColumn('country', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
