<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delivery tracking moved onto message_updates, so the smsg_log parity tables are
 * no longer used. Dropped to keep the schema lean (avoids two large new tables in
 * production). Down() is intentionally a no-op — recreate via the old parity work
 * only if you ever need full smsg_log parity again.
 */
class DropSmsgLogTables extends Migration
{
    public function up()
    {
        DB::statement('DROP TABLE IF EXISTS `smsg_receipt_buffer_new`');
        DB::statement('DROP TABLE IF EXISTS `smsg_log`');
    }

    public function down()
    {
        // no-op — see class docblock
    }
}
