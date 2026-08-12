<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * smsg_receipt_buffer_new — the DLR staging table for the NEW-system, table-based DLR flow
 * (no RabbitMQ). Kept separate from the legacy `smsg_receipt_buffer` so the two systems don't
 * share a buffer. Mirrors the daemon_dreceipt_inbound_buffer.php schema.
 *
 * Flow:
 *   1. SMPP DLR receiver INSERTs each DLR here as JSON in XMLDATA, status='new'  (fast).
 *   2. `dlr:process-buffer` reads status='new', locks 'doing', matches the indexed
 *      onesixty_suppliermsgref, updates smsg_log, then DELETEs the row.
 *
 * The `status` index is essential — the processor selects WHERE status='new' every cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('smsg_receipt_buffer_new')) {
            return; // already exists
        }

        Schema::create('smsg_receipt_buffer_new', function (Blueprint $table) {
            $table->increments('id');
            // Raw DLR payload (JSON). Column name kept for parity with the old daemon.
            $table->text('XMLDATA');
            // new = awaiting processing, doing = claimed by the daemon, done = processed (kept)
            $table->enum('status', ['new', 'doing', 'done'])->default('new');
            // Insert time in YYYYMMDDHHMM (12 chars), like the old system.
            $table->string('processtime', 12);

            // The daemon polls WHERE status='new' — index it (and processtime for age sweeps).
            $table->index('status', 'idx_receipt_buffer_new_status');
            $table->index('processtime', 'idx_receipt_buffer_new_processtime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smsg_receipt_buffer_new');
    }
};
