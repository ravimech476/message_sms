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
        Schema::table('contract_signatures', function (Blueprint $table) {
            // Add signature_image column if it doesn't exist
            if (!Schema::hasColumn('contract_signatures', 'signature_image')) {
                $table->string('signature_image')->nullable()->after('signature_data');
            }
            
            // Add signed_via column if it doesn't exist
            if (!Schema::hasColumn('contract_signatures', 'signed_via')) {
                $table->string('signed_via')->default('web')->after('user_agent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_signatures', function (Blueprint $table) {
            if (Schema::hasColumn('contract_signatures', 'signature_image')) {
                $table->dropColumn('signature_image');
            }
            if (Schema::hasColumn('contract_signatures', 'signed_via')) {
                $table->dropColumn('signed_via');
            }
        });
    }
};
