<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OLD SYSTEM parity for the payment-method value on invoices.
 *
 * OLD stores BACS payments as invoices.datacashreply = 'bacs' — the exact value the Monthly Sales
 * report classifies on (steve/mynews/toolslib.php:837 → MonthlySalesQuery). The new system had been
 * writing 'BACS Transfer', which the classifier did not recognise, so those payments were
 * mis-reported as PayPal.
 *
 * The write path is now fixed to store 'bacs' (OutstandingInvoiceController). This migration
 * corrects ONLY the EXISTING new-system rows that were written as 'BACS Transfer'. It relabels just
 * that one value to 'bacs'. It does NOT touch OLD-system records (those are already 'bacs'), card /
 * PayPal replies, or anything else — and it never changes amounts, wallets or any financial figure.
 *
 * 'BACS Transfer' was a value ONLY the new system ever wrote, so matching it exactly guarantees old
 * records are left completely untouched. Safe + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        // ONLY the new-system incorrect value 'BACS Transfer' → 'bacs'. Case-insensitive so
        // 'bacs transfer' variants are caught, but OLD 'bacs' (and everything else) is NOT matched.
        DB::table('invoices')
            ->whereRaw("LOWER(datacashreply) = 'bacs transfer'")
            ->update(['datacashreply' => 'bacs']);
    }

    public function down(): void
    {
        // No clean inverse: once 'BACS Transfer' rows become 'bacs' they are indistinguishable from
        // OLD-system 'bacs' rows, and we must never relabel OLD records. So down() is intentionally a
        // no-op — this is a one-way, label-only normalisation.
    }
};
