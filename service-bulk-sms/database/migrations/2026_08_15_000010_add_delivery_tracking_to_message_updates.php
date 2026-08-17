<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery tracking on the existing message_updates table (replaces smsg_log).
 *   supplier_message_id — the SMPP message-id; indexed so incoming DLRs match fast.
 *   delivered_at        — set when the delivery receipt arrives (the "Delivered at" time).
 * Sent time = existing created_at. Status = existing status enum + delivered_at.
 */
class AddDeliveryTrackingToMessageUpdates extends Migration
{
    public function up()
    {
        Schema::table('message_updates', function (Blueprint $table) {
            $table->string('supplier_message_id', 64)->nullable()->after('status_note');
            $table->timestamp('delivered_at')->nullable()->after('supplier_message_id');
            $table->index('supplier_message_id');
        });
    }

    public function down()
    {
        Schema::table('message_updates', function (Blueprint $table) {
            $table->dropIndex(['supplier_message_id']);
            $table->dropColumn(['supplier_message_id', 'delivered_at']);
        });
    }
}
