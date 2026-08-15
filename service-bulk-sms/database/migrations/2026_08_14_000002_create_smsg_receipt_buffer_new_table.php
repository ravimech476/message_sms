<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — the DLR inbox buffer. SMPP receivers do a fast INSERT of each DLR
 * here (XMLDATA = JSON); dlr:process-buffer later matches it to smsg_log.
 * Faithful copy of sms_expert's smsg_receipt_buffer_new.
 */
class CreateSmsgReceiptBufferNewTable extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `smsg_receipt_buffer_new` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `XMLDATA` text NOT NULL,
              `status` enum('new','doing','done') NOT NULL DEFAULT 'new',
              `processtime` varchar(12) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`),
              KEY `idx_receipt_buffer_new_status` (`status`),
              KEY `idx_receipt_buffer_new_processtime` (`processtime`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS `smsg_receipt_buffer_new`");
    }
}
