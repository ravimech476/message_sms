<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cost_per_sms — the per-SMS price Vonage returns in the submit_sm_resp (TLV 0x1422),
 * stored as-is at send time. Decimal(10,5) covers Vonage's GBP prices (e.g. 0.03340).
 */
class AddCostPerSmsToMessageUpdates extends Migration
{
    public function up()
    {
        Schema::table('message_updates', function (Blueprint $table) {
            $table->decimal('cost_per_sms', 10, 5)->nullable()->after('supplier_message_id');
        });
    }

    public function down()
    {
        Schema::table('message_updates', function (Blueprint $table) {
            $table->dropColumn('cost_per_sms');
        });
    }
}
