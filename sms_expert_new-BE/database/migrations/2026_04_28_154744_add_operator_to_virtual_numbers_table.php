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
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->string('operator', 50)->nullable()->after('msisdn')->index();
        });

        // Update existing records based on smsshortcodes.whichoperator
        \DB::statement("
            UPDATE virtual_numbers vn
            LEFT JOIN (
                SELECT number, whichoperator
                FROM smsshortcodes
                WHERE id IN (SELECT MAX(id) FROM smsshortcodes GROUP BY number)
            ) ss ON vn.msisdn = ss.number
            SET vn.operator = CASE
                WHEN ss.whichoperator LIKE 'Nexmo%' THEN 'Nexmo'
                WHEN ss.whichoperator LIKE 'Sinch%' OR ss.whichoperator LIKE 'mBlox%' THEN 'Sinch'
                ELSE 'Nexmo'
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('virtual_numbers', function (Blueprint $table) {
            $table->dropColumn('operator');
        });
    }
};
